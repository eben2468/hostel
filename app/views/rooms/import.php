<?php /** @var array $hostels */
use App\Core\Scope;
$isGlobal = Scope::isGlobal();
?>
<a href="<?= url('/rooms') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to rooms</a>

<form method="post" action="<?= url('/rooms/import') ?>" enctype="multipart/form-data" class="ui-card p-6 mt-3 space-y-5 max-w-2xl reveal">
    <?= csrf_field() ?>
    <div>
        <h3 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-file-arrow-up text-primary-500 text-sm"></i>Import Rooms</h3>
        <p class="text-xs text-gray-400 mt-1">Create many rooms at once from a spreadsheet, each with its beds, instead of adding them one by one.</p>
    </div>

    <?php if ($isGlobal): ?>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Hostel *</label>
            <select name="hostel_id" required class="ui-input">
                <option value="">Select hostel…</option>
                <?php foreach ($hostels as $h): ?>
                    <option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Every room in the file is created in this hostel.</p>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4">
        <div class="text-sm text-primary-800">
            <p class="font-semibold">Don't have a file yet?</p>
            <p class="text-primary-700/80 text-xs mt-0.5">Download a ready-made list — GF1–GF37, FF1–FF37, SF1–SF37, TF1–TF37 — edit the Type, Capacity and Price in Excel, then upload it back here.</p>
        </div>
        <a href="<?= url('/rooms/import/template') ?>" class="btn btn-ghost shrink-0"><i class="fa-solid fa-download"></i> Download room list</a>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">File *</label>
        <input type="file" name="file" required accept=".xlsx,.csv,.txt,.tsv"
               class="ui-input file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-700">
        <p class="text-xs text-gray-400 mt-1">Excel (.xlsx), CSV or tab-separated text. Max 5 MB.</p>
    </div>

    <label class="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/60 p-4 cursor-pointer">
        <input type="checkbox" name="create_floors" value="1" class="mt-0.5 rounded" checked>
        <span class="text-sm">
            <span class="font-medium text-gray-700">Create floors listed in the Floor column</span>
            <span class="block text-xs text-gray-400 mt-0.5">Rooms are grouped under those floors so the Floor filter works. A floor needs a block, so a “Main Block” is created for the hostel if it has none. Untick to import the rooms without any floor structure.</span>
        </span>
    </label>

    <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4 text-sm text-gray-600 space-y-2">
        <p class="font-semibold text-gray-700"><i class="fa-solid fa-table-columns text-primary-400 mr-1.5"></i>Columns</p>
        <p class="text-xs">The first row names the columns. Order does not matter; only <span class="font-medium text-gray-600">Room Number</span> is required.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="text-gray-400 text-left">
                    <tr><th class="py-1 pr-4 font-semibold">Column</th><th class="py-1 pr-4 font-semibold">Example</th><th class="py-1 font-semibold">If left out</th></tr>
                </thead>
                <tbody class="text-gray-500">
                    <tr><td class="py-1 pr-4 font-medium text-gray-600">Room Number *</td><td class="py-1 pr-4">GF1</td><td class="py-1">row is skipped</td></tr>
                    <tr><td class="py-1 pr-4">Floor</td><td class="py-1 pr-4">GF</td><td class="py-1">no floor assigned</td></tr>
                    <tr><td class="py-1 pr-4">Type</td><td class="py-1 pr-4">quad</td><td class="py-1">double</td></tr>
                    <tr><td class="py-1 pr-4">Capacity</td><td class="py-1 pr-4">4</td><td class="py-1">1</td></tr>
                    <tr><td class="py-1 pr-4">Price</td><td class="py-1 pr-4">1200</td><td class="py-1">0</td></tr>
                    <tr><td class="py-1 pr-4">Status</td><td class="py-1 pr-4">available</td><td class="py-1">available</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500">Types: single, double, triple, quad, deluxe, vip. Statuses: available, occupied, reserved, maintenance, closed. Anything unrecognised falls back to the default rather than failing the row.</p>
        <p class="text-xs text-gray-500"><span class="font-medium text-gray-600">Beds are created automatically</span> to match each room's capacity — a room with none cannot be allocated.</p>
        <p class="text-xs text-gray-500"><span class="font-medium text-gray-600">Re-uploading is safe.</span> A room number that already exists in the hostel is skipped, not duplicated, so you can add to the list and upload the same file again.</p>
    </div>

    <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
        <a href="<?= url('/rooms') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-file-arrow-up"></i>Upload &amp; Create Rooms</button>
    </div>
</form>
