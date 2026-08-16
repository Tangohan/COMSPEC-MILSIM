<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $form */
/** @var array<string,mixed>|null $model */
/** @var array<string,string> $profiles */
/** @var array<string,string> $complexities */
/** @var array<string,string> $regions */
/** @var array<string,string> $themes */
/** @var array<string,string> $statuses */
/** @var string $templateLabel */
$form = is_array($form ?? null) ? $form : [];
$model = is_array($model ?? null) ? $model : null;
$isEdit = $model !== null;
$action = $isEdit
    ? url('atak/sse/dev/modeles/' . (int) ($model['id'] ?? 0))
    : url('atak/sse/dev/modeles');
$val = static function (string $key, string $default = '') use ($form): string {
    return (string) ($form[$key] ?? $default);
};
$checked = static function (string $key, bool $default = false) use ($form): bool {
    if (!array_key_exists($key, $form)) {
        return $default;
    }
    $v = $form[$key];

    return $v === '1' || $v === 1 || $v === true || $v === 'on';
};
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dev')) ?>">Atelier</a> /
    <a class="link" href="<?= $h(url('atak/sse/dev/modeles')) ?>">Modèles</a> /
    <strong><?= $isEdit ? 'Modifier' : 'Nouveau' ?></strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Atelier // Saisie</div>
        <h1><?= $isEdit ? 'Modifier le modèle' : 'Nouveau modèle de mission' ?></h1>
        <p>
            Définissez le profil, le décor narratif et les listes utiles (alias, messages, documents).
            Ces réglages seront appliqués en jeu lors de la génération SSE.
            <?php if ($templateLabel !== ''): ?>
                <br><em>Point de départ : <?= $h($templateLabel) ?></em>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-reference"><strong>Formulaire</strong> Réf. ATH-SSE-DEV-MDL-EDIT</div>
</div>

