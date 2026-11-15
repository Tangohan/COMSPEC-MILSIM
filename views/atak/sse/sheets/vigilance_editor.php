<?php
declare(strict_types=1);
/**
 * Émission d'une fiche de vigilance : cinq étapes (niveau, consignes, cadre, service à informer, aperçu et émission), aperçu
 * en direct de l'avertissement, récapitulatif avant émission. Chargé par editor.php pour le modèle « vigilance » ; mêmes champs,
 * mêmes routes (aperçu, brouillon, émission) et mêmes droits que l'éditeur général. Sans JavaScript toutes les étapes
 * s'affichent à la suite et le formulaire reste utilisable.
 *
 * @var array<string, mixed> $subject
 * @var string $kind
 * @var string $model
 * @var string $lang langue du document
 * @var string $level diffusion
 * @var list<string> $levels
 * @var array<string, string> $texts
 * @var list<array<string, mixed>> $textFields
 * @var array<string, mixed> $old
 * @var list<string> $errors
 * @var ?string $denial
 * @var bool $ready
 * @var array<string, mixed> $vig données de SseVigilanceFollow::editorData
 */
use App\Core\Csrf;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseVigilanceFollow;
use App\Support\SseAlertLevel;
use App\Support\SseSheetCatalog as D;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ui = ($readerLang ?? 'fr') === 'en' ? 1 : 0;
$base = url('atak/sse/identites/emission/' . $kind . '/' . (int) ($kind === 'identite' ? $subject['identity_id'] : $subject['person_id']) . '/' . $model);
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-vigilance.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script src="' . $h(asset_url('assets/js/sse-vigilance.js')) . '" defer></script>';
$errors = is_array($errors ?? null) ? $errors : [];
$canIssue = $denial === null && $ready;
$subjectName = (string) ($subject['person']['display_name'] ?? $subject['identity']['display_name'] ?? $subject['ref']);
$vig = is_array($vig ?? null) ? $vig : [];

