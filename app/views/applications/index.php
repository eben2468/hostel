<?php /** @var array $applications @var bool $isStudent @var string $status @var ?bool $applicationsOpen @var array $dues */
use App\Core\Auth;
$applicationsOpen = $applicationsOpen ?? null;
$dues = $dues ?? [];
$arrears = $arrears ?? []; // only populated for a student; staff never see the panel
$filters = $filters ?? ['q' => '', 'status' => '', 'payment' => '', 'hostel' => '', 'term' => ''];
$hostels = $hostels ?? null;   // null = hostel-bound admin, no hostel picker
$terms   = $terms ?? [];
$hasFilters = implode('', $filters) !== '';
$status  = $filters['status']; // kept for the student view's shared markup
$duesCompact = true; // The list is a reminder; the full how-to lives on the form.

/** Label, pill classes and icon for a dues-reference verification state. */
$payBadge = function (?string $state): array {
    return [
        'verified'  => ['Verified',  'bg-green-100 text-green-700',  'fa-circle-check'],
        'not_found' => ['Not found', 'bg-red-100 text-red-700',      'fa-circle-xmark'],
    ][$state] ?? ['Awaiting check', 'bg-yellow-100 text-yellow-700', 'fa-hourglass-half'];
};
$colspan = $isStudent ? 7 : 8;
?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-3">
        <?php if (!$isStudent): ?>
            <p class="text-sm text-gray-500">
                <span class="font-semibold text-gray-700"><?= (int) ($pager['total'] ?? count($applications)) ?></span>
                application(s)<?= $hasFilters ? ' <span class="text-gray-400">found</span>' : '' ?>
            </p>
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
    <div class="flex flex-wrap gap-2">
        <?php if (Auth::hasRole('admin','hostel_admin')): ?>
            <a href="<?= url('/fees') ?>" class="btn btn-ghost"><i class="fa-solid fa-hand-holding-dollar"></i> Hall Dues Setup</a>
        <?php endif; ?>
        <?php if ($isStudent && $arrears): ?>
            <!-- Offering the button would only bounce them back here. -->
            <span class="btn bg-gray-100 text-gray-400 cursor-not-allowed" title="Settle your outstanding hall dues first">
                <i class="fa-solid fa-lock"></i> New Application
            </span>
        <?php elseif (($isStudent && $applicationsOpen) || Auth::hasRole('admin','hostel_admin')): ?>
            <a href="<?= url('/applications/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Application</a>
        <?php endif; ?>
    </div>
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

<?php if ($isStudent): ?>
    <?php require VIEW_PATH . '/partials/_arrears_panel.php'; ?>
    <?php require VIEW_PATH . '/partials/_dues_panel.php'; ?>