<form method="post" action="<?= $h($action) ?>" class="panel lab-device-form">
    <?= \App\Core\Csrf::field() ?>

    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">01</span> Identification</div>
    </div>
    <div class="panel-body lab-form-section">
        <div class="lab-form-grid">
            <div class="lab-form-field lab-form-field--span2">
                <label for="name">Nom du modèle</label>
                <input id="name" name="name" type="text" required maxlength="160" value="<?= $h($val('name')) ?>" autocomplete="off" placeholder="Ex. Chef cellule Nord">
            </div>
            <div class="lab-form-field">
                <label for="author_label">Auteur (indicatif)</label>
                <input id="author_label" name="author_label" type="text" maxlength="120" value="<?= $h($val('author_label')) ?>" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="status">État</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= $val('status', 'draft') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($isEdit): ?>
                <div class="lab-form-field lab-form-field--span2">
                    <label>Référence interne</label>
                    <input type="text" value="<?= $h($val('public_id')) ?>" disabled>
                    <p class="lab-form-help">Identifiant stable pour l’échange avec Arma — non modifiable après création.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">02</span> Profil et décor</div>
    </div>
    <div class="panel-body lab-form-section">
        <div class="lab-form-grid">
            <div class="lab-form-field">
                <label for="profile_code">Profil</label>
                <select id="profile_code" name="profile_code" required>
                    <?php foreach ($profiles as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= $val('profile_code', 'INSURGENT') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="complexity_code">Richesse de détail</label>
                <select id="complexity_code" name="complexity_code" required>
                    <?php foreach ($complexities as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= $val('complexity_code', 'DETAILED') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="region_code">Région</label>
                <select id="region_code" name="region_code" required>
                    <?php foreach ($regions as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= $val('region_code', 'IRAQ') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="theme_code">Thème narratif</label>
                <select id="theme_code" name="theme_code" required>
                    <?php foreach ($themes as $k => $lab): ?>
                        <option value="<?= $h($k) ?>" <?= $val('theme_code', 'weapons_cache') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="network_size">Taille du réseau (contacts)</label>
                <input id="network_size" name="network_size" type="number" min="0" max="40" value="<?= $h($val('network_size', '8')) ?>">
            </div>
            <div class="lab-form-field">
                <label for="noise_probability">Niveau de bruit</label>
                <select id="noise_probability" name="noise_probability">
                    <?php
                    $noiseOpts = [
                        '' => 'Laisser le générateur décider',
                        '0' => 'Aucun bruit',
                        '0.1' => 'Faible',
                        '0.2' => 'Modéré',
                        '0.35' => 'Élevé',
                        '0.5' => 'Très élevé',
                    ];
                    $noiseVal = \App\Services\Sse\SseArmaModelService::snapProbabilityChoice($val('noise_probability'));
                    foreach ($noiseOpts as $k => $lab):
                    ?>
                        <option value="<?= $h($k) ?>" <?= $noiseVal === (string) $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="lab-form-help">Quantité d’informations parasites mélangées au vrai renseignement.</p>
            </div>
            <div class="lab-form-field">
                <label for="false_lead_probability">Fausses pistes</label>
                <select id="false_lead_probability" name="false_lead_probability">
                    <?php
                    $leadOpts = [
                        '' => 'Laisser le générateur décider',
                        '0' => 'Aucune',
                        '0.1' => 'Quelques-unes',
                        '0.2' => 'Modérées',
                        '0.35' => 'Nombreuses',
                        '0.5' => 'Très nombreuses',
                    ];
                    $leadVal = \App\Services\Sse\SseArmaModelService::snapProbabilityChoice($val('false_lead_probability'));
                    foreach ($leadOpts as $k => $lab):
                    ?>
                        <option value="<?= $h($k) ?>" <?= $leadVal === (string) $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="lab-form-help">Pistes trompeuses destinées à complexifier l’exploitation.</p>
            </div>
        </div>

        <div class="lab-form-block" style="margin-top:14px">
            <h3 class="lab-form-subtitle">Contenus à inclure</h3>
            <div class="lab-form-grid">
                <?php
                $flags = [
                    ['include_biometrics', 'Biométrie', true],
                    ['include_phone', 'Téléphone', true],
                    ['include_documents', 'Documents', true],
                    ['include_computer', 'Ordinateur', false],
                ];
                foreach ($flags as [$fname, $flabel, $fdef]):
                ?>
                    <label class="lab-form-field" style="flex-direction:row;align-items:center;gap:8px">
                        <input type="checkbox" name="<?= $h($fname) ?>" value="1" <?= $checked($fname, $fdef) ? 'checked' : '' ?>>
                        <span><?= $h($flabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">03</span> Listes narratives</div>
    </div>
    <div class="panel-body lab-form-section">
        <p class="lab-form-lead">Une entrée par ligne. Laissez vide pour laisser le générateur choisir dans ses banques intégrées.</p>
        <div class="lab-form-grid">
            <div class="lab-form-field lab-form-field--span2">
                <label for="alias_pool_text">Alias / noms de guerre</label>
                <textarea id="alias_pool_text" name="alias_pool_text" rows="4" placeholder="Abu Karim&#10;Al-Rashid"><?= $h($val('alias_pool_text')) ?></textarea>
            </div>
            <div class="lab-form-field lab-form-field--span2">
                <label for="contact_pool_text">Contacts du réseau</label>
                <textarea id="contact_pool_text" name="contact_pool_text" rows="4" placeholder="Farid&#10;Le chauffeur"><?= $h($val('contact_pool_text')) ?></textarea>
            </div>
            <div class="lab-form-field lab-form-field--span2">
                <label for="sms_templates_text">Modèles de messages</label>
                <textarea id="sms_templates_text" name="sms_templates_text" rows="4" placeholder="Réunion confirmée. Point ALPHA."><?= $h($val('sms_templates_text')) ?></textarea>
            </div>
            <div class="lab-form-field lab-form-field--span2">
                <label for="document_templates_text">Modèles de documents</label>
                <textarea id="document_templates_text" name="document_templates_text" rows="4"><?= $h($val('document_templates_text')) ?></textarea>
            </div>
            <div class="lab-form-field lab-form-field--span2">
                <label for="codewords_text">Mots de code</label>
                <textarea id="codewords_text" name="codewords_text" rows="3" placeholder="ORAGE&#10;LUNE"><?= $h($val('codewords_text')) ?></textarea>
            </div>
            <div class="lab-form-field lab-form-field--span2">
                <label for="tags_text">Étiquettes (une par ligne)</label>
                <textarea id="tags_text" name="tags_text" rows="2" placeholder="Irak&#10;Cible prioritaire"><?= $h($val('tags_text')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">04</span> Identité forcée (optionnel)</div>
    </div>
    <div class="panel-body lab-form-section">
        <p class="lab-form-lead">Si renseignés, ces éléments écrasent le tirage aléatoire lors de l’application du modèle.</p>
        <div class="lab-form-grid">
            <div class="lab-form-field">
                <label for="forced_name">Nom civil</label>
                <input id="forced_name" name="forced_name" type="text" value="<?= $h($val('forced_name')) ?>" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="forced_alias">Alias</label>
                <input id="forced_alias" name="forced_alias" type="text" value="<?= $h($val('forced_alias')) ?>" autocomplete="off">
            </div>
            <div class="lab-form-field">
                <label for="forced_nationality">Nationalité</label>
                <input id="forced_nationality" name="forced_nationality" type="text" value="<?= $h($val('forced_nationality')) ?>" autocomplete="off">
            </div>
            <div class="lab-form-field lab-form-field--span2">
                <label for="notes">Notes pour le concepteur de mission</label>
                <textarea id="notes" name="notes" rows="3"><?= $h($val('notes')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="panel-body" style="display:flex;gap:10px;flex-wrap:wrap;padding-top:0">
        <button class="btn" type="submit"><?= $isEdit ? 'Enregistrer les modifications' : 'Créer le modèle' ?></button>
        <a class="btn btn--ghost" href="<?= $h($isEdit ? url('atak/sse/dev/modeles/' . (int) $model['id']) : url('atak/sse/dev/modeles')) ?>">Annuler</a>
    </div>
</form>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
