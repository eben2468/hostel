<?php /** @var array $students @var string $term @var string $status @var string $duesType @var bool $duesFilters */
use App\Core\Auth;
$canManage = Auth::hasRole('admin', 'hostel_admin');

$duesType    = $duesType ?? '';
$duesFilters = $duesFilters ?? false;   // false = hall-dues migration not run yet

/** The dues categories admins can narrow the list to, in dropdown order. */
$duesOptions = [
    'fresher'    => ['Freshers — dues paid',    'fa-seedling'],
    'continuing' => ['Continuing — dues paid',  'fa-user-clock'],
];

$total      = (int) ($pager['total'] ?? count($students));
$hasFilters = $term !== '' || $status !== '' || $duesType !== '';
// What the total above the table is counting, so "412" is never ambiguous.
$countLabel = [
    'fresher'    => 'fresher(s) with hall dues paid',
    'continuing' => 'continuing student(s) with hall dues paid',
][$duesType] ?? 'student(s)';

// Export the list as it is filtered on screen, rather than every student.
$exportQuery = array_filter(['q' => $term, 'status' => $status, 'dues_type' => $duesType], fn($v) => $v !== '');
$exportUrl   = url('/export/students') . ($exportQuery ? '?' . http_build_query($exportQuery) : '');
?>
<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-4">
    <form method="get" class="flex flex-wrap gap-2 flex-1 lg:max-w-2xl">
        <div class="relative flex-1 min-w-[12rem]">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
            <input name="q" value="<?= e($term) ?>" placeholder="Search name, ID, email…" class="ui-input pl-10" aria-label="Search students">
        </div>
        <select name="status" class="ui-input w-auto" aria-label="Filter by status">
            <option value="">All status</option>
            <?php foreach (['active','suspended','graduated','inactive'] as $s): ?>
                <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($duesFilters): ?>
            <select name="dues_type" class="ui-input w-auto" aria-label="Filter by hall dues category paid">
                <option value="">All dues categories</option>
                <?php foreach ($duesOptions as $key => [$label, $icon]): ?>
                    <option value="<?= $key ?>" <?= $duesType===$key?'selected':'' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i><span class="hidden sm:inline">Search</span></button>
    </form>
    <div class="flex flex-wrap items-center gap-3">
        <p class="text-sm text-gray-500 whitespace-nowrap">
            <?php if ($duesType !== ''): ?>
                <i class="fa-solid <?= $duesOptions[$duesType][1] ?> text-primary-400 mr-0.5"></i>
            <?php endif; ?>
            <span class="font-semibold text-gray-700"><?= $total ?></span>
            <?= $countLabel ?><?= $hasFilters ? ' <span class="text-gray-400">found</span>' : '' ?>
        </p>
        <?php if (Auth::hasRole('admin','hostel_admin','finance')): ?>
            <a href="<?= e($exportUrl) ?>" class="btn btn-ghost"><i class="fa-solid fa-file-csv"></i> Export</a>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <a href="<?= url('/students/import') ?>" class="btn btn-ghost"><i class="fa-solid fa-upload"></i> Import</a>
            <a href="<?= url('/students/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Student</a>
        <?php endif; ?>
    </div>
</div>

<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Student ID</th>
                    <th class="px-4 py-3 font-semibold">Name</th>
                    <th class="px-4 py-3 font-semibold">Programme</th>
                    <th class="px-4 py-3 font-semibold">Gender</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$students): ?>
                    <tr><td colspan="6" class="px-4 py-14 text-center">
                        <div class="inline-flex flex-col items-center text-gray-400">
                            <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-user-graduate text-xl"></i></span>
                            <p class="text-sm font-medium text-gray-500">No students found</p>
                            <p class="text-xs">Try adjusting your search or filters.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-700"><?= e($s['student_id']) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden bg-primary-100 text-primary-700 text-xs font-bold flex items-center justify-center shrink-0 ring-1 ring-primary-200">
                                    <?php if (!empty($s['photo'])): ?>
                                        <img src="<?= url('/uploads/'.$s['photo']) ?>" alt="" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?= e(strtoupper(substr($s['full_name'],0,1))) ?>
                                    <?php endif; ?>
                                </div>
                                <span class="font-medium text-gray-700"><?= e($s['full_name']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500"><?= e($s['programme'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= ucfirst($s['gender']) ?></td>
                        <td class="px-4 py-3"><?= status_badge($s['status']) ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="<?= url('/students/'.$s['id']) ?>" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-primary-50 hover:text-primary-600 transition" aria-label="View" title="View"><i class="fa-solid fa-eye"></i></a>
                            <?php if ($canManage): ?>
                                <a href="<?= url('/students/'.$s['id'].'/edit') ?>" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition" aria-label="Edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require VIEW_PATH . '/layouts/_pagination.php'; ?>
</div>
