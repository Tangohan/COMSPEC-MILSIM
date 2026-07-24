<?php
$base = url('');
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
$ref = 'JTFO-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
$tenant = $tenant ?? [];
$communityConfig = $communityConfig ?? [];
$formAction = $formAction ?? url('enlistment');
$tenantName = trim((string) ($tenant['name'] ?? 'Athena'));
$requireAiAck = array_key_exists('require_ai_ack', $communityConfig) ? (bool) $communityConfig['require_ai_ack'] : true;
$milsimPack = $milsimPack ?? \App\Services\Community\EnlistmentMilsimPackService::defaultPack();
$p = $milsimPack;
$fld = static function (string $k) use ($p): array {
    if (is_array($p['fields'][$k] ?? null)) {
        return $p['fields'][$k];
    }
    return ['label' => $k, 'placeholder' => '', 'widget' => 'text', 'options' => []];
};
$enlistSlug = trim((string) ($tenant['slug'] ?? 'default'));
$enlistmentContext = $enlistmentContext ?? [];
$canUseAccount = !empty($enlistmentContext['canUseAccount']);
$prefill = array_merge([
    'full_name' => '', 'email' => '', 'callsign' => '', 'age' => '', 'timezone' => '', 'weekly_availability' => '',
], is_array($enlistmentContext['prefill'] ?? null) ? $enlistmentContext['prefill'] : []);
$recruitmentPresets = $enlistmentContext['recruitmentPresets'] ?? [];
$hasMembershipOnTarget = !empty($enlistmentContext['hasMembershipOnTarget']);
$switchToTargetUrl = $enlistmentContext['switchToTargetUrl'] ?? null;
$tenantSlugForForm = trim((string) ($tenant['slug'] ?? ''));
$selectedRecruitmentOpening = is_array($selectedRecruitmentOpening ?? null) ? $selectedRecruitmentOpening : null;
$analyticsBeacon = $analyticsBeacon ?? null;
$enlistmentMemberOpeningInsight = is_array($enlistmentMemberOpeningInsight ?? null) ? $enlistmentMemberOpeningInsight : null;
$compactAccountOpening = $canUseAccount && $selectedRecruitmentOpening !== null;
$publishedOpenings = is_array($publishedOpenings ?? null) ? $publishedOpenings : [];
$tenantBranding = is_array($tenantBranding ?? null) ? $tenantBranding : [];

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

$ceStyle = '';
if ($brandPrimary !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandPrimary)) {
    $ceStyle .= '--ce-tenant-primary:' . $brandPrimary . ';';
}
if ($brandAccent !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandAccent)) {
    $ceStyle .= '--ce-tenant-accent:' . $brandAccent . ';';
}

