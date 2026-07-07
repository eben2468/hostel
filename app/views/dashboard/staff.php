<?php
/** @var array $stats @var array $trend @var array $recentPayments @var array $recentApps */
use App\Core\Auth;
$me = Auth::user();
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$cards = [
    ['Total Students',       (int) $stats['students'],         'fa-user-graduate', '#2b5b97', '/students',     'Enrolled residents'],
    ['Active Hostels',       (int) $stats['hostels'],          'fa-building',      '#7c3aed', '/hostels',      'Currently operating'],
    ['Available Rooms',      (int) $stats['available_rooms'],  'fa-door-open',     '#16a34a', '/rooms',        'Ready for allocation'],
    ['Pending Applications', (int) $stats['pending_apps'],     'fa-file-lines',    '#d97706', '/applications', 'Awaiting review'],
];
?>

<!-- Greeting hero -->
<div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary-700 to-primary-900 text-white p-6 sm:p-7 mb-5 shadow-pop">
    <div aria-hidden="true" class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-white/10 blur-2xl"></div>
    <div aria-hidden="true" class="absolute right-24 bottom-0 w-32 h-32 rounded-full bg-sky-300/10 blur-2xl"></div>
    <div class="relative flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-primary-200 text-sm"><?= $greet ?>,</p>
            <h2 class="text-2xl sm:text-3xl font-display font-extrabold tracking-tight"><?= e($me['name'] ?? 'Administrator') ?></h2>
            <p class="text-primary-200 text-sm mt-1"><?= date('l, j F Y') ?> · Here's what's happening across your hostels.</p>
        </div>
        <a href="<?= url('/reports') ?>" class="btn bg-white/15 hover:bg-white/25 text-white ring-1 ring-white/20 backdrop-blur">
            <i class="fa-solid fa-chart-pie"></i> View Reports
        </a>
    </div>
</div>

<!-- Stat cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <?php foreach ($cards as $i => [$label, $value, $icon, $accent, $link, $sub]): ?>
        <a href="<?= url($link) ?>" data-reveal="<?= $i ?>"
           class="ui-card ui-card-hover stat-tile p-5 flex items-start gap-4 group" style="--accent: <?= $accent ?>;">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0"
                 style="background: <?= $accent ?>1a; color: <?= $accent ?>;">
                <i class="fa-solid <?= $icon ?>"></i>
            </div>
            <div class="min-w-0">
                <p class="text-2xl font-bold text-gray-800 stat-value" data-count="<?= $value ?>">0</p>
                <p class="text-sm font-medium text-gray-600"><?= $label ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><?= $sub ?></p>
            </div>
            <i class="fa-solid fa-arrow-right ml-auto text-gray-300 group-hover:text-primary-500 group-hover:translate-x-0.5 transition self-center"></i>
        </a>
    <?php endforeach; ?>
</div>

<!-- Finance + occupancy row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
    <div class="ui-card p-5" data-reveal="0">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Revenue Today</p>
            <span class="w-8 h-8 rounded-lg bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-arrow-trend-up text-xs"></i></span>
        </div>
        <p class="text-2xl font-bold text-green-600 mt-1 tnum" data-count="<?= (float) $stats['revenue_today'] ?>" data-decimals="2" data-prefix="<?= CURRENCY_SIGN ?> ">0</p>
        <p class="text-xs text-gray-400 mt-1">This month: <span class="font-medium text-gray-500"><?= money($stats['revenue_month']) ?></span></p>
    </div>
    <div class="ui-card p-5" data-reveal="1">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Outstanding Balances</p>
            <span class="w-8 h-8 rounded-lg bg-red-50 text-red-600 flex items-center justify-center"><i class="fa-solid fa-receipt text-xs"></i></span>
        </div>
        <p class="text-2xl font-bold text-red-600 mt-1 tnum" data-count="<?= (float) $stats['outstanding'] ?>" data-decimals="2" data-prefix="<?= CURRENCY_SIGN ?> ">0</p>
        <p class="text-xs text-gray-400 mt-1"><span class="font-medium text-gray-500"><?= (int) $stats['open_complaints'] ?></span> open complaints</p>
    </div>
    <div class="ui-card p-5 flex items-center gap-5" data-reveal="2">
        <div class="relative w-24 h-24 shrink-0">
            <canvas id="occupancyGauge" aria-label="Occupancy rate gauge"></canvas>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-xl font-bold text-gray-800 tnum"><?= (int) $stats['occupancy_rate'] ?>%</span>
            </div>
        </div>
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-600">Occupancy Rate</p>
            <p class="text-xs text-gray-400 mt-1"><span class="font-semibold text-gray-700"><?= (int) $stats['occupied_rooms'] ?></span> occupied / <?= (int) $stats['rooms'] ?> rooms</p>
            <a href="<?= url('/rooms') ?>" class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:underline mt-2">Manage rooms <i class="fa-solid fa-chevron-right text-[9px]"></i></a>
        </div>
    </div>
