<?php /** @var array $allocations */ ?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($allocations) ?></span> allocation(s)</p>
    <a href="<?= url('/allocations/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Allocate Room</a>
</div>
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
