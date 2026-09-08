<?php /** @var array $room @var array $occupants @var array $applicants */
$applicants = $applicants ?? [];
$free       = max(0, (int) $room['capacity'] - (int) $room['occupied']);
// Beds physically free but already spoken for by applications awaiting review.
$claimed    = count($applicants);
$openToApply = max(0, $free - $claimed);
$statusPill = [
    'available'   => 'bg-green-100 text-green-700',
    'occupied'    => 'bg-blue-100 text-blue-700',
    'reserved'    => 'bg-amber-100 text-amber-700',
    'maintenance' => 'bg-orange-100 text-orange-700',
    'closed'      => 'bg-gray-200 text-gray-600',
][$room['status']] ?? 'bg-gray-100 text-gray-600';
?>
<a href="<?= url('/rooms') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to rooms</a>

<!-- Room summary -->
<div class="ui-card overflow-hidden mt-3" data-reveal="0">
    <div class="bg-gradient-to-r from-primary-700 to-primary-500 px-6 py-5 text-white">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-display font-bold text-2xl leading-tight">Room <?= e($room['room_number']) ?></h2>
                <p class="text-sm text-white/80 mt-0.5">
                    <?= e($room['hostel_name'] ?: 'No hostel') ?>
                    <?php if ($room['block_name'] || $room['floor_number']): ?>
                        · <?= e(trim(($room['block_name'] ?? '') . ' ' . ($room['floor_number'] ? 'Floor ' . $room['floor_number'] : ''))) ?>
                    <?php endif; ?>
                    · <?= ucfirst($room['room_type']) ?>
                </p>
            </div>
            <a href="<?= url('/rooms/' . $room['id'] . '/edit') ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-sm font-medium hover:bg-white/25 transition">
                <i class="fa-solid fa-pen-to-square"></i>Edit room
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-100 border-b border-gray-100">
        <?php foreach ([
            ['Occupied', (int) $room['occupied'] . ' / ' . (int) $room['capacity'], 'fa-users'],
            ['Free beds', $free . ($claimed ? ' (' . $claimed . ' applied for)' : ''), 'fa-bed'],
            ['Open to apply', (string) $openToApply, 'fa-door-open'],
        ] as [$label, $value, $icon]): ?>
            <div class="p-4">
                <p class="text-[11px] uppercase tracking-wider text-gray-400 font-semibold flex items-center gap-1.5">
                    <i class="fa-solid <?= $icon ?> text-gray-300"></i><?= $label ?>
                </p>
                <p class="mt-1 text-lg font-bold text-gray-800 tnum"><?= e($value) ?></p>
            </div>
        <?php endforeach; ?>
        <div class="p-4">
            <p class="text-[11px] uppercase tracking-wider text-gray-400 font-semibold flex items-center gap-1.5"><i class="fa-solid fa-circle-dot text-gray-300"></i>Status</p>
            <p class="mt-1"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusPill ?>"><?= ucfirst(str_replace('_', ' ', $room['status'])) ?></span></p>
        </div>
    </div>
</div>

<!-- Occupants -->
<div class="flex items-center justify-between mt-5 mb-3">
    <h3 class="font-display font-bold text-gray-800 flex items-center gap-2">
        <i class="fa-solid fa-users text-primary-500 text-sm"></i>
        Students in this room
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500"><?= count($occupants) ?></span>
    </h3>
    <?php if ($free > 0 && !in_array($room['status'], ['maintenance','closed'], true)): ?>
        <a href="<?= url('/allocations/create?room=' . $room['id']) ?>" class="btn btn-ghost"><i class="fa-solid fa-user-plus"></i> Allocate a student</a>
    <?php endif; ?>
</div>

