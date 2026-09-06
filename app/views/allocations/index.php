<?php /** @var array $allocations @var array $filters @var ?array $hostels @var array $pager */
$filters = $filters ?? ['q' => '', 'status' => '', 'hostel' => '', 'sort' => ''];
$hostels = $hostels ?? null;   // null = hostel-bound admin, no hostel picker
$hasFilters = trim($filters['q'] . $filters['status'] . $filters['hostel']) !== '';
?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <p class="text-sm text-gray-500">
        <span class="font-semibold text-gray-700"><?= (int) ($pager['total'] ?? count($allocations)) ?></span>
        allocation(s)<?= $hasFilters ? ' <span class="text-gray-400">found</span>' : '' ?>
    </p>
    <a href="<?= url('/allocations/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Allocate Room</a>
</div>

<!-- Filters. A GET form, so a result set is a shareable URL and the pager
     carries the filters across pages. -->
<form method="get" action="<?= url('/allocations') ?>" class="ui-card p-3 mb-4 flex flex-wrap items-end gap-3" data-reveal="0">
    <div class="flex-1 min-w-[13rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Search</label>
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
            <input name="q" value="<?= e($filters['q']) ?>" class="ui-input pl-8" placeholder="Student, ID, room, bed…">
        </div>
    </div>

    <div class="min-w-[9rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Status</label>
        <select name="status" class="ui-input" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach (['active' => 'Active', 'checked_in' => 'Checked in', 'checked_out' => 'Checked out', 'cancelled' => 'Cancelled'] as $v => $label): ?>
                <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($hostels !== null): ?>
        <div class="min-w-[10rem]">
            <label class="block text-[11px] font-medium text-gray-500 mb-1">Hostel</label>
            <select name="hostel" class="ui-input" onchange="this.form.submit()">
                <option value="">All hostels</option>
                <?php foreach ($hostels as $h): ?>
                    <option value="<?= (int) $h['id'] ?>" <?= $filters['hostel'] === (string) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <div class="min-w-[9rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Sort</label>
        <select name="sort" class="ui-input" onchange="this.form.submit()">
            <option value="">Room order</option>
            <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest first</option>
        </select>
    </div>

    <button class="btn btn-ghost"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
    <?php if ($hasFilters || $filters['sort'] !== ''): ?>
        <a href="<?= url('/allocations') ?>" class="btn btn-ghost border-transparent text-gray-500 hover:text-red-600"><i class="fa-solid fa-xmark"></i> Clear</a>
    <?php endif; ?>
</form>
<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Student</th>
                    <th class="px-4 py-3 font-semibold">Hostel / Room</th>
                    <th class="px-4 py-3 font-semibold">Bed</th>
                    <th class="px-4 py-3 font-semibold">Allocated</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$allocations): ?><tr><td colspan="6" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-bed text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No allocations yet</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($allocations as $a): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-700"><?= e($a['full_name']) ?></p>
                            <p class="text-xs text-gray-400"><?= e($a['student_no']) ?></p>
                        </td>
                        <td class="px-4 py-3 text-gray-500"><?= e($a['hostel_name'] ?? '') ?> · <?= e($a['room_number']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($a['bed_number'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= datef($a['created_at']) ?></td>
                        <td class="px-4 py-3"><?= status_badge($a['status']) ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <?php if ($a['status'] === 'active'): ?>
                                <form method="post" action="<?= url('/allocations/'.$a['id'].'/checkin') ?>" class="inline">
                                    <?= csrf_field() ?><button class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-green-600 hover:bg-green-50 text-xs font-medium transition" title="Check in"><i class="fa-solid fa-right-to-bracket"></i> In</button>
                                </form>
                            <?php elseif ($a['status'] === 'checked_in'): ?>
                                <form method="post" action="<?= url('/allocations/'.$a['id'].'/checkout') ?>" class="inline" onsubmit="return confirm('Check out this student and release the bed?')">
                                    <?= csrf_field() ?><button class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-orange-600 hover:bg-orange-50 text-xs font-medium transition" title="Check out"><i class="fa-solid fa-right-from-bracket"></i> Out</button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($a['status'], ['active','checked_in'], true)): ?>
                                <a href="<?= url('/transfers/create?allocation='.$a['id']) ?>" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-primary-50 hover:text-primary-600 transition" title="Transfer room"><i class="fa-solid fa-right-left"></i></a>
                                <form method="post" action="<?= url('/allocations/'.$a['id'].'/cancel') ?>" class="inline" onsubmit="return confirm('Cancel allocation?')">
                                    <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Cancel"><i class="fa-solid fa-ban"></i></button>
                                </form>
                            <?php else: ?>
                                <span class="text-gray-300 text-xs">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require VIEW_PATH . '/layouts/_pagination.php'; ?>
</div>
