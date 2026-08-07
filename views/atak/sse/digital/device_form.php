<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,string> $types */
/** @var array<string,string> $profiles */
$types = is_array($types ?? null) ? $types : [];
$profiles = is_array($profiles ?? null) ? $profiles : [];
require __DIR__ . '/_subnav.php';

$flags = [
    ['name' => 'locked', 'label' => 'Verrouillé', 'checked' => false],
    ['name' => 'airplane_mode', 'label' => 'Mode avion', 'checked' => false],
    ['name' => 'network_connected', 'label' => 'Connecté à un réseau', 'checked' => false],
    ['name' => 'encryption_detected', 'label' => 'Chiffrement détecté', 'checked' => false],
    ['name' => 'has_sim', 'label' => 'Carte SIM présente', 'checked' => false],
    ['name' => 'has_memory_card', 'label' => 'Carte mémoire présente', 'checked' => false],
    ['name' => 'has_battery', 'label' => 'Batterie présente', 'checked' => true],
];
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports')) ?>">Supports</a> / <strong>Nouveau</strong></div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire // Saisie</div>
        <h1>Enregistrer un support</h1>
        <p>Identification, état technique et traçabilité de la saisie — un support à la fois, clairement documenté.</p>
    </div>
    <div class="page-reference"><strong>Formulaire</strong> Réf. ATH-SSE-LABNUM-NEW</div>
</div>

<form method="post" action="<?= $h(url('atak/sse/exploitation-numerique/supports')) ?>" class="panel lab-device-form">
    <?= \App\Core\Csrf::field() ?>

    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02.02</span> Identification</div>
    </div>
    <div class="panel-body lab-form-section">
        <p class="lab-form-lead">Caractéristiques physiques du support tel qu’observé à la découverte.</p>
        <div class="lab-form-grid">
            <div class="lab-form-field">
                <label for="device_type">Type de support</label>
                <select id="device_type" name="device_type" required>
                    <?php foreach ($types as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= $k === 'telephone' ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="manufacturer">Fabricant</label>
                <input id="manufacturer" name="manufacturer" type="text" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="model">Modèle</label>
                <input id="model" name="model" type="text" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="serial_number">Numéro de série</label>
                <input id="serial_number" name="serial_number" type="text" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="color">Couleur</label>
                <input id="color" name="color" type="text" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="capacity_label">Capacité</label>
                <input id="capacity_label" name="capacity_label" type="text" placeholder="ex. 128 Go" autocomplete="off">
            </div>
            <div class="lab-form-field lab-form-field--span2">
                <label for="apparent_condition">État apparent</label>
                <input id="apparent_condition" name="apparent_condition" type="text" placeholder="Intact, fissuré, oxydé…" autocomplete="off">
            </div>
        </div>

        <div class="lab-form-block">
            <h3 class="lab-form-subtitle">Contexte de découverte</h3>
            <div class="lab-form-grid">
                <div class="lab-form-field lab-form-field--span2">
                    <label for="discovery_place">Lieu de découverte</label>
                    <input id="discovery_place" name="discovery_place" type="text" autocomplete="off">
                </div>
                <div class="lab-form-field">
                    <label for="mission_label">Mission</label>
                    <input id="mission_label" name="mission_label" type="text" autocomplete="off">
                </div>
                <div class="lab-form-field">
                    <label for="seized_by_label">Opérateur de saisie</label>
                    <input id="seized_by_label" name="seized_by_label" type="text" autocomplete="off">
                </div>
                <div class="lab-form-field">
                    <label for="seal_label">Scellé</label>
                    <input id="seal_label" name="seal_label" type="text" autocomplete="off">
                </div>
                <div class="lab-form-field">
                    <label for="data_profile">Profil de données (simulation)</label>
                    <select id="data_profile" name="data_profile">
                        <option value="">Automatique selon le type</option>
                        <?php foreach ($profiles as $k => $lab): ?>
                            <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02.03</span> État technique</div>
    </div>
    <div class="panel-body lab-form-section">
        <p class="lab-form-lead">État constaté au moment de la prise en charge — pas une analyse approfondie.</p>
        <div class="lab-form-grid lab-form-grid--4">
            <div class="lab-form-field">
                <label for="power_state">Alimentation</label>
                <select id="power_state" name="power_state">
                    <option value="off">Éteint</option>
                    <option value="on">Allumé</option>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="presumed_os">Système supposé</label>
                <input id="presumed_os" name="presumed_os" type="text" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="displayed_time">Heure affichée</label>
                <input id="displayed_time" name="displayed_time" type="text" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="language_label">Langue</label>
                <input id="language_label" name="language_label" type="text" autocomplete="off">
            </div>
        </div>

        <div class="lab-form-block">
            <h3 class="lab-form-subtitle">Indicateurs constatés</h3>
            <div class="lab-form-flags" role="group" aria-label="Indicateurs constatés">
                <?php foreach ($flags as $flag): ?>
                    <label class="sse-check lab-form-flag">
                        <input type="checkbox" name="<?= $h($flag['name']) ?>" value="1"<?= !empty($flag['checked']) ? ' checked' : '' ?>>
                        <span><?= $h($flag['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02.04</span> Traçabilité</div>
    </div>
    <div class="panel-body lab-form-section">
        <p class="lab-form-lead">Horodatage et conditionnement pour la chaîne de possession.</p>
        <div class="lab-form-grid lab-form-grid--2">
            <div class="lab-form-field">
                <label for="discovered_at">Découvert le</label>
                <input id="discovered_at" name="discovered_at" type="datetime-local">
            </div>
            <div class="lab-form-field">
                <label for="seized_at">Saisi le</label>
                <input id="seized_at" name="seized_at" type="datetime-local">
            </div>
            <div class="lab-form-field lab-form-field--wide">
                <label for="packaging_notes">Conditionnement</label>
                <textarea id="packaging_notes" name="packaging_notes" rows="2" placeholder="Sachet, boîte, faraday, étiquette…"></textarea>
            </div>
            <div class="lab-form-field lab-form-field--wide">
                <label for="damage_notes">Dommages</label>
                <textarea id="damage_notes" name="damage_notes" rows="2" placeholder="Impact, humidité, écran cassé…"></textarea>
            </div>
            <div class="lab-form-field lab-form-field--wide">
                <label for="accessories_notes">Accessoires</label>
                <textarea id="accessories_notes" name="accessories_notes" rows="2" placeholder="Chargeur, câble, étui, carte SIM détachée…"></textarea>
            </div>
            <div class="lab-form-field lab-form-field--wide">
                <label for="observations">Observations</label>
                <textarea id="observations" name="observations" rows="3" placeholder="Tout élément utile non couvert ci-dessus"></textarea>
            </div>
        </div>
    </div>

    <div class="panel-body lab-form-actions toolbar-actions">
        <button class="btn" type="submit">Enregistrer le support</button>
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/exploitation-numerique/supports')) ?>">Annuler</a>
    </div>
</form>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
