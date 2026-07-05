<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md reveal">
        <div class="text-center mb-7">
            <div class="inline-flex w-16 h-16 rounded-2xl bg-white/10 ring-1 ring-white/20 items-center justify-center shadow-lg backdrop-blur overflow-hidden">
                <?php if ($brandLogo = brand_logo()): ?>
                    <img src="<?= e($brandLogo) ?>" alt="Logo" class="w-full h-full object-contain p-1.5">
                <?php else: ?>
                    <i class="fa-solid fa-lock-open text-2xl text-white"></i>
                <?php endif; ?>
            </div>
            <h1 class="mt-4 text-2xl font-display font-extrabold tracking-tight">Set New Password</h1>
            <p class="text-primary-200 text-sm mt-1">Identity verified — choose a new password</p>
        </div>

        <div class="bg-white text-gray-800 rounded-2xl shadow-2xl p-8" x-data="{ show:false }">
            <form method="post" action="<?= url('/forgot-password/reset') ?>" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        <input id="password" :type="show ? 'text':'password'" name="password" required autocomplete="new-password"
                               class="ui-input pl-10 pr-10" placeholder="At least 6 characters">
                        <button type="button" @click="show=!show" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary-600 p-1" :aria-label="show ? 'Hide password' : 'Show password'">
                            <i class="fa-solid" :class="show ? 'fa-eye-slash':'fa-eye'"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="password_confirm" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        <input id="password_confirm" :type="show ? 'text':'password'" name="password_confirm" required autocomplete="new-password"
                               class="ui-input pl-10" placeholder="Re-enter password">
                    </div>
                </div>
                <button class="btn btn-primary w-full py-3 text-base"><i class="fa-solid fa-floppy-disk"></i>Save New Password</button>
            </form>
            <p class="text-center text-sm text-gray-500 mt-6">
                <a href="<?= url('/login') ?>" class="text-primary-600 font-semibold hover:underline">Back to sign in</a>
            </p>
        </div>
    </div>
</div>
