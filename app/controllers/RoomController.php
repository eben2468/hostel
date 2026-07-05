<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Scope;
use App\Models\Room;
use App\Models\Hostel;

class RoomController extends Controller
{
    private Room $rooms;

    public function __construct()
    {
        $this->rooms = new Room();
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $q      = trim($_GET['q'] ?? '');
        $hostel = trim($_GET['hostel'] ?? '');
        $type   = trim($_GET['type'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $floor  = trim($_GET['floor'] ?? '');
        $this->view('rooms/index', [
            'pageTitle' => 'Rooms',
            'rooms'     => $this->rooms->filtered($q, $hostel, $type, $status, $floor),
            // Hostel dropdown only for the global super admin (others are locked to one).
            'hostels'   => Scope::isGlobal() ? (new Hostel())->all('name') : null,
            // Floors within scope (narrowed to the chosen hostel when set).
            'floors'    => (new \App\Models\Floor())->options($hostel),
            'filters'   => ['q' => $q, 'hostel' => $hostel, 'type' => $type, 'status' => $status, 'floor' => $floor],
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('rooms/form', [
            'pageTitle' => 'Add Room',
            'room'      => null,
            'hostels'   => $this->hostelOptions(),
        ]);
    }

    /** Hostels a user may attach a room to: all for admin, only their own otherwise. */
    private function hostelOptions(): array
    {
        if (Scope::isGlobal()) {
            return (new Hostel())->all('name');
        }
        $own = (new Hostel())->find(Scope::hostelId());
        return $own ? [$own] : [];
    }

    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $errors = $this->validate(['hostel_id' => 'Hostel', 'room_number' => 'Room number']);
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/rooms/create');
        }
        $data = $this->data();
        $this->guardHostel($data['hostel_id']);
        $roomId = $this->rooms->create($data);
        // Auto-create bed records to match capacity.
        for ($b = 1; $b <= (int) $data['capacity']; $b++) {
            Database::insert("INSERT INTO beds (room_id, bed_number) VALUES (?,?)", [$roomId, 'Bed ' . $b]);
        }
        Audit::log('create', 'rooms', $roomId);
        Session::flash('success', 'Room created with ' . (int)$data['capacity'] . ' bed(s).');
        $this->redirect('/rooms');
    }

    public function edit($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $room = $this->rooms->find($id);
        if (!$room) {
            $this->redirect('/rooms');
        }
        $this->guardHostel((int) $room['hostel_id']);
        $this->view('rooms/form', [
            'pageTitle' => 'Edit Room',
            'room'      => $room,
            'hostels'   => $this->hostelOptions(),
        ]);
    }

    public function update($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $room = $this->rooms->find($id);
        if (!$room) {
            $this->redirect('/rooms');
        }
        $this->guardHostel((int) $room['hostel_id']);
        $this->rooms->update($id, $this->data());
        $this->rooms->syncOccupancy((int) $id);
        Audit::log('update', 'rooms', $id);
        Session::flash('success', 'Room updated.');
        $this->redirect('/rooms');
    }

    public function destroy($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $room = $this->rooms->find($id);
        if (!$room) {
            $this->redirect('/rooms');
        }
        $this->guardHostel((int) $room['hostel_id']);
        $this->rooms->delete($id);
        Audit::log('delete', 'rooms', $id);
        Session::flash('success', 'Room deleted.');
        $this->redirect('/rooms');
    }

    private function data(): array
    {
        $features = $_POST['features'] ?? [];
        // Hostel-bound users may only write rooms in their own hostel.
        $hostelId = Scope::isGlobal() ? (int) $this->input('hostel_id') : (int) Scope::hostelId();
        return [
            'hostel_id'   => $hostelId,
            'room_number' => $this->input('room_number'),
            'room_type'   => $this->input('room_type', 'double'),
            'capacity'    => max(1, (int) $this->input('capacity', 1)),
            'price'       => (float) $this->input('price', 0),
            'features'    => is_array($features) ? implode(',', $features) : $features,
            'status'      => $this->input('status', 'available'),
        ];
    }
}
