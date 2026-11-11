<?php
declare(strict_types=1);

use App\Repositories\SseIdCountryRepository as R;
use App\Services\Sse\SseIdentityGeneratorService as G;
use App\Support\SseIdCountryCatalog as K;
use App\Support\SseIdentityCatalog as C;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed>|null $country */
$c = $country ?? null;
$isEdit = $c !== null;
$used = (int) ($used ?? 0);
$format = (string) ($c['number_format'] ?? '@@#######');
$types = $c['doc_types'] ?? array_keys(C::DOC_TYPES);
$langs = $c['languages'] ?? ['fr', 'en'];
// Pays réel stylisé (univers réaliste-simulation) : univers, code MRZ, format et drapeau imposés par le jeu.
$real = !empty($c['real_sim']);
require __DIR__ . '/_subnav.php';
$sseExtraStyles .= '<link rel="stylesheet" href="' . $h(asset_url('assets/css/back-office-member-papers.css')) . '">';
?>

<div class="page-heading">
    <div>
        <h1><?= $isEdit ? $h($c['name']) . ' (' . $h($c['code']) . ')' : 'Nouveau pays émetteur' ?></h1>
        <?php if ($real): ?>
            <p>Pays réel stylisé, réservé à l’univers « Réaliste — simulation » de la communauté. Ses papiers restent des documents de jeu : gabarit générique, mention SIMULATION sur chaque face, aucune armoirie ni motif officiel, photos de personnages du jeu uniquement.</p>
        <?php else: ?>
            <p>Un pays de jeu est toujours imaginaire. Son code ne doit pas être celui d’un pays réel, sauf dans un univers de fiction (SCP, Star Wars…) ou depuis les pays réels stylisés (univers réaliste-simulation).</p>
        <?php endif; ?>
    </div>
</div>

