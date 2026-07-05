<?php /** @var array $block @var array $floors */ ?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <a href="<?= url('/hostels/'.$block['hostel_id'].'/blocks') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to blocks</a>
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($floors) ?></span> floor(s)</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Add floor form -->
    <div class="lg:sticky lg:top-4 h-fit" data-reveal="0">
        <div class="ui-card overflow-hidden">
            <div class="bg-gradient-to-br from-primary-600 to-primary-800 px-5 py-4 flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-white/15 text-white flex items-center justify-center"><i class="fa-solid fa-stairs"></i></span>
                <div class="min-w-0">
                    <h3 class="font-display font-bold text-white leading-tight truncate">Add Floor</h3>
                    <p class="text-white/70 text-xs truncate">to <?= e($block['name']) ?></p>
                </div>
            </div>
            <form method="post" action="<?= url('/blocks/'.$block['id'].'/floors') ?>" class="p-5 space-y-3">
                <?= csrf_field() ?>
                <div><label class="block text-sm text-gray-600 mb-1.5">Floor Number / Name <span class="text-red-500">*</span></label>
                    <input name="number" required placeholder="e.g. Ground, 1, 2" class="ui-input"></div>
                <div><label class="block text-sm text-gray-600 mb-1.5">Description</label>
                    <input name="description" class="ui-input"></div>
                <button class="btn btn-primary w-full"><i class="fa-solid fa-plus"></i>Add Floor</button>
            </form>
        </div>
    </div>

    <!-- Floors list -->
    <div class="lg:col-span-2 space-y-3.5">
        <?php if (!$floors): ?>
            <div class="ui-card p-12 text-center">
                <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 items-center justify-center mb-3"><i class="fa-solid fa-stairs text-xl"></i></span>
                <p class="text-sm font-medium text-gray-500">No floors yet.</p>
                <p class="text-xs text-gray-400 mt-1">Add your first floor using the form.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($floors as $idx => $f): ?>
            <div class="ui-card ui-card-hover p-5" data-reveal="<?= min($idx,6) ?>" x-data="{ editing: false }">
                <!-- View mode -->
                <div x-show="!editing" class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <span class="w-12 h-12 rounded-2xl bg-primary-50 text-primary-600 flex items-center justify-center shrink-0 text-lg"><i class="fa-solid fa-stairs"></i></span>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 truncate"><?= e($f['number']) ?></p>
                            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-[11px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"><i class="fa-solid fa-door-open text-[10px]"></i><?= (int)$f['room_count'] ?> room(s)</span>
                                <?php if ($f['description']): ?><span class="text-[11px] text-gray-400"><?= e($f['description']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" @click="editing = true" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition" title="Edit"><i class="fa-solid fa-pen"></i></button>
                        <form method="post" action="<?= url('/floors/'.$f['id'].'/delete') ?>" onsubmit="return confirm('Delete floor?')">
                            <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <!-- Edit mode -->
                <form x-show="editing" x-cloak style="display:none" method="post" action="<?= url('/floors/'.$f['id']) ?>" class="space-y-3">
                    <?= csrf_field() ?>
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-1"><i class="fa-solid fa-pen text-amber-500 text-xs"></i>Edit floor</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><label class="block text-sm text-gray-600 mb-1">Floor Number / Name <span class="text-red-500">*</span></label>
                            <input name="number" value="<?= e($f['number']) ?>" required class="ui-input"></div>
                        <div><label class="block text-sm text-gray-600 mb-1">Description</label>
                            <input name="description" value="<?= e($f['description'] ?? '') ?>" class="ui-input"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="btn btn-primary px-4 py-1.5 text-sm"><i class="fa-solid fa-floppy-disk"></i>Save</button>
                        <button type="button" @click="editing = false" class="btn btn-ghost px-4 py-1.5 text-sm">Cancel</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
