<?php /** @var array $applications @var bool $isStudent @var string $status @var ?bool $applicationsOpen */
use App\Core\Auth;
$applicationsOpen = $applicationsOpen ?? null;
?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-3">
        <?php if (!$isStudent): ?>
            <form method="get" class="flex gap-2">
                <select name="status" onchange="this.form.submit()" class="ui-input w-auto">
                    <option value="">All applications</option>
                    <?php foreach (['pending','approved','rejected','waiting','cancelled','expired'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php if (Auth::hasRole('hostel_admin') && $applicationsOpen !== null): ?>
            <!-- Hostel admin toggle: open/close student applications -->
            <form method="post" action="<?= url('/applications/toggle') ?>">
                <?= csrf_field() ?>
                <button type="submit" title="Click to <?= $applicationsOpen ? 'close' : 'open' ?> applications"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition
                               <?= $applicationsOpen
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                    : 'border-gray-200 bg-gray-50 text-gray-500 hover:bg-gray-100' ?>">
                    <i class="fa-solid <?= $applicationsOpen ? 'fa-toggle-on text-emerald-500' : 'fa-toggle-off text-gray-400' ?> text-base"></i>
                    Applications <?= $applicationsOpen ? 'Open' : 'Closed' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($isStudent && $applicationsOpen): ?>
        <a href="<?= url('/applications/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Application</a>
    <?php elseif (Auth::hasRole('admin','hostel_admin')): ?>
        <a href="<?= url('/applications/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Application</a>
    <?php endif; ?>
</div>

<?php if ($isStudent && !$applicationsOpen): ?>
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
        <i class="fa-solid fa-lock mt-0.5 text-amber-500"></i>
        <div class="text-sm">
            <p class="font-semibold">Applications are currently closed</p>
            <p class="text-amber-700/80">Your hostel is not accepting room applications right now. Please check back later.</p>
        </div>
    </div>
<?php endif; ?>

<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <?php if (!$isStudent): ?><th class="px-4 py-3 font-semibold">Student</th><?php endif; ?>
                    <th class="px-4 py-3 font-semibold">Preferred Hostel</th>
                    <th class="px-4 py-3 font-semibold">Preferred Room</th>
                    <th class="px-4 py-3 font-semibold">Room Type</th>
                    <th class="px-4 py-3 font-semibold">Year/Sem</th>
                    <th class="px-4 py-3 font-semibold">Submitted</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <?php if (!$isStudent): ?><th class="px-4 py-3 font-semibold text-right">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$applications): ?><tr><td colspan="8" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-file-lines text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No applications</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($applications as $a): ?>
                    <tr>
                        <?php if (!$isStudent): ?>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-700"><?= e($a['full_name']) ?></p>
                                <p class="text-xs text-gray-400"><?= e($a['student_no']) ?></p>
                            </td>
                        <?php endif; ?>
                        <td class="px-4 py-3 text-gray-500"><?= e($a['hostel_name'] ?? 'Any') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= !empty($a['preferred_room_number']) ? 'Room '.e($a['preferred_room_number']) : '—' ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= ucfirst($a['preferred_room_type'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($a['academic_year'] ?? '—') ?> <?= e($a['semester'] ?? '') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= datef($a['created_at']) ?></td>
                        <td class="px-4 py-3"><?= status_badge($a['status']) ?></td>
                        <?php if (!$isStudent): ?>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?php if ($a['status'] === 'pending' || $a['status'] === 'waiting'): ?>
                                    <form method="post" action="<?= url('/applications/'.$a['id'].'/approve') ?>" class="inline">
                                        <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-green-50 hover:text-green-600 transition" title="Approve"><i class="fa-solid fa-check"></i></button>
                                    </form>
                                    <form method="post" action="<?= url('/applications/'.$a['id'].'/waiting') ?>" class="inline">
                                        <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-yellow-50 hover:text-yellow-600 transition" title="Waiting list"><i class="fa-solid fa-clock"></i></button>
                                    </form>
                                    <form method="post" action="<?= url('/applications/'.$a['id'].'/reject') ?>" class="inline">
                                        <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                    <?php if ($a['status'] === 'pending'): ?>
                                        <a href="<?= url('/allocations/create?student='.$a['student_id']) ?>" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-primary-50 hover:text-primary-600 transition" title="Allocate"><i class="fa-solid fa-bed"></i></a>
                                    <?php endif; ?>
                                <?php elseif ($a['status'] === 'approved'): ?>
                                    <a href="<?= url('/allocations/create?student='.$a['student_id']) ?>" class="inline-flex items-center gap-1 text-primary-600 hover:underline text-xs font-medium"><i class="fa-solid fa-bed"></i> Allocate room</a>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$isStudent) { require VIEW_PATH . '/layouts/_pagination.php'; } ?>
</div>
