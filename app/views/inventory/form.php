<?php /** @var ?array $item @var array $hostels */
$isEdit = $item !== null;
$action = $isEdit ? url('/inventory/'.$item['id']) : url('/inventory');
function iv(?array $i,string $k){ return e($i[$k] ?? old($k)); }
?>
<a href="<?= url('/inventory') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left mr-1"></i>Back</a>
<form method="post" action="<?= $action ?>" class="ui-card p-6 mt-3 space-y-5 max-w-2xl">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Item Name *</label>
            <input name="name" value="<?= iv($item,'name') ?>" required class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Category</label>
            <select name="category" class="ui-input">
                <?php foreach (['bed','mattress','table','chair','wardrobe','fan','fire_extinguisher','other'] as $c): ?>
                    <option value="<?= $c ?>" <?= ($item['category']??'')===$c?'selected':'' ?>><?= ucwords(str_replace('_',' ',$c)) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Hostel</label>
            <select name="hostel_id" class="ui-input">
                <option value="">— Unassigned —</option>
                <?php foreach ($hostels as $h): ?><option value="<?= $h['id'] ?>" <?= ($item['hostel_id']??'')==$h['id']?'selected':'' ?>><?= e($h['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Quantity</label>
            <input type="number" min="0" name="quantity" value="<?= e($item['quantity'] ?? old('quantity','1')) ?>" class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Reorder Level</label>
            <input type="number" min="0" name="reorder_level" value="<?= e($item['reorder_level'] ?? old('reorder_level','0')) ?>" class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Condition</label>
            <select name="condition" class="ui-input">
                <?php foreach (['new','good','fair','damaged','replaced'] as $c): ?>
                    <option value="<?= $c ?>" <?= ($item['condition']??'good')===$c?'selected':'' ?>><?= ucfirst($c) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Notes</label>
            <input name="notes" value="<?= iv($item,'notes') ?>" class="ui-input"></div>
    </div>
    <div class="flex justify-end gap-3 pt-3 border-t">
        <a href="<?= url('/inventory') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary"><?= $isEdit?'Update':'Save' ?> Item</button>
    </div>
</form>
