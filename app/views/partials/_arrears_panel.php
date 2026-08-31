<?php
/**
 * Blocking notice shown to a student who still owes dues from a past semester.
 *
 * Expects (from the including view's scope):
 *   array $arrears — App\Models\DuesDebtor::outstandingFor() rows; [] hides it
 */
use App\Models\DuesDebtor;

$arrears = $arrears ?? [];
if (!$arrears) {
    return;
}
$owed = DuesDebtor::totalOwed($arrears);
?>
<div class="ui-card overflow-hidden mb-4 border-red-200" data-reveal="0">
    <div class="bg-gradient-to-r from-red-700 to-red-500 px-5 py-4 text-white">
        <div class="flex items-center gap-3">
            <span class="inline-flex w-9 h-9 rounded-xl bg-white/15 items-center justify-center"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <div>
                <h3 class="font-display font-bold leading-tight">Outstanding hall dues</h3>
                <p class="text-xs text-white/80">You cannot apply for a room until these are settled.</p>
            </div>
        </div>
    </div>

    <div class="p-5 space-y-4">
        <p class="text-sm text-gray-600 leading-relaxed">
            Our records show unpaid hall dues carried over from
            <?= count($arrears) === 1 ? 'a previous semester' : 'previous semesters' ?>.
            Please settle <?= count($arrears) === 1 ? 'it' : 'them' ?> at the hostel office, or pay into the hall
            dues account and take your receipt to the office so your record can be updated.
        </p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                    <tr class="border-b border-gray-100">
                        <th class="py-2 pr-4 font-semibold">Semester</th>
                        <th class="py-2 pr-4 font-semibold">Room</th>
                        <th class="py-2 font-semibold text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($arrears as $d): ?>
                        <tr>
                            <td class="py-2 pr-4 text-gray-700"><?= e(DuesDebtor::termLabel($d)) ?></td>
                            <td class="py-2 pr-4 text-gray-500"><?= e($d['room_label'] ?: '—') ?></td>
                            <td class="py-2 text-right font-semibold text-red-600 tnum">
                                <?= $d['amount'] !== null ? money($d['amount']) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($owed > 0): ?>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td class="py-2 pr-4 font-semibold text-gray-700" colspan="2">Total outstanding</td>
                            <td class="py-2 text-right font-bold text-red-700 tnum"><?= money($owed) ?></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <p class="text-xs text-gray-400 flex items-start gap-1.5">
            <i class="fa-solid fa-circle-info mt-0.5"></i>
            Already paid? Take your receipt to the hostel office — an admin marks the debt settled and
            you can apply straight away. If you believe this is not you, contact the office with your Student ID.
        </p>
    </div>
</div>