// État initial : saisie rejouée (erreur, brouillon) ou valeurs d'office.
$lvKey = isset($old['level']) && isset(SseAlertLevel::LEVELS[(string) $old['level']]) ? (string) $old['level'] : SseVigilanceFollow::levelKey((string) $threat, (string) $armed);
$known = [];
foreach (['measures', 'reasons'] as $k) {
    foreach ($vig[$k] ?? [] as $g) {
        foreach ($g['items'] as $it) {
            $known[$k][$it['code']] = true;
        }
    }
}
$selMeasures = is_array($old['measures'] ?? null) ? $old['measures'] : SseAlertLevel::DEFAULT_MEASURES[$lvKey];
$selMeasures = array_values(array_filter($selMeasures, static fn ($c): bool => isset($known['measures'][$c])));
$selReasons = array_values(array_filter(is_array($old['reasons'] ?? null) ? $old['reasons'] : [], static fn ($c): bool => isset($known['reasons'][$c])));
$zone = (string) ($old['zone'] ?? '');
$svcCode = array_key_exists('service', $old) ? (string) $old['service'] : (string) ($vig['defaultService'] ?? '');
$svcOther = (string) ($old['service_other'] ?? '');
$channel = (string) ($old['report_channel'] ?? '');
$delay = isset(SseAlertLevel::DELAYS[(string) ($old['report_delay'] ?? '')]) ? (string) $old['report_delay'] : (string) ($vig['defaultDelay'] ?? 'now');
$days = (int) $validity;
$chipDays = SseVigilanceFollow::SHORTCUT_DAYS;
if (!in_array($days, $chipDays, true)) {
    $chipDays[] = $days;
    sort($chipDays);
}
// Durée choisie : « h6 », « h24 », « h72 » (heures), « until_lifted » (sans échéance) ou un nombre de jours.
$hoursPicked = (int) ($old['validity_hours'] ?? 0);
$validityChoice = ($old['validity'] ?? '') === 'until_lifted' ? 'until_lifted' : (in_array($hoursPicked, D::VALIDITY_HOURS, true) ? 'h' . $hoursPicked : (string) $days);
$stepErrors = $errors !== [];
$data = [
    'levels' => $vig['levels'] ?? [], 'services' => $vig['serviceByCode'] ?? [], 'delays' => $vig['delays'] ?? [], 'maxCodes' => $vig['maxCodes'] ?? 6, 'today' => $vig['today'] ?? gmdate('Y-m-d'),
    'defaultLabel' => $vig['defaultLabel'] ?? '', 'defaultChannel' => $vig['defaultChannel'] ?? '', 'defaultService' => $vig['defaultService'] ?? '',
    'orders' => ['hold' => SseAlertLevel::ORDERS['hold'][$ui], 'order' => SseAlertLevel::ORDERS['order'][$ui]],
    'actionCodes' => SseAlertLevel::ACTION_CODES, 'holdCodes' => SseAlertLevel::HOLD_CODES,
    'langs' => ['fr' => 'Français', 'en' => 'English'], 'ui' => $ui,
    'classes' => array_combine($levels, array_map(static fn (string $l): string => SseRedactionService::levelLabel($l), $levels)),
    'errorStep' => $stepErrors ? (array_filter($errors, static fn ($e): bool => str_contains((string) $e, 'ervice')) !== [] ? 4 : 5) : 0,
];
$steps = [1 => 'Niveau', 2 => 'Consignes', 3 => 'Cadre', 4 => 'Service à informer', 5 => 'Aperçu et émission'];
$shapeSvg = static fn (string $shape, int $size): string => SseVigilanceFollow::shapeSvg($shape, $size);
?>
<div class="page-heading">
    <div>
        <h1>Fiche de vigilance</h1>
        <p><span translate="no"><?= $h($subjectName) ?></span> · <span translate="no"><?= $h($subject['ref']) ?></span></p>
    </div>
    <div class="vig-head__links">
        <a class="link" href="<?= $h($back) ?>">Retour à la fiche</a>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="vig-alert" role="alert"><strong>La fiche n’a pas été émise.</strong><ul><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (is_array($vigIssued ?? null) && !($vigIssued['masked'] ?? true)): ?>
    <section class="vig-done" role="status" aria-live="polite" style="--lv:<?= $h($vigIssued['hex']) ?>;--lv-ink:<?= $h($vigIssued['ink']) ?>">
        <div class="vig-done__main">
            <h2><span class="vig-done__ico"><?= $shapeSvg((string) $vigIssued['shape'], 30) ?></span> <span>Fiche</span> <span translate="no"><?= $h($vigIssued['ref']) ?></span> <span>émise</span></h2>
            <p class="vig-done__desc"><?= $h($vigIssued['title']) ?> · <span>valable jusqu’au</span> <strong><?= $h($vigIssued['until_label']) ?></strong>. <span>La fiche de la personne n’a pas été modifiée.</span></p>
            <div class="vig-done__actions">
                <a id="vig-download" class="vig-btn vig-btn--primary" href="<?= $h($vigIssued['pdf_url']) ?>" data-vig-autodownload="<?= (int) $vigIssued['id'] ?>">Télécharger le PDF</a>
                <a class="vig-btn" href="<?= $h($vigIssued['pdf_url'] . '?mode=impression') ?>" target="_blank" rel="noopener">Imprimer</a>
                <a class="vig-btn" href="<?= $h($back . '#documents-emis') ?>">Voir dans la fiche</a>
            </div>
        </div>
        <?php if ($vigIssued['url'] !== ''): ?>
            <div class="vig-done__link">
                <img src="<?= $h($vigIssued['qr_url']) ?>" alt="QR code de l’adresse de signalement" width="132" height="132">
                <div>
                    <label for="vig-done-url">Adresse de signalement</label>
                    <input id="vig-done-url" type="text" readonly value="<?= $h($vigIssued['url']) ?>">
                    <p class="vig-qr__row"><button type="button" class="vig-btn" data-vig-copy="vig-done-url">Copier le lien</button><span class="vig-copied" data-vig-copied hidden role="status">Lien copié.</span></p>
                </div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($denial !== null): ?>
    <div class="vig-note" role="note"><strong>Vous pouvez préparer la fiche, pas l’émettre.</strong> <span><?= $h($denial) ?></span></div>
