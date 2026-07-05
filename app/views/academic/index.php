<?php /** @var array $hostel @var array $years @var int $activeAllocations */
$curYear = $hostel['academic_year'] ?? '';
$curSem  = $hostel['semester'] ?? '';
$isSet   = $curYear !== '' && $curSem !== '';
?>
<div class="max-w-2xl space-y-4">
    <!-- Current session -->
    <div class="ui-card overflow-hidden" data-reveal="0">
        <div class="bg-gradient-to-br from-primary-600 to-primary-800 px-6 py-5 flex items-center gap-4">
            <span class="w-11 h-11 rounded-2xl bg-white/15 text-white flex items-center justify-center text-lg"><i class="fa-solid fa-calendar-day"></i></span>
            <div>
                <p class="text-white/70 text-xs uppercase tracking-widest">Active session · <?= e($hostel['name']) ?></p>
                <p class="text-white font-display font-bold text-xl leading-tight">
                    <?= $isSet ? e($curYear) . ' · ' . e($curSem) . ' semester' : 'Not set yet' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Change session -->
    <form method="post" action="<?= url('/academic') ?>" class="ui-card p-6 space-y-5" data-reveal="1"
          onsubmit="return confirm('Switching sessions parks this term\'s allocations and empties the rooms. If you have used the session you are switching to before, its allocations are restored exactly. You can switch back at any time — nothing is lost. Continue?');">
        <?= csrf_field() ?>
        <div>
            <h3 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-arrows-rotate text-primary-500 text-sm"></i>Set / change session</h3>
            <p class="text-xs text-gray-400 mt-1">New allocations, invoices and payments are stamped with the active session.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-600 mb-1">Academic Year</label>
                <select name="academic_year" required class="ui-input">
                    <option value="">Select year</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= e($y) ?>" <?= $curYear===$y?'selected':'' ?>><?= e($y) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="block text-sm font-medium text-gray-600 mb-1">Semester</label>
                <select name="semester" required class="ui-input">
                    <option value="">Select semester</option>
                    <?php foreach (['First','Second'] as $sm): ?>
                        <option value="<?= $sm ?>" <?= $curSem===$sm?'selected':'' ?>><?= $sm ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>

        <div class="flex items-start gap-3 rounded-xl border border-primary-200 bg-primary-50 p-4 text-primary-800">
            <i class="fa-solid fa-arrows-rotate mt-0.5 text-primary-500"></i>
            <div class="text-sm">
                <p class="font-semibold">Switching sessions is reversible</p>
                <p class="text-primary-700/80">
                    <?php if ($activeAllocations > 0): ?>
                        This session's <span class="font-semibold"><?= $activeAllocations ?></span> allocation(s) are parked and the rooms emptied.
                    <?php endif; ?>
                    Switch back at any time to restore this session's students, rooms and beds <span class="font-semibold">exactly</span> as they are now. Invoices and payments are always preserved.
                </p>
            </div>
        </div>

        <div class="flex justify-end pt-3 border-t">
            <button class="btn btn-primary"><i class="fa-solid fa-circle-check"></i>Activate session</button>
        </div>
    </form>
</div>
