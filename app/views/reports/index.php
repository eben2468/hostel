<?php /** @var array $occupancy @var array $finance @var array $methods @var array $complaints @var array $applications @var array $gender @var array $termOptions @var array $term */
$termOptions = $termOptions ?? [];
$term = $term ?? ['year' => '', 'sem' => ''];
// Query string that carries the selected term to term-aware exports.
$tq = ($term['year'] !== '' && $term['sem'] !== '') ? '?year=' . urlencode($term['year']) . '&sem=' . urlencode($term['sem']) : '';
?>
<!-- Print-only header -->
<div class="hidden print:block mb-5">
    <h1 class="text-2xl font-display font-extrabold text-gray-900"><?= e(brand_name()) ?></h1>
    <p class="text-sm text-gray-600 mt-0.5">Reports &amp; Analytics<?php if ($term['year'] !== ''): ?> · <?= e($term['year']) ?> · <?= e($term['sem']) ?> Semester<?php endif; ?></p>
    <p class="text-xs text-gray-400">Generated <?= date('d M Y · H:i') ?></p>
    <hr class="mt-3 border-gray-200">
</div>

<!-- Academic term filter -->
<form method="get" action="<?= url('/reports') ?>" class="ui-card p-3 mb-4 flex flex-wrap items-end gap-3 no-print" data-reveal="0">
    <div>
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Academic Year</label>
        <select name="year" class="ui-input w-auto">
            <?php $years = array_values(array_unique(array_map(fn($t)=>$t['academic_year'], $termOptions)));
            if ($term['year'] !== '' && !in_array($term['year'], $years, true)) $years[] = $term['year'];
            foreach ($years as $y): ?>
                <option value="<?= e($y) ?>" <?= $term['year']===$y?'selected':'' ?>><?= e($y) ?></option>
            <?php endforeach; ?>
            <?php if (!$years): ?><option value="">No data</option><?php endif; ?>
        </select>
    </div>
    <div>
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Semester</label>
        <select name="sem" class="ui-input w-auto">
            <?php foreach (['First','Second'] as $sm): ?>
                <option value="<?= $sm ?>" <?= $term['sem']===$sm?'selected':'' ?>><?= $sm ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-primary"><i class="fa-solid fa-filter"></i>Apply</button>
    <?php if ($term['year'] !== ''): ?>
        <span class="text-xs text-gray-400 ml-1">Showing finance, occupancy &amp; applications for <span class="font-semibold text-gray-600"><?= e($term['year']) ?> · <?= e($term['sem']) ?></span></span>
    <?php endif; ?>
</form>

<!-- Financial summary -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $fcards = [
        ['Total Invoiced', $finance['total_invoiced'], '#2b5b97', 'fa-file-invoice'],
        ['Total Collected', $finance['total_collected'], '#16a34a', 'fa-circle-check'],
        ['Outstanding', $finance['outstanding'], '#dc2626', 'fa-triangle-exclamation'],
        ['Refunded', $finance['refunded'], '#64748b', 'fa-rotate-left'],
    ];
    foreach ($fcards as $i => [$label, $val, $accent, $icon]): ?>
        <div class="ui-card ui-card-hover stat-tile p-5" data-reveal="<?= $i ?>" style="--accent: <?= $accent ?>;">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500"><?= $label ?></p>
                <span class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: <?= $accent ?>1a; color: <?= $accent ?>;"><i class="fa-solid <?= $icon ?> text-xs"></i></span>
            </div>
            <p class="text-xl font-bold mt-1 tnum" style="color: <?= $accent ?>;"><?= money($val) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
    <!-- Occupancy by hostel -->
    <div class="ui-card p-5" data-reveal="0">
        <h3 class="font-display font-bold text-gray-800 mb-4">Occupancy by Hostel</h3>
        <?php if (!$occupancy): ?><p class="text-sm text-gray-400 py-8 text-center">No data.</p><?php endif; ?>
        <?php foreach ($occupancy as $o): $pct = $o['capacity']>0 ? round($o['occupied']/$o['capacity']*100) : 0; ?>
            <div class="mb-3.5">
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600 font-medium"><?= e($o['name']) ?></span>
                    <span class="text-gray-400"><?= (int)$o['occupied'] ?>/<?= (int)$o['capacity'] ?> <span class="text-gray-500 font-medium">(<?= $pct ?>%)</span></span>
                </div>
                <div class="bar-track h-2.5"><div class="bar-fill" data-width="<?= $pct ?>" style="width:0"></div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Payment methods chart -->
    <div class="ui-card p-5" data-reveal="1">
        <h3 class="font-display font-bold text-gray-800 mb-4">Collections by Payment Method</h3>
        <?php if ($methods): ?><div class="h-64 flex items-center justify-center"><canvas id="methodsChart"></canvas></div>
        <?php else: ?><p class="text-sm text-gray-400 py-8 text-center">No payments yet.</p><?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
    <?php
    $miniTable = function(string $title, array $rows, string $labelKey, string $valKey, int $r) {
        echo '<div class="ui-card p-5" data-reveal="'.$r.'"><h3 class="font-display font-bold text-gray-800 mb-3">'.$title.'</h3>';
        if (!$rows) { echo '<p class="text-sm text-gray-400 py-6 text-center">No data.</p></div>'; return; }
        echo '<div class="divide-y divide-gray-100">';
        foreach ($rows as $row) {
            echo '<div class="flex justify-between py-2 text-sm"><span class="text-gray-600">'.e(ucwords(str_replace('_',' ',$row[$labelKey]))).'</span><span class="font-semibold text-gray-800 tnum">'.(int)$row[$valKey].'</span></div>';
        }
        echo '</div></div>';
    };
    $miniTable('Application Status', $applications, 'status', 'cnt', 0);
    $miniTable('Complaint Status (all-time)', $complaints, 'status', 'cnt', 1);
    $miniTable('Students by Gender (all-time)', $gender, 'gender', 'cnt', 2);
    ?>
