<?php
/**
 * État vide « pas d’organisation » — utilisé partout où une page nécessite une communauté réelle
 * mais que le compte est sur le tenant système (slug `default`).
 *
 * @var string $noOrgTitle Optionnel : titre spécifique à la page.
 * @var string $noOrgMessage Optionnel : message spécifique à la page.
 */
$noOrgTitle = trim((string) ($noOrgTitle ?? 'Aucune organisation'));
$noOrgMessage = trim((string) ($noOrgMessage ?? 'Cette section n’est disponible qu’une fois rattaché à une communauté. Rejoignez une communauté avec un code d’invitation, ou créez la vôtre.'));
?>
<div class="mx-auto max-w-xl px-4 py-16 text-center sm:py-24">
    <svg class="mx-auto h-24 w-24 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 21h18" />
        <path d="M5 21V7l7-4 7 4v14" />
        <path d="M9 9h.01M15 9h.01M9 13h.01M15 13h.01" />
        <path d="M10 21v-5a2 2 0 0 1 2-2 2 2 0 0 1 2 2v5" />
    </svg>
    <h2 class="mt-6 text-lg font-black tracking-tight text-slate-900"><?= htmlspecialchars($noOrgTitle, ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars($noOrgMessage, ENT_QUOTES, 'UTF-8') ?></p>
    <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
        <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-black uppercase tracking-wide text-white shadow-sm transition hover:bg-slate-800">Rejoindre ou créer une communauté</a>
    </div>
</div>
