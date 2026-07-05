<?php /** @var array $students @var array $hostels @var array $feeSchedule */
use App\Core\Scope;
$isGlobal = Scope::isGlobal();
$feeSchedule = $feeSchedule ?? [];
?>
<a href="<?= url('/invoices') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to invoices</a>

<form method="post" action="<?= url('/charges') ?>" class="ui-card p-6 mt-3 space-y-6 max-w-2xl reveal"
      x-data="{
          target: '<?= old('target') ?: 'all' ?>',
          fees: <?= htmlspecialchars(json_encode((object) $feeSchedule), ENT_QUOTES) ?>,
          applyFee(rt) {
              if (!rt || this.fees[rt] == null) return;
              this.$refs.amount.value = this.fees[rt];
              this.$refs.desc.value = 'Accommodation — ' + rt.charAt(0).toUpperCase() + rt.slice(1) + ' Room';
          }
      }">
    <?= csrf_field() ?>

    <div>
        <h3 class="font-display font-bold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-file-invoice-dollar text-primary-500 text-sm"></i>New Charge</h3>
        <p class="text-xs text-gray-400 mt-1">Bill students a custom payment (e.g. hostel dues, utilities, damages). It appears as an invoice they can pay.</p>
    </div>

    <?php if (!empty($feeSchedule)): ?>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Quick fill from room pricing <span class="text-gray-400 font-normal">(optional)</span></label>
            <select @change="applyFee($event.target.value)" class="ui-input">
                <option value="">— Choose a room type —</option>
                <?php foreach ($feeSchedule as $rt => $amt): ?>
                    <option value="<?= e($rt) ?>"><?= ucfirst($rt) ?> — <?= money($amt) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Fills the amount and description from your <a href="<?= url('/fees') ?>" class="text-primary-600 hover:underline">room pricing</a>.</p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Charge Name / Description <span class="text-red-500">*</span></label>
            <input x-ref="desc" name="description" value="<?= old('description') ?>" required class="ui-input" placeholder="e.g. Hostel Dues — First Semester">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Amount (<?= CURRENCY ?>) <span class="text-red-500">*</span></label>
            <input x-ref="amount" type="number" step="0.01" min="0.01" name="amount" value="<?= old('amount') ?>" required class="ui-input" placeholder="0.00">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Due Date</label>
            <input type="date" name="due_date" value="<?= old('due_date') ?>" class="ui-input">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-600 mb-2">Bill</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition" :class="target==='all' ? 'border-primary-400 bg-primary-50/60 ring-1 ring-primary-300' : 'border-gray-200'">
                <input type="radio" name="target" value="all" x-model="target" class="mt-0.5">
                <span>
                    <span class="block text-sm font-semibold text-gray-700">All active students</span>
                    <span class="block text-xs text-gray-400">Every active resident of the hostel</span>
                </span>
            </label>
            <label class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition" :class="target==='one' ? 'border-primary-400 bg-primary-50/60 ring-1 ring-primary-300' : 'border-gray-200'">
                <input type="radio" name="target" value="one" x-model="target" class="mt-0.5">
                <span>
                    <span class="block text-sm font-semibold text-gray-700">A specific student</span>
                    <span class="block text-xs text-gray-400">Bill one student only</span>
                </span>
            </label>
        </div>
    </div>

    <!-- All active students -> choose hostel -->
    <div x-show="target==='all'" x-cloak>
        <label class="block text-sm font-medium text-gray-600 mb-1">Hostel <span class="text-red-500">*</span></label>
        <?php if ($isGlobal): ?>
            <select name="hostel_id" class="ui-input" :required="target==='all'">
                <option value="">Select hostel</option>
                <?php foreach ($hostels as $h): ?>
                    <option value="<?= (int)$h['id'] ?>" <?= (string)old('hostel_id')===(string)$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($hostels): ?>
            <input type="text" class="ui-input bg-gray-50" value="<?= e($hostels[0]['name']) ?>" disabled>
            <p class="text-xs text-gray-400 mt-1">Your hostel's active residents will be billed.</p>
        <?php else: ?>
            <p class="text-sm text-amber-600">No hostel is linked to your account.</p>
        <?php endif; ?>
    </div>

    <!-- Specific student -->
    <div x-show="target==='one'" x-cloak>
        <label class="block text-sm font-medium text-gray-600 mb-1">Student <span class="text-red-500">*</span></label>
        <select name="student_id" class="ui-input" :required="target==='one'">
            <option value="">Select student</option>
            <?php foreach ($students as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (string)old('student_id')===(string)$s['id']?'selected':'' ?>><?= e($s['full_name']) ?> (<?= e($s['student_id']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800 text-sm">
        <i class="fa-solid fa-circle-info mt-0.5 text-amber-500"></i>
        <p>Each student gets an unpaid invoice for this amount, stamped with the hostel's current session. They can pay it online when Paystack is enabled for the hostel, or at the office.</p>
    </div>

    <div class="flex justify-end items-center gap-3 pt-3 border-t border-gray-100">
        <a href="<?= url('/invoices') ?>" class="btn btn-ghost border-transparent hover:bg-gray-100">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-file-circle-plus"></i>Create Charge</button>
    </div>
</form>
<style>[x-cloak]{display:none}</style>
