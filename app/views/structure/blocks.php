<?php /** @var array $hostel @var array $blocks */
// Gender → icon + tint so blocks read at a glance (mirrors the hostel cards).
$genderMeta = [
    'male'   => ['fa-mars',       'text-sky-600',    'bg-sky-50'],
    'female' => ['fa-venus',      'text-pink-600',   'bg-pink-50'],
    'mixed'  => ['fa-venus-mars', 'text-violet-600', 'bg-violet-50'],
];
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <a href="<?= url('/hostels/'.$hostel['id']) ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back to <?= e($hostel['name']) ?></a>
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($blocks) ?></span> block(s)</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Add block form -->
    <div class="lg:sticky lg:top-4 h-fit" data-reveal="0">
        <div class="ui-card overflow-hidden">
            <div class="bg-gradient-to-br from-primary-600 to-primary-800 px-5 py-4 flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-white/15 text-white flex items-center justify-center"><i class="fa-solid fa-layer-group"></i></span>
                <div>
                    <h3 class="font-display font-bold text-white leading-tight">Add Block</h3>
                    <p class="text-white/70 text-xs">A wing or building within this hostel</p>
                </div>
            </div>
            <form method="post" action="<?= url('/hostels/'.$hostel['id'].'/blocks') ?>" class="p-5 space-y-3">
                <?= csrf_field() ?>
                <div><label class="block text-sm text-gray-600 mb-1">Name <span class="text-red-500">*</span></label>
                    <input name="name" required placeholder="e.g. Block A" class="ui-input"></div>
                <div><label class="block text-sm text-gray-600 mb-1">Code</label>
                    <input name="code" placeholder="e.g. A" class="ui-input"></div>
                <div><label class="block text-sm text-gray-600 mb-1">Gender</label>
                    <select name="gender" class="ui-input">
                        <option value="mixed">Mixed</option><option value="male">Male</option><option value="female">Female</option>
                    </select></div>
                <div><label class="block text-sm text-gray-600 mb-1">Description</label>
                    <input name="description" class="ui-input"></div>
                <button class="btn btn-primary w-full"><i class="fa-solid fa-plus"></i>Add Block</button>
            </form>
        </div>
    </div>

    <!-- Blocks list -->
    <div class="lg:col-span-2 space-y-3.5">
        <?php if (!$blocks): ?>
            <div class="ui-card p-12 text-center">
                <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 items-center justify-center mb-3"><i class="fa-solid fa-layer-group text-xl"></i></span>
                <p class="text-sm font-medium text-gray-500">No blocks yet.</p>
                <p class="text-xs text-gray-400 mt-1">Add your first block using the form.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($blocks as $idx => $b):
            $g = strtolower((string) $b['gender']);
            [$gIcon, $gText, $gBg] = $genderMeta[$g] ?? $genderMeta['mixed'];
        ?>
            <div class="ui-card ui-card-hover p-5" data-reveal="<?= min($idx,6) ?>" x-data="{ editing: false }">
                <!-- View mode -->
                <div x-show="!editing" class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <span class="w-12 h-12 rounded-2xl <?= $gBg ?> <?= $gText ?> flex items-center justify-center shrink-0 text-lg"><i class="fa-solid fa-cubes"></i></span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold text-gray-800 truncate"><?= e($b['name']) ?></p>
                                <?php if (!empty($b['code'])): ?><span class="text-[11px] text-gray-400 font-mono bg-gray-100 px-1.5 py-0.5 rounded"><?= e($b['code']) ?></span><?php endif; ?>
                            </div>
                            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-[11px] font-medium <?= $gText ?> <?= $gBg ?> px-2 py-0.5 rounded-full"><i class="fa-solid <?= $gIcon ?> text-[10px]"></i><?= ucfirst($g) ?></span>
                                <span class="inline-flex items-center gap-1 text-[11px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"><i class="fa-solid fa-stairs text-[10px]"></i><?= (int)$b['floor_count'] ?> floor(s)</span>
                                <span class="inline-flex items-center gap-1 text-[11px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full"><i class="fa-solid fa-door-open text-[10px]"></i><?= (int)$b['room_count'] ?> room(s)</span>
                            </div>
                            <?php if ($b['description']): ?><p class="text-xs text-gray-400 mt-1.5"><?= e($b['description']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="<?= url('/blocks/'.$b['id'].'/floors') ?>" class="btn bg-primary-50 text-primary-700 hover:bg-primary-100 px-3 py-1.5 text-sm"><i class="fa-solid fa-stairs"></i>Floors</a>
                        <button type="button" @click="editing = true" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition" title="Edit"><i class="fa-solid fa-pen"></i></button>
                        <form method="post" action="<?= url('/blocks/'.$b['id'].'/delete') ?>" onsubmit="return confirm('Delete block and its floors?')">
                            <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <!-- Edit mode -->
                <form x-show="editing" x-cloak style="display:none" method="post" action="<?= url('/blocks/'.$b['id']) ?>" class="space-y-3">
                    <?= csrf_field() ?>
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-1"><i class="fa-solid fa-pen text-amber-500 text-xs"></i>Edit block</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div><label class="block text-sm text-gray-600 mb-1">Name <span class="text-red-500">*</span></label>
                            <input name="name" value="<?= e($b['name']) ?>" required class="ui-input"></div>
                        <div><label class="block text-sm text-gray-600 mb-1">Code</label>
                            <input name="code" value="<?= e($b['code'] ?? '') ?>" class="ui-input"></div>
                        <div><label class="block text-sm text-gray-600 mb-1">Gender</label>
                            <select name="gender" class="ui-input">
                                <?php foreach (['mixed','male','female'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $b['gender']===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div><label class="block text-sm text-gray-600 mb-1">Description</label>
                            <input name="description" value="<?= e($b['description'] ?? '') ?>" class="ui-input"></div>
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
