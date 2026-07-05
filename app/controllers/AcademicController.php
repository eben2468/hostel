<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Scope;
use App\Core\Database;
use App\Models\Hostel;

/**
 * Academic Session settings for hostel admins: choose the active academic year
 * and semester.
 *
 * Switching sessions is fully reversible. Leaving a term parks its live
 * allocations (status 'suspended') and empties the rooms; entering a term
 * restores whatever was parked under it — the same students, rooms and beds —
 * so moving back and forth between terms leaves each one exactly as it was.
 * Setting a term for the very first time simply adopts the current residents
 * into it (nothing is cleared).
 */
class AcademicController extends Controller
{
    public function index(): void
    {
        $this->requireAuth('hostel_admin');
        $hostelId = (int) Scope::hostelId();
        $hostel   = (new Hostel())->find($hostelId);
        if (!$hostel) {
            Session::flash('error', 'No hostel is linked to your account.');
            $this->redirect('/dashboard');
        }

        // Live occupancy that a rollover would clear (for the warning copy).
        $activeAllocations = (int) Database::scalar(
            "SELECT COUNT(*) FROM allocations a JOIN rooms r ON r.id = a.room_id
             WHERE r.hostel_id = ? AND a.status IN ('active','checked_in')",
            [$hostelId]
        );

        $this->view('academic/index', [
            'pageTitle'         => 'Academic Session',
            'hostel'            => $hostel,
            'years'             => Hostel::yearOptions($hostel['academic_year'] ?? null),
            'activeAllocations' => $activeAllocations,
        ]);
    }

    public function update(): void
    {
        $this->requireAuth('hostel_admin');
        Csrf::check();
        $hostelId = (int) Scope::hostelId();
        $hostel   = (new Hostel())->find($hostelId);
        if (!$hostel) {
            $this->redirect('/dashboard');
        }

        $year = trim($this->input('academic_year', ''));
        $sem  = trim($this->input('semester', ''));
        if ($year === '' || $sem === '') {
            Session::flash('error', 'Please choose both an academic year and a semester.');
            $this->redirect('/academic');
        }

        $curYear = $hostel['academic_year'] ?? '';
        $curSem  = $hostel['semester'] ?? '';
        if ($year === $curYear && $sem === $curSem) {
            Session::flash('info', 'That academic session is already active.');
            $this->redirect('/academic');
        }

        // First time a term is set: adopt the existing residents into it rather
        // than clearing them (there is no previous term to park).
        if ($curYear === '' || $curSem === '') {
            $this->adoptCurrentTerm($hostelId, $year, $sem);
            Audit::log('rollover', 'hostels', $hostelId, "set-term={$year}/{$sem}");
            Session::flash('success', "Session set to {$year} · {$sem} semester. Current residents were kept.");
            $this->redirect('/academic');
        }

        $restored = $this->switchTerm($hostelId, $curYear, $curSem, $year, $sem);

        Audit::log('rollover', 'hostels', $hostelId, "term={$year}/{$sem}");
        Session::flash('success', $restored > 0
            ? "Switched to {$year} · {$sem} semester. {$restored} previous allocation(s) for this session were restored."
            : "Switched to {$year} · {$sem} semester. Rooms are empty, ready for fresh allocation.");
        $this->redirect('/academic');
    }

