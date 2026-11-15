<?php
declare(strict_types=1);
/**
 * Formulaire « Placer un avis de recherche » sur une identité fictive. Fait de jeu de rôle : aucune donnée réelle.
 * Quatre sections en onglets : essentiel, signalement physique, pièces jointes, émission (service, dates, récapitulatif). Sélecteur de codes en
 * tiroir, modèles d’avis, aperçu en direct, bouton « Placer l’avis » collant. Styles préfixés « wc- » (assets/css/sse-wanted-codes.css),
 * comportement dans assets/js/sse-wanted-form.js.
 *
 * @var array<string,mixed> $identity
 * @var array<string,mixed> $old valeurs saisies (en cas d’erreur)
 * @var list<string> $errors
 * @var array<string,mixed> $wantedConfig
 * @var array<string, array{0:string,1:string}> $wantedTypes
 * @var array<string,mixed>|null $active
 * @var array<string,mixed> $codesData données du sélecteur de codes
 * @var array<string,mixed> $physicalDefaults signalement tiré de la fiche
 * @var list<array<string,mixed>> $libraryPhotos
 */
use App\Core\Csrf;
use App\Services\Sse\SseWantedAttachments;
use App\Support\SseWantedCatalog as W;
use App\Support\SseUsClassification;
use App\Support\SseWantedPhysical as P;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$lang = (string) ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$wantedConfig = is_array($wantedConfig ?? null) ? $wantedConfig : [];
$wantedTypes = is_array($wantedTypes ?? null) ? $wantedTypes : W::types();
$codesData = is_array($codesData ?? null) ? $codesData : ['items' => [], 'templates' => [], 'defaults' => []];
$physicalDefaults = is_array($physicalDefaults ?? null) ? $physicalDefaults : [];
$libraryPhotos = is_array($libraryPhotos ?? null) ? $libraryPhotos : [];
$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$phys = is_array($old['physical'] ?? null) ? $old['physical'] : $physicalDefaults;
$physVal = static fn (string $k): string => (string) ($phys[$k] ?? '');
$iid = (int) $identity['id'];
$back = url('atak/sse/papiers/identites/' . $iid);
$isMember = !empty($isMemberIdentity);
$serviceData = is_array($serviceData ?? null) ? $serviceData : ['items' => [], 'families' => [], 'default' => '', 'last' => '', 'classes' => []];
$serviceItems = $serviceData['items'];
$serviceFamilies = $serviceData['families'];
$svcSelected = array_key_exists('service_code', $old) ? (string) $old['service_code'] : (string) ($serviceData['default'] !== '' ? $serviceData['default'] : $serviceData['last']);
$svcPre = null;
foreach ($serviceItems as $it) {
    if ($it['code'] === $svcSelected) {
        $svcPre = $it['prefill'];
    }
}
if ($svcPre === null) {
    $svcSelected = '';
}
$wsPropose = !empty($wantedConfig['process']['validation']) && !empty($serviceStoreReady) && isset($rights) && is_object($rights) && !$rights->canAdmin();
$tenantKey = isset($rights) && is_object($rights) ? (int) $rights->tenantId() : 0;
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-wanted-services.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-papers.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-identity-dossier.css')) . '"><link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-wanted-codes.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-wanted-form.js')) . '" defer></script><script src="' . $h(asset_url('assets/js/sse-wanted-services.js')) . '" defer></script>';
$clientState = [
    'lang' => $lang,
    'selected' => ['r' => array_values(is_array($old['codes_reasons'] ?? null) ? $old['codes_reasons'] : []), 'm' => array_values(is_array($old['codes_measures'] ?? null) ? $old['codes_measures'] : [])],
    'hasOld' => $old !== [], 'template' => $val('codes_template'), 'physicalDefaults' => $physicalDefaults,
    'max' => 6, 'maxBytes' => 5 * 1024 * 1024, 'storageKey' => 'wc-recent-' . $tenantKey,
];
$tabs = [
    'essential' => ['Essentiel', 'Type, niveau de menace, motifs, mesures à prendre et dernier lieu connu.'],
    'physical' => ['Signalement', 'Description physique propre à cet avis : une copie datée, la fiche garde la sienne.'],
    'files' => ['Pièces jointes', 'Jusqu’à six images : portrait, signe distinctif, véhicule, lieu.'],
    'service' => ['Émission', 'Service demandeur fictif, dates et récapitulatif avant de placer l’avis.'],
];
?>
<div class="page-heading">
    <div>
        <h1>Placer un avis de recherche</h1>
        <p><span translate="no"><?= $h($identity['display_name']) ?></span> · <span translate="no"><?= $h($identity['ref']) ?></span></p>
    </div>
    <div class="page-reference"><a class="link" href="<?= $h($back) ?>">Retour à la fiche</a></div>
