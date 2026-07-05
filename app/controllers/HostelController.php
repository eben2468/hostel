<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Scope;
use App\Core\Auth;
use App\Models\Hostel;

class HostelController extends Controller
{
    private Hostel $hostels;

    public function __construct()
    {
        $this->hostels = new Hostel();
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        // Attach live room counts to each hostel; hostel admins see only their own.
        [$scope, $bind] = Scope::on('h.id');
        $hostels = Database::all(
            "SELECT h.*,
                    (SELECT COUNT(*) FROM rooms r WHERE r.hostel_id=h.id) AS room_count,
                    (SELECT COALESCE(SUM(occupied),0) FROM rooms r WHERE r.hostel_id=h.id) AS occupied_beds,
                    (SELECT COALESCE(SUM(capacity),0) FROM rooms r WHERE r.hostel_id=h.id) AS bed_capacity
             FROM hostels h WHERE 1{$scope} ORDER BY h.name",
            $bind
        );
        $this->view('hostels/index', ['pageTitle' => 'Hostels', 'hostels' => $hostels, 'canCreate' => Scope::isGlobal()]);
    }

    public function create(): void
    {
        // Only the super admin provisions new hostels.
        $this->requireAuth('admin');
        $this->view('hostels/form', ['pageTitle' => 'Add Hostel', 'hostel' => null]);
    }

    public function store(): void
    {
        $this->requireAuth('admin');
        Csrf::check();
        $errors = $this->validate(['name' => 'Hostel name', 'code' => 'Hostel code']);
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/hostels/create');
        }
        $id = $this->hostels->create($this->data());
        Audit::log('create', 'hostels', $id);
        Session::flash('success', 'Hostel created.');
        $this->redirect('/hostels/' . $id);
    }

    public function show($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $hostel = $this->hostels->find($id);
        if (!$hostel) {
            $this->redirect('/hostels');
        }
        $this->guardHostel((int) $hostel['id']);
        $rooms = Database::all(
            "SELECT r.*, b.name AS block_name FROM rooms r LEFT JOIN blocks b ON b.id=r.block_id
             WHERE r.hostel_id=? ORDER BY r.room_number",
            [$id]
        );
        // Floors are nested under a block; link "Manage Floors" to the first
        // block when one exists, otherwise send the admin to create a block.
        $firstBlockId = Database::scalar("SELECT id FROM blocks WHERE hostel_id=? ORDER BY name LIMIT 1", [$id]);
        $this->view('hostels/show', [
            'pageTitle'    => $hostel['name'],
            'hostel'       => $hostel,
            'rooms'        => $rooms,
            'firstBlockId' => $firstBlockId ? (int) $firstBlockId : null,
        ]);
    }

    public function edit($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $hostel = $this->hostels->find($id);
        if (!$hostel) {
            $this->redirect('/hostels');
        }
        $this->guardHostel((int) $hostel['id']);
        $this->view('hostels/form', ['pageTitle' => 'Edit Hostel', 'hostel' => $hostel]);
    }

    public function update($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $hostel = $this->hostels->find($id);
        if (!$hostel) {
            $this->redirect('/hostels');
        }
        $this->guardHostel((int) $hostel['id']);
        $this->hostels->update($id, $this->data());
        Audit::log('update', 'hostels', $id);
        Session::flash('success', 'Hostel updated.');
        $this->redirect('/hostels/' . $id);
    }

    public function destroy($id): void
    {
        $this->requireAuth('admin');
        Csrf::check();
        $this->hostels->delete($id);
        Audit::log('delete', 'hostels', $id);
        Session::flash('success', 'Hostel deleted.');
        $this->redirect('/hostels');
    }

    private function data(): array
    {
        $facilities = $_POST['facilities'] ?? [];
        $data = [
            'name'        => $this->input('name'),
            'code'        => $this->input('code'),
            'type'        => $this->input('type', 'mixed'),
            'address'     => $this->input('address'),
            'digital_address' => $this->input('digital_address'),
            'manager'     => $this->input('manager'),
            'description' => $this->input('description'),
            'facilities'  => is_array($facilities) ? implode(',', $facilities) : $facilities,
            'status'      => $this->input('status', 'active'),
        ];

        // Only the super admin manages a hostel's Paystack account. The public
        // key is stored as submitted; the secret is only overwritten when a new
        // value is entered, so it is never blanked by an edit that left the
        // (masked) field empty.
        if (Auth::hasRole('admin')) {
            $data['paystack_public_key'] = $this->input('paystack_public_key');
            $secret = (string) $this->input('paystack_secret_key');
            if ($secret !== '') {
                $data['paystack_secret_key'] = $secret;
            }
            // Toggle: students may pay online only when this is on (checkbox
            // absent from POST means off).
            $data['paystack_enabled'] = isset($_POST['paystack_enabled']) ? 1 : 0;
        }

        return $data;
    }
}
