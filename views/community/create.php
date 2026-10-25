<?php
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');
$warning = \App\Core\Session::getFlash('warning');
$stripeConfigured = $stripeConfigured ?? false;
/** @var list<array<string,mixed>> $gradesFr */
$gradesFr = $gradesFr ?? [];
/** @var list<array<string,mixed>> $gradesUs */
$gradesUs = $gradesUs ?? [];
$defaultWizardUnitsJson = $defaultWizardUnitsJson ?? '[]';
$quickUnitsArr = json_decode($defaultWizardUnitsJson, true);
if (!is_array($quickUnitsArr)) {
    $quickUnitsArr = [];
}
$zones = \DateTimeZone::listIdentifiers();
$gradesFrGrouped = $gradesFrGrouped ?? [];
$gradesUsGrouped = $gradesUsGrouped ?? [];
$badgeLabels = $badgeLabels ?? \App\Services\Community\TenantCommunityProfileService::badgeLabels();
$wizardPermissionGroups = $wizardPermissionGroups ?? \App\Services\Community\CommunityOnboardingValidationService::wizardPermissionFieldGroups();
$tenantTypes = \App\Services\Community\TenantTypeConfig::availableTypes();
$subscriptionOfferCards = is_array($subscriptionOfferCards ?? null) ? $subscriptionOfferCards : [];
$realUnitCatalogJson = json_encode(
    \App\Services\Community\RealUnitAffiliationCatalog::frontendPayload(),
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
);
$baseUrl = url('');
$communityCreateDraft = is_array($communityCreateDraft ?? null) ? $communityCreateDraft : [];
$onboardingStepKey = is_string($onboardingStep ?? null) ? strtolower(trim($onboardingStep)) : '';
$wizardStepMap = ['identity' => 1, 'organization' => 3, 'roles' => 3, 'grades' => 4, 'review' => 5];
$resumeWizardStep = $wizardStepMap[$onboardingStepKey] ?? 1;
if ($resumeWizardStep < 1 || $resumeWizardStep > 5) {
    $resumeWizardStep = 1;
}

