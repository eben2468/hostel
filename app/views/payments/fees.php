<?php /** @var ?array $hostels @var int $hostelId @var array $schedule @var array $roomTypes @var array $dues */
use App\Core\Scope;
use App\Models\Hostel;

$isGlobal = Scope::isGlobal();
$dues     = $dues ?? [];
$labels   = ['single' => 'Single (One in a room)', 'double' => 'Double', 'triple' => 'Triple', 'quad' => 'Quad'];

/** Current value of a dues column, ready for a form field. */
$duesVal = function (string $key) use ($dues) {
    $v = $dues[$key] ?? '';
    return $v === null ? '' : (string) $v;
};
/** Amount columns come back as "1200.00"; show them without trailing noise. */
$duesAmount = function (string $key) use ($dues) {
    $v = $dues[$key] ?? null;
    return $v === null || $v === '' ? '' : number_format((float) $v, 2, '.', '');
};
?>
<a href="<?= url('/invoices') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to invoices</a>

<?php if ($isGlobal): ?>
    <!-- Super admin: pick a hostel first (GET reload); everything below follows it. -->
    <form method="get" action="<?= url('/fees') ?>" class="ui-card p-5 mt-3 max-w-3xl">
        <label class="block text-sm font-medium text-gray-600 mb-1">Hostel</label>
        <select name="hostel_id" class="ui-input" onchange="this.form.submit()">
            <option value="">Select hostel…</option>
            <?php foreach ($hostels as $h): ?>
                <option value="<?= (int) $h['id'] ?>" <?= $hostelId === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="text-xs text-gray-400 mt-1">Room pricing and hall dues are set per hostel.</p>
    </form>
<?php endif; ?>

<?php if (!$hostelId): ?>
    <div class="ui-card p-6 mt-3 max-w-3xl">
        <?php if ($isGlobal): ?>
            <p class="text-sm text-gray-400">Choose a hostel above to set its room pricing and hall dues.</p>
        <?php else: ?>
            <p class="text-sm text-amber-600">No hostel is linked to your account.</p>
        <?php endif; ?>
    </div>
<?php else: ?>

<!-- ======================= 1. Room pricing ======================= -->
<div class="ui-card p-6 mt-3 max-w-3xl reveal">
    <div class="mb-5">
        <h3 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-tags text-primary-500 text-sm"></i>Room Pricing</h3>
        <p class="text-xs text-gray-400 mt-1">Set the accommodation price for each room type. Students see these fees, and you bill them from these prices on the New Charge page.</p>
    </div>

    <form method="post" action="<?= url('/fees') ?>" class="space-y-4">
        <?= csrf_field() ?>
        <?php if ($isGlobal): ?><input type="hidden" name="hostel_id" value="<?= (int) $hostelId ?>"><?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($roomTypes as $rt): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1"><?= e($labels[$rt] ?? ucfirst($rt)) ?></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= CURRENCY_SIGN ?></span>
                        <input type="number" step="0.01" min="0" name="price_<?= $rt ?>"
                               value="<?= isset($schedule[$rt]) ? e(number_format($schedule[$rt], 2, '.', '')) : '' ?>"
                               class="ui-input pl-12" placeholder="0.00">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-gray-400">Leave a field blank to remove that room type's price.</p>

        <div class="flex justify-end items-center gap-3 pt-3 border-t border-gray-100">
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Save Pricing</button>
        </div>
    </form>
</div>

<!-- ======================= 2. Dues notice ======================= -->
<div class="ui-card p-6 mt-4 max-w-3xl reveal" style="--d:60ms">
    <div class="mb-5">
        <h3 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-bullhorn text-primary-500 text-sm"></i>Hall Dues Notice</h3>
        <p class="text-xs text-gray-400 mt-1">Tell students what they owe this term. Freshers and continuing students often pay different amounts — set each one and add a short note explaining what it covers. Students see this on their Payments page and again when they apply for a room.</p>
    </div>

    <form method="post" action="<?= url('/fees/dues-notice') ?>" class="space-y-5">
        <?= csrf_field() ?>
        <?php if ($isGlobal): ?><input type="hidden" name="hostel_id" value="<?= (int) $hostelId ?>"><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ([
                ['fresher',    'Fresh students',      'fa-seedling',   'e.g. Covers hall dues, JCR levy and the one-off registration fee for first-year residents.'],
                ['continuing', 'Continuing students', 'fa-user-clock', 'e.g. Covers hall dues and the JCR levy. Continuing residents do not pay the registration fee again.'],
            ] as [$key, $title, $icon, $placeholder]): ?>
                <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 space-y-3">
                    <p class="text-sm font-semibold text-gray-700"><i class="fa-solid <?= $icon ?> text-primary-400 mr-1.5"></i><?= e($title) ?></p>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Amount due</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= CURRENCY_SIGN ?></span>
                            <input type="number" step="0.01" min="0" name="dues_<?= $key ?>_amount"
                                   value="<?= e($duesAmount('dues_' . $key . '_amount')) ?>"
                                   class="ui-input pl-12 bg-white" placeholder="0.00">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Note to students</label>
                        <textarea name="dues_<?= $key ?>_note" rows="4" class="ui-input bg-white text-sm"
                                  placeholder="<?= e($placeholder) ?>"><?= e($duesVal('dues_' . $key . '_note')) ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-gray-400">Leave an amount blank if that category has nothing to pay. Line breaks in a note are kept, so you can list the breakdown one item per line.</p>

        <div class="flex justify-end items-center gap-3 pt-3 border-t border-gray-100">
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Save Notice</button>
        </div>
    </form>
