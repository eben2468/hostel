<?php /** @var array $user @var ?array $student */
$student = $student ?? null;
$guardianMissing = $student !== null
    && (trim((string) $student['guardian_name']) === '' || trim((string) $student['guardian_phone']) === '');
/** Repopulated value after a rejected save, else what is stored. */
$val = function (string $key, $stored) {
    $old = \App\Core\Session::get('_old', []);
    return array_key_exists($key, $old) ? e($old[$key]) : e((string) $stored);
};
?>

<?php if ($guardianMissing): ?>
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800 max-w-5xl">
        <i class="fa-solid fa-user-shield mt-0.5 text-amber-500"></i>
        <div class="text-sm">
            <p class="font-semibold">Please add your parent or guardian's details</p>
            <p class="text-amber-700/80">
                The hostel needs someone to contact about you in an emergency. Fill in the
                <span class="font-medium">Parent / Guardian</span> section below and save — it only takes a moment.
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 max-w-5xl">
    <div class="ui-card p-6" data-reveal="0">
        <h3 class="font-display font-bold text-gray-800 mb-5 flex items-center gap-2"><i class="fa-solid fa-id-badge text-primary-500 text-sm"></i>Profile Information</h3>
        <form method="post" action="<?= url('/profile') ?>" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full overflow-hidden bg-gradient-to-br from-primary-500 to-primary-700 text-white text-2xl font-bold flex items-center justify-center ring-2 ring-primary-200 shrink-0">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= url('/uploads/'.$user['avatar']) ?>" alt="avatar" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= e(strtoupper(substr($user['name'],0,1))) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Profile Photo</label>
                    <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="block text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-primary-50 file:text-primary-700 file:font-medium hover:file:bg-primary-100 file:cursor-pointer">
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG or WebP, up to 2 MB.</p>
                </div>
            </div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Full Name</label>
                <input name="name" value="<?= $val('name', $user['name']) ?>" required class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Email</label>
                <input type="email" name="email" value="<?= $val('email', $user['email']) ?>" required class="ui-input">
                <p class="text-xs text-gray-400 mt-1">Must not already be used by another account.</p></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Phone</label>
                <input name="phone" value="<?= $val('phone', $user['phone']) ?>" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Role</label>
                <input value="<?= e(role_label($user['role'])) ?>" disabled class="ui-input bg-gray-50 text-gray-500 cursor-not-allowed"></div>

            <?php if ($student !== null): ?>
                <div class="pt-2 border-t border-gray-100">
                    <p class="text-sm font-semibold text-gray-700 flex items-center gap-2 mb-1">
                        <i class="fa-solid fa-user-shield text-primary-500 text-xs"></i>Parent / Guardian
                        <?php if ($guardianMissing): ?>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Needed</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-gray-400">Who the hostel should contact about you in an emergency.</p>
                </div>
                <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Guardian's Full Name</label>
                    <input name="guardian_name" value="<?= $val('guardian_name', $student['guardian_name']) ?>" required class="ui-input"></div>
                <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Guardian's Phone</label>
                    <input name="guardian_phone" value="<?= $val('guardian_phone', $student['guardian_phone']) ?>" required
                           inputmode="tel" class="ui-input" placeholder="e.g. 0244000000"></div>
                <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Relationship <span class="text-gray-400 font-normal">(optional)</span></label>
                    <select name="guardian_relationship" class="ui-input">
                        <option value="">Select relationship</option>
                        <?php $rel = $val('guardian_relationship', $student['guardian_relationship']); ?>
                        <?php foreach (['Father','Mother','Guardian','Brother','Sister','Uncle','Aunt','Other'] as $r): ?>
                            <option value="<?= $r ?>" <?= $rel === $r ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select></div>
            <?php endif; ?>

            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Save Changes</button>
        </form>
    </div>
    <div class="ui-card p-6" data-reveal="1">
        <h3 class="font-display font-bold text-gray-800 mb-5 flex items-center gap-2"><i class="fa-solid fa-lock text-primary-500 text-sm"></i>Change Password</h3>
        <form method="post" action="<?= url('/profile/password') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Current Password</label>
                <input type="password" name="current_password" required autocomplete="current-password" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">New Password</label>
                <input type="password" name="new_password" required autocomplete="new-password" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Confirm New Password</label>
                <input type="password" name="confirm_password" required autocomplete="new-password" class="ui-input"></div>
            <button class="btn bg-gray-800 hover:bg-gray-900 text-white"><i class="fa-solid fa-key"></i>Update Password</button>
        </form>
    </div>
</div>
