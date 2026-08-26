<?php
/** @var string[] $recipients addresses the code was emailed to */
/** @var int $resendWait seconds left before another code may be requested */
use App\Services\TwoFactor;
$masked = array_map([TwoFactor::class, 'mask'], $recipients);
?>
<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md reveal">
        <div class="text-center mb-7">
            <?php if ($brandLogo = brand_logo()): ?>
                <img src="<?= e($brandLogo) ?>" alt="Logo" class="inline-block w-28 h-28 object-contain drop-shadow-xl">
            <?php else: ?>
                <div class="inline-flex w-16 h-16 rounded-2xl bg-white/10 ring-1 ring-white/20 items-center justify-center shadow-lg backdrop-blur">
                    <i class="fa-solid fa-shield-halved text-2xl text-white"></i>
                </div>
            <?php endif; ?>
            <h1 class="mt-4 text-2xl font-display font-extrabold tracking-tight">Two-Step Verification</h1>
            <p class="text-primary-200 text-sm mt-1">One more step to secure your account</p>
        </div>

        <div class="bg-white text-gray-800 rounded-2xl shadow-2xl p-8">
            <div class="flex items-start gap-3 rounded-xl bg-primary-50 text-primary-800 px-4 py-3 mb-6">
                <i class="fa-solid fa-envelope-circle-check mt-0.5 text-primary-500"></i>
                <p class="text-sm leading-relaxed">
                    We emailed a <?= TWOFA_CODE_LENGTH ?>-digit code to
                    <?php foreach ($masked as $i => $address): ?>
                        <span class="font-semibold"><?= e($address) ?></span><?= $i < count($masked) - 1 ? ', ' : '' ?>
                    <?php endforeach; ?>.
                    It expires in <?= TWOFA_EXPIRY_MINUTES ?> minutes.
                </p>
            </div>

            <form method="post" action="<?= url('/login/verify') ?>" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-1.5">Verification Code</label>
                    <input id="code" name="code" required autofocus autocomplete="one-time-code"
                           inputmode="numeric" pattern="[0-9]*" maxlength="<?= TWOFA_CODE_LENGTH ?>"
                           class="ui-input text-center text-2xl font-mono tracking-[0.6em] py-3"
                           placeholder="<?= str_repeat('0', TWOFA_CODE_LENGTH) ?>"
                           oninput="this.value = this.value.replace(/\D/g, '')">
                </div>
                <button class="btn btn-primary w-full py-3 text-base">
                    <i class="fa-solid fa-shield-halved"></i>Verify &amp; Sign In
                </button>
            </form>

            <?php if (!\App\Services\Mailer::isConfigured() && APP_ENV === 'development'): ?>
                <p class="text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2 mt-4">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>SMTP is not configured, so the code was written to
                    <code>storage/logs/mail.log</code> instead of being emailed.
                </p>
            <?php endif; ?>

            <div class="flex items-center justify-between mt-6 text-sm"
                 x-data="{ wait: <?= (int) $resendWait ?> }"
                 x-init="setInterval(() => { if (wait > 0) wait--; }, 1000)">
                <form method="post" action="<?= url('/login/verify/resend') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" :disabled="wait > 0"
                            class="text-primary-600 font-medium hover:underline disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed">
                        <i class="fa-solid fa-rotate-right mr-1"></i>Resend code<span
                            x-show="wait > 0" x-cloak> (<span x-text="wait"></span>s)</span>
                    </button>
                </form>
                <a href="<?= url('/login/verify/cancel') ?>" class="text-gray-500 hover:text-gray-700">Cancel</a>
            </div>
        </div>

        <p class="text-center text-xs text-primary-200/80 mt-5">
            Didn't get the email? Check your spam folder, or contact your administrator.
        </p>
    </div>
</div>
