<?php
declare(strict_types=1);
/**
 * En-tête commun pages publiques inscription · rejoindre (aligné login + home).
 * Optionnel : $active = 'home'|'login'|'register'|'join'
 */
$active = $active ?? '';
$nav = function (string $key, string $href, string $label) use ($active): string {
    $is = $active === $key;
    $cls = $is
        ? 'text-emerald-700'
        : 'text-slate-500 hover:text-slate-900';

    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="text-[10px] font-black uppercase tracking-[0.2em] transition-colors ' . $cls . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
};
$homeUrl = url('');
?>
<header class="relative z-20 border-b border-slate-200/90 bg-white/90 backdrop-blur-md">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-4">
        <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="group flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-900 text-[11px] font-black text-white shadow-sm group-hover:bg-emerald-600 transition-colors">A</span>
            <span class="text-lg font-black italic tracking-tight text-slate-900 uppercase leading-none">Athena</span>
        </a>
        <nav class="flex flex-wrap items-center gap-4 sm:gap-8">
            <?= $nav('home', $homeUrl, 'Accueil') ?>
            <span class="text-slate-300 hidden sm:inline" aria-hidden="true">|</span>
            <?= $nav('login', url('login'), 'Connexion') ?>
            <?= $nav('register', url('register'), 'Inscription') ?>
            <?= $nav('join', url('join'), 'Code') ?>
        </nav>
    </div>
</header>
