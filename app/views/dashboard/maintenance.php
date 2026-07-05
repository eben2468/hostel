<?php /** @var array $stats @var array $byCategory @var array $queue */ ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $cards = [
        ["Open", (int) $stats['open'], 'fa-folder-open', '#d97706'],
        ["In Progress", (int) $stats['in_progress'], 'fa-spinner', '#0284c7'],
        ["Completed", (int) $stats['completed'], 'fa-circle-check', '#16a34a'],
        ["Urgent", (int) $stats['urgent'], 'fa-fire', '#dc2626'],
    ];
    foreach ($cards as $i => [$label, $val, $icon, $accent]): ?>
        <a href="<?= url('/complaints') ?>" class="ui-card ui-card-hover stat-tile p-5 flex items-center gap-4" data-reveal="<?= $i ?>" style="--accent: <?= $accent ?>;">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0" style="background: <?= $accent ?>1a; color: <?= $accent ?>;"><i class="fa-solid <?= $icon ?>"></i></div>
            <div><p class="text-2xl font-bold text-gray-800" data-count="<?= $val ?>">0</p><p class="text-sm text-gray-500"><?= $label ?></p></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
    <div class="ui-card p-5" data-reveal="0">
        <h3 class="font-display font-bold text-gray-800 mb-4">By Category</h3>
        <?php if (!$byCategory): ?><p class="text-sm text-gray-400 py-10 text-center">No complaints.</p><?php endif; ?>
        <?php
        $max = $byCategory ? max(array_map(fn($c)=>(int)$c['cnt'], $byCategory)) : 1;
        foreach ($byCategory as $c): $w = $max>0 ? round($c['cnt']/$max*100) : 0; ?>
            <div class="mb-3">
                <div class="flex justify-between text-sm mb-1"><span class="text-gray-600"><?= ucfirst($c['category']) ?></span><span class="text-gray-400 font-medium"><?= (int)$c['cnt'] ?></span></div>
                <div class="bar-track h-2"><div class="bar-fill" data-width="<?= $w ?>" style="width:0"></div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="ui-card p-5 lg:col-span-2" data-reveal="1">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-display font-bold text-gray-800">Work Queue <span class="text-xs font-normal text-gray-400">· by priority</span></h3>
            <a href="<?= url('/complaints') ?>" class="text-xs font-medium text-primary-600 hover:underline">All complaints</a>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (!$queue): ?><p class="text-sm text-gray-400 py-6 text-center">No open work. 🎉</p><?php endif; ?>
            <?php foreach ($queue as $c): ?>
                <?php $pc = ['urgent'=>'red','high'=>'orange','medium'=>'yellow','low'=>'gray'][$c['priority']] ?? 'gray'; ?>
                <div class="flex items-center gap-3 py-2.5">
                    <span class="w-1.5 h-10 rounded-full bg-<?= $pc ?>-400 shrink-0"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-700 truncate"><?= e($c['title']) ?></p>
                        <p class="text-xs text-gray-400"><?= ucfirst($c['category']) ?> · Room <?= e($c['room_number'] ?: 'N/A') ?> · <?= datef($c['created_at']) ?></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $pc ?>-100 text-<?= $pc ?>-700"><?= ucfirst($c['priority']) ?></span>
                        <?= status_badge($c['status']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
