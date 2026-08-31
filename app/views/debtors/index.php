<?php /** @var ?array $hostels @var int $hostelId @var array $debtors @var array $batches @var string $status @var string $q */
use App\Core\Scope;
$isGlobal = Scope::isGlobal();
$qs = $isGlobal && $hostelId ? '?hostel_id=' . $hostelId : '';

$outstanding = array_filter($debtors, fn($d) => $d['status'] === 'outstanding');
$matched     = array_filter($outstanding, fn($d) => !empty($d['matched_student_id']));
$owed        = array_sum(array_map(fn($d) => (float) ($d['amount'] ?? 0), $outstanding));
?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <div>
        <h2 class="font-display font-bold text-gray-800">Hall Dues Debtors</h2>
        <p class="text-xs text-gray-400 mt-0.5">Students carried over from previous semesters. Anyone still outstanding here cannot apply for a room.</p>
    </div>
    <?php if ($hostelId): ?>
        <div class="flex flex-wrap gap-2">
            <a href="<?= url('/debtors/create' . $qs) ?>" class="btn btn-ghost"><i class="fa-solid fa-user-plus"></i> Add Debtor</a>
            <a href="<?= url('/debtors/upload') ?>" class="btn btn-primary"><i class="fa-solid fa-file-arrow-up"></i> Upload List</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($isGlobal): ?>
    <form method="get" action="<?= url('/debtors') ?>" class="ui-card p-5 mb-4">
        <label class="block text-sm font-medium text-gray-600 mb-1">Hostel</label>
        <select name="hostel_id" class="ui-input" onchange="this.form.submit()">
            <option value="">Select hostel…</option>
            <?php foreach ($hostels as $h): ?>
                <option value="<?= (int) $h['id'] ?>" <?= $hostelId === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
<?php endif; ?>

<?php if (!$hostelId): ?>
    <div class="ui-card p-6">
        <p class="text-sm <?= $isGlobal ? 'text-gray-400' : 'text-amber-600' ?>">
            <?= $isGlobal ? 'Choose a hostel above to manage its debtors list.' : 'No hostel is linked to your account.' ?>
        </p>
    </div>
<?php else: ?>

<!-- Summary -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
    <?php foreach ([
        ['Outstanding',     count($outstanding),               'fa-triangle-exclamation', 'red'],
        ['Matched to accounts', count($matched),               'fa-user-check',           'amber'],
        ['Settled',         count($debtors) - count($outstanding), 'fa-circle-check',     'green'],
        ['Total owed',      money($owed),                      'fa-sack-dollar',          'primary'],
    ] as [$label, $value, $icon, $tone]): ?>
        <div class="ui-card p-4 stat-tile">
            <div class="flex items-center gap-2 text-<?= $tone ?>-600">
                <span class="inline-flex w-8 h-8 rounded-lg bg-<?= $tone ?>-50 items-center justify-center"><i class="fa-solid <?= $icon ?> text-sm"></i></span>
                <p class="text-[11px] uppercase tracking-wider text-gray-400 font-semibold"><?= e($label) ?></p>
            </div>
            <p class="mt-2 text-xl font-bold text-gray-800 tnum"><?= is_string($value) ? $value : (int) $value ?></p>
        </div>
    <?php endforeach; ?>
</div>

<?php if (count($outstanding) && !count($matched)): ?>
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
        <i class="fa-solid fa-circle-info mt-0.5 text-amber-500"></i>
        <div class="text-sm">
            <p class="font-semibold">None of these debtors has an account yet</p>
            <p class="text-amber-700/80">That is normal if they have not registered. The block takes effect automatically the moment a student signs up with a matching Student ID or phone number.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Uploads -->
<?php if ($batches): ?>
    <div class="ui-card p-5 mb-4" x-data="{ open: false }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-3 text-left">
            <span class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-layer-group text-primary-500 text-sm"></i>Uploads (<?= count($batches) ?>)</span>
            <i class="fa-solid fa-chevron-down text-gray-400 text-xs transition" :class="open && 'rotate-180'"></i>
        </button>
        <div x-cloak x-show="open" x-transition class="mt-4 divide-y divide-gray-100">
            <?php foreach ($batches as $b): ?>
                <?php $isManual = \App\Models\DuesDebtor::isManualBatch($b); ?>
                <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-700 flex items-center gap-1.5">
                            <i class="fa-solid <?= $isManual ? 'fa-user-plus' : 'fa-file-lines' ?> text-gray-300 text-xs"></i>
                            <?= e($b['label'] ?: $b['filename']) ?>
                        </p>
                        <p class="text-xs text-gray-400">
                            <?= $isManual ? 'Typed in on this screen' : e($b['filename']) ?> · <?= (int) $b['rows_now'] ?> row(s),
                            <?= (int) $b['cleared_now'] ?> settled · <?= datef($b['created_at'], 'd M Y H:i') ?>
                            <?= $b['uploaded_by_name'] ? ' · by ' . e($b['uploaded_by_name']) : '' ?>
                            <?= (int) $b['skipped_count'] > 0 ? ' · ' . (int) $b['skipped_count'] . ' row(s) unread' : '' ?>
                        </p>
                    </div>
                    <form method="post" action="<?= url('/debtors/batches/' . $b['id'] . '/delete') ?>"
                          onsubmit="return confirm('Delete <?= $isManual ? 'all' : 'this upload and all' ?> <?= (int) $b['rows_now'] ?> debtor row(s)<?= $isManual ? ' added by hand' : ' it created' ?>?')">
                        <?= csrf_field() ?>
                        <button class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-400 hover:bg-red-50 hover:text-red-600 transition"><i class="fa-solid fa-trash"></i><?= $isManual ? 'Delete all' : 'Delete upload' ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Filters -->
