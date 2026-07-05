<?php
/** @var array $p */
use App\Models\Setting;

$brand      = $p['hostel_name'] ?: (Setting::get('institution_name') ?: APP_NAME);
$firstName  = trim(explode(' ', trim((string) $p['full_name']))[0] ?? '');
$term       = trim(($p['academic_year'] ?? '') . ($p['semester'] ? ' · ' . $p['semester'] . ' Sem' : ''));
$balance    = isset($p['invoice_balance']) ? (float) $p['invoice_balance'] : null;
$fullyPaid  = $balance === null || $balance <= 0.001;
$verify     = strtoupper(substr(md5($p['receipt_no'] . ($p['reference'] ?? '')), 0, 12));
$issued     = trim((string) ($p['hostel_address'] ?: '')) ?: (Setting::get('institution_name') ?: APP_NAME);
?>
<div class="receipt-page min-h-screen flex items-center justify-center p-4 sm:p-6 bg-gray-100">
    <div class="receipt w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden reveal ring-1 ring-black/5">

            <!-- Header -->
            <div class="relative bg-gradient-to-br from-primary-700 via-primary-800 to-primary-900 text-white px-7 pt-8 pb-9 text-center overflow-hidden">
                <div aria-hidden="true" class="deco absolute inset-0 opacity-[0.10]" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:16px 16px;"></div>
                <div aria-hidden="true" class="deco absolute -top-10 -right-10 w-40 h-40 rounded-full bg-white/10 blur-2xl"></div>
                <div class="relative">
                    <span class="inline-flex w-16 h-16 rounded-2xl bg-white/15 ring-1 ring-white/25 items-center justify-center mb-3 shadow-lg">
                        <i class="fa-solid fa-circle-check text-3xl"></i>
                    </span>
                    <h1 class="text-xl font-display font-extrabold leading-tight tracking-tight"><?= e($brand) ?></h1>
                    <?php if ($p['hostel_code']): ?>
                        <p class="text-primary-200/90 text-[11px] uppercase tracking-[0.2em] mt-0.5"><?= e($p['hostel_code']) ?></p>
                    <?php endif; ?>
                    <div class="inline-flex items-center gap-2 mt-3 px-3 py-1 rounded-full bg-white/10 ring-1 ring-white/15 text-xs font-medium">
                        <i class="fa-solid fa-receipt text-[10px]"></i> Official Payment Receipt
                    </div>
                </div>
            </div>

            <!-- Perforation -->
            <div class="relative h-5 bg-white">
                <div class="notch absolute -left-2.5 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-gray-100"></div>
                <div class="notch absolute -right-2.5 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-gray-100"></div>
                <div class="border-t-2 border-dashed border-gray-200 mx-5 mt-2.5"></div>
            </div>

            <!-- Greeting -->
            <div class="px-7 pt-1 pb-4 text-center">
                <p class="text-sm text-gray-600">
                    Thank you<?= $firstName !== '' ? ', <span class="font-semibold text-gray-800">' . e($firstName) . '</span>' : '' ?>.
                    We've received your payment.
                </p>
            </div>

            <!-- Amount -->
            <div class="px-7">
                <div class="rounded-2xl border border-green-100 bg-green-50/70 px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] uppercase tracking-wider text-green-700/70 font-semibold">Amount Paid</p>
                        <p class="text-3xl font-display font-extrabold text-green-600 tnum leading-tight mt-0.5"><?= money($p['amount']) ?></p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white rounded-full text-xs font-bold tracking-wide shadow-sm">
                        <i class="fa-solid fa-check"></i><?= $fullyPaid ? 'PAID' : 'PART-PAID' ?>
                    </span>
                </div>
                <?php if (!$fullyPaid): ?>
                    <div class="flex justify-between items-center text-xs mt-2 px-1 text-amber-700">
                        <span class="font-medium"><i class="fa-solid fa-circle-exclamation mr-1"></i>Outstanding balance</span>
                        <span class="font-bold tnum"><?= money($balance) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Details -->
            <div class="px-7 py-5 mt-2 space-y-3 text-sm">
                <?php
                $line = function ($l, $v, $mono = false) {
                    echo '<div class="flex justify-between gap-4 items-baseline">'
                       . '<span class="text-gray-400">' . $l . '</span>'
                       . '<span class="font-semibold text-gray-800 text-right ' . ($mono ? 'font-mono text-xs' : '') . '">' . e($v) . '</span></div>';
                };
                $line('Receipt No', $p['receipt_no'], true);
                $line('Date', datef($p['paid_at'], 'd M Y · H:i'));
                $line('Student', $p['full_name']);
                $line('Student ID', $p['student_no'], true);
                if (!empty($p['programme'])) $line('Programme', $p['programme']);
                if ($term !== '')            $line('Session', $term);
                if ($p['invoice_no'])        $line('Invoice', $p['invoice_no'], true);
                if (!empty($p['description']))$line('For', $p['description']);
                $line('Method', ucwords(str_replace('_', ' ', $p['method'])));
                if ($p['reference'])         $line('Reference', $p['reference'], true);
                ?>
            </div>

            <!-- Verification / footer -->
            <div class="px-7 pb-6">
                <div class="border-t border-dashed border-gray-200 pt-4 flex items-center justify-between gap-3">
                    <div class="text-left">
                        <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">Verification</p>
                        <p class="font-mono text-xs text-gray-600 tracking-wider"><?= e($verify) ?></p>
                    </div>
                    <div class="text-right">
                        <div class="inline-block border-b border-gray-300 w-24 mb-1"></div>
                        <p class="text-[10px] text-gray-400">Authorised Signature</p>
                    </div>
                </div>
                <p class="text-center text-[11px] text-gray-400 mt-4 leading-relaxed">
                    This is a computer-generated receipt from <span class="font-medium text-gray-500"><?= e($brand) ?></span><?= $issued && $issued !== $brand ? ', ' . e($issued) : '' ?>.<br>
                    Keep it as proof of payment · Generated <?= date('d M Y · H:i') ?>
                </p>
            </div>
        </div>

        <!-- Actions (screen only) -->
        <div class="receipt-actions mt-4 flex gap-2 print:hidden">
            <a href="<?= url('/payments/'.$p['id'].'/receipt-pdf') ?>" class="btn bg-green-600 hover:bg-green-700 text-white flex-1"><i class="fa-solid fa-file-pdf"></i>Download PDF</a>
            <button onclick="window.print()" class="btn btn-primary flex-1"><i class="fa-solid fa-print"></i>Print</button>
            <a href="<?= url('/payments') ?>" class="btn btn-ghost bg-white flex-1">Done</a>
        </div>
    </div>
</div>

<style>
@media print {
    /* Strip the app chrome so only the receipt prints, on clean white. */
    html, body { background: #fff !important; }
    /* Hide the blank layout's ambient glow and any flash alerts. */
    body > [aria-hidden="true"], body > [role="alert"] { display: none !important; }
    .receipt-page { min-height: 0 !important; padding: 0 !important; background: #fff !important; }
    .receipt { max-width: 100% !important; margin: 0 auto !important; }
    .receipt .shadow-2xl { box-shadow: none !important; }
    .receipt .rounded-3xl { border-radius: 0 !important; }
    .receipt-actions, .deco { display: none !important; }
    .notch { background: #fff !important; }
    /* Preserve the header colour band and coloured accents when printing. */
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    @page { margin: 12mm; }
}
</style>
