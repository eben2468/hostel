<a href="<?= url('/notices') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left mr-1"></i>Back</a>
<form method="post" action="<?= url('/notices') ?>" class="ui-card p-6 mt-3 space-y-5 max-w-2xl">
    <?= csrf_field() ?>
    <div><label class="block text-sm font-medium text-gray-600 mb-1">Title *</label>
        <input name="title" required class="ui-input"></div>
    <div><label class="block text-sm font-medium text-gray-600 mb-1">Message *</label>
        <textarea name="body" rows="5" required class="ui-input"></textarea></div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Audience</label>
            <select name="audience" class="ui-input">
                <option value="all">Everyone</option>
                <option value="students">Students only</option>
                <option value="staff">Staff only</option>
            </select></div>
        <div><label class="block text-sm font-medium text-gray-600 mb-1">Expiry Date</label>
            <input type="date" name="expires_at" class="ui-input"></div>
    </div>
    <label class="flex items-center gap-2 text-sm text-gray-600">
        <input type="checkbox" name="is_pinned" class="rounded border-gray-300"> Pin this notice to the top
    </label>
    <div class="flex justify-end gap-3 pt-3 border-t">
        <a href="<?= url('/notices') ?>" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
        <button class="btn btn-primary">Publish Notice</button>
    </div>
</form>
