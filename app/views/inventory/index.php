<?php /** @var array $items @var array $lowStock */
use App\Core\Auth;
$canManage = Auth::hasRole('admin','hostel_admin');
?>
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-gray-500"><span class="font-semibold text-gray-700"><?= count($items) ?></span> item type(s)</p>
    <?php if ($canManage): ?>
        <a href="<?= url('/inventory/create') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Item</a>
    <?php endif; ?>
</div>

<?php if ($lowStock): ?>
    <div class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800 text-sm shadow-sm" data-reveal="0">
        <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
        <div><strong><?= count($lowStock) ?></strong> item(s) at or below reorder level:
        <?= e(implode(', ', array_map(fn($i) => $i['name'] . ' (' . $i['quantity'] . ')', $lowStock))) ?></div>
    </div>
<?php endif; ?>

<div class="ui-card overflow-hidden" data-reveal="1">
    <div class="overflow-x-auto">
        <table class="w-full text-sm ui-table">
            <thead class="text-gray-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 font-semibold">Item</th>
                    <th class="px-4 py-3 font-semibold">Category</th>
                    <th class="px-4 py-3 font-semibold">Hostel</th>
                    <th class="px-4 py-3 font-semibold">Qty</th>
                    <th class="px-4 py-3 font-semibold">Condition</th>
                    <?php if ($canManage): ?><th class="px-4 py-3 font-semibold text-right">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (!$items): ?><tr><td colspan="6" class="px-4 py-14 text-center">
                    <div class="inline-flex flex-col items-center text-gray-400">
                        <span class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3"><i class="fa-solid fa-boxes-stacked text-xl"></i></span>
                        <p class="text-sm font-medium text-gray-500">No inventory items</p>
                    </div>
                </td></tr><?php endif; ?>
                <?php foreach ($items as $i): $low = ($i['reorder_level']>0 && $i['quantity']<=$i['reorder_level']); ?>
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-700"><?= e($i['name']) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= ucwords(str_replace('_',' ',$i['category'])) ?></td>
                        <td class="px-4 py-3 text-gray-500"><?= e($i['hostel_name'] ?? '—') ?></td>
                        <td class="px-4 py-3 font-medium tnum <?= $low?'text-amber-600':'text-gray-700' ?>"><?= (int)$i['quantity'] ?><?php if ($low): ?> <i class="fa-solid fa-triangle-exclamation text-xs" title="Low stock"></i><?php endif; ?></td>
                        <td class="px-4 py-3"><?= status_badge($i['condition']) ?></td>
                        <?php if ($canManage): ?>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="<?= url('/inventory/'.$i['id'].'/edit') ?>" class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-amber-50 hover:text-amber-600 transition" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <form method="post" action="<?= url('/inventory/'.$i['id'].'/delete') ?>" class="inline" onsubmit="return confirm('Delete item?')"><?= csrf_field() ?><button class="inline-flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 transition" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
