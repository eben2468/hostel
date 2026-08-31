<?php /** @var ?array $hostels @var int $hostelId */
use App\Core\Scope;
$isGlobal = Scope::isGlobal();
?>
<a href="<?= url('/debtors') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to debtors</a>

<form method="post" action="<?= url('/debtors/upload') ?>" enctype="multipart/form-data" class="ui-card p-6 mt-3 space-y-5 max-w-2xl reveal">
    <?= csrf_field() ?>
    <div>
        <h3 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-file-arrow-up text-primary-500 text-sm"></i>Upload Debtors List</h3>
        <p class="text-xs text-gray-400 mt-1">Students on this list are stopped from applying for a room until an admin marks their debt settled.</p>
    </div>

    <?php if ($isGlobal): ?>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Hostel *</label>
            <select name="hostel_id" required class="ui-input">
                <option value="">Select hostel…</option>
                <?php foreach ($hostels as $h): ?>
                    <option value="<?= (int) $h['id'] ?>" <?= $hostelId === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">The list only blocks students belonging to this hostel.</p>
        </div>
    <?php endif; ?>

    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">File *</label>
        <input type="file" name="file" required accept=".txt,.tsv,.csv,.xlsx"
               class="ui-input file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-700">
        <p class="text-xs text-gray-400 mt-1">Excel (.xlsx), CSV, or tab-separated text (.txt / .tsv). Max 5 MB.</p>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Label <span class="text-gray-400 font-normal">(optional)</span></label>
        <input name="label" class="ui-input" placeholder="e.g. 2024/2025 arrears as at September" maxlength="150">
        <p class="text-xs text-gray-400 mt-1">Helps you tell one upload from another later.</p>
    </div>

    <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4 text-sm text-gray-600 space-y-2">
        <p class="font-semibold text-gray-700"><i class="fa-solid fa-circle-info text-primary-400 mr-1.5"></i>What the file should contain</p>
        <p>One row per debtor. Columns can be in any order — each row is read by what the values look like:</p>
        <ul class="list-disc list-inside space-y-0.5 text-xs text-gray-500">
            <li><span class="font-medium text-gray-600">Student ID</span> — mixed letters and digits, e.g. <span class="tnum">226TR02000104</span></li>
            <li><span class="font-medium text-gray-600">Phone</span> — 9 to 12 digits. A leading zero lost by Excel is fine; matching uses the last 9 digits</li>
            <li><span class="font-medium text-gray-600">Name</span>, <span class="font-medium text-gray-600">Room</span> (e.g. GF12) and <span class="font-medium text-gray-600">Amount</span> (e.g. 150.00) — all optional</li>
        </ul>
        <p class="text-xs text-gray-500">A heading row such as <span class="font-medium">“2ND SEMESTER, 2025/2026”</span> is picked up and applied to every row beneath it. Title banners and column headers are ignored.</p>
        <p class="text-xs text-gray-500">Each row needs <span class="font-medium text-gray-600">at least a student ID or a phone number</span> — without one there is nothing to match a student on. Anything unreadable is reported back to you rather than silently dropped.</p>
    </div>

    <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
        <a href="<?= url('/debtors') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-file-arrow-up"></i>Upload &amp; Import</button>
    </div>
</form>
