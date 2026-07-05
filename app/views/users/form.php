<?php /** @var ?array $user */
/** @var array $roles assignable role values */
/** @var array $hostels selectable hostels */
$isEdit = $user !== null;
$action = $isEdit ? url('/users/' . $user['id']) : url('/users');
$roleLabels = [
    'admin' => 'Super Admin (global)', 'hostel_admin' => 'Hostel Admin', 'finance' => 'Finance Officer',
    'maintenance' => 'Maintenance Officer', 'security' => 'Security Officer', 'student' => 'Student',
];
function uval(?array $u, string $k): string { return e($u[$k] ?? old($k)); }
?>
<a href="<?= url('/users') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to users</a>

<form method="post" action="<?= $action ?>" class="ui-card p-6 mt-3 space-y-7 reveal">
    <?= csrf_field() ?>

    <section>
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-id-badge text-primary-500 text-sm"></i>Account</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                <input name="name" value="<?= uval($user,'name') ?>" required class="ui-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="<?= uval($user,'email') ?>" required class="ui-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Phone</label>
                <input name="phone" value="<?= uval($user,'phone') ?>" class="ui-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Password <?= $isEdit ? '' : '<span class="text-red-500">*</span>' ?></label>
                <input type="password" name="password" <?= $isEdit ? '' : 'required' ?> class="ui-input" autocomplete="new-password" placeholder="<?= $isEdit ? 'Leave blank to keep current' : '' ?>">
            </div>
        </div>
    </section>

    <section>
        <h3 class="font-display font-bold text-gray-800 border-b border-gray-100 pb-2.5 mb-4 flex items-center gap-2"><i class="fa-solid fa-user-shield text-primary-500 text-sm"></i>Role &amp; Access</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Role</label>
                <select name="role" class="ui-input" x-data x-on:change="$dispatch('role-changed', $event.target.value)">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= e($r) ?>" <?= ($user['role'] ?? old('role'))===$r?'selected':'' ?>><?= e($roleLabels[$r] ?? ucfirst($r)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Hostel</label>
                <select name="hostel_id" class="ui-input">
                    <option value="">— Global / None —</option>
                    <?php foreach ($hostels as $h): ?>
                        <option value="<?= (int)$h['id'] ?>" <?= (string)($user['hostel_id'] ?? old('hostel_id'))===(string)$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-400 mt-1">Super Admins are global; all other roles are bound to one hostel.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1.5">Status</label>
                <select name="is_active" class="ui-input">
                    <option value="1" <?= (string)($user['is_active'] ?? '1')==='1'?'selected':'' ?>>Active</option>
                    <option value="0" <?= (string)($user['is_active'] ?? '1')==='0'?'selected':'' ?>>Inactive</option>
                </select>
            </div>
        </div>
    </section>

    <div class="flex justify-end items-center gap-3 pt-3 border-t border-gray-100">
        <a href="<?= url('/users') ?>" class="btn btn-ghost border-transparent hover:bg-gray-100">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i><?= $isEdit?'Update':'Save' ?> User</button>
    </div>
</form>
