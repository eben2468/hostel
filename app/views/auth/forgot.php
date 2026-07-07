<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md reveal" x-data="{ tab: 'self' }">
        <div class="text-center mb-7">
            <?php if ($brandLogo = brand_logo()): ?>
                <img src="<?= e($brandLogo) ?>" alt="Logo" class="inline-block w-28 h-28 object-contain drop-shadow-xl">
            <?php else: ?>
                <div class="inline-flex w-16 h-16 rounded-2xl bg-white/10 ring-1 ring-white/20 items-center justify-center shadow-lg backdrop-blur">
                    <i class="fa-solid fa-key text-2xl text-white"></i>
                </div>
            <?php endif; ?>
            <h1 class="mt-4 text-2xl font-display font-extrabold tracking-tight">Forgot Password</h1>
            <p class="text-primary-200 text-sm mt-1">Reset it yourself, or ask an admin for help</p>
        </div>

        <div class="bg-white text-gray-800 rounded-2xl shadow-2xl p-8">
            <!-- Option switch -->
            <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100 rounded-xl mb-6 text-sm font-medium">
                <button type="button" @click="tab='self'"
                        :class="tab==='self' ? 'bg-white shadow text-primary-700' : 'text-gray-500'"
                        class="rounded-lg py-2 transition">Reset myself</button>
                <button type="button" @click="tab='request'"
                        :class="tab==='request' ? 'bg-white shadow text-primary-700' : 'text-gray-500'"
                        class="rounded-lg py-2 transition">Ask an admin</button>
            </div>

            <!-- Option 1: self-service verification -->
            <form x-show="tab==='self'" method="post" action="<?= url('/forgot-password/verify') ?>" class="space-y-5">
                <?= csrf_field() ?>
                <p class="text-xs text-gray-500 -mt-1">Confirm your details to set a new password right away.</p>
                <div>
                    <label for="s-name" class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                    <input id="s-name" name="full_name" value="<?= old('full_name') ?>" required class="ui-input">
                </div>
                <div>
                    <label for="s-sid" class="block text-sm font-medium text-gray-700 mb-1.5">Student ID</label>
                    <input id="s-sid" name="student_code" value="<?= old('student_code') ?>" required class="ui-input">
                </div>
                <div>
                    <label for="s-dob" class="block text-sm font-medium text-gray-700 mb-1.5">Date of Birth</label>
                    <input id="s-dob" type="date" name="date_of_birth" value="<?= old('date_of_birth') ?>" required class="ui-input">
                </div>
                <button class="btn btn-primary w-full py-3 text-base"><i class="fa-solid fa-shield-halved"></i>Verify &amp; Continue</button>
            </form>

            <!-- Option 2: request an admin reset -->
            <form x-show="tab==='request'" x-cloak method="post" action="<?= url('/forgot-password/request') ?>" class="space-y-5">
                <?= csrf_field() ?>
                <p class="text-xs text-gray-500 -mt-1">Can't verify? Send your details for an administrator to reset your password.</p>
                <div>
                    <label for="r-name" class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                    <input id="r-name" name="full_name" value="<?= old('full_name') ?>" required class="ui-input">
                </div>
                <div>
                    <label for="r-sid" class="block text-sm font-medium text-gray-700 mb-1.5">Student ID</label>
                    <input id="r-sid" name="student_code" value="<?= old('student_code') ?>" required class="ui-input">
                </div>
                <div>
                    <label for="r-contact" class="block text-sm font-medium text-gray-700 mb-1.5">Email or Phone <span class="text-gray-400 font-normal">(so we can reach you)</span></label>
                    <input id="r-contact" name="contact" value="<?= old('contact') ?>" class="ui-input" placeholder="you@example.com / 024...">
                </div>
                <div>
                    <label for="r-msg" class="block text-sm font-medium text-gray-700 mb-1.5">Message <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea id="r-msg" name="message" rows="2" class="ui-input" placeholder="Anything the admin should know"><?= old('message') ?></textarea>
                </div>
                <button class="btn btn-primary w-full py-3 text-base"><i class="fa-solid fa-paper-plane"></i>Send Request</button>
            </form>

            <p class="text-center text-sm text-gray-500 mt-6">
                <a href="<?= url('/login') ?>" class="text-primary-600 font-semibold hover:underline">Back to sign in</a>
            </p>
        </div>
    </div>
</div>
<style>[x-cloak]{display:none}</style>