    /**
     * Stamp the hostel's current live allocations with the given term and make it
     * active. Used the first time a term is chosen, so pre-existing residents
     * become part of that term instead of being wiped.
     */
    private function adoptCurrentTerm(int $hostelId, string $year, string $sem): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                "UPDATE allocations SET academic_year = ?, semester = ?
                 WHERE status IN ('active','checked_in')
                   AND room_id IN (SELECT id FROM rooms WHERE hostel_id = ?)",
                [$year, $sem, $hostelId]
            );
            Database::run(
                "UPDATE hostels SET academic_year = ?, semester = ? WHERE id = ?",
                [$year, $sem, $hostelId]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Could not set the session: ' . $e->getMessage());
            $this->redirect('/academic');
        }
    }

    /**
     * Reversibly move the hostel from one set term to another, in a single
     * transaction:
     *   1. Park the outgoing term's live allocations as 'suspended', stamped with
     *      that outgoing term so they can be found again.
     *   2. Empty every bed and room (bar maintenance/closed).
     *   3. Point the hostel at the incoming term.
     *   4. Restore the incoming term's parked allocations — same students, rooms
     *      and beds — recomputing occupancy. Their exact prior state is recovered
     *      (checked-in when a check-in was recorded, otherwise active).
     *
     * @return int number of allocations restored for the incoming term
     */
    private function switchTerm(int $hostelId, string $fromYear, string $fromSem, string $toYear, string $toSem): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // 1. Park the outgoing term. Remember each allocation's exact live
            //    status in resume_status (evaluated before status is overwritten),
            //    and stamp the outgoing term so a later return can find it.
            Database::run(
                "UPDATE allocations
                    SET resume_status = status, status = 'suspended',
                        academic_year = ?, semester = ?
                  WHERE status IN ('active','checked_in')
                    AND room_id IN (SELECT id FROM rooms WHERE hostel_id = ?)",
                [$fromYear, $fromSem, $hostelId]
            );
            // 2. Empty the hostel's live occupancy.
            Database::run(
                "UPDATE beds SET status='available', student_id=NULL
                 WHERE room_id IN (SELECT id FROM rooms WHERE hostel_id = ?)",
                [$hostelId]
            );
            Database::run(
                "UPDATE rooms SET occupied=0, status='available'
                 WHERE hostel_id = ? AND status NOT IN ('maintenance','closed')",
                [$hostelId]
            );
            // 3. Activate the incoming term.
            Database::run(
                "UPDATE hostels SET academic_year = ?, semester = ? WHERE id = ?",
                [$toYear, $toSem, $hostelId]
            );
            // 4. Restore whatever was parked under the incoming term, returning
            //    each allocation to the exact status it held before it was parked.
            Database::run(
                "UPDATE allocations
                    SET status = COALESCE(resume_status, 'active'), resume_status = NULL
                  WHERE status='suspended' AND academic_year = ? AND semester = ?
                    AND room_id IN (SELECT id FROM rooms WHERE hostel_id = ?)",
                [$toYear, $toSem, $hostelId]
            );
            $restored = (int) Database::scalar(
                "SELECT COUNT(*) FROM allocations
                 WHERE status IN ('active','checked_in') AND academic_year = ? AND semester = ?
                   AND room_id IN (SELECT id FROM rooms WHERE hostel_id = ?)",
                [$toYear, $toSem, $hostelId]
            );
            // Re-seat the beds that the restored allocations hold.
            Database::run(
                "UPDATE beds b
                   JOIN allocations a ON a.bed_id = b.id
                    SET b.status='occupied', b.student_id = a.student_id
                  WHERE a.status IN ('active','checked_in')
                    AND a.academic_year = ? AND a.semester = ?
                    AND b.room_id IN (SELECT id FROM rooms WHERE hostel_id = ?)",
                [$toYear, $toSem, $hostelId]
            );
            // Recompute each room's occupied count and derived status.
            Database::run(
                "UPDATE rooms r
                    SET occupied = (SELECT COUNT(*) FROM allocations a
                                     WHERE a.room_id = r.id AND a.status IN ('active','checked_in'))
                  WHERE r.hostel_id = ?",
                [$hostelId]
            );
            Database::run(
                "UPDATE rooms SET status = CASE
                    WHEN status IN ('maintenance','closed') THEN status
                    WHEN occupied >= capacity THEN 'occupied'
                    ELSE 'available' END
                 WHERE hostel_id = ?",
                [$hostelId]
            );
            $pdo->commit();
            return $restored;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Could not switch session: ' . $e->getMessage());
            $this->redirect('/academic');
        }
    }
}
