<?php
/** @var array $tenant */
/** @var array $memberships */
/** @var array $communityConfig */
/** @var array<string, mixed>|null $communityProfile */
/** @var bool $hasMembershipInTenant */
/** @var bool $showForumCta */
$slug = $tenant['slug'] ?? '';
$name = $tenant['name'] ?? '';
$communityConfig = $communityConfig ?? [];
$cp = $communityProfile ?? \App\Services\Community\TenantCommunityProfileService::getPublicViewModel($communityConfig, (string) ($tenant['slug'] ?? ''));
$hasMembershipInTenant = $hasMembershipInTenant ?? false;
$showForumCta = $showForumCta ?? true;

$presentationMode = ($cp['presentationMode'] ?? 'simple') === 'military' ? 'military' : 'simple';
$simpleBody = (string) ($cp['simpleBody'] ?? '');
$expectations = (string) ($cp['expectations'] ?? '');
$gameLabel = (string) ($cp['gameLabel'] ?? '');
$mainMods = (string) ($cp['mainMods'] ?? '');
$modpackSize = $cp['modpackSize'] ?? null;
$militarySections = is_array($cp['militarySections'] ?? null) ? $cp['militarySections'] : [];
$styleBadgeLabels = is_array($cp['styleBadgeLabels'] ?? null) ? $cp['styleBadgeLabels'] : [];
$welcomeText = (string) ($cp['welcomeText'] ?? '');
$registrationMode = (string) ($cp['registrationModeLabel'] ?? 'MilSim complet');
$isLocked = !empty($cp['isLocked']);
$discordUrl = (string) ($cp['discordUrl'] ?? '');
$contactEmail = (string) ($cp['contactEmail'] ?? '');
$contactIntro = (string) ($cp['contactIntro'] ?? '');
$contactFormEnabled = !empty($cp['contactFormEnabled']);
$publicAudience = ($cp['publicAudience'] ?? 'unit') === 'platform' ? 'platform' : 'unit';

