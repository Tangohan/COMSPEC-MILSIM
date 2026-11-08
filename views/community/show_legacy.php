<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var array $memberships */
/** @var array $communityConfig */
/** @var array<string, mixed>|null $communityProfile */
/** @var bool $hasMembershipInTenant */
/** @var array<string, mixed>|null $planBadge */
/** @var array<string, mixed> $tenantBranding */
/** @var list<array<string, mixed>> $recruitmentPublishedOpenings */
$slug = (string) ($tenant['slug'] ?? '');
$name = (string) ($tenant['name'] ?? '');
$communityConfig = $communityConfig ?? [];
$cp = $communityProfile ?? \App\Services\Community\TenantCommunityProfileService::getPublicViewModel($communityConfig, $slug);
$hasMembershipInTenant = $hasMembershipInTenant ?? false;
$planBadge = is_array($planBadge ?? null) ? $planBadge : null;
$tenantBranding = is_array($tenantBranding ?? null) ? $tenantBranding : [];
$openings = is_array($recruitmentPublishedOpenings ?? null) ? $recruitmentPublishedOpenings : [];

$presentationMode = ($cp['presentationMode'] ?? 'simple') === 'military' ? 'military' : 'simple';
$simpleBody = trim((string) ($cp['simpleBody'] ?? ''));
$expectations = trim((string) ($cp['expectations'] ?? ''));
$gameLabel = trim((string) ($cp['gameLabel'] ?? ''));
$mainMods = trim((string) ($cp['mainMods'] ?? ''));
$militarySections = is_array($cp['militarySections'] ?? null) ? $cp['militarySections'] : [];
$styleBadgeLabels = is_array($cp['styleBadgeLabels'] ?? null) ? $cp['styleBadgeLabels'] : [];
$welcomeText = trim((string) ($cp['welcomeText'] ?? ''));
$registrationMode = (string) ($cp['registrationModeLabel'] ?? 'MilSim complet');
$isLocked = !empty($cp['isLocked']);
$discordUrl = (string) ($cp['discordUrl'] ?? '');
$contactFormEnabled = !empty($cp['contactFormEnabled']);
$contactIntro = (string) ($cp['contactIntro'] ?? '');
$publicAudience = ($cp['publicAudience'] ?? 'unit') === 'platform' ? 'platform' : 'unit';
$communityCode = trim((string) ($tenant['community_code'] ?? ''));
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$userId = (int) (\App\Core\Session::get('user_id') ?? 0);

// Présentation : accueil, sinon texte de présentation, sinon première section.
$lead = $welcomeText !== '' ? $welcomeText : $simpleBody;
if ($lead === '' && $presentationMode === 'military') {
    foreach ($militarySections as $sec) {
        if (is_array($sec) && trim((string) ($sec['body'] ?? '')) !== '') {
            $lead = trim((string) $sec['body']);
            break;
        }
    }
}

$logoUrl = trim((string) ($tenantBranding['logo_url'] ?? ''));
$bannerUrl = trim((string) ($tenantBranding['banner_url'] ?? ''));
foreach (['logoUrl', 'bannerUrl'] as $k) {
    $v = $$k;
    if ($v !== '' && !preg_match('#^(https?:)?//#i', $v)) {
        $$k = asset_url(ltrim($v, '/'));
    }
}
$primary = trim((string) ($tenantBranding['primary_color'] ?? ''));
$accent = trim((string) ($tenantBranding['accent_color'] ?? ''));
$cpStyle = '';
if (preg_match('/^#[0-9A-Fa-f]{6}$/', $primary) === 1 && \App\Support\ColorContrast::ratio('#041016', $primary) >= \App\Support\ColorContrast::MIN_TEXT_RATIO) {
    $cpStyle .= '--cp-tenant-primary:' . $primary . ';';
}
$cpStyle .= '--cp-tenant-accent-text:' . \App\Support\ColorContrast::readableOn($accent !== '' ? $accent : '#6ee7b7', '#0b1220', '#6ee7b7') . ';';

