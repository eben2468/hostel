<?php /** @var array $students @var array $rooms @var int $preselect @var int $preselectRoom */
$preselectRoom = $preselectRoom ?? 0;
// A preferred room that is no longer on the available list (it filled up, or
// went to maintenance) must be said out loud rather than silently ignored.
$preselectRoomAvailable = $preselectRoom
    && in_array($preselectRoom, array_map('intval', array_column($rooms, 'id')), true);
?>
<a href="<?= url('/allocations') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left mr-1"></i>Back</a>
<form method="post" action="<?= url('/allocations') ?>" class="ui-card p-6 mt-3 space-y-5 max-w-2xl">
    <?= csrf_field() ?>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Student *</label>
        <select name="student_id" required class="ui-input">
            <option value="">Select student</option>
            <?php foreach ($students as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $preselect==$s['id']?'selected':'' ?>><?= e($s['full_name']) ?> (<?= e($s['student_id']) ?>) · <?= ucfirst($s['gender']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!$students): ?><p class="text-xs text-amber-600 mt-1">All students already have active allocations.</p><?php endif; ?>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Room *</label>
        <select name="room_id" required class="ui-input">
            <option value="">Select an available room</option>
            <?php foreach ($rooms as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $preselectRoom === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['hostel_name']) ?> · Room <?= e($r['room_number']) ?> (<?= ucfirst($r['room_type']) ?>) — <?= (int)$r['occupied'] ?>/<?= (int)$r['capacity'] ?> · <?= money($r['price']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!$rooms): ?>
            <p class="text-xs text-amber-600 mt-1">No rooms with free beds available.</p>
        <?php elseif ($preselectRoomAvailable): ?>
            <p class="text-xs text-green-600 mt-1"><i class="fa-solid fa-circle-check"></i> Pre-selected from the student's application — change it if you are placing them elsewhere.</p>
        <?php elseif ($preselectRoom): ?>
            <p class="text-xs text-amber-600 mt-1"><i class="fa-solid fa-triangle-exclamation"></i> The room this student applied for is no longer free. Please choose another.</p>
        <?php endif; ?>
    </div>
    <div><label class="block text-sm font-medium text-gray-600 mb-1">Remarks</label>
        <input name="remarks" class="ui-input"></div>
    <div class="bg-indigo-50 text-indigo-700 text-sm rounded-lg p-3">
        <i class="fa-solid fa-circle-info mr-1"></i> The allocation is stamped with the hostel's active academic session. Allocating assigns the first free bed and generates an accommodation invoice for the room fee.
    </div>
    <div class="flex justify-end gap-3 pt-3 border-t">
        <a href="<?= url('/allocations') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary">Allocate Room</button>
    </div>
</form>
