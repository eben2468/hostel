<?php /** @var array $visitors @var bool $isStudent */
use App\Core\Auth;
$canManage = Auth::hasRole('admin','hostel_admin','security');
?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($visitors) ?></span> visitor record(s)</p>
    <a href="<?= url('/visitors/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Register Visitor</a>
</div>
<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Visitor</th>
                    <?php if (!$isStudent): ?><th class="px-4 py-3 font-semibold">Host</th><?php endif; ?>
                    <th class="px-4 py-3 font-semibold">Purpose</th>
                    <th class="px-4 py-3 font-semibold">Date</th>
                    <th class="px-4 py-3 font-semibold">Pass</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <?php if ($canManage): ?><th class="px-4 py-3 font-semibold text-right">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$visitors): ?><tr><td colspan="7" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-user-check text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No visitors</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($visitors as $v): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-700"><?= e($v['visitor_name']) ?></p>
                            <p class="text-xs text-gray-400"><?= e($v['phone'] ?? '') ?></p>
                        </td>
                        <?php if (!$isStudent): ?><td class="px-4 py-3 text-gray-500"><?= e($v['host_name'] ?? '—') ?></td><?php endif; ?>
                        <td class="px-4 py-3 text-gray-500"><?= e($v['purpose'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= datef($v['visit_date']) ?></td>
                        <td class="px-4 py-3"><span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded"><?= e($v['pass_code'] ?? '—') ?></span></td>
                        <td class="px-4 py-3"><?= status_badge($v['status']) ?></td>
                        <?php if ($canManage): ?>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?php if ($v['status'] === 'pending'): ?>
                                    <form method="post" action="<?= url('/visitors/'.$v['id'].'/approve') ?>" class="inline"><?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-green-50 hover:text-green-600 transition" title="Approve"><i class="fa-solid fa-check"></i></button></form>
                                    <form method="post" action="<?= url('/visitors/'.$v['id'].'/blacklist') ?>" class="inline" onsubmit="return confirm('Blacklist this visitor?')"><?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Blacklist"><i class="fa-solid fa-ban"></i></button></form>
                                <?php elseif ($v['status'] === 'approved'): ?>
                                    <form method="post" action="<?= url('/visitors/'.$v['id'].'/checkin') ?>" class="inline"><?= csrf_field() ?><button class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-primary-600 hover:bg-primary-50 text-xs font-medium transition"><i class="fa-solid fa-right-to-bracket"></i> In</button></form>
                                <?php elseif ($v['status'] === 'checked_in'): ?>
                                    <form method="post" action="<?= url('/visitors/'.$v['id'].'/checkout') ?>" class="inline"><?= csrf_field() ?><button class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-orange-600 hover:bg-orange-50 text-xs font-medium transition"><i class="fa-solid fa-right-from-bracket"></i> Out</button></form>
                                <?php else: ?>
                                    <span class="text-gray-300 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$isStudent) { require VIEW_PATH . '/layouts/_pagination.php'; } ?>
</div>
