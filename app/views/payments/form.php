<?php /** @var array $students @var array $invoices */ ?>
<a href="<?= url('/payments') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back</a>
<form method="post" action="<?= url('/payments') ?>" class="ui-card p-6 mt-3 space-y-5 max-w-2xl reveal"
      x-data="{ invoices: <?= htmlspecialchars(json_encode(array_map(fn($i)=>['id'=>$i['id'],'student'=>$i['student_id'],'balance'=>$i['balance'],'no'=>$i['invoice_no']], $invoices)), ENT_QUOTES) ?>,
                student:'', invoice:'',
                fill(){ const inv=this.invoices.find(x=>x.id==this.invoice); if(inv){ this.$refs.amount.value=inv.balance; this.student=inv.student; } } }">
    <?= csrf_field() ?>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Apply to Invoice (optional)</label>
        <select name="invoice_id" x-model="invoice" @change="fill()" class="ui-input">
            <option value="">— None (general payment) —</option>
            <?php foreach ($invoices as $i): ?>
                <option value="<?= $i['id'] ?>"><?= e($i['invoice_no']) ?> · <?= e($i['full_name']) ?> · bal <?= money($i['balance']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Student *</label>
        <select name="student_id" x-model="student" required class="ui-input">
            <option value="">Select student</option>
            <?php foreach ($students as $s): ?>
                <option value="<?= $s['id'] ?>"><?= e($s['full_name']) ?> (<?= e($s['student_id']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Amount (<?= CURRENCY ?>) *</label>
            <input x-ref="amount" type="number" step="0.01" name="amount" required class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Method</label>
            <select name="method" class="ui-input">
                <?php foreach (['cash'=>'Cash','paystack'=>'Paystack','momo'=>'Mobile Money','bank_transfer'=>'Bank Transfer','manual'=>'Manual'] as $k=>$v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Reference / Transaction ID</label>
            <input name="reference" class="ui-input"></div>
    </div>
    <div class="flex justify-end items-center gap-3 pt-3 border-t border-gray-100">
        <a href="<?= url('/payments') ?>" class="btn btn-ghost border-transparent hover:bg-gray-100">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-money-bill-wave"></i>Record Payment</button>
    </div>
</form>
