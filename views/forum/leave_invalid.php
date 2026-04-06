<?php
$baseUrl = url('');
$forumConfig = $forumConfig ?? [];
$subtitle = trim((string) ($forumConfig['subtitle'] ?? 'Athena'));
$subtitleSafe = htmlspecialchars($subtitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>
<div class="flex min-h-screen flex-col bg-[#0a0a0c]">
    <div class="border-b border-amber-500/15 bg-amber-950/25 px-5 py-5 md:px-7">
        <div class="mx-auto max-w-lg">
            <p class="text-[10px] font-black uppercase tracking-[0.25em] text-amber-400/90">Lien de sortie</p>
            <h1 class="mt-1 text-lg font-black uppercase italic tracking-tight text-white">Lien invalide ou expiré</h1>
            <p class="mt-2 text-xs leading-relaxed text-amber-200/70">Repars depuis une page récente du portail (forum, message…) pour générer un nouveau lien sécurisé.</p>
        </div>
    </div>
    <div class="border-b border-white/[0.06] bg-slate-900/40 px-5 py-2.5 md:px-7">
        <div class="mx-auto max-w-lg rounded-lg border border-emerald-500/20 bg-emerald-950/25 px-3 py-2 text-[11px] leading-snug text-emerald-100/85">
            <span class="font-black text-emerald-400"><?= $subtitleSafe ?></span>
            <span class="text-emerald-100/80"> — les liens externes protégés expirent volontairement après un court délai.</span>
        </div>
    </div>
    <div class="flex flex-1 flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md rounded-2xl border border-white/10 bg-slate-900/60 p-8 text-center shadow-xl shadow-black/40 backdrop-blur-sm">
            <p class="text-sm leading-relaxed text-slate-400">Ce lien ne peut plus être utilisé. Ouvre plutôt le message ou le sujet d’origine et reclique sur le lien externe.</p>
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/forum" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-500">
                Retour au forum
            </a>
            <a href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>/dashboard" class="mt-3 inline-flex w-full items-center justify-center rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-white/10">
                Tableau de bord
            </a>
        </div>
    </div>
</div>
