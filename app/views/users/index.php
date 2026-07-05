<?php /** @var array $users @var array $filters @var ?array $hostels */
use App\Core\Auth;
$filters = $filters ?? ['q' => '', 'role' => '', 'hostel' => '', 'status' => ''];
$hostels = $hostels ?? null;
$roleLabels = [
    'admin' => 'Super Admin', 'hostel_admin' => 'Hostel Admin', 'finance' => 'Finance',
    'maintenance' => 'Maintenance', 'security' => 'Security', 'student' => 'Student',
];
$roleBadge = fn (string $role): string => $roleLabels[$role] ?? ucfirst($role);
$hasFilters = ($filters['q'] ?? '') !== '' || ($filters['role'] ?? '') !== '' || ($filters['hostel'] ?? '') !== '' || ($filters['status'] ?? '') !== '';
?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($users) ?></span> user(s)<?php if ($hasFilters): ?> <span class="text-gray-400">found</span><?php endif; ?></p>
    <a href="<?= url('/users/create') ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add User</a>
</div>

<!-- Filters -->
<form method="get" action="<?= url('/users') ?>" class="ui-card p-3 mb-4 flex flex-wrap items-end gap-3" data-reveal="0">
    <div class="flex-1 min-w-[10rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Search</label>
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
            <input name="q" value="<?= e($filters['q']) ?>" placeholder="Name, username or email" class="ui-input pl-8">
        </div>
    </div>
    <div class="min-w-[9rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Role</label>
        <select name="role" class="ui-input">
            <option value="">All roles</option>
            <?php foreach ($roleLabels as $r => $label): ?>
                <option value="<?= $r ?>" <?= $filters['role']===$r?'selected':'' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($hostels !== null): ?>
    <div class="min-w-[9rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Hostel</label>
        <select name="hostel" class="ui-input">
            <option value="">All hostels</option>
            <?php foreach ($hostels as $h): ?>
                <option value="<?= (int)$h['id'] ?>" <?= (string)$filters['hostel']===(string)$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="min-w-[8rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Status</label>
        <select name="status" class="ui-input">
            <option value="">All statuses</option>
            <option value="active" <?= $filters['status']==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $filters['status']==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
    </div>
    <div class="flex items-center gap-2">
        <button class="btn btn-primary"><i class="fa-solid fa-filter"></i>Apply</button>
        <?php if ($hasFilters): ?>
            <a href="<?= url('/users') ?>" class="btn btn-ghost" title="Clear filters"><i class="fa-solid fa-xmark"></i>Clear</a>
        <?php endif; ?>
    </div>
</form>

<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Name</th>
                    <th class="px-4 py-3 font-semibold">Username</th>
                    <th class="px-4 py-3 font-semibold">Role</th>
                    <th class="px-4 py-3 font-semibold">Hostel</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$users): ?>
                    <tr><td colspan="6" class="px-4 py-14 text-center">
                        <div class="inline-flex flex-col items-center text-gray-400">
                            <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-users-gear text-xl"></i></span>
                            <p class="text-sm font-medium text-gray-500">No users yet</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full overflow-hidden bg-primary-100 text-primary-700 text-xs font-bold flex items-center justify-center shrink-0 ring-1 ring-primary-200">
                                    <?php if (!empty($u['avatar'])): ?>
                                        <img src="<?= url('/uploads/'.$u['avatar']) ?>" alt="" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?= e(strtoupper(substr($u['name'],0,1))) ?>
                                    <?php endif; ?>
                                </div>
                                <span class="font-medium text-gray-700"><?= e($u['name']) ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs"><?= e($u['username']) ?></td>
                        <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-50 text-primary-700"><?= e($roleBadge($u['role'])) ?></span></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($u['hostel_name'] ?? '— Global —') ?></td>
                        <td class="px-4 py-3"><?= status_badge((int)$u['is_active'] === 1 ? 'active' : 'inactive') ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="<?= url('/users/'.$u['id'].'/edit') ?>" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition" aria-label="Edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <?php if ((int)$u['id'] !== (int)Auth::id()): ?>
                                <form method="post" action="<?= url('/users/'.$u['id'].'/delete') ?>" class="inline" onsubmit="return confirm('Delete this user?');">
                                    <?= csrf_field() ?>
                                    <button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" aria-label="Delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
