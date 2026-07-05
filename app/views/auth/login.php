<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md reveal">
        <div class="text-center mb-7">
            <div class="inline-flex w-16 h-16 rounded-2xl bg-white/10 ring-1 ring-white/20 items-center justify-center shadow-lg backdrop-blur overflow-hidden">
                <?php if ($brandLogo = brand_logo()): ?>
                    <img src="<?= e($brandLogo) ?>" alt="Logo" class="w-full h-full object-contain p-1.5">
                <?php else: ?>
                    <i class="fa-solid fa-building-columns text-3xl text-white"></i>
                <?php endif; ?>
            </div>
            <h1 class="mt-4 text-2xl font-display font-extrabold tracking-tight"><?= e(brand_name()) ?></h1>
            <p class="text-primary-200 text-sm mt-1">Sign in to access your dashboard</p>
        </div>

        <div class="bg-white text-gray-800 rounded-2xl shadow-2xl p-8" x-data="{ show:false }">
            <form method="post" action="<?= url('/login') ?>" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label for="login" class="block text-sm font-medium text-gray-700 mb-1.5">Student ID / Email</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        <input id="login" name="login" value="<?= old('login') ?>" required autofocus autocomplete="username"
                               class="ui-input pl-10" placeholder="Student ID or email">
                    </div>
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        <input id="password" :type="show ? 'text':'password'" name="password" required autocomplete="current-password"
                               class="ui-input pl-10 pr-10" placeholder="••••••••">
                        <button type="button" @click="show=!show" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary-600 p-1" :aria-label="show ? 'Hide password' : 'Show password'">
                            <i class="fa-solid" :class="show ? 'fa-eye-slash':'fa-eye'"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <label class="flex items-center gap-2 text-gray-600 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"> Remember me
                    </label>
                    <a href="<?= url('/forgot-password') ?>" class="text-primary-600 font-medium hover:underline">Forgot password?</a>
                </div>
                <button class="btn btn-primary w-full py-3 text-base">
                    <i class="fa-solid fa-right-to-bracket"></i>Sign In
                </button>
            </form>
            <p class="text-center text-sm text-gray-500 mt-6">
                New student? <a href="<?= url('/register') ?>" class="text-primary-600 font-semibold hover:underline">Create an account</a>
            </p>
        </div>
    </div>
</div>
