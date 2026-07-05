<?php
/**
 * Pagination links. Expects $pager (Paginator::make result) in scope.
 * Preserves all existing query-string params except `page`.
 */
if (empty($pager) || ($pager['lastPage'] ?? 1) <= 1) {
    if (!empty($pager) && ($pager['total'] ?? 0) > 0) {
        echo '<div class="px-4 py-3 text-xs text-gray-400">Showing all ' . (int) $pager['total'] . ' result(s)</div>';
    }
    return;
}

$query = $_GET;
unset($query['page']);
$base = '?' . http_build_query($query);
$sep  = $query ? '&' : '';
$link = fn(int $p) => $base . $sep . 'page=' . $p;

$page = $pager['page'];
$last = $pager['lastPage'];
$start = max(1, $page - 2);
$end   = min($last, $page + 2);
?>
<nav class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-gray-100 bg-gray-50/60" aria-label="Pagination">
    <p class="text-xs text-gray-500">Showing <span class="font-semibold text-gray-700"><?= $pager['from'] ?>–<?= $pager['to'] ?></span> of <span class="font-semibold text-gray-700"><?= $pager['total'] ?></span></p>
    <div class="flex items-center gap-1">
        <a href="<?= $page > 1 ? e($link($page - 1)) : '#' ?>" <?= $page > 1 ? '' : 'aria-disabled="true" tabindex="-1"' ?>
           class="w-9 h-9 flex items-center justify-center rounded-lg text-sm transition <?= $page > 1 ? 'bg-white border border-gray-200 hover:border-primary-300 hover:text-primary-600 text-gray-600' : 'bg-gray-100 text-gray-300 pointer-events-none' ?>" aria-label="Previous page">
            <i class="fa-solid fa-chevron-left text-xs"></i>
        </a>
        <?php if ($start > 1): ?>
            <a href="<?= e($link(1)) ?>" class="min-w-9 h-9 px-3 flex items-center justify-center rounded-lg text-sm bg-white border border-gray-200 hover:border-primary-300 hover:text-primary-600 text-gray-600 transition">1</a>
            <?php if ($start > 2): ?><span class="px-1 text-gray-400">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
            <a href="<?= e($link($p)) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?>
               class="min-w-9 h-9 px-3 flex items-center justify-center rounded-lg text-sm font-medium transition <?= $p === $page ? 'bg-primary-600 text-white shadow-sm shadow-primary-600/30' : 'bg-white border border-gray-200 hover:border-primary-300 hover:text-primary-600 text-gray-600' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($end < $last): ?>
            <?php if ($end < $last - 1): ?><span class="px-1 text-gray-400">…</span><?php endif; ?>
            <a href="<?= e($link($last)) ?>" class="min-w-9 h-9 px-3 flex items-center justify-center rounded-lg text-sm bg-white border border-gray-200 hover:border-primary-300 hover:text-primary-600 text-gray-600 transition"><?= $last ?></a>
        <?php endif; ?>
        <a href="<?= $page < $last ? e($link($page + 1)) : '#' ?>" <?= $page < $last ? '' : 'aria-disabled="true" tabindex="-1"' ?>
           class="w-9 h-9 flex items-center justify-center rounded-lg text-sm transition <?= $page < $last ? 'bg-white border border-gray-200 hover:border-primary-300 hover:text-primary-600 text-gray-600' : 'bg-gray-100 text-gray-300 pointer-events-none' ?>" aria-label="Next page">
            <i class="fa-solid fa-chevron-right text-xs"></i>
        </a>
    </div>
</nav>
