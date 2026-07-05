<?php /** @var array $payments @var array $invoices @var bool $paystackEnabled @var array $feeSchedule */
$feeSchedule = $feeSchedule ?? [];
$feeLabels = ['single' => 'Single', 'double' => 'Double', 'triple' => 'Triple', 'quad' => 'Quad'];
?>
<?php if ($feeSchedule): ?>
<div class="ui-card p-5 mb-4" data-reveal="0">
    <h3 class="font-display font-bold text-gray-800 mb-1 flex items-center gap-2"><span class="inline-flex w-8 h-8 rounded-lg bg-primary-50 text-primary-600 items-center justify-center"><i class="fa-solid fa-tags text-sm"></i></span>Hostel Fees</h3>
    <p class="text-xs text-gray-400 mb-3">Standard accommodation prices for your hostel. You can pay only once your hostel admin has billed you — new charges appear under “Outstanding Invoices” below.</p>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <?php foreach ($feeSchedule as $rt => $amt): ?>
            <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-3 text-center">
                <p class="text-[11px] uppercase tracking-wider text-gray-400 font-semibold"><?= e($feeLabels[$rt] ?? ucfirst($rt)) ?></p>
                <p class="text-base font-bold text-gray-800 tnum mt-0.5"><?= money($amt) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="ui-card p-5" data-reveal="0">
        <h3 class="font-display font-bold text-gray-800 mb-3 flex items-center gap-2"><span class="inline-flex w-8 h-8 rounded-lg bg-red-50 text-red-600 items-center justify-center"><i class="fa-solid fa-file-invoice-dollar text-sm"></i></span>Outstanding Invoices</h3>
        <?php $hasUnpaid = false; ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($invoices as $inv): if ($inv['status']==='paid') continue; $hasUnpaid = true; ?>
                <div class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-700"><?= e($inv['invoice_no']) ?></p>
                        <p class="text-xs text-gray-400"><?= e($inv['description'] ?? '') ?> · due <?= datef($inv['due_date']) ?></p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-semibold text-red-600 tnum"><?= money($inv['balance']) ?></p>
                        <?php if ($paystackEnabled): ?>
                            <form method="post" action="<?= url('/invoices/'.$inv['id'].'/pay') ?>" onsubmit="return confirm('Proceed to pay <?= money($inv['balance']) ?>?')">
                                <?= csrf_field() ?>
                                <button class="btn bg-green-600 hover:bg-green-700 text-white mt-1.5 px-3 py-1.5 text-xs"><i class="fa-solid fa-credit-card"></i>Pay Now</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$hasUnpaid): ?>
                <div class="py-8 text-center">
                    <span class="inline-flex w-12 h-12 rounded-2xl bg-green-50 text-green-500 items-center justify-center mb-2"><i class="fa-solid fa-circle-check text-xl"></i></span>
                    <p class="text-sm text-gray-500 font-medium">No outstanding invoices. You're all cleared! 🎉</p>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$paystackEnabled && $hasUnpaid): ?>
            <p class="text-xs text-gray-400 mt-3 flex items-center gap-1.5"><i class="fa-solid fa-circle-info"></i>Online payment is currently unavailable. Please settle your balance at the hostel office.</p>
        <?php endif; ?>
    </div>
    <div class="ui-card p-5" data-reveal="1">
        <h3 class="font-display font-bold text-gray-800 mb-3 flex items-center gap-2"><span class="inline-flex w-8 h-8 rounded-lg bg-green-50 text-green-600 items-center justify-center"><i class="fa-solid fa-clock-rotate-left text-sm"></i></span>Payment History</h3>
        <div class="divide-y divide-gray-100">
            <?php if (!$payments): ?><p class="text-sm text-gray-400 py-6 text-center">No payments yet.</p><?php endif; ?>
            <?php foreach ($payments as $p): ?>
                <div class="flex items-center gap-3 py-3">
                    <span class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-receipt text-xs"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-700"><?= e($p['receipt_no']) ?></p>
                        <p class="text-xs text-gray-400"><?= datef($p['paid_at'],'d M Y') ?> · <?= ucfirst(str_replace('_',' ',$p['method'])) ?></p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-semibold text-green-600 tnum"><?= money($p['amount']) ?></p>
                        <a href="<?= url('/payments/'.$p['id'].'/receipt') ?>" class="text-xs text-primary-600 font-medium hover:underline">Receipt</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
