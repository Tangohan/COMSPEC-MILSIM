<?php
declare(strict_types=1);

use App\Support\DecorationCatalog;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$definitions = is_array($definitions ?? null) ? $definitions : [];
$members = is_array($members ?? null) ? $members : [];
$customMotifs = is_array($customMotifs ?? null) ? $customMotifs : [];
$patternChoices = is_array($patternChoices ?? null) ? $patternChoices : DecorationCatalog::PATTERN_CHOICES;
$glyphChoices = is_array($glyphChoices ?? null) ? $glyphChoices : [
    '' => 'Aucun',
    'star' => 'Étoile',
    'cross' => 'Croix',
    'wreath' => 'Couronne',
    'circle' => 'Cercle',
];
$personLabel = static function (array $u): string {
    $n = trim((string) ($u['display_name'] ?? ''));

    return $n !== '' ? $n : (string) ($u['username'] ?? ('#' . ($u['id'] ?? '')));
};
$motifStorage = \App\Core\Container::get(\App\Services\Personnel\DecorationMotifStorageService::class);
?>
<div class="bo-adv">
    <?php include __DIR__ . '/_flash.php'; ?>

    <div class="bo-adv__grid2">
        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Nouvelle décoration</h2>
                <p>Référentiel : nom, grade de la décoration, critère d’attribution.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/referentiels/decorations')) ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <input type="text" name="code" required placeholder="Code" maxlength="32">
                <input type="text" name="name" required placeholder="Nom">
                <input type="text" name="decoration_grade" placeholder="Grade de la décoration (ex. Bronze, Croix)">
                <textarea name="award_criterion" rows="3" class="bo-adv__textarea" placeholder="Critère d’attribution"></textarea>
                <input type="number" name="sort_order" value="0" min="0">
                <button class="ath-btn ath-btn--solid" type="submit">Créer</button>
            </form>
        </section>
        <section class="bo-adv__panel">
            <div class="bo-adv__panel-head">
                <h2>Attribuer une citation</h2>
                <p>Attribution réelle : texte, autorité, date. Distinct d’une qualification.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/referentiels/decorations/attribuer')) ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <select name="personnel_id" required>
                    <option value="">Personnel</option>
                    <?php foreach ($members as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h($personLabel($u)) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="definition_id" required>
                    <option value="">Décoration</option>
                    <?php foreach ($definitions as $d): ?>
                        <?php if (!empty($d['archived_at'])) {
                            continue;
                        } ?>
                        <option value="<?= (int) ($d['id'] ?? 0) ?>"><?= $h((string) ($d['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="authority" placeholder="Autorité">
                <input type="date" name="awarded_at" value="<?= $h(date('Y-m-d')) ?>">
                <textarea name="citation_text" rows="4" class="bo-adv__textarea" placeholder="Texte de citation"></textarea>
                <button class="ath-btn ath-btn--solid" type="submit">Attribuer</button>
            </form>
        </section>
    </div>

    <div class="bo-adv__table-wrap">
        <table class="bo-adv__table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Nom</th>
                    <th>Grade</th>
                    <th>Critère</th>
                    <th>Attributions</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definitions as $d): ?>
                    <tr class="<?= !empty($d['archived_at']) ? 'is-archived' : '' ?>">
                        <td class="bo-adv__mono"><?= $h((string) ($d['code'] ?? '')) ?></td>
                        <td><strong><?= $h((string) ($d['name'] ?? '')) ?></strong></td>
                        <td><?= $h((string) ($d['decoration_grade'] ?? '—')) ?></td>
                        <td><?= $h((string) ($d['award_criterion'] ?? '—')) ?></td>
                        <td><?= (int) ($d['holders_count'] ?? 0) ?></td>
                        <td><?= !empty($d['archived_at']) ? 'Archivée' : 'Active' ?></td>
                        <td>
                            <?php if (empty($d['archived_at'])): ?>
                                <form method="post" action="<?= $h(url('back-office/referentiels/decorations/' . (int) ($d['id'] ?? 0) . '/archive')) ?>" onsubmit="return confirm('Archiver cette décoration ?');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="bo-adv__linkbtn">Archiver</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($definitions === []): ?>
                    <tr><td colspan="7" class="bo-adv__empty">Aucune décoration dans le référentiel.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <section id="motifs" class="bo-adv__panel" style="margin-top:1.5rem;">
        <div class="bo-adv__panel-head">
            <h2>Motifs de placard</h2>
            <p>Créez des rubans ou médailles propres à votre communauté : motif coloré du catalogue, ou image importée. Ils apparaîtront ensuite dans le choix des décorations sur chaque fiche.</p>
        </div>

        <form method="post" action="<?= $h(url('back-office/referentiels/decorations/motifs')) ?>" enctype="multipart/form-data" class="bo-adv__stack" data-motif-editor>
            <?= \App\Core\Csrf::field() ?>
            <div class="bo-adv__grid2" style="gap:1rem;">
                <div class="bo-adv__stack">
                    <label>
                        <span class="bo-adv__label">Nom du motif</span>
                        <input type="text" name="motif_name" required maxlength="120" placeholder="Ex. Ruban opération Atlas">
                    </label>
                    <label>
                        <span class="bo-adv__label">Type</span>
                        <select name="motif_type" data-motif-type>
                            <option value="ribbon">Ruban</option>
                            <option value="medal">Médaille</option>
                        </select>
                    </label>
                    <label>
                        <span class="bo-adv__label">Motif de base</span>
                        <select name="pattern_class" data-motif-pattern>
                            <?php foreach ($patternChoices as $class => $label): ?>
                                <option value="<?= $h((string) $class) ?>"><?= $h((string) $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label data-motif-glyph-wrap style="display:none;">
                        <span class="bo-adv__label">Symbole sur la médaille</span>
                        <select name="glyph" data-motif-glyph>
                            <?php foreach ($glyphChoices as $val => $label): ?>
                                <option value="<?= $h((string) $val) ?>"><?= $h((string) $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span class="bo-adv__label">Échelon ou mention (facultatif)</span>
                        <input type="text" name="level_label" maxlength="40" placeholder="Ex. Or, Argent, Bronze">
                    </label>
                    <label>
                        <span class="bo-adv__label">Description (facultatif)</span>
                        <textarea name="motif_description" rows="2" class="bo-adv__textarea" maxlength="400" placeholder="Courte description visible pour l’encadrement"></textarea>
                    </label>
                    <div class="bo-adv__grid2" style="gap:0.75rem;">
                        <label>
                            <span class="bo-adv__label">Couleur 1</span>
                            <input type="color" name="color_1" value="#6e1c1c" data-motif-c1>
                        </label>
                        <label>
                            <span class="bo-adv__label">Couleur 2</span>
                            <input type="color" name="color_2" value="#e8c77e" data-motif-c2>
                        </label>
                        <label>
                            <span class="bo-adv__label">Couleur 3 (facultatif)</span>
                            <input type="color" name="color_3" value="#ffffff" data-motif-c3>
                        </label>
                        <label>
                            <span class="bo-adv__label">Ordre d’affichage</span>
                            <input type="number" name="motif_sort_order" value="0" min="0">
                        </label>
                    </div>
                    <label>
                        <span class="bo-adv__label">Importer une image (facultatif)</span>
                        <input type="file" name="motif_image" accept="image/png,image/jpeg,image/webp" data-motif-file>
                    </label>
                    <p class="text-[11px] text-slate-500">PNG, JPEG ou WebP, 2 Mo maximum. Si une image est fournie, elle remplace le motif de base à l’affichage.</p>
                    <button class="ath-btn ath-btn--solid" type="submit">Créer le motif</button>
                </div>
                <div>
                    <p class="bo-adv__label" style="margin-bottom:0.5rem;">Aperçu</p>
                    <div class="dk-ribbon-card" style="max-width:220px;">
                        <div class="dk-ribbon-swatch dk-rb-svc" data-motif-preview aria-hidden="true"></div>
                        <div class="dk-ribbon-name" data-motif-preview-name>Nouveau motif</div>
                        <span class="dk-fid">Créée par l’organisation</span>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($customMotifs !== []): ?>
            <div class="bo-adv__table-wrap" style="margin-top:1.25rem;">
                <table class="bo-adv__table">
                    <thead>
                        <tr>
                            <th>Aperçu</th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Motif</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customMotifs as $motif):
                            $mid = (int) ($motif['id'] ?? 0);
                            $mName = (string) ($motif['name'] ?? '');
                            $mType = ((string) ($motif['motif_type'] ?? 'ribbon')) === 'medal' ? 'Médaille' : 'Ruban';
                            $mPattern = (string) ($motif['pattern_class'] ?? 'dk-rb-svc2');
                            $mPatternLabel = $patternChoices[$mPattern] ?? 'Motif catalogue';
                            $mImage = trim((string) ($motif['image_path'] ?? ''));
                            $mUrl = $mImage !== '' ? $motifStorage->publicUrl($mImage) : '';
                            $mColors = [];
                            $decodedColors = json_decode((string) ($motif['colors_json'] ?? ''), true);
                            if (is_array($decodedColors)) {
                                foreach ($decodedColors as $hex) {
                                    $hex = strtoupper(trim((string) $hex));
                                    if (preg_match('/^#[0-9A-F]{6}$/', $hex)) {
                                        $mColors[] = $hex;
                                    }
                                }
                            }
                            if ($mUrl !== '') {
                                $swatchClass = 'dk-ribbon-swatch dk-rb-image';
                                $swatchStyle = ' style="background-image:url(\'' . $h($mUrl) . '\')"';
                            } elseif (count($mColors) >= 2) {
                                $stops = [];
                                $n = count($mColors);
                                foreach ($mColors as $i => $hex) {
                                    $from = (int) floor(($i / $n) * 100);
                                    $to = (int) floor((($i + 1) / $n) * 100);
                                    $stops[] = $hex . ' ' . $from . '% ' . $to . '%';
                                }
                                $swatchClass = 'dk-ribbon-swatch';
                                $swatchStyle = ' style="background:linear-gradient(90deg, ' . $h(implode(', ', $stops)) . ')"';
                            } else {
                                $swatchClass = 'dk-ribbon-swatch ' . $mPattern;
                                $swatchStyle = '';
                            }
                            ?>
                        <tr>
                            <td><span class="<?= $h($swatchClass) ?>"<?= $swatchStyle ?> aria-hidden="true"></span></td>
                            <td><strong><?= $h($mName) ?></strong></td>
                            <td><?= $h($mType) ?></td>
                            <td><?= $mUrl !== '' ? 'Image importée' : $h($mPatternLabel) ?></td>
                            <td>
                                <details>
                                    <summary class="bo-adv__linkbtn" style="cursor:pointer;">Modifier</summary>
                                    <form method="post" action="<?= $h(url('back-office/referentiels/decorations/motifs/' . $mid)) ?>" enctype="multipart/form-data" class="bo-adv__stack" style="margin-top:0.75rem;min-width:260px;">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="text" name="motif_name" required value="<?= $h($mName) ?>" maxlength="120">
                                        <select name="motif_type">
                                            <option value="ribbon"<?= $mType === 'Ruban' ? ' selected' : '' ?>>Ruban</option>
                                            <option value="medal"<?= $mType === 'Médaille' ? ' selected' : '' ?>>Médaille</option>
                                        </select>
                                        <select name="pattern_class">
                                            <?php foreach ($patternChoices as $class => $label): ?>
                                                <option value="<?= $h((string) $class) ?>"<?= $mPattern === $class ? ' selected' : '' ?>><?= $h((string) $label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="glyph">
                                            <?php foreach ($glyphChoices as $val => $label): ?>
                                                <option value="<?= $h((string) $val) ?>"<?= ((string) ($motif['glyph'] ?? '')) === (string) $val ? ' selected' : '' ?>><?= $h((string) $label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="level_label" value="<?= $h((string) ($motif['level_label'] ?? '')) ?>" maxlength="40" placeholder="Échelon">
                                        <textarea name="motif_description" rows="2" class="bo-adv__textarea" maxlength="400"><?= $h((string) ($motif['description'] ?? '')) ?></textarea>
                                        <input type="file" name="motif_image" accept="image/png,image/jpeg,image/webp">
                                        <?php if ($mUrl !== ''): ?>
                                            <label style="display:flex;gap:0.5rem;align-items:center;">
                                                <input type="checkbox" name="remove_image" value="1">
                                                Retirer l’image actuelle
                                            </label>
                                        <?php endif; ?>
                                        <input type="number" name="motif_sort_order" value="<?= (int) ($motif['sort_order'] ?? 0) ?>" min="0">
                                        <button class="ath-btn ath-btn--solid" type="submit">Enregistrer</button>
                                    </form>
                                    <form method="post" action="<?= $h(url('back-office/referentiels/decorations/motifs/' . $mid . '/archive')) ?>" onsubmit="return confirm('Retirer ce motif du catalogue ?');" style="margin-top:0.5rem;">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit" class="bo-adv__linkbtn">Retirer</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="bo-adv__empty" style="margin-top:1rem;">Aucun motif personnalisé pour l’instant. Créez-en un ci-dessus.</p>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
  var form = document.querySelector('[data-motif-editor]');
  if (!form) return;
  var typeEl = form.querySelector('[data-motif-type]');
  var patternEl = form.querySelector('[data-motif-pattern]');
  var glyphWrap = form.querySelector('[data-motif-glyph-wrap]');
  var nameEl = form.querySelector('input[name="motif_name"]');
  var preview = form.querySelector('[data-motif-preview]');
  var previewName = form.querySelector('[data-motif-preview-name]');
  var fileEl = form.querySelector('[data-motif-file]');
  var objectUrl = null;

  var c1 = form.querySelector('[data-motif-c1]');
  var c2 = form.querySelector('[data-motif-c2]');
  var c3 = form.querySelector('[data-motif-c3]');

  function refresh() {
    if (!preview) return;
    if (glyphWrap) glyphWrap.style.display = typeEl && typeEl.value === 'medal' ? '' : 'none';
    if (previewName && nameEl) {
      previewName.textContent = nameEl.value.trim() || 'Nouveau motif';
    }
    if (objectUrl) {
      preview.className = 'dk-ribbon-swatch dk-rb-image';
      preview.style.backgroundImage = 'url(\'' + objectUrl + '\')';
      preview.style.background = '';
      return;
    }
    preview.style.backgroundImage = '';
    if (c1 && c2) {
      var colors = [c1.value, c2.value];
      if (c3 && c3.value) colors.push(c3.value);
      var stops = colors.map(function (hex, i) {
        var from = Math.floor((i / colors.length) * 100);
        var to = Math.floor(((i + 1) / colors.length) * 100);
        return hex + ' ' + from + '% ' + to + '%';
      });
      preview.className = 'dk-ribbon-swatch';
      preview.style.background = 'linear-gradient(90deg, ' + stops.join(', ') + ')';
      return;
    }
    var cls = patternEl ? patternEl.value : 'dk-rb-svc';
    preview.className = 'dk-ribbon-swatch ' + cls;
    preview.style.background = '';
  }

  if (typeEl) typeEl.addEventListener('change', refresh);
  if (patternEl) patternEl.addEventListener('change', refresh);
  if (nameEl) nameEl.addEventListener('input', refresh);
  if (c1) c1.addEventListener('input', refresh);
  if (c2) c2.addEventListener('input', refresh);
  if (c3) c3.addEventListener('input', refresh);
  if (fileEl) {
    fileEl.addEventListener('change', function () {
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
      }
      var f = fileEl.files && fileEl.files[0];
      if (f) {
        objectUrl = URL.createObjectURL(f);
      }
      refresh();
    });
  }
  refresh();
})();
</script>