$wizardSteps = [
    1 => 'Identité',
    2 => 'Type',
    3 => 'Organisation',
    4 => 'Grades',
    5 => 'Accès & offre',
];
/** @var list<array<string,mixed>> $orbatPresets */
$orbatPresets = is_array($orbatPresets ?? null) ? $orbatPresets : \App\Services\Community\WizardOrbatPresets::forWizard();
$officialKits = \App\Services\OrganizationCatalog\OrganizationKitDefinitions::officialKits();
$jobDoctrineKits = \App\Services\OrganizationCatalog\OrganizationKitDefinitions::jobDoctrineCodes();
$guideUrl = url('communities/create/guide');
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/community-create.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="community-create">
    <div class="cc-shell">
        <header class="cc-hero">
            <div class="cc-hero__inner">
                <p class="cc-brand">Athena<span>.</span></p>
                <p class="cc-kicker">Nouvelle communauté</p>
                <p class="cc-lead">Donnez un nom, choisissez le profil adapté, préparez l’organisation, puis ouvrez les candidatures. Cinq étapes, une seule décision à la fois.</p>
                <div class="cc-hero__actions">
                    <button type="button" class="cc-hero__btn" data-cc-tutorial-open>Tutoriel en 2 minutes</button>
                    <a class="cc-hero__link" href="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Guide complet des champs</a>
                </div>
                <p class="cc-hero__meta">Comptez 5 à 10 minutes. Seul le nom est obligatoire : tout le reste a une valeur par défaut et se modifie ensuite dans le back-office.</p>
            </div>
        </header>

        <?php if ($error): ?>
        <div class="cc-flash cc-flash--error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($warning): ?>
        <div class="cc-flash cc-flash--error" role="alert"><?= htmlspecialchars($warning) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="cc-flash cc-flash--ok"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="cc-board">
            <nav class="cc-stepper" aria-label="Étapes de création">
                <?php foreach ($wizardSteps as $i => $label): ?>
                <button type="button" class="cc-stepper__btn wizard-step-tab" data-step-tab="<?= (int) $i ?>" data-active="<?= $i === 1 ? '1' : '0' ?>" data-done="0">
                    <span class="cc-stepper__num">Étape <?= (int) $i ?></span>
                    <span class="cc-stepper__label"><?= htmlspecialchars($label) ?></span>
                </button>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="<?= url('communities/create') ?>" enctype="multipart/form-data" class="cc-form space-y-0" id="community-create-form" novalidate>
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="wizard_units_json" id="wizard-units-json" value="<?= htmlspecialchars($defaultWizardUnitsJson, ENT_QUOTES, 'UTF-8') ?>">

                <?php /* ——— 1. Identité ——— */ ?>
                <div class="wizard-panel" data-step="1">
                    <div class="cc-panel-head">
                        <p class="cc-panel-head__eyebrow">Étape 1 sur 5</p>
                        <h1 class="cc-panel-head__title">Identité de la communauté</h1>
                        <p class="cc-panel-head__text">Le nom public, la langue et le fuseau horaire. L’adresse web peut être générée automatiquement ou choisie par vous.</p>
                    </div>

                    <aside class="cc-tip" aria-label="Conseils pour cette étape">
                        <p class="cc-tip__title">À retenir</p>
                        <ul>
                            <li>Le <strong>nom affiché</strong> est le seul champ obligatoire de tout l’assistant.</li>
                            <li>L’adresse web est la seule donnée difficile à changer ensuite : prenez une forme courte et stable.</li>
                            <li>La vitrine publique peut rester vide : vous la compléterez dans <em>Paramètres de la communauté</em>.</li>
                        </ul>
                    </aside>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Nom et adresse</h2>
                        <p class="cc-section__text">Ce que les visiteurs et les membres verront en premier sur Athena.</p>
                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="cc-label" for="cc-name">Nom affiché</label>
                                <input id="cc-name" type="text" name="name" required maxlength="255" class="cc-field" placeholder="92e Régiment d’infanterie" aria-describedby="cc-name-error cc-slug-preview">
                                <p id="cc-name-error" class="cc-error hidden" role="alert">Indiquez le nom de la communauté pour continuer.</p>
                                <p class="cc-hint" id="cc-slug-preview">Adresse publique : <span class="font-mono" data-slug-preview><?= htmlspecialchars(rtrim(url('c/'), '/'), ENT_QUOTES, 'UTF-8') ?>/…</span></p>
                                <details class="cc-help">
                                    <summary>Comment bien choisir le nom&nbsp;?</summary>
                                    <p>C’est le titre de la fiche registre, de la page publique, des e-mails et de la fenêtre de connexion Overwatch. Préférez le nom sous lequel vos joueurs vous connaissent déjà (ex. «&nbsp;Task Force Phoenix&nbsp;», «&nbsp;2e Compagnie — 13e BCA&nbsp;»). Il reste modifiable dans le back-office.</p>
                                </details>
                            </div>
                            <div class="md:col-span-2">
                                <label class="cc-checkrow">
                                    <input type="checkbox" name="wizard_custom_community_slug" value="1" id="wizard-custom-community-slug">
                                    <span>
                                        <strong>Choisir une adresse web personnalisée</strong>
                                        <span>Sinon, Athena dérive l’adresse du nom affiché (lettres minuscules et tirets).</span>
                                    </span>
                                </label>
                                <div id="wizard-community-slug-wrap" class="mt-3 hidden">
                                    <label class="cc-label" for="wizard-community-slug-input">Adresse courte dans le lien public</label>
                                    <input type="text" name="slug" id="wizard-community-slug-input" pattern="[a-z0-9]([-a-z0-9]*[a-z0-9])?" class="cc-field font-mono" placeholder="ex. mon-unite">
                                    <p class="cc-hint">Lettres minuscules, chiffres et tirets uniquement. Exemple&nbsp;: <span class="font-mono">tf-phoenix</span>. Évitez les dates et les numéros de saison : l’adresse figure dans les liens partagés sur Discord et dans les invitations.</p>
                                </div>
                            </div>
                            <div>
                                <label class="cc-label" for="cc-locale">Langue par défaut</label>
                                <select id="cc-locale" name="wizard_default_locale" class="cc-select">
                                    <option value="fr" selected>Français</option>
                                    <option value="en">English</option>
                                    <option value="ru">Русский</option>
                                    <option value="es">Español</option>
                                </select>
                                <p class="cc-hint">Langue de travail de l’unité (ORBAT, annuaire). Le site s’affiche en français ou en anglais, au choix de chaque membre.</p>
                            </div>
                            <div>
                                <label class="cc-label" for="cc-tz">Fuseau horaire</label>
                                <select id="cc-tz" name="wizard_timezone" class="cc-select">
                                    <?php foreach ($zones as $z): ?>
                                    <option value="<?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?>" <?= $z === 'Europe/Paris' ? 'selected' : '' ?>><?= htmlspecialchars($z, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="cc-hint">Sert à afficher les heures des opérations, des rappels et des pointages. Choisissez celui de la majorité de vos joueurs.</p>
                            </div>
                        </div>
                    </section>

                    <section class="cc-section" id="unit-affiliation-section">
                        <h2 class="cc-section__title">Représentation de l’unité</h2>
                        <p class="cc-section__text">Aide les visiteurs à comprendre si votre communauté s’inspire d’une unité réelle ou d’un cadre fictif.</p>

                        <div class="mt-5">
                            <p class="cc-label">Votre communauté représente-t-elle une unité réelle&nbsp;?</p>
                            <div class="cc-choice-grid cc-choice-grid--2 mt-3">
                                <label class="cc-choice">
                                    <input type="radio" name="wizard_represents_real_unit" value="1" class="sr-only" data-unit-affiliation-mode="real">
                                    <span class="cc-choice__title">Oui</span>
                                    <span class="cc-choice__text">Unité, régiment ou composante existante (forces spéciales, troupes aéroportées, infanterie…).</span>
                                </label>
                                <label class="cc-choice">
                                    <input type="radio" name="wizard_represents_real_unit" value="0" class="sr-only" data-unit-affiliation-mode="fictional">
                                    <span class="cc-choice__title">Non</span>
                                    <span class="cc-choice__text">Cadre fictif, inspiré ou original.</span>
                                </label>
                            </div>
                        </div>

                        <div id="unit-affiliation-fictional" class="mt-5 hidden">
                            <label class="cc-label" for="wizard-fictional-unit-label">Quelle unité fictive représentez-vous&nbsp;?</label>
                            <input type="text" name="wizard_fictional_unit_label" id="wizard-fictional-unit-label" maxlength="200" class="cc-field" placeholder="ex. Task Force Phoenix, 1er régiment fictif…">
                            <p class="cc-hint">Nom tel qu’il apparaîtra sur la fiche registre.</p>
                        </div>

                        <div id="unit-affiliation-real" class="mt-5 hidden space-y-4">
                            <div>
                                <label class="cc-label" for="wizard-real-unit-country">Pays de rattachement</label>
                                <select name="wizard_real_unit_country" id="wizard-real-unit-country" class="cc-select max-w-md">
                                    <option value="">— Choisir un pays —</option>
                                    <?php foreach (\App\Services\Community\RealUnitAffiliationCatalog::countryLabels() as $code => $label): ?>
                                    <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="cc-hint">Vous pouvez sélectionner plusieurs unités, mais uniquement au sein d’un même pays.</p>
                            </div>

                            <details class="cc-help">
                                <summary>Pourquoi déclarer une unité réelle&nbsp;?</summary>
                                <p>L’unité apparaît sur la fiche registre et aide les candidats à trouver une communauté qui joue «&nbsp;leur&nbsp;» unité. C’est une indication d’inspiration, pas une affiliation officielle. Votre unité manque&nbsp;? Choisissez «&nbsp;Non&nbsp;» et saisissez son nom comme unité fictive.</p>
                            </details>

                            <div id="unit-affiliation-real-picker" class="hidden">
                                <label class="cc-label" for="wizard-real-unit-search">Rechercher une unité</label>
                                <input type="search" id="wizard-real-unit-search" class="cc-field" placeholder="Ex. Hubert, 1RPIMA, USASOC, plongée…" autocomplete="off">
                                <p class="cc-hint mt-1">Liste issue du référentiel militaire (commandements, composantes, régiments, commandos…). La recherche tolère les accents et inclut les sigles, surnoms et anciens noms (ex. «&nbsp;Green Berets&nbsp;», «&nbsp;BFST&nbsp;»).</p>
                                <div class="cc-unit-affiliation-list mt-3" id="wizard-real-unit-list" role="group" aria-label="Unités de forces spéciales"></div>

                                <p class="cc-hint mt-2" id="wizard-real-unit-selection-summary">Aucune unité sélectionnée.</p>
                            </div>
                        </div>
                    </section>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Présentation publique</h2>
                        <p class="cc-section__text">Optionnel pour démarrer. Vous pourrez tout affiner ensuite depuis la fiche registre.</p>

                        <details class="mt-5 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 open:bg-white">
                            <summary class="cursor-pointer text-sm font-bold text-slate-900">Renseigner la vitrine maintenant</summary>
                            <div class="mt-4 space-y-5">
                                <div class="cc-soft space-y-3">
                                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-900">Page publique</p>
                                    <div>
                                        <label class="cc-label">Modèle de page</label>
                                        <select name="wizard_public_page_layout" class="cc-select max-w-md">
                                            <option value="showcase" selected>Vitrine (pleine page)</option>
                                            <option value="legacy">Classique (carte)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="cc-label">Accroche sous le titre</label>
                                        <textarea name="wizard_public_hero_subtitle" rows="2" maxlength="600" class="cc-area" placeholder="Une phrase d’introduction claire…"></textarea>
                                    </div>
                                    <div>
                                        <label class="cc-label">Doctrine (court)</label>
                                        <input type="text" name="wizard_public_doctrine" maxlength="200" class="cc-field" placeholder="ex. Commandement interarmes">
                                    </div>
                                </div>

                                <div>
                                    <p class="cc-label">Badges de style</p>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($badgeLabels as $slug => $blab): ?>
                                        <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                            <input type="checkbox" name="wizard_style_badges[]" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" class="rounded border-slate-300 text-emerald-600">
                                            <?= htmlspecialchars($blab, ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="cc-label">Mode de présentation</label>
                                        <select name="wizard_presentation_mode" class="cc-select">
                                            <option value="simple" selected>Texte simple</option>
                                            <option value="military">Sections type militaire</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="cc-label">Jeu / théâtre</label>
                                        <input type="text" name="wizard_game_label" maxlength="120" class="cc-field" placeholder="Arma 3, Squad…">
                                    </div>
                                </div>
                                <div>
                                    <label class="cc-label">Bio courte du hero</label>
                                    <textarea name="wizard_simple_body" rows="2" maxlength="8000" class="cc-area" placeholder="Quelques lignes pour présenter l’esprit de la communauté dès l’arrivée sur la page…"></textarea>
                                    <p class="cc-hint">Texte court affiché dans le bandeau d’accueil.</p>
                                </div>
                                <div>
                                    <label class="cc-label">Qui sommes-nous ?</label>
                                    <textarea name="wizard_public_about_body" rows="5" maxlength="8000" class="cc-area" placeholder="Présentez votre histoire, votre cadre de jeu, votre manière d’accueillir et ce qui vous distingue."></textarea>
                                    <p class="cc-hint">Cette zone sert au texte de présentation plus détaillé sous le bandeau.</p>
                                </div>
                                <div>
                                    <label class="cc-label">Attentes / mot d’ordre</label>
                                    <textarea name="wizard_expectations" rows="2" maxlength="8000" class="cc-area" placeholder="Disponibilité, esprit d’équipe…"></textarea>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="cc-file-card space-y-3">
                                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Logo</p>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-slate-700">Envoyer une image</span>
                                            <input type="file" name="wizard_logo_file" accept="image/jpeg,image/png,image/webp,image/gif" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white file:uppercase file:tracking-wider hover:file:bg-emerald-600">
                                        </label>
                                        <p class="text-[11px] text-slate-500">JPEG, PNG, WebP ou GIF — max 3&nbsp;Mo.</p>
                                        <div class="wizard-img-preview mt-2 hidden overflow-hidden rounded-xl border border-slate-200 bg-white" data-preview-for="wizard_logo_file">
                                            <img src="" alt="" class="max-h-40 w-full object-contain">
                                        </div>
                                        <div class="border-t border-slate-200 pt-3">
                                            <label class="mb-1 block text-xs font-semibold text-slate-600">Ou lien externe</label>
                                            <input type="url" name="wizard_logo_url" maxlength="500" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm" placeholder="https://…">
                                        </div>
                                    </div>
                                    <div class="cc-file-card space-y-3">
                                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Bannière</p>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-slate-700">Envoyer une image</span>
                                            <input type="file" name="wizard_public_banner_file" accept="image/jpeg,image/png,image/webp,image/gif" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white file:uppercase file:tracking-wider hover:file:bg-emerald-600">
                                        </label>
                                        <p class="text-[11px] text-slate-500">Image large recommandée (paysage).</p>
                                        <div class="wizard-img-preview mt-2 hidden overflow-hidden rounded-xl border border-slate-200 bg-slate-900/5" data-preview-for="wizard_public_banner_file">
                                            <img src="" alt="" class="max-h-48 w-full object-cover">
                                        </div>
                                        <div class="border-t border-slate-200 pt-3">
                                            <label class="mb-1 block text-xs font-semibold text-slate-600">Ou lien externe</label>
                                            <input type="url" name="wizard_public_banner_url" maxlength="500" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm" placeholder="https://…">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </section>
                </div>

                <?php /* ——— 2. Type ——— */ ?>
                <div class="wizard-panel hidden" data-step="2">
                    <div class="cc-panel-head">
                        <p class="cc-panel-head__eyebrow">Étape 2 sur 5</p>
                        <h1 class="cc-panel-head__title">Type de communauté</h1>
                        <p class="cc-panel-head__text">Chaque profil ouvre un périmètre d’outils différent. Vous pourrez faire évoluer l’organisation plus tard ; le type fixe le point de départ.</p>
                    </div>

                    <aside class="cc-tip" aria-label="Conseils pour cette étape">
                        <p class="cc-tip__title">À retenir</p>
                        <ul>
                            <li><strong>Complet</strong> convient à la plupart des unités MilSim : forum, formations, recrutement, effectifs, opérations et carte.</li>
                            <li><strong>Bureau des effectifs</strong> et <strong>Carte ATAK</strong> masquent des modules pour garder une interface simple.</li>
                            <li>Le type se change plus tard : les données déjà saisies ne sont jamais supprimées, seulement masquées.</li>
                            <li>Les membres arrivent au niveau d’accès <strong>Membre</strong> : ils utilisent les outils sans rien administrer. Vous restez <strong>Gestionnaire</strong>.</li>
                            <li><a href="<?= htmlspecialchars(url('documentation/profils'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Comparer les profils en détail</a></li>
                        </ul>
                    </aside>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Quel usage visez-vous&nbsp;?</h2>
                        <p class="cc-section__text">Choisissez le cadre le plus proche de votre besoin réel — pas le plus large « au cas où ».</p>
                        <div class="cc-choice-grid cc-choice-grid--3 mt-5">
                            <?php foreach ($tenantTypes as $typeSlug => $typeInfo): ?>
                            <label class="cc-choice">
                                <input type="radio" name="tenant_type" value="<?= htmlspecialchars($typeSlug, ENT_QUOTES, 'UTF-8') ?>" class="sr-only" <?= $typeSlug === 'full' ? 'checked' : '' ?>>
                                <span class="cc-choice__eyebrow">Profil</span>
                                <span class="cc-choice__title"><?= htmlspecialchars($typeInfo['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="cc-choice__text"><?= htmlspecialchars($typeInfo['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php
                                $ccTypeModules = \App\Services\Community\TenantTypeConfig::allowedModulesByType()[$typeSlug] ?? [];
                                $ccModuleLabels = [];
                                foreach (\App\Services\Community\TenantTypeConfig::moduleCatalog() as $ccMod => $ccMeta) {
                                    if (in_array($ccMod, $ccTypeModules, true)) {
                                        $ccModuleLabels[] = $ccMeta['label'];
                                    }
                                }
                                ?>
                                <span class="cc-choice__note">Inclus : <?= htmlspecialchars(implode(' · ', $ccModuleLabels), ENT_QUOTES, 'UTF-8') ?>.</span>
                                <span class="cc-choice__note"><?= htmlspecialchars(\App\Services\Community\TenantTypeConfig::profileGuide($typeSlug)['ideal_for'][0] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="cc-soft mt-5 hidden" data-cc-type-note="effectifs">
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-900">Bureau des effectifs : comment ça marche</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5 text-xs leading-relaxed text-slate-700">
                                <li><strong>Vous tenez le registre comme un tableur.</strong> Vos membres n’ont pas besoin de compte : vous créez leurs fiches, ou vous importez votre fichier Excel (une ligne par membre).</li>
                                <li><strong>Recrutement uniquement sur Discord.</strong> Pas de formulaire de candidature sur le site : le bouton de votre page publique ouvre votre serveur Discord. Une fois la recrue acceptée, vous ajoutez sa fiche.</li>
                                <li><strong>Seuls ceux qui tiennent les fiches se connectent</strong> (état-major, RH). Le menu de gestion ne montre que les outils du registre.</li>
                            </ul>
                        </div>
                        <div class="cc-soft cc-soft--muted mt-5 hidden" data-cc-type-note="atak">
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Carte ATAK : comment ça marche</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5 text-xs leading-relaxed text-slate-700">
                                <li>Vos joueurs créent un compte par invitation pour relier leur téléphone en jeu.</li>
                                <li><strong>Recrutement uniquement sur Discord</strong> : pas de formulaire de candidature sur le site, le bouton de votre page publique ouvre votre serveur.</li>
                                <li>Le menu de gestion ne garde que la carte, les terminaux, le matériel, les membres et le forum.</li>
                            </ul>
                        </div>
                    </section>
                </div>

                <?php /* ——— 3. Organisation (rôles + ORBAT) ——— */ ?>
                <div class="wizard-panel hidden" data-step="3">
                    <div class="cc-panel-head">
                        <p class="cc-panel-head__eyebrow">Étape 3 sur 5</p>
                        <h1 class="cc-panel-head__title">Organisation</h1>
                        <p class="cc-panel-head__text">Définissez les rôles de départ et la structure des unités. Tout restera modifiable dans l’administration après la création.</p>
                    </div>

                    <aside class="cc-tip" aria-label="Conseils pour cette étape">
                        <p class="cc-tip__title">Trois notions à ne pas confondre</p>
                        <ul>
                            <li><strong>Rôles</strong> : ce qu’un membre a le droit de faire sur le site (modérer, recruter, administrer).</li>
                            <li><strong>Fonctions</strong> (emplois) : le métier affiché dans le dossier — chef de groupe, infirmier, pilote. Elles ne donnent aucun droit.</li>
                            <li><strong>Unités</strong> : l’organigramme (ORBAT) dans lequel les membres sont affectés.</li>
                        </ul>
                    </aside>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Rôles et droits</h2>
                        <p class="cc-section__text">Ces modèles créent les profils de départ de votre organisation (commandement, ressources humaines, instruction, membres, invités et modération du forum). Vous pourrez les ajuster à tout moment après la création.</p>
                        <div class="mt-5 space-y-3">
                            <label class="cc-choice">
                                <input type="radio" name="wizard_roles_template" value="quick" class="sr-only" checked>
                                <span class="cc-choice__eyebrow">Recommandé</span>
                                <span class="cc-choice__title">Démarrage rapide</span>
                                <span class="cc-choice__text">Convient à la plupart des unités. Les droits sont équilibrés dès le départ&nbsp;: le modérateur du forum peut gérer les échanges courants, sans étendre sa portée à tout l’espace forum de l’organisation.</span>
                            </label>
                            <label class="cc-choice">
                                <input type="radio" name="wizard_roles_template" value="standard" class="sr-only">
                                <span class="cc-choice__eyebrow">Alternative</span>
                                <span class="cc-choice__title">Modération élargie</span>
                                <span class="cc-choice__text">Même profils de base, avec un droit supplémentaire&nbsp;: le modérateur du forum peut aussi intervenir sur l’espace forum de l’organisation (annonces, sections réservées, supervision plus large).</span>
                            </label>
                        </div>

                        <details class="mt-5 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                            <summary class="cursor-pointer text-sm font-bold text-slate-800">Comparer les rôles inclus</summary>
                            <p class="mt-3 text-xs leading-relaxed text-slate-600">Le compte fondateur reçoit le rôle «&nbsp;Fondateur&nbsp;». Les nouveaux membres reçoivent «&nbsp;Membre&nbsp;» une fois acceptés ; les visiteurs connectés non membres sont «&nbsp;Invités&nbsp;».</p>
                            <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50/70 px-3 py-2 text-xs leading-relaxed text-emerald-900 hidden" data-cc-type-note="effectifs"><strong>Bureau des effectifs :</strong> ces rôles ne concernent que les personnes qui se connectent (état-major, RH). Les membres dont vous tenez la fiche sans compte n’ont aucun rôle à gérer, et le recrutement se fait sur Discord, pas sur le site.</p>
                            <p class="mt-3 text-xs leading-relaxed text-slate-600">Aperçu des accès principaux. La seule différence entre les deux modèles concerne le rôle de modération du forum.</p>
                            <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                                <table class="min-w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-200 bg-slate-50">
                                            <th class="px-3 py-2 font-black text-slate-600">Rôle</th>
                                            <th class="px-3 py-2 font-black text-slate-600">Forum</th>
                                            <th class="px-3 py-2 font-black text-slate-600">Documents et formation</th>
                                            <th class="px-3 py-2 font-black text-slate-600">Particularité</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-slate-700">
                                        <tr><td class="px-3 py-2 font-semibold">Fondateur</td><td class="px-3 py-2">Accès complet</td><td class="px-3 py-2">Accès complet</td><td class="px-3 py-2">Pilotage de l’organisation</td></tr>
                                        <tr><td class="px-3 py-2 font-semibold">État-major</td><td class="px-3 py-2">Accès complet</td><td class="px-3 py-2">Accès complet</td><td class="px-3 py-2">Encadrement</td></tr>
                                        <tr><td class="px-3 py-2 font-semibold">Ressources humaines</td><td class="px-3 py-2">Lecture et sujets</td><td class="px-3 py-2">Consultation</td><td class="px-3 py-2">Effectifs et recrutement</td></tr>
                                        <tr><td class="px-3 py-2 font-semibold">Instructeur</td><td class="px-3 py-2">Participation</td><td class="px-3 py-2">Gestion des formations</td><td class="px-3 py-2">Instruction</td></tr>
                                        <tr><td class="px-3 py-2 font-semibold">Membre</td><td class="px-3 py-2">Participation</td><td class="px-3 py-2">Selon affectation</td><td class="px-3 py-2">Profil de base</td></tr>
                                        <tr><td class="px-3 py-2 font-semibold">Invité</td><td class="px-3 py-2">Lecture seule</td><td class="px-3 py-2">Sans accès dédié</td><td class="px-3 py-2">Accès limité</td></tr>
                                        <tr class="bg-emerald-50/50"><td class="px-3 py-2 font-semibold">Modérateur forum</td><td class="px-3 py-2">Modération des échanges</td><td class="px-3 py-2">Sans accès dédié</td><td class="px-3 py-2 font-semibold text-emerald-800">Portée élargie avec le modèle «&nbsp;Modération élargie&nbsp;»</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </details>

                        <details class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                            <summary class="cursor-pointer text-sm font-bold text-slate-800">Rôles supplémentaires (optionnel)</summary>
                            <p class="mt-3 text-xs leading-relaxed text-slate-600">Créez des profils propres à votre unité (par exemple «&nbsp;Cellule logistique&nbsp;» ou «&nbsp;Opérateur cartographie&nbsp;»), puis cochez les autorisations correspondantes. Vous pourrez les attribuer aux membres ensuite.</p>
                            <div id="wizard-custom-roles-container" class="mt-4 space-y-4"></div>
                            <button type="button" id="wizard-add-custom-role" class="mt-2 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wider text-slate-700 hover:border-emerald-400 hover:text-emerald-800">
                                + Ajouter un rôle
                            </button>
                        </details>

                        <template id="wizard-custom-role-tpl">
                            <div class="wizard-custom-role-row rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Nom affiché</label>
                                        <input type="text" data-role-field="name" maxlength="80" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="ex. Cellule renseignement">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-[10px] font-bold uppercase text-slate-500">Identifiant court</label>
                                        <input type="text" data-role-field="slug" maxlength="50" class="w-full rounded-xl border border-slate-200 px-3 py-2 font-mono text-sm" placeholder="ex. renseignement" pattern="[a-z][a-z0-9_]{1,48}">
                                        <p class="mt-1 text-[10px] text-slate-500">Version courte du nom, sans espaces ni accents (lettres minuscules, chiffres ou _).</p>
                                    </div>
                                </div>
                                <div class="mt-4 space-y-3">
                                    <?php foreach ($wizardPermissionGroups as $groupLabel => $items): ?>
                                    <fieldset class="rounded-xl border border-slate-100 p-3">
                                        <legend class="px-1 text-[10px] font-black uppercase tracking-wider text-slate-500"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></legend>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <?php foreach ($items as $item): ?>
                                            <label class="inline-flex items-center gap-2 rounded-lg bg-slate-50 px-2 py-1 text-xs text-slate-700">
                                                <input type="checkbox" class="custom-perm-cb rounded border-slate-300 text-emerald-600" data-perm-slug="<?= htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                                <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-3 text-right">
                                    <button type="button" class="text-xs font-bold text-red-600 hover:underline" data-remove-row>Retirer ce rôle</button>
                                </div>
                            </div>
                        </template>
                    </section>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Démarrer avec un modèle</h2>
                        <p class="cc-section__text">En plus de la structure que vous composez ci-dessous, vous pouvez copier un modèle officiel. Les éléments déjà présents restent inchangés ; seuls ceux qui manquent sont ajoutés. Aucun membre n’est créé.</p>
                        <details class="cc-help">
                            <summary>Que contient un modèle&nbsp;?</summary>
                            <p>Un modèle ajoute des <strong>unités</strong> à l’organigramme, un catalogue de <strong>fonctions</strong> (emplois du dossier) et parfois des <strong>rôles</strong> d’accès. Les modèles marqués «&nbsp;Catalogue d’emplois&nbsp;» n’ajoutent aucun droit. Un seul modèle peut être choisi ici ; les autres restent disponibles dans <em>Back-office → Catalogue de l’organisation</em>, avec un aperçu avant application.</p>
                        </details>
                        <div class="mt-4">
                            <label class="cc-label" for="cc-kit-filter">Filtrer les modèles</label>
                            <input type="search" id="cc-kit-filter" class="cc-field" placeholder="Ex. infanterie, hélicoptère, SOF, gaming…" autocomplete="off">
                        </div>
                        <div class="mt-4 space-y-3" id="cc-kit-list">
                            <label class="cc-choice">
                                <input type="radio" name="wizard_catalog_kit_code" value="" class="sr-only" checked>
                                <span class="cc-choice__eyebrow">Par défaut</span>
                                <span class="cc-choice__title">Sans modèle supplémentaire</span>
                                <span class="cc-choice__text">Vous gardez uniquement la structure et les rôles définis dans cet assistant. Le catalogue restera disponible plus tard dans le back-office.</span>
                            </label>
                            <?php foreach ($officialKits as $kit): ?>
                            <?php
                            $kitIsJobCatalog = in_array((string) ($kit['code'] ?? ''), $jobDoctrineKits, true);
                            $kitGrades = ((string) ($kit['grade_system_code'] ?? '')) === 'US_CLASSIC' ? 'Grades américains' : 'Grades français';
                            $kitSearch = mb_strtolower((string) ($kit['title'] ?? '') . ' ' . (string) ($kit['summary'] ?? '') . ' ' . implode(' ', array_column($kit['job_roles'] ?? [], 'name')));
                            ?>
                            <label class="cc-choice" data-kit-search="<?= htmlspecialchars($kitSearch, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="radio" name="wizard_catalog_kit_code" value="<?= htmlspecialchars((string) ($kit['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="sr-only">
                                <span class="cc-choice__eyebrow"><?= $kitIsJobCatalog ? 'Catalogue d’emplois' : 'Modèle officiel' ?> · <?= htmlspecialchars($kitGrades, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="cc-choice__title"><?= htmlspecialchars((string) ($kit['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="cc-choice__text"><?= htmlspecialchars((string) ($kit['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(\App\Services\OrganizationCatalog\OrganizationKitDefinitions::volumeLabel($kit), ENT_QUOTES, 'UTF-8') ?>.</span>
                            </label>
                            <?php endforeach; ?>
                            <p class="cc-hint hidden" id="cc-kit-empty">Aucun modèle ne correspond. Effacez le filtre pour tout afficher.</p>
                        </div>
                    </section>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Structure des unités</h2>
                        <p class="cc-section__text">Représentez la chaîne de commandement : une unité racine, puis des sous-niveaux si besoin (groupe, section, équipe, escouade).</p>

                        <label class="cc-checkrow mt-5">
                            <input type="hidden" name="wizard_founder_commands_root" value="0">
                            <input type="checkbox" name="wizard_founder_commands_root" value="1" id="wizard-founder-commands-root" checked>
                            <span>
                                <strong>Vous êtes le chef de l’unité principale</strong>
                                <span>Les autres postes restent vacants jusqu’à ce que vous les attribuiez dans Effectifs.</span>
                            </span>
                        </label>

                        <div class="mt-5">
                            <p class="cc-label">Partir d’une structure type</p>
                            <p class="cc-hint mt-0">Choisissez la forme la plus proche de votre communauté : elle remplace l’arborescence ci-dessous, que vous pourrez ensuite renommer, compléter ou élaguer.</p>
                            <div class="cc-preset-grid mt-3" id="cc-orbat-presets" role="list">
                                <?php foreach ($orbatPresets as $preset): ?>
                                <button type="button" class="cc-preset" role="listitem" data-orbat-preset="<?= htmlspecialchars((string) $preset['id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="cc-preset__audience"><?= htmlspecialchars((string) $preset['audience'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="cc-preset__title"><?= htmlspecialchars((string) $preset['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="cc-preset__text"><?= htmlspecialchars((string) $preset['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="cc-preset__meta"><?= (int) $preset['count'] ?> unités</span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <p class="cc-hint" id="cc-orbat-preset-status" aria-live="polite"></p>
                        </div>
                        <label class="cc-checkrow mt-3">
                            <input type="checkbox" name="wizard_orbat_custom_slug" value="1" id="wizard-orbat-custom-slug">
                            <span>
                                <strong>Adresses courtes par unité</strong>
                                <span>Sinon, chaque adresse est dérivée du nom de l’unité.</span>
                            </span>
                        </label>

                        <details class="cc-help">
                            <summary>Lire l’arborescence et les types d’unité</summary>
                            <p>Chaque ligne est une unité : <strong>nom</strong>, adresse courte, <strong>type</strong>, ordre d’affichage et unité parente. «&nbsp;+ Sous-unité&nbsp;» crée un niveau en dessous, «&nbsp;Suppr.&nbsp;» retire l’unité et toutes ses sous-unités.</p>
                            <ul>
                                <li><strong>Groupe</strong> : grande formation (compagnie, groupement, communauté).</li>
                                <li><strong>Section</strong> : niveau intermédiaire (section, peloton, pôle).</li>
                                <li><strong>Escouade</strong> : groupe de combat de 6 à 12 joueurs.</li>
                                <li><strong>Équipe</strong> : plus petit échelon (binôme, équipe de feu, équipage).</li>
                            </ul>
                            <p>Il faut au moins une unité racine. Les postes restent vacants : les affectations se font ensuite dans <em>Effectifs → Affectations</em>.</p>
                        </details>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" id="orbat-add-root" class="cc-btn cc-btn--ink">+ Unité racine</button>
                        </div>
                        <div id="orbat-builder-root" class="mt-4 min-h-[120px]"></div>

                        <details class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            <summary class="cursor-pointer text-sm font-bold text-slate-700">Mode expert : arborescence avancée</summary>
                            <label class="mt-3 mb-2 block text-[11px] font-black uppercase tracking-[0.22em] text-slate-500">Données structurées des unités</label>
                            <textarea id="wizard-units-editor" rows="8" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 font-mono text-xs text-slate-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"><?= htmlspecialchars($defaultWizardUnitsJson, ENT_QUOTES, 'UTF-8') ?></textarea>
                            <p class="mt-2 text-xs text-slate-500">Synchronisé avec le constructeur ci-dessus ; réservé aux parcours avancés. Chaque unité attend <span class="font-mono">key</span>, <span class="font-mono">parent_key</span> (vide pour une racine), <span class="font-mono">name</span>, <span class="font-mono">slug</span> et <span class="font-mono">type</span> (group, section, squad ou team).</p>
                        </details>
                    </section>
                </div>

                <?php /* ——— 4. Grades ——— */ ?>
                <div class="wizard-panel hidden" data-step="4">
                    <div class="cc-panel-head">
                        <p class="cc-panel-head__eyebrow">Étape 4 sur 5</p>
                        <h1 class="cc-panel-head__title">Référentiel de grades</h1>
                        <p class="cc-panel-head__text">Choisissez le jeu de grades affiché dans votre communauté, puis le grade initial du compte fondateur.</p>
                    </div>

                    <aside class="cc-tip" aria-label="Conseils pour cette étape">
                        <p class="cc-tip__title">À retenir</p>
                        <ul>
                            <li>Prenez le référentiel de l’armée que vous jouez : il s’affiche sur les profils, l’ORBAT et les courriers.</li>
                            <li>Les grades sont des repères d’ancienneté et de commandement ; ils ne donnent aucun droit d’accès.</li>
                            <li>Si un modèle d’organisation est choisi à l’étape précédente, vérifiez que son référentiel (indiqué sur la carte) correspond.</li>
                        </ul>
                    </aside>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Modèle</h2>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <label class="cc-choice min-w-[12rem] flex-1">
                                <input type="radio" name="wizard_grade_system_code" value="FR_CLASSIC" class="sr-only" checked data-grade-system>
                                <span class="cc-choice__eyebrow">Référentiel</span>
                                <span class="cc-choice__title">Français</span>
                                <span class="cc-choice__text">Grades classiques de l’armée de terre française.</span>
                            </label>
                            <label class="cc-choice min-w-[12rem] flex-1">
                                <input type="radio" name="wizard_grade_system_code" value="US_CLASSIC" class="sr-only" data-grade-system>
                                <span class="cc-choice__eyebrow">Référentiel</span>
                                <span class="cc-choice__title">United States</span>
                                <span class="cc-choice__text">Grades classiques des forces armées américaines.</span>
                            </label>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2">
                            <div id="preview-fr" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 max-h-80 overflow-y-auto">
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Aperçu français</p>
                                <div class="mt-3 space-y-3 text-xs text-slate-700">
                                    <?php foreach ($gradesFrGrouped as $block): ?>
                                        <?php if (empty($block['grades'])) { continue; } ?>
                                        <div>
                                            <p class="font-black text-slate-800"><?= htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <ul class="mt-1 space-y-0.5 pl-2 border-l-2 border-emerald-200">
                                                <?php foreach ($block['grades'] as $g): ?>
                                                <li><?= htmlspecialchars((string) ($g['label_short'] ?? $g['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) ($g['label_long'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div id="preview-us" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 max-h-80 overflow-y-auto opacity-60">
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Aperçu US</p>
                                <div class="mt-3 space-y-3 text-xs text-slate-700">
                                    <?php foreach ($gradesUsGrouped as $block): ?>
                                        <?php if (empty($block['grades'])) { continue; } ?>
                                        <div>
                                            <p class="font-black text-slate-800"><?= htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <ul class="mt-1 space-y-0.5 pl-2 border-l-2 border-slate-300">
                                                <?php foreach ($block['grades'] as $g): ?>
                                                <li><?= htmlspecialchars((string) ($g['label_short'] ?? $g['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) ($g['label_long'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <label class="cc-label" for="founder-grade-fr">Grade du fondateur</label>
                            <p class="cc-hint mt-0 mb-2">Le grade affiché sur votre propre dossier. Les grades des membres s’attribuent ensuite dans Effectifs.</p>
                            <select name="wizard_founder_grade_id" id="founder-grade-fr" class="cc-select founder-grade-select">
                                <?php foreach ($gradesFrGrouped as $block): ?>
                                    <?php if (empty($block['grades'])) { continue; } ?>
                                    <optgroup label="<?= htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?php foreach ($block['grades'] as $g): ?>
                                        <option value="<?= (int) $g['id'] ?>"><?= htmlspecialchars((string) ($g['label_long'] ?? $g['label_short'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <select name="wizard_founder_grade_id_us" id="founder-grade-us" class="hidden cc-select founder-grade-select">
                                <?php foreach ($gradesUsGrouped as $block): ?>
                                    <?php if (empty($block['grades'])) { continue; } ?>
                                    <optgroup label="<?= htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?php foreach ($block['grades'] as $g): ?>
                                        <option value="<?= (int) $g['id'] ?>"><?= htmlspecialchars((string) ($g['label_long'] ?? $g['label_short'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </section>
                </div>

                <?php /* ——— 5. Accès & offre ——— */ ?>
                <div class="wizard-panel hidden" data-step="5">
                    <div class="cc-panel-head">
                        <p class="cc-panel-head__eyebrow">Étape 5 sur 5</p>
                        <h1 class="cc-panel-head__title">Inscription, offre et validation</h1>
                        <p class="cc-panel-head__text">Dernière étape : comment les candidats arrivent, quelle formule vous choisissez, puis le récapitulatif avant création.</p>
                    </div>

                    <aside class="cc-tip" aria-label="Conseils pour cette étape">
                        <p class="cc-tip__title">À retenir</p>
                        <ul>
                            <li>Pas encore prêt à recruter&nbsp;? Cochez «&nbsp;Fermer le recrutement&nbsp;» : la vitrine reste visible, les candidatures attendront.</li>
                            <li>Le bouton <strong>Aperçu</strong> montre la page publique sans rien créer.</li>
                            <li>Le récapitulatif en bas liste vos choix : cliquez sur une étape du bandeau pour corriger avant de valider.</li>
                        </ul>
                    </aside>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Mode d’inscription</h2>
                        <p class="cc-section__text" data-cc-reg-full>Ce choix définit le parcours candidat sur la page d’enrôlement public. Vous pourrez le modifier ensuite.</p>
                        <div class="cc-soft mt-5 hidden" data-cc-reg-discord-only>
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-900">Recrutement uniquement sur Discord</p>
                            <p class="mt-3 text-xs leading-relaxed text-slate-700">Avec le profil choisi, personne ne candidate sur le site. Le bouton de votre page publique devient « Rejoindre sur Discord » et ouvre votre serveur. Pensez à renseigner le lien d’invitation Discord après la création (Paramètres → Inscription). Pour un formulaire de candidature sur le site, choisissez le profil « Complet ».</p>
                        </div>
                        <div class="cc-choice-grid cc-choice-grid--3 mt-5" data-cc-reg-full>
                            <label class="cc-choice">
                                <input type="radio" name="registration_mode" value="milsim" class="sr-only" checked id="registration_mode_milsim">
                                <span class="cc-choice__eyebrow">Sélection</span>
                                <span class="cc-choice__title">Dossier MilSim complet</span>
                                <span class="cc-choice__text">Candidature détaillée : identité, matériel, expérience, motivation et confirmation d’engagement.</span>
                            </label>
                            <label class="cc-choice">
                                <input type="radio" name="registration_mode" value="simple" class="sr-only" id="registration_mode_simple">
                                <span class="cc-choice__eyebrow">Sélection</span>
                                <span class="cc-choice__title">Formulaire court</span>
                                <span class="cc-choice__text">Quelques champs essentiels pour un onboarding rapide ou une file d’attente légère.</span>
                            </label>
                            <label class="cc-choice">
                                <input type="radio" name="registration_mode" value="discord" class="sr-only" id="registration_mode_discord">
                                <span class="cc-choice__eyebrow">Sélection</span>
                                <span class="cc-choice__title">Recrutement via Discord</span>
                                <span class="cc-choice__text">Pseudo Discord et questions personnalisées — idéal si votre recrutement vit déjà sur Discord.</span>
                            </label>
                        </div>
                        <div class="mt-5 space-y-4">
                            <div id="registration-mode-detail-milsim" class="cc-soft">
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-900">Dossier MilSim</p>
                                <ul class="mt-3 list-disc space-y-2 pl-5 text-xs leading-relaxed text-slate-700">
                                    <li>Filtrage fort et dossier documenté pour chaque candidature.</li>
                                    <li>Personnalisation du préambule, des règles et des champs depuis l’atelier ci-dessous.</li>
                                </ul>
                                <button type="button" class="btn-open-milsim-form-editor cc-btn cc-btn--ink mt-4">Ouvrir l’édition du formulaire</button>
                            </div>
                            <div id="registration-mode-detail-simple" class="cc-soft cc-soft--muted hidden">
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Formulaire court</p>
                                <ul class="mt-3 list-disc space-y-2 pl-5 text-xs leading-relaxed text-slate-700">
                                    <li>Appel, disponibilité, motivation — sans le parcours dossier complet.</li>
                                    <li>Le message d’accueil ci-dessous porte le ton de votre vitrine.</li>
                                </ul>
                                <button type="button" id="btn-open-welcome-editor" class="cc-btn cc-btn--ghost mt-4">Aller au message d’accueil</button>
                            </div>
                            <div id="registration-mode-detail-discord" class="cc-soft cc-soft--muted hidden">
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Discord</p>
                                <ul class="mt-3 list-disc space-y-2 pl-5 text-xs leading-relaxed text-slate-700">
                                    <li>Le candidat renseigne son pseudo Discord et répond à vos questions.</li>
                                    <li>Après création, configurez ces questions depuis le back-office recrutement.</li>
                                </ul>
                            </div>
                        </div>

                        <details class="cc-help">
                            <summary>Quel mode choisir&nbsp;?</summary>
                            <p><strong>Dossier MilSim</strong> si vous sélectionnez vos joueurs (matériel, expérience, disponibilités). <strong>Formulaire court</strong> pour une communauté ouverte où l’on rejoint vite. <strong>Discord</strong> si l’entretien se fait déjà sur votre serveur : Athena garde la trace des candidatures. Le mode se change à tout moment dans <em>Paramètres → Inscription</em>.</p>
                        </details>

                        <div class="mt-5">
                            <label class="cc-label" for="wizard-welcome-text">Message d’accueil</label>
                            <textarea id="wizard-welcome-text" name="welcome_text" rows="3" maxlength="500" class="cc-area" placeholder="Texte visible sur la page publique de la communauté."></textarea>
                            <p class="cc-hint">Utile surtout en formulaire court ; complète la vitrine.</p>
                        </div>

                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            <label class="cc-checkrow">
                                <input type="checkbox" name="community_locked" value="1">
                                <span>
                                    <strong>Fermer le recrutement</strong>
                                    <span>Les nouvelles candidatures ne sont pas acceptées pour le moment.</span>
                                </span>
                            </label>
                            <label class="cc-checkrow">
                                <input type="checkbox" name="require_ai_ack" value="1" checked>
                                <span>
                                    <strong>Exiger la confirmation « sans IA »</strong>
                                    <span>Le candidat atteste avoir rédigé sa candidature lui-même.</span>
                                </span>
                            </label>
                            <label class="cc-checkrow md:col-span-2">
                                <input type="checkbox" name="refuse_other_community_members" value="1">
                                <span>
                                    <strong>Refuser les comptes déjà rattachés à une autre communauté</strong>
                                    <span>Un compte Athena déjà membre d’une autre communauté ne pourra pas candidater ici. Les visiteurs sans compte, et les comptes sans communauté (espace « Pas d’organisation »), restent acceptés.</span>
                                </span>
                            </label>
                        </div>

                        <div class="mt-5 rounded-2xl border border-emerald-200/80 bg-white p-5 ring-1 ring-emerald-100/50">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-emerald-900">Atelier formulaire MilSim</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">Édition visuelle avec aperçu</p>
                                    <p class="mt-2 max-w-xl text-xs leading-relaxed text-slate-600">Préambule, règles, champs et rendu à droite. L’essentiel se fait à la souris.</p>
                                </div>
                                <button type="button" class="btn-open-milsim-form-editor cc-btn cc-btn--ink shrink-0">Ouvrir l’atelier</button>
                            </div>
                            <details class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/80 p-3">
                                <summary class="cursor-pointer text-xs font-bold text-slate-600">Mode expert : import structuré (optionnel)</summary>
                                <p class="mt-2 text-xs text-slate-500">Collez une configuration complète fournie par votre équipe si vous en disposez.</p>
                                <textarea name="wizard_enlistment_milsim_json" id="wizard_enlistment_milsim_json" rows="5" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-900" placeholder="Configuration complète du formulaire"></textarea>
                            </details>
                        </div>

                        <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5">
                            <h3 class="text-sm font-black text-slate-900">Créneaux de disponibilité</h3>
                            <p class="mt-1 text-xs leading-relaxed text-slate-600">Choisissez dès maintenant les créneaux attendus pour votre communauté. Vous pourrez les modifier ensuite dans la fiche organisation.</p>
                            <div class="mt-4">
                                <?php
                                $selectedSlots = [];
                                $idsInputName = 'wizard_milsim[availability_slot_ids][]';
                                $customInputName = 'wizard_milsim[availability_slot_custom][]';
                                $configuredFlagName = 'wizard_milsim[availability_slots_configured]';
                                $formId = 'community-create-form';
                                include base_path('views/partials/availability_slots_editor.php');
                                ?>
                            </div>
                        </div>
                    </section>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Visibilité de l’organigramme</h2>
                        <p class="cc-section__text">Qui peut consulter la structure des unités sur le portail.</p>
                        <div class="mt-4 max-w-md">
                            <label class="cc-label" for="cc-orbat-vis">Visibilité</label>
                            <select id="cc-orbat-vis" name="wizard_orbat_visibility" class="cc-select">
                                <option value="public">Visible par tous les visiteurs</option>
                                <option value="members" selected>Réservée aux membres</option>
                                <option value="command">Réservée au commandement</option>
                            </select>
                            <p class="cc-hint">«&nbsp;Visible par tous&nbsp;» montre l’organigramme sur la page publique, utile pour recruter sur des postes précis. Les noms des membres ne s’affichent qu’aux personnes autorisées.</p>
                        </div>
                    </section>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Formule</h2>
                        <p class="cc-section__text">Choisissez la formule adaptée à votre unité. Les restrictions de capacité et d’accès dépendent de l’offre retenue. Vous pouvez commencer gratuitement et changer de formule plus tard sans perdre vos données.</p>
                        <div class="cc-choice-grid cc-choice-grid--3 mt-5">
                            <?php foreach ($subscriptionOfferCards as $idx => $offer): ?>
                            <?php
                            $offerValue = (string) ($offer['value'] ?? '');
                            $offerPaid = !empty($offer['paid']);
                            $offerHeart = !empty($offer['heart']);
                            $offerAvailable = !array_key_exists('available', $offer) || !empty($offer['available']);
                            $offerLimits = is_array($offer['limits'] ?? null) ? $offer['limits'] : [];
                            $offerClass = $offerHeart ? 'cc-choice--heart' : ($offerPaid ? '' : 'cc-choice--dark');
                            ?>
                            <label class="cc-choice <?= $offerClass ?> min-h-[14rem] <?= !$offerAvailable ? 'opacity-70' : '' ?>">
                                <input
                                    type="radio"
                                    name="plan_choice"
                                    value="<?= htmlspecialchars($offerValue, ENT_QUOTES, 'UTF-8') ?>"
                                    class="sr-only"
                                    <?= $idx === 0 ? 'checked' : '' ?>
                                    data-paid="<?= $offerPaid ? '1' : '0' ?>"
                                    data-heart="<?= $offerHeart ? '1' : '0' ?>"
                                    data-plan-label="<?= htmlspecialchars((string) ($offer['title'] ?? $offerValue), ENT_QUOTES, 'UTF-8') ?>"
                                    <?= !$offerAvailable ? 'disabled' : '' ?>
                                >
                                <span class="cc-choice__eyebrow"><?= htmlspecialchars((string) ($offer['eyebrow'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="cc-choice__title"><?= htmlspecialchars((string) ($offer['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="cc-choice__text"><?= htmlspecialchars((string) ($offer['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($offerLimits !== []): ?>
                                <span class="mt-3 block text-xs text-slate-600">
                                    <?= htmlspecialchars(implode(' · ', array_map('strval', $offerLimits)), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php endif; ?>
                                <span class="cc-choice__meta <?= $offerHeart ? 'text-rose-700' : ($offerPaid ? 'text-sky-700' : 'text-emerald-300') ?>">
                                    <?= htmlspecialchars((string) ($offer['meta'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if (!$offerAvailable): ?>
                                <span class="mt-2 block text-xs font-semibold text-amber-700"><?= htmlspecialchars((string) ($offer['unavailable_hint'] ?? 'Souscription indisponible pour le moment'), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="paid-hint" class="cc-soft cc-soft--rose mt-4 hidden">
                            Votre communauté est créée tout de suite. Vous arrivez ensuite sur la page de paiement d’Athena : payez par PayPal avec la référence affichée, l’équipe Athena active la formule dès réception (en attendant, l’essai fondateur Pro est actif).
                        </div>
                    </section>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Ancienneté de l’organisation</h2>
                        <p class="cc-section__text">Cette date sert au calcul d’ancienneté du fondateur et des membres. Vous pourrez la modifier ensuite.</p>
                        <div class="mt-5 space-y-3">
                            <label class="cc-choice">
                                <input type="radio" name="wizard_org_founding_choice" value="today" class="sr-only" checked data-founding-choice>
                                <span class="cc-choice__eyebrow">Recommandé</span>
                                <span class="cc-choice__title">Cette communauté commence aujourd’hui</span>
                                <span class="cc-choice__text">L’ancienneté du fondateur part d’aujourd’hui.</span>
                            </label>
                            <label class="cc-choice">
                                <input type="radio" name="wizard_org_founding_choice" value="older" class="sr-only" data-founding-choice>
                                <span class="cc-choice__eyebrow">Option</span>
                                <span class="cc-choice__title">L’unité existait avant Athena</span>
                                <span class="cc-choice__text">Indiquez la date de création réelle si vous la connaissez. Sinon, vous pourrez la saisir plus tard dans Ancienneté.</span>
                            </label>
                        </div>
                        <div id="wizard-org-founding-date-wrap" class="mt-4 hidden max-w-sm">
                            <label class="cc-label" for="wizard-org-founding-date">Date de création de l’unité</label>
                            <input type="date" name="wizard_org_founding_date" id="wizard-org-founding-date" class="cc-select" max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                            <p class="cc-hint">Facultatif pour l’instant. Une date dans le futur n’est pas acceptée.</p>
                        </div>
                    </section>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Dès la création</h2>
                        <p class="cc-section__text">Athena prépare déjà le cadre de départ. Vous pourrez tout ajuster ensuite.</p>
                        <ul class="mt-4 list-disc space-y-2 pl-5 text-sm leading-relaxed text-slate-700">
                            <li>Le parcours d’arrivée des nouveaux membres est prêt.</li>
                            <li>Le bureau effectifs utilise des réglages de départ (vous pourrez les personnaliser).</li>
                            <li>Votre compte fondateur est en service actif.</li>
                            <li>La fenêtre de connexion Overwatch reprend le nom de la communauté, avec les méthodes d’accès habituelles.</li>
                        </ul>
                    </section>

                    <section class="cc-section">
                        <h2 class="cc-section__title">Récapitulatif</h2>
                        <p class="cc-section__text">Vérifiez les points essentiels avant de créer la communauté.</p>
                        <div id="wizard-recap" class="cc-recap mt-4">
                            <p class="font-bold text-slate-900">Synthèse</p>
                            <ul id="wizard-recap-list">
                                <li>Complétez les étapes : le détail apparaît ici.</li>
                            </ul>
                        </div>
                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <button type="submit" formaction="<?= htmlspecialchars(url('communities/create/preview'), ENT_QUOTES, 'UTF-8') ?>" formmethod="post" formtarget="_blank" class="cc-btn cc-btn--ghost">
                                Aperçu (nouvel onglet)
                            </button>
                            <span class="text-xs text-slate-500">Enregistre un brouillon et ouvre la simulation.</span>
                        </div>
                    </section>
                </div>

                <div class="cc-nav">
                    <button type="button" id="wizard-prev" class="cc-btn cc-btn--ghost hidden">Précédent</button>
                    <div class="ml-auto flex flex-wrap gap-3">
                        <button type="button" id="wizard-next" class="cc-btn cc-btn--next">Suivant</button>
                        <button type="submit" id="submit-btn" class="cc-btn cc-btn--primary hidden">Créer la communauté</button>
                    </div>
                </div>

                <?php include base_path('views/community/partials/milsim_wizard_modal.php'); ?>
            </form>
        </div>
    </div>

    <div class="cc-tutorial hidden" id="cc-tutorial" role="dialog" aria-modal="true" aria-labelledby="cc-tutorial-title">
        <div class="cc-tutorial__backdrop" data-cc-tutorial-close></div>
        <div class="cc-tutorial__card">
            <div class="cc-tutorial__head">
                <p class="cc-tutorial__kicker">Tutoriel · <span data-cc-tutorial-count>1 / 6</span></p>
                <button type="button" class="cc-tutorial__x" data-cc-tutorial-close aria-label="Fermer le tutoriel">×</button>
            </div>
            <div class="cc-tutorial__slides">
                <section class="cc-tutorial__slide" data-cc-slide="0">
                    <h2 id="cc-tutorial-title">Créer votre communauté en cinq étapes</h2>
                    <p>L’assistant prépare tout ce dont une unité a besoin le premier soir : page publique, recrutement, organigramme, rôles et grades. Rien n’est définitif : chaque choix se retrouve dans le back-office.</p>
                    <ol class="cc-tutorial__list">
                        <li><strong>Identité</strong> — nom, adresse, langue, unité représentée.</li>
                        <li><strong>Type</strong> — l’étendue des outils activés.</li>
                        <li><strong>Organisation</strong> — rôles, modèle et organigramme.</li>
                        <li><strong>Grades</strong> — référentiel et grade du fondateur.</li>
                        <li><strong>Accès &amp; offre</strong> — inscription, formule, récapitulatif.</li>
                    </ol>
                </section>
                <section class="cc-tutorial__slide hidden" data-cc-slide="1" data-cc-goto="1">
                    <h2>1. Identité</h2>
                    <p>Seul le <strong>nom affiché</strong> est obligatoire. L’adresse web (<span class="font-mono">/c/votre-unite</span>) est calculée à partir du nom ; cochez «&nbsp;adresse personnalisée&nbsp;» pour la choisir.</p>
                    <p>Si vous jouez une unité réelle, déclarez-la : les candidats la retrouvent dans le registre. La vitrine (logo, bannière, textes) peut attendre.</p>
                </section>
                <section class="cc-tutorial__slide hidden" data-cc-slide="2" data-cc-goto="2">
                    <h2>2. Type de communauté</h2>
                    <p><strong>Complet</strong> active tous les modules. Les profils <strong>Bureau des effectifs</strong> et <strong>Carte ATAK</strong> allègent l’interface pour les communautés qui n’utilisent qu’une partie d’Athena.</p>
                    <p>Changer de type plus tard masque ou affiche des modules, sans jamais effacer de données.</p>
                </section>
                <section class="cc-tutorial__slide hidden" data-cc-slide="3" data-cc-goto="3">
                    <h2>3. Organisation</h2>
                    <p>Choisissez un jeu de <strong>rôles</strong> (les droits sur le site), éventuellement un <strong>modèle officiel</strong> (unités, fonctions et grades types), puis une <strong>structure type</strong> : escouade, section, peloton US, compagnie, détachement SOF…</p>
                    <p>L’arborescence se modifie ligne par ligne : renommez, ajoutez des sous-unités, supprimez une branche.</p>
                </section>
                <section class="cc-tutorial__slide hidden" data-cc-slide="4" data-cc-goto="4">
                    <h2>4. Grades</h2>
                    <p>Référentiel français ou américain, puis votre propre grade. Les grades servent d’affichage et d’ancienneté ; les droits viennent des rôles.</p>
                </section>
                <section class="cc-tutorial__slide hidden" data-cc-slide="5" data-cc-goto="5">
                    <h2>5. Accès &amp; offre, puis après la création</h2>
                    <p>Choisissez comment les candidats postulent, qui voit l’organigramme et la formule. Vérifiez le récapitulatif, puis «&nbsp;Créer la communauté&nbsp;».</p>
                    <p class="cc-tutorial__next">Ensuite, dans le back-office&nbsp;: <strong>Configuration initiale</strong> pour la liste de démarrage, <strong>Invitations</strong> pour faire venir vos premiers membres, et la recherche <kbd>Ctrl</kbd>&nbsp;+&nbsp;<kbd>K</kbd> pour retrouver n’importe quelle page ou guide.</p>
                </section>
            </div>
            <div class="cc-tutorial__foot">
                <button type="button" class="cc-btn cc-btn--ghost" data-cc-tutorial-prev>Précédent</button>
                <a class="cc-tutorial__guide" href="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Guide complet</a>
                <button type="button" class="cc-btn cc-btn--next" data-cc-tutorial-next>Suivant</button>
            </div>
        </div>
    </div>
</div>

<script>
window.__realUnitCatalog = <?= $realUnitCatalogJson ?>;
</script>
<script src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/js/community-orbat-builder.js"></script>
<script src="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/assets/js/community-unit-affiliation.js"></script>
<script>
(function () {
    var form = document.getElementById('community-create-form');
    if (!form) return;

    var customCommunitySlugCb = document.getElementById('wizard-custom-community-slug');
    var communitySlugWrap = document.getElementById('wizard-community-slug-wrap');
    var communitySlugInput = document.getElementById('wizard-community-slug-input');
    function syncCommunitySlugUi() {
        var on = customCommunitySlugCb && customCommunitySlugCb.checked;
        if (communitySlugWrap) communitySlugWrap.classList.toggle('hidden', !on);
        if (communitySlugInput) {
            communitySlugInput.required = !!on;
            if (!on) communitySlugInput.value = '';
        }
    }
    if (customCommunitySlugCb) {
        customCommunitySlugCb.addEventListener('change', syncCommunitySlugUi);
        syncCommunitySlugUi();
    }

    var detailMilsim = document.getElementById('registration-mode-detail-milsim');
    var detailSimple = document.getElementById('registration-mode-detail-simple');
    var detailDiscord = document.getElementById('registration-mode-detail-discord');
    var milsimModal = document.getElementById('milsim-wizard-modal');
    var welcomeTa = document.getElementById('wizard-welcome-text');
    var btnWelcome = document.getElementById('btn-open-welcome-editor');

    function currentRegistrationMode() {
        var checked = form.querySelector('input[name="registration_mode"]:checked');
        return checked ? checked.value : 'milsim';
    }

    function syncRegistrationModeUi() {
        var mode = currentRegistrationMode();
        if (detailMilsim) detailMilsim.classList.toggle('hidden', mode !== 'milsim');
        if (detailSimple) detailSimple.classList.toggle('hidden', mode !== 'simple');
        if (detailDiscord) detailDiscord.classList.toggle('hidden', mode !== 'discord');
    }

    form.querySelectorAll('input[name="registration_mode"]').forEach(function (el) {
        el.addEventListener('change', syncRegistrationModeUi);
    });
    syncRegistrationModeUi();

    // Profils sans recrutement (Bureau des effectifs, Carte ATAK) : Discord uniquement, expliqué à chaque étape.
    function syncTenantTypeUi() {
        var type = (form.querySelector('input[name="tenant_type"]:checked') || {}).value || 'full';
        var discordOnly = type !== 'full';
        form.querySelectorAll('[data-cc-type-note]').forEach(function (el) {
            el.classList.toggle('hidden', el.getAttribute('data-cc-type-note') !== type);
        });
        form.querySelectorAll('[data-cc-reg-full]').forEach(function (el) { el.classList.toggle('hidden', discordOnly); });
        form.querySelectorAll('[data-cc-reg-discord-only]').forEach(function (el) { el.classList.toggle('hidden', !discordOnly); });
        if (discordOnly) {
            var d = document.getElementById('registration_mode_discord');
            if (d) { d.checked = true; }
            if (detailMilsim) detailMilsim.classList.add('hidden');
            if (detailSimple) detailSimple.classList.add('hidden');
            if (detailDiscord) detailDiscord.classList.add('hidden');
        } else {
            syncRegistrationModeUi();
        }
    }
    form.querySelectorAll('input[name="tenant_type"]').forEach(function (el) {
        el.addEventListener('change', syncTenantTypeUi);
    });
    syncTenantTypeUi();

    function closeMilsimModal() {
        if (milsimModal) milsimModal.classList.add('hidden');
    }
    function openMilsimFormEditor() {
        if (milsimModal) {
            milsimModal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
    }
    if (milsimModal) {
        milsimModal.querySelectorAll('[data-milsim-modal-close], [data-milsim-modal-backdrop]').forEach(function (el) {
            el.addEventListener('click', function () {
                closeMilsimModal();
                document.body.classList.remove('overflow-hidden');
            });
        });
    }
    document.querySelectorAll('.btn-open-milsim-form-editor').forEach(function (btn) {
        btn.addEventListener('click', openMilsimFormEditor);
    });
    if (btnWelcome) {
        btnWelcome.addEventListener('click', function () {
            if (welcomeTa) {
                welcomeTa.scrollIntoView({ behavior: 'smooth', block: 'center' });
                welcomeTa.focus();
            }
        });
    }

    var hint = document.getElementById('paid-hint');
    var btn = document.getElementById('submit-btn');
    var nextBtn = document.getElementById('wizard-next');
    var prevBtn = document.getElementById('wizard-prev');
    var step = 1;
    var maxStep = 5;
    var panels = form.querySelectorAll('.wizard-panel');
    var tabs = document.querySelectorAll('.community-create [data-step-tab]');
    var unitsHidden = document.getElementById('wizard-units-json');
    var unitsEditor = document.getElementById('wizard-units-editor');
    var defaultUnits = <?= json_encode($quickUnitsArr, JSON_UNESCAPED_UNICODE) ?>;
    var orbatBuilder = typeof initOrbatBuilder === 'function'
        ? initOrbatBuilder('orbat-builder-root', 'wizard-units-json', { defaultUnits: defaultUnits })
        : null;

    var addRootBtn = document.getElementById('orbat-add-root');
    if (addRootBtn && orbatBuilder) {
        addRootBtn.addEventListener('click', function () { orbatBuilder.addRoot(); });
    }

    function syncUnitsFromOrgStep() {
        if (orbatBuilder) {
            orbatBuilder.sync();
        } else if (unitsEditor && unitsHidden) {
            unitsHidden.value = unitsEditor.value;
        }
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function updateRecap() {
        var list = document.getElementById('wizard-recap-list');
        if (!list) return;
        var name = (form.querySelector('[name="name"]') || {}).value || '';
        var slug = (form.querySelector('[name="slug"]') || {}).value || '';
        var tz = (form.querySelector('[name="wizard_timezone"]') || {}).value || '';
        var roles = (form.querySelector('input[name="wizard_roles_template"]:checked') || {}).value || '';
        var gs = (form.querySelector('input[name="wizard_grade_system_code"]:checked') || {}).value || '';
        var tenantType = (form.querySelector('input[name="tenant_type"]:checked') || {}).value || '';
        var tenantLabel = '';
        var tt = form.querySelector('input[name="tenant_type"]:checked');
        if (tt) {
            var titleEl = tt.closest('label') && tt.closest('label').querySelector('.cc-choice__title');
            tenantLabel = titleEl ? titleEl.textContent.trim() : tenantType;
        }
        var fgText = '';
        var fgSel = form.querySelector('select.founder-grade-select:not(.hidden)');
        if (fgSel && fgSel.selectedIndex >= 0) {
            fgText = fgSel.options[fgSel.selectedIndex].text;
        }
        var unitsCount = 0;
        try {
            var raw = unitsHidden ? unitsHidden.value : '[]';
            var arr = JSON.parse(raw || '[]');
            unitsCount = Array.isArray(arr) ? arr.length : 0;
        } catch (e) { unitsCount = 0; }
        var reg = currentRegistrationMode();
        var locked = form.querySelector('[name="community_locked"]') && form.querySelector('[name="community_locked"]').checked;
        var plan = '';
        var planText = '';
        form.querySelectorAll('input[name="plan_choice"]').forEach(function (el) {
            if (el.checked) {
                plan = el.value;
                planText = el.getAttribute('data-plan-label') || el.value;
            }
        });
        var regLabel = reg === 'simple' ? 'Formulaire court' : (reg === 'discord' ? 'Recrutement via Discord' : 'Dossier MilSim complet');
        var planLabel = planText || '—';
        var lines = [];
        lines.push('Nom : ' + (name || '—'));
        if (slug) lines.push('Adresse courte : ' + slug);
        if (tz) lines.push('Fuseau : ' + tz);
        if (tenantLabel) lines.push('Type : ' + tenantLabel);
        lines.push('Modèle rôles : ' + (roles === 'standard' ? 'Modération élargie' : 'Démarrage rapide'));
        var extraRoles = document.querySelectorAll('#wizard-custom-roles-container .wizard-custom-role-row').length;
        if (extraRoles > 0) lines.push('Rôles supplémentaires : ' + extraRoles);
        lines.push('Unités : ' + unitsCount);
        var kitRadio = form.querySelector('input[name="wizard_catalog_kit_code"]:checked');
        var kitLabel = 'Sans modèle supplémentaire';
        if (kitRadio && kitRadio.value) {
            var kitTitle = kitRadio.closest('label') && kitRadio.closest('label').querySelector('.cc-choice__title');
            kitLabel = kitTitle ? kitTitle.textContent.trim() : kitRadio.value;
        }
        lines.push('Modèle d’organisation : ' + kitLabel);
        var founderRoot = form.querySelector('#wizard-founder-commands-root');
        lines.push('Chef de l’unité principale : ' + (founderRoot && founderRoot.checked ? 'vous' : 'à désigner plus tard'));
        var founding = (form.querySelector('input[name="wizard_org_founding_choice"]:checked') || {}).value || 'today';
        if (founding === 'older') {
            var foundingDate = (form.querySelector('[name="wizard_org_founding_date"]') || {}).value || '';
            lines.push('Ancienneté : unité existante' + (foundingDate ? ' (depuis ' + foundingDate + ')' : ', date à préciser plus tard'));
        } else {
            lines.push('Ancienneté : commence aujourd’hui');
        }
        lines.push('Grades : ' + (gs === 'US_CLASSIC' ? 'United States' : 'Français'));
        if (fgText) lines.push('Grade fondateur : ' + fgText);
        lines.push('Inscription : ' + regLabel);
        lines.push('Recrutement fermé : ' + (locked ? 'oui' : 'non'));
        lines.push('Formule : ' + planLabel);
        list.innerHTML = lines.map(function (l) { return '<li>' + escapeHtml(l) + '</li>'; }).join('');
    }

    function showStep(n) {
        step = Math.min(maxStep, Math.max(1, n));
        panels.forEach(function (p) {
            var d = parseInt(p.getAttribute('data-step'), 10);
            p.classList.toggle('hidden', d !== step);
        });
        tabs.forEach(function (t) {
            var d = parseInt(t.getAttribute('data-step-tab'), 10);
            t.setAttribute('data-active', d === step ? '1' : '0');
        });
        prevBtn.classList.toggle('hidden', step <= 1);
        nextBtn.classList.toggle('hidden', step >= maxStep);
        btn.classList.toggle('hidden', step < maxStep);
        if (step === maxStep) {
            syncPaid();
            updateRecap();
        }
        try {
            document.querySelector('.community-create .cc-board').scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) { /* ignore */ }
    }

    function syncPaid() {
        var paid = false;
        var heart = false;
        form.querySelectorAll('input[name="plan_choice"]').forEach(function (el) {
            if (!el.checked) return;
            if (el.getAttribute('data-paid') === '1') paid = true;
            if (el.getAttribute('data-heart') === '1') heart = true;
        });
        if (hint) hint.classList.toggle('hidden', !paid);
        if (btn) {
            if (paid && heart) btn.textContent = 'Créer et soutenir (2 €)';
            else if (paid) btn.textContent = 'Créer et poursuivre le paiement';
            else btn.textContent = 'Créer la communauté';
        }
    }
    form.querySelectorAll('input[name="plan_choice"]').forEach(function (el) {
        el.addEventListener('change', syncPaid);
    });

    var nameInput = document.getElementById('cc-name');
    var nameError = document.getElementById('cc-name-error');
    var reservedSlugs = <?= json_encode(\App\Services\Community\TenantSlugService::RESERVED) ?>;
    function previewSlug(name) {
        var t = String(name || '').toLowerCase();
        if (t.normalize) t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        t = t.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        if (t.length > 50) t = t.substring(0, 50).replace(/-+$/g, '');
        return t;
    }
    var slugPreviewEl = document.querySelector('[data-slug-preview]');
    var slugPreviewBase = <?= json_encode(rtrim(url('c/'), '/') . '/') ?>;
    function syncSlugPreview() {
        if (!slugPreviewEl) return;
        var custom = customCommunitySlugCb && customCommunitySlugCb.checked && communitySlugInput && communitySlugInput.value.trim() !== '';
        var slug = custom ? communitySlugInput.value.trim().toLowerCase() : previewSlug(nameInput ? nameInput.value : '');
        var note = '';
        if (slug && reservedSlugs.indexOf(slug) !== -1) note = ' — adresse réservée, un suffixe sera ajouté';
        slugPreviewEl.textContent = slugPreviewBase + (slug || '…') + note;
    }
    if (nameInput) nameInput.addEventListener('input', function () {
        syncSlugPreview();
        if (nameInput.value.trim() !== '' && nameError) {
            nameError.classList.add('hidden');
            nameInput.removeAttribute('aria-invalid');
        }
    });
    if (communitySlugInput) communitySlugInput.addEventListener('input', syncSlugPreview);
    if (customCommunitySlugCb) customCommunitySlugCb.addEventListener('change', syncSlugPreview);

    function validateStep(n) {
        if (n === 1 && nameInput && nameInput.value.trim() === '') {
            if (nameError) nameError.classList.remove('hidden');
            nameInput.setAttribute('aria-invalid', 'true');
            nameInput.focus();
            return false;
        }
        if (n === 1 && customCommunitySlugCb && customCommunitySlugCb.checked && communitySlugInput && !communitySlugInput.checkValidity()) {
            communitySlugInput.reportValidity();
            return false;
        }
        return true;
    }
    function markDone(n) {
        tabs.forEach(function (t) {
            if (parseInt(t.getAttribute('data-step-tab'), 10) === n) t.setAttribute('data-done', '1');
        });
    }

    nextBtn.addEventListener('click', function () {
        if (!validateStep(step)) return;
        if (step === 3) {
            syncUnitsFromOrgStep();
            if (unitsEditor && unitsHidden) unitsEditor.value = unitsHidden.value;
        }
        markDone(step);
        showStep(step + 1);
    });
    prevBtn.addEventListener('click', function () { showStep(step - 1); });
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            var n = parseInt(t.getAttribute('data-step-tab'), 10);
            if (n > step && !validateStep(step)) return;
            if (n > step) markDone(step);
            if (step === 3) {
                syncUnitsFromOrgStep();
                if (unitsEditor && unitsHidden) unitsEditor.value = unitsHidden.value;
            }
            showStep(n);
        });
    });

    var orbatPresets = <?= json_encode($orbatPresets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var presetStatus = document.getElementById('cc-orbat-preset-status');
    document.querySelectorAll('[data-orbat-preset]').forEach(function (presetBtn) {
        presetBtn.addEventListener('click', function () {
            if (!orbatBuilder) return;
            var id = presetBtn.getAttribute('data-orbat-preset');
            var preset = null;
            orbatPresets.forEach(function (p) { if (p.id === id) preset = p; });
            if (!preset) return;
            var current = [];
            try { current = JSON.parse(unitsHidden ? unitsHidden.value : '[]') || []; } catch (e) { current = []; }
            if (current.length > 0 && !window.confirm('Remplacer l’arborescence actuelle (' + current.length + ' unités) par « ' + preset.label + ' » ?')) {
                return;
            }
            orbatBuilder.loadUnits(preset.units);
            if (unitsEditor) unitsEditor.value = JSON.stringify(preset.units, null, 2);
            document.querySelectorAll('[data-orbat-preset]').forEach(function (b) {
                b.setAttribute('aria-pressed', b === presetBtn ? 'true' : 'false');
            });
            if (presetStatus) presetStatus.textContent = '« ' + preset.label + ' » chargé : ' + preset.units.length + ' unités. Renommez-les librement ci-dessous.';
        });
    });

    var kitFilter = document.getElementById('cc-kit-filter');
    var kitEmpty = document.getElementById('cc-kit-empty');
    function normalizeText(v) {
        var t = String(v || '').toLowerCase();
        if (t.normalize) t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        return t.trim();
    }
    if (kitFilter) {
        kitFilter.addEventListener('input', function () {
            var q = normalizeText(kitFilter.value);
            var tokens = q ? q.split(/\s+/) : [];
            var shown = 0;
            document.querySelectorAll('#cc-kit-list [data-kit-search]').forEach(function (card) {
                var hay = normalizeText(card.getAttribute('data-kit-search'));
                var ok = tokens.every(function (tok) { return hay.indexOf(tok) !== -1; });
                var checked = card.querySelector('input:checked');
                card.classList.toggle('hidden', !ok && !checked);
                if (ok) shown++;
            });
            if (kitEmpty) kitEmpty.classList.toggle('hidden', shown > 0 || tokens.length === 0);
        });
    }
    if (unitsEditor && orbatBuilder) {
        unitsEditor.addEventListener('change', function () {
            try {
                var j = JSON.parse(unitsEditor.value);
                if (Array.isArray(j)) orbatBuilder.loadUnits(j);
            } catch (e) { /* ignore */ }
        });
    }

    form.addEventListener('submit', function (ev) {
        if (nameInput && nameInput.value.trim() === '') {
            ev.preventDefault();
            showStep(1);
            validateStep(1);
            return;
        }
        if (customCommunitySlugCb && !customCommunitySlugCb.checked && communitySlugInput) {
            communitySlugInput.value = '';
        }
        syncUnitsFromOrgStep();
        if (unitsEditor && unitsHidden) unitsEditor.value = unitsHidden.value;
        var fr = document.getElementById('founder-grade-fr');
        var us = document.getElementById('founder-grade-us');
        var sys = form.querySelector('input[name="wizard_grade_system_code"]:checked');
        if (sys && fr && us) {
            if (sys.value === 'US_CLASSIC') {
                fr.disabled = true;
                us.disabled = false;
                fr.name = '';
                us.name = 'wizard_founder_grade_id';
            } else {
                fr.disabled = false;
                us.disabled = true;
                us.name = '';
                fr.name = 'wizard_founder_grade_id';
            }
        }
    });

    var gradeRadios = form.querySelectorAll('[data-grade-system]');
    var previewFr = document.getElementById('preview-fr');
    var previewUs = document.getElementById('preview-us');
    gradeRadios.forEach(function (r) {
        r.addEventListener('change', function () {
            var us = r.value === 'US_CLASSIC';
            if (previewFr) previewFr.classList.toggle('opacity-60', us);
            if (previewUs) previewUs.classList.toggle('opacity-60', !us);
            var frSel = document.getElementById('founder-grade-fr');
            var usSel = document.getElementById('founder-grade-us');
            if (frSel && usSel) {
                frSel.classList.toggle('hidden', us);
                usSel.classList.toggle('hidden', !us);
            }
        });
    });

    var customRoleIdx = 0;
    var tplCustomRole = document.getElementById('wizard-custom-role-tpl');
    var customRolesContainer = document.getElementById('wizard-custom-roles-container');
    var btnAddCustomRole = document.getElementById('wizard-add-custom-role');
    function appendCustomRoleRow() {
        if (!tplCustomRole || !customRolesContainer) return;
        if (customRoleIdx >= 15) {
            window.alert('Maximum 15 rôles supplémentaires.');
            return;
        }
        var frag = tplCustomRole.content.cloneNode(true);
        var row = frag.querySelector('.wizard-custom-role-row');
        if (!row) return;
        var idx = customRoleIdx;
        customRoleIdx += 1;
        row.querySelectorAll('[data-role-field]').forEach(function (inp) {
            var f = inp.getAttribute('data-role-field');
            inp.name = 'wizard_custom_roles[' + idx + '][' + f + ']';
        });
        row.querySelectorAll('.custom-perm-cb').forEach(function (cb) {
            cb.name = 'wizard_custom_roles[' + idx + '][perms][]';
            cb.value = cb.getAttribute('data-perm-slug') || '';
        });
        var rm = row.querySelector('[data-remove-row]');
        if (rm) rm.addEventListener('click', function () { row.remove(); });
        customRolesContainer.appendChild(frag);
    }
    if (btnAddCustomRole) btnAddCustomRole.addEventListener('click', appendCustomRoleRow);

    form.querySelectorAll('input[type="file"][name="wizard_logo_file"], input[type="file"][name="wizard_public_banner_file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var wrap = form.querySelector('.wizard-img-preview[data-preview-for="' + input.name + '"]');
            if (!wrap || !input.files || !input.files[0]) return;
            var url = URL.createObjectURL(input.files[0]);
            var img = wrap.querySelector('img');
            if (img) img.src = url;
            wrap.classList.remove('hidden');
        });
    });

    var wizardDraft = <?= json_encode($communityCreateDraft, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var resumeWizardStep = <?= (int) $resumeWizardStep ?>;

    function restoreWizardDraft() {
        if (!wizardDraft || typeof wizardDraft !== 'object') return;
        Object.keys(wizardDraft).forEach(function (key) {
            if (key === 'wizard_custom_roles' || key.indexOf('wizard_custom_roles[') === 0) return;
            var val = wizardDraft[key];
            if (val === null || val === undefined) return;
            var nodes = form.querySelectorAll('[name="' + key.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
            if (!nodes.length) return;
            var first = nodes[0];
            if (first.type === 'radio') {
                nodes.forEach(function (n) { n.checked = (String(n.value) === String(val)); });
            } else if (first.type === 'checkbox') {
                if (nodes.length === 1) {
                    first.checked = val === '1' || val === 1 || val === true || val === 'on';
                }
            } else {
                first.value = String(val);
            }
        });
        if (wizardDraft.wizard_custom_community_slug) {
            if (customCommunitySlugCb) customCommunitySlugCb.checked = true;
            syncCommunitySlugUi();
        }
        if (wizardDraft.wizard_units_json && unitsHidden) {
            unitsHidden.value = String(wizardDraft.wizard_units_json);
            if (orbatBuilder) {
                try { orbatBuilder.loadUnits(JSON.parse(String(wizardDraft.wizard_units_json))); } catch (e) { /* ignore */ }
            }
        }
        if (wizardDraft.wizard_grade_system_code) {
            var gsRadio = form.querySelector('input[name="wizard_grade_system_code"][value="' + wizardDraft.wizard_grade_system_code + '"]');
            if (gsRadio) gsRadio.checked = true;
        }
        if (wizardDraft.wizard_catalog_kit_code !== undefined) {
            var kitRadio = form.querySelector('input[name="wizard_catalog_kit_code"][value="' + String(wizardDraft.wizard_catalog_kit_code) + '"]');
            if (kitRadio) kitRadio.checked = true;
        }
        if (wizardDraft.wizard_founder_commands_root !== undefined) {
            var founderCb = document.getElementById('wizard-founder-commands-root');
            if (founderCb) {
                var fv = wizardDraft.wizard_founder_commands_root;
                founderCb.checked = fv === '1' || fv === 1 || fv === true || fv === 'on';
            }
        }
        syncRegistrationModeUi();
        syncPaid();
        if (window.CommunityUnitAffiliation && typeof window.CommunityUnitAffiliation.restore === 'function') {
            window.CommunityUnitAffiliation.restore(wizardDraft);
        }
    }

    function syncFoundingUi() {
        var wrap = document.getElementById('wizard-org-founding-date-wrap');
        if (!wrap) return;
        var choice = form.querySelector('input[name="wizard_org_founding_choice"]:checked');
        wrap.classList.toggle('hidden', !(choice && choice.value === 'older'));
    }
    form.querySelectorAll('[data-founding-choice]').forEach(function (el) {
        el.addEventListener('change', syncFoundingUi);
    });

    var tutorial = document.getElementById('cc-tutorial');
    var tutorialIdx = 0;
    var tutorialSlides = tutorial ? tutorial.querySelectorAll('[data-cc-slide]') : [];
    function renderTutorial() {
        tutorialSlides.forEach(function (sl, i) { sl.classList.toggle('hidden', i !== tutorialIdx); });
        var count = tutorial.querySelector('[data-cc-tutorial-count]');
        if (count) count.textContent = (tutorialIdx + 1) + ' / ' + tutorialSlides.length;
        var prev = tutorial.querySelector('[data-cc-tutorial-prev]');
        var next = tutorial.querySelector('[data-cc-tutorial-next]');
        if (prev) prev.disabled = tutorialIdx === 0;
        if (next) next.textContent = tutorialIdx >= tutorialSlides.length - 1 ? 'Commencer' : 'Suivant';
    }
    function openTutorial() {
        if (!tutorial) return;
        tutorialIdx = 0;
        renderTutorial();
        tutorial.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        var nb = tutorial.querySelector('[data-cc-tutorial-next]');
        if (nb) nb.focus();
    }
    function closeTutorial() {
        if (!tutorial) return;
        tutorial.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        try { window.localStorage.setItem('athena.ccTutorialSeen', '1'); } catch (e) { /* stockage indisponible */ }
    }
    if (tutorial) {
        tutorial.querySelectorAll('[data-cc-tutorial-close]').forEach(function (el) { el.addEventListener('click', closeTutorial); });
        var tPrev = tutorial.querySelector('[data-cc-tutorial-prev]');
        var tNext = tutorial.querySelector('[data-cc-tutorial-next]');
        if (tPrev) tPrev.addEventListener('click', function () { tutorialIdx = Math.max(0, tutorialIdx - 1); renderTutorial(); });
        if (tNext) tNext.addEventListener('click', function () {
            if (tutorialIdx >= tutorialSlides.length - 1) {
                closeTutorial();
                showStep(1);
                if (nameInput) nameInput.focus();
                return;
            }
            tutorialIdx += 1;
            renderTutorial();
        });
        tutorial.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeTutorial();
            if (e.key === 'ArrowRight' && tNext) tNext.click();
            if (e.key === 'ArrowLeft' && tPrev && !tPrev.disabled) tPrev.click();
        });
    }
    document.querySelectorAll('[data-cc-tutorial-open]').forEach(function (b) { b.addEventListener('click', openTutorial); });

    restoreWizardDraft();
    syncFoundingUi();
    syncSlugPreview();
    showStep(resumeWizardStep);

    var hasDraft = wizardDraft && typeof wizardDraft === 'object' && Object.keys(wizardDraft).length > 0;
    var tutorialSeen = false;
    try { tutorialSeen = window.localStorage.getItem('athena.ccTutorialSeen') === '1'; } catch (e) { tutorialSeen = true; }
    if (!hasDraft && !tutorialSeen && resumeWizardStep === 1) openTutorial();
})();
</script>
