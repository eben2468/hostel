<?php /** @var array $hostels */ /** @var bool $canCreate */ $canCreate = $canCreate ?? true; ?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($hostels) ?></span> hostel(s)</p>
    <?php if ($canCreate): ?>
    <a href="<?= url('/hostels/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Hostel</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    <?php if (!$hostels): ?>
        <div class="col-span-full ui-card p-12 text-center">
            <span class="inline-flex w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 items-center justify-center mb-3"><i class="fa-solid fa-building text-xl"></i></span>
            <p class="text-sm font-medium text-gray-500">No hostels yet.</p>
        </div>
    <?php endif; ?>
    <?php
    // Gender/type → icon + tint so each card reads at a glance.
    $typeMeta = [
        'male'       => ['fa-mars',        'text-sky-600',    'bg-sky-50'],
        'female'     => ['fa-venus',       'text-pink-600',   'bg-pink-50'],
        'mixed'      => ['fa-venus-mars',  'text-violet-600', 'bg-violet-50'],
        'private'    => ['fa-key',         'text-amber-600',  'bg-amber-50'],
        'university' => ['fa-graduation-cap','text-primary-600','bg-primary-50'],
    ];
    foreach ($hostels as $idx => $h):
        $cap = (int) $h['bed_capacity']; $occ = (int) $h['occupied_beds'];
        $pct = $cap > 0 ? round($occ / $cap * 100) : 0;
        // Occupancy severity → bar colour + label tint.
        if ($pct >= 90)      { $barBg = 'linear-gradient(90deg,#f43f5e,#e11d48)'; $pctTint = 'text-rose-600 bg-rose-50'; }
        elseif ($pct >= 70)  { $barBg = 'linear-gradient(90deg,#f59e0b,#d97706)'; $pctTint = 'text-amber-600 bg-amber-50'; }
        else                 { $barBg = 'linear-gradient(90deg,#22c55e,#16a34a)'; $pctTint = 'text-emerald-600 bg-emerald-50'; }
        $type = strtolower((string) $h['type']);
        [$tIcon, $tText, $tBg] = $typeMeta[$type] ?? $typeMeta['mixed'];
        $facilities = array_values(array_filter(array_map('trim', explode(',', (string) ($h['facilities'] ?? '')))));
        $hasImage = !empty($h['image']);
    ?>
        <div class="ui-card ui-card-hover overflow-hidden group" data-reveal="<?= min($idx,6) ?>">
            <!-- Header -->
            <div class="relative h-32 flex flex-col justify-between p-4 overflow-hidden">
                <?php if ($hasImage): ?>
                    <img src="<?= url('/uploads/'.$h['image']) ?>" alt="" class="absolute inset-0 w-full h-full object-cover transition duration-500 group-hover:scale-105">
                    <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-br from-primary-900/80 via-primary-800/70 to-primary-900/85"></div>
                <?php else: ?>
                    <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-br from-primary-600 to-primary-900 transition duration-500 group-hover:scale-105"></div>
                <?php endif; ?>
                <div aria-hidden="true" class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10 blur-xl"></div>
                <div aria-hidden="true" class="absolute inset-0 opacity-[0.08]" style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:16px 16px;"></div>

                <!-- Top row: type pill + status -->
                <div class="relative flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-white/95 bg-white/15 backdrop-blur-sm px-2.5 py-1 rounded-full ring-1 ring-white/20">
                        <i class="fa-solid <?= $tIcon ?>"></i><?= ucfirst($type) ?>
                    </span>
                    <?= status_badge($h['status']) ?>
                </div>

                <!-- Name + address -->
                <div class="relative">
                    <h3 class="text-white font-display font-bold text-lg leading-tight drop-shadow-sm"><?= e($h['name']) ?></h3>
                    <?php if (!empty($h['address'])): ?>
                        <p class="text-white/70 text-xs mt-0.5 flex items-center gap-1 truncate"><i class="fa-solid fa-location-dot text-[10px]"></i><span class="truncate"><?= e($h['address']) ?></span></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Body -->
            <div class="p-5 pt-4">
                <!-- Floating type tile (right, clear of the header title) + code -->
                <div class="flex items-end justify-between -mt-9 mb-4 relative">
                    <span class="text-[11px] text-gray-400 font-mono bg-gray-100 px-2 py-1 rounded-md">Code: <?= e($h['code']) ?></span>
                    <span class="w-12 h-12 rounded-2xl <?= $tBg ?> <?= $tText ?> ring-4 ring-white shadow-sm flex items-center justify-center text-lg">
                        <i class="fa-solid <?= $tIcon ?>"></i>
                    </span>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-2 text-center mb-4">
                    <div class="rounded-xl border border-gray-100 bg-gray-50/60 py-2.5 transition hover:border-primary-200 hover:bg-primary-50/50">
                        <i class="fa-solid fa-door-open text-gray-300 text-xs mb-0.5"></i>
                        <p class="text-lg font-bold text-gray-800 tnum leading-none"><?= (int)$h['room_count'] ?></p>
                        <p class="text-[11px] text-gray-400 mt-1">Rooms</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/60 py-2.5 transition hover:border-primary-200 hover:bg-primary-50/50">
                        <i class="fa-solid fa-bed text-gray-300 text-xs mb-0.5"></i>
                        <p class="text-lg font-bold text-gray-800 tnum leading-none"><?= $occ ?></p>
                        <p class="text-[11px] text-gray-400 mt-1">Occupied</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/60 py-2.5 transition hover:border-primary-200 hover:bg-primary-50/50">
                        <i class="fa-solid fa-users text-gray-300 text-xs mb-0.5"></i>
                        <p class="text-lg font-bold text-gray-800 tnum leading-none"><?= $cap ?></p>
                        <p class="text-[11px] text-gray-400 mt-1">Capacity</p>
                    </div>
                </div>

                <!-- Occupancy -->
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-medium text-gray-500">Occupancy</span>
                    <span class="text-[11px] font-bold px-1.5 py-0.5 rounded-md <?= $pctTint ?> tnum"><?= $pct ?>%</span>
                </div>
                <div class="bar-track h-2 mb-4">
                    <div class="bar-fill" data-width="<?= $pct ?>" style="width:0;background:<?= $barBg ?>"></div>
                </div>

                <!-- Facilities -->
                <?php if ($facilities): ?>
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        <?php foreach (array_slice($facilities, 0, 3) as $f): ?>
                            <span class="text-[11px] text-gray-500 bg-gray-100 rounded-md px-2 py-0.5"><?= e($f) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($facilities) > 3): ?>
                            <span class="text-[11px] text-primary-600 bg-primary-50 rounded-md px-2 py-0.5 font-medium">+<?= count($facilities) - 3 ?> more</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="flex gap-2">
                    <a href="<?= url('/hostels/'.$h['id']) ?>" class="btn btn-ghost flex-1"><i class="fa-solid fa-eye"></i>View</a>
                    <a href="<?= url('/hostels/'.$h['id'].'/edit') ?>" class="btn flex-1 bg-primary-50 text-primary-700 hover:bg-primary-100"><i class="fa-solid fa-pen"></i>Edit</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