<?php elseif (!$ready): ?>
    <div class="vig-note" role="note">Les fiches PDF seront disponibles dès que l’étape de base de données « fiches PDF » aura été passée par l’équipe technique. L’émission reste fermée en attendant.</div>
<?php endif; ?>

<?php if ($canIssue): ?>
    <form class="vig-quick" method="post" action="<?= $h($base . '/rapide') ?>">
        <?= Csrf::field() ?>
        <p><strong>Génération rapide</strong> <span>Une fiche prête à diffuser d’un clic, sans texte personnalisé : niveau tiré de la fiche, consignes d’usage, service par défaut.</span></p>
        <button type="submit" class="vig-btn">Générer sans personnaliser</button>
    </form>
<?php endif; ?>

<form class="vig" id="vig-form" method="post" action="<?= $h($base . '/emettre') ?>" data-vig data-base="<?= $h($base) ?>" data-csrf="<?= $h(Csrf::token()) ?>" data-ready="<?= $ready ? '1' : '0' ?>" data-error-step="<?= (int) $data['errorStep'] ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="draft_id" id="vig-draft-id" value="<?= (int) ($draft['id'] ?? 0) ?>">
    <input type="hidden" name="faces" value="auto">
    <input type="hidden" name="measures_sent" value="1">
    <script type="application/json" id="vig-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <div hidden id="vig-i18n">
        <span data-k="saving">Enregistrement du brouillon…</span><span data-k="saved">Brouillon enregistré</span><span data-k="saveFail">Brouillon non enregistré</span>
        <span data-k="previewLoading">Génération de l’aperçu…</span><span data-k="previewReady">Aperçu à jour</span><span data-k="previewFail">L’aperçu n’a pas pu être généré</span>
        <span data-k="none">Aucune</span><span data-k="default">Par défaut</span><span data-k="of">sur</span><span data-k="days">jours</span><span data-k="until">jusqu’au</span>
        <span data-k="addressSoon">Créée à l’émission</span><span data-k="limit">Maximum atteint : décochez une consigne pour en choisir une autre.</span>
        <span data-k="channelAuto">Contact du service</span><span data-k="noService">Service par défaut de la communauté</span><span data-k="other">Autre service</span>
        <span data-k="noEnd">sans échéance</span><span data-k="untilLifted">Jusqu’à levée</span><span data-k="hours">heures</span><span data-k="empty">Rien de coché</span><span data-k="nameError">Ce nom n’est pas accepté</span><span data-k="copied">Lien copié.</span>
    </div>

    <div class="vig-layout">
        <div class="vig-flow">
            <nav class="vig-steps" aria-label="Étapes de la fiche" role="tablist">
                <?php foreach ($steps as $n => $label): ?>
                    <button type="button" role="tab" class="vig-steps__b<?= $n === 1 ? ' is-on' : '' ?>" id="vig-tab-<?= (int) $n ?>" aria-controls="vig-s<?= (int) $n ?>" aria-selected="<?= $n === 1 ? 'true' : 'false' ?>" data-vig-go="<?= (int) $n ?>"><span class="vig-steps__n"><?= (int) $n ?></span><span class="vig-steps__t"><?= $h($label) ?></span></button>
                <?php endforeach; ?>
            </nav>

            <!-- 1. Niveau -->
            <section class="vig-step is-on" id="vig-s1" role="tabpanel" aria-labelledby="vig-tab-1" data-vig-step="1">
                <h2>Niveau de vigilance</h2>
                <p class="vig-step__desc">Il fixe la couleur, le titre et la conduite d’usage de l’avertissement ; il ne dépend jamais de la couleur seule.</p>
                <div class="vig-levels" role="radiogroup" aria-label="Niveau de vigilance">
                    <?php foreach ($vig['levels'] ?? [] as $l): ?>
                        <label class="vig-level" style="--lv:<?= $h($l['hex']) ?>;--lv-ink:<?= $h($l['ink']) ?>">
                            <input type="radio" name="level" value="<?= $h($l['key']) ?>"<?= $lvKey === $l['key'] ? ' checked' : '' ?> data-vig-level>
                            <span class="vig-level__card">
                                <span class="vig-level__top"><span class="vig-level__ico"><?= $shapeSvg((string) $l['shape'], 44) ?></span><span class="vig-level__rank"><b><?= (int) $l['rank'] ?>/4</b><?= SseVigilanceFollow::pips((int) $l['rank']) ?></span></span>
                                <span class="vig-level__title"><?= $h($l['title']) ?></span>
                                <span class="vig-level__hint"><?= $h($l['hint']) ?></span>
                                <span class="vig-level__check" aria-hidden="true"><svg viewBox="0 0 20 20" width="16" height="16"><path d="M4 10.5l4 4 8-9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="threat" value="<?= $h($threat) ?>" data-vig-threat>
                <input type="hidden" name="armed" value="<?= $h($armed) ?>" data-vig-armed>
            </section>

            <!-- 2. Consignes -->
            <section class="vig-step" id="vig-s2" role="tabpanel" aria-labelledby="vig-tab-2" data-vig-step="2">
                <h2>Consignes et motifs</h2>
                <p class="vig-step__desc">Ce que le terrain doit faire, pourquoi, et vos précisions ; six consignes et six motifs au plus.</p>
                <div class="vig-seg vig-seg--inline" role="tablist" aria-label="Contenu de la fiche">
                    <button type="button" role="tab" class="is-on" aria-selected="true" data-vig-sub="measures">Conduite à tenir <span class="vig-seg__n" data-vig-count="measures">0</span></button>
                    <button type="button" role="tab" aria-selected="false" data-vig-sub="reasons">Motifs <span class="vig-seg__n" data-vig-count="reasons">0</span></button>
                    <button type="button" role="tab" aria-selected="false" data-vig-sub="texts">Précisions</button>
                </div>
                <?php foreach (['measures' => ['Conduite à tenir', 'Les consignes cochées sont imprimées dans l’ordre ; « Ne pas… » en est déduit.'], 'reasons' => ['Motifs', 'Pourquoi cette personne est signalée, avec les codes de la communauté.']] as $kindKey => [$kt, $kd]): ?>
                    <div class="vig-sub<?= $kindKey === 'measures' ? ' is-on' : '' ?>" data-vig-subpanel="<?= $h($kindKey) ?>">
                        <p class="vig-sub__desc"><?= $h($kd) ?></p>
                        <div class="vig-find">
                            <label class="vig-sr" for="vig-find-<?= $h($kindKey) ?>">Filtrer la liste</label>
                            <input id="vig-find-<?= $h($kindKey) ?>" type="search" placeholder="Filtrer la liste" data-vig-find="<?= $h($kindKey) ?>" autocomplete="off">
                            <span class="vig-find__max" data-vig-max="<?= $h($kindKey) ?>" role="status"></span>
                        </div>
                        <?php $sel = $kindKey === 'measures' ? $selMeasures : $selReasons; ?>
                        <?php foreach ($vig[$kindKey] ?? [] as $g): ?>
                            <fieldset class="vig-group" data-vig-group>
                                <legend><?= $h($g['label']) ?></legend>
                                <div class="vig-opts">
                                    <?php foreach ($g['items'] as $it): ?>
                                        <label class="vig-opt" data-vig-opt data-find="<?= $h(mb_strtolower($it['label'] . ' ' . $it['code'], 'UTF-8')) ?>">
                                            <input type="checkbox" name="<?= $h($kindKey) ?>[]" value="<?= $h($it['code']) ?>"<?= in_array($it['code'], $sel, true) ? ' checked' : '' ?> data-vig-code="<?= $h($kindKey) ?>" data-label="<?= $h($it['label']) ?>">
                                            <span class="vig-opt__box" aria-hidden="true"></span>
                                            <span class="vig-opt__t"><b translate="no"><?= $h($it['label']) ?></b><small translate="no"><?= $h($it['code']) ?><?= $it['order'] === 'action' ? ' · ' : '' ?><?php if ($it['order'] === 'action'): ?><em>intervention</em><?php endif; ?></small></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php endforeach; ?>
                        <p class="vig-empty" data-vig-nofind hidden>Aucun résultat pour ce filtre.</p>
                    </div>
                <?php endforeach; ?>
                <div class="vig-sub" data-vig-subpanel="texts">
                    <p class="vig-sub__desc">Texte libre ajouté aux consignes et motifs cochés. Gras : **texte** ; liste : une ligne qui commence par « - ».</p>
                    <?php foreach ($textFields as [$key, $max, $labels, $hints]): ?>
                        <?php $val = (string) ($texts[$key] ?? ''); $fid = 'vig-t-' . $key; ?>
                        <div class="vig-field" data-vig-text data-max="<?= (int) $max ?>">
                            <div class="vig-field__top"><label for="<?= $h($fid) ?>"><?= $h($labels[$ui]) ?></label><span class="vig-count"><span data-count>0</span> / <?= (int) $max ?></span></div>
                            <textarea id="<?= $h($fid) ?>" name="texts[<?= $h($key) ?>]" rows="<?= $max > 600 ? 5 : 3 ?>" maxlength="<?= (int) $max * 2 ?>" data-orig="<?= $h($val) ?>"><?= $h($val) ?></textarea>
                            <p class="vig-hint"><?= $h($hints[$ui]) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- 3. Cadre -->
            <section class="vig-step" id="vig-s3" role="tabpanel" aria-labelledby="vig-tab-3" data-vig-step="3">
                <h2>Zone, durée et diffusion</h2>
                <p class="vig-step__desc">Où l’avertissement s’applique, jusqu’à quand, dans quelle langue et à qui il peut être montré.</p>
                <div class="vig-grid">
                    <div class="vig-fld vig-fld--wide">
                        <label for="vig-zone">Zone concernée</label>
                        <input id="vig-zone" type="text" name="zone" maxlength="120" value="<?= $h($zone) ?>" placeholder="Ex. secteur nord, marché couvert, axe principal" autocomplete="off">
                        <p class="vig-hint">Facultatif. Imprimée sur la fiche.</p>
                    </div>
                    <div class="vig-fld vig-fld--wide">
                        <span class="vig-lbl" id="vig-validity-l">Durée de validité</span>
                        <div class="vig-chipsel" role="radiogroup" aria-labelledby="vig-validity-l">
                            <?php foreach (D::VALIDITY_HOURS as $hh): ?>
                                <label class="vig-chipopt"><input type="radio" name="validity" value="h<?= (int) $hh ?>"<?= $validityChoice === 'h' . $hh ? ' checked' : '' ?> data-vig-validity data-hours="<?= (int) $hh ?>"><span><?= (int) $hh ?> <span>heures</span></span></label>
                            <?php endforeach; ?>
                            <?php foreach ($chipDays as $d): ?>
                                <label class="vig-chipopt"><input type="radio" name="validity" value="<?= (int) $d ?>"<?= $validityChoice === (string) $d ? ' checked' : '' ?> data-vig-validity data-days="<?= (int) $d ?>"><span><?= (int) $d ?> <span>jours</span></span></label>
                            <?php endforeach; ?>
                            <label class="vig-chipopt"><input type="radio" name="validity" value="until_lifted"<?= $validityChoice === 'until_lifted' ? ' checked' : '' ?> data-vig-validity data-lifted="1"><span>Jusqu’à levée</span></label>
                        </div>
                        <p class="vig-hint"><span>Échéance :</span> <strong data-vig-until>—</strong>. <span>Elle peut être renouvelée ou levée avant ; « jusqu’à levée » reste en vigueur jusqu’à ce qu’on la lève.</span></p>
                    </div>
                    <div class="vig-fld">
                        <span class="vig-lbl" id="vig-lang-l">Langue du document</span>
                        <div class="vig-chipsel" role="radiogroup" aria-labelledby="vig-lang-l">
                            <label class="vig-chipopt"><input type="radio" name="lang" value="fr"<?= $lang === 'fr' ? ' checked' : '' ?> data-vig-lang><span>Français</span></label>
                            <label class="vig-chipopt"><input type="radio" name="lang" value="en"<?= $lang === 'en' ? ' checked' : '' ?> data-vig-lang><span>English</span></label>
                        </div>
                    </div>
                    <div class="vig-fld">
                        <label for="vig-class">Diffusion</label>
                        <select id="vig-class" name="classification" data-vig-class>
                            <?php foreach ($levels as $l): ?>
                                <option value="<?= $h($l) ?>" <?= $level === $l ? 'selected' : '' ?>><?= $h(SseRedactionService::levelLabel($l) . ' — ' . \App\Support\SseUsClassification::banner($l)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="vig-hint">Les données au-dessus de ce niveau sont remplacées par une barre dans le PDF.</p>
                    </div>
                </div>
            </section>

            <!-- 4. Service à informer -->
            <section class="vig-step" id="vig-s4" role="tabpanel" aria-labelledby="vig-tab-4" data-vig-step="4">
                <h2>Service à informer</h2>
                <p class="vig-step__desc">Qui reçoit les signalements, par quel canal et sous quel délai ; imprimé sur la fiche avec l’adresse de signalement.</p>
                <div class="vig-grid">
                    <div class="vig-fld vig-fld--wide">
                        <label for="vig-service">Service</label>
                        <select id="vig-service" name="service" data-vig-service>
                            <option value=""<?= $svcCode === '' ? ' selected' : '' ?>>Service par défaut de la communauté<?= ($vig['defaultLabel'] ?? '') !== '' ? ' — ' . $h($vig['defaultLabel']) : '' ?></option>
                            <?php foreach ($vig['services'] ?? [] as $g): ?>
                                <optgroup label="<?= $h($g['label']) ?>">
                                    <?php foreach ($g['items'] as $s): ?>
                                        <option value="<?= $h($s['code']) ?>"<?= $svcCode === $s['code'] ? ' selected' : '' ?>><?= $h($s['name'] . ($s['ac'] !== '' ? ' (' . $s['ac'] . ')' : '')) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                            <option value="OTHER"<?= $svcCode === 'OTHER' ? ' selected' : '' ?>>Autre…</option>
                        </select>
                    </div>
                    <div class="vig-fld vig-fld--wide" data-vig-other hidden>
                        <label for="vig-other">Nom du service</label>
                        <input id="vig-other" type="text" name="service_other" maxlength="120" value="<?= $h($svcOther) ?>" placeholder="Nom du service à informer" autocomplete="off" data-vig-other-input>
                        <p class="vig-hint">Le nom d’une organisation réelle est refusé ; il est contrôlé avant l’émission.</p>
                        <p class="vig-inline-err" data-vig-other-err role="alert" hidden></p>
                    </div>
                    <div class="vig-svc vig-fld--wide" data-vig-svc aria-live="polite">
                        <span class="vig-svc__bar" aria-hidden="true"></span>
                        <div class="vig-svc__body">
                            <strong data-vig-svc-name>—</strong>
                            <span class="vig-svc__desc" data-vig-svc-desc></span>
                            <span class="vig-svc__contact" data-vig-svc-contact></span>
                        </div>
                    </div>
                    <div class="vig-fld vig-fld--wide">
                        <label for="vig-channel">Canal de compte rendu</label>
                        <input id="vig-channel" type="text" name="report_channel" maxlength="200" value="<?= $h($channel) ?>" placeholder="" autocomplete="off" data-vig-channel>
                        <p class="vig-hint">Laissez vide pour reprendre le contact du service.</p>
                    </div>
                    <div class="vig-fld vig-fld--wide">
                        <span class="vig-lbl" id="vig-delay-l">Délai de compte rendu</span>
                        <div class="vig-chipsel" role="radiogroup" aria-labelledby="vig-delay-l">
                            <?php foreach ($vig['delays'] ?? [] as $k => $label): ?>
                                <label class="vig-chipopt"><input type="radio" name="report_delay" value="<?= $h($k) ?>"<?= $delay === $k ? ' checked' : '' ?> data-vig-delay><span><?= $h($label) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 5. Aperçu et émission -->
            <section class="vig-step" id="vig-s5" role="tabpanel" aria-labelledby="vig-tab-5" data-vig-step="5">
                <h2>Aperçu et émission</h2>
                <p class="vig-step__desc">Relisez le récapitulatif et le PDF tel qu’il sera imprimé, puis émettez : la fiche de la personne n’est pas modifiée.</p>
                <div class="vig-review">
                    <dl class="vig-recap" data-vig-recap>
                        <div><dt>Niveau</dt><dd data-r="level"></dd></div>
                        <div><dt>Conduite à tenir</dt><dd data-r="measures"></dd></div>
                        <div><dt>Motifs</dt><dd data-r="reasons"></dd></div>
                        <div><dt>Zone</dt><dd data-r="zone"></dd></div>
                        <div><dt>Validité</dt><dd data-r="validity"></dd></div>
                        <div><dt>Langue et diffusion</dt><dd data-r="doc"></dd></div>
                        <div><dt>Service à informer</dt><dd data-r="service"></dd></div>
                        <div><dt>Canal et délai</dt><dd data-r="channel"></dd></div>
                        <div class="vig-recap__addr"><dt>Adresse de signalement</dt><dd><span data-r="address"></span> <span class="vig-recap__qr"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M3 3h8v8H3zm2 2v4h4V5zm8-2h8v8h-8zm2 2v4h4V5zM3 13h8v8H3zm2 2v4h4v-4zm9-2h2v2h-2zm4 0h3v2h-3zm-4 4h2v4h-2zm4 1h3v3h-3z"/></svg> <span>Avec son QR code, créée à l’émission</span></span></dd></div>
                    </dl>
                    <div class="vig-pdfbox">
                        <div class="vig-pdfbox__bar">
                            <span class="vig-pdfbox__state" data-vig-pdf-state role="status">Aperçu non généré</span>
                            <button type="button" class="vig-btn" data-vig-refresh>Actualiser l’aperçu</button>
                            <button type="submit" class="vig-btn" formaction="<?= $h($base . '/apercu') ?>" formtarget="_blank" formnovalidate>Ouvrir le PDF</button>
                        </div>
                        <iframe class="vig-pdf" title="Aperçu du PDF de la fiche de vigilance" data-vig-pdf></iframe>
                    </div>
                </div>
            </section>

            <div class="vig-nav">
                <button type="button" class="vig-btn" data-vig-prev hidden>Précédent</button>
                <span class="vig-nav__state" id="vig-save-state" role="status"><?= !empty($draft) ? 'Brouillon « ' . $h($draft['title']) . ' »' : ($ready ? 'Brouillon enregistré automatiquement' : 'Brouillons indisponibles') ?></span>
                <?php if ($ready): ?><button type="button" class="vig-btn" data-vig-open="vig-drafts">Brouillons <span class="vig-seg__n"><?= count($drafts) ?></span></button><?php endif; ?>
                <button type="button" class="vig-btn vig-btn--primary" data-vig-next>Suivant</button>
                <?php if ($canIssue): ?><button type="submit" class="vig-btn vig-btn--issue" id="vig-issue" data-vig-issue hidden>Émettre la fiche de vigilance</button><?php endif; ?>
            </div>
        </div>

        <aside class="vig-live" aria-label="Aperçu en direct de l’avertissement" data-vig-live>
            <div class="vig-card" data-vig-card>
                <div class="vig-card__band">
                    <span class="vig-card__ico" data-vig-card-ico></span>
                    <div>
                        <strong class="vig-card__title" data-vig-card-title>—</strong>
                        <span class="vig-card__rank" data-vig-card-rank></span>
                    </div>
                </div>
                <p class="vig-card__order" data-vig-card-order></p>
                <div class="vig-card__body">
                    <p class="vig-card__who" translate="no"><?= $h($subjectName) ?></p>
                    <ul class="vig-card__list" data-vig-card-measures></ul>
                    <dl class="vig-card__facts">
                        <div><dt>Valable</dt><dd data-vig-card-until>—</dd></div>
                        <div><dt>À informer</dt><dd data-vig-card-service>—</dd></div>
                        <div><dt>Délai</dt><dd data-vig-card-delay>—</dd></div>
                    </dl>
                    <p class="vig-card__qr"><svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M3 3h8v8H3zm2 2v4h4V5zm8-2h8v8h-8zm2 2v4h4V5zM3 13h8v8H3zm2 2v4h4v-4zm9-2h2v2h-2zm4 0h3v2h-3zm-4 4h2v4h-2zm4 1h3v3h-3z"/></svg><span>QR code et adresse de signalement créés à l’émission</span></p>
                </div>
            </div>
            <p class="vig-live__note">Aperçu de l’avertissement ; le PDF complet est à l’étape 5.</p>
        </aside>
    </div>

    <?php if ($ready): ?>
        <dialog class="vig-dialog" id="vig-drafts" aria-labelledby="vig-drafts-t">
            <div class="vig-dialog__form">
                <h3 id="vig-drafts-t">Brouillons de la fiche</h3>
                <p class="vig-dialog__desc">Le brouillon est enregistré tout seul pendant la saisie ; il reprend tous les choix de cet écran.</p>
                <label for="vig-draft-title">Nom du brouillon</label>
                <input id="vig-draft-title" type="text" name="draft_title" maxlength="80" value="<?= $h($draft['title'] ?? '') ?>" placeholder="Ex. version pour le briefing">
                <?php if ($drafts !== []): ?>
                    <ul class="vig-drafts">
                        <?php foreach ($drafts as $d): ?>
                            <li>
                                <a class="link" href="<?= $h($base . '?brouillon=' . (int) $d['id']) ?>" translate="no"><?= $h($d['title'] !== '' ? $d['title'] : 'Sans nom') ?></a>
                                <span class="vig-drafts__when"><?= $h(strtoupper($d['lang'])) ?> · <?= $h($d['updated_at']) ?> UTC</span>
                                <button type="submit" class="vig-btn" formmethod="post" formaction="<?= $h($base . '/brouillon/' . (int) $d['id'] . '/supprimer') ?>" formnovalidate data-confirm="Supprimer ce brouillon ?">Supprimer</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="vig-dialog__actions">
                    <a class="vig-btn" href="<?= $h($base) ?>">Nouveau brouillon</a>
                    <button type="button" class="vig-btn" data-vig-save>Enregistrer maintenant</button>
                    <button type="button" class="vig-btn vig-btn--primary" data-vig-close>Fermer</button>
                </div>
            </div>
        </dialog>
    <?php endif; ?>
</form>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
