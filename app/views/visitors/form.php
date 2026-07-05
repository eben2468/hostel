<?php /** @var array $students */
use App\Core\Auth;
$isStaff = !Auth::hasRole('student');
?>
<a href="<?= url('/visitors') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left mr-1"></i>Back</a>
<form method="post" action="<?= url('/visitors') ?>" class="ui-card p-6 mt-3 space-y-5 max-w-2xl">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php if ($isStaff): ?>
            <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Host Student (optional)</label>
                <select name="student_id" class="ui-input">
                    <option value="">— None —</option>
                    <?php foreach ($students as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['full_name']) ?> (<?= e($s['student_id']) ?>)</option><?php endforeach; ?>
                </select></div>
        <?php endif; ?>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Visitor Name *</label>
            <input name="visitor_name" required class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Phone</label>
            <input name="phone" class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Visit Date</label>
            <input type="date" name="visit_date" value="<?= date('Y-m-d') ?>" class="ui-input"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Purpose</label>
            <input name="purpose" class="ui-input"></div>
    </div>
    <div class="flex justify-end gap-3 pt-3 border-t">
        <a href="<?= url('/visitors') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary">Register Visitor</button>
    </div>
</form>
