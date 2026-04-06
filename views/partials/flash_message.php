<?php
declare(strict_types=1);
/**
 * Carte de message flash (erreur, succès, avertissement, info).
 * Variables : $flash_message (string), $flash_variant ('error'|'success'|'warning'|'info').
 * Optionnel : $flash_title, $flash_description, $flash_surface ('dark' pour fond slate-950),
 *             $flash_margin_class (défaut mb-6, ex. mb-0 dans un conteneur space-y).
 */
$variant = $flash_variant ?? 'error';
$message = isset($flash_message) ? trim((string) $flash_message) : '';
if ($message === '') {
    return;
}

$eyebrowTitle = isset($flash_title) ? (string) $flash_title : null;
$description = isset($flash_description) ? (string) $flash_description : null;

if ($eyebrowTitle === null || $eyebrowTitle === '') {
    if ($variant === 'error') {
        if (preg_match('/confirmez votre adresse|e-mail avant de vous connecter|vérification.*e-mail/i', $message)) {
            $eyebrowTitle = 'Confirmation requise';
            $description = ($description !== null && $description !== '') ? $description : null;
        } elseif (preg_match('/authentification|session|connecter|connecté/i', $message)) {
            $eyebrowTitle = 'Accès refusé';
            $description = ($description !== null && $description !== '') ? $description : 'Cette action ou cette page nécessite une session valide avant de pouvoir être consultée.';
        } else {
            $eyebrowTitle = 'Erreur';
        }
    } elseif ($variant === 'success') {
        $eyebrowTitle = 'Succès';
    } elseif ($variant === 'warning') {
        $eyebrowTitle = 'Attention';
    } else {
        $eyebrowTitle = 'Information';
    }
}

$themes = [
    'error' => [
        'wrap' => 'border-red-200 bg-red-50',
        'iconWrap' => 'bg-red-100 text-red-700 ring-1 ring-red-200',
        'eyebrow' => 'text-red-600',
        'heading' => 'text-red-900',
        'body' => 'text-red-800',
        'icon' => 'error',
    ],
    'success' => [
        'wrap' => 'border-emerald-200 bg-emerald-50',
        'iconWrap' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
        'eyebrow' => 'text-emerald-600',
        'heading' => 'text-emerald-900',
        'body' => 'text-emerald-800',
        'icon' => 'success',
    ],
    'warning' => [
        'wrap' => 'border-amber-200 bg-amber-50',
        'iconWrap' => 'bg-amber-100 text-amber-800 ring-1 ring-amber-200',
        'eyebrow' => 'text-amber-700',
        'heading' => 'text-amber-950',
        'body' => 'text-amber-900',
        'icon' => 'warning',
    ],
    'info' => [
        'wrap' => 'border-slate-200 bg-slate-50',
        'iconWrap' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-200',
        'eyebrow' => 'text-slate-600',
        'heading' => 'text-slate-900',
        'body' => 'text-slate-700',
        'icon' => 'info',
    ],
];

$t = $themes[$variant] ?? $themes['error'];

if (!empty($flash_surface) && $flash_surface === 'dark') {
    $darkOverrides = [
        'error' => [
            'wrap' => 'border-red-500/35 bg-red-950/50',
            'iconWrap' => 'bg-red-950/90 text-red-300 ring-1 ring-red-500/30',
            'eyebrow' => 'text-red-400',
            'heading' => 'text-red-100',
            'body' => 'text-red-200/95',
        ],
        'success' => [
            'wrap' => 'border-emerald-500/35 bg-emerald-950/45',
            'iconWrap' => 'bg-emerald-950/90 text-emerald-300 ring-1 ring-emerald-500/30',
            'eyebrow' => 'text-emerald-400',
            'heading' => 'text-emerald-100',
            'body' => 'text-emerald-200/95',
        ],
        'warning' => [
            'wrap' => 'border-amber-500/35 bg-amber-950/40',
            'iconWrap' => 'bg-amber-950/90 text-amber-300 ring-1 ring-amber-500/30',
            'eyebrow' => 'text-amber-400',
            'heading' => 'text-amber-100',
            'body' => 'text-amber-200/95',
        ],
        'info' => [
            'wrap' => 'border-slate-500/35 bg-slate-900/60',
            'iconWrap' => 'bg-slate-900/90 text-slate-300 ring-1 ring-slate-600/40',
            'eyebrow' => 'text-slate-400',
            'heading' => 'text-slate-100',
            'body' => 'text-slate-300',
        ],
    ];
    if (isset($darkOverrides[$variant])) {
        $t = array_merge($t, $darkOverrides[$variant]);
    }
}

$eyebrowCls = $t['eyebrow'];
$headingCls = $t['heading'];
$bodyCls = $t['body'];
$iconKind = $t['icon'];

$eyebrowLabel = $eyebrowTitle !== null && $eyebrowTitle !== '' ? $eyebrowTitle : 'Erreur';
$flash_margin_class = isset($flash_margin_class) ? (string) $flash_margin_class : 'mb-6';
?>
<div class="<?= htmlspecialchars($flash_margin_class, ENT_QUOTES, 'UTF-8') ?> overflow-hidden rounded-[1.25rem] border <?= htmlspecialchars($t['wrap'], ENT_QUOTES, 'UTF-8') ?> shadow-sm" role="alert">
    <div class="flex items-start gap-4 px-4 py-4 sm:px-5">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl <?= htmlspecialchars($t['iconWrap'], ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($iconKind === 'success'): ?>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
            <?php elseif ($iconKind === 'warning'): ?>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <?php elseif ($iconKind === 'info'): ?>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
            </svg>
            <?php else: ?>
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/>
            </svg>
            <?php endif; ?>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-black uppercase tracking-[0.22em] <?= htmlspecialchars($eyebrowCls, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($eyebrowLabel, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <p class="mt-1 text-sm font-semibold <?= htmlspecialchars($headingCls, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <?php if ($description !== null && $description !== ''): ?>
            <p class="mt-1 text-sm leading-6 <?= htmlspecialchars($bodyCls, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>
