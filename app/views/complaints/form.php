<a href="<?= url('/complaints') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back</a>
<form method="post" action="<?= url('/complaints') ?>" class="ui-card p-6 mt-3 space-y-5 max-w-2xl reveal">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Category *</label>
            <select name="category" class="ui-input">
                <?php foreach (['electrical','plumbing','furniture','internet','cleaning','security','noise','water','other'] as $c): ?>
                    <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Priority</label>
            <select name="priority" class="ui-input">
                <?php foreach (['low','medium','high','urgent'] as $p): ?>
                    <option value="<?= $p ?>" <?= $p==='medium'?'selected':'' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Title *</label>
            <input name="title" required class="ui-input" placeholder="e.g. Faulty ceiling fan"></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Room Number</label>
            <input name="room_number" class="ui-input"></div>
        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-600 mb-1">Description</label>
            <textarea name="description" rows="4" class="ui-input" placeholder="Describe the issue…"></textarea></div>
    </div>
    <div class="flex justify-end items-center gap-3 pt-3 border-t border-gray-100">
        <a href="<?= url('/complaints') ?>" class="btn btn-ghost border-transparent hover:bg-gray-100">Cancel</a>
        <button class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i>Submit Complaint</button>
    </div>
</form>
