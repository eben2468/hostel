<?php /** @var ?array $hostels @var int $hostelId @var ?array $debtor @var array $matches */
use App\Core\Scope;
use App\Models\DuesDebtor;
use App\Models\Hostel;

$isGlobal = Scope::isGlobal();
$isEdit   = $debtor !== null;
$action   = $isEdit ? url('/debtors/' . $debtor['id']) : url('/debtors');
$matches  = $matches ?? [];

/** Current value for a field: repopulated input first, then the stored row. */
$val = function (string $key, $default = '') use ($debtor) {
    $old = \App\Core\Session::get('_old', []);
    if (array_key_exists($key, $old)) {
        return e($old[$key]);
    }
    return e($debtor[$key] ?? $default);
};
$years = Hostel::yearOptions($debtor['academic_year'] ?? null);
?>
<a href="<?= url('/debtors' . ($isGlobal && $hostelId ? '?hostel_id=' . $hostelId : '')) ?>"
   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to debtors</a>

<form method="post" action="<?= $action ?>" class="ui-card p-6 mt-3 space-y-5 max-w-2xl reveal">
    <?= csrf_field() ?>
    <div>
        <h3 class="font-display font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid <?= $isEdit ? 'fa-pen-to-square' : 'fa-user-plus' ?> text-primary-500 text-sm"></i>
            <?= $isEdit ? 'Edit Debtor' : 'Add Debtor' ?>
        </h3>
        <p class="text-xs text-gray-400 mt-1">
            <?= $isEdit
                ? 'Corrections take effect immediately — fixing a mistyped ID or phone number makes the row start matching straight away.'
                : 'For one-off arrears you would rather not re-upload a whole file for.' ?>
        </p>
    </div>

    <?php if ($isEdit && $matches): ?>
        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
            <i class="fa-solid fa-user-check mt-0.5 text-amber-500"></i>
            <div class="text-sm">
                <p class="font-semibold">Currently blocking</p>
                <p class="text-amber-700/80">
                    <?php foreach ($matches as $i => $m): ?>
                        <?= $i ? ', ' : '' ?><a href="<?= url('/students/' . $m['id']) ?>" class="underline"><?= e($m['full_name']) ?> (<?= e($m['student_id']) ?>)</a>
                    <?php endforeach; ?>
                </p>
            </div>
        </div>
    <?php elseif ($isEdit): ?>
        <div class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 text-gray-600">
            <i class="fa-solid fa-circle-info mt-0.5 text-gray-400"></i>
            <p class="text-sm">No registered student matches these details yet, so this row is not blocking anybody. Check the student ID and phone number against the student's record.</p>
        </div>
    <?php endif; ?>

    <?php if ($isGlobal && !$isEdit): ?>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Hostel *</label>
            <select name="hostel_id" required class="ui-input">
                <option value="">Select hostel…</option>
                <?php foreach ($hostels as $h): ?>
                    <option value="<?= (int) $h['id'] ?>" <?= $hostelId === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">The debt only blocks students belonging to this hostel.</p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Full name</label>
            <input name="full_name" value="<?= $val('full_name') ?>" class="ui-input" maxlength="150" placeholder="As it appears on the hall list">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Student ID</label>
            <input name="student_no" value="<?= $val('student_no') ?>" class="ui-input tnum" maxlength="60" placeholder="e.g. 226TR02000104">
            <p class="text-xs text-gray-400 mt-1">Matched ignoring case and punctuation.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Phone</label>
            <input name="phone" value="<?= $val('phone') ?>" class="ui-input tnum" maxlength="40" placeholder="e.g. 0548811774">
            <p class="text-xs text-gray-400 mt-1">Matched on the last 9 digits, so a missing leading zero is fine.</p>
        </div>

        <div class="sm:col-span-2 flex items-start gap-2.5 rounded-xl border border-gray-100 bg-gray-50/60 p-3 text-xs text-gray-500">
            <i class="fa-solid fa-circle-info mt-0.5 text-gray-400"></i>
            <span>Fill in <span class="font-medium text-gray-600">at least one</span> of Student ID or Phone — that is what the student is matched on. Giving both is safest: either one will catch them.</span>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Academic year</label>
            <select name="academic_year" class="ui-input">
                <option value="">Not specified</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= e($y) ?>" <?= $val('academic_year') === e($y) ? 'selected' : '' ?>><?= e($y) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Semester</label>
            <select name="semester" class="ui-input">
                <option value="">Not specified</option>
                <?php foreach (DuesDebtor::SEMESTERS as $sm): ?>
                    <option value="<?= $sm ?>" <?= $val('semester') === $sm ? 'selected' : '' ?>><?= $sm ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Room</label>
            <input name="room_label" value="<?= $val('room_label') ?>" class="ui-input" maxlength="40" placeholder="e.g. GF12">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Amount owed</label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= CURRENCY_SIGN ?></span>
                <input type="number" step="0.01" min="0" name="amount"
                       value="<?= $debtor && $debtor['amount'] !== null ? e(number_format((float) $debtor['amount'], 2, '.', '')) : $val('amount') ?>"
                       class="ui-input pl-12" placeholder="0.00">
            </div>
            <p class="text-xs text-gray-400 mt-1">Shown to the student. Leave blank if unknown.</p>
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-3 pt-3 border-t border-gray-100">
        <?php if ($isEdit): ?>
            <button type="submit" form="delete-debtor-<?= (int) $debtor['id'] ?>"
                    class="mr-auto inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-400 hover:bg-red-50 hover:text-red-600 transition">
                <i class="fa-solid fa-trash"></i>Delete this row
            </button>
        <?php endif; ?>
        <a href="<?= url('/debtors' . ($isGlobal && $hostelId ? '?hostel_id=' . $hostelId : '')) ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i><?= $isEdit ? 'Save Changes' : 'Add Debtor' ?></button>
    </div>
</form>

<?php if ($isEdit): ?>
    <!-- Kept outside the edit form: nested forms are invalid HTML. -->
    <form id="delete-debtor-<?= (int) $debtor['id'] ?>" method="post" action="<?= url('/debtors/' . $debtor['id'] . '/delete') ?>"
          onsubmit="return confirm('Remove this debtor row completely? This cannot be undone.')">
        <?= csrf_field() ?>
    </form>
<?php endif; ?>
