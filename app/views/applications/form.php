<?php /** @var array $preferredRooms @var array $students @var array $dues @var ?string $studentType */
use App\Core\Auth;
use App\Models\Hostel;

$isStaff = !Auth::hasRole('student');
$preferredRooms = $preferredRooms ?? [];
$dues = $dues ?? [];
// The panel highlights the card matching the applicant's category.
$duesStudentType = $studentType ?? null;
$refRequired = !$isStaff && Hostel::duesReferenceRequired($dues);
// Room data for the client-side "filter by room type" dropdown.
$roomData = array_map(fn($r) => [
    'id'    => (int) $r['id'],
    'type'  => $r['room_type'],
    'label' => 'Room ' . $r['room_number'] . ' · ' . ucfirst($r['room_type'])
             . ($isStaff && !empty($r['hostel_name']) ? ' · ' . $r['hostel_name'] : '')
             . ' · ' . money($r['price']) . ' · ' . ((int) $r['capacity'] - (int) $r['occupied']) . ' bed(s) free',
], $preferredRooms);
?>
<a href="<?= url('/applications') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left mr-1"></i>Back</a>

<div class="max-w-3xl mt-3">
    <?php require VIEW_PATH . '/partials/_dues_panel.php'; ?>
</div>

<form method="post" action="<?= url('/applications') ?>" class="ui-card p-6 space-y-5 max-w-3xl">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"
         x-data="{ roomType: '', rooms: <?= htmlspecialchars(json_encode($roomData), ENT_QUOTES) ?> }">
        <?php if ($isStaff): ?>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Student *</label>
                <select name="student_id" required class="ui-input">
                    <option value="">Select student</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['full_name']) ?> (<?= e($s['student_id']) ?>)</option>
                    <?php endforeach; ?>
                </select></div>
        <?php endif; ?>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Preferred Room Type</label>
            <select name="preferred_room_type" class="ui-input" x-model="roomType" @change="$refs.roomSel.value = ''">
                <option value="">No preference</option>
                <?php foreach (['single','double','triple','quad'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Filters the preferred room list.</p>
        </div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Preferred Room</label>
            <select name="preferred_room_id" class="ui-input" x-ref="roomSel">
                <option value="">No preference</option>
                <template x-for="r in (roomType ? rooms.filter(x => x.type === roomType) : rooms)" :key="r.id">
                    <option :value="r.id" x-text="r.label"></option>
                </template>
            </select>
            <?php if (!$isStaff && !$preferredRooms): ?><p class="text-xs text-gray-400 mt-1">No rooms currently available in your hostel — you can still apply without a preference.</p><?php endif; ?>
            <p x-cloak style="display:none" x-show="roomType && rooms.filter(x => x.type === roomType).length === 0" class="text-xs text-amber-600 mt-1">No <span x-text="roomType"></span> rooms available right now — pick another type or leave as “No preference”.</p>
        </div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Medical Conditions</label>
            <input name="medical_conditions" value="<?= old('medical_conditions') ?>" class="ui-input"></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Special Needs</label>
            <input name="special_needs" value="<?= old('special_needs') ?>" class="ui-input"></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Remarks</label>
            <textarea name="remarks" rows="2" class="ui-input"><?= old('remarks') ?></textarea></div>
    </div>

    <!-- ---------------- Hall dues payment proof ---------------- -->
    <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-5 space-y-4">
        <div>
            <h4 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-receipt text-primary-500 text-sm"></i>Hall Dues Payment</h4>
            <p class="text-xs text-gray-500 mt-1">
                <?php if ($isStaff): ?>
                    Enter the reference from the student's dues payment if they have one. You can also leave it blank and add it later once they produce a receipt.
                <?php else: ?>
                    Pay your hall dues into the account shown above, then enter the Reference ID your bank or mobile-money transfer gave you.
                    A hostel admin checks every reference against the account — if no payment is found, your application can be cancelled.
                <?php endif; ?>
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">I am a</label>
                <select name="student_type" class="ui-input bg-white">
                    <?php if ($duesStudentType === null): ?><option value="">Select category</option><?php endif; ?>
                    <?php foreach (Hostel::STUDENT_TYPES as $key => $label): ?>
                        <option value="<?= $key ?>" <?= old('student_type', $duesStudentType) === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">Decides which dues amount applies to you.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">
                    Payment Reference ID <?= $refRequired ? '<span class="text-red-500">*</span>' : '<span class="text-gray-400 font-normal">(optional)</span>' ?>
                </label>
                <input name="payment_reference" value="<?= old('payment_reference') ?>" class="ui-input bg-white tnum"
                       placeholder="e.g. TRX-8842019PQ" maxlength="80" <?= $refRequired ? 'required' : '' ?>>
                <p class="text-xs text-gray-400 mt-1">The transaction ID on your receipt or confirmation SMS.</p>
            </div>
        </div>

        <?php if ($refRequired): ?>
            <p class="text-xs text-amber-700 flex items-start gap-1.5">
                <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
                Enter the reference exactly as it appears on your receipt. A wrong or reused reference will not be traceable and the application will be cancelled.
            </p>
        <?php endif; ?>
    </div>

    <div class="flex justify-end gap-3 pt-3 border-t">
        <a href="<?= url('/applications') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary">Submit Application</button>
    </div>
</form>
