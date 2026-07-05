<?php /** @var array $stats @var array $pending @var array $recent */ ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $cards = [
        ["Visitors Today", (int) $stats['today'], 'fa-calendar-day', '#2b5b97'],
        ["Pending Approval", (int) $stats['pending'], 'fa-hourglass-half', '#d97706'],
        ["Currently In", (int) $stats['checked_in'], 'fa-door-open', '#16a34a'],
        ["Blacklisted", (int) $stats['blacklisted'], 'fa-ban', '#dc2626'],
    ];
    foreach ($cards as $i => [$label, $val, $icon, $accent]): ?>
        <a href="<?= url('/visitors') ?>" class="ui-card ui-card-hover stat-tile p-5 flex items-center gap-4" data-reveal="<?= $i ?>" style="--accent: <?= $accent ?>;">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0" style="background: <?= $accent ?>1a; color: <?= $accent ?>;"><i class="fa-solid <?= $icon ?>"></i></div>
            <div><p class="text-2xl font-bold text-gray-800" data-count="<?= $val ?>">0</p><p class="text-sm text-gray-500"><?= $label ?></p></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="ui-card p-5 mt-4 overflow-hidden" data-reveal="0">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-display font-bold text-gray-800">Active Visitor Queue</h3>
        <a href="<?= url('/visitors') ?>" class="text-xs font-medium text-primary-600 hover:underline">Manage visitors</a>
    </div>
    <div class="overflow-x-auto -mx-5">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-2.5 font-semibold">Visitor</th><th class="px-3 py-2.5 font-semibold">Host</th>
                    <th class="px-3 py-2.5 font-semibold">Purpose</th><th class="px-3 py-2.5 font-semibold">Pass</th>
                    <th class="px-3 py-2.5 font-semibold">Status</th><th class="px-5 py-2.5 font-semibold text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$pending): ?><tr><td colspan="6" class="px-5 py-8 text-center text-gray-400">No active visitors.</td></tr><?php endif; ?>
                <?php foreach ($pending as $v): ?>
                    <tr>
                        <td class="px-5 py-2.5 font-medium text-gray-700"><?= e($v['visitor_name']) ?></td>
                        <td class="px-3 py-2.5 text-gray-500"><?= e($v['host_name'] ?? '—') ?></td>
                        <td class="px-3 py-2.5 text-gray-500"><?= e($v['purpose'] ?? '—') ?></td>
                        <td class="px-3 py-2.5"><span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded"><?= e($v['pass_code'] ?? '—') ?></span></td>
                        <td class="px-3 py-2.5"><?= status_badge($v['status']) ?></td>
                        <td class="px-5 py-2.5 text-right whitespace-nowrap">
                            <?php if ($v['status'] === 'pending'): ?>
                                <form method="post" action="<?= url('/visitors/'.$v['id'].'/approve') ?>" class="inline"><?= csrf_field() ?><button class="inline-flex items-center gap-1 text-green-600 hover:text-green-700 text-xs font-medium"><i class="fa-solid fa-check"></i> Approve</button></form>
                            <?php elseif ($v['status'] === 'approved'): ?>
                                <form method="post" action="<?= url('/visitors/'.$v['id'].'/checkin') ?>" class="inline"><?= csrf_field() ?><button class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 text-xs font-medium"><i class="fa-solid fa-right-to-bracket"></i> Check in</button></form>
                            <?php elseif ($v['status'] === 'checked_in'): ?>
                                <form method="post" action="<?= url('/visitors/'.$v['id'].'/checkout') ?>" class="inline"><?= csrf_field() ?><button class="inline-flex items-center gap-1 text-orange-600 hover:text-orange-700 text-xs font-medium"><i class="fa-solid fa-right-from-bracket"></i> Check out</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="ui-card p-5 mt-4" data-reveal="1">
    <h3 class="font-display font-bold text-gray-800 mb-3">Recent Visitor Log</h3>
    <div class="divide-y divide-gray-100">
        <?php if (!$recent): ?><p class="text-sm text-gray-400 py-6 text-center">No visitor history.</p><?php endif; ?>
        <?php foreach ($recent as $v): ?>
            <div class="flex items-center gap-3 py-2.5">
                <span class="w-9 h-9 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-user-check text-xs"></i></span>
                <div class="min-w-0 flex-1"><p class="text-sm font-medium text-gray-700 truncate"><?= e($v['visitor_name']) ?></p>
                    <p class="text-xs text-gray-400">Host: <?= e($v['host_name'] ?? '—') ?> · <?= datef($v['created_at'],'d M Y H:i') ?></p></div>
                <?= status_badge($v['status']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