<form method="get" action="<?= url('/debtors') ?>" class="flex flex-wrap gap-2 mb-4">
    <?php if ($isGlobal): ?><input type="hidden" name="hostel_id" value="<?= (int) $hostelId ?>"><?php endif; ?>
    <input name="q" value="<?= e($q) ?>" placeholder="Search name, student ID or phone…" class="ui-input w-auto flex-1 min-w-[16rem]">
    <select name="status" class="ui-input w-auto" onchange="this.form.submit()">
        <option value="">All</option>
        <option value="outstanding" <?= $status === 'outstanding' ? 'selected' : '' ?>>Outstanding</option>
        <option value="cleared" <?= $status === 'cleared' ? 'selected' : '' ?>>Settled</option>
    </select>
    <button class="btn btn-ghost"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
</form>

<!-- Debtors -->
<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Name</th>
                    <th class="px-4 py-3 font-semibold">Student ID</th>
                    <th class="px-4 py-3 font-semibold">Phone</th>
                    <th class="px-4 py-3 font-semibold">Semester</th>
                    <th class="px-4 py-3 font-semibold">Room</th>
                    <th class="px-4 py-3 font-semibold text-right">Amount</th>
                    <th class="px-4 py-3 font-semibold">Account</th>
                    <th class="px-4 py-3 font-semibold text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$debtors): ?>
                    <tr><td colspan="8" class="px-4 py-14 text-center">
                        <div class="inline-flex flex-col items-center text-gray-400">
                            <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-user-check text-xl"></i></span>
                            <p class="text-sm font-medium text-gray-500">No debtors recorded</p>
                            <p class="text-xs text-gray-400 mt-1">Upload the hall's list — or add one by hand — to start blocking applications from students in arrears.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($debtors as $d): ?>
                    <?php $isOut = $d['status'] === 'outstanding'; ?>
                    <tr class="<?= $isOut ? '' : 'opacity-60' ?>">
                        <td class="px-4 py-3 font-medium text-gray-700"><?= e($d['full_name'] ?: '—') ?></td>
                        <td class="px-4 py-3 text-gray-500 tnum"><?= e($d['student_no'] ?: '—') ?></td>
                        <td class="px-4 py-3 text-gray-500 tnum"><?= e($d['phone'] ?: '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e(trim(($d['semester'] ?? '') . ' ' . ($d['academic_year'] ?? ''))) ?: '—' ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($d['room_label'] ?: '—') ?></td>
                        <td class="px-4 py-3 text-right tnum <?= $isOut ? 'text-red-600 font-semibold' : 'text-gray-400' ?>">
                            <?= $d['amount'] !== null ? money($d['amount']) : '—' ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if (!empty($d['matched_student_id'])): ?>
                                <a href="<?= url('/students/' . $d['matched_student_id']) ?>"
                                   class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline"
                                   title="Matched to a registered student">
                                    <i class="fa-solid fa-user-check"></i><?= e($d['matched_name']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">No account yet</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <?php if ($isOut): ?>
                                <form method="post" action="<?= url('/debtors/' . $d['id'] . '/clear') ?>" class="inline"
                                      onsubmit="return confirm('Mark this debt as settled? The student will be able to apply for a room again.')">
                                    <?= csrf_field() ?>
                                    <button class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 hover:bg-green-50 hover:text-green-700 transition"><i class="fa-solid fa-circle-check"></i>Mark settled</button>
                                </form>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-xs text-green-600 font-medium mr-1"><i class="fa-solid fa-circle-check"></i>Settled</span>
                                <form method="post" action="<?= url('/debtors/' . $d['id'] . '/restore') ?>" class="inline">
                                    <?= csrf_field() ?>
                                    <button class="inline-flex w-7 h-7 items-center justify-center rounded-lg text-gray-300 hover:bg-amber-50 hover:text-amber-600 transition" title="Reopen this debt"><i class="fa-solid fa-rotate-left text-xs"></i></button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= url('/debtors/' . $d['id'] . '/edit') ?>"
                               class="inline-flex w-7 h-7 items-center justify-center rounded-lg text-gray-300 hover:bg-primary-50 hover:text-primary-600 transition"
                               title="Edit this debtor"><i class="fa-solid fa-pen-to-square text-xs"></i></a>
                            <form method="post" action="<?= url('/debtors/' . $d['id'] . '/delete') ?>" class="inline"
                                  onsubmit="return confirm('Remove this debtor row completely? This cannot be undone.')">
                                <?= csrf_field() ?>
                                <button class="inline-flex w-7 h-7 items-center justify-center rounded-lg text-gray-300 hover:bg-red-50 hover:text-red-600 transition" title="Remove this row"><i class="fa-solid fa-trash text-xs"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>
