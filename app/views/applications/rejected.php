<?php /** @var array $applications @var array $pager @var string $q */ ?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <div>
        <a href="<?= url('/applications') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to applications</a>
        <p class="text-sm text-gray-500 mt-1">
            <span class="font-semibold text-gray-700"><?= (int) ($pager['total'] ?? count($applications)) ?></span>
            rejected application(s)<?= $q !== '' ? ' <span class="text-gray-400">found</span>' : '' ?>
        </p>
    </div>
    <form method="get" action="<?= url('/applications/rejected') ?>" class="flex gap-2">
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
            <input name="q" value="<?= e($q) ?>" class="ui-input pl-8 w-auto min-w-[15rem]" placeholder="Student, ID, reference, room…">
        </div>
        <button class="btn btn-ghost">Search</button>
        <?php if ($q !== ''): ?>
            <a href="<?= url('/applications/rejected') ?>" class="btn btn-ghost border-transparent text-gray-500"><i class="fa-solid fa-xmark"></i></a>
        <?php endif; ?>
    </form>
</div>

<div class="mb-4 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-blue-800">
    <i class="fa-solid fa-circle-info mt-0.5 text-blue-500"></i>
    <div class="text-sm">
        <p class="font-semibold">Undoing a rejection</p>
        <p class="text-blue-700/80">
            Restoring puts the application back to <span class="font-medium">Pending</span> on the Applications page,
            clears the rejection reason and tells the student it is being looked at again. Nothing about their
            record was deleted when it was rejected.
        </p>
    </div>
</div>

<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Student</th>
                    <th class="px-4 py-3 font-semibold">Preferred</th>
                    <th class="px-4 py-3 font-semibold">Reason given</th>
                    <th class="px-4 py-3 font-semibold">Rejected</th>
                    <th class="px-4 py-3 font-semibold text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$applications): ?>
                    <tr><td colspan="5" class="px-4 py-14 text-center">
                        <div class="inline-flex flex-col items-center text-gray-400">
                            <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-inbox text-xl"></i></span>
                            <p class="text-sm font-medium text-gray-500"><?= $q !== '' ? 'No rejected applications match that search' : 'No rejected applications' ?></p>
                            <p class="text-xs text-gray-400 mt-1">Anything you reject on the Applications page appears here, ready to undo.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($applications as $a): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <a href="<?= url('/students/' . $a['student_id']) ?>" class="font-medium text-gray-700 hover:text-primary-600"><?= e($a['full_name']) ?></a>
                            <p class="text-xs text-gray-400 tnum"><?= e($a['student_no']) ?></p>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            <?= e($a['hostel_name'] ?: '—') ?>
                            <?php if (!empty($a['preferred_room_number'])): ?>
                                <p class="text-xs text-gray-400">Room <?= e($a['preferred_room_number']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-500 max-w-xs">
                            <?= $a['review_note'] ? e($a['review_note']) : '<span class="text-gray-300">No reason recorded</span>' ?>
                        </td>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?= datef($a['reviewed_at'] ?: $a['created_at'], 'd M Y') ?></td>
                        <td class="px-4 py-3 text-right">
                            <form method="post" action="<?= url('/applications/' . $a['id'] . '/restore') ?>" class="inline"
                                  onsubmit="return confirm('Restore this application to Pending? The student will be told it is being reviewed again.')">
                                <?= csrf_field() ?>
                                <button class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 hover:bg-green-50 hover:text-green-700 transition">
                                    <i class="fa-solid fa-rotate-left"></i>Restore to pending
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php require VIEW_PATH . '/layouts/_pagination.php'; ?>
</div>