</div>

<!-- Export & print -->
<div class="ui-card p-5 mt-4 no-print" data-reveal="0">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h3 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-file-export text-primary-500 text-sm"></i>Export &amp; Print</h3>
            <p class="text-xs text-gray-400 mt-1">Download a category as CSV<?php if ($tq): ?> (for <span class="font-medium text-gray-500"><?= e($term['year']) ?> · <?= e($term['sem']) ?></span> where applicable)<?php endif; ?>, the full summary as PDF, or print this page.</p>
        </div>
    </div>

    <?php
    // [label, url, icon, note] — term-aware categories carry $tq.
    $exports = [
        ['Students',     url('/export/students'),              'fa-user-graduate', 'All residents'],
        ['Payments',     url('/export/payments') . $tq,        'fa-money-bill-wave', 'Collections'],
        ['Invoices',     url('/export/invoices') . $tq,        'fa-file-invoice-dollar', 'Billing'],
        ['Applications', url('/export/applications') . $tq,    'fa-file-lines', 'Room requests'],
        ['Complaints',   url('/export/complaints'),            'fa-screwdriver-wrench', 'Maintenance'],
        ['Occupancy',    url('/export/occupancy') . $tq,       'fa-bed', 'Per hostel'],
    ];
    ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 mt-4">
        <?php foreach ($exports as [$label, $href, $icon, $note]): ?>
            <a href="<?= $href ?>" class="group rounded-xl border border-gray-200 hover:border-primary-300 hover:bg-primary-50/40 p-3 text-center transition">
                <span class="inline-flex w-9 h-9 rounded-lg bg-gray-100 group-hover:bg-primary-100 text-gray-500 group-hover:text-primary-600 items-center justify-center mb-1.5 transition"><i class="fa-solid <?= $icon ?> text-sm"></i></span>
                <span class="block text-sm font-semibold text-gray-700 leading-tight"><?= $label ?></span>
                <span class="block text-[10px] text-gray-400 mt-0.5"><i class="fa-solid fa-file-csv mr-0.5"></i><?= $note ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-wrap justify-end gap-2 mt-4 pt-4 border-t border-gray-100">
        <a href="<?= url('/reports/pdf') . $tq ?>" class="btn bg-green-600 hover:bg-green-700 text-white"><i class="fa-solid fa-file-pdf"></i>Financial Summary (PDF)</a>
        <button onclick="window.print()" class="btn btn-ghost border border-gray-200"><i class="fa-solid fa-print"></i>Print Report</button>
    </div>
</div>

<?php if ($methods): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new Chart(document.getElementById('methodsChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($m)=>ucwords(str_replace('_',' ',$m['method'])), $methods)) ?>,
            datasets: [{ data: <?= json_encode(array_map(fn($m)=>(float)$m['total'], $methods)) ?>, backgroundColor: window.CHMS.PALETTE, borderWidth: 2, borderColor: '#fff' }]
        },
        options: { cutout: '60%', plugins:{ legend:{ position:'bottom' } } }
    });
});
</script>
<?php endif; ?>
