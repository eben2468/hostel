<?php
/** @var array $settings */
/** @var string[] $twofaRoles      roles that currently require a login code */
/** @var array $twofaEmails        role => override recipient address(es) */
/** @var bool $smtpConfigured      whether SMTP credentials are filled in */
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
                <input name="smtp_user" value="<?= $g('smtp_user') ?>" class="ui-input" placeholder="noreply@vvu.edu.gh">
                <p class="text-xs text-gray-400 mt-1">The <strong>full email address</strong>, Gmail or school — Google rejects a bare mailbox name.</p></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">SMTP Password</label>
                <input type="password" name="smtp_pass" value="<?= $g('smtp_pass') ?>" class="ui-input" autocomplete="new-password">
                <p class="text-xs text-gray-400 mt-1">Gmail: the 16-character App Password (spaces are removed automatically).</p></div>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">From Address</label>
                <input name="smtp_from" value="<?= $g('smtp_from') ?>" class="ui-input" placeholder="hostel@vvu.edu.gh">
                <p class="text-xs text-gray-400 mt-1">On Google, another address on the same domain is used as typed; a different domain becomes the Reply-To instead.</p></div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Leave blank to log emails to <code>storage/logs/mail.log</code> instead of sending.</p>

        <details class="mt-4 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
            <summary class="text-sm font-medium text-gray-700 cursor-pointer"><i class="fa-brands fa-google text-red-500 mr-1.5"></i>Using a Gmail or school (Google Workspace) account</summary>
            <ol class="text-xs text-gray-500 mt-3 space-y-1.5 list-decimal ml-4">
                <li>Sign in to the account that will send the mail — a personal Gmail, or the school account (e.g. <code>noreply@vvu.edu.gh</code>).</li>
                <li>Turn on 2-Step Verification for it at <code>myaccount.google.com/security</code>.</li>
                <li>Create an <strong>App Password</strong> (Security → App passwords) and copy the 16-character code.</li>
                <li>Host <code>smtp.gmail.com</code>, port <code>587</code>, username the <strong>full address</strong>, password the App Password — never the account password.</li>
                <li>From Address: the same account, or any other address on the <strong>same domain</strong> that Google knows as an alias. A different domain is replaced by the sign-in address and kept as the Reply-To, because Google will not send as a domain it cannot verify.</li>
            </ol>
            <p class="text-xs text-gray-500 mt-3 pt-3 border-t border-gray-200">
                <i class="fa-solid fa-graduation-cap text-primary-400 mr-1"></i><strong>School accounts:</strong>
                a Workspace administrator can switch App Passwords off for the whole organisation. If step 3 offers no
                App Password option, ask IT to allow it for this one account, or to set up the
                <code>smtp-relay.gmail.com</code> relay — both work here.
            </p>
        </details>

        <div class="flex flex-col sm:flex-row gap-2 mt-4 pt-4 border-t border-gray-100">
            <input type="email" name="test_email_to" class="ui-input flex-1" placeholder="Send a test email to…">
            <button type="submit" formaction="<?= url('/settings/test-email') ?>" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200 px-4 whitespace-nowrap">
                <i class="fa-solid fa-paper-plane"></i>Save &amp; send test
            </button>
        </div>
    </div>

    <?php
    // Two-factor authentication. The role rows drive twofa_roles (which roles are
    // challenged) and twofa_recipients (where each role's codes are delivered).
    $twofaOn = ($settings['twofa_enabled'] ?? '0') === '1';
    ?>
    <div class="ui-card p-6" data-reveal="3" x-data="{ on: <?= $twofaOn ? 'true' : 'false' ?> }">
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-shield-halved text-primary-500 text-sm"></i>Two-Factor Authentication (Email)</h3>

        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="twofa_enabled" value="1" x-model="on" <?= $twofaOn ? 'checked' : '' ?>
                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 w-4 h-4 mt-0.5">
            <span class="text-sm font-medium text-gray-700">Require an emailed code at sign-in
                <span class="block text-xs text-gray-400 font-normal mt-0.5">After the password is accepted, the selected roles must enter a <?= TWOFA_CODE_LENGTH ?>-digit code that expires in <?= TWOFA_EXPIRY_MINUTES ?> minutes.</span>
            </span>
        </label>

        <?php if (!$smtpConfigured): ?>
            <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mt-3">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>SMTP is not configured yet. Fill in the email section above and send a test first — two-factor sign-in stays off until mail works, so no one gets locked out.
            </p>
        <?php endif; ?>

        <div class="mt-5 transition-opacity" :class="on ? '' : 'opacity-50'">
            <div class="hidden sm:grid sm:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-3 px-1 pb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                <span>Role requires 2FA</span>
                <span>Send codes to</span>
            </div>
            <div class="divide-y divide-gray-100">
                <?php foreach (roles_list() as $role => $label): ?>
                    <?php $checked = in_array($role, $twofaRoles, true); ?>
                    <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] gap-2 sm:gap-3 items-center py-2.5">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="twofa_roles[]" value="<?= e($role) ?>" <?= $checked ? 'checked' : '' ?>
                                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 w-4 h-4">
                            <span class="text-sm text-gray-700"><?= e($label) ?></span>
                        </label>
                        <input type="email" multiple name="twofa_recipient[<?= e($role) ?>]"
                               value="<?= e($twofaEmails[$role] ?? '') ?>"
                               class="ui-input text-sm" placeholder="Defaults to each user's own email">
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-3">
                Leave an address blank to send each user's code to the email on their own account. Enter one or more addresses (comma separated) to route that role's codes to fixed mailboxes instead — useful for shared administrator accounts.
            </p>
        </div>
    </div>

    <div class="ui-card p-6" data-reveal="4">
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

    <div class="ui-card p-6" data-reveal="5">
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 w-4 h-4">
            <span class="text-sm font-medium text-gray-700"><i class="fa-solid fa-triangle-exclamation text-amber-500 mr-1"></i>Maintenance mode <span class="text-gray-400 font-normal">(takes the public site offline for students)</span></span>
        </label>
    </div>

    <div class="flex justify-end sticky bottom-4">
        <button class="btn btn-primary px-6 py-2.5 shadow-pop"><i class="fa-solid fa-floppy-disk"></i>Save Settings</button>
    </div>
</form>
