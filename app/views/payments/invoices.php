<?php /** @var array $invoices */ ?>
<div class="flex flex-wrap items-center justify-between gap-2 mb-4">
    <a href="<?= url('/payments') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to payments</a>
    <div class="flex flex-wrap gap-2">
        <a href="<?= url('/export/invoices') ?>" class="btn btn-ghost"><i class="fa-solid fa-file-csv"></i> Export</a>
        <?php if (\App\Core\Auth::hasRole('admin','hostel_admin')): ?>
            <a href="<?= url('/fees') ?>" class="btn btn-ghost"><i class="fa-solid fa-hand-holding-dollar"></i> Fees & Hall Dues</a>
            <a href="<?= url('/charges/create') ?>" class="btn btn-ghost"><i class="fa-solid fa-file-invoice-dollar"></i> New Charge</a>
        <?php endif; ?>
        <a href="<?= url('/payments/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Record Payment</a>
    </div>
</div>
<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Invoice #</th>
                    <th class="px-4 py-3 font-semibold">Student</th>
                    <th class="px-4 py-3 font-semibold">Description</th>
                    <th class="px-4 py-3 font-semibold">Amount</th>
                    <th class="px-4 py-3 font-semibold">Paid</th>
                    <th class="px-4 py-3 font-semibold">Balance</th>
                    <th class="px-4 py-3 font-semibold">Due</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$invoices): ?><tr><td colspan="8" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-file-invoice text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No invoices yet</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-700"><?= e($i['invoice_no']) ?></td>
                        <td class="px-4 py-3"><?= e($i['full_name']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($i['description'] ?? '—') ?></td>
                        <td class="px-4 py-3 tnum text-gray-700"><?= money($i['amount']) ?></td>
                        <td class="px-4 py-3 text-green-600 tnum"><?= money($i['amount_paid']) ?></td>
                        <td class="px-4 py-3 text-red-600 tnum font-medium"><?= money($i['balance']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= datef($i['due_date']) ?></td>
                        <td class="px-4 py-3"><?= status_badge($i['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
