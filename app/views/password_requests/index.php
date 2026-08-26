<?php /** @var array $requests */
use App\Core\Auth;
$isGlobal = Auth::hasRole('admin');
?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($requests) ?></span> request(s)</p>
</div>

<div class="space-y-3">
    <?php if (!$requests): ?>
        <div class="ui-card p-12 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 items-center justify-center mb-3"><i class="fa-solid fa-user-lock text-xl"></i></span>
            <p class="text-sm font-medium text-gray-500">No password reset requests.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($requests as $idx => $r): ?>
        <?php
            $pending = $r['status'] === 'pending';
            $hasAccount = !empty($r['student_user_id']);
        ?>
        <div class="ui-card ui-card-hover p-5" x-data="{ open:false }" data-reveal="<?= min($idx,6) ?>">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <?= status_badge($r['status']) ?>
                        <?php if (!$r['student_id']): ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600"><i class="fa-solid fa-triangle-exclamation text-[9px] mr-1"></i>No matching student</span>
                        <?php elseif (!$hasAccount): ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600"><i class="fa-solid fa-triangle-exclamation text-[9px] mr-1"></i>No login account</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="font-display font-bold text-gray-800 mt-2"><?= e($r['full_name']) ?>
                        <span class="font-mono text-xs text-gray-500 font-normal">· <?= e($r['student_code']) ?></span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">
                        <?php if ($isGlobal): ?><i class="fa-solid fa-building text-[9px] mr-1"></i><?= e($r['hostel_name'] ?? '— Unknown hostel —') ?> · <?php endif; ?>
                        <i class="fa-solid fa-clock text-[9px] mr-1"></i><?= datef($r['created_at'],'d M Y H:i') ?>
                        <?php if (!empty($r['contact'])): ?> · <i class="fa-solid fa-address-book text-[9px] mr-1"></i><?= e($r['contact']) ?><?php endif; ?>
                    </p>
                    <?php if (!empty($r['message'])): ?>
                        <p class="text-sm text-gray-600 mt-2 bg-gray-50 border border-gray-100 rounded-lg p-2.5"><?= e($r['message']) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($pending): ?>
                    <div class="flex items-center gap-2 shrink-0">
                        <?php if ($hasAccount): ?>
                            <button @click="open=!open" class="btn btn-primary px-3 py-1.5 text-sm"><i class="fa-solid fa-key"></i><span class="hidden sm:inline">Reset</span></button>
                        <?php endif; ?>
                        <form method="post" action="<?= url('/password-requests/'.$r['id'].'/reject') ?>" onsubmit="return confirm('Dismiss this request?')">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"><i class="fa-solid fa-xmark"></i><span class="hidden sm:inline">Dismiss</span></button>
                        </form>
                    </div>
                <?php elseif ($r['status'] === 'resolved'): ?>
                    <span class="text-xs text-gray-400 shrink-0"><i class="fa-solid fa-check text-green-500 mr-1"></i>Handled</span>
                <?php endif; ?>
            </div>

            <?php if ($pending && $hasAccount): ?>
                <form x-show="open" x-cloak x-transition method="post" action="<?= url('/password-requests/'.$r['id'].'/resolve') ?>"
                      class="mt-4 pt-4 border-t border-gray-100 flex flex-col sm:flex-row gap-3 sm:items-end">
                    <?= csrf_field() ?>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-600 mb-1.5">New password for this student</label>
                        <input name="password" required minlength="<?= MIN_PASSWORD_LENGTH ?>" class="ui-input" autocomplete="new-password" placeholder="At least <?= MIN_PASSWORD_LENGTH ?> characters">
                    </div>
                    <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Set Password</button>
                </form>
                <p x-show="open" x-cloak class="text-xs text-gray-400 mt-2">Share the new password with the student through a trusted channel; they can change it after signing in.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<style>[x-cloak]{display:none}</style>
