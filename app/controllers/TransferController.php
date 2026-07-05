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

class TransferController extends Controller
{
    private Allocation $alloc;
    private Room $rooms;

    public function __construct()
    {
        $this->alloc = new Allocation();
        $this->rooms = new Room();
    }

    /** History of transfers. */
    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        [$scope, $bind] = Scope::on('r.hostel_id');
        $transfers = Database::all(
            "SELECT a.*, s.full_name, s.student_id AS student_no, r.room_number, h.name AS hostel_name
             FROM allocations a
             JOIN students s ON s.id = a.student_id
             JOIN rooms r ON r.id = a.room_id
             LEFT JOIN hostels h ON h.id = r.hostel_id
             WHERE a.status = 'transferred'{$scope}
             ORDER BY a.created_at DESC",
            $bind
        );
        $this->view('transfers/index', ['pageTitle' => 'Room Transfers', 'transfers' => $transfers]);
    }

    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $preselect = (int) ($_GET['allocation'] ?? 0);
        // Active allocations available to transfer.
        [$scope, $bind] = Scope::on('r.hostel_id');
        $active = Database::all(
            "SELECT a.id, s.full_name, s.student_id AS student_no, r.room_number, h.name AS hostel_name
             FROM allocations a
             JOIN students s ON s.id = a.student_id
             JOIN rooms r ON r.id = a.room_id
             LEFT JOIN hostels h ON h.id = r.hostel_id
             WHERE a.status IN ('active','checked_in'){$scope}
             ORDER BY s.full_name",
            $bind
        );
        $this->view('transfers/form', [
            'pageTitle' => 'Transfer Room',
            'active'    => $active,
            'rooms'     => $this->rooms->available(),
            'preselect' => $preselect,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();

        $allocationId = (int) $this->input('allocation_id');
        $newRoomId    = (int) $this->input('new_room_id');
        $reason       = $this->input('reason', 'Transfer');

        $current = $this->alloc->find($allocationId);
        if (!$current || !in_array($current['status'], ['active', 'checked_in'], true)) {
            Session::flash('error', 'Selected allocation is not active.');
            $this->redirect('/transfers/create');
        }
        // The source allocation must belong to the caller's hostel.
        $currentRoom = $this->rooms->find((int) $current['room_id']);
        $this->guardHostel($currentRoom ? (int) $currentRoom['hostel_id'] : null);

        $newRoom = $this->rooms->find($newRoomId);
        if (!$newRoom || $newRoom['occupied'] >= $newRoom['capacity'] || in_array($newRoom['status'], ['maintenance', 'closed'], true)) {
            Session::flash('error', 'Target room is not available.');
            $this->redirect('/transfers/create');
        }
        // The target room must also be in the caller's hostel (no cross-hostel transfer).
        $this->guardHostel((int) $newRoom['hostel_id']);
        if ((int) $current['room_id'] === $newRoomId) {
            Session::flash('error', 'Student is already in that room.');
            $this->redirect('/transfers/create');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $oldRoomId = (int) $current['room_id'];

            // Release the old bed.
            if ($current['bed_id']) {
                Database::run("UPDATE beds SET status='available', student_id=NULL WHERE id=?", [$current['bed_id']]);
            }
            // Close the old allocation.
            Database::run("UPDATE allocations SET status='transferred' WHERE id=?", [$allocationId]);

            // Assign a free bed in the new room.
            $bed = Database::first("SELECT id FROM beds WHERE room_id=? AND status='available' ORDER BY id LIMIT 1", [$newRoomId]);
            $bedId = $bed['id'] ?? null;

            $newAllocId = $this->alloc->create([
                'student_id'    => (int) $current['student_id'],
                'room_id'       => $newRoomId,
                'bed_id'        => $bedId,
                'academic_year' => $current['academic_year'],
                'semester'      => $current['semester'] ?? null,
                'allocated_by'  => Auth::id(),
                'status'        => $current['status'], // preserve active / checked_in
                'check_in_at'   => $current['check_in_at'],
                'remarks'       => 'Transferred from room ' . $oldRoomId . ': ' . $reason,
            ]);
            if ($bedId) {
                Database::run("UPDATE beds SET status='occupied', student_id=? WHERE id=?", [$current['student_id'], $bedId]);
            }

            $this->rooms->syncOccupancy($oldRoomId);
            $this->rooms->syncOccupancy($newRoomId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Transfer failed: ' . $e->getMessage());
            $this->redirect('/transfers/create');
        }

        Audit::log('transfer', 'allocations', $newAllocId, 'from alloc ' . $allocationId);
        Session::flash('success', 'Student transferred successfully.');
        $this->redirect('/allocations');
    }
}
