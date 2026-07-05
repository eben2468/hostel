<?php /** @var ?array $room @var array $hostels */
$isEdit = $room !== null;
$action = $isEdit ? url('/rooms/'.$room['id']) : url('/rooms');
$selFeatures = $room ? explode(',', (string)$room['features']) : [];
$allFeatures = ['Air Conditioner','Fan','Wardrobe','Study Desk','Balcony','Bathroom','Toilet','Water Heater','Refrigerator','Internet','Television'];
function rv(?array $r,string $k){ return e($r[$k] ?? old($k)); }
?>
<a href="<?= url('/rooms') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left mr-1"></i>Back to rooms</a>
<form method="post" action="<?= $action ?>" class="ui-card p-6 mt-3 space-y-5">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Hostel *</label>
            <select name="hostel_id" required class="ui-input">
                <option value="">Select hostel</option>
                <?php foreach ($hostels as $h): ?>
                    <option value="<?= $h['id'] ?>" <?= ($room['hostel_id']??'')==$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Room Number *</label>
            <input name="room_number" value="<?= rv($room,'room_number') ?>" required class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Room Type</label>
            <select name="room_type" class="ui-input">
                <?php foreach (['single','double','triple','quad'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($room['room_type']??'double')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Capacity (beds)</label>
            <input type="number" min="1" name="capacity" value="<?= e($room['capacity'] ?? old('capacity','2')) ?>" class="ui-input">
            <?php if ($isEdit): ?><p class="text-xs text-gray-400 mt-1">Beds are auto-created when adding new rooms.</p><?php endif; ?></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Price (<?= CURRENCY ?>)</label>
            <input type="number" step="0.01" name="price" value="<?= rv($room,'price') ?>" class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Status</label>
            <select name="status" class="ui-input">
                <?php foreach (['available','occupied','reserved','maintenance','closed'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($room['status']??'available')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select></div>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-2">Features</label>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <?php foreach ($allFeatures as $f): ?>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="features[]" value="<?= $f ?>" <?= in_array($f,$selFeatures,true)?'checked':'' ?> class="rounded border-gray-300"><?= $f ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="flex justify-end gap-3 pt-3 border-t">
        <a href="<?= url('/rooms') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary"><?= $isEdit?'Update':'Save' ?> Room</button>
    </div>
</form>
