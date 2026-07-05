<?php /** @var array $complaints @var bool $isStudent */
use App\Core\Auth;
$canManage = Auth::hasRole('admin','hostel_admin','maintenance');
$statuses = ['open','assigned','in_progress','waiting_parts','completed','rejected','closed'];
?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($complaints) ?></span> complaint(s)</p>
    <?php if (Auth::hasRole('student','admin','hostel_admin')): ?>
        <a href="<?= url('/complaints/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Report Complaint</a>
    <?php endif; ?>
</div>

<div class="space-y-3">
    <?php if (!$complaints): ?>
        <div class="ui-card p-12 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 items-center justify-center mb-3"><i class="fa-solid fa-screwdriver-wrench text-xl"></i></span>
            <p class="text-sm font-medium text-gray-500">No complaints.</p>
        </div>
    <?php endif; ?>
    <?php foreach ($complaints as $idx => $c): ?>
        <?php $pc = ['urgent'=>'red','high'=>'orange','medium'=>'yellow','low'=>'gray'][$c['priority']] ?? 'gray'; ?>
        <div class="ui-card ui-card-hover overflow-hidden flex" x-data="{ open:false }" data-reveal="<?= min($idx,6) ?>">
            <div class="w-1.5 bg-<?= $pc ?>-400 shrink-0"></div>
            <div class="flex-1 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600"><i class="fa-solid fa-tag text-[9px] mr-1"></i><?= ucfirst($c['category']) ?></span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-<?= $pc ?>-100 text-<?= $pc ?>-700"><?= ucfirst($c['priority']) ?> priority</span>
                            <?= status_badge($c['status']) ?>
                        </div>
                        <h3 class="font-display font-bold text-gray-800 mt-2"><?= e($c['title']) ?></h3>
                        <p class="text-sm text-gray-500 mt-1"><?= e($c['description'] ?? '') ?></p>
                        <p class="text-xs text-gray-400 mt-2">
                            <?php if (!$isStudent && !empty($c['full_name'])): ?><i class="fa-solid fa-user text-[9px] mr-1"></i><?= e($c['full_name']) ?> · <?php endif; ?>
                            <i class="fa-solid fa-door-closed text-[9px] mr-1"></i>Room <?= e($c['room_number'] ?: 'N/A') ?> · <?= datef($c['created_at'],'d M Y H:i') ?>
                        </p>
                        <?php if (!empty($c['technician_notes'])): ?>
                            <p class="text-xs text-primary-700 mt-2 bg-primary-50 border border-primary-100 rounded-lg p-2.5"><i class="fa-solid fa-wrench mr-1"></i><?= e($c['technician_notes']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($canManage): ?>
                        <button @click="open=!open" class="btn btn-ghost px-3 py-1.5 text-sm shrink-0"><i class="fa-solid fa-pen"></i><span class="hidden sm:inline">Update</span></button>
                    <?php endif; ?>
                </div>
                <?php if ($canManage): ?>
                    <form x-show="open" x-cloak x-transition method="post" action="<?= url('/complaints/'.$c['id'].'/status') ?>" class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <?= csrf_field() ?>
                        <select name="status" class="ui-input">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $s ?>" <?= $c['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input name="technician_notes" value="<?= e($c['technician_notes'] ?? '') ?>" placeholder="Technician notes" class="ui-input">
                        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i>Save</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php if (!$isStudent && !empty($pager) && $pager['lastPage'] > 1): ?>
    <div class="ui-card mt-3 overflow-hidden">
        <?php require VIEW_PATH . '/layouts/_pagination.php'; ?>
    </div>
<?php endif; ?>
<style>[x-cloak]{display:none}</style>
