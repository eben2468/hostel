<?php /** @var array $hostels */ $hostels = $hostels ?? []; ?>
<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-lg reveal">
        <div class="text-center mb-7">
            <?php if ($brandLogo = brand_logo()): ?>
                <img src="<?= e($brandLogo) ?>" alt="Logo" class="inline-block w-28 h-28 object-contain drop-shadow-xl">
            <?php else: ?>
                <div class="inline-flex w-16 h-16 rounded-2xl bg-white/10 ring-1 ring-white/20 items-center justify-center shadow-lg backdrop-blur">
                    <i class="fa-solid fa-user-plus text-2xl text-white"></i>
                </div>
            <?php endif; ?>
            <h1 class="mt-4 text-2xl font-display font-extrabold tracking-tight">Student Registration</h1>
            <p class="text-primary-200 text-sm mt-1"><?= e(brand_name()) ?></p>
        </div>
        <div class="bg-white text-gray-800 rounded-2xl shadow-2xl p-8">
            <form method="post" action="<?= url('/register') ?>" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?= csrf_field() ?>
                <div class="sm:col-span-2">
                    <label for="r-name" class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                    <input id="r-name" name="name" value="<?= old('name') ?>" required autocomplete="name" class="ui-input">
                </div>
                <div x-data="{ sid: '<?= old('student_id') ?>' }">
                    <label for="r-sid" class="block text-sm font-medium text-gray-700 mb-1.5">Student ID</label>
                    <input id="r-sid" name="student_id" x-model="sid" @input="sid = sid.toUpperCase()"
                           required minlength="13" maxlength="13"
                           pattern="[0-9]{3}[A-Za-z]{2,4}[0-9]{6,8}"
                           title="13 characters: 3 digits, then letters, then digits — e.g. 226AB01234567"
                           placeholder="e.g. 226AB01234567"
                           autocomplete="off" spellcheck="false" class="ui-input tnum uppercase">
                    <!-- A live count, because the rule people get wrong is the length. -->
                    <p class="text-xs mt-1 flex items-center gap-1.5"
                       :class="sid.length === 13 ? 'text-green-600' : 'text-gray-400'">
                        <i class="fa-solid text-[10px]" :class="sid.length === 13 ? 'fa-circle-check' : 'fa-circle-info'"></i>
                        <span><span x-text="sid.length">0</span> of 13 characters — exactly as printed on your student card.</span>
                    </p>
                </div>
                <div>
                    <label for="r-gender" class="block text-sm font-medium text-gray-700 mb-1.5">Gender</label>
                    <select id="r-gender" name="gender" class="ui-input">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label for="r-email" class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input id="r-email" type="email" name="email" value="<?= old('email') ?>" required autocomplete="email" class="ui-input">
                </div>
                <div>
                    <label for="r-phone" class="block text-sm font-medium text-gray-700 mb-1.5">Phone</label>
                    <input id="r-phone" name="phone" value="<?= old('phone') ?>" autocomplete="tel" class="ui-input">
                </div>
                <div>
                    <label for="r-nationality" class="block text-sm font-medium text-gray-700 mb-1.5">Nationality</label>
                    <input id="r-nationality" name="nationality" value="<?= old('nationality') ?>" class="ui-input">
                </div>
                <div>
                    <label for="r-programme" class="block text-sm font-medium text-gray-700 mb-1.5">Programme</label>
                    <input id="r-programme" name="programme" value="<?= old('programme') ?>" class="ui-input">
                </div>
                <div>
                    <label for="r-department" class="block text-sm font-medium text-gray-700 mb-1.5">Department</label>
                    <input id="r-department" name="department" value="<?= old('department') ?>" class="ui-input">
                </div>
                <div>
                    <label for="r-level" class="block text-sm font-medium text-gray-700 mb-1.5">Level</label>
                    <select id="r-level" name="level" class="ui-input">
                        <option value="">Select level</option>
                        <?php foreach (['100','200','300','400'] as $lvl): ?>
                            <option value="<?= $lvl ?>" <?= old('level')===$lvl?'selected':'' ?>><?= $lvl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="r-hostel" class="block text-sm font-medium text-gray-700 mb-1.5">Hostel</label>
                    <select id="r-hostel" name="hostel_id" required class="ui-input">
                        <option value="">Select your hostel</option>
                        <?php foreach ($hostels as $h): ?>
                            <option value="<?= (int)$h['id'] ?>" <?= (string)old('hostel_id')===(string)$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">You'll be a member of this hostel.</p>
                </div>

                <!-- Next of kin: the hostel office needs someone to reach if the
                     student cannot be contacted, so both fields are required. -->
                <div class="sm:col-span-2 pt-1">
                    <p class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <i class="fa-solid fa-user-shield text-primary-500 text-xs"></i>Parent / Guardian
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">Who the hostel should contact about you in an emergency.</p>
                </div>
                <div>
                    <label for="r-gname" class="block text-sm font-medium text-gray-700 mb-1.5">Guardian's Full Name</label>
                    <input id="r-gname" name="guardian_name" value="<?= old('guardian_name') ?>" required class="ui-input">
                </div>
                <div>
                    <label for="r-gphone" class="block text-sm font-medium text-gray-700 mb-1.5">Guardian's Phone</label>
                    <input id="r-gphone" name="guardian_phone" value="<?= old('guardian_phone') ?>" required
                           inputmode="tel" autocomplete="tel" class="ui-input" placeholder="e.g. 0244000000">
                </div>
                <div class="sm:col-span-2">
                    <label for="r-grel" class="block text-sm font-medium text-gray-700 mb-1.5">Relationship <span class="text-gray-400 font-normal">(optional)</span></label>
                    <select id="r-grel" name="guardian_relationship" class="ui-input">
                        <option value="">Select relationship</option>
                        <?php foreach (['Father','Mother','Guardian','Brother','Sister','Uncle','Aunt','Other'] as $rel): ?>
                            <option value="<?= $rel ?>" <?= old('guardian_relationship')===$rel?'selected':'' ?>><?= $rel ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label for="r-pass" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <input id="r-pass" type="password" name="password" required autocomplete="new-password" class="ui-input" placeholder="At least <?= MIN_PASSWORD_LENGTH ?> characters">
                </div>
                <button class="btn btn-primary sm:col-span-2 w-full py-3 text-base mt-1">
                    <i class="fa-solid fa-user-plus"></i>Create Account
                </button>
            </form>
            <p class="text-center text-sm text-gray-500 mt-6">
                Already registered? <a href="<?= url('/login') ?>" class="text-primary-600 font-semibold hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</div>