$communityCode = trim((string) ($tenant['community_code'] ?? ''));
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$userId = (int) (\App\Core\Session::get('user_id') ?? 0);
?>
<div class="relative overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/90 shadow-[0_20px_60px_-24px_rgba(15,23,42,0.2)] max-w-3xl mx-auto px-4 py-10 md:py-14 md:px-8">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-32 bg-[radial-gradient(ellipse_at_top,rgba(16,185,129,0.12),transparent_55%)]" aria-hidden="true"></div>
    <div class="relative">
    <?php if ($flashSuccess): ?>
        <p class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <p class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= htmlspecialchars($flashError) ?></p>
    <?php endif; ?>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <?php foreach ($styleBadgeLabels as $bl): ?>
            <?php if (is_string($bl) && $bl !== ''): ?>
                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-emerald-900"><?= htmlspecialchars($bl) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <h1 class="text-3xl font-black uppercase tracking-tight text-slate-900 mb-2"><?= htmlspecialchars($name) ?></h1>
    <p class="text-sm text-slate-500 mb-6"><?= $publicAudience === 'platform' ? 'Portail public — fiche de présentation' : 'Page publique — fiche de présentation de la communauté' ?></p>

    <?php if ($gameLabel !== '' || $mainMods !== '' || $modpackSize !== null): ?>
        <div class="rounded-2xl border border-slate-200 bg-white/90 backdrop-blur-sm p-5 mb-8 space-y-2 text-sm shadow-sm">
            <?php if ($gameLabel !== ''): ?>
                <p><span class="font-bold text-slate-700">Jeu :</span> <?= htmlspecialchars($gameLabel) ?></p>
            <?php endif; ?>
            <?php if ($mainMods !== ''): ?>
                <p><span class="font-bold text-slate-700">Mods principaux :</span> <?= nl2br(htmlspecialchars($mainMods)) ?></p>
            <?php endif; ?>
            <?php if ($modpackSize !== null && (string) $modpackSize !== ''): ?>
                <p><span class="font-bold text-slate-700">Taille modpack (indicatif) :</span> <?= htmlspecialchars((string) $modpackSize) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($welcomeText !== ''): ?>
        <p class="text-sm text-slate-700 mb-6 leading-relaxed border-l-4 border-emerald-500/40 pl-4"><?= nl2br(htmlspecialchars($welcomeText)) ?></p>
    <?php endif; ?>

    <?php if ($presentationMode === 'simple' && $simpleBody !== ''): ?>
        <div class="prose prose-slate max-w-none mb-8">
            <h2 class="text-lg font-black text-slate-900 mb-2">Présentation</h2>
            <div class="text-sm text-slate-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($simpleBody) ?></div>
        </div>
    <?php elseif ($presentationMode === 'military' && $militarySections !== []): ?>
        <div class="space-y-6 mb-8">
            <?php foreach ($militarySections as $sec): ?>
                <?php if (!is_array($sec)) { continue; } ?>
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-700 mb-1"><?= htmlspecialchars((string) ($sec['label'] ?? '')) ?></p>
                    <?php if (trim((string) ($sec['title'] ?? '')) !== ''): ?>
                        <h3 class="text-base font-black text-slate-900 mb-2"><?= htmlspecialchars((string) $sec['title']) ?></h3>
                    <?php endif; ?>
                    <div class="text-sm text-slate-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars((string) ($sec['body'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($expectations !== ''): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 mb-8">
            <h2 class="text-sm font-black uppercase tracking-widest text-amber-900 mb-2">Attentes</h2>
            <p class="text-sm text-amber-950/90 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($expectations) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($publicAudience === 'platform'): ?>
    <p class="text-xs text-slate-500 mb-8">Portail plateforme · accès public : <strong><?= $isLocked ? 'Restreint' : 'Actif' ?></strong></p>
    <?php else: ?>
    <p class="text-xs text-slate-500 mb-8">Recrutement : <strong><?= htmlspecialchars($registrationMode) ?></strong>
        · Statut : <strong><?= $isLocked ? 'Verrouillé' : 'Ouvert' ?></strong></p>
    <?php endif; ?>

    <div class="flex flex-wrap gap-3 mb-10">
        <?php if ($showForumCta): ?>
            <a href="<?= htmlspecialchars(url('c/' . $slug . '/forum')) ?>" class="inline-flex items-center px-4 py-2.5 bg-slate-900 text-white text-xs font-bold uppercase tracking-wider rounded-xl hover:bg-emerald-700">Découvrir le forum</a>
        <?php else: ?>
            <span class="inline-flex items-center px-4 py-2.5 bg-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider rounded-xl cursor-not-allowed" title="Forum réservé aux membres">Forum (réservé membres)</span>
        <?php endif; ?>

        <?php if (!$isLocked): ?>
            <a href="<?= htmlspecialchars(url('c/' . $slug . '/enlistment')) ?>" class="comspec-analytics-cta inline-flex items-center px-4 py-2.5 <?= $publicAudience === 'platform' ? 'border border-dashed border-slate-300 text-slate-600' : 'border border-slate-300' ?> text-xs font-bold uppercase rounded-xl hover:bg-slate-50" data-comspec-zone="fiche_classique"><?= $publicAudience === 'platform' ? 'Candidater' : 'Rejoindre (candidature)' ?></a>
        <?php else: ?>
            <span class="inline-flex items-center px-4 py-2.5 border border-slate-200 text-slate-400 text-xs font-bold uppercase rounded-xl"><?= $publicAudience === 'platform' ? 'Candidatures fermées' : 'Inscription fermée' ?></span>
        <?php endif; ?>
        <?php if ($discordUrl !== '' || $contactEmail !== '' || $contactFormEnabled): ?>
            <a href="#community-contact" class="inline-flex items-center px-4 py-2.5 border border-slate-300 text-xs font-bold uppercase rounded-xl hover:bg-slate-50">Contacter l'équipe</a>
        <?php endif; ?>

        <?php if (!\App\Core\Session::get('user_id')): ?>
            <a href="<?= url('login') ?>" class="inline-flex items-center px-4 py-2.5 border border-slate-300 text-xs font-bold uppercase rounded-xl hover:bg-slate-50">Connexion</a>
        <?php endif; ?>
        <a href="<?= url('') ?>" class="inline-flex items-center px-4 py-2.5 text-xs font-bold uppercase text-slate-500">Accueil</a>
    </div>

    <?php if ($communityCode !== '' && !$isLocked): ?>
        <p class="text-sm text-slate-600 mb-2">Code pour rejoindre : <code class="bg-emerald-50 text-emerald-900 px-2 py-0.5 rounded font-mono font-bold"><?= htmlspecialchars($communityCode) ?></code>
            · <a href="<?= url('join') ?>?code=<?= rawurlencode($communityCode) ?>" class="text-emerald-700 underline text-xs font-semibold">Page rejoindre</a></p>
    <?php elseif ($communityCode !== '' && $isLocked): ?>
        <p class="text-sm text-slate-500 mb-2">Le recrutement par code est suspendu (communauté verrouillée).</p>
    <?php else: ?>
        <p class="text-sm text-slate-500 mb-2">Code communauté : <span class="text-slate-400">non défini</span></p>
    <?php endif; ?>

    <?php if ($discordUrl !== '' || $contactEmail !== '' || ($userId && $hasMembershipInTenant)): ?>
        <div id="community-contact" class="mt-10 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <h2 class="text-sm font-black uppercase tracking-widest text-slate-900 mb-4">Contact</h2>
            <?php if ($contactIntro !== ''): ?>
                <p class="text-sm text-slate-600 mb-4"><?= htmlspecialchars($contactIntro) ?></p>
            <?php endif; ?>
            <div class="flex flex-wrap gap-3 mb-6">
                <?php if ($discordUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($discordUrl) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-500">Discord</a>
                <?php endif; ?>
                <?php if ($contactEmail !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-800 hover:bg-slate-50">E-mail</a>
                <?php endif; ?>
                <?php if ($userId && $hasMembershipInTenant): ?>
                    <a href="<?= url('messages') ?>" class="inline-flex items-center gap-2 rounded-xl border border-emerald-500 bg-emerald-50 px-4 py-2.5 text-xs font-bold text-emerald-900 hover:bg-emerald-100">Messagerie interne</a>
                <?php endif; ?>
            </div>
            <?php if ($contactFormEnabled): ?>
                <form method="post" action="<?= htmlspecialchars(url('c/' . rawurlencode($slug) . '/contact')) ?>" class="space-y-3 border-t border-slate-100 pt-6">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="text-xs font-bold text-slate-700">Écrire à l’équipe (réponse par e-mail)</p>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Votre e-mail</label>
                        <input type="email" name="from_email" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="vous@exemple.fr">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Message</label>
                        <textarea name="body" rows="4" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Votre message…"></textarea>
                    </div>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white hover:bg-emerald-700">Envoyer</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($memberships)): ?>
        <p class="mt-8 text-xs text-slate-600">Vos adhésions avec le même e-mail : utilisez le tableau de bord pour changer de communauté active.</p>
    <?php endif; ?>
    </div>
</div>
