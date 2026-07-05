<?php /** @var array $notices @var bool $canManage */ ?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($notices) ?></span> notice(s)</p>
    <?php if ($canManage): ?>
        <a href="<?= url('/notices/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Notice</a>
    <?php endif; ?>
</div>
<div class="space-y-3">
    <?php if (!$notices): ?>
        <div class="ui-card p-12 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 items-center justify-center mb-3"><i class="fa-solid fa-bullhorn text-xl"></i></span>
            <p class="text-sm font-medium text-gray-500">No notices posted.</p>
        </div>
    <?php endif; ?>
    <?php foreach ($notices as $idx => $n): ?>
        <div class="ui-card ui-card-hover overflow-hidden flex" data-reveal="<?= min($idx,6) ?>">
            <div class="w-1.5 shrink-0 <?= $n['is_pinned'] ? 'bg-amber-400' : 'bg-primary-400' ?>"></div>
            <div class="flex-1 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <?php if ($n['is_pinned']): ?><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700"><i class="fa-solid fa-thumbtack text-[9px]"></i> Pinned</span><?php endif; ?>
                            <h3 class="font-display font-bold text-gray-800"><?= e($n['title']) ?></h3>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500"><?= ucfirst($n['audience']) ?></span>
                        </div>
                        <p class="text-sm text-gray-600 mt-2 whitespace-pre-line"><?= e($n['body']) ?></p>
                        <p class="text-xs text-gray-400 mt-3"><i class="fa-regular fa-clock mr-1"></i><?= datef($n['created_at'],'d M Y H:i') ?><?php if ($n['expires_at']): ?> · expires <?= datef($n['expires_at']) ?><?php endif; ?></p>
                    </div>
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= url('/notices/'.$n['id'].'/delete') ?>" onsubmit="return confirm('Delete notice?')">
                            <?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Delete"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
