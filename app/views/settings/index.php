<?php /** @var array $settings */
$g = fn(string $k, string $d='') => e($settings[$k] ?? $d);
?>
<form method="post" action="<?= url('/settings') ?>" enctype="multipart/form-data" class="max-w-3xl space-y-4">
    <?= csrf_field() ?>

    <div class="ui-card p-6" data-reveal="0">
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-building-columns text-primary-500 text-sm"></i>Branding &amp; Institution</h3>

        <!-- Logo -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-5" x-data="{ preview: '<?= brand_logo() ? e(brand_logo()) : '' ?>' }">
            <div class="w-20 h-20 rounded-2xl bg-gray-100 ring-1 ring-gray-200 flex items-center justify-center overflow-hidden shrink-0">
                <template x-if="preview"><img :src="preview" alt="Logo preview" class="w-full h-full object-contain p-1.5"></template>
                <template x-if="!preview"><i class="fa-solid fa-image text-2xl text-gray-300"></i></template>
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-600 mb-1">System Logo</label>
                <input type="file" name="system_logo" accept="image/png,image/jpeg,image/webp"
                       @change="const f=$event.target.files[0]; if(f) preview=URL.createObjectURL(f)"
                       class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 cursor-pointer">
                <p class="text-xs text-gray-400 mt-1">PNG, JPG or WebP up to 2&nbsp;MB. Shown in the sidebar and on the login &amp; sign-up pages.</p>
                <?php if (brand_logo()): ?>
                    <label class="inline-flex items-center gap-2 text-xs text-red-500 mt-2 cursor-pointer">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300" @change="if($event.target.checked) preview=''">
                        Remove current logo
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">System / Institution Name</label>
                <input name="institution_name" value="<?= $g('institution_name') ?>" class="ui-input" placeholder="<?= APP_NAME ?>">
                <p class="text-xs text-gray-400 mt-1">Appears on the header/navbar, login, sign-up and receipts.</p></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">Currency</label>
                <input name="currency" value="<?= $g('currency', CURRENCY) ?>" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">Academic Year</label>
                <input name="academic_year" value="<?= $g('academic_year','2025/2026') ?>" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">Semester</label>
                <input name="semester" value="<?= $g('semester','First') ?>" class="ui-input"></div>
        </div>
    </div>

    <div class="ui-card p-6" data-reveal="1">
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-credit-card text-primary-500 text-sm"></i>Payment Gateway (Paystack)</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-600 mb-1">Public Key</label>
                <input name="paystack_public_key" value="<?= $g('paystack_public_key') ?>" class="ui-input font-mono" placeholder="pk_test_..."></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">Secret Key</label>
                <input name="paystack_secret_key" value="<?= $g('paystack_secret_key') ?>" type="password" class="ui-input font-mono" placeholder="sk_test_..."></div>
        </div>
    </div>

    <div class="ui-card p-6" data-reveal="2">
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-envelope text-primary-500 text-sm"></i>Email (SMTP)</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-600 mb-1">SMTP Host</label>
                <input name="smtp_host" value="<?= $g('smtp_host') ?>" class="ui-input" placeholder="smtp.gmail.com"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">SMTP Port</label>
                <input name="smtp_port" value="<?= $g('smtp_port','587') ?>" class="ui-input" placeholder="587 (TLS) or 465 (SSL)"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">SMTP Username</label>
                <input name="smtp_user" value="<?= $g('smtp_user') ?>" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">SMTP Password</label>
                <input type="password" name="smtp_pass" value="<?= $g('smtp_pass') ?>" class="ui-input"></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">From Address</label>
                <input name="smtp_from" value="<?= $g('smtp_from') ?>" class="ui-input" placeholder="no-reply@yourhostel.edu"></div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Leave blank to log emails to <code>storage/logs/mail.log</code> instead of sending.</p>
    </div>

    <div class="ui-card p-6" data-reveal="3">
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-comment-sms text-primary-500 text-sm"></i>SMS</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div><label class="block text-sm font-medium text-gray-600 mb-1">Provider</label>
                <select name="sms_provider" class="ui-input">
                    <?php foreach (['arkesel'=>'Arkesel','hubtel'=>'Hubtel'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= ($settings['sms_provider']??'arkesel')===$k?'selected':'' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">Sender ID</label>
                <input name="sms_sender" value="<?= $g('sms_sender') ?>" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">API Key</label>
                <input type="password" name="sms_api_key" value="<?= $g('sms_api_key') ?>" class="ui-input"></div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Leave blank to log SMS to <code>storage/logs/sms.log</code> instead of sending.</p>
    </div>

    <div class="ui-card p-6" data-reveal="4">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 w-4 h-4">
            <span class="text-sm font-medium text-gray-700"><i class="fa-solid fa-triangle-exclamation text-amber-500 mr-1"></i>Maintenance mode <span class="text-gray-400 font-normal">(takes the public site offline for students)</span></span>
        </label>
    </div>

    <div class="flex justify-end sticky bottom-4">
        <button class="btn btn-primary px-6 py-2.5 shadow-pop"><i class="fa-solid fa-floppy-disk"></i>Save Settings</button>
    </div>
</form>
