<?php
/**
 * Visiteur sans compte : la candidature se fait avec un compte (« un compte, une identité »). Deux boutons mènent à la
 * connexion ou à la création du compte, puis ramènent sur le formulaire de cette communauté.
 *
 * @var array<string, mixed> $enlistmentContext
 * @var string $tenantName
 * @var string $accountTheme 'dark' (page d'enrôlement) ou 'light' (pages du portail)
 */
declare(strict_types=1);

$accountTheme = ($accountTheme ?? 'dark') === 'light' ? 'light' : 'dark';
$loginStepUrl = (string) ($enlistmentContext['loginStepUrl'] ?? url('login'));
$registerStepUrl = (string) ($enlistmentContext['registerStepUrl'] ?? url('register'));
$gateTenantName = (string) ($tenantName ?? '');
?>
<?php if ($accountTheme === 'dark'): ?>
<div class="ce-gatecard" role="region" aria-labelledby="ce-gatecard-title">
    <p class="ce-gatecard__kicker">Un compte, une identité</p>
    <h3 class="ce-gatecard__title" id="ce-gatecard-title">Connectez-vous pour candidater</h3>
    <p class="ce-gatecard__text">Votre candidature chez <strong><?= htmlspecialchars($gateTenantName, ENT_QUOTES, 'UTF-8') ?></strong> est rattachée à votre compte : votre nom, votre portrait et votre e-mail sont repris de votre profil. Après la connexion ou la création du compte, vous revenez ici, sur le formulaire.</p>
    <div class="ce-gatecard__actions">
        <a class="ce-btn ce-btn--primary ce-btn--lg" href="<?= htmlspecialchars($loginStepUrl, ENT_QUOTES, 'UTF-8') ?>">Se connecter</a>
        <a class="ce-btn ce-btn--ghost ce-btn--lg" href="<?= htmlspecialchars($registerStepUrl, ENT_QUOTES, 'UTF-8') ?>">Créer un compte</a>
    </div>
</div>
<?php else: ?>
<div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-slate-900" role="region" aria-labelledby="enl-gate-title">
    <p class="text-[10px] font-black uppercase tracking-widest text-emerald-800">Un compte, une identité</p>
    <h2 class="mt-1 text-lg font-black" id="enl-gate-title">Connectez-vous pour candidater</h2>
    <p class="mt-2 text-sm leading-relaxed text-slate-700">Votre candidature chez <strong><?= htmlspecialchars($gateTenantName, ENT_QUOTES, 'UTF-8') ?></strong> est rattachée à votre compte : votre nom et votre e-mail sont repris de votre profil. Après la connexion ou la création du compte, vous revenez sur ce formulaire.</p>
    <div class="mt-4 flex flex-wrap gap-3">
        <a class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-3 text-xs font-black uppercase tracking-widest text-white hover:bg-emerald-700" href="<?= htmlspecialchars($loginStepUrl, ENT_QUOTES, 'UTF-8') ?>">Se connecter</a>
        <a class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-xs font-black uppercase tracking-widest text-slate-900 hover:bg-slate-50" href="<?= htmlspecialchars($registerStepUrl, ENT_QUOTES, 'UTF-8') ?>">Créer un compte</a>
    </div>
</div>
<?php endif; ?>
