<?php /** @var array $rooms @var ?array $hostels @var array $floors @var array $filters */
$filters = $filters ?? ['q' => '', 'hostel' => '', 'type' => '', 'status' => '', 'floor' => ''];
$hostels = $hostels ?? null;
$floors  = $floors ?? [];
$hasFilters = ($filters['q'] ?? '') !== '' || ($filters['hostel'] ?? '') !== '' || ($filters['type'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['floor'] ?? '') !== '';
$multiHostel = $hostels !== null; // super admin sees floors across hostels → add hostel to the label
$roomTypes = ['single','double','triple','quad'];
$statuses  = ['available','occupied','reserved','maintenance','closed'];
?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($rooms) ?></span> room(s)<?php if ($hasFilters): ?> <span class="text-gray-400">found</span><?php endif; ?></p>
    <a href="<?= url('/rooms/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Room</a>
</div>

<!-- Filters -->
<form method="get" action="<?= url('/rooms') ?>" class="ui-card p-3 mb-4 flex flex-wrap items-end gap-3" data-reveal="0">
    <div class="flex-1 min-w-[10rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Search room</label>
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
            <input name="q" value="<?= e($filters['q']) ?>" placeholder="Room number" class="ui-input pl-8">
        </div>
    </div>
    <?php if ($hostels !== null): ?>
    <div class="min-w-[9rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Hostel</label>
        <select name="hostel" class="ui-input">
            <option value="">All hostels</option>
            <?php foreach ($hostels as $h): ?>
                <option value="<?= (int)$h['id'] ?>" <?= (string)$filters['hostel']===(string)$h['id']?'selected':'' ?>><?= e($h['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <?php if ($floors): ?>
    <div class="min-w-[10rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Floor</label>
        <select name="floor" class="ui-input">
            <option value="">All floors</option>
            <?php foreach ($floors as $f):
                $label = ($multiHostel && !empty($f['hostel_name']) ? $f['hostel_name'].' · ' : '')
                       . (!empty($f['block_name']) ? $f['block_name'].' · ' : '') . $f['number'];
            ?>
                <option value="<?= (int)$f['id'] ?>" <?= (string)$filters['floor']===(string)$f['id']?'selected':'' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="min-w-[8rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Type</label>
        <select name="type" class="ui-input">
            <option value="">All types</option>
            <?php foreach ($roomTypes as $t): ?>
                <option value="<?= $t ?>" <?= $filters['type']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="min-w-[8rem]">
        <label class="block text-[11px] font-medium text-gray-500 mb-1">Status</label>
        <select name="status" class="ui-input">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-center gap-2">
        <button class="btn btn-primary"><i class="fa-solid fa-filter"></i>Apply</button>
        <?php if ($hasFilters): ?>
            <a href="<?= url('/rooms') ?>" class="btn btn-ghost" title="Clear filters"><i class="fa-solid fa-xmark"></i>Clear</a>
        <?php endif; ?>
    </div>
</form>
<div class="ui-card overflow-hidden" data-reveal="0">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Room</th>
                    <th class="px-4 py-3 font-semibold">Hostel</th>
                    <th class="px-4 py-3 font-semibold">Type</th>
                    <th class="px-4 py-3 font-semibold">Occupancy</th>
                    <th class="px-4 py-3 font-semibold">Price</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$rooms): ?><tr><td colspan="7" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-door-open text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No rooms yet</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($rooms as $r): $pct = (int)$r['capacity']>0?round($r['occupied']/$r['capacity']*100):0; ?>
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-700"><?= e($r['room_number']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($r['hostel_name'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= ucfirst($r['room_type']) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-16 bar-track h-1.5"><div class="bar-fill" data-width="<?= $pct ?>" style="width:0"></div></div>
                                <span class="text-xs text-gray-500 tnum"><?= (int)$r['occupied'] ?>/<?= (int)$r['capacity'] ?></span>
                            </div>
                        </td>
                        <td class="px-4 py-3 tnum text-gray-700"><?= money($r['price']) ?></td>
                        <td class="px-4 py-3"><?= status_badge($r['status']) ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="<?= url('/rooms/'.$r['id'].'/edit') ?>" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
                            <form method="post" action="<?= url('/rooms/'.$r['id'].'/delete') ?>" class="inline" onsubmit="return confirm('Delete room?')">
                                <?= csrf_field() ?>
                                <button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