</div>

<!-- Revenue chart -->
<div class="ui-card p-5 mt-4" data-reveal="0">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="font-display font-bold text-gray-800">Revenue Trend</h3>
            <p class="text-xs text-gray-400">Completed payments · last 6 months</p>
        </div>
        <span class="text-xs font-medium text-primary-600 bg-primary-50 px-2.5 py-1 rounded-full"><?= CURRENCY ?></span>
    </div>
    <div class="h-72"><canvas id="revenueChart"></canvas></div>
</div>

<!-- Recent activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
    <div class="ui-card p-5" data-reveal="0">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-display font-bold text-gray-800">Recent Payments</h3>
            <a href="<?= url('/payments') ?>" class="text-xs font-medium text-primary-600 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (!$recentPayments): ?>
                <p class="text-sm text-gray-400 py-6 text-center">No payments yet.</p>
            <?php endif; ?>
            <?php foreach ($recentPayments as $p): ?>
                <div class="flex items-center gap-3 py-2.5">
                    <span class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-money-bill-wave text-xs"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-700 truncate"><?= e($p['full_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= e($p['receipt_no']) ?> · <?= datef($p['paid_at']) ?></p>
                    </div>
                    <span class="text-sm font-semibold text-green-600 tnum"><?= money($p['amount']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="ui-card p-5" data-reveal="1">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-display font-bold text-gray-800">Recent Applications</h3>
            <a href="<?= url('/applications') ?>" class="text-xs font-medium text-primary-600 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (!$recentApps): ?>
                <p class="text-sm text-gray-400 py-6 text-center">No applications yet.</p>
            <?php endif; ?>
            <?php foreach ($recentApps as $a): ?>
                <div class="flex items-center gap-3 py-2.5">
                    <span class="w-9 h-9 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-lines text-xs"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-700 truncate"><?= e($a['full_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= datef($a['created_at']) ?></p>
                    </div>
                    <?= status_badge($a['status']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const B = window.CHMS.BRAND;

    // Occupancy gauge (doughnut)
    const rate = <?= (int) $stats['occupancy_rate'] ?>;
    new Chart(document.getElementById('occupancyGauge'), {
        type: 'doughnut',
        data: { datasets: [{ data: [rate, 100 - rate], backgroundColor: [B.primary, '#e2e8f0'], borderWidth: 0 }] },
        options: { cutout: '74%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });

    // Revenue area chart
    const rc = document.getElementById('revenueChart');
    const grad = window.chmsAreaGradient(rc.getContext('2d'), B.primary, 288);
    new Chart(rc, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($trend, 'label')) ?>,
            datasets: [{
                label: 'Revenue', data: <?= json_encode(array_map('floatval', array_column($trend, 'total'))) ?>,
                borderColor: B.primary, backgroundColor: grad, fill: true, tension: .4,
                borderWidth: 3, pointRadius: 4, pointBackgroundColor: '#fff', pointBorderColor: B.primary, pointBorderWidth: 2,
                pointHoverRadius: 6,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, border: { display: false }, ticks: { callback: v => '<?= CURRENCY_SIGN ?> ' + v.toLocaleString() } },
                x: { grid: { display: false }, border: { display: false } }
            }
        }
    });
});
</script>