</div>

<?php if ($errors !== []): ?>
    <div class="dos-errors" role="alert" data-wc-errors><strong>L’avis n’a pas été enregistré.</strong><ul><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (empty($wantedReady)): ?>
    <div class="dos-note dos-note--info">Les avis de recherche seront disponibles dès que l’étape de base de données « avis de recherche » aura été passée par l’équipe technique.</div>
<?php elseif (is_array($active ?? null)): ?>
    <div class="dos-note">Cette identité fait déjà l’objet de l’avis <strong translate="no"><?= $h($active['ref']) ?></strong>. <a class="link" href="<?= $h($back . '#avis') ?>">Lever cet avis</a> avant d’en placer un autre.</div>
<?php else: ?>
    <?php if (empty($extrasReady)): ?>
        <div class="dos-note dos-note--info">La codification, le signalement et les pièces jointes seront disponibles dès que l’étape de base de données « codification des avis » aura été passée : l’avis se place en attendant, avec son texte seul.</div>
    <?php endif; ?>
    <form class="wc-form" id="wc-form" method="post" enctype="multipart/form-data" action="<?= $h(url('atak/sse/papiers/identites/' . $iid . '/avis')) ?>" data-wc-form>
        <?= Csrf::field() ?>
        <script type="application/json" id="wc-data"><?= json_encode($codesData + ['state' => $clientState], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <script type="application/json" id="ws-data"><?= json_encode([
            'lang' => $lang, 'families' => $serviceFamilies, 'items' => $serviceItems, 'default' => $serviceData['default'], 'last' => $serviceData['last'], 'classes' => $serviceData['classes'],
            'nowLocal' => (string) ($nowLocal ?? ''), 'tz' => (string) ($timezoneName ?? 'UTC'), 'hasOld' => $old !== [],
            'subject' => (string) ($identity['display_name'] ?? '') . ' · ' . (string) ($identity['ref'] ?? ''), 'hasActive' => !empty($hasActiveNotice), 'isMember' => $isMember, 'selected' => $svcSelected, 'propose' => $wsPropose,
            'draftKey' => 'ws-draft-' . $tenantKey . '-' . $iid, 'recentKey' => 'ws-service-' . $tenantKey,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <div id="ws-labels" hidden>
            <span data-k="other">Autre… (saisie ponctuelle)</span>
            <span data-k="search">Rechercher un service, un sigle ou un code</span>
            <span data-k="none">Aucun service ne correspond.</span>
            <span data-k="pick">Choisir un service</span>
            <span data-k="role">Rôle</span>
            <span data-k="contact">Coordonnées</span>
            <span data-k="max">Classification maximale</span>
            <span data-k="custom">Service de la communauté</span>
            <span data-k="catalogue">Catalogue</span>
            <span data-k="last">Dernier service utilisé</span>
            <span data-k="class_ok">Ce service peut émettre à ce niveau.</span>
            <span data-k="class_bad">Ce service ne peut pas émettre à ce niveau (maximum : %1).</span>
            <span data-k="r_subject">Sujet</span>
            <span data-k="r_type">Type d’avis</span>
            <span data-k="r_threat">Menace</span>
            <span data-k="r_armed">Armé et dangereux</span>
            <span data-k="r_reasons">Motifs</span>
            <span data-k="r_measures">Mesures</span>
            <span data-k="r_zone">Zone</span>
            <span data-k="r_service">Service</span>
            <span data-k="r_class">Classification</span>
            <span data-k="r_issued">Émission</span>
            <span data-k="r_expires">Expiration</span>
            <span data-k="r_files">Pièces jointes</span>
            <span data-k="r_none">Aucun</span>
            <span data-k="r_free">Saisie libre</span>
            <span data-k="r_now">maintenant</span>
            <span data-k="r_noexp">sans expiration</span>
            <span data-k="r_images">%1 image(s)</span>
            <span data-k="r_codes">%1 code(s)</span>
            <span data-k="blocking">À corriger avant de placer l’avis</span>
            <span data-k="warning">À vérifier</span>
            <span data-k="ready">Rien à signaler : l’avis peut être placé.</span>
            <span data-k="c_member">Cette identité est celle d’un membre : aucun avis n’est placé sur un membre réel.</span>
            <span data-k="c_active">Cette identité fait déjà l’objet d’un avis actif : levez-le ou renouvelez-le.</span>
            <span data-k="c_reason">Aucun motif : choisissez un code de motif ou écrivez la raison de l’avis.</span>
            <span data-k="c_expiry_order">La date d’expiration précède la date d’émission.</span>
            <span data-k="c_expiry_past">La date d’expiration est déjà passée : l’avis expirerait dès sa pose.</span>
            <span data-k="c_service_class">Le service ne peut pas émettre à ce niveau de classification (maximum : %1).</span>
            <span data-k="c_armed_zone">Avis « armé et dangereux » sans pays ni zone.</span>
            <span data-k="c_service_threat">Ce niveau de menace est inhabituel pour ce service.</span>
            <span data-k="c_service_type">Ce type d’avis est inhabituel pour ce service.</span>
            <span data-k="c_type_threat">Un avis de ce type porte d’ordinaire une menace élevée ou critique.</span>
            <span data-k="c_no_expiry">Aucune date d’expiration alors que ce service émet d’ordinaire pour %1 jours.</span>
            <span data-k="c_long_expiry">Validité de plus d’un an : prévoyez plutôt un renouvellement.</span>
            <span data-k="draft_restored">Brouillon retrouvé : vos dernières saisies ont été rétablies.</span>
            <span data-k="draft_clear">Effacer le brouillon</span>
            <span data-k="draft_cleared">Brouillon effacé.</span>
            <span data-k="go_blocked">Corrigez les points bloquants du récapitulatif pour placer l’avis.</span>
            <span data-k="go_issues">%1 point(s) à corriger</span>
        </div>
        <div id="wc-labels" hidden>
            <span data-k="selected">%1 sélectionné(s)</span>
            <span data-k="none">Aucun code</span>
            <span data-k="remove">Retirer le code</span>
            <span data-k="reasons">Codes de motifs</span>
            <span data-k="measures">Codes de mesures à prendre</span>
            <span data-k="noresult">Aucun code ne correspond.</span>
            <span data-k="threat">Menace suggérée : %1</span>
            <span data-k="apply">Appliquer</span>
            <span data-k="toolong">Le texte dépasse la limite : le dernier code n’a pas été ajouté.</span>
            <span data-k="badtype">Image refusée : JPEG, PNG ou WebP seulement.</span>
            <span data-k="toobig">Image refusée : 5 Mo au plus.</span>
            <span data-k="toomany">Six images au plus.</span>
            <span data-k="preview_empty">Rien à afficher pour l’instant.</span>
            <span data-k="images">%1 image(s)</span>
            <span data-k="codes">%1 code(s)</span>
            <span data-k="template_done">Modèle appliqué : %1</span>
            <span data-k="main">Principale</span>
            <span data-k="lbl_type">Type</span>
            <span data-k="lbl_threat">Menace</span>
            <span data-k="lbl_armed">Armé et dangereux</span>
            <span data-k="lbl_reasons">Motifs</span>
            <span data-k="lbl_measures">Mesures à prendre</span>
            <span data-k="lbl_physical">Signalement</span>
            <span data-k="lbl_place">Dernier lieu connu</span>
            <span data-k="recent">Récents</span>
            <span data-k="chars">%1 / %2 caractères</span>
        </div>

        <nav class="wc-tabs" role="tablist" aria-label="Sections de l’avis" data-wc-tabs>
            <?php $first = true; foreach ($tabs as $key => $tab): ?>
                <button class="wc-tab" type="button" role="tab" id="wc-tab-<?= $h($key) ?>" aria-controls="wc-sec-<?= $h($key) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" data-wc-tab="<?= $h($key) ?>"><?= $h($tab[0]) ?><span class="wc-tab__n" data-wc-count="<?= $h($key) ?>"></span></button>
            <?php $first = false; endforeach; ?>
        </nav>

        <div class="wc-layout">
        <div class="wc-main">
        <section class="wc-sec" id="wc-sec-essential" role="tabpanel" aria-labelledby="wc-tab-essential" data-wc-sec="essential">
            <h2>Essentiel</h2>
            <p class="wc-sec__desc"><?= $h($tabs['essential'][1]) ?></p>
            <div class="wc-row wc-row--3">
                <div>
                    <label for="w-type">Type d’avis</label>
                    <select id="w-type" name="notice_type" required data-wc-type>
                        <?php foreach ($wantedTypes as $k => $labels): ?>
                            <option value="<?= $h($k) ?>" <?= $val('notice_type', 'poi') === $k ? 'selected' : '' ?>><?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="w-threat">Niveau de menace</label>
                    <select id="w-threat" name="threat_level" required data-wc-threat>
                        <?php foreach (W::THREATS as $k => $labels): ?>
                            <option value="<?= $h($k) ?>" <?= $val('threat_level', 'medium') === $k ? 'selected' : '' ?>><?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="w-armed">Armé et dangereux</label>
                    <select id="w-armed" name="armed" data-wc-armed>
                        <?php foreach (W::ARMED as $k => $labels): ?>
                            <option value="<?= $h($k) ?>" <?= $val('armed', 'unknown') === $k ? 'selected' : '' ?>><?= $h($lang === 'en' ? $labels[1] : $labels[0]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="wc-field">
                <div class="wc-field__head">
                    <label for="w-reason">Motifs, charges ou raison de l’intérêt</label>
                    <span class="wc-field__tools">
                        <?php if (($codesData['templates'] ?? []) !== []): ?><button class="btn btn--ghost btn--sm wc-btn" type="button" data-wc-templates>Modèles d’avis</button><?php endif; ?>
                        <button class="btn btn--sm wc-btn" type="button" data-wc-open="r">Choisir des codes</button>
                    </span>
                </div>
                <div class="wc-chips" data-wc-chips="r" aria-live="polite"></div>
                <div class="wc-recent" data-wc-recent="r" hidden></div>
                <textarea id="w-reason" name="reason" required minlength="3" maxlength="<?= (int) W::REASON_MAX ?>" data-wc-text="r"><?= $h($val('reason')) ?></textarea>
                <p class="hint"><span data-wc-counter="r"></span> Choisir un code ajoute sa phrase au texte, que vous modifiez librement. Texte imaginaire, propre au jeu de rôle.</p>
            </div>

            <div class="wc-field">
                <div class="wc-field__head">
                    <label for="w-instructions">Mesures à prendre</label>
                    <span class="wc-field__tools"><button class="btn btn--sm wc-btn" type="button" data-wc-open="m">Choisir des codes</button></span>
                </div>
                <div class="wc-chips" data-wc-chips="m" aria-live="polite"></div>
                <div class="wc-recent" data-wc-recent="m" hidden></div>
                <textarea id="w-instructions" name="instructions" maxlength="<?= (int) W::INSTRUCTIONS_MAX ?>" placeholder="<?= $h($wantedConfig['default_instructions_fr'] ?? '') ?>" data-wc-text="m"><?= $h($val('instructions')) ?></textarea>
                <p class="hint"><span data-wc-counter="m"></span> Vide : la consigne par défaut de la communauté est reprise.</p>
            </div>
            <div class="wc-suggest" data-wc-suggest hidden></div>

            <div class="wc-row wc-row--3">
                <div><label for="w-zone">Pays ou zone</label><input id="w-zone" type="text" name="zone" maxlength="120" value="<?= $h($val('zone')) ?>"></div>
                <div><label for="w-place">Dernier lieu connu</label><input id="w-place" type="text" name="last_seen_place" maxlength="160" value="<?= $h($val('last_seen_place')) ?>" data-wc-place></div>
                <div><label for="w-seen">Date du dernier lieu connu</label><input id="w-seen" type="datetime-local" name="last_seen_at" value="<?= $h($val('last_seen_at')) ?>"></div>
            </div>
            <div data-wc-hidden-inputs></div>
            <input type="hidden" name="codes_template" value="<?= $h($val('codes_template')) ?>" data-wc-template-input>
        </section>

        <section class="wc-sec" id="wc-sec-physical" role="tabpanel" aria-labelledby="wc-tab-physical" data-wc-sec="physical">
            <h2>Signalement physique</h2>
            <p class="wc-sec__desc"><?= $h($tabs['physical'][1]) ?></p>
            <?php if ($isMember): ?>
                <div class="dos-note dos-note--info">Cette identité est celle d’un membre : son signalement n’est pas copié depuis la fiche. Décrivez seulement ce que cet avis doit dire.</div>
            <?php elseif ($physicalDefaults !== [] && $old === []): ?>
                <p class="wc-note">Prérempli depuis la fiche de l’identité. Vos changements ne s’appliquent qu’à cet avis.
                    <button class="btn btn--ghost btn--sm wc-btn" type="button" data-wc-physical-reset>Revenir à la fiche</button></p>
            <?php endif; ?>
            <div class="wc-row wc-row--4">
                <?php
                $selectField = static function (string $field, string $label, array $map) use ($h, $lang, $physVal): void {
                    echo '<div><label for="p-' . $h($field) . '">' . $h($label) . '</label><select id="p-' . $h($field) . '" name="phys_' . $h($field) . '" data-wc-phys="' . $h($field) . '"><option value="">—</option>';
                    foreach ($map as $k => $labels) {
                        echo '<option value="' . $h($k) . '"' . ($physVal($field) === (string) $k ? ' selected' : '') . '>' . $h($labels[$lang === 'en' ? 1 : 0]) . '</option>';
                    }
                    echo '</select></div>';
                };
                $selectField('sex', 'Sexe', P::SEX);
                $selectField('age', 'Âge estimé', P::AGE);
                ?>
                <div><label for="p-height_cm">Taille (cm)</label><input id="p-height_cm" type="number" inputmode="numeric" min="100" max="250" name="phys_height_cm" value="<?= $h($physVal('height_cm')) ?>" data-wc-phys="height_cm"></div>
                <div><label for="p-weight_kg">Poids (kg)</label><input id="p-weight_kg" type="number" inputmode="numeric" min="30" max="250" name="phys_weight_kg" value="<?= $h($physVal('weight_kg')) ?>" data-wc-phys="weight_kg"></div>
                <?php
                $selectField('build', 'Corpulence', P::BUILD);
                $selectField('hair', 'Cheveux', P::HAIR);
                $selectField('facial_hair', 'Pilosité faciale', P::FACIAL_HAIR);
                $selectField('eyes', 'Yeux', P::EYES);
                $selectField('complexion', 'Teint', P::COMPLEXION);
                $selectField('gait', 'Démarche', P::GAIT);
                ?>
            </div>
            <fieldset class="wc-pills">
                <legend>Équipements habituels</legend>
                <?php $eqSel = is_array($phys['equipment'] ?? null) ? $phys['equipment'] : []; foreach (P::EQUIPMENT as $k => $labels): ?>
                    <label class="wc-pill"><input type="checkbox" name="phys_equipment[]" value="<?= $h($k) ?>" <?= in_array($k, $eqSel, true) ? 'checked' : '' ?> data-wc-equipment><span><?= $h($labels[$lang === 'en' ? 1 : 0]) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="wc-row wc-row--2">
                <?php
                $freeField = static function (string $field, string $label, int $max) use ($h, $physVal): void {
                    echo '<div><label for="p-' . $h($field) . '">' . $h($label) . '</label><input id="p-' . $h($field) . '" type="text" name="phys_' . $h($field) . '" maxlength="' . $max . '" value="' . $h($physVal($field)) . '" data-wc-phys="' . $h($field) . '"></div>';
                };
                $freeField('marks', 'Signes particuliers', P::FREE['marks']);
                $freeField('tattoos', 'Tatouages', P::FREE['tattoos']);
                $freeField('scars', 'Cicatrices', P::FREE['scars']);
                $freeField('clothing', 'Vêtements portés', P::FREE['clothing']);
                $freeField('language', 'Langue et accent', P::FREE['language']);
                $freeField('disabilities', 'Handicaps', P::FREE['disabilities']);
                $freeField('notes', 'Autres précisions', P::FREE['notes']);
                ?>
            </div>
            <p class="hint">Aucun état civil : ni nom, ni date de naissance, ni adresse. Les champs courts vont au PDF et au jeu.</p>
        </section>

        <section class="wc-sec" id="wc-sec-files" role="tabpanel" aria-labelledby="wc-tab-files" data-wc-sec="files">
            <h2>Pièces jointes</h2>
            <p class="wc-sec__desc"><?= $h($tabs['files'][1]) ?></p>
            <?php if ($isMember || (!empty($attachLock) && empty($attachLock['allowed']))): ?>
                <div class="dos-note dos-note--info">Cette identité est celle d’un membre (<?= $h(\App\Services\Sse\SseMemberImageGate::LABEL) ?>) : aucune image n’est jointe sans l’autorisation de la hiérarchie du membre.</div>
            <?php elseif (empty($attachmentsReady)): ?>
                <div class="dos-note dos-note--info">Les pièces jointes seront disponibles dès que l’étape de base de données « codification des avis » aura été passée.</div>
            <?php else: ?>
                <div class="wc-attach-head">
                    <span class="wc-note" data-wc-attach-count></span>
                    <button class="btn btn--sm wc-btn" type="button" data-wc-add-file>Ajouter une image</button>
                </div>
                <div class="wc-rows" data-wc-rows></div>
                <template id="wc-row-tpl">
                    <div class="wc-att" data-wc-att>
                        <div class="wc-att__thumb" data-wc-thumb><span>JPEG, PNG, WebP</span></div>
                        <div class="wc-att__fields">
                            <input class="wc-file" type="file" name="att_file[]" accept="image/jpeg,image/png,image/webp" data-wc-file aria-label="Choisir une image">
                            <div class="wc-row wc-row--2">
                                <select name="att_kind[]" aria-label="Type de pièce">
                                    <?php foreach (SseWantedAttachments::KINDS as $k => $labels): ?><option value="<?= $h($k) ?>"><?= $h($labels[$lang === 'en' ? 1 : 0]) ?></option><?php endforeach; ?>
                                </select>
                                <input type="text" name="att_caption[]" maxlength="160" placeholder="Légende" aria-label="Légende">
                            </div>
                            <label class="wc-radio"><input type="radio" name="att_main" value="" data-wc-main><span>Image principale du PDF</span></label>
                        </div>
                        <button class="wc-x" type="button" data-wc-remove-att aria-label="Retirer cette image">×</button>
                    </div>
                </template>
                <?php if ($libraryPhotos !== []): ?>
                    <fieldset class="wc-library">
                        <legend>Joindre depuis la photothèque</legend>
                        <p class="wc-sec__desc">Photos déjà rattachées à cette identité. L’avis garde sa propre copie.</p>
                        <div class="wc-library__grid">
                            <?php foreach ($libraryPhotos as $ph): $pid = (int) $ph['id']; ?>
                                <div class="wc-photo">
                                    <label class="wc-photo__pick">
                                        <input type="checkbox" name="att_photo[]" value="<?= $pid ?>" data-wc-photo>
                                        <img src="<?= $h(\App\Services\Sse\SseSeekPhotoService::photoUrl($ph) ?? '') ?>" alt="<?= $h($ph['caption'] ?? 'Photo #' . $pid) ?>" loading="lazy">
                                        <span class="wc-photo__cap"><?= $h($ph['caption'] ?? 'Photo #' . $pid) ?></span>
                                    </label>
                                    <select name="att_photo_kind[<?= $pid ?>]" aria-label="Type de pièce">
                                        <?php foreach (SseWantedAttachments::KINDS as $k => $labels): ?><option value="<?= $h($k) ?>"><?= $h($labels[$lang === 'en' ? 1 : 0]) ?></option><?php endforeach; ?>
                                    </select>
                                    <label class="wc-radio"><input type="radio" name="att_main" value="ph:<?= $pid ?>" data-wc-main><span>Principale</span></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>
                <p class="hint">JPEG, PNG ou WebP, 5 Mo au plus. Les métadonnées (EXIF, position GPS) sont retirées ; les images restent dans l’espace privé du portail et ne vont jamais au jeu, seulement au PDF.</p>
            <?php endif; ?>
        </section>

        <section class="wc-sec ws-sec" id="wc-sec-service" role="tabpanel" aria-labelledby="wc-tab-service" data-wc-sec="service" data-ws-root>
            <h2>Émission</h2>
            <p class="wc-sec__desc"><?= $h($tabs['service'][1]) ?> Autorité émettrice (fictive) : <strong translate="no" data-ws-authority><?= $h($lang === 'en' ? $wantedConfig['authority_en'] ?? '' : $wantedConfig['authority_fr'] ?? '') ?></strong>. <?php if (!empty($rights) && $rights->canManage()): ?><a class="link" href="<?= $h(url('atak/sse/avis/reglages')) ?>#services">Gérer les services</a><?php endif; ?></p>
            <?php if (empty($serviceStoreReady)): ?>
                <div class="dos-note dos-note--info">Le service choisi est écrit dans l’avis avec son texte ; son code, le circuit de validation et le renouvellement seront disponibles dès que l’étape de base de données « processus des avis » aura été passée.</div>
            <?php endif; ?>

            <div class="ws-service">
                <div class="ws-field">
                    <label for="ws-service">Choisir un service</label>
                    <select id="ws-service" name="service_code" data-ws-select>
                        <option value="">Autre… (saisie ponctuelle)</option>
                        <?php foreach ($serviceFamilies as $famKey => $famName): $famItems = array_filter($serviceItems, static fn (array $i): bool => $i['family'] === $famKey); if ($famItems === []) { continue; } ?>
                            <optgroup label="<?= $h($famName) ?>">
                                <?php foreach ($famItems as $item): ?>
                                    <option value="<?= $h($item['code']) ?>" <?= $svcSelected === $item['code'] ? 'selected' : '' ?>><?= $h($item['name'] . ($item['ac'] !== '' ? ' (' . $item['ac'] . ')' : '')) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint">Choisir un service remplit les coordonnées, la classification, le niveau de menace usuel et la durée de validité. Vous pouvez les ajuster ensuite.</p>
                </div>
                <div class="ws-card" data-ws-card aria-live="polite" hidden></div>
            </div>

            <div class="wc-row wc-row--2" data-ws-free>
                <div><label for="w-contact">Service demandeur (fictif)</label><input id="w-contact" type="text" name="contact_label" maxlength="160" value="<?= $h($val('contact_label', $svcPre['contact_label'] ?? (string) ($wantedConfig['contact_label'] ?? ''))) ?>" data-ws-contact></div>
                <div><label for="w-contact-d">Coordonnées du service (fictives)</label><input id="w-contact-d" type="text" name="contact_detail" maxlength="200" value="<?= $h($val('contact_detail', $svcPre['contact_detail'] ?? (string) ($wantedConfig['contact_detail'] ?? ''))) ?>" data-ws-contact-detail></div>
            </div>

            <div class="wc-row wc-row--3">
                <div>
                    <label for="ws-class">Classification de l’avis</label>
                    <select id="ws-class" name="classification" data-ws-class>
                        <?php foreach (SseUsClassification::codes() as $cc): ?>
                            <option value="<?= $h($cc) ?>" <?= $val('classification', $svcPre['classification'] ?? (string) ($wantedConfig['default_classification'] ?? 'encadrement')) === $cc ? 'selected' : '' ?>><?= $h(SseUsClassification::label($cc, $lang)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="hint ws-hint-class" data-ws-class-msg></p>
                </div>
                <div>
                    <label for="w-issued">Date d’émission</label>
                    <input id="w-issued" type="datetime-local" name="issued_at" value="<?= $h($val('issued_at')) ?>" data-ws-issued>
                    <div class="ws-chips" role="group" aria-label="Raccourcis de la date d’émission"><button class="ws-chip" type="button" data-ws-now>Maintenant</button></div>
                </div>
                <div>
                    <label for="w-expires">Date d’expiration (facultative)</label>
                    <input id="w-expires" type="datetime-local" name="expires_at" value="<?= $h($val('expires_at')) ?>" data-ws-expires>
                    <div class="ws-chips" role="group" aria-label="Raccourcis de la date d’expiration">
                        <button class="ws-chip" type="button" data-ws-plus="7">+7 j</button>
                        <button class="ws-chip" type="button" data-ws-plus="30">+30 j</button>
                        <button class="ws-chip" type="button" data-ws-plus="90">+90 j</button>
                        <button class="ws-chip" type="button" data-ws-noexp>Sans expiration</button>
                    </div>
                    <input type="hidden" name="no_expiry" value="<?= $val('no_expiry') === '1' ? '1' : '' ?>" data-ws-noexp-input>
                </div>
            </div>
            <p class="hint ws-tz">Dates à l’heure de la communauté (<?= $h($timezoneName ?? 'UTC') ?>). Vide : maintenant, et l’expiration par défaut du service.</p>

            <div class="ws-recap" data-ws-recap hidden>
                <h3>Récapitulatif de l’avis</h3>
                <p class="wc-sec__desc">Relisez avant de placer l’avis : les points bloquants sont à corriger, les avertissements laissent passer.</p>
                <ul class="ws-findings" data-ws-findings aria-live="polite"></ul>
                <dl class="ws-recap__list" data-ws-recap-list></dl>
            </div>
        </section>
        </div>

        <aside class="wc-preview" aria-label="Aperçu de l’avis">
            <details class="wc-preview__box" open data-wc-preview-box>
                <summary>Aperçu de l’avis</summary>
                <div class="wc-preview__body" data-wc-preview></div>
            </details>
        </aside>
        </div>

        <div class="wc-bar">
            <span class="wc-bar__status" data-wc-status aria-live="polite"></span>
            <a class="btn btn--ghost" href="<?= $h($back) ?>">Annuler</a>
            <button class="btn btn--danger wc-bar__go" type="submit" data-ws-go><?= $wsPropose ? 'Proposer à la validation' : 'Placer l’avis' ?></button>
        </div>
    </form>

    <div class="wc-drawer" data-wc-drawer hidden>
        <div class="wc-drawer__veil" data-wc-close></div>
        <div class="wc-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="wc-drawer-title" tabindex="-1">
            <header class="wc-drawer__head">
                <h2 id="wc-drawer-title" data-wc-drawer-title>Codes</h2>
                <button class="wc-x" type="button" data-wc-close aria-label="Fermer">×</button>
            </header>
            <div class="wc-drawer__tools">
                <input type="search" data-wc-search placeholder="Tape un code ou un mot" aria-label="Rechercher un code" autocomplete="off">
                <select data-wc-family aria-label="Famille"></select>
            </div>
            <div class="wc-drawer__list" data-wc-list></div>
            <footer class="wc-drawer__foot">
                <span data-wc-drawer-count></span>
                <button class="btn btn--sm" type="button" data-wc-close>Terminé</button>
            </footer>
        </div>
    </div>

    <div class="wc-drawer wc-drawer--modal" data-wc-tpl-modal hidden>
        <div class="wc-drawer__veil" data-wc-close></div>
        <div class="wc-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="wc-tpl-title" tabindex="-1">
            <header class="wc-drawer__head">
                <h2 id="wc-tpl-title">Modèles d’avis</h2>
                <button class="wc-x" type="button" data-wc-close aria-label="Fermer">×</button>
            </header>
            <p class="wc-sec__desc wc-tpl-desc">Une combinaison de codes prête à l’emploi.</p>
            <label class="wc-radio wc-tpl-opt"><input type="checkbox" data-wc-tpl-apply-meta checked><span>Appliquer aussi le type, la menace et l’état armé suggérés</span></label>
            <div class="wc-drawer__list" data-wc-tpl-list></div>
        </div>
    </div>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
