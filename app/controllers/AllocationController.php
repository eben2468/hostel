<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Scope;
use App\Models\Allocation;
use App\Models\Room;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\Notify;

class AllocationController extends Controller
{
    private Allocation $alloc;
    private Room $rooms;

    public function __construct()
    {
        $this->alloc = new Allocation();
        $this->rooms = new Room();
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $pager = $this->alloc->paginatedDetailed(\App\Core\Paginator::currentPage());
        $this->view('allocations/index', [
            'pageTitle'   => 'Allocations',
            'allocations' => $pager['rows'],
            'pager'       => $pager,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $preselect = (int) ($_GET['student'] ?? 0);
        // Students without an active allocation. A hostel admin may allocate
        // students already in their hostel, or as-yet unassigned applicants
        // (who get bound to this hostel on allocation); the super admin sees all.
        $studentScope = Scope::isGlobal() ? '' : ' AND (hostel_id = ? OR hostel_id IS NULL)';
        $studentBind  = Scope::isGlobal() ? [] : [Scope::hostelId()];
        $students = Database::all(
            "SELECT * FROM students
             WHERE id NOT IN (SELECT student_id FROM allocations WHERE status IN ('active','checked_in'))
             {$studentScope}
             ORDER BY full_name",
            $studentBind
        );
        $this->view('allocations/form', [
            'pageTitle' => 'Allocate Room',
            'students'  => $students,
            'rooms'     => $this->rooms->available(),
            'preselect' => $preselect,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();

        $studentId = (int) $this->input('student_id');
        $roomId    = (int) $this->input('room_id');
        if (!$studentId || !$roomId) {
            Session::flash('error', 'Please select both a student and a room.');
            $this->redirect('/allocations/create');
        }

        $room = $this->rooms->find($roomId);
        if (!$room || $room['occupied'] >= $room['capacity'] || in_array($room['status'], ['maintenance','closed'], true)) {
            Session::flash('error', 'Selected room is not available.');
            $this->redirect('/allocations/create');
        }
        // A hostel-bound user may only allocate into a room in their own hostel.
        $this->guardHostel((int) $room['hostel_id']);
        if ($this->alloc->activeForStudent($studentId)) {
            Session::flash('error', 'That student already has an active allocation.');
            $this->redirect('/allocations/create');
        }
        // Stamp the allocation + invoice with the hostel's active academic session.
        $term = (new \App\Models\Hostel())->term((int) $room['hostel_id']);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Pick the first free bed in the room, if any.
            $bed = Database::first(
                "SELECT id FROM beds WHERE room_id=? AND status='available' ORDER BY id LIMIT 1",
                [$roomId]
            );
            $bedId = $bed['id'] ?? null;

            $allocId = $this->alloc->create([
                'student_id'    => $studentId,
                'room_id'       => $roomId,
                'bed_id'        => $bedId,
                'academic_year' => $term['academic_year'],
                'semester'      => $term['semester'],
                'allocated_by'  => Auth::id(),
                'status'        => 'active',
                'remarks'       => $this->input('remarks'),
            ]);

            if ($bedId) {
                Database::run("UPDATE beds SET status='occupied', student_id=? WHERE id=?", [$studentId, $bedId]);
            }
            // Bind the student to the hostel they were allocated into. This is how
            // a self-registered (unassigned) student joins a hostel's ecosystem.
            Database::run("UPDATE students SET hostel_id=? WHERE id=?", [(int) $room['hostel_id'], $studentId]);
            $this->rooms->syncOccupancy($roomId);

            // Generate an invoice for the room fee.
            $amount = (float) $room['price'];
            if ($amount > 0) {
                Database::insert(
                    "INSERT INTO invoices (invoice_no, student_id, allocation_id, academic_year, semester, description, amount, balance, due_date, status)
                     VALUES (?,?,?,?,?,?,?,?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'unpaid')",
                    [\App\Models\Invoice::generateNumber(), $studentId, $allocId,
                     $term['academic_year'], $term['semester'],
                     'Accommodation: Room ' . $room['room_number'], $amount, $amount]
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Allocation failed: ' . $e->getMessage());
            $this->redirect('/allocations/create');
        }

        Audit::log('create', 'allocations', $allocId);
        Notify::student($studentId, 'Room allocated',
            'You have been allocated Room ' . $room['room_number'] . '. An invoice has been generated.',
            '/dashboard', 'fa-bed');
        Session::flash('success', 'Room allocated and invoice generated.');
        $this->redirect('/allocations');
    }

    /** Load an allocation and 403 if it belongs to another hostel. */
    private function guardedAllocation($id): ?array
    {
        $a = $this->alloc->find($id);
        if (!$a) {
            return null;
        }
        $room = $this->rooms->find((int) $a['room_id']);
        $this->guardHostel($room ? (int) $room['hostel_id'] : null);
        return $a;
    }

    public function checkin($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'security');
        Csrf::check();
        if (!$this->guardedAllocation($id)) {
            $this->redirect('/allocations');
        }
        Database::run("UPDATE allocations SET status='checked_in', check_in_at=NOW() WHERE id=?", [$id]);
        Audit::log('checkin', 'allocations', $id);
        Session::flash('success', 'Student checked in.');
        $this->redirect('/allocations');
    }

    public function checkout($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'security');
        Csrf::check();
        $a = $this->guardedAllocation($id);
        if ($a) {
            Database::run("UPDATE allocations SET status='checked_out', check_out_at=NOW() WHERE id=?", [$id]);
            if ($a['bed_id']) {
                Database::run("UPDATE beds SET status='available', student_id=NULL WHERE id=?", [$a['bed_id']]);
            }
            $this->rooms->syncOccupancy((int) $a['room_id']);
            Audit::log('checkout', 'allocations', $id);
        }
        Session::flash('success', 'Student checked out; bed released.');
        $this->redirect('/allocations');
    }

    public function cancel($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $a = $this->guardedAllocation($id);
        if ($a) {
            Database::run("UPDATE allocations SET status='cancelled' WHERE id=?", [$id]);
            if ($a['bed_id']) {
                Database::run("UPDATE beds SET status='available', student_id=NULL WHERE id=?", [$a['bed_id']]);
            }
            $this->rooms->syncOccupancy((int) $a['room_id']);
            Audit::log('cancel', 'allocations', $id);
        }
        Session::flash('success', 'Allocation cancelled.');
        $this->redirect('/allocations');
    }
}
