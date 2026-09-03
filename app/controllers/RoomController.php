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

    public function importForm(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('rooms/import', [
            'pageTitle' => 'Import Rooms',
            'hostels'   => $this->hostelOptions(),
        ]);
    }

    /**
     * A ready-made room list to download, edit and upload back.
     *
     * Generated rather than shipped as a file, so it is always reachable from
     * the app itself — including on a live server where dropping a spreadsheet
     * next to the code is awkward. Excel opens the CSV directly.
     */
    public function importTemplate(): void
    {
        $this->requireAuth('admin', 'hostel_admin');

        $floors  = ['GF' => 'Ground', 'FF' => 'First', 'SF' => 'Second', 'TF' => 'Top'];
        $perFloor = max(1, min(500, (int) ($_GET['rooms'] ?? 37)));

        $rows = [];
        foreach (array_keys($floors) as $code) {
            for ($i = 1; $i <= $perFloor; $i++) {
                $rows[] = [
                    'room_number' => $code . $i,
                    'floor'       => $code,
                    'room_type'   => 'quad',
                    'capacity'    => 4,
                    'price'       => 0,
                    'status'      => 'available',
                ];
            }
        }
        \App\Core\Csv::download('rooms-template', $rows, [
            'room_number' => 'Room Number', 'floor' => 'Floor', 'room_type' => 'Type',
            'capacity' => 'Capacity', 'price' => 'Price', 'status' => 'Status',
        ]);
    }

    /** Create many rooms at once from an uploaded sheet. */
    public function import(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();

        $hostelId = Scope::isGlobal() ? (int) $this->input('hostel_id') : (int) Scope::hostelId();
        if (!$hostelId) {
            Session::flash('error', 'Please choose the hostel these rooms belong to.');
            $this->redirect('/rooms/import');
        }
        $this->guardHostel($hostelId);

        $file  = $_FILES['file'] ?? null;
        $error = $this->validateUpload($file);
        if ($error !== null) {
            Session::flash('error', $error);
            $this->redirect('/rooms/import');
        }

        $read = \App\Services\SheetReader::read($file['tmp_name'], $file['name']);
        if (!$read['ok']) {
            Session::flash('error', $read['error']);
            $this->redirect('/rooms/import');
        }

        $result = \App\Services\RoomImporter::import(
            $read['rows'], $hostelId, isset($_POST['create_floors'])
        );
        if ($result['error'] !== null) {
            Session::flash('error', $result['error']);
            $this->redirect('/rooms/import');
        }

        Audit::log('import', 'rooms', $hostelId,
            $result['created'] . ' rooms from ' . $file['name']);

        Session::flash('success', sprintf(
            '%d room(s) created with %d bed(s)%s.',
            $result['created'], $result['beds'],
            $result['floors'] ? ', and ' . $result['floors'] . ' floor(s) added' : ''
        ));
        if ($result['skipped']) {
            $shown = array_slice($result['skipped'], 0, 8);
            Session::flash('warning', count($result['skipped']) . ' row(s) skipped: '
                . implode(' ', $shown)
                . (count($result['skipped']) > 8 ? ' …and ' . (count($result['skipped']) - 8) . ' more.' : ''));
        }
        $this->redirect('/rooms');
    }

    /** @return string|null an error message, or null when the upload is usable */
    private function validateUpload(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Please choose a file to upload.';
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Upload failed (error code ' . $file['error'] . ').';
        }
        // CLI has no HTTP upload to verify; the web path always checks.
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($file['tmp_name'])) {
            return 'That file could not be read.';
        }
        if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
            return 'The file must be between 1 byte and 5 MB.';
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xls') {
            return 'Old-style .xls files are not supported. Save the file as .xlsx or .csv and upload that.';
        }
        if (!in_array($ext, \App\Services\SheetReader::EXTENSIONS, true)) {
            return 'Unsupported file type. Upload a .xlsx, .csv, .txt or .tsv file.';
        }
        return null;
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
