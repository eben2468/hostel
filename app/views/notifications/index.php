<?php /** @var array $notifications */ ?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($notifications) ?></span> notification(s)</p>
    <form method="post" action="<?= url('/notifications/read-all') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-ghost"><i class="fa-solid fa-check-double"></i> Mark all read</button>
    </form>
</div>
<div class="ui-card divide-y divide-gray-100 overflow-hidden" data-reveal="0">
    <?php if (!$notifications): ?>
        <div class="p-12 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 items-center justify-center mb-3"><i class="fa-regular fa-bell-slash text-xl"></i></span>
            <p class="text-sm font-medium text-gray-500">No notifications.</p>
        </div>
    <?php endif; ?>
    <?php foreach ($notifications as $n): ?>
        <a href="<?= url('/notifications/'.$n['id'].'/read') ?>" class="flex items-start gap-3 p-4 hover:bg-gray-50 transition <?= $n['is_read'] ? '' : 'bg-primary-50/50' ?>">
            <div class="w-10 h-10 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shrink-0">
                <i class="fa-solid <?= e($n['icon'] ?: 'fa-bell') ?>"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-800 <?= $n['is_read'] ? 'font-medium' : 'font-semibold' ?>"><?= e($n['title']) ?></p>
                <?php if ($n['body']): ?><p class="text-sm text-gray-500"><?= e($n['body']) ?></p><?php endif; ?>
                <p class="text-xs text-gray-400 mt-1"><i class="fa-regular fa-clock mr-1"></i><?= datef($n['created_at'],'d M Y H:i') ?></p>
            </div>
            <?php if (!$n['is_read']): ?><span class="w-2.5 h-2.5 rounded-full bg-primary-500 mt-2 shrink-0 ring-2 ring-primary-100"></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
