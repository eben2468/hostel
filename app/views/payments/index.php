<?php /** @var array $payments @var float $totalToday */ ?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <div class="ui-card stat-tile px-5 py-3 flex items-center gap-3" style="--accent:#16a34a;" data-reveal="0">
        <span class="w-10 h-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-sack-dollar"></i></span>
        <div>
            <p class="text-xs text-gray-500">Collected today</p>
            <p class="font-bold text-green-600 tnum text-lg" data-count="<?= (float) $totalToday ?>" data-decimals="2" data-prefix="<?= CURRENCY_SYMBOL ?> ">0</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= url('/export/payments') ?>" class="btn btn-ghost"><i class="fa-solid fa-file-csv"></i> Export</a>
        <a href="<?= url('/invoices') ?>" class="btn btn-ghost"><i class="fa-solid fa-file-invoice"></i> Invoices</a>
        <?php if (\App\Core\Auth::hasRole('admin','hostel_admin')): ?>
            <a href="<?= url('/fees') ?>" class="btn btn-ghost"><i class="fa-solid fa-tags"></i> Room Pricing</a>
            <a href="<?= url('/charges/create') ?>" class="btn btn-ghost"><i class="fa-solid fa-file-invoice-dollar"></i> New Charge</a>
        <?php endif; ?>
        <a href="<?= url('/payments/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Record Payment</a>
    </div>
</div>
<div class="ui-card overflow-hidden" data-reveal="1">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Receipt</th>
                    <th class="px-4 py-3 font-semibold">Student</th>
                    <th class="px-4 py-3 font-semibold">Invoice</th>
                    <th class="px-4 py-3 font-semibold">Method</th>
                    <th class="px-4 py-3 font-semibold">Amount</th>
                    <th class="px-4 py-3 font-semibold">Date</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$payments): ?><tr><td colspan="8" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-money-bill-wave text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No payments recorded</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td class="px-4 py-3"><span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded"><?= e($p['receipt_no']) ?></span></td>
                        <td class="px-4 py-3 font-medium text-gray-700"><?= e($p['full_name']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($p['invoice_no'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= ucfirst(str_replace('_',' ',$p['method'])) ?></td>
                        <td class="px-4 py-3 font-semibold text-green-600 tnum"><?= money($p['amount']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= datef($p['paid_at'],'d M Y H:i') ?></td>
                        <td class="px-4 py-3"><?= status_badge($p['status']) ?></td>
                        <td class="px-4 py-3 text-right"><a href="<?= url('/payments/'.$p['id'].'/receipt') ?>" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-primary-50 hover:text-primary-600 transition" title="Receipt" aria-label="View receipt"><i class="fa-solid fa-receipt"></i></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require VIEW_PATH . '/layouts/_pagination.php'; ?>
</div>
