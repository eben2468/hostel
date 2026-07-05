<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Scope;
use App\Models\Inventory;
use App\Models\Hostel;

class InventoryController extends Controller
{
    private Inventory $inventory;

    public function __construct()
    {
        $this->inventory = new Inventory();
    }

    /** Hostels a user may attach inventory to: all for admin, only their own otherwise. */
    private function hostelOptions(): array
    {
        if (Scope::isGlobal()) {
            return (new Hostel())->all('name');
        }
        $own = (new Hostel())->find(Scope::hostelId());
        return $own ? [$own] : [];
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'maintenance');
        $this->view('inventory/index', [
            'pageTitle' => 'Inventory & Assets',
            'items'     => $this->inventory->allWithHostel(),
            'lowStock'  => $this->inventory->lowStock(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('inventory/form', [
            'pageTitle' => 'Add Inventory Item',
            'item'      => null,
            'hostels'   => $this->hostelOptions(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $errors = $this->validate(['name' => 'Item name']);
        if ($errors) {
            Session::flash('error', reset($errors));
            $this->redirect('/inventory/create');
        }
        $id = $this->inventory->create($this->data());
        Audit::log('create', 'inventory', $id);
        Session::flash('success', 'Inventory item added.');
        $this->redirect('/inventory');
    }

    public function edit($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $item = $this->inventory->find($id);
        if (!$item) {
            $this->redirect('/inventory');
        }
        $this->guardHostel($item['hostel_id'] !== null ? (int) $item['hostel_id'] : null);
        $this->view('inventory/form', [
            'pageTitle' => 'Edit Inventory Item',
            'item'      => $item,
            'hostels'   => $this->hostelOptions(),
        ]);
    }

    public function update($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $item = $this->inventory->find($id);
        if (!$item) {
            $this->redirect('/inventory');
        }
        $this->guardHostel($item['hostel_id'] !== null ? (int) $item['hostel_id'] : null);
        $this->inventory->update($id, $this->data());
        Audit::log('update', 'inventory', $id);
        Session::flash('success', 'Inventory item updated.');
        $this->redirect('/inventory');
    }

    public function destroy($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $item = $this->inventory->find($id);
        if (!$item) {
            $this->redirect('/inventory');
        }
        $this->guardHostel($item['hostel_id'] !== null ? (int) $item['hostel_id'] : null);
        $this->inventory->delete($id);
        Audit::log('delete', 'inventory', $id);
        Session::flash('success', 'Inventory item deleted.');
        $this->redirect('/inventory');
    }

    private function data(): array
    {
        // Hostel-bound users may only write inventory for their own hostel.
        $hostelId = Scope::isGlobal() ? ($this->input('hostel_id') ?: null) : Scope::hostelId();
        return [
            'name'          => $this->input('name'),
            'category'      => $this->input('category', 'other'),
            'hostel_id'     => $hostelId,
            'quantity'      => max(0, (int) $this->input('quantity', 1)),
            'condition'     => $this->input('condition', 'good'),
            'reorder_level' => max(0, (int) $this->input('reorder_level', 0)),
            'notes'         => $this->input('notes'),
        ];
    }
}