$enlistUrl = url('c/' . rawurlencode($slug) . '/enlistment');
$letter = mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : 'A', 0, 1));
$cssHref = url('assets/css/community-public-dark.css') . '?v=' . rawurlencode((string) (@filemtime(base_path('public/assets/css/community-public-dark.css')) ?: 1));
$openingCount = count($openings);
$hasContact = $discordUrl !== '' || $contactFormEnabled || ($userId > 0 && $hasMembershipInTenant);
?>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="<?= htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<section class="cp" style="<?= htmlspecialchars($cpStyle, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="cp-title">
    <?php if ($bannerUrl !== ''): ?>
    <div class="cp__bg" aria-hidden="true"><img src="<?= htmlspecialchars($bannerUrl, ENT_QUOTES, 'UTF-8') ?>" alt=""></div>
    <?php endif; ?>
    <div class="cp__grid">
        <div class="cp__main">
            <?php if ($flashSuccess): ?>
                <p class="cp__flash cp__flash--ok" role="status"><?= htmlspecialchars((string) $flashSuccess) ?></p>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <p class="cp__flash cp__flash--err" role="alert"><?= htmlspecialchars((string) $flashError) ?></p>
            <?php endif; ?>

            <div class="cp__brand">
                <?php if ($logoUrl !== ''): ?>
                    <img class="cp__logo" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Emblème de la communauté" width="72" height="72">
                <?php else: ?>
                    <div class="cp__mark" aria-hidden="true"><?= htmlspecialchars($letter) ?></div>
                <?php endif; ?>
                <div class="cp__chips">
                    <?php if ($planBadge !== null): ?>
                        <span class="cp__chip cp__chip--plan">Formule <?= htmlspecialchars((string) $planBadge['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php foreach ($styleBadgeLabels as $bl): ?>
                        <?php if (is_string($bl) && $bl !== ''): ?>
                            <span class="cp__chip"><?= htmlspecialchars($bl) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <h1 class="cp__title" id="cp-title"><?= name_blur_html($name) ?></h1>
            <?php if ($lead !== ''): ?>
                <p class="cp__lead"><?= htmlspecialchars($lead) ?></p>
            <?php endif; ?>

            <div class="cp__chips" style="margin-top:1.1rem">
                <?php if ($isLocked): ?>
                    <span class="cp__chip cp__chip--closed"><?= $publicAudience === 'platform' ? 'Candidatures fermées' : 'Recrutement fermé' ?></span>
                <?php else: ?>
                    <span class="cp__chip cp__chip--open">Recrutement ouvert</span>
                    <?php if ($openingCount > 0): ?>
                        <span class="cp__chip"><?= $openingCount ?> <?= $openingCount > 1 ? 'postes à pourvoir' : 'poste à pourvoir' ?></span>
                    <?php endif; ?>
                <?php endif; ?>
                <span class="cp__chip"><?= htmlspecialchars($registrationMode) ?></span>
            </div>

            <div class="cp__cta">
                <?php if (!$isLocked): ?>
                    <a href="<?= htmlspecialchars($enlistUrl, ENT_QUOTES, 'UTF-8') ?>" class="cp__btn cp__btn--primary comspec-analytics-cta" data-comspec-zone="fiche_classique" data-comspec-cta="<?= $publicAudience === 'platform' ? 'candidater' : 'rejoindre' ?>"><?= $publicAudience === 'platform' ? 'Candidater' : 'Rejoindre' ?></a>
                    <a href="<?= htmlspecialchars($enlistUrl, ENT_QUOTES, 'UTF-8') ?>#attentes" class="cp__btn cp__btn--ghost">Ce que nous attendons</a>
                <?php else: ?>
                    <span class="cp__btn cp__btn--off">Recrutement fermé</span>
                <?php endif; ?>
                <?php if (!$userId): ?>
                    <a href="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>" class="cp__btn cp__btn--ghost">Connexion</a>
                <?php endif; ?>
            </div>

            <p class="cp__foot">
                <?php if ($communityCode !== '' && !$isLocked): ?>
                    Vous avez un code ? <a href="<?= htmlspecialchars(url('join') . '?code=' . rawurlencode($communityCode), ENT_QUOTES, 'UTF-8') ?>">Rejoindre avec un code</a>
                <?php endif; ?>
                <?php if ($communityCode !== '' && !$isLocked): ?> · <?php endif; ?>
                <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>">Accueil</a>
            </p>
        </div>

        <aside class="cp__side" aria-label="À propos de la communauté">
            <?php if ($gameLabel !== '' || $mainMods !== ''): ?>
            <div class="cp__card">
                <p class="cp__card-title">Jeu et mods</p>
                <?php if ($gameLabel !== ''): ?>
                    <p class="cp__card-text"><strong><?= htmlspecialchars($gameLabel) ?></strong></p>
                <?php endif; ?>
                <?php if ($mainMods !== ''): ?>
                    <p class="cp__card-text"><?= htmlspecialchars($mainMods) ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($expectations !== ''): ?>
            <div class="cp__card">
                <p class="cp__card-title">Ce que nous attendons</p>
                <p class="cp__card-text"><?= htmlspecialchars($expectations) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($hasContact): ?>
            <div class="cp__card" id="community-contact">
                <p class="cp__card-title">Contact</p>
                <?php if ($contactIntro !== ''): ?>
                    <p class="cp__card-text"><?= htmlspecialchars($contactIntro) ?></p>
                <?php endif; ?>
                <ul class="cp__list">
                    <?php if ($discordUrl !== ''): ?>
                        <li><a href="<?= htmlspecialchars($discordUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Discord de la communauté</a></li>
                    <?php endif; ?>
                    <?php if ($userId > 0 && $hasMembershipInTenant): ?>
                        <li><a href="<?= htmlspecialchars(url('messages'), ENT_QUOTES, 'UTF-8') ?>">Messagerie interne</a></li>
                    <?php endif; ?>
                </ul>
                <?php if ($contactFormEnabled): ?>
                <details class="cp__details">
                    <summary class="cp__foot" style="cursor:pointer">Écrire à l’équipe</summary>
                    <form method="post" action="<?= htmlspecialchars(url('c/' . rawurlencode($slug) . '/contact'), ENT_QUOTES, 'UTF-8') ?>" style="margin-top:0.75rem;display:grid;gap:0.6rem">
                        <?= \App\Core\Csrf::field() ?>
                        <label class="cp__label" for="cp-from-email">Votre e-mail (pour la réponse)</label>
                        <input class="cp__input" style="text-transform:none;letter-spacing:0;text-align:left;font-size:1rem" id="cp-from-email" type="email" name="from_email" required autocomplete="email">
                        <label class="cp__label" for="cp-body">Message</label>
                        <textarea class="cp__input" style="text-transform:none;letter-spacing:0;text-align:left;font-size:1rem" id="cp-body" name="body" rows="3" required></textarea>
                        <button type="submit" class="cp__btn cp__btn--primary">Envoyer</button>
                    </form>
                </details>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </aside>
    </div>
</section>