<?php else: ?>
    <!-- Filters. A GET form, so any result set is a shareable URL and the
         pager carries the filters across pages. -->
    <form method="get" action="<?= url('/applications') ?>" class="ui-card p-3 mb-4 flex flex-wrap items-end gap-3" data-reveal="0">
        <div class="flex-1 min-w-[13rem]">
            <label class="block text-[11px] font-medium text-gray-500 mb-1">Search</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                <input name="q" value="<?= e($filters['q']) ?>" class="ui-input pl-8"
                       placeholder="Student, ID, reference, room…">
            </div>
        </div>

        <div class="min-w-[9rem]">
            <label class="block text-[11px] font-medium text-gray-500 mb-1">Status</label>
            <select name="status" class="ui-input" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach (['pending','approved','rejected','waiting','cancelled','expired'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="min-w-[10rem]">
            <label class="block text-[11px] font-medium text-gray-500 mb-1">Dues payment</label>
            <select name="payment" class="ui-input" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach (['unverified' => 'Awaiting check', 'verified' => 'Verified', 'not_found' => 'Not found'] as $v => $label): ?>
                    <option value="<?= $v ?>" <?= $filters['payment'] === $v ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($hostels !== null): ?>
            <div class="min-w-[10rem]">
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Hostel</label>
                <select name="hostel" class="ui-input" onchange="this.form.submit()">
                    <option value="">All hostels</option>
                    <?php foreach ($hostels as $h): ?>
                        <option value="<?= (int) $h['id'] ?>" <?= $filters['hostel'] === (string) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if ($terms): ?>
            <div class="min-w-[9rem]">
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Academic year</label>
                <select name="term" class="ui-input" onchange="this.form.submit()">
                    <option value="">All years</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?= e($t) ?>" <?= $filters['term'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <button class="btn btn-ghost"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if ($hasFilters): ?>
            <a href="<?= url('/applications') ?>" class="btn btn-ghost border-transparent text-gray-500 hover:text-red-600"><i class="fa-solid fa-xmark"></i> Clear</a>
        <?php endif; ?>
    </form>
<?php endif; ?>

<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <?php if (!$isStudent): ?><th class="px-4 py-3 font-semibold">Student</th><?php endif; ?>
                    <th class="px-4 py-3 font-semibold">Preferred Hostel</th>
                    <th class="px-4 py-3 font-semibold">Preferred Room</th>
                    <th class="px-4 py-3 font-semibold">Dues Payment</th>
                    <th class="px-4 py-3 font-semibold">Year/Sem</th>
                    <th class="px-4 py-3 font-semibold">Submitted</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <?php if (!$isStudent): ?><th class="px-4 py-3 font-semibold text-right">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$applications): ?><tr><td colspan="<?= $colspan ?>" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-file-lines text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No applications</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($applications as $a): ?>
                    <?php
                    [$payLabel, $payClass, $payIcon] = $payBadge($a['payment_status'] ?? null);
                    $reference  = trim((string) ($a['payment_reference'] ?? ''));
                    $duplicates = (int) ($a['ref_duplicates'] ?? 0);
                    $note       = trim((string) ($a['review_note'] ?? ''));
                    $isOpenApp  = in_array($a['status'], ['pending', 'waiting'], true);
                    ?>
                    <tr class="align-top">
                        <?php if (!$isStudent): ?>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-700"><?= e($a['full_name']) ?></p>
                                <p class="text-xs text-gray-400"><?= e($a['student_no']) ?></p>
                                <?php if (!empty($a['student_type'])): ?>
                                    <p class="text-[11px] text-gray-400 mt-0.5"><i class="fa-solid <?= $a['student_type'] === 'fresher' ? 'fa-seedling' : 'fa-user-clock' ?> mr-0.5"></i><?= $a['student_type'] === 'fresher' ? 'Fresh' : 'Continuing' ?></p>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td class="px-4 py-3 text-gray-500"><?= e($a['hostel_name'] ?? 'Any') ?></td>
                        <td class="px-4 py-3 text-gray-500">
                            <?= !empty($a['preferred_room_number']) ? 'Room '.e($a['preferred_room_number']) : '—' ?>
                            <span class="block text-xs text-gray-400"><?= $a['preferred_room_type'] ? ucfirst($a['preferred_room_type']) : '' ?></span>
                        </td>

                        <!-- Dues reference + verification state -->
                        <td class="px-4 py-3">
                            <?php if ($reference !== ''): ?>
                                <p class="font-medium text-gray-700 tnum break-all"><?= e($reference) ?></p>
                            <?php else: ?>
                                <p class="text-gray-300">No reference</p>
                            <?php endif; ?>
                            <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium <?= $payClass ?>">
                                <i class="fa-solid <?= $payIcon ?>"></i><?= $payLabel ?>
                            </span>
                            <?php if ($a['payment_amount'] !== null && $a['payment_amount'] !== ''): ?>
                                <span class="block text-[11px] text-gray-400 mt-0.5">Expected <?= money($a['payment_amount']) ?></span>
                            <?php endif; ?>
                            <?php if (!$isStudent && $duplicates > 0): ?>
                                <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-red-600" title="This reference appears on another application">
                                    <i class="fa-solid fa-clone"></i>Used on <?= $duplicates ?> other application<?= $duplicates > 1 ? 's' : '' ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3 text-gray-500"><?= e($a['academic_year'] ?? '—') ?> <?= e($a['semester'] ?? '') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= datef($a['created_at']) ?></td>
                        <td class="px-4 py-3">
                            <?= status_badge($a['status']) ?>
                            <?php if ($note !== ''): ?>
                                <p class="mt-1.5 max-w-[15rem] rounded-lg bg-gray-50 border border-gray-100 p-2 text-[11px] leading-relaxed text-gray-600 whitespace-pre-line">
                                    <span class="font-semibold text-gray-500">Note:</span> <?= e($note) ?>
                                </p>
                            <?php endif; ?>
                        </td>

                        <?php if (!$isStudent): ?>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?php if ($isOpenApp): ?>
                                    <!-- Dues check: confirm the reference, or flag it as untraceable -->
                                    <div class="inline-flex items-center gap-0.5 mr-1 pr-1.5 border-r border-gray-200">
                                        <form method="post" action="<?= url('/applications/'.$a['id'].'/verify-payment') ?>" class="inline">
                                            <?= csrf_field() ?><input type="hidden" name="payment_status" value="verified">
                                            <button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-green-50 hover:text-green-600 transition" title="Payment found — mark reference verified"><i class="fa-solid fa-circle-check"></i></button>
                                        </form>
                                        <form method="post" action="<?= url('/applications/'.$a['id'].'/verify-payment') ?>" class="inline">
                                            <?= csrf_field() ?><input type="hidden" name="payment_status" value="not_found">
                                            <button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="No payment traced for this reference"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                                        </form>
                                    </div>

                                    <form method="post" action="<?= url('/applications/'.$a['id'].'/approve') ?>" class="inline">
                                        <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-green-50 hover:text-green-600 transition" title="Approve"><i class="fa-solid fa-check"></i></button>
                                    </form>
                                    <form method="post" action="<?= url('/applications/'.$a['id'].'/waiting') ?>" class="inline">
                                        <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-yellow-50 hover:text-yellow-600 transition" title="Waiting list"><i class="fa-solid fa-clock"></i></button>
                                    </form>
                                    <form method="post" action="<?= url('/applications/'.$a['id'].'/reject') ?>" class="inline">
                                        <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                    </form>

                                    <!-- Cancel with a note — the unpaid-dues route -->
                                    <span x-data="{ open: false }" class="inline">
                                        <button type="button" @click="open = true"
                                                class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition"
                                                title="Cancel with a note to the student"><i class="fa-solid fa-ban"></i></button>

                                        <div x-cloak x-show="open" @keydown.escape.window="open = false"
                                             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50" @click.self="open = false">
                                            <form method="post" action="<?= url('/applications/'.$a['id'].'/cancel') ?>"
                                                  class="w-full max-w-lg rounded-2xl bg-white p-6 text-left shadow-xl">
                                                <?= csrf_field() ?>
                                                <h3 class="font-display font-bold text-gray-800 flex items-center gap-2">
                                                    <span class="inline-flex w-9 h-9 rounded-xl bg-red-50 text-red-600 items-center justify-center"><i class="fa-solid fa-ban text-sm"></i></span>
                                                    Cancel application
                                                </h3>
                                                <p class="mt-2 text-sm text-gray-500">
                                                    Cancelling <span class="font-medium text-gray-700"><?= e($a['full_name']) ?></span>'s application for
                                                    <?= $reference !== '' ? 'reference <span class="font-medium text-gray-700 tnum">' . e($reference) . '</span>' : 'an application with <span class="font-medium text-gray-700">no reference</span>' ?>.
                                                    Your note is sent to the student, so say exactly what they need to do.
                                                </p>
                                                <label class="mt-4 block text-sm font-medium text-gray-600 mb-1">Note to the student *</label>
                                                <textarea name="review_note" rows="4" required class="ui-input text-sm"
                                                          placeholder="We could not trace a payment for this reference. Please bring your receipt to the hostel office, or re-apply with the correct Reference ID."></textarea>
                                                <div class="mt-5 flex justify-end gap-3">
                                                    <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-600">Close</button>
                                                    <button class="btn bg-red-600 hover:bg-red-700 text-white"><i class="fa-solid fa-ban"></i>Cancel application</button>
                                                </div>
                                            </form>
                                        </div>
                                    </span>

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
