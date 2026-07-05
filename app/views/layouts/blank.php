<?php
use App\Core\Session;
$flashes = Session::getFlashes();
$pageTitle = $pageTitle ?? brand_name();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <?php require VIEW_PATH . '/layouts/_head.php'; ?>
</head>
<body class="h-full font-sans antialiased relative overflow-x-hidden
             bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950 text-white">

    <!-- Decorative ambient glows -->
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -top-32 -left-24 w-[28rem] h-[28rem] rounded-full bg-primary-500/20 blur-3xl"></div>
        <div class="absolute top-1/3 -right-32 w-[26rem] h-[26rem] rounded-full bg-sky-400/10 blur-3xl"></div>
        <div class="absolute -bottom-40 left-1/3 w-[30rem] h-[30rem] rounded-full bg-primary-400/10 blur-3xl"></div>
        <div class="absolute inset-0 opacity-[0.04]"
             style="background-image:radial-gradient(#fff 1px,transparent 1px);background-size:22px 22px;"></div>
    </div>

    <?php foreach ($flashes as $type => $msg): ?>
        <?php $color = $type === 'success' ? 'green' : ($type === 'error' ? 'red' : 'blue'); ?>
        <div class="fixed top-4 left-1/2 -translate-x-1/2 z-50 rounded-xl bg-white shadow-pop border-l-4 border-<?= $color ?>-500 px-5 py-3 text-sm text-<?= $color ?>-800 animate-fade-up" role="alert">
            <?= e($msg) ?>
        </div>
    <?php endforeach; ?>

    <div class="relative z-10">
        <?= $content ?>
    </div>

    <script defer src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
