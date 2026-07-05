<?php /** @var array $stats @var array $trend @var array $methods @var array $recent @var array $topDebtors */ ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $cards = [
        ["Revenue Today", (float) $stats['revenue_today'], 'fa-calendar-day', '#16a34a'],
        ["Revenue This Month", (float) $stats['revenue_month'], 'fa-calendar', '#2b5b97'],
        ["Outstanding", (float) $stats['outstanding'], 'fa-triangle-exclamation', '#dc2626'],
        ["Total Collected", (float) $stats['collected'], 'fa-sack-dollar', '#7c3aed'],
    ];
    foreach ($cards as $i => [$label, $val, $icon, $accent]): ?>
        <div class="ui-card ui-card-hover stat-tile p-5 flex items-center gap-4" data-reveal="<?= $i ?>" style="--accent: <?= $accent ?>;">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0" style="background: <?= $accent ?>1a; color: <?= $accent ?>;"><i class="fa-solid <?= $icon ?>"></i></div>
            <div class="min-w-0">
                <p class="text-xl font-bold text-gray-800 tnum" data-count="<?= $val ?>" data-decimals="2" data-prefix="<?= CURRENCY_SYMBOL ?> ">0</p>
                <p class="text-sm text-gray-500"><?= $label ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
    <div class="ui-card p-5 lg:col-span-2" data-reveal="0">
        <h3 class="font-display font-bold text-gray-800 mb-4">Revenue Trend <span class="text-xs font-normal text-gray-400">· 6 months</span></h3>
        <div class="h-72"><canvas id="finTrend"></canvas></div>
    </div>
    <div class="ui-card p-5" data-reveal="1">
        <h3 class="font-display font-bold text-gray-800 mb-4">By Method</h3>
        <?php if ($methods): ?><div class="h-64 flex items-center justify-center"><canvas id="finMethods"></canvas></div>
        <?php else: ?><p class="text-sm text-gray-400 py-10 text-center">No payments yet.</p><?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
    <div class="ui-card p-5" data-reveal="0">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-display font-bold text-gray-800">Recent Transactions</h3>
            <a href="<?= url('/payments') ?>" class="text-xs font-medium text-primary-600 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (!$recent): ?><p class="text-sm text-gray-400 py-6 text-center">No payments.</p><?php endif; ?>
            <?php foreach ($recent as $p): ?>
                <div class="flex items-center gap-3 py-2.5">
                    <span class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-money-bill text-xs"></i></span>
                    <div class="min-w-0 flex-1"><p class="text-sm font-medium text-gray-700 truncate"><?= e($p['full_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= e($p['receipt_no']) ?> · <?= datef($p['paid_at']) ?></p></div>
                    <span class="text-sm font-semibold text-green-600 tnum"><?= money($p['amount']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="ui-card p-5" data-reveal="1">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-display font-bold text-gray-800">Top Outstanding Balances</h3>
            <a href="<?= url('/invoices') ?>" class="text-xs font-medium text-primary-600 hover:underline">Invoices</a>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (!$topDebtors): ?><p class="text-sm text-gray-400 py-6 text-center">No outstanding balances. 🎉</p><?php endif; ?>
            <?php foreach ($topDebtors as $d): ?>
                <div class="flex items-center gap-3 py-2.5">
                    <span class="w-9 h-9 rounded-full bg-red-50 text-red-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-user text-xs"></i></span>
                    <div class="min-w-0 flex-1"><p class="text-sm font-medium text-gray-700 truncate"><?= e($d['full_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= e($d['student_id']) ?></p></div>
                    <span class="text-sm font-semibold text-red-600 tnum"><?= money($d['owed']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const B = window.CHMS.BRAND, P = window.CHMS.PALETTE;
    new Chart(document.getElementById('finTrend'), {
        type:'bar',
        data:{ labels: <?= json_encode(array_column($trend,'label')) ?>,
            datasets:[{ label:'Revenue', data: <?= json_encode(array_map('floatval', array_column($trend,'total'))) ?>,
                backgroundColor: B.primary, borderRadius: 8, maxBarThickness: 46 }] },
        options:{ plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true, border:{display:false}, ticks:{ callback:v=>'<?= CURRENCY_SYMBOL ?> '+v.toLocaleString() }}, x:{grid:{display:false}, border:{display:false}} } }
    });
    <?php if ($methods): ?>
    new Chart(document.getElementById('finMethods'), {
        type:'doughnut',
        data:{ labels: <?= json_encode(array_map(fn($m)=>ucwords(str_replace('_',' ',$m['method'])), $methods)) ?>,
            datasets:[{ data: <?= json_encode(array_map(fn($m)=>(float)$m['total'], $methods)) ?>, backgroundColor: P, borderWidth: 2, borderColor: '#fff' }] },
        options:{ cutout:'62%', plugins:{legend:{position:'bottom'}} }
    });
    <?php endif; ?>
});
</script>
