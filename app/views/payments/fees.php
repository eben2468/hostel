<?php /** @var ?array $hostels @var int $hostelId @var array $schedule @var array $roomTypes */
use App\Core\Scope;
$isGlobal = Scope::isGlobal();
$labels = ['single' => 'Single (One in a room)', 'double' => 'Double', 'triple' => 'Triple', 'quad' => 'Quad'];
?>
<a href="<?= url('/invoices') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to invoices</a>

<div class="ui-card p-6 mt-3 max-w-2xl reveal">
    <div class="mb-5">
        <h3 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-tags text-primary-500 text-sm"></i>Room Pricing</h3>
        <p class="text-xs text-gray-400 mt-1">Set the accommodation price for each room type. Students see these fees, and you bill them from these prices on the New Charge page.</p>
    </div>

    <?php if ($isGlobal): ?>
        <!-- Super admin: pick a hostel first (GET reload). -->
        <form method="get" action="<?= url('/fees') ?>" class="mb-5">
            <label class="block text-sm font-medium text-gray-600 mb-1">Hostel</label>
            <div class="flex gap-2">
                <select name="hostel_id" class="ui-input" onchange="this.form.submit()">
                    <option value="">Select hostel…</option>
                    <?php foreach ($hostels as $h): ?>
                        <option value="<?= (int)$h['id'] ?>" <?= $hostelId===(int)$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($hostelId): ?>
        <form method="post" action="<?= url('/fees') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <?php if ($isGlobal): ?><input type="hidden" name="hostel_id" value="<?= (int)$hostelId ?>"><?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach ($roomTypes as $rt): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1"><?= e($labels[$rt] ?? ucfirst($rt)) ?></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= CURRENCY_SYMBOL ?></span>
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
    <?php elseif ($isGlobal): ?>
        <p class="text-sm text-gray-400">Choose a hostel above to set its room pricing.</p>
    <?php else: ?>
        <p class="text-sm text-amber-600">No hostel is linked to your account.</p>
    <?php endif; ?>
</div>
