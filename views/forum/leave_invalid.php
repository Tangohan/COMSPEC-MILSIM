<?php
$baseUrl = url('');
$forumConfig = $forumConfig ?? [];
$subtitle = trim((string) ($forumConfig['subtitle'] ?? 'Athena'));
$subtitleSafe = htmlspecialchars($subtitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>
<div class="flex min-h-screen flex-col bg-white">
    <div class="border-b border-amber-200 bg-amber-50 px-5 py-5 md:px-7">
        <div class="mx-auto max-w-lg">
            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-amber-800">Lien de sortie</p>
            <h1 class="mt-1 text-lg font-black uppercase italic tracking-tight text-slate-900">Lien invalide ou expiré</h1>
            <p class="mt-2 text-xs leading-relaxed text-amber-900/80">Repars depuis une page récente du portail (forum, message…) pour générer un nouveau lien sécurisé.</p>
        </div>
    </div>
    <div class="border-b border-slate-200 bg-slate-50 px-5 py-2.5 md:px-7">
        <div class="mx-auto max-w-lg rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] leading-snug text-emerald-900">
            <span class="font-black text-emerald-800"><?= $subtitleSafe ?></span>
            <span class="text-emerald-900/85"> — les liens externes protégés expirent volontairement après un court délai.</span>
        </div>
    </div>
    <div class="flex flex-1 flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-lg shadow-slate-200/60">
            <p class="text-sm leading-relaxed text-slate-600">Ce lien ne peut plus être utilisé. Ouvre plutôt le message ou le sujet d’origine et reclique sur le lien externe.</p>
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/forum" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">
                Retour au forum
            </a>
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/dashboard" class="mt-3 inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-100">
                Tableau de bord
            </a>
        </div>
    </div>
</div>
