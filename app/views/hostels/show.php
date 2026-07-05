<?php /** @var array $hostel @var array $rooms @var ?int $firstBlockId */
$facilities = array_filter(explode(',', (string)$hostel['facilities']));
// Floors live under a block: target the first block when present, else the
// blocks page so the admin can create a block before adding floors.
$floorsUrl = !empty($firstBlockId)
    ? url('/blocks/'.$firstBlockId.'/floors')
    : url('/hostels/'.$hostel['id'].'/blocks');
?>
<div class="flex items-center justify-between mb-4">
    <a href="<?= url('/hostels') ?>" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-600 transition"><i class="fa-solid fa-arrow-left"></i>Back</a>
    <div class="flex gap-2">
        <a href="<?= url('/hostels/'.$hostel['id'].'/blocks') ?>" class="btn bg-primary-50 text-primary-700 hover:bg-primary-100"><i class="fa-solid fa-layer-group"></i>Manage Blocks</a>
        <a href="<?= $floorsUrl ?>" class="btn bg-primary-50 text-primary-700 hover:bg-primary-100"><i class="fa-solid fa-stairs"></i>Manage Floors</a>
        <a href="<?= url('/hostels/'.$hostel['id'].'/edit') ?>" class="btn bg-amber-500 hover:bg-amber-600 text-white"><i class="fa-solid fa-pen"></i>Edit</a>
    </div>
</div>

<div class="ui-card overflow-hidden mb-4" data-reveal="0">
    <div class="h-24 bg-gradient-to-br from-primary-600 to-primary-900 relative">
        <div aria-hidden="true" class="absolute inset-0 opacity-[0.07]" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:16px 16px;"></div>
    </div>
    <div class="p-6 -mt-12 relative">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-end gap-4">
                <div class="w-20 h-20 rounded-2xl bg-white shadow-pop ring-1 ring-gray-100 flex items-center justify-center text-primary-600 text-3xl"><i class="fa-solid fa-building"></i></div>
                <div class="pb-1">
                    <h2 class="text-2xl font-display font-extrabold text-gray-800"><?= e($hostel['name']) ?></h2>
                    <p class="text-gray-500 text-sm"><?= e($hostel['code']) ?> · <?= ucfirst($hostel['type']) ?> · Manager: <?= e($hostel['manager'] ?: '—') ?></p>
                </div>
            </div>
            <div class="pt-12"><?= status_badge($hostel['status']) ?></div>
        </div>
        <p class="text-sm text-gray-500 mt-3"><i class="fa-solid fa-location-dot text-gray-400 mr-1"></i><?= e($hostel['address']) ?></p>
        <?php if ($hostel['description']): ?><p class="text-sm text-gray-600 mt-2"><?= e($hostel['description']) ?></p><?php endif; ?>
        <?php if ($facilities): ?>
            <div class="flex flex-wrap gap-2 mt-4">
                <?php foreach ($facilities as $f): ?>
                    <span class="px-3 py-1 bg-primary-50 text-primary-700 rounded-full text-xs font-medium"><i class="fa-solid fa-check text-[9px] mr-1"></i><?= e($f) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="ui-card overflow-hidden" data-reveal="1">
    <div class="flex items-center justify-between p-5 pb-3">
        <h3 class="font-display font-bold text-gray-800">Rooms <span class="text-sm font-normal text-gray-400">(<?= count($rooms) ?>)</span></h3>
        <a href="<?= url('/rooms/create') ?>" class="text-sm font-medium text-primary-600 hover:underline">+ Add Room</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr><th class="px-5 py-2.5 font-semibold">Room</th><th class="px-3 py-2.5 font-semibold">Type</th><th class="px-3 py-2.5 font-semibold">Beds</th><th class="px-3 py-2.5 font-semibold">Price</th><th class="px-5 py-2.5 font-semibold">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$rooms): ?><tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">No rooms.</td></tr><?php endif; ?>
                <?php foreach ($rooms as $r): ?>
                    <tr>
                        <td class="px-5 py-2.5 font-medium text-gray-700"><?= e($r['room_number']) ?></td>
                        <td class="px-3 py-2.5 text-gray-500"><?= ucfirst($r['room_type']) ?></td>
                        <td class="px-3 py-2.5 tnum"><?= (int)$r['occupied'] ?>/<?= (int)$r['capacity'] ?></td>
                        <td class="px-3 py-2.5 tnum"><?= money($r['price']) ?></td>
                        <td class="px-5 py-2.5"><?= status_badge($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
