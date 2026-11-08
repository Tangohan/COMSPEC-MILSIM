<?php
declare(strict_types=1);

$base = url('');
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
$ref = 'JTN-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
$tenant = $tenant ?? [];
$communityConfig = $communityConfig ?? [];
$formAction = $formAction ?? url('enlistment');
$tenantName = trim((string) ($tenant['name'] ?? 'Athena'));
$requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
$milsimPack = $milsimPack ?? \App\Services\Community\EnlistmentMilsimPackService::defaultPack();
$p = $milsimPack;
$motivationSection = \App\Services\Community\EnlistmentMilsimPackService::normalizeMotivationSection(
    is_array($p['motivation'] ?? null) ? $p['motivation'] : null,
    is_array($p['fields'] ?? null) ? $p['fields'] : null
);
$motivationSectionTitle = trim((string) ($motivationSection['title'] ?? '')) !== ''
    ? (string) $motivationSection['title']
    : (string) ($p['section_3'] ?? 'Motivation');
$fld = static function (string $k) use ($p): array {
    if (is_array($p['fields'][$k] ?? null)) {
        return $p['fields'][$k];
    }
    return ['label' => $k, 'placeholder' => '', 'widget' => 'text', 'options' => []];
};
// Créneaux à cocher : ceux de la communauté, à défaut le catalogue courant.
$availabilitySlots = \App\Services\Community\EnlistmentMilsimPackService::slotsOrDefault($p['availability_slots'] ?? []);
$enlistSlug = trim((string) ($tenant['slug'] ?? 'default'));

// Un compte est nécessaire pour candidater : l'identité vient du compte, jamais d'un champ du formulaire.
$enlistmentContext = $enlistmentContext ?? [];
$isAuthenticated = !empty($enlistmentContext['isAuthenticated']);
$loginStepUrl = (string) ($enlistmentContext['loginStepUrl'] ?? url('login'));
$prefill = array_merge([
    'timezone' => '',
    'weekly_availability' => '',
], is_array($enlistmentContext['prefill'] ?? null) ? $enlistmentContext['prefill'] : []);
$prefillAvailabilitySelected = [];
if (trim((string) ($prefill['weekly_availability'] ?? '')) !== '') {
    $prefillAvailabilitySelected = \App\Services\Community\EnlistmentMilsimPackService::filterCandidateSlotSelection(
        $availabilitySlots,
        preg_split('/\s*,\s*/', (string) $prefill['weekly_availability']) ?: []
    );
}
$timezoneOptions = \App\Support\Profile\EnlistmentTimezoneCatalog::optionsForSelect();
$tenantSlugForForm = trim((string) ($tenant['slug'] ?? ''));
$selectedRecruitmentOpening = is_array($selectedRecruitmentOpening ?? null) ? $selectedRecruitmentOpening : null;
$analyticsBeacon = $analyticsBeacon ?? null;
$enlistmentMemberOpeningInsight = is_array($enlistmentMemberOpeningInsight ?? null) ? $enlistmentMemberOpeningInsight : null;
$compactAccountOpening = $isAuthenticated && $selectedRecruitmentOpening !== null;
$publishedOpenings = is_array($publishedOpenings ?? null) ? $publishedOpenings : [];
$tenantBranding = is_array($tenantBranding ?? null) ? $tenantBranding : [];
$existingCandidature = is_array($existingCandidature ?? null) ? $existingCandidature : null;
$blocksNewCandidature = $existingCandidature !== null && !empty($existingCandidature['blocks_form']);
$customQuestions = \App\Services\Community\EnlistmentMilsimPackService::normalizeCustomQuestions($p['custom_questions'] ?? []);
$customBySection = ['identity' => [], 'gear' => [], 'motivation' => [], 'commitment' => []];
foreach ($customQuestions as $cqRow) {
    $sec = (string) ($cqRow['section'] ?? 'commitment');
    if (!isset($customBySection[$sec])) {
        $sec = 'commitment';
    }
    $customBySection[$sec][] = $cqRow;
}
$disclaimerRecruitment = is_array($p['disclaimer_recruitment_lines'] ?? null) && $p['disclaimer_recruitment_lines'] !== []
    ? array_values(array_filter($p['disclaimer_recruitment_lines'], static fn ($l) => is_string($l) && trim($l) !== ''))
    : \App\Services\Community\EnlistmentMilsimPackService::defaultRecruitmentDisclaimerLines();

$brandLogo = trim((string) ($tenantBranding['logo_url'] ?? ''));
$brandBanner = trim((string) ($tenantBranding['banner_url'] ?? ''));
$brandPrimary = trim((string) ($tenantBranding['primary_color'] ?? ''));
$brandAccent = trim((string) ($tenantBranding['accent_color'] ?? ''));
if ($brandLogo !== '' && !preg_match('#^(https?:)?//#i', $brandLogo) && !str_starts_with($brandLogo, '/')) {
    $brandLogo = asset_url(ltrim($brandLogo, '/'));
} elseif ($brandLogo !== '' && str_starts_with($brandLogo, '/')) {
    $brandLogo = asset_url(ltrim($brandLogo, '/'));
}
if ($brandBanner !== '' && !preg_match('#^(https?:)?//#i', $brandBanner) && !str_starts_with($brandBanner, '/')) {
    $brandBanner = asset_url(ltrim($brandBanner, '/'));
} elseif ($brandBanner !== '' && str_starts_with($brandBanner, '/')) {
    $brandBanner = asset_url(ltrim($brandBanner, '/'));
}

// Couleurs de la communauté : jamais au prix de la lisibilité (texte clair sur fond sombre, texte foncé sur les
// boutons pleins ; seuil de contraste 4,5:1).
$deckBg = '#0b1220';
$ceStyle = '';
if (preg_match('/^#[0-9A-Fa-f]{6}$/', $brandPrimary) === 1
    && \App\Support\ColorContrast::ratio('#041016', $brandPrimary) >= \App\Support\ColorContrast::MIN_TEXT_RATIO) {
    $ceStyle .= '--ce-tenant-primary:' . $brandPrimary . ';';
}
if (preg_match('/^#[0-9A-Fa-f]{6}$/', $brandAccent) === 1) {
    $ceStyle .= '--ce-tenant-accent:' . $brandAccent . ';';
}
$ceStyle .= '--ce-tenant-accent-text:' . \App\Support\ColorContrast::readableOn($brandAccent !== '' ? $brandAccent : '#6ee7b7', $deckBg, '#6ee7b7') . ';';

