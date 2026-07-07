<?php
/**
 * Shared <head> resources for every layout.
 * Defines the corporate-navy theme by OVERRIDING Tailwind's `indigo`
 * palette (so all existing `indigo-*` classes across every view retone
 * instantly) and cooling the neutral `gray` scale to slate. Also registers
 * semantic colours, the typeface, elevation and motion tokens.
 */
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#203655">
<meta name="color-scheme" content="light">
<title><?= e($pageTitle) ?> · <?= e(brand_short()) ?></title>

<!-- Favicon: a tight, square icon derived from the system logo -->
<?php if ($faviconUrl = brand_favicon()): ?>
    <link rel="icon" type="image/png" sizes="128x128" href="<?= e($faviconUrl) ?>">
    <link rel="apple-touch-icon" href="<?= e($faviconUrl) ?>">
<?php endif; ?>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

<!-- Tailwind (Play CDN) + Alpine + Font Awesome -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                /* Corporate deep blue — overrides Tailwind's default indigo
                   AND is aliased as `primary` for new markup. */
                indigo: {
                    50:'#eff5fb',100:'#dbe8f5',200:'#bdd4ec',300:'#90b6dd',400:'#5c91c9',
                    500:'#3a72b3',600:'#2b5b97',700:'#254a7a',800:'#233f66',900:'#203655',950:'#142339'
                },
                primary: {
                    50:'#eff5fb',100:'#dbe8f5',200:'#bdd4ec',300:'#90b6dd',400:'#5c91c9',
                    500:'#3a72b3',600:'#2b5b97',700:'#254a7a',800:'#233f66',900:'#203655',950:'#142339'
                },
                /* Cool, slightly desaturated neutrals (slate) for a corporate feel */
                gray: {
                    50:'#f8fafc',100:'#f1f5f9',200:'#e2e8f0',300:'#cbd5e1',400:'#94a3b8',
                    500:'#64748b',600:'#475569',700:'#334155',800:'#1e293b',900:'#0f172a',950:'#020617'
                },
            },
            fontFamily: {
                sans: ['Inter','ui-sans-serif','system-ui','sans-serif'],
                display: ['"Plus Jakarta Sans"','Inter','sans-serif'],
            },
            boxShadow: {
                card: '0 1px 3px rgba(16,33,64,.07), 0 1px 2px rgba(16,33,64,.04)',
                pop: '0 10px 25px -5px rgba(16,33,64,.14), 0 8px 10px -6px rgba(16,33,64,.08)',
            },
            keyframes: {
                fadeInUp: { '0%':{opacity:0,transform:'translateY(14px)'}, '100%':{opacity:1,transform:'none'} },
                popIn:    { '0%':{opacity:0,transform:'scale(.94)'}, '100%':{opacity:1,transform:'scale(1)'} },
            },
            animation: {
                'fade-up': 'fadeInUp .55s cubic-bezier(.22,1,.36,1) both',
                'pop-in':  'popIn .4s cubic-bezier(.22,1,.36,1) both',
            },
        }
    }
};
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
<style>[x-cloak]{display:none!important}</style>