$showcaseUrl = $tenantSlugForForm !== '' ? url('c/' . rawurlencode($tenantSlugForForm)) : $base . '/';
$formAnchor = '#candidature';
$logoLetter = mb_strtoupper(mb_substr(trim((string) ($p['logo_letter'] ?? 'A')) ?: mb_substr($tenantName, 0, 1) ?: 'A', 0, 1));
$heroLead = trim((string) ($p['preamble_lead'] ?? ''));
$roeItems = is_array($p['roe_items'] ?? null) ? array_values(array_filter($p['roe_items'], static fn ($r) => is_string($r) && trim($r) !== '')) : [];
$cssHref = url('assets/css/community-enlistment.css');
$jsHref = url('assets/js/community-enlistment.js');
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
<body class="ce ce--deck"<?= $ceStyle !== '' ? ' style="' . htmlspecialchars($ceStyle, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

    <div id="preamble" class="ce-gate" data-skip-if-stored="1">
        <div class="ce-gate__card">
            <div class="ce-gate__brand">
                <?php if ($brandLogo !== ''): ?>
                    <img class="ce-topbar__logo" src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" width="52" height="52" style="width:3.25rem;height:3.25rem;border-radius:0.85rem">
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
            <a href="<?= htmlspecialchars($showcaseUrl) ?>">Vitrine</a>
            <a href="<?= $formAnchor ?>">Candidater</a>
            <span id="clock" class="ce-topbar__clock" aria-hidden="true">--:--:--</span>
        </nav>
    </header>

    <div class="ce-deck" id="ce-deck" data-ce-index="0"<?= ($success || $error) ? ' data-ce-start="candidature"' : '' ?>>
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
                <h1 class="ce-hero__name"><?= htmlspecialchars($tenantName) ?></h1>
                <?php if ($heroLead !== ''): ?>
                <p class="ce-hero__tagline"><?= htmlspecialchars($heroLead) ?></p>
                <?php endif; ?>
                <div class="ce-hero__meta">
                    <span class="ce-chip"><?= htmlspecialchars((string) $p['portal_title']) ?></span>
                    <span class="ce-chip"><?= htmlspecialchars((string) $p['classified_badge']) ?></span>
                    <?php if ($selectedRecruitmentOpening !== null): ?>
                    <span class="ce-chip">Poste ciblé</span>
                    <?php endif; ?>
                </div>
                <div class="ce-hero__cta-row">
                    <a href="<?= $formAnchor ?>" class="ce-btn ce-btn--primary"><?= htmlspecialchars((string) $p['candidate_prefix']) ?> — démarrer</a>
                    <a href="#parcours" class="ce-btn ce-btn--ghost">Voir le parcours</a>
                    <a href="<?= htmlspecialchars($showcaseUrl) ?>" class="ce-btn ce-btn--ghost">Retour à la vitrine</a>
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
                    <div class="ce-step">
                        <div class="ce-step__n">01</div>
                        <h3 class="ce-step__title">Préparez votre dossier</h3>
                        <p class="ce-step__text">Identité, disponibilités, matériel et motivation. Répondez avec précision : c’est ce que l’équipe lit en premier.</p>
                    </div>
                    <div class="ce-step">
                        <div class="ce-step__n">02</div>
                        <h3 class="ce-step__title">Envoyez votre candidature</h3>
                        <p class="ce-step__text">Une fois le formulaire validé, votre dossier entre dans la file de recrutement de <?= htmlspecialchars($tenantName) ?>.</p>
                    </div>
                    <div class="ce-step">
                        <div class="ce-step__n">03</div>
                        <h3 class="ce-step__title">Suivi et décision</h3>
                        <p class="ce-step__text">Vous recevez une confirmation, puis un suivi si l’équipe a besoin d’échanger ou de vous accueillir.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ce-slide" id="attentes" data-ce-label="Attentes" aria-label="Avant de candidater" aria-hidden="true" tabindex="-1">
        <div class="ce-section ce-section--slide ce-section--alt">
            <div class="ce-wrap">
                <p class="ce-kicker"><?= htmlspecialchars((string) $p['roe_title']) ?></p>
                <h2 class="ce-title">Avant de candidater</h2>
                <p class="ce-lead"><?= htmlspecialchars((string) $p['op_col1']) ?></p>
                <?php if ($roeItems !== []): ?>
                <div class="ce-reqs">
                    <?php foreach ($roeItems as $i => $rule): ?>
                    <div class="ce-req">
                        <span class="ce-req__n"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <p class="ce-req__text"><?= htmlspecialchars($rule) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="ce-empty">Aucune consigne particulière n’est affichée pour le moment. Suivez les indications du formulaire de candidature.</p>
                <?php endif; ?>
                <div class="ce-note-grid">
                    <div>
                        <p><?= htmlspecialchars((string) $p['op_col2']) ?></p>
                        <p class="ce-note-grid__warn"><?= htmlspecialchars((string) $p['op_ai_warning']) ?></p>
                    </div>
                    <div>
                        <p><?= htmlspecialchars((string) $p['archive_note']) ?></p>
                    </div>
                </div>
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
                        $oUrl = $tenantSlugForForm !== ''
                            ? url('c/' . rawurlencode($tenantSlugForForm) . '/enlistment?ouverture=' . $oid) . '#candidature'
                            : url('enlistment?ouverture=' . $oid) . '#candidature';
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

    <section class="ce-slide ce-slide--form" id="candidature" data-ce-label="Candidature" aria-label="Formulaire de candidature" aria-hidden="true" tabindex="-1">
        <div class="ce-form-stage">
        <?php if ($success): ?>
        <div class="ce-alert ce-alert--ok" role="status"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="ce-alert ce-alert--err" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <div class="ce-wrap ce-form-layout">
            <aside class="ce-rail" aria-label="Avancement du dossier">
                <div class="ce-rail__block">
                    <p class="ce-rail__label"><?= htmlspecialchars((string) $p['session_block_title']) ?></p>
                    <div class="ce-rail__row">
                        <span><?= htmlspecialchars((string) $p['ref_label']) ?></span>
                        <span><?= htmlspecialchars($ref) ?></span>
                    </div>
                    <div class="ce-rail__row">
                        <span>Connexion</span>
                        <span><?= htmlspecialchars((string) $p['security_label']) ?></span>
                    </div>
                    <div class="ce-progress">
                        <div class="ce-progress__bar"><div class="ce-progress__fill" id="progress-bar"></div></div>
                        <p id="progress-text" class="ce-progress__text" data-progress-prefix="<?= htmlspecialchars((string) $p['progress_prefix']) ?>"><?= htmlspecialchars((string) $p['progress_prefix']) ?> 0 réponses</p>
                    </div>
                </div>
                <nav class="ce-rail__nav">
                    <a href="#ce-sec-mode">Mode de candidature</a>
                    <a href="#ce-sec-identity">Identité et contact</a>
                    <a href="#ce-sec-gear">Matériel et expérience</a>
                    <a href="#ce-sec-motivation">Motivation</a>
                    <a href="#ce-sec-commit">Engagement</a>
                </nav>
            </aside>

            <div class="ce-form-shell">
                <div class="ce-form-head">
                    <p class="ce-form-head__doc"><?= htmlspecialchars((string) $p['doc_control']) ?></p>
                    <h2><?= htmlspecialchars((string) $p['candidate_prefix']) ?> <?= htmlspecialchars($tenantName) ?></h2>
                    <div class="ce-form-head__meta">
                        <span><?= htmlspecialchars((string) $p['queue_label']) ?></span>
                        <span><?= htmlspecialchars((string) $p['ref_label']) ?> <?= htmlspecialchars($ref) ?></span>
                    </div>
                </div>

                <div class="ce-mobile-progress">
                    <div class="ce-progress">
                        <div class="ce-progress__bar"><div class="ce-progress__fill" id="progress-bar-mobile" style="width:0%"></div></div>
                        <p id="progress-text-mobile" class="ce-progress__text"><?= htmlspecialchars((string) $p['progress_prefix']) ?> 0 réponses</p>
                    </div>
                </div>

                <form method="post" action="<?= htmlspecialchars($formAction) ?>" class="ce-form<?= $compactAccountOpening ? ' enlist-compact-default' : '' ?>" id="recruitment-form" data-can-use-account="<?= $canUseAccount ? '1' : '0' ?>" data-compact-opening="<?= $compactAccountOpening ? '1' : '0' ?>">
                    <?= \App\Core\Csrf::field() ?>
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

                    <?php if ($hasMembershipOnTarget && $switchToTargetUrl): ?>
                        <div class="ce-banner ce-banner--amber">
                            <p class="ce-banner__kicker">Compte sur cette communauté</p>
                            <p class="ce-banner__text">Basculer le contexte Athena pour préremplir avec votre compte.</p>
                            <p class="ce-banner__text" style="margin-top:0.65rem"><a href="<?= htmlspecialchars($switchToTargetUrl) ?>">Basculer et continuer</a></p>
                        </div>
                    <?php endif; ?>

                    <section class="ce-form-section" id="ce-sec-mode">
                        <h3 class="ce-section-title"><?= htmlspecialchars((string) ($p['section_0'] ?? 'Comment candidater')) ?></h3>
                        <?php if ($canUseAccount): ?>
                            <div class="ce-flow">
                                <button type="button" id="enlist-btn-flow-account" class="ce-flow-btn is-active enlist-flow-btn">Compte Athena</button>
                                <button type="button" id="enlist-btn-flow-guest" class="ce-flow-btn enlist-flow-btn">Invité (personnage ou identité réelle)</button>
                            </div>
                            <input type="hidden" name="enlistment_flow" id="enlistment_flow" value="account">
                        <?php else: ?>
                            <input type="hidden" name="enlistment_flow" id="enlistment_flow" value="guest">
                            <p class="ce-help">Choisissez plus bas si le dossier est porté par un <strong>personnage</strong> ou par votre <strong>identité réelle</strong> (contact administratif).</p>
                        <?php endif; ?>

                        <?php if ($canUseAccount): ?>
                            <div id="enlist-account-panel" class="ce-panel">
                                <p class="ce-panel__title">Envoi avec le compte connecté</p>
                                <div class="ce-check-list">
                                    <label class="ce-check">
                                        <input type="checkbox" name="share_email" value="1" checked>
                                        <span>Partager mon <strong>e-mail</strong> de connexion</span>
                                    </label>
                                    <label class="ce-check">
                                        <input type="checkbox" name="share_name" value="1" checked>
                                        <span>Partager mon <strong>nom</strong> (profil)</span>
                                    </label>
                                    <label class="ce-check">
                                        <input type="checkbox" name="share_callsign" value="1">
                                        <span>Partager mon <strong>indicatif</strong> du profil</span>
                                    </label>
                                </div>
                                <?php if (!empty($recruitmentPresets)): ?>
                                    <div style="margin-top:1rem">
                                        <label class="ce-label" for="recruitment_preset_select">Profil enregistré (optionnel)</label>
                                        <select name="recruitment_preset_id" class="input-field" id="recruitment_preset_select">
                                            <option value="">— Aucun —</option>
                                            <?php foreach ($recruitmentPresets as $rp): ?>
                                                <?php
                                                $pid = (int) ($rp['id'] ?? 0);
                                                $pl = (string) ($rp['label'] ?? '');
                                                $pay = $rp['payload'] ?? [];
                                                if (!is_array($pay)) {
                                                    $pay = [];
                                                }
                                                ?>
                                                <option value="<?= $pid ?>" data-payload="<?= htmlspecialchars(json_encode($pay, JSON_UNESCAPED_UNICODE)) ?>"><?= htmlspecialchars($pl) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php
                                    $rpShareLabels = [
                                        'identity' => 'Identité personnage (prénom, nom, naissance, nationalité)',
                                        'character_name' => 'Nom de scène (optionnel)',
                                        'bio' => 'Biographie',
                                        'cv' => 'Parcours',
                                        'image_url' => 'Portrait enregistré',
                                        'image_external_url' => 'Lien vers un portrait',
                                        'admin_notes' => 'Notes personnelles du profil',
                                        'availability' => 'Synthèse des disponibilités',
                                    ];
                                    ?>
                                    <div id="enlist-rp-share-panel" class="hidden" style="margin-top:1rem">
                                        <p class="ce-panel__title">Contenu du profil transmis au recrutement</p>
                                        <p class="ce-help">Ne cochez que ce que vous acceptez d’ajouter à ce dossier. Le reste reste sur votre compte.</p>
                                        <div class="ce-check-list">
                                        <?php foreach ($rpShareLabels as $shareKey => $shareLabel): ?>
                                            <label class="ce-check">
                                                <input type="checkbox" name="share_rp_<?= htmlspecialchars($shareKey) ?>" value="1" checked disabled class="enlist-rp-share-cb">
                                                <span><?= htmlspecialchars($shareLabel) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                            <label class="ce-check">
                                                <input type="checkbox" name="include_milsim_from_preset" value="1" checked disabled class="enlist-include-milsim-cb">
                                                <span>Inclure aussi les réponses techniques enregistrées dans ce modèle (matériel, créneaux, motivation, etc.).</span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="ce-consent">
                                    <label class="ce-check">
                                        <input type="checkbox" name="consent_data_sharing" value="1" id="consent_data_sharing">
                                        <span>J’accepte que les informations cochées et le contenu de ce dossier soient transmis à l’équipe de <strong><?= htmlspecialchars($tenantName) ?></strong>.</span>
                                    </label>
                                </div>
                                <p class="ce-help" style="margin-top:0.85rem;margin-bottom:0">Les champs ci-dessous complètent votre profil pour le recrutement.</p>
                            </div>
                        <?php endif; ?>

                        <?php if ($compactAccountOpening): ?>
                            <div id="enlist-compact-toolbar" class="ce-banner" style="margin-bottom:1.25rem">
                                <p class="ce-banner__kicker">Parcours court</p>
                                <p class="ce-banner__text">Seuls les éléments utiles à une candidature ciblée sont affichés. Vous pouvez à tout moment compléter le dossier comme pour une première inscription.</p>
                                <p style="margin-top:0.85rem">
                                    <button type="button" id="enlist-btn-expand-full" class="ce-btn ce-btn--ghost">Fournir le questionnaire complet</button>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div id="enlist-guest-identity" class="<?= $canUseAccount ? 'hidden' : '' ?>" <?= $canUseAccount ? 'style="display:none"' : '' ?>>
                            <p class="ce-panel__title">Identité portée par la candidature</p>
                            <div class="ce-identity">
                                <label class="ce-check">
                                    <input type="radio" name="identity_kind" value="admin" checked>
                                    <span>Identité réelle (dossier administratif)</span>
                                </label>
                                <label class="ce-check">
                                    <input type="radio" name="identity_kind" value="rp">
                                    <span>Personnage (univers fictionnel)</span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="ce-form-section" id="ce-sec-identity">
                        <h3 class="ce-section-title"><?= htmlspecialchars((string) $p['section_1']) ?></h3>
                        <div id="enlist-guest-names" class="ce-field-grid <?= $canUseAccount ? 'hidden' : '' ?>" <?= $canUseAccount ? 'style="display:none"' : '' ?>>
                            <div class="space-y-2 ce-span-2">
                                <label id="label-full-name" class="ce-label"><?= htmlspecialchars($fld('full_name')['label']) ?></label>
                                <input type="text" name="full_name" id="input-full-name" class="input-field track-field guest-req-field" placeholder="<?= htmlspecialchars($fld('full_name')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['full_name']) ?>"
                                    autocomplete="name">
                            </div>
                            <div id="legal-full-row" class="space-y-2 ce-span-2 hidden">
                                <label class="ce-label"><?= htmlspecialchars($fld('legal_full_name')['label']) ?></label>
                                <input type="text" name="legal_full_name" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('legal_full_name')['placeholder']) ?>" autocomplete="name">
                            </div>
                            <div id="guest-rp-detail" class="hidden ce-span-2 ce-rp-box space-y-4">
                                <p class="ce-panel__title">Identité personnage (optionnel si le champ unique ci-dessus suffit)</p>
                                <div class="ce-field-grid">
                                    <div class="space-y-2">
                                        <label class="ce-label">Prénom (personnage)</label>
                                        <input type="text" name="guest_rp_first_name" class="input-field track-field" maxlength="100" autocomplete="off">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="ce-label">Nom (personnage)</label>
                                        <input type="text" name="guest_rp_last_name" class="input-field track-field" maxlength="100" autocomplete="off">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="ce-label">Date de naissance (personnage)</label>
                                        <input type="date" name="guest_rp_birth_date" class="input-field track-field" autocomplete="off">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="ce-label">Nationalité (personnage)</label>
                                        <input type="text" name="guest_rp_nationality" class="input-field track-field" maxlength="100" autocomplete="off">
                                    </div>
                                    <div class="space-y-2 ce-span-2">
                                        <label class="ce-label">Nom de scène (optionnel)</label>
                                        <input type="text" name="guest_rp_scene_name" class="input-field track-field" maxlength="150" autocomplete="off" placeholder="Surcharge du libellé si renseigné">
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-2 ce-span-2">
                                <label class="ce-label"><?= htmlspecialchars($fld('email')['label']) ?></label>
                                <input type="email" name="email" id="input-email" class="input-field track-field guest-req-field" placeholder="<?= htmlspecialchars($fld('email')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['email']) ?>"
                                    autocomplete="email">
                            </div>
                        </div>
                        <div class="enlist-full-only ce-field-grid" style="margin-top:1.1rem">
                            <div class="space-y-2">
                                <label class="ce-label"><?= htmlspecialchars($fld('age')['label']) ?></label>
                                <input type="number" name="age" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('age')['placeholder']) ?>" min="16" max="99"
                                    value="<?= htmlspecialchars($prefill['age']) ?>">
                            </div>
                            <div class="space-y-2">
                                <label class="ce-label"><?= htmlspecialchars($fld('timezone')['label']) ?></label>
                                <input type="text" name="timezone" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('timezone')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['timezone']) ?>">
                            </div>
                            <div class="space-y-2 ce-span-2">
                                <label class="ce-label"><?= htmlspecialchars($fld('weekly_availability')['label']) ?></label>
                                <input type="text" name="weekly_availability" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('weekly_availability')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['weekly_availability']) ?>">
                            </div>
                            <div class="space-y-2 ce-span-2">
                                <label class="ce-label"><?= htmlspecialchars($fld('callsign')['label']) ?></label>
                                <input type="text" name="callsign" class="input-field track-field" placeholder="<?= htmlspecialchars($fld('callsign')['placeholder']) ?>"
                                    value="<?= htmlspecialchars($prefill['callsign']) ?>">
                            </div>
                        </div>
                    </section>

                    <section class="ce-form-section enlist-full-only" id="ce-sec-gear">
                        <h3 class="ce-section-title"><?= htmlspecialchars((string) $p['section_2']) ?></h3>
                        <div class="space-y-6">
                            <?php $fieldName = 'system_config'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            <div class="ce-field-grid">
                                <?php $fieldName = 'microphone_quality'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                                <?php $fieldName = 'ace_acre_level'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            </div>
                            <?php $fieldName = 'past_milsim_experience'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                        </div>
                    </section>

                    <section class="ce-form-section" id="ce-sec-motivation">
                        <h3 class="ce-section-title"><?= htmlspecialchars((string) $p['section_3']) ?></h3>
                        <div class="space-y-6">
                            <?php $fieldName = 'motivation_why_join'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            <div class="enlist-full-only">
                                <?php $fieldName = 'motivation_accountability'; include base_path('views/partials/enlistment_milsim_widget.php'); ?>
                            </div>
                        </div>
                    </section>

                    <section class="ce-form-section enlist-full-only" id="ce-sec-commit">
                        <h3 class="ce-section-title"><?= htmlspecialchars((string) $p['section_4']) ?></h3>
                        <div class="ce-commit-row">
                            <span><?= htmlspecialchars((string) $p['commitment_q13']) ?></span>
                            <select name="commitment_effort" class="input-field track-field">
                                <option value="">Sélectionner</option>
                                <option value="Oui">Oui</option>
                                <option value="Non">Non</option>
                            </select>
                        </div>
                        <div class="ce-commit-row">
                            <span><?= htmlspecialchars((string) $p['availability_q15']) ?></span>
                            <select name="availability_wed_sat" class="input-field track-field">
                                <option value="">Sélectionner</option>
                                <option value="Oui">Oui</option>
                                <option value="Non">Non</option>
                                <option value="Variable">Variable</option>
                            </select>
                        </div>
                    </section>

                    <!-- Code d'invitation (optionnel) -->
                    <div class="ce-field-group">
                        <label for="invite-code" class="ce-label">
                            Code d'invitation (optionnel)
                            <span class="ce-label-hint">Si vous avez reçu un code d'invitation, saisissez-le ici pour accélérer votre candidature</span>
                        </label>
                        <input type="text" 
                               name="invite_code" 
                               id="invite-code" 
                               class="ce-input"
                               placeholder="Ex: MIGRATION2026"
                               maxlength="64"
                               pattern="[A-Z0-9\-_]*"
                               style="text-transform: uppercase;">
                    </div>

                    <div class="ce-submit-zone">
                        <?php if ($requireAiAck): ?>
                            <div class="ce-ai-ack">
                                <input type="checkbox" name="no_ai_confirmed" id="no-ai-check" value="1" class="track-field">
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
        </div>

        <footer class="ce-foot">
            <div class="ce-wrap">
                <a href="<?= htmlspecialchars($showcaseUrl) ?>">Retour à la vitrine <?= htmlspecialchars($tenantName) ?></a>
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

        (function enlistmentFlowUi() {
            var form = document.getElementById('recruitment-form');
            if (!form) return;
            var canUseAccount = form.getAttribute('data-can-use-account') === '1';
            var compactOpening = form.getAttribute('data-compact-opening') === '1';
            var flowInput = document.getElementById('enlistment_flow');
            var modeInput = document.getElementById('enlistment_form_mode');
            var accPanel = document.getElementById('enlist-account-panel');
            var guestId = document.getElementById('enlist-guest-identity');
            var guestNames = document.getElementById('enlist-guest-names');
            var btnAcc = document.getElementById('enlist-btn-flow-account');
            var btnGuest = document.getElementById('enlist-btn-flow-guest');
            var btnExpand = document.getElementById('enlist-btn-expand-full');
            var legalRow = document.getElementById('legal-full-row');
            var guestRpDetail = document.getElementById('guest-rp-detail');
            var labelFull = document.getElementById('label-full-name');
            var LABEL_ADMIN = <?= json_encode($fld('full_name')['label'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            var LABEL_RP = 'Nom du personnage';

            function setGuestFieldsRequired(isGuest) {
                document.querySelectorAll('.guest-req-field').forEach(function(el) {
                    el.required = !!isGuest;
                });
            }

            function syncMotivationRequired() {
                var ta = document.querySelector('textarea[name="motivation_why_join"]');
                if (!ta) return;
                var flow = flowInput ? flowInput.value : 'guest';
                var compact = form.classList.contains('enlist-compact-default') && !form.classList.contains('enlist-compact-expanded');
                ta.required = (flow === 'account' && compact);
            }

            function syncRpSharePanel() {
                var sel = document.getElementById('recruitment_preset_select');
                var panel = document.getElementById('enlist-rp-share-panel');
                if (!panel) return;
                var guest = flowInput && flowInput.value === 'guest';
                var picked = !guest && sel && sel.value && String(sel.value).length > 0;
                panel.classList.toggle('hidden', !picked);
                panel.querySelectorAll('input').forEach(function(inp) {
                    inp.disabled = !picked;
                });
            }

            function syncIdentityKind() {
                var rp = false;
                document.querySelectorAll('input[name="identity_kind"]').forEach(function(r) {
                    if (r.checked) rp = (r.value === 'rp');
                });
                if (legalRow) {
                    legalRow.classList.toggle('hidden', !rp);
                }
                if (guestRpDetail) {
                    guestRpDetail.classList.toggle('hidden', !rp);
                }
                if (labelFull) {
                    labelFull.textContent = rp ? LABEL_RP : LABEL_ADMIN;
                }
            }

            function setFlow(f) {
                if (!flowInput) return;
                flowInput.value = f;
                var guest = (f === 'guest');
                if (canUseAccount) {
                    if (accPanel) accPanel.style.display = guest ? 'none' : '';
                    if (guestId) { guestId.style.display = guest ? '' : 'none'; guestId.classList.toggle('hidden', !guest); }
                    if (guestNames) { guestNames.style.display = guest ? '' : 'none'; guestNames.classList.toggle('hidden', !guest); }
                    if (btnAcc && btnGuest) {
                        btnAcc.classList.toggle('is-active', !guest);
                        btnGuest.classList.toggle('is-active', guest);
                    }
                    document.querySelectorAll('#enlist-account-panel input, #enlist-account-panel select').forEach(function(el) {
                        if (el.type === 'button') return;
                        el.disabled = guest;
                    });
                    document.querySelectorAll('input[name="identity_kind"]').forEach(function(r) {
                        r.disabled = !guest;
                    });
                }
                if (guest) {
                    form.classList.add('enlist-compact-expanded');
                    if (modeInput) modeInput.value = 'full';
                } else if (compactOpening) {
                    form.classList.remove('enlist-compact-expanded');
                    if (modeInput) modeInput.value = 'compact';
                } else {
                    form.classList.remove('enlist-compact-expanded');
                    if (modeInput) modeInput.value = 'full';
                }
                setGuestFieldsRequired(guest || !canUseAccount);
                syncRpSharePanel();
                syncMotivationRequired();
            }

            if (canUseAccount && btnAcc && btnGuest) {
                btnAcc.addEventListener('click', function() { setFlow('account'); });
                btnGuest.addEventListener('click', function() { setFlow('guest'); });
                setFlow(flowInput && flowInput.value === 'guest' ? 'guest' : 'account');
            } else {
                setGuestFieldsRequired(true);
                syncRpSharePanel();
                syncMotivationRequired();
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

            document.querySelectorAll('input[name="identity_kind"]').forEach(function(r) {
                r.addEventListener('change', syncIdentityKind);
            });
            syncIdentityKind();

            var presetSel = document.getElementById('recruitment_preset_select');
            if (presetSel) {
                presetSel.addEventListener('change', function(ev) {
                    syncRpSharePanel();
                    var opt = ev.target.selectedOptions[0];
                    var raw = opt && opt.getAttribute('data-payload');
                    if (!raw) return;
                    try {
                        var payload = JSON.parse(raw);
                        var mo = document.querySelector('textarea[name="motivation_why_join"]');
                        if (mo && payload.motivation_why_join) mo.value = payload.motivation_why_join;
                        var cs = document.querySelector('input[name="callsign"]');
                        if (cs && payload.callsign) cs.value = payload.callsign;
                        var av = document.querySelector('input[name="weekly_availability"]');
                        if (av && payload.availability) av.value = payload.availability;
                    } catch (e) {}
                });
            }

            form.addEventListener('submit', function(ev) {
                var flow = flowInput ? flowInput.value : 'guest';
                if (flow === 'guest') {
                    setGuestFieldsRequired(true);
                } else {
                    setGuestFieldsRequired(false);
                }
                syncMotivationRequired();
                if (canUseAccount && flow === 'account') {
                    var c = document.getElementById('consent_data_sharing');
                    if (c && !c.checked) {
                        ev.preventDefault();
                        alert('Veuillez accepter le partage des données avec l’équipe de recrutement.');
                    }
                }
            });
        })();
    </script>
    <script src="<?= htmlspecialchars($jsHref) ?>" defer></script>
    <?php require base_path('views/partials/analytics_beacon.php'); ?>
</body>
</html>
