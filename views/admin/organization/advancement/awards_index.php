<?php
declare(strict_types=1);

use App\Support\DecorationCatalog;

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$definitions = is_array($definitions ?? null) ? $definitions : [];
$members = is_array($members ?? null) ? $members : [];
$customMotifs = is_array($customMotifs ?? null) ? $customMotifs : [];
$imagesReady = (bool) ($imagesReady ?? true);
$maxImportFiles = (int) ($maxImportFiles ?? 20);
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

$activeDefs = array_values(array_filter($definitions, static fn (array $d): bool => empty($d['archived_at'])));
$archivedCount = count($definitions) - count($activeDefs);
$withImage = 0;
$branchSuggestions = ['Infanterie', 'Génie', 'Forces spéciales', 'Aviation', 'Santé', 'Transmissions', 'Artillerie', 'Cavalerie', 'Infantry', 'Engineer', 'Special Forces', 'Aviation Branch'];
foreach ($definitions as $d) {
    if (trim((string) ($d['image_path'] ?? '')) !== '') {
        $withImage++;
    }
    $b = trim((string) ($d['branch'] ?? ''));
    if ($b !== '' && !in_array($b, $branchSuggestions, true)) {
        $branchSuggestions[] = $b;
    }
}
$acceptTypes = 'image/png,image/webp,image/jpeg,.png,.webp,.jpg,.jpeg';
?>
<div class="bo-adv rdk" data-rdk>
    <?php include __DIR__ . '/_flash.php'; ?>

    <?php if (!$imagesReady): ?>
        <p class="rdk-notice" role="status">
            <strong>Mise à jour de la base nécessaire.</strong>
            Les insignes et la branche seront enregistrés après le passage de <code>run-migrations.php</code>. En attendant, les décorations restent créables sans image.
        </p>
    <?php endif; ?>

    <datalist id="rdk-branches">
        <?php foreach ($branchSuggestions as $b): ?>
            <option value="<?= $h($b) ?>"></option>
        <?php endforeach; ?>
    </datalist>

    <section id="import" class="bo-adv__panel rdk-import" aria-labelledby="rdk-import-title">
        <div class="rdk-head">
            <div>
                <p class="rdk-kicker">Le plus rapide</p>
                <h2 id="rdk-import-title">Importer des insignes</h2>
                <p>Déposez vos images : chaque fichier devient une décoration nommée d’après le fichier (<span class="rdk-mono">ranger_tab.png</span> → « Ranger Tab »). Vérifiez ou complétez les lignes, puis importez.</p>
            </div>
            <ul class="rdk-facts" aria-label="Conditions d’import">
                <li>PNG, WebP ou JPEG</li>
                <li>Fond transparent conservé</li>
                <li>2 Mo par image</li>
                <li><?= $maxImportFiles ?> images par envoi</li>
            </ul>
        </div>

        <form method="post" action="<?= $h(url('back-office/referentiels/decorations/lot')) ?>" enctype="multipart/form-data" data-rdk-bulk>
            <?= \App\Core\Csrf::field() ?>
            <label class="rdk-drop" data-rdk-drop>
                <input type="file" name="images[]" accept="<?= $h($acceptTypes) ?>" multiple class="rdk-sr" data-rdk-bulk-input<?= $imagesReady ? '' : ' disabled' ?>>
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                <span class="rdk-drop__title">Glissez vos insignes ici</span>
                <span class="rdk-drop__sub">ou <u>choisissez des fichiers</u> sur votre ordinateur</span>
            </label>
            <p class="rdk-error" data-rdk-bulk-error hidden></p>

            <div class="rdk-rows" data-rdk-rows hidden>
                <div class="rdk-rows__head" aria-hidden="true">
                    <span>Insigne</span><span>Nom</span><span>Code</span><span>Branche</span><span>Motif d’attribution</span><span></span>
                </div>
                <ol class="rdk-rows__list" data-rdk-list></ol>
            </div>

            <div class="rdk-bulk-foot" data-rdk-foot hidden>
                <p>Un code déjà présent dans le référentiel met à jour la décoration existante (nouvel insigne, nom, branche).</p>
                <div class="rdk-bulk-foot__actions">
                    <button type="button" class="ath-btn" data-rdk-clear>Tout retirer</button>
                    <button type="submit" class="ath-btn ath-btn--solid" data-rdk-submit>Importer</button>
                </div>
            </div>
        </form>
        <template data-rdk-row-tpl>
            <li class="rdk-row">
                <span class="rdk-thumb rdk-checker"><img alt="" data-f="img"></span>
                <label><span class="rdk-sr">Nom</span><input type="text" name="bulk_name[]" maxlength="180" required data-f="name"></label>
                <label><span class="rdk-sr">Code</span><input type="text" name="bulk_code[]" maxlength="32" class="rdk-mono" data-f="code"></label>
                <label><span class="rdk-sr">Branche</span><input type="text" name="bulk_branch[]" maxlength="80" list="rdk-branches" placeholder="Ex. Infantry" data-f="branch"></label>
                <label><span class="rdk-sr">Motif d’attribution</span><input type="text" name="bulk_criterion[]" placeholder="Ex. Completion of Ranger School" data-f="criterion"></label>
                <button type="button" class="rdk-icon-btn" data-f="remove" aria-label="Retirer cette image">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
                <span class="rdk-row__meta" data-f="meta"></span>
            </li>
        </template>
    </section>

    <div class="bo-adv__grid2">
        <section class="bo-adv__panel" aria-labelledby="rdk-new-title">
            <div class="bo-adv__panel-head">
                <h2 id="rdk-new-title">Nouvelle décoration</h2>
                <p>Une décoration à la fois, avec son insigne. Le code se remplit tout seul à partir du nom.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/referentiels/decorations')) ?>" enctype="multipart/form-data" class="rdk-form" data-rdk-single>
                <?= \App\Core\Csrf::field() ?>
                <label class="rdk-single-drop rdk-checker" data-rdk-single-drop>
                    <input type="file" name="image" accept="<?= $h($acceptTypes) ?>" class="rdk-sr" data-rdk-single-input<?= $imagesReady ? '' : ' disabled' ?>>
                    <img alt="" data-rdk-single-preview hidden>
                    <span class="rdk-single-drop__empty" data-rdk-single-empty>
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/></svg>
                        Insigne
                        <small>Glisser ou cliquer</small>
                    </span>
                </label>
                <div class="rdk-form__fields">
                    <label>
                        <span class="bo-adv__label">Nom</span>
                        <input type="text" name="name" required maxlength="180" placeholder="Ex. Ranger Tab" data-rdk-name>
                    </label>
                    <div class="rdk-form__pair">
                        <label>
                            <span class="bo-adv__label">Code</span>
                            <input type="text" name="code" maxlength="32" placeholder="RANGER_TAB" class="rdk-mono" data-rdk-code>
                        </label>
                        <label>
                            <span class="bo-adv__label">Branche / arme</span>
                            <input type="text" name="branch" maxlength="80" list="rdk-branches" placeholder="Ex. Infantry">
                        </label>
                    </div>
                    <p class="rdk-help" data-rdk-file-note>PNG transparent recommandé, 2 Mo maximum.</p>
                </div>
                <label class="rdk-form__wide">
                    <span class="bo-adv__label">Motif d’attribution</span>
                    <textarea name="award_criterion" rows="2" class="bo-adv__textarea" placeholder="Ex. Completion of Ranger School"></textarea>
                </label>
                <div class="rdk-form__pair rdk-form__wide">
                    <label>
                        <span class="bo-adv__label">Grade de la décoration (facultatif)</span>
                        <input type="text" name="decoration_grade" maxlength="80" placeholder="Ex. Bronze, Croix, Tab">
                    </label>
                    <label>
                        <span class="bo-adv__label">Ordre d’affichage</span>
                        <input type="number" name="sort_order" value="0" min="0">
                    </label>
                </div>
                <div class="rdk-form__wide">
                    <button class="ath-btn ath-btn--solid" type="submit">Créer la décoration</button>
                </div>
            </form>
        </section>
        <section class="bo-adv__panel" aria-labelledby="rdk-grant-title">
            <div class="bo-adv__panel-head">
                <h2 id="rdk-grant-title">Attribuer une citation</h2>
                <p>Attribution réelle : texte, autorité, date. Distinct d’une qualification.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/referentiels/decorations/attribuer')) ?>" class="bo-adv__stack">
                <?= \App\Core\Csrf::field() ?>
                <label>
                    <span class="bo-adv__label">Personnel</span>
                    <select name="personnel_id" required>
                        <option value="">Choisir un membre</option>
                        <?php foreach ($members as $u): ?>
                            <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= $h($personLabel($u)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="bo-adv__label">Décoration</span>
                    <select name="definition_id" required>
                        <option value="">Choisir une décoration</option>
                        <?php foreach ($activeDefs as $d): ?>
                            <option value="<?= (int) ($d['id'] ?? 0) ?>"><?= $h((string) ($d['name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="rdk-form__pair">
                    <label>
                        <span class="bo-adv__label">Autorité</span>
                        <input type="text" name="authority" placeholder="Ex. Chef de corps">
                    </label>
                    <label>
                        <span class="bo-adv__label">Date</span>
                        <input type="date" name="awarded_at" value="<?= $h(date('Y-m-d')) ?>">
                    </label>
                </div>
                <label>
                    <span class="bo-adv__label">Texte de citation</span>
                    <textarea name="citation_text" rows="4" class="bo-adv__textarea" placeholder="Ex. Pour son sang-froid lors de l’opération Atlas…"></textarea>
                </label>
                <button class="ath-btn ath-btn--solid" type="submit">Attribuer</button>
            </form>
        </section>
    </div>

    <section id="referentiel" class="bo-adv__panel" aria-labelledby="rdk-list-title">
        <div class="rdk-list-head">
            <div>
                <h2 id="rdk-list-title">Référentiel <span class="rdk-count"><?= count($activeDefs) ?></span></h2>
                <p class="bo-adv__muted"><?= $withImage ?> avec insigne<?= $archivedCount > 0 ? ' · ' . $archivedCount . ' archivée' . ($archivedCount > 1 ? 's' : '') : '' ?></p>
            </div>
            <?php if ($definitions !== []): ?>
                <label class="rdk-search">
                    <span class="rdk-sr">Rechercher une décoration</span>
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="search" placeholder="Nom, code, branche…" data-rdk-search>
                </label>
            <?php endif; ?>
        </div>

        <?php if ($definitions === []): ?>
            <p class="bo-adv__empty">Aucune décoration dans le référentiel. Importez vos insignes ci-dessus pour commencer.</p>
        <?php else: ?>
            <ul class="rdk-grid" data-rdk-grid>
                <?php foreach ($definitions as $d):
                    $did = (int) ($d['id'] ?? 0);
                    $archived = !empty($d['archived_at']);
                    $img = trim((string) ($d['image_path'] ?? ''));
                    $imgUrl = $img !== '' ? $motifStorage->publicUrl($img) : '';
                    $dName = (string) ($d['name'] ?? '');
                    $dCode = (string) ($d['code'] ?? '');
                    $dBranch = trim((string) ($d['branch'] ?? ''));
                    $dGrade = trim((string) ($d['decoration_grade'] ?? ''));
                    $dCrit = trim((string) ($d['award_criterion'] ?? ''));
                    $holders = (int) ($d['holders_count'] ?? 0);
                    $search = mb_strtolower($dName . ' ' . $dCode . ' ' . $dBranch . ' ' . $dGrade . ' ' . $dCrit, 'UTF-8');
                    ?>
                    <li id="deco-<?= $did ?>" class="rdk-card<?= $archived ? ' is-archived' : '' ?>" data-search="<?= $h($search) ?>">
                        <div class="rdk-card__img rdk-checker">
                            <?php if ($imgUrl !== ''): ?>
                                <img src="<?= $h($imgUrl) ?>" alt="Insigne <?= $h($dName) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="rdk-card__noimg" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($dName, 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="rdk-card__body">
                            <p class="rdk-card__code rdk-mono"><?= $h($dCode) ?></p>
                            <h3><?= $h($dName) ?></h3>
                            <p class="rdk-card__tags">
                                <?php if ($dBranch !== ''): ?><span class="rdk-tag"><?= $h($dBranch) ?></span><?php endif; ?>
                                <?php if ($dGrade !== ''): ?><span class="rdk-tag rdk-tag--soft"><?= $h($dGrade) ?></span><?php endif; ?>
                                <?php if ($archived): ?><span class="rdk-tag rdk-tag--muted">Archivée</span><?php endif; ?>
                                <?php if ($imgUrl === '' && !$archived): ?><span class="rdk-tag rdk-tag--warn">Sans insigne</span><?php endif; ?>
                            </p>
                            <?php if ($dCrit !== ''): ?><p class="rdk-card__crit"><?= $h($dCrit) ?></p><?php endif; ?>
                            <p class="rdk-card__meta"><?= $holders ?> attribution<?= $holders > 1 ? 's' : '' ?></p>
                        </div>
                        <?php if (!$archived): ?>
                            <details class="rdk-card__edit">
                                <summary>Modifier</summary>
                                <form method="post" action="<?= $h(url('back-office/referentiels/decorations/' . $did . '/update')) ?>" enctype="multipart/form-data" class="bo-adv__stack">
                                    <?= \App\Core\Csrf::field() ?>
                                    <label><span class="bo-adv__label">Nom</span><input type="text" name="name" required maxlength="180" value="<?= $h($dName) ?>"></label>
                                    <div class="rdk-form__pair">
                                        <label><span class="bo-adv__label">Code</span><input type="text" name="code" maxlength="32" class="rdk-mono" value="<?= $h($dCode) ?>"></label>
                                        <label><span class="bo-adv__label">Branche</span><input type="text" name="branch" maxlength="80" list="rdk-branches" value="<?= $h($dBranch) ?>"></label>
                                    </div>
                                    <label><span class="bo-adv__label">Motif d’attribution</span><textarea name="award_criterion" rows="2" class="bo-adv__textarea"><?= $h($dCrit) ?></textarea></label>
                                    <div class="rdk-form__pair">
                                        <label><span class="bo-adv__label">Grade</span><input type="text" name="decoration_grade" maxlength="80" value="<?= $h($dGrade) ?>"></label>
                                        <label><span class="bo-adv__label">Ordre</span><input type="number" name="sort_order" min="0" value="<?= (int) ($d['sort_order'] ?? 0) ?>"></label>
                                    </div>
                                    <label>
                                        <span class="bo-adv__label"><?= $imgUrl !== '' ? 'Remplacer l’insigne' : 'Ajouter un insigne' ?></span>
                                        <input type="file" name="image" accept="<?= $h($acceptTypes) ?>"<?= $imagesReady ? '' : ' disabled' ?>>
                                    </label>
                                    <?php if ($imgUrl !== ''): ?>
                                        <label class="bo-adv__check"><input type="checkbox" name="remove_image" value="1"> Retirer l’insigne actuel</label>
                                    <?php endif; ?>
                                    <button class="ath-btn ath-btn--solid" type="submit">Enregistrer</button>
                                </form>
                                <form method="post" action="<?= $h(url('back-office/referentiels/decorations/' . $did . '/archive')) ?>" onsubmit="return confirm('Archiver cette décoration ? Les attributions existantes sont conservées.');" class="rdk-card__archive">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="bo-adv__linkbtn">Archiver</button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="bo-adv__empty" data-rdk-noresult hidden>Aucune décoration ne correspond à cette recherche.</p>
        <?php endif; ?>
    </section>

    <section id="motifs" class="bo-adv__panel">
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
<script src="<?= htmlspecialchars(asset_url('assets/js/referentiel-decorations.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
