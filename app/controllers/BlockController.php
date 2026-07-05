<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Audit;
use App\Models\Block;
use App\Models\Floor;
use App\Models\Hostel;

class BlockController extends Controller
{
    private Block $blocks;

    public function __construct()
    {
        $this->blocks = new Block();
    }

    /** List blocks for a hostel (+ inline add form). */
    public function index($hostelId): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $hostel = (new Hostel())->find($hostelId);
        if (!$hostel) {
            $this->redirect('/hostels');
        }
        $this->guardHostel((int) $hostel['id']);
        $this->view('structure/blocks', [
            'pageTitle' => $hostel['name'] . ' · Blocks',
            'hostel'    => $hostel,
            'blocks'    => $this->blocks->forHostel((int) $hostelId),
        ]);
    }

    public function store($hostelId): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $this->guardHostel((int) $hostelId);
        $errors = $this->validate(['name' => 'Block name']);
        if ($errors) {
            Session::flash('error', reset($errors));
            $this->redirect('/hostels/' . $hostelId . '/blocks');
        }
        $id = $this->blocks->create([
            'hostel_id'   => (int) $hostelId,
            'name'        => $this->input('name'),
            'code'        => $this->input('code'),
            'gender'      => $this->input('gender', 'mixed'),
            'description' => $this->input('description'),
            'status'      => 'active',
        ]);
        Audit::log('create', 'blocks', $id);
        Session::flash('success', 'Block added.');
        $this->redirect('/hostels/' . $hostelId . '/blocks');
    }

    public function update($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $block = $this->blocks->find($id);
        if (!$block) {
            $this->redirect('/hostels');
        }
        $this->guardHostel((int) $block['hostel_id']);
        $errors = $this->validate(['name' => 'Block name']);
        if ($errors) {
            Session::flash('error', reset($errors));
            $this->redirect('/hostels/' . $block['hostel_id'] . '/blocks');
        }
        $this->blocks->update($id, [
            'name'        => $this->input('name'),
            'code'        => $this->input('code'),
            'gender'      => $this->input('gender', 'mixed'),
            'description' => $this->input('description'),
        ]);
        Audit::log('update', 'blocks', $id);
        Session::flash('success', 'Block updated.');
        $this->redirect('/hostels/' . $block['hostel_id'] . '/blocks');
    }

    public function destroy($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $block = $this->blocks->find($id);
        if (!$block) {
            $this->redirect('/hostels');
        }
        $this->guardHostel((int) $block['hostel_id']);
        $this->blocks->delete($id);
        Audit::log('delete', 'blocks', $id);
        Session::flash('success', 'Block deleted.');
        $this->redirect('/hostels/' . ($block['hostel_id'] ?? '') . '/blocks');
    }

    // --- Floors (nested under a block) -------------------------------------
    public function floors($blockId): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $block = $this->blocks->find($blockId);
        if (!$block) {
            $this->redirect('/hostels');
        }
        $this->guardHostel((int) $block['hostel_id']);
        $this->view('structure/floors', [
            'pageTitle' => $block['name'] . ' · Floors',
            'block'     => $block,
            'floors'    => (new Floor())->forBlock((int) $blockId),
        ]);
    }

    public function storeFloor($blockId): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $block = $this->blocks->find($blockId);
        if (!$block) {
            $this->redirect('/hostels');
        }
        $this->guardHostel((int) $block['hostel_id']);
        $errors = $this->validate(['number' => 'Floor number']);
        if ($errors) {
            Session::flash('error', reset($errors));
            $this->redirect('/blocks/' . $blockId . '/floors');
        }
        $id = (new Floor())->create([
            'block_id'    => (int) $blockId,
            'number'      => $this->input('number'),
            'description' => $this->input('description'),
        ]);
        Audit::log('create', 'floors', $id);
        Session::flash('success', 'Floor added.');
        $this->redirect('/blocks/' . $blockId . '/floors');
    }

    public function updateFloor($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $floor = (new Floor())->find($id);
        if (!$floor) {
            $this->redirect('/hostels');
        }
        $block = $this->blocks->find($floor['block_id']);
        $this->guardHostel($block ? (int) $block['hostel_id'] : null);
        $errors = $this->validate(['number' => 'Floor number']);
        if ($errors) {
            Session::flash('error', reset($errors));
            $this->redirect('/blocks/' . $floor['block_id'] . '/floors');
        }
        (new Floor())->update($id, [
            'number'      => $this->input('number'),
            'description' => $this->input('description'),
        ]);
        Audit::log('update', 'floors', $id);
        Session::flash('success', 'Floor updated.');
        $this->redirect('/blocks/' . $floor['block_id'] . '/floors');
    }

    public function destroyFloor($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $floor = (new Floor())->find($id);
        if (!$floor) {
            $this->redirect('/hostels');
        }
        $block = $this->blocks->find($floor['block_id']);
        $this->guardHostel($block ? (int) $block['hostel_id'] : null);
        (new Floor())->delete($id);
        Audit::log('delete', 'floors', $id);
        Session::flash('success', 'Floor deleted.');
        $this->redirect('/blocks/' . ($floor['block_id'] ?? '') . '/floors');
    }
}
