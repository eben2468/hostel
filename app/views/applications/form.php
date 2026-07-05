<?php /** @var array $preferredRooms @var array $students */
use App\Core\Auth;
$isStaff = !Auth::hasRole('student');
$preferredRooms = $preferredRooms ?? [];
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
<form method="post" action="<?= url('/applications') ?>" class="ui-card p-6 mt-3 space-y-5 max-w-3xl">
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
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Preferred Room Type</label>
            <select name="preferred_room_type" class="ui-input" x-model="roomType" @change="$refs.roomSel.value = ''">
                <option value="">No preference</option>
                <?php foreach (['single','double','triple','quad'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Filters the preferred room list above.</p>
        </div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Medical Conditions</label>
            <input name="medical_conditions" class="ui-input"></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Special Needs</label>
            <input name="special_needs" class="ui-input"></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Remarks</label>
            <textarea name="remarks" rows="2" class="ui-input"></textarea></div>
    </div>
    <div class="flex justify-end gap-3 pt-3 border-t">
        <a href="<?= url('/applications') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary">Submit Application</button>
    </div>
</form>