<form class="panel" method="post" enctype="multipart/form-data" action="<?= $h(url('atak/sse/papiers/pays' . ($isEdit ? '/' . (int) $c['id'] : ''))) ?>">
    <?= \App\Core\Csrf::field() ?>
    <div class="panel-header"><div class="panel-title"><span class="panel-index">01</span> Identité du pays</div><?php if ($isEdit): ?><div class="panel-meta"><?= $used ?> papier(s) émis par ce pays</div><?php endif; ?></div>
    <div class="panel-body">
        <div class="lab-form-grid">
            <div class="lab-form-field">
                <label for="name">Nom du pays</label>
                <input id="name" name="name" type="text" maxlength="120" required value="<?= $h($c['name'] ?? '') ?>" placeholder="Ex. République de Karvonie">
            </div>
            <div class="lab-form-field">
                <label for="name_en">Nom en anglais</label>
                <input id="name_en" name="name_en" type="text" maxlength="120" value="<?= $h($c['name_en'] ?? '') ?>">
            </div>
            <div class="lab-form-field">
                <label for="code">Code à trois lettres</label>
                <?php if ($isEdit): ?>
                    <input id="code" type="text" value="<?= $h($c['code']) ?>" disabled translate="no">
                    <small class="muted">Le code ne change plus : il est imprimé sur les papiers déjà émis.</small>
                <?php else: ?>
                    <input id="code" name="code" type="text" maxlength="3" required pattern="[A-Za-z]{3}" autocomplete="off" translate="no" style="text-transform:uppercase">
                    <small class="muted">Inventé, hors codes des pays réels (FRA, USA…).</small>
                <?php endif; ?>
            </div>
            <div class="lab-form-field">
                <label for="mrz_code">Code de la bande de lecture</label>
                <input id="mrz_code" name="mrz_code" type="text" maxlength="3" value="<?= $h($c['mrz_code'] ?? '') ?>" autocomplete="off" translate="no" style="text-transform:uppercase" placeholder="Identique au code"<?= $real ? ' disabled' : '' ?>>
                <?php if ($real): ?><small class="muted">Code de l’OACI, imprimé tel quel (Allemagne : « D<< »).</small><?php endif; ?>
            </div>
            <div class="lab-form-field">
                <label for="theme">Univers</label>
                <select id="theme" name="theme"<?= $real ? ' disabled' : '' ?>>
                    <?php foreach (K::THEMES as $k => $label): ?>
                        <?php if ($k === \App\Support\SseIdRealCountryCatalog::UNIVERSE && !$real) { continue; } ?>
                        <option value="<?= $h($k) ?>" <?= ($c['theme'] ?? 'contemporary') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="authority">Autorité émettrice par défaut</label>
                <input id="authority" name="authority" type="text" maxlength="160" value="<?= $h($c['authority'] ?? '') ?>" placeholder="Ex. Préfecture de la capitale">
            </div>
            <div class="lab-form-field">
                <label for="region">Noms tirés au sort</label>
                <select id="region" name="region">
                    <?php foreach (K::REGIONS as $k => $label): ?>
                        <option value="<?= $h($k) ?>" <?= ($c['region'] ?? '') === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lab-form-field">
                <label for="number_format">Format du numéro</label>
                <input id="number_format" name="number_format" type="text" maxlength="20" required value="<?= $h($format) ?>" autocomplete="off" translate="no" style="font-family:monospace;text-transform:uppercase" data-idm-format<?= $real ? ' disabled' : '' ?>>
                <small class="muted">« # » pour un chiffre, « @ » pour une lettre, le reste est recopié. Exemple : <strong translate="no" data-idm-format-preview><?= $h(G::documentNumber(strtoupper($format), 424242)) ?></strong></small>
            </div>
        </div>

        <fieldset class="lab-form-field" style="margin-top:12px">
            <legend>Papiers délivrés et durée de validité (années)</legend>
            <?php foreach (C::DOC_TYPES as $k => $label): ?>
                <div>
                    <label><input type="checkbox" name="doc_types[]" value="<?= $h($k) ?>" <?= in_array($k, $types, true) ? 'checked' : '' ?>> <?= $h($label) ?></label>
                    <input type="number" name="validity[<?= $h($k) ?>]" min="1" max="50" value="<?= $c !== null ? R::validityFor($c, $k) : ($k === 'driving_license' ? 15 : 10) ?>" aria-label="Durée de validité : <?= $h($label) ?>" style="width:5em">
                </div>
            <?php endforeach; ?>
        </fieldset>

        <fieldset class="lab-form-field" style="margin-top:12px">
            <legend>Langues imprimées sur les papiers</legend>
            <?php foreach (K::LANGUAGES as $k => $label): ?>
                <label style="display:inline-flex;gap:4px;margin-right:12px"><input type="checkbox" name="languages[]" value="<?= $h($k) ?>" <?= in_array($k, $langs, true) ? 'checked' : '' ?>> <?= $h($label) ?></label>
            <?php endforeach; ?>
        </fieldset>

        <div class="lab-form-grid" style="margin-top:12px">
            <?php if ($real): ?>
                <div class="lab-form-field">
                    <span>Drapeau</span>
                    <span><img class="idm-flag" src="<?= $h((string) \App\Support\SseIdRealCountryCatalog::flagDataUri((string) $c['code'])) ?>" alt=""> <small class="muted">Drapeau simple du jeu (bandes de couleur). Aucune image téléversée : pas d’armoiries ni d’emblème officiel.</small></span>
                </div>
            <?php else: ?>
            <div class="lab-form-field">
                <label for="flag">Drapeau ou armoiries (image PNG, JPG ou WebP, 1 Mo)</label>
                <input id="flag" name="flag" type="file" accept="image/png,image/jpeg,image/webp">
                <?php if (($c['flag_path'] ?? null) !== null): ?>
                    <span><img class="idm-flag" src="<?= $h(url($c['flag_path'])) ?>" alt=""> <label><input type="checkbox" name="flag_remove" value="1"> Retirer l’image</label></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="lab-form-field">
                <label for="color_primary">Couleur principale</label>
                <input id="color_primary" name="color_primary" type="color" value="<?= $h($c['color_primary'] ?? '#1F4E79') ?>">
            </div>
            <div class="lab-form-field">
                <label for="color_secondary">Couleur secondaire</label>
                <input id="color_secondary" name="color_secondary" type="color" value="<?= $h($c['color_secondary'] ?? '#F2C14E') ?>">
            </div>
        </div>

        <?php if ($real): ?>
            <p style="margin-top:12px"><label><input type="checkbox" name="fictional" value="1" checked required> Document de jeu : ses papiers portent la mention SIMULATION — DOCUMENT DE JEU et n’ont aucune valeur réelle.</label></p>
        <?php else: ?>
            <p style="margin-top:12px"><label><input type="checkbox" name="fictional" value="1" checked required> Pays fictif : ses papiers portent la mention SIMULATION et n’ont aucune valeur réelle.</label></p>
        <?php endif; ?>

        <div class="idp-actions" style="margin-top:12px">
            <button class="btn" type="submit"><?= $isEdit ? 'Enregistrer' : 'Créer le pays' ?></button>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/papiers/pays')) ?>">Annuler</a>
        </div>
    </div>
</form>
<script>
(function () {
    var input = document.querySelector('[data-idm-format]');
    var out = document.querySelector('[data-idm-format-preview]');
    if (!input || !out) { return; }
    var digits = '4829137506', letters = 'KAVRLMTPZE';
    input.addEventListener('input', function () {
        var f = input.value.toUpperCase(), s = '';
        for (var i = 0; i < f.length; i++) {
            var ch = f.charAt(i);
            s += ch === '#' ? digits.charAt(i % 10) : (ch === '@' ? letters.charAt(i % 10) : ch);
        }
        out.textContent = s || '-';
    });
}());
</script>
<?php
$sseContent = ob_get_clean();
require dirname(__DIR__) . '/_layout.php';
