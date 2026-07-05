<?php /** @var ?array $hostel */
$isEdit = $hostel !== null;
$action = $isEdit ? url('/hostels/'.$hostel['id']) : url('/hostels');
$selectedFacilities = $hostel ? explode(',', (string)$hostel['facilities']) : [];
$allFacilities = ['WiFi','Kitchen','Study Room','TV Room','Laundry','Generator','Water Supply','Parking','Security','CCTV','Internet','Gym','Common Room'];
function hv(?array $h,string $k){ return e($h[$k] ?? old($k)); }
?>
<a href="<?= url('/hostels') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left mr-1"></i>Back to hostels</a>
<form method="post" action="<?= $action ?>" class="ui-card p-6 mt-3 space-y-5">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Hostel Name *</label>
            <input name="name" value="<?= hv($hostel,'name') ?>" required class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Hostel Code *</label>
            <input name="code" value="<?= hv($hostel,'code') ?>" required class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Type</label>
            <select name="type" class="ui-input">
                <?php foreach (['male','female','mixed','private','university'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($hostel['type']??'mixed')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Manager</label>
            <input name="manager" value="<?= hv($hostel,'manager') ?>" class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Digital Address</label>
            <input name="digital_address" value="<?= hv($hostel,'digital_address') ?>" class="ui-input"></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Address</label>
            <input name="address" value="<?= hv($hostel,'address') ?>" class="ui-input"></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Description</label>
            <textarea name="description" rows="2" class="ui-input"><?= hv($hostel,'description') ?></textarea></div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-600 mb-2">Facilities</label>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <?php foreach ($allFacilities as $f): ?>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="facilities[]" value="<?= $f ?>" <?= in_array($f,$selectedFacilities,true)?'checked':'' ?> class="rounded border-gray-300">
                    <?= $f ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div><label class="block text-sm font-medium text-gray-600 mb-1">Status</label>
        <select name="status" class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <?php foreach (['active','inactive','archived'] as $s): ?>
                <option value="<?= $s ?>" <?= ($hostel['status']??'active')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select></div>

    <?php if (\App\Core\Auth::hasRole('admin')): $hasSecret = !empty($hostel['paystack_secret_key']); $pEnabled = !empty($hostel['paystack_enabled']); ?>
    <div class="border-t pt-5">
        <h3 class="font-display font-bold text-gray-800 flex items-center gap-2 mb-1"><i class="fa-solid fa-credit-card text-primary-500 text-sm"></i>Payment Integration (Paystack)</h3>
        <p class="text-xs text-gray-400 mb-4">This hostel collects its own payments with its own Paystack account. Leave blank to use the system-wide keys.</p>

        <label class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 mb-4 cursor-pointer" x-data="{ on: <?= $pEnabled ? 'true' : 'false' ?> }">
            <span>
                <span class="block text-sm font-semibold text-gray-700">Enable online payment for students</span>
                <span class="block text-xs text-gray-400">When on, students see a “Pay Now” button and can pay through Paystack.</span>
            </span>
            <input type="checkbox" name="paystack_enabled" value="1" x-model="on" class="sr-only peer">
            <span class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors" :class="on ? 'bg-green-500' : 'bg-gray-300'">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform" :class="on ? 'translate-x-5' : 'translate-x-0.5'"></span>
            </span>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Public Key</label>
                <input name="paystack_public_key" value="<?= hv($hostel,'paystack_public_key') ?>" autocomplete="off" spellcheck="false" class="ui-input font-mono text-xs" placeholder="pk_live_...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">Secret Key</label>
                <input type="password" name="paystack_secret_key" value="" autocomplete="new-password" spellcheck="false" class="ui-input font-mono text-xs"
                       placeholder="<?= $hasSecret ? '•••••••• set — leave blank to keep' : 'sk_live_...' ?>">
                <p class="text-xs mt-1 <?= $hasSecret ? 'text-green-600' : 'text-gray-400' ?>">
                    <i class="fa-solid <?= $hasSecret ? 'fa-circle-check' : 'fa-circle-info' ?> mr-0.5"></i>
                    <?= $hasSecret ? 'A secret key is configured. Enter a new one only to replace it.' : 'No secret key yet — this hostel uses the system default.' ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex justify-end gap-3 pt-3 border-t">
        <a href="<?= url('/hostels') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary"><?= $isEdit?'Update':'Save' ?> Hostel</button>
    </div>
</form>
