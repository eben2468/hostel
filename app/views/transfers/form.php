<?php /** @var array $active @var array $rooms @var int $preselect */ ?>
<a href="<?= url('/transfers') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left mr-1"></i>Back</a>
<form method="post" action="<?= url('/transfers') ?>" class="ui-card p-6 mt-3 space-y-5 max-w-2xl">
    <?= csrf_field() ?>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Student to transfer *</label>
        <select name="allocation_id" required class="ui-input">
            <option value="">Select an active allocation</option>
            <?php foreach ($active as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $preselect==$a['id']?'selected':'' ?>>
                    <?= e($a['full_name']) ?> (<?= e($a['student_no']) ?>) — currently <?= e($a['hostel_name'] ?? '') ?> Room <?= e($a['room_number']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (!$active): ?><p class="text-xs text-amber-600 mt-1">No active allocations to transfer.</p><?php endif; ?>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">New Room *</label>
        <select name="new_room_id" required class="ui-input">
            <option value="">Select target room (with free bed)</option>
            <?php foreach ($rooms as $r): ?>
                <option value="<?= $r['id'] ?>"><?= e($r['hostel_name']) ?> · Room <?= e($r['room_number']) ?> (<?= ucfirst($r['room_type']) ?>) — <?= (int)$r['occupied'] ?>/<?= (int)$r['capacity'] ?> · <?= money($r['price']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Reason</label>
        <select name="reason" class="ui-input">
            <?php foreach (['Medical','Upgrade','Downgrade','Maintenance','Conflict Resolution','Personal Request'] as $r): ?>
                <option value="<?= $r ?>"><?= $r ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="bg-indigo-50 text-indigo-700 text-sm rounded-lg p-3">
        <i class="fa-solid fa-circle-info mr-1"></i> The student's old bed is released, the previous allocation is marked <em>transferred</em>, and a new bed is assigned in the target room.
    </div>
    <div class="flex justify-end gap-3 pt-3 border-t">
        <a href="<?= url('/transfers') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary">Transfer Student</button>
    </div>
</form>
