<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Scope;
use App\Models\Hostel;
use App\Services\ReportPdf;

class ReportController extends Controller
{
    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        $data = $this->gather();
        $this->view('reports/index', array_merge(['pageTitle' => 'Reports & Analytics'], $data));
    }

    /** Download the financial + occupancy report as a PDF. */
    public function pdf(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        ReportPdf::financial($this->gather(), 'D');
        exit;
    }

    /** Collect all report datasets (scoped to the caller's hostel + academic term). */
    private function gather(): array
    {
        [$ho, $hoB]   = Scope::on('h.id');               // hostels
        [$inv, $invB] = Scope::onStudent('student_id');  // invoices
        [$pay, $payB] = Scope::onStudent('student_id');  // payments
        [$app, $appB] = Scope::on('preferred_hostel_id'); // applications
        [$st, $stB]   = Scope::on('hostel_id');          // students
        // Complaints (NULL-inclusive for general items).
        $cmp = ''; $cmpB = [];
        if (!Scope::isGlobal()) {
            $cmp  = " AND (student_id IN (SELECT id FROM students WHERE hostel_id = ?) OR student_id IS NULL)";
            $cmpB = [Scope::hostelId()];
        }

        // --- Academic term selection -------------------------------------------------
        $termOptions = $this->termOptions();
        $active = Scope::isGlobal() ? ['academic_year' => null, 'semester' => null]
                                    : (new Hostel())->term((int) Scope::hostelId());
        $year = trim($_GET['year'] ?? '');
        $sem  = trim($_GET['sem'] ?? '');
        if ($year === '' || $sem === '') {
            if ($active['academic_year']) {         // hostel admin → their active term
                $year = $active['academic_year']; $sem = $active['semester'];
            } elseif ($termOptions) {               // else → most recent term in data
                $year = $termOptions[0]['academic_year']; $sem = $termOptions[0]['semester'];
            }
        }
        // Term WHERE fragment applied to term-stamped tables; empty = all terms.
        $term = ''; $termB = [];
        if ($year !== '' && $sem !== '') {
            $term = " AND academic_year = ? AND semester = ?";
            $termB = [$year, $sem];
        }

        // Occupancy by hostel — from the selected term's allocations (rooms are
        // reset each term, so we count that term's allocations rather than live rooms).
        if ($term !== '') {
            $occupancy = Database::all(
                "SELECT h.name,
                        COALESCE((SELECT SUM(r.capacity) FROM rooms r WHERE r.hostel_id=h.id),0) AS capacity,
                        (SELECT COUNT(*) FROM allocations a JOIN rooms r ON r.id=a.room_id
                          WHERE r.hostel_id=h.id AND a.academic_year=? AND a.semester=?
                            AND a.status IN ('active','checked_in','checked_out')) AS occupied,
                        COALESCE((SELECT COUNT(*) FROM rooms r WHERE r.hostel_id=h.id),0) AS rooms
                 FROM hostels h WHERE 1{$ho}
                 GROUP BY h.id, h.name ORDER BY h.name",
                array_merge([$year, $sem], $hoB)
            );
        } else {
            $occupancy = Database::all(
                "SELECT h.name, COALESCE(SUM(r.capacity),0) AS capacity,
                        COALESCE(SUM(r.occupied),0) AS occupied, COUNT(r.id) AS rooms
                 FROM hostels h LEFT JOIN rooms r ON r.hostel_id = h.id
                 WHERE 1{$ho} GROUP BY h.id, h.name ORDER BY h.name", $hoB
            );
        }

        // Financial summary (term-filtered).
        $finance = [
            'total_invoiced' => (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE 1{$inv}{$term}", array_merge($invB, $termB)),
            'total_collected'=> (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'{$pay}{$term}", array_merge($payB, $termB)),
            'outstanding'    => (float) Database::scalar("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN ('unpaid','partial'){$inv}{$term}", array_merge($invB, $termB)),
            'refunded'       => (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='refunded'{$pay}{$term}", array_merge($payB, $termB)),
        ];

        // Payment method breakdown (term-filtered).
        $methods = Database::all(
            "SELECT method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
             FROM payments WHERE status='completed'{$pay}{$term} GROUP BY method ORDER BY total DESC",
            array_merge($payB, $termB)
        );

        // Application funnel (term-filtered).
        $applications = Database::all(
            "SELECT status, COUNT(*) AS cnt FROM applications WHERE 1{$app}{$term} GROUP BY status",
            array_merge($appB, $termB)
        );

        // Maintenance / complaint stats and gender split stay all-time.
        $complaints = Database::all(
            "SELECT status, COUNT(*) AS cnt FROM complaints WHERE 1{$cmp} GROUP BY status", $cmpB
        );
        $gender = Database::all("SELECT gender, COUNT(*) AS cnt FROM students WHERE 1{$st} GROUP BY gender", $stB);

        return [
            'occupancy'    => $occupancy,
            'finance'      => $finance,
            'methods'      => $methods,
            'complaints'   => $complaints,
            'applications' => $applications,
            'gender'       => $gender,
            'termOptions'  => $termOptions,
            'term'         => ['year' => $year, 'sem' => $sem],
        ];
    }

    /** Distinct academic terms present in the data, most recent first. */
    private function termOptions(): array
    {
        return Database::all(
            "SELECT academic_year, semester FROM (
                SELECT academic_year, semester FROM allocations WHERE academic_year IS NOT NULL AND semester IS NOT NULL
                UNION SELECT academic_year, semester FROM invoices    WHERE academic_year IS NOT NULL AND semester IS NOT NULL
                UNION SELECT academic_year, semester FROM applications WHERE academic_year IS NOT NULL AND semester IS NOT NULL
             ) t
             GROUP BY academic_year, semester
             ORDER BY academic_year DESC, semester DESC"
        );
    }
}
