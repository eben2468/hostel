<?php /** @var ?array $student @var ?array $allocation @var array $invoices @var float $outstanding @var array $notices */
use App\Core\Auth;
$me = Auth::user();
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!-- Greeting -->
<div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary-700 to-primary-900 text-white p-6 mb-5 shadow-pop">
    <div aria-hidden="true" class="absolute -right-8 -top-8 w-40 h-40 rounded-full bg-white/10 blur-2xl"></div>
    <div class="relative">
        <p class="text-primary-200 text-sm"><?= $greet ?>,</p>
        <h2 class="text-2xl font-display font-extrabold tracking-tight"><?= e($me['name'] ?? 'Student') ?></h2>
        <p class="text-primary-200 text-sm mt-1"><?= date('l, j F Y') ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Room card -->
    <div class="ui-card ui-card-hover p-5" data-reveal="0">
        <h3 class="font-display font-bold text-gray-800 mb-3"><span class="inline-flex w-8 h-8 rounded-lg bg-primary-50 text-primary-600 items-center justify-center mr-2"><i class="fa-solid fa-bed text-sm"></i></span>My Room</h3>
        <?php if ($allocation): ?>
            <p class="text-2xl font-bold text-gray-800"><?= e($allocation['room_number']) ?></p>
            <p class="text-sm text-gray-500"><?= e($allocation['hostel_name']) ?> · <?= e($allocation['bed_number'] ?? 'Bed') ?></p>
            <div class="mt-2"><?= status_badge($allocation['status']) ?></div>
        <?php else: ?>
            <p class="text-sm text-gray-400">You have no active room allocation.</p>
            <a href="<?= url('/applications/create') ?>" class="inline-flex items-center gap-1 mt-3 text-primary-600 text-sm font-medium hover:underline">Apply for accommodation <i class="fa-solid fa-arrow-right text-xs"></i></a>
        <?php endif; ?>
    </div>

    <!-- Payment card -->
    <div class="ui-card ui-card-hover p-5" data-reveal="1">
        <h3 class="font-display font-bold text-gray-800 mb-3"><span class="inline-flex w-8 h-8 rounded-lg bg-green-50 text-green-600 items-center justify-center mr-2"><i class="fa-solid fa-money-bill-wave text-sm"></i></span>Balance</h3>
        <p class="text-2xl font-bold tnum <?= $outstanding > 0 ? 'text-red-600' : 'text-green-600' ?>" data-count="<?= (float) $outstanding ?>" data-decimals="2" data-prefix="<?= CURRENCY_SYMBOL ?> ">0</p>
        <p class="text-sm text-gray-500"><?= $outstanding > 0 ? 'Outstanding' : 'All cleared' ?></p>
        <a href="<?= url('/payments') ?>" class="inline-flex items-center gap-1 mt-3 text-primary-600 text-sm font-medium hover:underline">View payments <i class="fa-solid fa-arrow-right text-xs"></i></a>
    </div>

    <!-- Quick actions -->
    <div class="ui-card p-5" data-reveal="2">
        <h3 class="font-display font-bold text-gray-800 mb-3"><span class="inline-flex w-8 h-8 rounded-lg bg-amber-50 text-amber-600 items-center justify-center mr-2"><i class="fa-solid fa-bolt text-sm"></i></span>Quick Actions</h3>
        <div class="space-y-2 text-sm">
            <a href="<?= url('/applications/create') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-gray-50 hover:bg-primary-50 hover:text-primary-700 transition"><i class="fa-solid fa-file-pen w-4 text-gray-400"></i>Apply for Hostel</a>
            <a href="<?= url('/complaints/create') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-gray-50 hover:bg-primary-50 hover:text-primary-700 transition"><i class="fa-solid fa-screwdriver-wrench w-4 text-gray-400"></i>Report a Complaint</a>
            <a href="<?= url('/profile') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-gray-50 hover:bg-primary-50 hover:text-primary-700 transition"><i class="fa-solid fa-user w-4 text-gray-400"></i>Update Profile</a>
        </div>
    </div>
</div>

<!-- Invoices + notices -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
    <div class="ui-card p-5" data-reveal="0">
        <h3 class="font-display font-bold text-gray-800 mb-3">My Invoices</h3>
        <?php if (!$invoices): ?><p class="text-sm text-gray-400 py-6 text-center">No invoices yet.</p><?php endif; ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($invoices as $inv): ?>
                <div class="flex items-center justify-between py-2.5">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-700 truncate"><?= e($inv['invoice_no']) ?></p>
                        <p class="text-xs text-gray-400 truncate"><?= e($inv['description'] ?? '') ?></p>
                    </div>
                    <div class="text-right shrink-0 ml-3">
                        <p class="text-sm font-semibold text-gray-800 tnum"><?= money($inv['balance']) ?></p>
                        <?= status_badge($inv['status']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="ui-card p-5" data-reveal="1">
        <h3 class="font-display font-bold text-gray-800 mb-3">Notices</h3>
        <?php if (!$notices): ?><p class="text-sm text-gray-400 py-6 text-center">No notices.</p><?php endif; ?>
        <div class="space-y-3">
            <?php foreach ($notices as $n): ?>
                <div class="border-l-4 border-primary-400 bg-primary-50/40 rounded-r-lg pl-3 pr-2 py-2">
                    <p class="text-sm font-medium text-gray-700"><?= e($n['title']) ?></p>
                    <p class="text-xs text-gray-500 line-clamp-2"><?= e(mb_strimwidth(strip_tags($n['body']), 0, 120, '…')) ?></p>
                    <p class="text-xs text-gray-400 mt-1"><?= datef($n['created_at']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
