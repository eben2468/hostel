<?php
/**
 * Hall-dues panel shown to students: what they owe, where to pay it, and the
 * reminder to keep the Reference ID for their room application.
 *
 * Expects (from the including view's scope):
 *   array   $dues            — App\Models\Hostel::dues() output; [] hides the panel
 *   ?string $duesStudentType — 'fresher' | 'continuing' to highlight one card
 *   bool    $duesCompact     — true drops the payment instructions block
 */
use App\Models\Hostel;

$dues = $dues ?? [];
if (!$dues || (!Hostel::duesPublished($dues)
        && ($dues['dues_fresher_amount'] ?? null) === null
        && ($dues['dues_continuing_amount'] ?? null) === null)) {
    return; // Nothing published yet — show nothing rather than an empty shell.
}

$duesStudentType = $duesStudentType ?? null;
$duesCompact     = $duesCompact ?? false;
$term = trim(($dues['academic_year'] ?? '') . ' ' . ($dues['semester'] ?? ''));

$duesCards = [
    'fresher'    => ['Fresh students',      'fa-seedling',    $dues['dues_fresher_amount'] ?? null,    $dues['dues_fresher_note'] ?? ''],
    'continuing' => ['Continuing students', 'fa-user-clock',  $dues['dues_continuing_amount'] ?? null, $dues['dues_continuing_note'] ?? ''],
];
?>
<div class="ui-card overflow-hidden mb-4" data-reveal="0">
    <div class="bg-gradient-to-r from-primary-700 to-primary-500 px-5 py-4 text-white">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="inline-flex w-9 h-9 rounded-xl bg-white/15 items-center justify-center"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                <div>
                    <h3 class="font-display font-bold leading-tight">Hall Dues<?= !empty($dues['hostel_name']) ? ' · ' . e($dues['hostel_name']) : '' ?></h3>
                    <p class="text-xs text-white/70">Pay before you apply, then submit your Reference ID.</p>
                </div>
            </div>
            <?php if ($term !== ''): ?>
                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-medium"><?= e($term) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-5 space-y-5">
        <!-- What you owe -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php foreach ($duesCards as $key => [$label, $icon, $amount, $note]): ?>
                <?php $isMine = $duesStudentType === $key; ?>
                <div class="rounded-xl border p-4 <?= $isMine ? 'border-primary-300 bg-primary-50/70 ring-1 ring-primary-200' : 'border-gray-100 bg-gray-50/60' ?>">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] uppercase tracking-wider font-semibold <?= $isMine ? 'text-primary-700' : 'text-gray-400' ?>">
                            <i class="fa-solid <?= $icon ?> mr-1"></i><?= e($label) ?>
                        </p>
                        <?php if ($isMine): ?>
                            <span class="rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-semibold text-white">You</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xl font-bold tnum <?= $amount !== null ? 'text-gray-800' : 'text-gray-300' ?>">
                        <?= $amount !== null ? money($amount) : 'Not set' ?>
                    </p>
                    <?php if (trim((string) $note) !== ''): ?>
                        <p class="mt-2 text-xs leading-relaxed text-gray-500 whitespace-pre-line"><?= e($note) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (Hostel::duesPublished($dues)): ?>
            <!-- Where to pay -->
            <div>
                <p class="text-[11px] uppercase tracking-wider font-semibold text-gray-400 mb-2">Pay into</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php if (trim((string) ($dues['dues_account_number'] ?? '')) !== ''): ?>
                        <div class="rounded-xl border border-gray-100 p-4">
                            <p class="text-xs font-semibold text-gray-600 mb-2"><i class="fa-solid fa-building-columns text-primary-500 mr-1.5"></i>Bank transfer</p>
                            <dl class="space-y-1.5 text-sm">
                                <?php foreach ([
                                    'Bank'    => $dues['dues_bank_name'] ?? '',
                                    'Account name' => $dues['dues_account_name'] ?? '',
                                    'Branch'  => $dues['dues_branch'] ?? '',
                                ] as $label => $value): ?>
                                    <?php if (trim((string) $value) !== ''): ?>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-gray-400"><?= e($label) ?></dt>
                                            <dd class="font-medium text-gray-700 text-right"><?= e($value) ?></dd>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <div class="flex items-center justify-between gap-3 pt-1.5 border-t border-gray-100">
                                    <dt class="text-gray-400">Account no.</dt>
                                    <dd class="flex items-center gap-2">
                                        <span class="font-bold tnum text-gray-800"><?= e($dues['dues_account_number']) ?></span>
                                        <button type="button" class="text-gray-300 hover:text-primary-600 transition" title="Copy account number"
                                                onclick="navigator.clipboard.writeText('<?= e($dues['dues_account_number']) ?>')"><i class="fa-regular fa-copy"></i></button>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    <?php endif; ?>

                    <?php if (trim((string) ($dues['dues_momo_number'] ?? '')) !== ''): ?>
                        <div class="rounded-xl border border-gray-100 p-4">
                            <p class="text-xs font-semibold text-gray-600 mb-2"><i class="fa-solid fa-mobile-screen-button text-primary-500 mr-1.5"></i>Mobile money</p>
                            <dl class="space-y-1.5 text-sm">
                                <?php foreach ([
                                    'Network'      => $dues['dues_momo_network'] ?? '',
                                    'Account name' => $dues['dues_momo_name'] ?? '',
                                ] as $label => $value): ?>
                                    <?php if (trim((string) $value) !== ''): ?>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-gray-400"><?= e($label) ?></dt>
                                            <dd class="font-medium text-gray-700 text-right"><?= e($value) ?></dd>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <div class="flex items-center justify-between gap-3 pt-1.5 border-t border-gray-100">
                                    <dt class="text-gray-400">Number</dt>
                                    <dd class="flex items-center gap-2">
                                        <span class="font-bold tnum text-gray-800"><?= e($dues['dues_momo_number']) ?></span>
                                        <button type="button" class="text-gray-300 hover:text-primary-600 transition" title="Copy number"
                                                onclick="navigator.clipboard.writeText('<?= e($dues['dues_momo_number']) ?>')"><i class="fa-regular fa-copy"></i></button>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$duesCompact && trim((string) ($dues['dues_instructions'] ?? '')) !== ''): ?>
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-4">
                    <p class="text-[11px] uppercase tracking-wider font-semibold text-gray-400 mb-1.5"><i class="fa-solid fa-list-check mr-1"></i>How to pay</p>
                    <p class="text-sm leading-relaxed text-gray-600 whitespace-pre-line"><?= e($dues['dues_instructions']) ?></p>
                </div>
            <?php endif; ?>

            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                <i class="fa-solid fa-receipt mt-0.5 text-amber-500"></i>
                <p class="text-sm leading-relaxed">
                    <span class="font-semibold">Keep your Reference ID.</span>
                    Your bank or mobile-money transfer gives you a reference (transaction ID) once the payment goes through.
                    You must enter it on your room application — the hostel admin checks it against this account before approving,
                    and an application with no traceable payment can be cancelled.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
