<?php /** @var array $logs @var array $modules @var string $module */ ?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <form method="get" class="flex gap-2">
        <select name="module" onchange="this.form.submit()" class="ui-input w-auto">
            <option value="">All modules</option>
            <?php foreach ($modules as $m): ?>
                <option value="<?= e($m) ?>" <?= $module===$m?'selected':'' ?>><?= ucfirst($m) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="flex items-center gap-3">
        <p class="text-sm text-gray-500">Showing latest <span class="font-semibold text-gray-700"><?= count($logs) ?></span> entries</p>
        <a href="<?= url('/export/audit') ?>" class="btn btn-ghost"><i class="fa-solid fa-file-csv"></i> Export</a>
    </div>
</div>
<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">When</th>
                    <th class="px-4 py-3 font-semibold">User</th>
                    <th class="px-4 py-3 font-semibold">Role</th>
                    <th class="px-4 py-3 font-semibold">Action</th>
                    <th class="px-4 py-3 font-semibold">Module</th>
                    <th class="px-4 py-3 font-semibold">Record</th>
                    <th class="px-4 py-3 font-semibold">Details</th>
                    <th class="px-4 py-3 font-semibold">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$logs): ?><tr><td colspan="8" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-clipboard-list text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No audit entries</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap tnum"><?= datef($l['created_at'],'d M H:i:s') ?></td>
                        <td class="px-4 py-3 text-gray-700 font-medium"><?= e($l['user_name'] ?? 'System') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e(role_label($l['role'])) ?></td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium bg-primary-50 text-primary-700"><?= e($l['action']) ?></span></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($l['module'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-400"><?= e($l['record_id'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($l['details'] ?? '') ?></td>
                        <td class="px-4 py-3 text-gray-400 font-mono text-xs"><?= e($l['ip_address'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require VIEW_PATH . '/layouts/_pagination.php'; ?>
</div>
