<?php /** @var array $transfers */ ?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($transfers) ?></span> transfer record(s)</p>
    <a href="<?= url('/transfers/create') ?>" class="btn btn-primary"><i class="fa-solid fa-right-left"></i> New Transfer</a>
</div>
<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Student</th>
                    <th class="px-4 py-3 font-semibold">From (old room)</th>
                    <th class="px-4 py-3 font-semibold">Reason / Notes</th>
                    <th class="px-4 py-3 font-semibold">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$transfers): ?><tr><td colspan="4" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-right-left text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No transfers recorded</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($transfers as $t): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-700"><?= e($t['full_name']) ?></p>
                            <p class="text-xs text-gray-400"><?= e($t['student_no']) ?></p>
                        </td>
                        <td class="px-4 py-3 text-gray-500"><?= e($t['hostel_name'] ?? '') ?> · <?= e($t['room_number']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($t['remarks'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= datef($t['created_at'],'d M Y H:i') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