</div>

<!-- ======================= 3. Payment account ======================= -->
<div class="ui-card p-6 mt-4 max-w-3xl reveal" style="--d:120ms">
    <div class="mb-5">
        <h3 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-building-columns text-primary-500 text-sm"></i>Dues Payment Account</h3>
        <p class="text-xs text-gray-400 mt-1">The bank and/or mobile-money account students pay hall dues into. Once you save at least one of them, the details appear on every student's Payments page and on the room-application form, together with the Reference ID box.</p>
    </div>

    <form method="post" action="<?= url('/fees/dues-account') ?>" class="space-y-5">
        <?= csrf_field() ?>
        <?php if ($isGlobal): ?><input type="hidden" name="hostel_id" value="<?= (int) $hostelId ?>"><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 space-y-3">
                <p class="text-sm font-semibold text-gray-700"><i class="fa-solid fa-building-columns text-primary-400 mr-1.5"></i>Bank transfer</p>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Bank name</label>
                    <input name="dues_bank_name" value="<?= e($duesVal('dues_bank_name')) ?>" class="ui-input bg-white" placeholder="GCB Bank PLC"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Account name</label>
                    <input name="dues_account_name" value="<?= e($duesVal('dues_account_name')) ?>" class="ui-input bg-white" placeholder="Name the account is held in"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Account number</label>
                    <input name="dues_account_number" value="<?= e($duesVal('dues_account_number')) ?>" class="ui-input bg-white tnum" placeholder="1234567890123"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Branch</label>
                    <input name="dues_branch" value="<?= e($duesVal('dues_branch')) ?>" class="ui-input bg-white" placeholder="Campus branch"></div>
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4 space-y-3">
                <p class="text-sm font-semibold text-gray-700"><i class="fa-solid fa-mobile-screen-button text-primary-400 mr-1.5"></i>Mobile money</p>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Network</label>
                    <input name="dues_momo_network" value="<?= e($duesVal('dues_momo_network')) ?>" class="ui-input bg-white" placeholder="MTN MoMo / Telecel Cash / AT Money"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Account name</label>
                    <input name="dues_momo_name" value="<?= e($duesVal('dues_momo_name')) ?>" class="ui-input bg-white" placeholder="Name registered on the wallet"></div>
                <div><label class="block text-xs font-medium text-gray-500 mb-1">Number</label>
                    <input name="dues_momo_number" value="<?= e($duesVal('dues_momo_number')) ?>" class="ui-input bg-white tnum" placeholder="024 000 0000"></div>
                <p class="text-xs text-gray-400 pt-1">Either channel is optional — fill in the ones you actually accept.</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Payment instructions</label>
            <textarea name="dues_instructions" rows="4" class="ui-input text-sm"
                      placeholder="1. Transfer the exact amount to the account above.&#10;2. Use your Student ID as the payment narration.&#10;3. Save the Reference ID on your receipt or SMS confirmation.&#10;4. Enter that Reference ID when you apply for a room."><?= e($duesVal('dues_instructions')) ?></textarea>
            <p class="text-xs text-gray-400 mt-1">Shown to students step by step. Line breaks are preserved.</p>
        </div>

        <label class="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/50 p-4 cursor-pointer">
            <input type="checkbox" name="dues_reference_required" value="1" class="mt-0.5 rounded"
                   <?= (int) ($dues['dues_reference_required'] ?? 1) === 1 ? 'checked' : '' ?>>
            <span class="text-sm">
                <span class="font-medium text-gray-700">Require a Reference ID on room applications</span>
                <span class="block text-xs text-gray-400 mt-0.5">Students cannot submit an application without entering the reference from their dues payment. Only enforced once an account above has been saved.</span>
            </span>
        </label>

        <div class="flex justify-end items-center gap-3 pt-3 border-t border-gray-100">
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Save Account</button>
        </div>
    </form>
</div>

<!-- ======================= Preview ======================= -->
<?php if (Hostel::duesPublished($dues) || ($dues['dues_fresher_amount'] ?? null) !== null || ($dues['dues_continuing_amount'] ?? null) !== null): ?>
    <div class="max-w-3xl mt-6">
        <p class="text-[11px] uppercase tracking-wider font-semibold text-gray-400 mb-2"><i class="fa-solid fa-eye mr-1"></i>What students see</p>
        <?php require VIEW_PATH . '/partials/_dues_panel.php'; ?>
    </div>
<?php endif; ?>

<?php endif; ?>