<?php if ($openToApply === 0 && $claimed > 0): ?>
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
        <i class="fa-solid fa-lock mt-0.5 text-amber-500"></i>
        <div class="text-sm">
            <p class="font-semibold">Closed to new applications</p>
            <p class="text-amber-700/80">
                Every bed is either occupied or already applied for, so students can no longer pick this room.
                Beds are released automatically if you reject or cancel one of the applications below.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php if (!$occupants): ?>
    <div class="ui-card p-10 text-center" data-reveal="1">
        <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-100 items-center justify-center mb-3 text-gray-400"><i class="fa-solid fa-bed text-xl"></i></span>
        <p class="text-sm font-medium text-gray-500">Nobody is living in this room yet</p>
        <p class="text-xs text-gray-400 mt-1">All <?= (int) $room['capacity'] ?> bed(s) are free.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <?php foreach ($occupants as $i => $s): ?>
            <div class="ui-card p-5" data-reveal="<?= min($i, 3) ?>">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-xl overflow-hidden bg-gradient-to-br from-primary-500 to-primary-700 text-white text-xl font-bold flex items-center justify-center shrink-0">
                        <?php if (!empty($s['photo'])): ?>
                            <img src="<?= url('/uploads/' . $s['photo']) ?>" alt="<?= e($s['full_name']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?= e(strtoupper(substr($s['full_name'], 0, 1))) ?>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="<?= url('/students/' . $s['id']) ?>" class="font-semibold text-gray-800 hover:text-primary-600 block truncate"><?= e($s['full_name']) ?></a>
                                <p class="text-xs text-gray-400 tnum"><?= e($s['student_id']) ?></p>
                            </div>
                            <?php if ($s['bed_number']): ?>
                                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-[11px] font-semibold text-primary-700 whitespace-nowrap"><?= e($s['bed_number']) ?></span>
                            <?php endif; ?>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                            <?php
                            $rows = [
                                'Gender'    => ucfirst((string) $s['gender']),
                                'Level'     => $s['level'],
                                'Programme' => $s['programme'],
                                'Phone'     => $s['phone'],
                                'Email'     => $s['email'],
                                'Guardian'  => trim(($s['guardian_name'] ?? '') . ($s['guardian_phone'] ? ' · ' . $s['guardian_phone'] : '')),
                            ];
                            foreach ($rows as $label => $value):
                                if (trim((string) $value) === '') { continue; } ?>
                                <div class="<?= in_array($label, ['Programme','Email','Guardian'], true) ? 'col-span-2' : '' ?>">
                                    <dt class="text-gray-400"><?= $label ?></dt>
                                    <dd class="text-gray-700 truncate" title="<?= e($value) ?>"><?= e($value) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>

                        <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap items-center gap-2 text-[11px]">
                            <span class="rounded-full px-2 py-0.5 font-semibold <?= $s['allocation_status'] === 'checked_in' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' ?>">
                                <?= $s['allocation_status'] === 'checked_in' ? 'Checked in' : 'Allocated' ?>
                            </span>
                            <?php if ($s['student_status'] !== 'active'): ?>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-700"><?= ucfirst($s['student_status']) ?> account</span>
                            <?php endif; ?>
                            <span class="text-gray-400">
                                <?= $s['check_in_at'] ? 'Checked in ' . datef($s['check_in_at'], 'd M Y') : 'Since ' . datef($s['allocated_at'], 'd M Y') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Applicants holding the remaining beds -->
<?php if ($applicants): ?>
    <h3 class="font-display font-bold text-gray-800 flex items-center gap-2 mt-6 mb-3">
        <i class="fa-solid fa-hourglass-half text-amber-500 text-sm"></i>
        Applied for this room, awaiting a decision
        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700"><?= count($applicants) ?></span>
    </h3>
    <div class="ui-card overflow-hidden" data-reveal="0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm ui-table">
                <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Student</th>
                        <th class="px-4 py-3 font-semibold">Level / Programme</th>
                        <th class="px-4 py-3 font-semibold">Dues reference</th>
                        <th class="px-4 py-3 font-semibold">Applied</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($applicants as $ap): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <a href="<?= url('/students/' . $ap['id']) ?>" class="font-medium text-gray-700 hover:text-primary-600"><?= e($ap['full_name']) ?></a>
                                <p class="text-xs text-gray-400 tnum"><?= e($ap['student_id']) ?><?= $ap['phone'] ? ' · ' . e($ap['phone']) : '' ?></p>
                            </td>
                            <td class="px-4 py-3 text-gray-500"><?= e(trim(($ap['level'] ? 'Level ' . $ap['level'] : '') . ' ' . ($ap['programme'] ?? ''))) ?: '—' ?></td>
                            <td class="px-4 py-3 text-gray-500 tnum"><?= e($ap['payment_reference'] ?: '—') ?></td>
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?= datef($ap['applied_at'], 'd M Y') ?></td>
                            <td class="px-4 py-3"><?= status_badge($ap['application_status']) ?></td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?= url('/applications?q=' . urlencode((string) $ap['student_id'])) ?>"
                                   class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 hover:bg-primary-50 hover:text-primary-700 transition">
                                    <i class="fa-solid fa-arrow-right"></i>Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
