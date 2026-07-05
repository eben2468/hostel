<?php /** @var array $user */ ?>
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
                <input name="name" value="<?= e($user['name']) ?>" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Email</label>
                <input type="email" name="email" value="<?= e($user['email']) ?>" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Phone</label>
                <input name="phone" value="<?= e($user['phone']) ?>" class="ui-input"></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1.5">Role</label>
                <input value="<?= e(role_label($user['role'])) ?>" disabled class="ui-input bg-gray-50 text-gray-500 cursor-not-allowed"></div>
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
