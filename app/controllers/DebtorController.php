<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Scope;
use App\Models\DuesDebtor;
use App\Models\Hostel;
use App\Services\SheetReader;
use App\Services\DebtorListParser;

/**
 * The hall's carried-forward dues debtors: admins upload the list, and students
 * on it are stopped from applying for a room until the arrears are settled.
 */
class DebtorController extends Controller
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $hostelId = $this->resolveHostel();

        $this->view('debtors/index', [
            'pageTitle' => 'Hall Dues Debtors',
            'hostels'   => Scope::isGlobal() ? (new Hostel())->all('name') : null,
            'hostelId'  => $hostelId,
            'debtors'   => $hostelId ? DuesDebtor::listFor($hostelId, trim($_GET['status'] ?? ''), trim($_GET['q'] ?? '')) : [],
            'batches'   => $hostelId ? DuesDebtor::batchesFor($hostelId) : [],
            'status'    => trim($_GET['status'] ?? ''),
            'q'         => trim($_GET['q'] ?? ''),
        ]);
    }

    public function uploadForm(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('debtors/upload', [
            'pageTitle' => 'Upload Debtors List',
            'hostels'   => Scope::isGlobal() ? (new Hostel())->all('name') : null,
            'hostelId'  => $this->resolveHostel(),
        ]);
    }

    /** Blank form for adding one debtor by hand. */
    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('debtors/form', [
            'pageTitle' => 'Add Debtor',
            'hostels'   => Scope::isGlobal() ? (new Hostel())->all('name') : null,
            'hostelId'  => $this->resolveHostel(),
            'debtor'    => null,
            'matches'   => [],
        ]);
    }

    /** Save a hand-entered debtor into the hostel's manual batch. */
    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();

        $hostelId = Scope::isGlobal() ? (int) $this->input('hostel_id') : (int) Scope::hostelId();
        if (!$hostelId) {
            Session::set('_old', $_POST);
            Session::flash('error', 'Please choose the hostel this debtor belongs to.');
            $this->redirect('/debtors/create');
        }
        $this->guardHostel($hostelId);

        $data = DuesDebtor::fromInput($_POST);
        [$errors, $warnings] = DuesDebtor::checkInput($data);
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/debtors/create' . (Scope::isGlobal() ? '?hostel_id=' . $hostelId : ''));
        }

        $batchId = DuesDebtor::manualBatchId($hostelId, Auth::id());
        $data['batch_id']  = $batchId;
        $data['hostel_id'] = $hostelId;
        $id = (new DuesDebtor())->create($data);
        Database::run("UPDATE dues_debtor_batches SET row_count = row_count + 1 WHERE id = ?", [$batchId]);

        Audit::log('create', 'dues_debtors', $id, (string) ($data['student_no'] ?? $data['phone']));
        Session::forget('_old');
        Session::flash('success', $this->savedMessage($data, $hostelId, 'added'));
        if ($warnings) {
            Session::flash('warning', implode(' ', $warnings));
        }
        $this->redirect('/debtors' . (Scope::isGlobal() ? '?hostel_id=' . $hostelId : ''));
    }

    /** Edit form for one debtor, uploaded or hand-entered alike. */
    public function edit($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $debtor = $this->findScoped($id);
        $this->view('debtors/form', [
            'pageTitle' => 'Edit Debtor',
            'hostels'   => Scope::isGlobal() ? (new Hostel())->all('name') : null,
            'hostelId'  => $debtor['hostel_id'] !== null ? (int) $debtor['hostel_id'] : 0,
            'debtor'    => $debtor,
            'matches'   => DuesDebtor::matchesFor($debtor, $debtor['hostel_id'] !== null ? (int) $debtor['hostel_id'] : null),
        ]);
    }

    /**
     * Save edits. The match keys are recomputed from the new values, so
     * correcting a mistyped ID or phone takes effect immediately.
     */
    public function update($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $debtor = $this->findScoped($id);

        $data = DuesDebtor::fromInput($_POST);
        [$errors, $warnings] = DuesDebtor::checkInput($data);
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/debtors/' . $id . '/edit');
        }

        (new DuesDebtor())->update($id, $data);
        Audit::log('update', 'dues_debtors', $id, (string) ($data['student_no'] ?? $data['phone']));
        Session::forget('_old');
        $hostelId = $debtor['hostel_id'] !== null ? (int) $debtor['hostel_id'] : null;
        Session::flash('success', $this->savedMessage($data, $hostelId, 'updated'));
        if ($warnings) {
            Session::flash('warning', implode(' ', $warnings));
        }
        $this->redirect('/debtors' . (Scope::isGlobal() && $hostelId ? '?hostel_id=' . $hostelId : ''));
    }

    /**
     * Remove a single debtor row. Needed because one mistyped hand-entry would
     * otherwise only be removable by deleting a whole batch and everything in it.
     */
    public function destroy($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $debtor = $this->findScoped($id);
        Database::run("DELETE FROM dues_debtors WHERE id = ?", [$id]);
        Audit::log('delete', 'dues_debtors', $id, (string) ($debtor['student_no'] ?? $debtor['phone']));
        Session::flash('success', trim(($debtor['full_name'] ?? 'That row') . ' — removed from the debtors list.'));
        $this->redirect('/debtors' . (Scope::isGlobal() && $debtor['hostel_id'] ? '?hostel_id=' . (int) $debtor['hostel_id'] : ''));
    }

    /**
     * Read an uploaded list and store it as one batch. The whole upload is a
     * unit: if the parse produced nothing usable nothing is written at all, and
     * a batch that turns out wrong is removed in a single click.
     */
    public function upload(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();

        $hostelId = Scope::isGlobal() ? (int) $this->input('hostel_id') : (int) Scope::hostelId();
        if (!$hostelId) {
            Session::flash('error', 'Please choose the hostel this list belongs to.');
            $this->redirect('/debtors/upload');
        }
        $this->guardHostel($hostelId);

        $file = $_FILES['file'] ?? null;
        $error = $this->validateUpload($file);
        if ($error !== null) {
            Session::flash('error', $error);
            $this->redirect('/debtors/upload');
        }

        $read = SheetReader::read($file['tmp_name'], $file['name']);
        if (!$read['ok']) {
            Session::flash('error', $read['error']);
            $this->redirect('/debtors/upload');
        }

        $parsed = DebtorListParser::parse($read['rows']);
        if (!$parsed['records']) {
            Session::flash('error', 'No debtor rows were recognised in that file. '
                . 'Each row needs at least a student ID or a phone number.');
            $this->redirect('/debtors/upload');
        }

        $batchId = Database::insert(
            "INSERT INTO dues_debtor_batches (hostel_id, filename, label, row_count, skipped_count, uploaded_by)
             VALUES (?,?,?,?,?,?)",
            [
                $hostelId,
                mb_substr((string) $file['name'], 0, 255),
                $this->input('label') ?: null,
                count($parsed['records']),
                count($parsed['skipped']),
                Auth::id(),
            ]
        );

        try {
            DuesDebtor::importBatch($batchId, $hostelId, $parsed['records']);
        } catch (\Throwable $e) {
            // The batch row would otherwise be left describing rows that are
            // not there; drop it so the list never shows a phantom upload.
            Database::run("DELETE FROM dues_debtor_batches WHERE id = ?", [$batchId]);
            Session::flash('error', 'Could not save the list: ' . $e->getMessage());
            $this->redirect('/debtors/upload');
        }

        Audit::log('import', 'dues_debtors', $batchId,
            count($parsed['records']) . ' debtors from ' . $file['name']);

        $matched = $this->countMatched($batchId);
        Session::flash('success', sprintf(
            'Imported %d debtor(s) from %s. %d already match a student account and will be blocked from applying.',
            count($parsed['records']), $file['name'], $matched
        ));
        // Warnings and unread rows are advisory, so they ride along separately
        // rather than burying the result above.
        if ($parsed['warnings'] || $parsed['skipped']) {
            Session::flash('warning', $this->reviewMessage($parsed));
        }
        $this->redirect('/debtors' . (Scope::isGlobal() ? '?hostel_id=' . $hostelId : ''));
    }

    /** Mark one debt settled so the student can apply again. */
    public function clear($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $debt = $this->findScoped($id);
        Database::run(
            "UPDATE dues_debtors SET status='cleared', cleared_by=?, cleared_at=NOW(), note=? WHERE id=?",
            [Auth::id(), $this->input('note') ?: null, $id]
        );
        Audit::log('update', 'dues_debtors', $id, 'cleared');
        Session::flash('success', trim(($debt['full_name'] ?? 'That debt') . ' — marked settled.'));
        $this->back();
    }

    /** Undo a clearing that was made in error. */
    public function restore($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $this->findScoped($id);
        Database::run(
            "UPDATE dues_debtors SET status='outstanding', cleared_by=NULL, cleared_at=NULL WHERE id=?",
            [$id]
        );
        Audit::log('update', 'dues_debtors', $id, 'reopened');
        Session::flash('success', 'Debt reopened.');
        $this->back();
    }

    /** Remove a whole upload — the undo for importing the wrong file. */
    public function deleteBatch($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $batch = Database::first("SELECT * FROM dues_debtor_batches WHERE id = ?", [$id]);
        if (!$batch) {
            $this->redirect('/debtors');
        }
        $this->guardHostel($batch['hostel_id'] !== null ? (int) $batch['hostel_id'] : null);
        // dues_debtors rows cascade off the batch.
        Database::run("DELETE FROM dues_debtor_batches WHERE id = ?", [$id]);
        Audit::log('delete', 'dues_debtors', $id, 'batch ' . $batch['filename']);
        Session::flash('success', 'Upload removed along with its debtor rows.');
        $this->redirect('/debtors' . (Scope::isGlobal() ? '?hostel_id=' . (int) $batch['hostel_id'] : ''));
    }

    // ------------------------------------------------------------- helpers --

    /** The hostel being managed: a bound admin's own, else the ?hostel_id pick. */
    private function resolveHostel(): int
    {
        $hostelId = Scope::isGlobal() ? (int) ($_GET['hostel_id'] ?? 0) : (int) Scope::hostelId();
        if ($hostelId) {
            $this->guardHostel($hostelId);
        }
        return $hostelId;
    }

    /** Load a debtor row, refusing one that belongs to another hostel. */
    private function findScoped($id): array
    {
        $debt = Database::first("SELECT * FROM dues_debtors WHERE id = ?", [$id]);
        if (!$debt) {
            $this->redirect('/debtors');
        }
        $this->guardHostel($debt['hostel_id'] !== null ? (int) $debt['hostel_id'] : null);
        return $debt;
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
        // Refuse anything that did not arrive as a real upload, so a crafted
        // tmp_name can never point the reader at a file on disk. CLI has no
        // HTTP upload to verify, matching the fallback in Core\Upload::image.
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($file['tmp_name'])) {
            return 'That file could not be read.';
        }
        if ($file['size'] <= 0 || $file['size'] > self::MAX_BYTES) {
            return 'The file must be between 1 byte and 5 MB.';
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xls') {
            return 'Old-style .xls files are not supported. Save the file as .xlsx or .csv and upload that.';
        }
        if (!in_array($ext, SheetReader::EXTENSIONS, true)) {
            return 'Unsupported file type. Upload a .xlsx, .csv, .txt or .tsv file.';
        }
        return null;
    }

    /**
     * Confirm the save and, more usefully, say whether the row will actually
     * catch anybody — a debtor matching no account blocks nothing until that
     * student registers.
     */
    private function savedMessage(array $data, ?int $hostelId, string $verb): string
    {
        $who = $data['full_name'] ?: ($data['student_no'] ?: $data['phone']);
        $matches = DuesDebtor::matchesFor($data, $hostelId);
        if (!$matches) {
            return "{$who} {$verb}. No student account matches these details yet — "
                . 'the block will apply automatically if one registers.';
        }
        $names = implode(', ', array_map(fn($m) => $m['full_name'] . ' (' . $m['student_id'] . ')', array_slice($matches, 0, 3)));
        return "{$who} {$verb} — matches {$names}, who can no longer apply for a room.";
    }

    /** How many rows of a batch line up with an existing student account. */
    private function countMatched(int $batchId): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(DISTINCT d.id)
             FROM dues_debtors d
             JOIN students s
               ON (d.student_no_norm IS NOT NULL
                   AND UPPER(REPLACE(REPLACE(s.student_id,'-',''),' ','')) = d.student_no_norm)
               OR (d.phone_norm IS NOT NULL
                   AND RIGHT(REPLACE(REPLACE(s.phone,' ',''),'-',''), 9) = d.phone_norm)
             WHERE d.batch_id = ?",
            [$batchId]
        );
    }

    /** A single readable summary of what needs the admin's eye after an import. */
    private function reviewMessage(array $parsed): string
    {
        $parts = [];
        if ($parsed['skipped']) {
            $lines = array_slice(array_map(fn($s) => 'line ' . $s['line'], $parsed['skipped']), 0, 6);
            $parts[] = count($parsed['skipped']) . ' row(s) could not be read (' . implode(', ', $lines)
                . (count($parsed['skipped']) > 6 ? ', …' : '') . ').';
        }
        foreach (array_slice($parsed['warnings'], 0, 6) as $w) {
            $parts[] = $w;
        }
        if (count($parsed['warnings']) > 6) {
            $parts[] = '… and ' . (count($parsed['warnings']) - 6) . ' more.';
        }
        return implode(' ', $parts);
    }
}