$showcaseUrl = $tenantSlugForForm !== '' ? url('c/' . rawurlencode($tenantSlugForForm)) : $base . '/';
$formAnchor = '#candidature';
// Bouton « Commencer » : le formulaire pour un compte connecté, la connexion (puis le formulaire) sinon.
$startHref = $isAuthenticated ? $formAnchor : $loginStepUrl;
$logoLetter = mb_strtoupper(mb_substr(trim((string) ($p['logo_letter'] ?? 'A')) ?: mb_substr($tenantName, 0, 1) ?: 'A', 0, 1));
$heroLead = trim((string) ($p['preamble_lead'] ?? ''));
$roeItems = is_array($p['roe_items'] ?? null) ? array_values(array_filter($p['roe_items'], static fn ($r) => is_string($r) && trim($r) !== '')) : [];
$cssPath = base_path('public/assets/css/community-enlistment.css');
$jsPath = base_path('public/assets/js/community-enlistment.js');
$cssVer = is_file($cssPath) ? (string) filemtime($cssPath) : '1';
$jsVer = is_file($jsPath) ? (string) filemtime($jsPath) : '1';
$cssHref = url('assets/css/community-enlistment.css') . '?v=' . rawurlencode($cssVer);
$jsHref = url('assets/js/community-enlistment.js') . '?v=' . rawurlencode($jsVer);
$candidatePrefix = trim((string) ($p['candidate_prefix'] ?? 'Rejoindre'));
$submittedAtLabel = '';
if ($existingCandidature !== null && trim((string) ($existingCandidature['submitted_at'] ?? '')) !== '') {
    try {
        $submittedAtLabel = (new \DateTimeImmutable((string) $existingCandidature['submitted_at']))->format('d/m/Y à H:i');
    } catch (\Throwable) {
        $submittedAtLabel = '';
    }
}
$dossierRefLabel = '';
if ($blocksNewCandidature && (int) ($existingCandidature['enlistment_id'] ?? 0) > 0) {
    $dossierRefLabel = 'JTN-' . str_pad((string) (int) $existingCandidature['enlistment_id'], 6, '0', STR_PAD_LEFT);
}
// Étapes de la candidature (écrans « Parcours » et « Avant de candidater »).
$journeySteps = [
    ['Connectez-vous', 'Votre candidature est rattachée à votre compte : nom, portrait et e-mail sont repris de votre profil.'],
    ['Remplissez le dossier', 'Disponibilités, matériel et motivation. Répondez avec vos mots : c’est ce que l’équipe lit en premier.'],
    ['Suivi et décision', 'Vous suivez votre dossier en ligne ; l’équipe vous recontacte si votre profil correspond.'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrôlement — <?= htmlspecialchars($tenantName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= htmlspecialchars($cssHref) ?>" rel="stylesheet">
</head>
<body class="ce ce--deck<?= $blocksNewCandidature ? ' ce--status-only' : '' ?>"<?= $ceStyle !== '' ? ' style="' . htmlspecialchars($ceStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

    <div id="preamble" class="ce-gate" data-skip-if-stored="1">
        <div class="ce-gate__card">
            <div class="ce-gate__brand">
                <?php if ($brandLogo !== ''): ?>
                    <img class="ce-topbar__logo" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" width="52" height="52"
                         data-fallback-letter="<?= htmlspecialchars($logoLetter, ENT_QUOTES, 'UTF-8') ?>"
                         onerror="var m=document.createElement('div');m.className='ce-gate__mark';m.setAttribute('aria-hidden','true');m.textContent=this.getAttribute('data-fallback-letter')||'A';this.replaceWith(m);">
                <?php else: ?>
                    <div class="ce-gate__mark" aria-hidden="true"><?= htmlspecialchars($logoLetter) ?></div>
                <?php endif; ?>
                <div>
                    <p class="ce-gate__title"><?= htmlspecialchars((string) $p['portal_title']) ?></p>
                    <p class="ce-gate__sub"><?= htmlspecialchars($tenantName) ?></p>
                </div>
            </div>
            <h1><?= htmlspecialchars((string) $p['preamble_title']) ?></h1>
            <p class="ce-gate__lead"><?= htmlspecialchars((string) $p['preamble_lead']) ?></p>
            <?php
            $statusLines = is_array($p['preamble_status_lines'] ?? null) ? $p['preamble_status_lines'] : [];
            $statusLines = array_values(array_filter($statusLines, static fn ($l) => is_string($l) && trim($l) !== ''));
            ?>
            <?php if ($statusLines !== []): ?>
            <ul class="ce-gate__hints">
                <?php foreach ($statusLines as $line): ?>
                <li><?= htmlspecialchars((string) $line) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <button type="button" onclick="startApp()" class="ce-gate__cta"><?= htmlspecialchars((string) $p['preamble_cta']) ?></button>
            <p class="ce-gate__foot"><?= htmlspecialchars((string) $p['preamble_footer']) ?></p>
        </div>
    </div>

    <header class="ce-topbar">
        <div class="ce-topbar__brand">
            <?php if ($brandLogo !== ''): ?>
                <img class="ce-topbar__logo" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php endif; ?>
            <span class="ce-topbar__name"><?= htmlspecialchars((string) $p['nav_brand']) ?></span>
        </div>
        <nav class="ce-topbar__links" aria-label="Navigation">
            <a href="<?= htmlspecialchars($showcaseUrl) ?>">Page de la communauté</a>
            <?php if ($blocksNewCandidature && !empty($existingCandidature['portal_url'])): ?>
            <a href="<?= htmlspecialchars((string) $existingCandidature['portal_url'], ENT_QUOTES, 'UTF-8') ?>">Suivre ma candidature</a>
            <?php else: ?>
            <a href="<?= htmlspecialchars($startHref, ENT_QUOTES, 'UTF-8') ?>"><?= $blocksNewCandidature ? 'Mon dossier' : 'Candidater' ?></a>
            <?php endif; ?>
            <span id="clock" class="ce-topbar__clock" aria-hidden="true">--:--:--</span>
        </nav>
    </header>

    <div class="ce-deck" id="ce-deck" data-ce-index="0"<?= ($success || $error || $blocksNewCandidature) ? ' data-ce-start="candidature"' : '' ?>>
        <div class="ce-slides" id="ce-slides">

    <section class="ce-slide is-active" id="slide-hero" data-ce-label="Accueil" aria-label="Accueil">
        <div class="ce-hero">
            <div class="ce-hero__media" aria-hidden="true">
                <?php if ($brandBanner !== ''): ?>
                    <img src="<?= htmlspecialchars($brandBanner, ENT_QUOTES, 'UTF-8') ?>" alt="">
                <?php else: ?>
                    <div class="ce-hero__fallback"></div>
                <?php endif; ?>
            </div>
            <div class="ce-hero__scrim"></div>
            <div class="ce-hero__inner">
                <p class="ce-hero__kicker">
                    <span class="ce-hero__kicker-dot" aria-hidden="true"></span>
                    <?= htmlspecialchars((string) $p['queue_label']) ?>
                </p>
                <div class="ce-hero__brand-row">
                    <?php if ($brandLogo !== ''): ?>
                        <img class="ce-hero__logo" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Emblème <?= htmlspecialchars($tenantName) ?>">
                    <?php endif; ?>
                </div>
                <h1 class="ce-hero__name"><span class="ce-community-name"><?= htmlspecialchars($tenantName) ?></span></h1>
                <?php if ($heroLead !== ''): ?>
                <p class="ce-hero__tagline"><?= htmlspecialchars($heroLead) ?></p>
                <?php endif; ?>
                <div class="ce-hero__meta">
                    <span class="ce-chip"><?= htmlspecialchars((string) $p['portal_title']) ?></span>
                    <span class="ce-chip"><?= htmlspecialchars((string) $p['classified_badge']) ?></span>
                    <?php if ($blocksNewCandidature): ?>
                    <span class="ce-chip">Dossier déjà déposé</span>
                    <?php elseif ($selectedRecruitmentOpening !== null): ?>
                    <span class="ce-chip">Poste ciblé</span>
                    <?php endif; ?>
                </div>
                <div class="ce-hero__cta-row">
                    <?php if ($blocksNewCandidature && !empty($existingCandidature['portal_url'])): ?>
                    <a href="<?= htmlspecialchars((string) $existingCandidature['portal_url'], ENT_QUOTES, 'UTF-8') ?>" class="ce-btn ce-btn--primary">Suivre ma candidature</a>
                    <a href="<?= $formAnchor ?>" class="ce-btn ce-btn--ghost">Voir le statut</a>
                    <?php elseif ($blocksNewCandidature): ?>
                    <a href="<?= $formAnchor ?>" class="ce-btn ce-btn--primary">Voir le statut de mon dossier</a>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars($startHref, ENT_QUOTES, 'UTF-8') ?>" class="ce-btn ce-btn--primary ce-btn--lg">Commencer ma candidature</a>
                    <a href="#attentes" class="ce-btn ce-btn--ghost">Ce que nous attendons</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($showcaseUrl) ?>" class="ce-btn ce-btn--ghost">Page de la communauté</a>
                </div>
            </div>
        </div>
    </section>

    <section class="ce-slide" id="parcours" data-ce-label="Parcours" aria-label="Parcours" aria-hidden="true" tabindex="-1">
        <div class="ce-section ce-section--slide">
            <div class="ce-wrap">
                <p class="ce-kicker">Parcours</p>
                <h2 class="ce-title">Trois étapes pour rejoindre</h2>
                <p class="ce-lead">Un dossier clair, une lecture attentive par l’équipe, puis un retour si votre profil correspond.</p>
                <div class="ce-steps">
                    <?php foreach ($journeySteps as $i => $step): ?>
                    <div class="ce-step">
                        <div class="ce-step__n"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></div>
                        <h3 class="ce-step__title"><?= htmlspecialchars($step[0]) ?></h3>
                        <p class="ce-step__text"><?= htmlspecialchars($step[1]) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="ce-slide" id="attentes" data-ce-label="Attentes" aria-label="Avant de candidater" aria-hidden="true" tabindex="-1">
        <div class="ce-section ce-section--slide ce-section--alt ce-att">
            <?php if ($brandBanner !== ''): ?>
            <div class="ce-att__bg" aria-hidden="true"><img src="<?= htmlspecialchars($brandBanner, ENT_QUOTES, 'UTF-8') ?>" alt=""></div>
            <?php endif; ?>
            <div class="ce-wrap ce-att__grid">
                <header class="ce-att__head">
                    <div class="ce-att__brand">
                        <?php if ($brandLogo !== ''): ?>
                            <img class="ce-att__logo" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Emblème <?= htmlspecialchars($tenantName) ?>" width="72" height="72">
                        <?php else: ?>
                            <div class="ce-att__mark" aria-hidden="true"><?= htmlspecialchars($logoLetter) ?></div>
                        <?php endif; ?>
                        <p class="ce-att__name"><span class="ce-community-name"><?= htmlspecialchars($tenantName) ?></span></p>
                    </div>
                    <p class="ce-kicker"><?= htmlspecialchars((string) $p['roe_title']) ?></p>
                    <h2 class="ce-att__title">Avant de candidater</h2>
                    <p class="ce-att__lead"><?= htmlspecialchars((string) $p['op_col1']) ?></p>
                    <div class="ce-att__cta">
                        <?php if ($blocksNewCandidature): ?>
                        <a href="<?= $formAnchor ?>" class="ce-btn ce-btn--primary ce-btn--lg">Voir le statut de mon dossier</a>
                        <?php else: ?>
                        <a href="<?= htmlspecialchars($startHref, ENT_QUOTES, 'UTF-8') ?>" class="ce-btn ce-btn--primary ce-btn--lg">Commencer ma candidature</a>
                        <?php if (!$isAuthenticated): ?>
                        <p class="ce-att__cta-note">Un compte est nécessaire : vous reviendrez ici après la connexion.</p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </header>
                <div class="ce-att__main">
                    <?php if ($roeItems !== []): ?>
                    <ol class="ce-att__cards">
                        <?php foreach ($roeItems as $i => $rule): ?>
                        <li class="ce-att-card">
                            <span class="ce-att-card__n" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <p class="ce-att-card__text"><?= htmlspecialchars($rule) ?></p>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php else: ?>
                    <p class="ce-empty">Aucune consigne particulière n’est affichée pour le moment. Suivez les indications du formulaire de candidature.</p>
                    <?php endif; ?>
                    <div class="ce-att__notes">
                        <aside class="ce-callout ce-callout--ai" role="note">
                            <p class="ce-callout__title">Aucune IA dans votre dossier</p>
                            <p class="ce-callout__text"><?= htmlspecialchars((string) $p['op_ai_warning']) ?></p>
                        </aside>
                        <aside class="ce-callout" role="note">
                            <p class="ce-callout__title">Après l’envoi</p>
                            <p class="ce-callout__text"><?= htmlspecialchars((string) $p['op_col2']) ?> <?= htmlspecialchars((string) $p['archive_note']) ?></p>
                        </aside>
                    </div>
                </div>
                <ol class="ce-att__steps" aria-label="Étapes de la candidature">
                    <?php foreach ($journeySteps as $i => $step): ?>
                    <li class="ce-att-step">
                        <span class="ce-att-step__n" aria-hidden="true"><?= $i + 1 ?></span>
                        <div>
                            <p class="ce-att-step__title"><?= htmlspecialchars($step[0]) ?></p>
                            <p class="ce-att-step__text"><?= htmlspecialchars($step[1]) ?></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
    </section>

    <section class="ce-slide" id="offres" data-ce-label="Postes ouverts" aria-label="Postes ouverts" aria-hidden="true" tabindex="-1">
        <div class="ce-section ce-section--slide">
            <div class="ce-wrap">
                <p class="ce-kicker">Postes ouverts</p>
                <h2 class="ce-title">Candidater sur une offre</h2>
                <?php if ($selectedRecruitmentOpening !== null): ?>
                <p class="ce-lead">Vous postulez actuellement pour « <?= htmlspecialchars((string) ($selectedRecruitmentOpening['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ». Vous pouvez aussi choisir une autre offre ci-dessous.</p>
                <?php else: ?>
                <p class="ce-lead">Si un poste vous intéresse, sélectionnez-le pour rattacher votre dossier. Sinon, continuez avec une candidature générale.</p>
                <?php endif; ?>

                <?php if ($publishedOpenings === []): ?>
                <p class="ce-empty">Aucun poste publié pour le moment. Vous pouvez tout de même déposer une candidature générale via le formulaire.</p>
                <?php else: ?>
                <div class="ce-openings">
                    <?php foreach ($publishedOpenings as $ro): ?>
                        <?php
                        if (!is_array($ro)) {
                            continue;
                        }
                        $oid = (int) ($ro['id'] ?? 0);
                        if ($oid <= 0) {
                            continue;
                        }
                        $otitle = trim((string) ($ro['title'] ?? ''));
                        $ounit = trim((string) ($ro['unit_name'] ?? ''));
                        $isSelected = $selectedRecruitmentOpening !== null && (int) ($selectedRecruitmentOpening['id'] ?? 0) === $oid;
                        // Visiteur sans compte : l'offre passe par la connexion, puis revient sur le formulaire.
                        if ($isAuthenticated) {
                            $oUrl = $tenantSlugForForm !== ''
                                ? url('c/' . rawurlencode($tenantSlugForForm) . '/enlistment?ouverture=' . $oid) . '#candidature'
                                : url('enlistment?ouverture=' . $oid) . '#candidature';
                        } else {
                            $oUrl = $tenantSlugForForm !== ''
                                ? url('c/' . rawurlencode($tenantSlugForForm) . '/enlistment/connexion?ouverture=' . $oid)
                                : $loginStepUrl;
                        }
                        ?>
                    <a class="ce-opening" href="<?= htmlspecialchars($oUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <h3 class="ce-opening__title"><?= htmlspecialchars($otitle !== '' ? $otitle : 'Poste ouvert') ?><?= $isSelected ? ' · sélectionné' : '' ?></h3>
                        <?php if ($ounit !== ''): ?>
                        <p class="ce-opening__meta"><?= htmlspecialchars($ounit) ?></p>
                        <?php endif; ?>
                        <span class="ce-opening__cta"><?= $isSelected ? 'Déjà sélectionné — remplir le dossier' : 'Candidater à ce poste' ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="ce-slide ce-slide--form" id="candidature" data-ce-label="<?= $blocksNewCandidature ? 'Mon dossier' : 'Candidature' ?>" aria-label="<?= $blocksNewCandidature ? 'Statut de la candidature' : 'Formulaire de candidature' ?>" aria-hidden="true" tabindex="-1">
        <div class="ce-form-stage<?= $blocksNewCandidature ? ' ce-form-stage--status' : '' ?>">
        <?php if ($success): ?>
        <div class="ce-alert ce-alert--ok" role="status"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="ce-alert ce-alert--err" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$isAuthenticated): ?>
        <div class="ce-wrap ce-gate-stage">
            <?php $accountTheme = 'dark'; require base_path('views/enlistment/_account_gate.php'); ?>
        </div>
        <?php elseif ($blocksNewCandidature && $existingCandidature !== null): ?>
        <div class="ce-wrap ce-status-layout">
            <article class="ce-status-panel" role="status">
                <header class="ce-status-panel__head">
                    <p class="ce-status-panel__doc"><?= htmlspecialchars((string) ($p['doc_control'] ?? 'Dossier de recrutement')) ?></p>
                    <h2 class="ce-status-panel__title">
                        <span class="ce-title-verb"><?= htmlspecialchars($candidatePrefix) ?></span>
                        <span class="ce-community-name"><?= htmlspecialchars($tenantName) ?></span>
                    </h2>
                    <p class="ce-status-panel__sub">Votre dossier est déjà enregistré — aucun nouveau formulaire n’est nécessaire.</p>
                </header>

                <div class="ce-status-panel__body">
                    <p class="ce-status-badge"><?= htmlspecialchars((string) ($existingCandidature['status_label'] ?? 'En cours'), ENT_QUOTES, 'UTF-8') ?></p>
                    <h3 class="ce-status-panel__heading"><?= htmlspecialchars((string) ($existingCandidature['title'] ?? 'Candidature déjà en cours'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="ce-status-panel__lead"><?= htmlspecialchars((string) ($existingCandidature['lead'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if (!empty($existingCandidature['next_steps'])): ?>
                    <p class="ce-status-panel__lead"><?= htmlspecialchars((string) $existingCandidature['next_steps'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <dl class="ce-status-meta">
                        <?php if ($dossierRefLabel !== ''): ?>
                        <div class="ce-status-meta__row">
                            <dt><?= htmlspecialchars((string) ($p['ref_label'] ?? 'Référence')) ?></dt>
                            <dd><code><?= htmlspecialchars($dossierRefLabel) ?></code></dd>
                        </div>
                        <?php endif; ?>
                        <?php if ($submittedAtLabel !== ''): ?>
                        <div class="ce-status-meta__row">
                            <dt>Déposée le</dt>
                            <dd><?= htmlspecialchars($submittedAtLabel) ?></dd>
                        </div>
                        <?php endif; ?>
                        <div class="ce-status-meta__row">
                            <dt>Connexion</dt>
                            <dd><?= htmlspecialchars((string) ($p['security_label'] ?? 'Sécurisée')) ?></dd>
                        </div>
                        <div class="ce-status-meta__row">
                            <dt>Communauté</dt>
                            <dd><span class="ce-community-name"><?= htmlspecialchars($tenantName) ?></span></dd>
                        </div>
                    </dl>

                    <div class="ce-status-panel__actions">
                        <?php if (!empty($existingCandidature['portal_url'])): ?>
                        <a class="ce-btn ce-btn--primary" href="<?= htmlspecialchars((string) $existingCandidature['portal_url'], ENT_QUOTES, 'UTF-8') ?>">Suivre ma candidature</a>
                        <?php endif; ?>
                        <a class="ce-btn ce-btn--soft" href="<?= htmlspecialchars($showcaseUrl) ?>">Page de la communauté</a>
                    </div>
                </div>
            </article>
        </div>
        <?php else: ?>
        <div class="ce-wrap ce-form-layout">
            <aside class="ce-rail" aria-label="Avancement du dossier">
                <?php
                $railClassification = trim((string) ($p['rail_classification'] ?? ''));
                $railMetaRows = \App\Services\Community\EnlistmentMilsimPackService::normalizeRailMetaRows($p['rail_meta_rows'] ?? []);
                $railStamp = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', (string) ($p['watermark'] ?? 'ATHENA')) ?: 'ATHENA', 0, 8));
                $railHour = date('H:i');
                $railDay = date('d/m/Y');
                ?>
                <div class="ce-rail__block">
                    <?php if ($railClassification !== ''): ?>
                        <p class="ce-rail__classif" aria-hidden="true"><?= htmlspecialchars($railClassification, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="ce-rail__label"><?= htmlspecialchars((string) $p['session_block_title']) ?></p>
                    <div class="ce-rail__row">
                        <span><?= htmlspecialchars((string) $p['ref_label']) ?></span>
                        <span><?= htmlspecialchars($ref) ?></span>
                    </div>
                    <div class="ce-rail__row">
                        <span>Liaison</span>
                        <span><?= htmlspecialchars((string) $p['security_label']) ?></span>
                    </div>
                    <div class="ce-rail__row">
                        <span>Horodatage</span>
                        <span><?= htmlspecialchars($railDay . ' · ' . $railHour, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php foreach ($railMetaRows as $metaRow): ?>
                        <div class="ce-rail__row ce-rail__row--meta">
                            <span><?= htmlspecialchars((string) $metaRow['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span><?= htmlspecialchars((string) $metaRow['value'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="ce-progress">
                        <div class="ce-progress__bar"><div class="ce-progress__fill" id="progress-bar"></div></div>
                        <p id="progress-text" class="ce-progress__text" data-progress-prefix="<?= htmlspecialchars((string) $p['progress_prefix']) ?>"><?= htmlspecialchars((string) $p['progress_prefix']) ?> 0 réponses</p>
                    </div>
                    <p class="ce-rail__stamp" aria-hidden="true">VISA <?= htmlspecialchars($railStamp, ENT_QUOTES, 'UTF-8') ?> · SAISIE CANDIDAT</p>
                </div>
                <nav class="ce-rail__nav">
                    <p class="ce-rail__nav-label">Parcours dossier</p>
                    <a href="#ce-sec-mode">01 — Votre compte</a>
                    <a href="#ce-sec-identity">02 — Disponibilités</a>
                    <a href="#ce-sec-gear">03 — Matériel et expérience</a>
                    <a href="#ce-sec-motivation">04 — <?= htmlspecialchars($motivationSectionTitle) ?></a>
                    <a href="#ce-sec-commit">05 — Engagement</a>
                </nav>
            </aside>

            <div class="ce-form-shell">
                <div class="ce-form-head">
                    <p class="ce-form-head__doc"><?= htmlspecialchars((string) $p['doc_control']) ?></p>
                    <h2 class="ce-form-head__title">
                        <span class="ce-title-verb"><?= htmlspecialchars($candidatePrefix) ?></span>
                        <span class="ce-community-name"><?= htmlspecialchars($tenantName) ?></span>
                    </h2>
                    <div class="ce-form-head__meta">
                        <span><?= htmlspecialchars((string) $p['queue_label']) ?></span>
                        <span><?= htmlspecialchars((string) $p['ref_label']) ?> <?= htmlspecialchars($ref) ?></span>
                    </div>
                </div>

                <?php if ($existingCandidature !== null): ?>
                    <div class="ce-notice ce-notice--<?= !empty($existingCandidature['blocks_form']) ? 'info' : 'muted' ?>" role="status">
                        <p class="ce-notice__kicker"><?= htmlspecialchars((string) ($existingCandidature['status_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <h3 class="ce-notice__title"><?= htmlspecialchars((string) ($existingCandidature['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="ce-notice__lead"><?= htmlspecialchars((string) ($existingCandidature['lead'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (!empty($existingCandidature['next_steps'])): ?>
                            <p class="ce-notice__lead"><?= htmlspecialchars((string) $existingCandidature['next_steps'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <?php if (!empty($existingCandidature['portal_url'])): ?>
                            <p class="ce-notice__actions">
                                <a class="ce-btn ce-btn--primary" href="<?= htmlspecialchars((string) $existingCandidature['portal_url'], ENT_QUOTES, 'UTF-8') ?>">Suivre ma candidature</a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="ce-mobile-progress">
                    <div class="ce-progress">
                        <div class="ce-progress__bar"><div class="ce-progress__fill" id="progress-bar-mobile" style="width:0%"></div></div>
                        <p id="progress-text-mobile" class="ce-progress__text"><?= htmlspecialchars((string) $p['progress_prefix']) ?> 0 réponses</p>
                    </div>
                </div>

                <form method="post" action="<?= htmlspecialchars($formAction) ?>" class="ce-form<?= $compactAccountOpening ? ' enlist-compact-default' : '' ?>" id="recruitment-form" data-compact-opening="<?= $compactAccountOpening ? '1' : '0' ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <?php $inviteLinkTheme = 'dark'; require base_path('views/enlistment/_invite_link.php'); ?>
                    <?php if ($tenantSlugForForm !== ''): ?>
                        <input type="hidden" name="enlistment_tenant_slug" value="<?= htmlspecialchars($tenantSlugForForm) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="enlistment_form_mode" id="enlistment_form_mode" value="<?= $compactAccountOpening ? 'compact' : 'full' ?>">

                    <?php if ($selectedRecruitmentOpening !== null && !empty($selectedRecruitmentOpening['id'])): ?>
                        <input type="hidden" name="enlistment_opening_id" value="<?= (int) $selectedRecruitmentOpening['id'] ?>">
                        <div class="ce-banner">
                            <p class="ce-banner__kicker">Candidature ciblée</p>
                            <p class="ce-banner__title">Vous postulez pour : <?= htmlspecialchars((string) ($selectedRecruitmentOpening['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="ce-banner__text">Votre dossier sera rattaché à cet avis pour le suivi côté recrutement.</p>
                        </div>
                        <?php if ($enlistmentMemberOpeningInsight !== null): ?>
                            <?php require base_path('views/partials/enlistment_member_opening_insight.php'); ?>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="ce-archive">
                        <strong>À savoir —</strong> <?= htmlspecialchars((string) $p['archive_note']) ?>
                    </div>

                    <section class="ce-form-section" id="ce-sec-mode">
                        <h3 class="ce-section-title">Votre compte</h3>
                        <?php $accountTheme = 'dark'; require base_path('views/enlistment/_account_identity.php'); ?>

                        <?php if ($compactAccountOpening): ?>
                            <div id="enlist-compact-toolbar" class="ce-banner">
                                <p class="ce-banner__kicker">Parcours court</p>
                                <p class="ce-banner__text">Seuls les éléments utiles à une candidature ciblée sont affichés. Vous pouvez à tout moment compléter le dossier comme pour une première inscription.</p>
                                <p>
                                    <button type="button" id="enlist-btn-expand-full" class="ce-btn ce-btn--ghost">Fournir le questionnaire complet</button>
                                </p>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="ce-form-section enlist-full-only" id="ce-sec-identity">
                        <h3 class="ce-section-title">Disponibilités</h3>
                        <div class="ce-field-grid" id="ce-identity-meta-grid">
                            <div class="ce-field ce-span-2">
                                <label class="ce-label" for="enlist-timezone"><?= htmlspecialchars((string) $fld('timezone')['label']) ?></label>
                                <select name="timezone" id="enlist-timezone" class="input-field track-field">
                                    <option value="">Sélectionner</option>
                                    <?php foreach ($timezoneOptions as $tzOpt): ?>
                                        <?php
                                        $tzVal = (string) ($tzOpt['iana'] ?? '');
                                        $tzLab = (string) ($tzOpt['option_label'] ?? $tzOpt['label'] ?? $tzVal);
                                        $tzSel = ($prefill['timezone'] !== '' && $prefill['timezone'] === $tzVal) ? ' selected' : '';
                                        ?>
                                        <option value="<?= htmlspecialchars($tzVal, ENT_QUOTES, 'UTF-8') ?>"<?= $tzSel ?>><?= htmlspecialchars($tzLab, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <fieldset class="ce-field ce-span-2 ce-slots">
                                <legend class="ce-label"><?= htmlspecialchars((string) $fld('weekly_availability')['label']) ?></legend>
                                <div class="ce-slot-box">
                                    <?php foreach ($availabilitySlots as $slot): ?>
                                        <?php
                                        $slotId = (string) ($slot['id'] ?? '');
                                        $slotLabel = (string) ($slot['label'] ?? '');
                                        if ($slotLabel === '') {
                                            continue;
                                        }
                                        $slotChecked = in_array($slotLabel, $prefillAvailabilitySelected, true);
                                        ?>
                                        <label class="ce-check">
                                            <input type="checkbox"
                                                   name="availability_slot[]"
                                                   value="<?= htmlspecialchars($slotId !== '' ? $slotId : $slotLabel, ENT_QUOTES, 'UTF-8') ?>"
                                                   class="track-field"
                                                   <?= $slotChecked ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <label class="ce-label ce-label--sub" for="enlist-availability-notes">Précisions <span class="ce-label-optional">(facultatif)</span></label>
                                <input type="text" id="enlist-availability-notes" name="weekly_availability_notes" class="input-field track-field" maxlength="300" placeholder="Exceptions, périodes d’absence…">
                            </fieldset>
                        </div>
                        <?php if ($customBySection['identity'] !== []): ?>
                            <div class="ce-field-stack" style="margin-top:1.1rem">
                                <?php foreach ($customBySection['identity'] as $customQuestion): ?>
                                    <?php include base_path('views/partials/enlistment_custom_question_field.php'); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="ce-form-section enlist-full-only" id="ce-sec-gear">
                        <h3 class="ce-section-title"><?= htmlspecialchars((string) $p['section_2']) ?></h3>
                        <div class="ce-field-grid">
                            <div class="ce-span-2">
                                <?php $fieldName = 'system_config'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            </div>
                            <?php $fieldName = 'microphone_quality'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            <?php $fieldName = 'ace_acre_level'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            <div class="ce-span-2">
                                <?php $fieldName = 'past_milsim_experience'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            </div>
                        </div>
                        <?php if ($customBySection['gear'] !== []): ?>
                            <div class="ce-field-stack" style="margin-top:1.1rem">
                                <?php foreach ($customBySection['gear'] as $customQuestion): ?>
                                    <?php include base_path('views/partials/enlistment_custom_question_field.php'); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="ce-form-section" id="ce-sec-motivation">
                        <h3 class="ce-section-title"><?= htmlspecialchars($motivationSectionTitle) ?></h3>
                        <?php if (trim((string) ($motivationSection['intro'] ?? '')) !== ''): ?>
                            <p class="ce-help" style="margin-bottom:1.25rem;"><?= htmlspecialchars((string) $motivationSection['intro']) ?></p>
                        <?php endif; ?>
                        <div class="space-y-6">
                            <?php if (!empty($motivationSection['why_join']['enabled'])): ?>
                                <?php
                                $fieldName = 'motivation_why_join';
                                $fieldRequired = !empty($motivationSection['why_join']['required']);
                                $fieldOverride = [
                                    'label' => (string) ($motivationSection['why_join']['label'] ?? ''),
                                    'placeholder' => (string) ($motivationSection['why_join']['placeholder'] ?? ''),
                                    'help' => (string) ($motivationSection['why_join']['help'] ?? ''),
                                    'widget' => 'textarea',
                                    'options' => [],
                                ];
                                include base_path('views/partials/enlistment_milsim_widget.php');
                                ?>
                            <?php endif; ?>
                            <?php if (!empty($motivationSection['accountability']['enabled'])): ?>
                            <div class="enlist-full-only">
                                <?php
                                $fieldName = 'motivation_accountability';
                                $fieldRequired = !empty($motivationSection['accountability']['required']);
                                $fieldOverride = [
                                    'label' => (string) ($motivationSection['accountability']['label'] ?? ''),
                                    'placeholder' => (string) ($motivationSection['accountability']['placeholder'] ?? ''),
                                    'help' => (string) ($motivationSection['accountability']['help'] ?? ''),
                                    'widget' => 'textarea',
                                    'options' => [],
                                ];
                                include base_path('views/partials/enlistment_milsim_widget.php');
                                ?>
                            </div>
                            <?php endif; ?>
                            <?php foreach ($customBySection['motivation'] as $customQuestion): ?>
                                <?php include base_path('views/partials/enlistment_custom_question_field.php'); ?>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="ce-form-section enlist-full-only" id="ce-sec-commit">
                        <h3 class="ce-section-title"><?= htmlspecialchars((string) $p['section_4']) ?></h3>
                        <div class="ce-commit-row">
                            <label for="enlist-commitment-effort"><?= htmlspecialchars((string) $p['commitment_q13']) ?></label>
                            <select name="commitment_effort" id="enlist-commitment-effort" class="input-field track-field">
                                <option value="">Sélectionner</option>
                                <option value="Oui">Oui</option>
                                <option value="Non">Non</option>
                            </select>
                        </div>
                        <div class="ce-commit-row">
                            <label for="enlist-availability-wed-sat"><?= htmlspecialchars((string) $p['availability_q15']) ?></label>
                            <select name="availability_wed_sat" id="enlist-availability-wed-sat" class="input-field track-field">
                                <option value="">Sélectionner</option>
                                <option value="Oui">Oui</option>
                                <option value="Non">Non</option>
                                <option value="Variable">Variable</option>
                            </select>
                        </div>
                        <?php if ($customBySection['commitment'] !== []): ?>
                            <div class="ce-field-stack" style="margin-top:0.5rem">
                                <?php foreach ($customBySection['commitment'] as $customQuestion): ?>
                                    <?php include base_path('views/partials/enlistment_custom_question_field.php'); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="ce-form-section" id="ce-sec-notices" aria-label="Informations importantes">
                        <h3 class="ce-section-title">Avant d’envoyer</h3>
                        <div class="ce-notice-stack">
                            <aside class="ce-notice ce-notice--warn">
                                <p class="ce-notice__kicker">Recrutement de l’unité</p>
                                <ul class="ce-notice__list">
                                    <?php foreach ($disclaimerRecruitment as $dLine): ?>
                                        <li><?= htmlspecialchars((string) $dLine, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </aside>
                            <aside class="ce-notice ce-notice--muted">
                                <p class="ce-notice__kicker">Rôle de la plateforme</p>
                                <p class="ce-notice__lead">Athena permet de déposer et de suivre votre dossier. La plateforme n’intervient pas dans les décisions de recrutement de l’unité : l’examen, l’acceptation ou le refus relèvent uniquement de l’équipe de <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?>.</p>
                            </aside>
                            <aside class="ce-notice ce-notice--info">
                                <p class="ce-notice__kicker">Analyse automatique des formulations</p>
                                <p class="ce-notice__lead">Vos réponses font l’objet d’une analyse automatique destinée à repérer les insultes ou propos injurieux. Un contenu inapproprié peut entraîner le rejet d’un message ou la suspension de l’accès au suivi.</p>
                            </aside>
                        </div>
                    </section>

                    <div class="ce-field-group">
                        <label for="invite-code" class="ce-label">
                            Code d'invitation prioritaire (optionnel)
                            <span class="ce-label-hint">Si l’équipe vous a transmis un code prioritaire, saisissez-le ici pour accélérer votre candidature</span>
                        </label>
                        <input type="text"
                               name="invite_code"
                               id="invite-code"
                               class="input-field"
                               placeholder="Ex: MIGRATION2026"
                               maxlength="64"
                               pattern="[A-Z0-9\-_]*"
                               style="text-transform: uppercase;">
                    </div>

                    <div class="ce-submit-zone">
                        <?php $accountTheme = 'dark'; require base_path('views/enlistment/_account_consent.php'); ?>
                        <?php if ($requireAiAck): ?>
                            <div class="ce-ai-ack">
                                <input type="checkbox" name="no_ai_confirmed" id="no-ai-check" value="1" class="track-field" required>
                                <label for="no-ai-check"><?= htmlspecialchars((string) $p['ai_checkbox']) ?></label>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="no_ai_confirmed" value="1">
                        <?php endif; ?>
                        <button type="submit" class="ce-btn ce-btn--primary ce-btn--block"><?= htmlspecialchars((string) $p['submit_button']) ?></button>
                        <p class="ce-submit-foot"><?= htmlspecialchars((string) $p['submit_footer']) ?></p>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        </div>

        <footer class="ce-foot">
            <div class="ce-wrap">
                <a href="<?= htmlspecialchars($showcaseUrl) ?>">Retour à la page de <span class="ce-community-name"><?= htmlspecialchars($tenantName) ?></span></a>
                · Athena
            </div>
        </footer>
    </section>

        </div><!-- #ce-slides -->

        <nav class="ce-deck-nav" aria-label="Navigation des écrans">
            <button type="button" class="ce-deck-btn" id="ce-deck-prev" aria-label="Écran précédent">Précédent</button>
            <div class="ce-deck-dots" id="ce-deck-dots" role="tablist" aria-label="Indicateurs d’écran"></div>
            <button type="button" class="ce-deck-btn ce-deck-btn--next" id="ce-deck-next" aria-label="Écran suivant">Suivant</button>
            <p class="ce-deck-live" id="ce-deck-live" aria-live="polite"></p>
        </nav>
    </div><!-- #ce-deck -->

    <script>
        var ENLIST_PREAMBLE_KEY = <?= json_encode('athena_enlist_preamble_' . $enlistSlug, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        var ENLIST_PREAMBLE_LABEL = <?= json_encode((string) $p['preamble_title'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        function updateClock() {
            var t = new Date().toISOString().split('T')[1].split('.')[0];
            var el = document.getElementById('clock');
            if (el) el.textContent = t;
        }
        function updateProgress() {
            var fields = document.querySelectorAll('.track-field');
            var completed = 0;
            fields.forEach(function(field) {
                if (field.type === 'checkbox') { if (field.checked) completed++; }
                else if (field.value && field.value.trim() !== '') completed++;
            });
            var total = fields.length;
            var percent = total ? Math.round((completed / total) * 100) : 0;
            var bar = document.getElementById('progress-bar');
            var barM = document.getElementById('progress-bar-mobile');
            var text = document.getElementById('progress-text');
            var textM = document.getElementById('progress-text-mobile');
            var prefix = 'Complété :';
            if (text && text.getAttribute('data-progress-prefix')) {
                prefix = text.getAttribute('data-progress-prefix');
            }
            var label = prefix + ' ' + completed + ' / ' + total;
            if (bar) bar.style.width = percent + '%';
            if (barM) barM.style.width = percent + '%';
            if (text) text.textContent = label;
            if (textM) textM.textContent = label;
        }
        function startApp() {
            try {
                localStorage.setItem(ENLIST_PREAMBLE_KEY, JSON.stringify({
                    label: ENLIST_PREAMBLE_LABEL,
                    accepted: true,
                    at: new Date().toISOString()
                }));
            } catch (e) {}
            var p = document.getElementById('preamble');
            if (p) {
                p.classList.add('is-hidden');
                setTimeout(function() { p.classList.add('hidden'); }, 850);
            }
        }
        (function checkStoredAccess() {
            try {
                var raw = localStorage.getItem(ENLIST_PREAMBLE_KEY);
                if (raw) {
                    var data = JSON.parse(raw);
                    if (data && data.accepted === true) {
                        var p = document.getElementById('preamble');
                        if (p && p.getAttribute('data-skip-if-stored') === '1') {
                            p.classList.add('is-hidden', 'hidden');
                        }
                    }
                }
            } catch (e) {}
        })();
        setInterval(updateClock, 1000);
        updateClock();
        document.querySelectorAll('.track-field').forEach(function(f) {
            f.addEventListener('input', updateProgress);
            f.addEventListener('change', updateProgress);
        });
        updateProgress();

        (function enlistmentFormUi() {
            var form = document.getElementById('recruitment-form');
            if (!form) return;
            var compactOpening = form.getAttribute('data-compact-opening') === '1';
            var modeInput = document.getElementById('enlistment_form_mode');
            var btnExpand = document.getElementById('enlist-btn-expand-full');

            function syncMotivationRequired() {
                var ta = document.querySelector('textarea[name="motivation_why_join"]');
                if (!ta) return;
                var tenantRequired = ta.getAttribute('data-tenant-required') === '1';
                var compact = form.classList.contains('enlist-compact-default') && !form.classList.contains('enlist-compact-expanded');
                ta.required = tenantRequired || compact;
            }

            if (btnExpand) {
                btnExpand.addEventListener('click', function() {
                    form.classList.add('enlist-compact-expanded');
                    if (modeInput) modeInput.value = 'full';
                    syncMotivationRequired();
                    try {
                        var s = document.getElementById('ce-sec-identity');
                        if (s) s.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } catch (e) {}
                });
            }
            if (modeInput) modeInput.value = compactOpening ? 'compact' : 'full';
            syncMotivationRequired();
            form.addEventListener('submit', syncMotivationRequired);
        })();
    </script>
    <script src="<?= htmlspecialchars($jsHref) ?>" defer></script>
    <?php require base_path('views/partials/analytics_beacon.php'); ?>
</body>
</html>
