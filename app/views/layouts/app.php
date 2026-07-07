<?php
use App\Core\Auth;
use App\Core\Session;
use App\Services\Notify;

$user   = Auth::user();
$role   = Auth::role();
$flashes = Session::getFlashes();
$nav    = require VIEW_PATH . '/layouts/_nav.php';
$pageTitle = $pageTitle ?? 'Dashboard';
$unread   = $user ? Notify::unreadCount((int) $user['id']) : 0;
$notifs   = $user ? Notify::recent((int) $user['id']) : [];
$initial  = strtoupper(substr($user['name'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <?php require VIEW_PATH . '/layouts/_head.php'; ?>
</head>
<body class="h-full bg-gray-100 text-gray-800 font-sans antialiased"
      x-data="{ sidebar: window.innerWidth > 1024 && localStorage.getItem('sidebar') !== 'closed' }"
      x-init="$watch('sidebar', v => localStorage.setItem('sidebar', v ? 'open' : 'closed'))"
      @keydown.escape.window="if (window.innerWidth <= 1024) sidebar = false">

<a href="#main-content" class="skip-link">Skip to main content</a>

<div class="min-h-full flex">

    <!-- Mobile backdrop -->
    <div x-show="sidebar && window.innerWidth <= 1024" x-cloak x-transition.opacity
         @click="sidebar = false"
         class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-30 lg:hidden"></div>

    <!-- Sidebar -->
    <aside class="bg-gradient-to-b from-primary-900 to-primary-950 text-primary-100 w-72 flex-shrink-0 flex flex-col fixed inset-y-0 z-40 transition-transform duration-300 ease-out"
           :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           aria-label="Main navigation">
        <div class="h-16 flex items-center gap-3 px-5 border-b border-white/10">
            <?php if ($brandLogo = brand_logo()): ?>
                <img src="<?= e($brandLogo) ?>" alt="Logo" class="w-12 h-12 rounded-xl object-cover shrink-0">
            <?php else: ?>
                <div class="w-9 h-9 rounded-xl bg-white/10 ring-1 ring-white/15 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-building-columns text-lg text-white"></i>
                </div>
            <?php endif; ?>
            <div class="leading-tight min-w-0">
                <span class="block font-display font-extrabold text-white tracking-tight truncate"><?= e(brand_short()) ?></span>
                <span class="block text-[10px] uppercase tracking-widest text-primary-300">Hostel Suite</span>
            </div>
            <button @click="sidebar = false" class="ml-auto w-8 h-8 rounded-lg flex items-center justify-center text-primary-300 hover:text-white hover:bg-white/10 transition" aria-label="Collapse sidebar" title="Collapse sidebar">
                <i class="fa-solid fa-angles-left"></i>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-5" aria-label="Sections">
            <?php foreach ($nav as $group): ?>
                <div>
                    <p class="px-3 mb-1.5 text-[10px] font-semibold uppercase tracking-widest text-primary-400/80"><?= e($group['heading']) ?></p>
                    <div class="space-y-0.5">
                        <?php foreach ($group['items'] as [$label, $path, $icon]): $active = is_active($path); ?>
                            <a href="<?= url($path) ?>"
                               class="nav-link group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm
                                      <?= $active
                                            ? 'bg-white/12 text-white font-semibold shadow-inner ring-1 ring-white/10'
                                            : 'text-primary-200 hover:bg-white/8 hover:text-white' ?>"
                               <?= $active ? 'aria-current="page"' : '' ?>>
                                <i class="nav-icon fa-solid <?= $icon ?> w-5 text-center <?= $active ? 'text-white' : 'text-primary-300 group-hover:text-white' ?>"></i>
                                <span><?= e($label) ?></span>
                                <?php if ($active): ?><span class="ml-auto w-1.5 h-1.5 rounded-full bg-white"></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-white/10">
            <div class="flex items-center gap-3 rounded-xl bg-white/5 px-3 py-2.5">
                <div class="w-8 h-8 rounded-full bg-white/10 ring-1 ring-white/15 flex items-center justify-center text-xs font-bold text-white shrink-0">
                    <?= e($initial) ?>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white truncate"><?= e($user['name'] ?? '') ?></p>
                    <p class="text-[10px] text-primary-300"><?= role_label($role) ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main column -->
    <div class="flex-1 flex flex-col min-w-0 transition-[margin] duration-300 ease-out"
         :class="sidebar ? 'lg:ml-72' : 'lg:ml-0'">
        <!-- Topbar -->
        <header class="app-topbar h-16 bg-white/90 backdrop-blur border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 sticky top-0 z-20">
            <div class="flex items-center gap-3 min-w-0">
                <button @click="sidebar = !sidebar" class="w-9 h-9 -ml-1 rounded-lg hover:bg-gray-100 text-gray-600 flex items-center justify-center transition" :aria-expanded="sidebar" aria-label="Toggle sidebar" title="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="min-w-0">
                    <h1 class="text-base sm:text-lg font-display font-bold text-gray-800 truncate"><?= e($pageTitle) ?></h1>
                </div>
            </div>
            <div class="flex items-center gap-1 sm:gap-2">
                <!-- Notifications bell -->
                <div class="relative" x-data="{ bell:false }">
                    <button @click="bell=!bell" class="relative w-10 h-10 rounded-xl hover:bg-gray-100 text-gray-500 hover:text-primary-600 flex items-center justify-center transition" aria-label="Notifications">
                        <i class="fa-solid fa-bell text-lg"></i>
                        <?php if ($unread > 0): ?>
                            <span class="absolute top-1.5 right-1.5 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center ring-2 ring-white"><?= $unread > 9 ? '9+' : $unread ?></span>
                        <?php endif; ?>
                    </button>
                    <div x-show="bell" @click.outside="bell=false" x-transition.origin.top.right x-cloak
                         class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-pop border border-gray-200 overflow-hidden z-30">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-700">Notifications</span>
                            <?php if ($unread > 0): ?><span class="text-[11px] font-medium text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full"><?= $unread ?> new</span><?php endif; ?>
                        </div>
                        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                            <?php if (!$notifs): ?>
                                <div class="px-4 py-10 text-center">
                                    <i class="fa-regular fa-bell-slash text-2xl text-gray-300"></i>
                                    <p class="mt-2 text-sm text-gray-400">No notifications yet.</p>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($notifs as $n): ?>
                                <a href="<?= url('/notifications/'.$n['id'].'/read') ?>" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition <?= $n['is_read'] ? '' : 'bg-primary-50/50' ?>">
                                    <span class="mt-0.5 w-8 h-8 rounded-lg bg-primary-100 text-primary-600 flex items-center justify-center shrink-0">
                                        <i class="fa-solid <?= e($n['icon'] ?: 'fa-bell') ?> text-xs"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-gray-700 leading-snug"><?= e($n['title']) ?></p>
                                        <p class="text-xs text-gray-400 mt-0.5"><?= datef($n['created_at'],'d M H:i') ?></p>
                                    </div>
                                    <?php if (!$n['is_read']): ?><span class="mt-1.5 w-2 h-2 rounded-full bg-primary-500 shrink-0"></span><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?= url('/notifications') ?>" class="block px-4 py-2.5 text-center text-sm font-medium text-primary-600 hover:bg-gray-50 border-t border-gray-100">View all notifications</a>
                    </div>
                </div>

                <!-- User menu -->
                <div class="relative" x-data="{ open:false }">
                    <button @click="open=!open" class="flex items-center gap-2 pl-1 pr-2 py-1 rounded-xl hover:bg-gray-100 transition" aria-label="Account menu">
                        <div class="w-9 h-9 rounded-full overflow-hidden bg-gradient-to-br from-primary-500 to-primary-700 text-white flex items-center justify-center font-semibold ring-2 ring-white shadow-sm">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= url('/uploads/'.$user['avatar']) ?>" alt="" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?= e($initial) ?>
                            <?php endif; ?>
                        </div>
                        <span class="hidden sm:block text-sm font-medium text-gray-700 max-w-[10rem] truncate"><?= e($user['name'] ?? '') ?></span>
                        <i class="fa-solid fa-chevron-down text-xs text-gray-400 hidden sm:block"></i>
                    </button>
                    <div x-show="open" @click.outside="open=false" x-transition.origin.top.right x-cloak
                         class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-pop border border-gray-200 py-1.5 text-sm overflow-hidden z-30">
                        <div class="px-4 py-2.5 border-b border-gray-100">
                            <p class="font-semibold text-gray-800 truncate"><?= e($user['name'] ?? '') ?></p>
                            <p class="text-xs text-gray-400"><?= role_label($role) ?></p>
                        </div>
                        <a href="<?= url('/profile') ?>" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 text-gray-700"><i class="fa-solid fa-user w-4 text-gray-400"></i>My Profile</a>
                        <a href="<?= url('/logout') ?>" class="flex items-center gap-3 px-4 py-2.5 hover:bg-red-50 text-red-600"><i class="fa-solid fa-right-from-bracket w-4"></i>Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main content -->
        <main id="main-content" class="app-main flex-1 p-4 sm:p-6 lg:p-8 max-w-[1600px] w-full mx-auto" tabindex="-1">
            <?php foreach ($flashes as $type => $msg): ?>
                <?php
                    $tone = $type === 'success' ? ['green','fa-circle-check']
                          : ($type === 'error' ? ['red','fa-circle-exclamation'] : ['blue','fa-circle-info']);
                    [$color, $ficon] = $tone;
                ?>
                <div x-data="{show:true}" x-show="show" x-cloak x-transition.opacity.duration.300ms
                     x-init="setTimeout(()=>show=false, 5000)" role="alert"
                     class="mb-4 flex items-start gap-3 rounded-xl border border-<?= $color ?>-200 bg-<?= $color ?>-50 p-4 text-<?= $color ?>-800 shadow-sm animate-fade-up">
                    <i class="fa-solid <?= $ficon ?> mt-0.5 text-<?= $color ?>-500"></i>
                    <span class="flex-1 text-sm font-medium"><?= e($msg) ?></span>
                    <button @click="show=false" class="text-<?= $color ?>-400 hover:text-<?= $color ?>-600" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endforeach; ?>

            <div class="animate-fade-up">
                <?= $content ?>
            </div>
        </main>

        <footer class="app-footer px-6 py-5 text-center text-xs text-gray-400">
            &copy; <?= date('Y') ?> <?= e(brand_name()) ?> · <span class="text-gray-300">v1.0</span>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>
