<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/**
 * Bureau SSE › Production › Gestion des fiches : référentiel des fiches de renseignement de la communauté.
 * Bande d'en-tête (titre, chiffres du référentiel), puis une carte à onglets : Étiquettes, Catégories, Types de fiche, Modèles,
 * Règles, Import / export. Chaque onglet : une ligne de description, un tableau dense triable et filtrable ; création et
 * modification dans des fenêtres. Sans le droit « gérer » : lecture seule. Sans l'étape de base de données : catalogue livré.
 * Styles : sse-fiche-rens.css (.fnr-* : onglets, fenêtres) et sse-fiches-etiquettes.css (.fng-*, .fnt-*).
 * Scripts : sse-fiche-rens.js (onglets, fenêtres) et sse-fiches-etiquettes.js (tri, filtre, remplissage des fenêtres) ; aucun texte.
 *
 * @var array<string, string> $tabs
 * @var list<array<string, mixed>> $tags référentiel complet, avec « usage »
 * @var array{active: int, custom: int, archived: int, disabled: int, notes: int, kinds: int, families: int} $stats
 * @var array<string, array<string, mixed>> $kinds
 * @var array{default_validity: string, precedence: array<string, string>, rel_to: string} $rules
 * @var bool $ready
 * @var bool $canManageRef
 * @var bool $isManager
 * @var string $lang
 */
$tabs = is_array($tabs ?? null) ? $tabs : [];
$tags = is_array($tags ?? null) ? $tags : [];
$stats = (is_array($stats ?? null) ? $stats : []) + ['active' => 0, 'custom' => 0, 'archived' => 0, 'disabled' => 0, 'notes' => 0, 'kinds' => 0, 'families' => 0];
$kinds = is_array($kinds ?? null) ? $kinds : [];
$rules = (is_array($rules ?? null) ? $rules : []) + ['default_validity' => '', 'precedence' => [], 'rel_to' => ''];
$ready = (bool) ($ready ?? false);
$canManageRef = (bool) ($canManageRef ?? false);
$isManager = (bool) ($isManager ?? false);
$lang = ($lang ?? 'fr') === 'en' ? 'en' : 'fr';
$cat = \App\Support\SseFieldNoteTagCatalog::class;
$csrf = \App\Core\Csrf::token();
$base = \App\Controllers\Web\SseFieldNoteAdminController::PATH;
$json = static fn (array $data): string => htmlspecialchars((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$label = static fn (array $tag): string => (string) ($lang === 'en' ? $tag['label_en'] : $tag['label_fr']);
$other = static fn (array $tag): string => (string) ($lang === 'en' ? $tag['label_fr'] : $tag['label_en']);
$desc = static fn (array $tag): string => (string) ($lang === 'en' ? $tag['desc_en'] : $tag['desc_fr']);
$stateLabels = $cat::STATUSES;
$tagFamilyKeys = \App\Controllers\Web\SseFieldNoteAdminController::tagFamilies();
// Familles et étiquettes actives pour les choix des fenêtres (étiquettes suggérées, étiquettes obligatoires).
$pickerFamilies = [];
foreach ($tags as $tag) {
    if ($tag['stored'] !== 'links' || $tag['status'] !== 'active') {
        continue;
    }
    $familyKey = (string) $tag['family'];
    $pickerFamilies[$familyKey] ??= ['key' => $familyKey, 'label' => $cat::familyLabel($familyKey, $lang), 'hint' => $cat::familyHint($familyKey, $lang), 'tags' => []];
    $pickerFamilies[$familyKey]['tags'][] = \App\Services\Sse\SseFieldNoteTagService::present($tag, '', $lang);
}
$pickerFamiliesNoCategory = array_diff_key($pickerFamilies, ['category' => true]);
$byKey = [];
$tagRows = [];
$categoryRows = [];
foreach ($tags as $tag) {
    $byKey[(string) $tag['key']] = $tag;
    if ($tag['family'] === 'category') {
        $categoryRows[] = $tag;
    } else {
        $tagRows[] = $tag;
    }
}
$linkTags = array_values(array_filter($tags, static fn (array $t): bool => $t['stored'] === 'links'));
$fill = static function (array $tag) use ($cat, $lang): array {
    return [
        '_mode' => 'edit' . ($tag['origin'] === 'custom' ? ' custom' : ' catalog') . ($tag['origin'] === 'custom' && (int) $tag['usage'] === 0 ? ' deletable' : ''),
        '_family' => $tag['family'], 'key' => $tag['key'], 'from' => $tag['key'], 'code' => $tag['code'],
        'family_label' => $cat::familyLabel((string) $tag['family'], $lang),
        'label_fr' => $tag['label_fr'], 'label_en' => $tag['label_en'], 'desc_fr' => $tag['desc_fr'], 'desc_en' => $tag['desc_en'],
        'color' => $tag['color'], 'sort' => $tag['sort'], 'status' => $tag['status'], 'usage' => (int) $tag['usage'],
        'tab' => $tag['family'] === 'category' ? 'categories' : 'etiquettes',
    ];
};
$ico = static fn (string $d): string => '<svg class="fnr-i" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
$close = $ico('M6 6l12 12M18 6L6 18');
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-fiche-rens.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-visibility.css')) . '">'
    . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-fiches-etiquettes.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-fiche-rens.js')) . '"></script>'
    . '<script defer src="' . $h(asset_url('assets/js/sse-visibility.js')) . '"></script>'
    . '<script defer src="' . $h(asset_url('assets/js/sse-fiches-etiquettes.js')) . '"></script>';

/** Lignes d'un tableau d'étiquettes (onglets Étiquettes et Catégories). */
$renderRows = static function (array $rows, bool $withFamily) use ($h, $json, $label, $other, $desc, $fill, $cat, $lang, $stateLabels, $canManageRef, $ico): void {
    foreach ($rows as $tag) {
        $name = $label($tag);
        $second = $other($tag);
        $mergeable = $tag['stored'] === 'links';
        ?>
        <tr<?= $canManageRef ? ' data-fng-row="' . $json($fill($tag)) . '"' : '' ?> data-family="<?= $h($tag['family']) ?>" data-status="<?= $h($tag['status']) ?>" data-origin="<?= $h($tag['origin']) ?>"
            data-search="<?= $h($tag['code'] . ' ' . $tag['label_fr'] . ' ' . $tag['label_en'] . ' ' . $tag['desc_fr'] . ' ' . $cat::familyLabel((string) $tag['family'], $lang)) ?>">
            <td class="fng-code" data-label="Code" data-sort="<?= $h($tag['code']) ?>"><span class="fnt<?= $tag['status'] !== 'active' ? ' fnt--off' : '' ?>" style="--fnt: <?= $h($tag['hex']) ?>" title="Couleur : <?= $h($cat::COLORS[$tag['color']]['label'] ?? '') ?>"><?= $h($tag['code']) ?></span></td>
            <td class="fng-wide" data-sort="<?= $h($name) ?>"><span class="fng-label"><?= $h($name) ?><?php if ($second !== '' && $second !== $name): ?><small lang="<?= $lang === 'en' ? 'fr' : 'en' ?>"><?= $h($second) ?></small><?php endif; ?></span><?php if ($desc($tag) !== ''): ?><span class="fng-sub"><?= $h($desc($tag)) ?></span><?php endif; ?></td>
            <?php if ($withFamily): ?>
                <td class="fng-family" data-label="Famille"><?= $h($cat::familyLabel((string) $tag['family'], $lang)) ?></td>
            <?php endif; ?>
            <td data-label="Ordre" class="fng-num" data-sort="<?= (int) $tag['sort'] ?>"><?= (int) $tag['sort'] ?></td>
            <td data-label="État" data-sort="<?= $h($tag['status']) ?>"><span class="fng-state fng-state--<?= $h($tag['status']) ?>"><?= $h($stateLabels[$tag['status']] ?? '') ?></span></td>
            <td data-label="Fiches" class="fng-num" data-sort="<?= (int) $tag['usage'] ?>"><?= (int) $tag['usage'] ?></td>
            <td data-label="Origine" data-sort="<?= $h($tag['origin']) ?>"><span class="fng-origin"><?= !empty($tag['from_attach']) ? 'Rattachements' : ($tag['origin'] === 'custom' ? 'Propre' : ($tag['customized'] ? 'Réglée' : 'Livrée')) ?></span></td>
            <?php if ($canManageRef): ?>
                <td class="fng-rowacts-cell">
                    <div class="fng-rowacts">
                        <button type="button" class="btn btn--ghost" data-fnr-open="fng-dlg-etiquette" aria-haspopup="dialog" data-fng-fill="@row">Modifier</button>
                        <button type="button" class="btn btn--ghost fng-icon" data-fnr-open="fng-dlg-etat" aria-haspopup="dialog" data-fng-fill="@row" title="Changer l’état : activer, désactiver, archiver" aria-label="Changer l’état de <?= $h($tag['code']) ?>"><?= $ico('M12 3v9M6.3 7.2a8 8 0 1 0 11.4 0') ?><span class="fng-icon__text">État</span></button>
                        <?php if ($mergeable): ?>
                            <button type="button" class="btn btn--ghost fng-icon" data-fnr-open="fng-dlg-fusion" aria-haspopup="dialog" data-fng-fill="@row" title="Fusionner dans une autre étiquette" aria-label="Fusionner <?= $h($tag['code']) ?>"><?= $ico('M6 4v5a5 5 0 0 0 5 5h7M6 20v-6M15 10l4 4-4 4') ?><span class="fng-icon__text">Fusionner</span></button>
                        <?php endif; ?>
                    </div>
                </td>
            <?php endif; ?>
        </tr>
        <?php
    }
};
?>
<div class="fnr fng" data-fnr>
    <header class="fng-strip">
        <div class="fng-strip__halo" aria-hidden="true"></div>
        <div class="fng-strip__veil" aria-hidden="true"></div>
        <div class="fng-strip__body">
            <div class="fng-strip__identity">
                <span class="fng-strip__index" aria-hidden="true">F.10</span>
                <div>
                    <h1 class="fng-title">Gestion des fiches</h1>
                    <p class="fng-strip__desc">Le référentiel des fiches de renseignement de votre communauté : étiquettes de style américain, catégories, types de fiche, trames et règles.</p>
                </div>
            </div>
            <div class="fng-strip__meta">
                <p class="fng-key"><strong><?= (int) $stats['active'] ?></strong><span>étiquettes actives</span></p>
                <dl class="fng-figs">
                    <div><dt>propres à la communauté</dt><dd><?= (int) $stats['custom'] ?></dd></div>
                    <div><dt>archivées</dt><dd><?= (int) $stats['archived'] ?></dd></div>
                    <div><dt>fiches de renseignement</dt><dd><?= (int) $stats['notes'] ?></dd></div>
                    <div><dt>types de fiche actifs</dt><dd><?= (int) $stats['kinds'] ?></dd></div>
                </dl>
            </div>
        </div>
    </header>

    <?php if (!$ready): ?>
        <p class="fng-notice">
            <strong>Catalogue livré en lecture seule</strong>
            L’étape de base de données des étiquettes n’est pas encore passée sur ce site : les fiches gardent leurs catégories, les étiquettes ne peuvent pas encore être posées ni réglées.
        </p>
    <?php elseif (!$isManager): ?>
        <p class="fng-notice fng-notice--info">
            <strong>Lecture seule</strong>
            Vous voyez le référentiel de votre communauté. Le modifier demande le droit « gérer » des fiches de renseignement.
        </p>
    <?php endif; ?>

    <div class="fng-card">
        <div class="fnr-tabs" role="tablist" aria-label="Rubriques du référentiel">
            <?php $first = true; foreach ($tabs as $tabKey => $tabLabel): ?>
                <button type="button" role="tab" id="fng-tab-<?= $h($tabKey) ?>" aria-controls="fng-panel-<?= $h($tabKey) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>"<?= $first ? '' : ' tabindex="-1"' ?> data-fnr-tab="<?= $h($tabKey) ?>"><?= $h($tabLabel) ?></button>
            <?php $first = false; endforeach; ?>
        </div>

        <section class="fng-panel" role="tabpanel" id="fng-panel-etiquettes" aria-labelledby="fng-tab-etiquettes" data-fnr-panel="etiquettes">
            <div class="fng-panel__head">
                <div>
                    <h2 class="fng-h2">Étiquettes</h2>
                    <p class="fng-desc">Les mentions proposées sur les fiches, par famille : activez, renommez, ordonnez celles du catalogue livré, ou créez les vôtres, communes aux fiches et aux rattachements.</p>
                </div>
                <?php if ($canManageRef): ?>
                    <div class="fng-actions">
                        <button type="button" class="btn" data-fnr-open="fng-dlg-etiquette" aria-haspopup="dialog" data-fng-fill="<?= $json(['_mode' => 'create create-tag', 'key' => '', 'code' => '', 'family' => 'theme', 'label_fr' => '', 'label_en' => '', 'desc_fr' => '', 'desc_en' => '', 'color' => 'slate', 'sort' => '', 'qualifier' => '', 'tab' => 'etiquettes']) ?>">Créer une étiquette</button>
                        <button type="button" class="btn btn--ghost" data-fnr-open="fng-dlg-retablir" aria-haspopup="dialog" data-fng-fill="<?= $json(['_mode' => 'reset-tag', 'family' => 'discipline', 'tab' => 'etiquettes']) ?>">Rétablir le catalogue livré</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="fng-toolbar">
                <label class="sr-only" for="fng-f-tags-q">Chercher une étiquette</label>
                <input type="search" id="fng-f-tags-q" data-fng-filter="tags" autocomplete="off" placeholder="Chercher un code ou un libellé">
                <label class="sr-only" for="fng-f-tags-family">Famille</label>
                <select id="fng-f-tags-family" data-fng-filter="tags" data-fng-attr="family">
                    <option value="">Toutes les familles</option>
                    <?php foreach ($tagFamilyKeys as $familyKey): ?>
                        <option value="<?= $h($familyKey) ?>"><?= $h($cat::familyLabel($familyKey, $lang)) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="sr-only" for="fng-f-tags-status">État</label>
                <select id="fng-f-tags-status" data-fng-filter="tags" data-fng-attr="status">
                    <option value="">Tous les états</option>
                    <?php foreach ($stateLabels as $stateKey => $stateLabel): ?>
                        <option value="<?= $h($stateKey) ?>"><?= $h($stateLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="sr-only" for="fng-f-tags-origin">Origine</label>
                <select id="fng-f-tags-origin" data-fng-filter="tags" data-fng-attr="origin">
                    <option value="">Livrées et propres</option>
                    <option value="catalog">Catalogue livré</option>
                    <option value="custom">Propres à la communauté</option>
                </select>
                <span class="fng-toolbar__count"><span data-fng-count="tags"><?= count($tagRows) ?></span> sur <?= count($tagRows) ?></span>
            </div>
            <div class="fng-tablewrap">
                <table class="fng-table" data-fng-table="tags">
                    <thead>
                        <tr>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Code</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Libellé</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Famille</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="num">Ordre</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">État</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="num">Fiches</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Origine</button></th>
                            <?php if ($canManageRef): ?><th scope="col"><span class="sr-only">Actions</span></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody><?php $renderRows($tagRows, true); ?></tbody>
                </table>
            </div>
            <p class="fng-none" data-fng-none="tags" hidden>Aucune étiquette ne correspond à ces filtres.</p>
        </section>

        <section class="fng-panel" role="tabpanel" id="fng-panel-categories" aria-labelledby="fng-tab-categories" data-fnr-panel="categories" hidden>
            <div class="fng-panel__head">
                <div>
                    <h2 class="fng-h2">Catégories</h2>
                    <p class="fng-desc">Les catégories en pastilles de couleur des fiches : renommez ou désactivez celles d’origine, ajoutez celles de votre communauté.</p>
                </div>
                <?php if ($canManageRef): ?>
                    <div class="fng-actions">
                        <button type="button" class="btn" data-fnr-open="fng-dlg-etiquette" aria-haspopup="dialog" data-fng-fill="<?= $json(['_mode' => 'create create-category', 'key' => '', 'code' => '', 'label_fr' => '', 'label_en' => '', 'desc_fr' => '', 'desc_en' => '', 'color' => 'slate', 'sort' => '', 'qualifier' => '', 'tab' => 'categories']) ?>">Créer une catégorie</button>
                        <button type="button" class="btn btn--ghost" data-fnr-open="fng-dlg-retablir" aria-haspopup="dialog" data-fng-fill="<?= $json(['_mode' => 'reset-category', 'tab' => 'categories']) ?>">Rétablir les catégories livrées</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="fng-toolbar">
                <label class="sr-only" for="fng-f-cats-q">Chercher une catégorie</label>
                <input type="search" id="fng-f-cats-q" data-fng-filter="cats" autocomplete="off" placeholder="Chercher un code ou un libellé">
                <label class="sr-only" for="fng-f-cats-status">État</label>
                <select id="fng-f-cats-status" data-fng-filter="cats" data-fng-attr="status">
                    <option value="">Tous les états</option>
                    <?php foreach ($stateLabels as $stateKey => $stateLabel): ?>
                        <option value="<?= $h($stateKey) ?>"><?= $h($stateLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="fng-toolbar__count"><span data-fng-count="cats"><?= count($categoryRows) ?></span> sur <?= count($categoryRows) ?></span>
            </div>
            <div class="fng-tablewrap">
                <table class="fng-table" data-fng-table="cats">
                    <thead>
                        <tr>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Code</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Libellé</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="num">Ordre</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">État</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="num">Fiches</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Origine</button></th>
                            <?php if ($canManageRef): ?><th scope="col"><span class="sr-only">Actions</span></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody><?php $renderRows($categoryRows, false); ?></tbody>
                </table>
            </div>
            <p class="fng-none" data-fng-none="cats" hidden>Aucune catégorie ne correspond à ces filtres.</p>
        </section>

        <section class="fng-panel" role="tabpanel" id="fng-panel-types" aria-labelledby="fng-tab-types" data-fnr-panel="types" hidden>
            <div class="fng-panel__head">
                <div>
                    <h2 class="fng-h2">Types de fiche</h2>
                    <p class="fng-desc">Quels types sont proposés à la rédaction, sous quel libellé, avec quelles étiquettes suggérées et quels champs demandés.</p>
                </div>
            </div>
            <div class="fng-tablewrap">
                <table class="fng-table fng-table--kinds" data-fng-table="kinds">
                    <thead>
                        <tr>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Sigle</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Libellé</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="num">Ordre</button></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">État</button></th>
                            <th scope="col"><span>Étiquettes suggérées</span></th>
                            <th scope="col"><span>Champs demandés</span></th>
                            <?php if ($canManageRef): ?><th scope="col"><span class="sr-only">Actions</span></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kinds as $kindCode => $kind): ?>
                            <tr data-search="<?= $h($kindCode . ' ' . $kind['label']) ?>">
                                <td data-label="Sigle" data-sort="<?= $h($kindCode) ?>"><span class="fnt" style="--fnt: #2a247f"><?= $h($kindCode) ?></span></td>
                                <td class="fng-wide" data-sort="<?= $h($kind['label']) ?>"><span class="fng-label"><?= $h($kind['label']) ?></span><?php if ($kind['label'] !== $kind['default_label']): ?><span class="fng-sub">Libellé d’origine : <?= $h($kind['default_label']) ?></span><?php endif; ?><span class="fng-sub"><?= $h($kind['hint']) ?></span></td>
                                <td data-label="Ordre" class="fng-num" data-sort="<?= (int) $kind['sort'] ?>"><?= (int) $kind['sort'] ?></td>
                                <td data-label="État" data-sort="<?= $kind['enabled'] ? 'a' : 'z' ?>"><span class="fng-state fng-state--<?= $kind['enabled'] ? 'active' : 'disabled' ?>"><?= $kind['enabled'] ? 'Proposé' : 'Retiré de la rédaction' ?></span></td>
                                <td class="fng-wide" data-label="Étiquettes suggérées">
                                    <?php if ($kind['suggested'] === []): ?><span class="fng-sub">Aucune</span><?php else: ?>
                                        <span class="fng-pills"><?php foreach ($kind['suggested'] as $key): if (!isset($byKey[$key])) { continue; } ?><span class="fnt" style="--fnt: <?= $h($byKey[$key]['hex']) ?>"><?= $h($byKey[$key]['code']) ?></span><?php endforeach; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="fng-fields" data-label="Champs demandés"><?= $kind['required_fields'] === [] ? '<span class="fng-sub">Aucun</span>' : $h(implode(', ', array_map(static fn (string $f): string => $cat::REQUIRABLE_FIELDS[$f] ?? $f, $kind['required_fields']))) ?></td>
                                <?php if ($canManageRef): ?>
                                    <td class="fng-rowacts-cell"><div class="fng-rowacts">
                                        <button type="button" class="btn btn--ghost" data-fnr-open="fng-dlg-type" aria-haspopup="dialog" data-fng-fill="<?= $json(['_mode' => 'edit', 'kind' => $kindCode, 'kind_code' => $kindCode, 'label' => $kind['label'], 'enabled' => $kind['enabled'] ? '1' : '0', 'sort' => $kind['sort'], 'suggested' => $kind['suggested'], 'required_fields' => $kind['required_fields']]) ?>">Modifier</button>
                                    </div></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="fng-panel" role="tabpanel" id="fng-panel-modeles" aria-labelledby="fng-tab-modeles" data-fnr-panel="modeles" hidden>
            <div class="fng-panel__head">
                <div>
                    <h2 class="fng-h2">Modèles</h2>
                    <p class="fng-desc">La trame proposée au rédacteur pour chaque type de fiche : il l’insère d’un bouton, puis la complète.</p>
                </div>
            </div>
            <div class="fng-tablewrap">
                <table class="fng-table" data-fng-table="templates">
                    <thead>
                        <tr>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="text">Type</button></th>
                            <th scope="col"><span>Trame</span></th>
                            <th scope="col"><button type="button" class="fng-sort" data-fng-sort="num">Longueur</button></th>
                            <?php if ($canManageRef): ?><th scope="col"><span class="sr-only">Actions</span></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kinds as $kindCode => $kind): $len = mb_strlen((string) $kind['template']); ?>
                            <tr>
                                <td data-label="Type" data-sort="<?= $h($kindCode) ?>"><span class="fnt" style="--fnt: #2a247f"><?= $h($kindCode) ?></span> <span class="fng-sub"><?= $h($kind['label']) ?></span></td>
                                <td class="fng-wide"><?php if ($len === 0): ?><span class="fng-sub">Aucune trame : le rédacteur part d’une page blanche.</span><?php else: ?><p class="fng-template"><?= $h($kind['template']) ?></p><?php endif; ?></td>
                                <td data-label="Longueur" class="fng-num" data-sort="<?= $len ?>"><?= $len ?> / <?= (int) \App\Support\SseFieldNoteCatalog::BODY_MAX_LENGTH ?></td>
                                <?php if ($canManageRef): ?>
                                    <td class="fng-rowacts-cell"><div class="fng-rowacts">
                                        <button type="button" class="btn btn--ghost" data-fnr-open="fng-dlg-modele" aria-haspopup="dialog" data-fng-fill="<?= $json(['_mode' => 'edit', 'kind' => $kindCode, 'kind_code' => $kindCode . ' — ' . $kind['label'], 'template' => $kind['template']]) ?>"><?= $len === 0 ? 'Écrire la trame' : 'Modifier la trame' ?></button>
                                    </div></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php require __DIR__ . '/_sheet_visibility_admin.php'; ?>
        </section>

        <section class="fng-panel" role="tabpanel" id="fng-panel-regles" aria-labelledby="fng-tab-regles" data-fnr-panel="regles" hidden>
            <div class="fng-panel__head">
                <div>
                    <h2 class="fng-h2">Règles</h2>
                    <p class="fng-desc">Ce que le portail demande ou pose de lui-même à la rédaction : étiquettes obligatoires par type, validité par défaut, priorité de traitement.</p>
                </div>
                <?php if ($canManageRef): ?>
                    <div class="fng-actions">
                        <button type="button" class="btn" data-fnr-open="fng-dlg-regles" aria-haspopup="dialog">Modifier les règles</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="fng-tablewrap">
                <table class="fng-table">
                    <thead>
                        <tr><th scope="col"><span>Règle</span></th><th scope="col"><span>Réglage de la communauté</span></th><?php if ($canManageRef): ?><th scope="col"><span class="sr-only">Actions</span></th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kinds as $kindCode => $kind): ?>
                            <tr>
                                <td data-label="Règle"><span class="fng-label">Étiquettes obligatoires : <?= $h($kindCode) ?></span><span class="fng-sub"><?= $h($kind['label']) ?></span></td>
                                <td class="fng-wide">
                                    <?php if ($kind['required_tags'] === []): ?><span class="fng-sub">Aucune</span><?php else: ?>
                                        <span class="fng-pills"><?php foreach ($kind['required_tags'] as $need): ?>
                                            <?php if (str_starts_with($need, 'family:')): ?><span class="fng-pill">Une étiquette « <?= $h($cat::familyLabel(substr($need, 7), $lang)) ?> »</span>
                                            <?php elseif (isset($byKey[$need])): ?><span class="fnt" style="--fnt: <?= $h($byKey[$need]['hex']) ?>"><?= $h($byKey[$need]['code']) ?></span><?php endif; ?>
                                        <?php endforeach; ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canManageRef): ?>
                                    <td class="fng-rowacts-cell"><div class="fng-rowacts">
                                        <button type="button" class="btn btn--ghost" data-fnr-open="fng-dlg-requis" aria-haspopup="dialog" data-fng-fill="<?= $json(['_mode' => 'edit', 'kind' => $kindCode, 'kind_code' => $kindCode . ' — ' . $kind['label'], 'families' => array_values(array_filter($kind['required_tags'], static fn (string $n): bool => str_starts_with($n, 'family:'))), 'required' => array_values(array_filter($kind['required_tags'], static fn (string $n): bool => !str_starts_with($n, 'family:')))]) ?>">Modifier</button>
                                    </div></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td data-label="Règle"><span class="fng-label">Validité posée par défaut</span><span class="fng-sub">Ajoutée à la fiche si le rédacteur n’en choisit aucune.</span></td>
                            <td class="fng-wide"><?php if ($rules['default_validity'] !== '' && isset($byKey[$rules['default_validity']])): $dv = $byKey[$rules['default_validity']]; ?><span class="fnt" style="--fnt: <?= $h($dv['hex']) ?>"><?= $h($dv['code']) ?></span> <?= $h($label($dv)) ?><?php else: ?><span class="fng-sub">Aucune</span><?php endif; ?></td><?php if ($canManageRef): ?><td></td><?php endif; ?>
                        </tr>
                        <?php foreach (\App\Support\SseFieldNoteCatalog::URGENCIES as $urgencyKey => $urgencyDef): $pc = (string) ($rules['precedence'][$urgencyKey] ?? $cat::defaultPrecedence($urgencyKey)); ?>
                            <tr>
                                <td data-label="Règle"><span class="fng-label">Urgence « <?= $h($urgencyDef['label']) ?> »</span><span class="fng-sub">Priorité de traitement écrite sur la fiche.</span></td>
                                <td class="fng-wide"><span class="fnt" style="--fnt: #b7791f"><?= $h($pc) ?></span> <?= $h($lang === 'en' ? $cat::PRECEDENCES[$pc]['desc_en'] : $cat::PRECEDENCES[$pc]['desc_fr']) ?></td><?php if ($canManageRef): ?><td></td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td data-label="Règle"><span class="fng-label">Liste « REL TO » proposée</span><span class="fng-sub">Préremplie quand un rédacteur pose l’étiquette REL TO.</span></td>
                            <td class="fng-wide"><?= $rules['rel_to'] !== '' ? '<span class="fnr-mono">REL TO ' . $h($rules['rel_to']) . '</span>' : '<span class="fng-sub">Aucune</span>' ?></td><?php if ($canManageRef): ?><td></td><?php endif; ?>
                        </tr>
                        <tr>
                            <td data-label="Règle"><span class="fng-label">Cotation de la source</span><span class="fng-sub">Rappel : libellés américains des lettres A à F.</span></td>
                            <td class="fng-wide"><span class="fng-pills"><?php foreach ($cat::RELIABILITY as $letter => $def): ?><span class="fng-pill" lang="en"><?= $h($letter) ?> · <?= $h($def['en']) ?></span><?php endforeach; ?></span></td><?php if ($canManageRef): ?><td></td><?php endif; ?>
                        </tr>
                        <tr>
                            <td data-label="Règle"><span class="fng-label">Cotation de l’information</span><span class="fng-sub">Rappel : libellés américains des chiffres 1 à 6.</span></td>
                            <td class="fng-wide"><span class="fng-pills"><?php foreach ($cat::CREDIBILITY as $digit => $def): ?><span class="fng-pill" lang="en"><?= $h($digit) ?> · <?= $h($def['en']) ?></span><?php endforeach; ?></span></td><?php if ($canManageRef): ?><td></td><?php endif; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="fnt-note" style="margin-top:16px">Une étiquette de diffusion (NOFORN, REL TO, ORCON…) est une mention écrite sur la fiche. Elle ne change pas qui peut la lire : l’accès reste décidé par la classification de la fiche et par les droits d’accès de la communauté. Les règles s’appliquent à la rédaction sur le portail.</p>
        </section>

        <section class="fng-panel" role="tabpanel" id="fng-panel-echanges" aria-labelledby="fng-tab-echanges" data-fnr-panel="echanges" hidden>
            <div class="fng-panel__head">
                <div>
                    <h2 class="fng-h2">Import / export</h2>
                    <p class="fng-desc">Sauvegardez le référentiel de votre communauté dans un fichier JSON, ou reprenez celui d’une autre de vos communautés.</p>
                </div>
            </div>
            <div class="fng-split">
                <section>
                    <h3 class="fng-h3">Exporter</h3>
                    <p class="fng-desc">Le fichier contient vos étiquettes propres, vos réglages du catalogue livré, les types de fiche, les trames et les règles. Aucune fiche n’y figure.</p>
                    <?php if ($isManager): ?>
                        <div class="fng-actions"><a class="btn" href="<?= $h(url($base . '/export')) ?>">Exporter le référentiel (JSON)</a></div>
                    <?php else: ?>
                        <p class="fng-help">L’export demande le droit « gérer » des fiches de renseignement.</p>
                    <?php endif; ?>
                </section>
                <section>
                    <h3 class="fng-h3">Importer</h3>
                    <p class="fng-desc">Le fichier est vérifié en entier avant la première écriture : à la moindre erreur, rien n’est appliqué. Rien n’est supprimé ; un nom d’organisation réelle est refusé.</p>
                    <?php if ($canManageRef): ?>
                        <div class="fng-actions"><button type="button" class="btn" data-fnr-open="fng-dlg-import" aria-haspopup="dialog">Importer un référentiel</button></div>
                    <?php else: ?>
                        <p class="fng-help">L’import demande le droit « gérer » des fiches de renseignement.</p>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    </div>

    <?php if ($canManageRef): ?>
        <dialog class="fnr-dialog fnr-dialog--wide" id="fng-dlg-etiquette" aria-labelledby="fng-dlg-etiquette-title">
            <form method="post" action="<?= $h(url($base . '/etiquette')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="key" value="">
                <input type="hidden" name="tab" value="etiquettes">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fng-dlg-etiquette-title"><span data-fng-when="create-tag">Créer une étiquette</span><span data-fng-when="create-category" hidden>Créer une catégorie</span><span data-fng-when="edit" hidden>Modifier <span data-fng-out="code"></span></span></h2>
                        <p class="fnr-desc">Le code s’écrit tel quel sur la fiche ; le libellé l’explique au lecteur.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $close ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <div class="fng-grid2" data-fng-when="create">
                        <label class="fnr-field" for="fng-tag-code">Code court
                            <input id="fng-tag-code" type="text" name="code" maxlength="<?= (int) $cat::CODE_MAX ?>" required autocapitalize="characters" placeholder="Ex. SECTEUR NORD">
                            <span class="fng-help">Majuscules, chiffres, espaces ou tirets. Il ne change plus après la création.</span>
                        </label>
                        <label class="fnr-field" for="fng-tag-family" data-fng-when="create-tag">Famille
                            <select id="fng-tag-family" name="family">
                                <?php foreach ($tagFamilyKeys as $familyKey): ?>
                                    <option value="<?= $h($familyKey) ?>"><?= $h($cat::familyLabel($familyKey, $lang)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <input type="hidden" name="family" value="category" data-fng-when="create-category" disabled>
                    </div>
                    <p class="fng-readonly" data-fng-when="edit" hidden><strong data-fng-out="code"></strong><span data-fng-out="family_label"></span><span data-fng-when="catalog" hidden>Étiquette du catalogue livré : le code et la famille ne changent pas.</span><span data-fng-when="custom" hidden>Étiquette propre à la communauté : pour changer de code, créez-en une autre puis fusionnez.</span></p>
                    <div class="fng-grid2">
                        <label class="fnr-field" for="fng-tag-fr">Libellé français
                            <input id="fng-tag-fr" type="text" name="label_fr" maxlength="<?= (int) $cat::LABEL_MAX ?>" required>
                        </label>
                        <label class="fnr-field" for="fng-tag-en">Libellé anglais
                            <input id="fng-tag-en" type="text" name="label_en" maxlength="<?= (int) $cat::LABEL_MAX ?>" lang="en">
                        </label>
                        <label class="fnr-field" for="fng-tag-dfr">Description française (une ligne)
                            <input id="fng-tag-dfr" type="text" name="desc_fr" maxlength="<?= (int) $cat::DESCRIPTION_MAX ?>">
                        </label>
                        <label class="fnr-field" for="fng-tag-den">Description anglaise (une ligne)
                            <input id="fng-tag-den" type="text" name="desc_en" maxlength="<?= (int) $cat::DESCRIPTION_MAX ?>" lang="en">
                        </label>
                        <label class="fnr-field" for="fng-tag-color">Couleur
                            <select id="fng-tag-color" name="color">
                                <?php foreach ($cat::COLORS as $colorKey => $colorDef): ?>
                                    <option value="<?= $h($colorKey) ?>"><?= $h($colorDef['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="fnr-field" for="fng-tag-sort">Ordre dans la famille
                            <input id="fng-tag-sort" type="number" name="sort" min="0" max="9999" step="1" inputmode="numeric" placeholder="À la suite">
                        </label>
                        <label class="fnr-field" for="fng-tag-q" data-fng-when="create-tag">Complément demandé au rédacteur
                            <select id="fng-tag-q" name="qualifier">
                                <option value="">Aucun</option>
                                <option value="number">Un numéro (PIR 2)</option>
                                <option value="text">Un texte libre</option>
                                <option value="date">Une échéance</option>
                            </select>
                        </label>
                    </div>
                    <p class="fng-help">Un nom d’organisation réelle est refusé : les étiquettes sont des mentions, pas des émetteurs.</p>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer</button>
                </footer>
            </form>
        </dialog>

        <dialog class="fnr-dialog fnr-dialog--small" id="fng-dlg-etat" aria-labelledby="fng-dlg-etat-title">
            <form method="post" action="<?= $h(url($base . '/etat')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="key" value="">
                <input type="hidden" name="tab" value="etiquettes">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fng-dlg-etat-title">État de <span data-fng-out="code"></span></h2>
                        <p class="fnr-desc">Une étiquette désactivée ou archivée n’est plus proposée ; les fiches qui la portent la gardent.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $close ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <p class="fng-readonly"><strong data-fng-out="label_fr"></strong><span><span data-fng-out="usage"></span> fiche(s) la portent.</span></p>
                    <label class="fnr-field" for="fng-etat-status">Nouvel état
                        <select id="fng-etat-status" name="status">
                            <option value="active">Active : proposée sur les fiches</option>
                            <option value="disabled">Désactivée : retirée des propositions</option>
                            <option value="archived" data-fng-when="custom">Archivée : gardée pour mémoire</option>
                        </select>
                    </label>
                    <p class="fng-help" data-fng-when="deletable" hidden>Aucune fiche ne porte cette étiquette propre : elle peut aussi être supprimée.</p>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="submit" class="btn btn--danger" formaction="<?= $h(url($base . '/supprimer')) ?>" data-fng-when="deletable" hidden>Supprimer</button>
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer l’état</button>
                </footer>
            </form>
        </dialog>

        <dialog class="fnr-dialog" id="fng-dlg-fusion" aria-labelledby="fng-dlg-fusion-title">
            <form method="post" action="<?= $h(url($base . '/fusion')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="from" value="">
                <input type="hidden" name="tab" value="etiquettes">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fng-dlg-fusion-title">Fusionner <span data-fng-out="code"></span></h2>
                        <p class="fnr-desc">Les fiches qui portent cette étiquette porteront celle que vous gardez ; la première est ensuite retirée des propositions.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $close ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <p class="fng-readonly"><strong data-fng-out="label_fr"></strong><span><span data-fng-out="family_label"></span> · <span data-fng-out="usage"></span> fiche(s) la portent.</span></p>
                    <label class="fnr-field" for="fng-fusion-into">Étiquette à garder (même famille)
                        <select id="fng-fusion-into" name="into" required data-fng-same-family>
                            <option value="">Choisir l’étiquette à garder</option>
                            <?php foreach ($linkTags as $tag): if ($tag['status'] === 'archived') { continue; } ?>
                                <option value="<?= $h($tag['key']) ?>" data-family="<?= $h($tag['family']) ?>"><?= $h($tag['code']) ?> — <?= $h($label($tag)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="fng-help">La fusion ne se défait pas d’un clic : elle s’inscrit au journal du bureau.</p>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Fusionner</button>
                </footer>
            </form>
        </dialog>

        <dialog class="fnr-dialog fnr-dialog--small" id="fng-dlg-retablir" aria-labelledby="fng-dlg-retablir-title">
            <form method="post" action="<?= $h(url($base . '/retablir')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="tab" value="etiquettes">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fng-dlg-retablir-title">Rétablir le catalogue livré ?</h2>
                        <p class="fnr-desc">Les libellés, couleurs, ordre et états d’origine de la famille reviennent. Vos étiquettes propres et les fiches ne changent pas.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $close ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <label class="fnr-field" for="fng-retablir-family" data-fng-when="reset-tag">Famille à rétablir
                        <select id="fng-retablir-family" name="family">
                            <?php foreach ($tagFamilyKeys as $familyKey): ?>
                                <option value="<?= $h($familyKey) ?>"><?= $h($cat::familyLabel($familyKey, $lang)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <input type="hidden" name="family" value="category" data-fng-when="reset-category" disabled>
                    <p class="fng-readonly" data-fng-when="reset-category" hidden><strong>Catégories</strong><span>Les dix-sept catégories d’origine retrouvent leur libellé, leur couleur et leur état.</span></p>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close autofocus>Annuler</button>
                    <button type="submit" class="btn btn--danger">Rétablir</button>
                </footer>
            </form>
        </dialog>

        <dialog class="fnr-dialog fnr-dialog--wide" id="fng-dlg-type" aria-labelledby="fng-dlg-type-title">
            <form method="post" action="<?= $h(url($base . '/type')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="kind" value="">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fng-dlg-type-title">Type <span data-fng-out="kind_code"></span></h2>
                        <p class="fnr-desc">Le sigle reste celui des fiches déjà transmises ; seul le libellé affiché change.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $close ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <div class="fng-grid2">
                        <label class="fnr-field" for="fng-type-label">Libellé
                            <input id="fng-type-label" type="text" name="label" maxlength="<?= (int) $cat::LABEL_MAX ?>" required>
                        </label>
                        <label class="fnr-field" for="fng-type-sort">Ordre
                            <input id="fng-type-sort" type="number" name="sort" min="0" max="9999" step="1" inputmode="numeric">
                        </label>
                    </div>
                    <label class="fng-check"><input type="checkbox" name="enabled" value="1"> Proposer ce type à la rédaction</label>
                    <fieldset class="fng-fieldset">
                        <legend>Étiquettes suggérées au rédacteur</legend>
                        <?php
                        $tagsMax = 12;
                        $pickerSelected = [];
                        $pickerKind = '';
                        $pickerId = 'fng-type-picker';
                        $pickerName = 'suggested';
                        $pickerQualifiers = false;
                        $tagSuggestions = [];
                        $tagFamilies = $pickerFamilies;
                        require __DIR__ . '/partials/field_note_tag_picker.php';
                        ?>
                        <span class="fng-help">Elles s’ajoutent d’un geste à la rédaction ; rien n’est imposé.</span>
                    </fieldset>
                    <fieldset class="fng-fieldset">
                        <legend>Champs demandés pour ce type</legend>
                        <div class="fng-checks">
                            <?php foreach ($cat::REQUIRABLE_FIELDS as $fieldKey => $fieldLabel): ?>
                                <label class="fng-check"><input type="checkbox" name="required_fields[]" value="<?= $h($fieldKey) ?>"> <?= $h($fieldLabel) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer le type</button>
                </footer>
            </form>
        </dialog>

        <dialog class="fnr-dialog fnr-dialog--wide" id="fng-dlg-modele" aria-labelledby="fng-dlg-modele-title">
            <form method="post" action="<?= $h(url($base . '/modele')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="kind" value="">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fng-dlg-modele-title">Trame <span data-fng-out="kind_code"></span></h2>
                        <p class="fnr-desc">Des rubriques courtes à compléter, par exemple les six lignes d’un compte rendu SALUTE.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $close ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <label class="fnr-field" for="fng-modele-text">Texte de la trame
                        <textarea id="fng-modele-text" class="fng-tall" name="template" maxlength="<?= (int) \App\Support\SseFieldNoteCatalog::BODY_MAX_LENGTH ?>" rows="10" placeholder="Effectif :&#10;Activité :&#10;Lieu :&#10;Unité :&#10;Heure :&#10;Équipement :"></textarea>
                        <span class="fng-help">Laissez vide pour retirer la trame. <?= (int) \App\Support\SseFieldNoteCatalog::BODY_MAX_LENGTH ?> caractères au plus, comme le texte d’une fiche.</span>
                    </label>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer la trame</button>
                </footer>
            </form>
        </dialog>

        <dialog class="fnr-dialog fnr-dialog--wide" id="fng-dlg-regles" aria-labelledby="fng-dlg-regles-title">
            <form method="post" action="<?= $h(url($base . '/regles')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fng-dlg-regles-title">Règles des fiches</h2>
                        <p class="fnr-desc">Priorité de traitement, validité et liste de diffusion proposées ; les fiches déjà transmises ne changent pas.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $close ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <fieldset class="fng-fieldset">
                        <legend>Priorité de traitement écrite pour chaque urgence</legend>
                        <div class="fng-map">
                            <?php foreach (\App\Support\SseFieldNoteCatalog::URGENCIES as $urgencyKey => $urgencyDef): $pc = (string) ($rules['precedence'][$urgencyKey] ?? $cat::defaultPrecedence($urgencyKey)); ?>
                                <label class="fnr-field" for="fng-regle-p-<?= $h($urgencyKey) ?>"><?= $h($urgencyDef['label']) ?>
                                    <select id="fng-regle-p-<?= $h($urgencyKey) ?>" name="precedence[<?= $h($urgencyKey) ?>]">
                                        <?php foreach ($cat::PRECEDENCES as $pKey => $pDef): ?>
                                            <option value="<?= $h($pKey) ?>" <?= $pKey === $pc ? 'selected' : '' ?>><?= $h($pKey) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div class="fng-grid2">
                        <label class="fnr-field" for="fng-regle-validity">Validité posée par défaut
                            <select id="fng-regle-validity" name="default_validity">
                                <option value="">Aucune</option>
                                <?php foreach ($linkTags as $tag): if ($tag['family'] !== 'validity' || $tag['status'] !== 'active') { continue; } ?>
                                    <option value="<?= $h($tag['key']) ?>" <?= $rules['default_validity'] === $tag['key'] ? 'selected' : '' ?>><?= $h($tag['code']) ?> — <?= $h($label($tag)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="fnr-field" for="fng-regle-relto">Liste « REL TO » proposée
                            <input id="fng-regle-relto" type="text" name="rel_to" maxlength="<?= (int) $cat::QUALIFIER_MAX ?>" value="<?= $h($rules['rel_to']) ?>" placeholder="Ex. USA, ALTIS">
                        </label>
                    </div>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer les règles</button>
                </footer>
            </form>
        </dialog>

        <dialog class="fnr-dialog fnr-dialog--wide" id="fng-dlg-requis" aria-labelledby="fng-dlg-requis-title">
            <form method="post" action="<?= $h(url($base . '/requis')) ?>" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="kind" value="">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fng-dlg-requis-title">Étiquettes obligatoires : <span data-fng-out="kind_code"></span></h2>
                        <p class="fnr-desc">Ce que le rédacteur doit poser pour transmettre une fiche de ce type depuis le portail.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $close ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <fieldset class="fng-fieldset">
                        <legend>Au moins une étiquette de la famille</legend>
                        <div class="fng-checks">
                            <?php foreach ($tagFamilyKeys as $familyKey): ?>
                                <label class="fng-check"><input type="checkbox" name="families[]" value="family:<?= $h($familyKey) ?>"> <?= $h($cat::familyLabel($familyKey, $lang)) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <fieldset class="fng-fieldset">
                        <legend>Étiquettes précises à porter</legend>
                        <?php
                        $tagsMax = 8;
                        $pickerSelected = [];
                        $pickerKind = '';
                        $pickerId = 'fng-requis-picker';
                        $pickerName = 'required';
                        $pickerQualifiers = false;
                        $tagSuggestions = [];
                        $tagFamilies = $pickerFamiliesNoCategory;
                        require __DIR__ . '/partials/field_note_tag_picker.php';
                        ?>
                    </fieldset>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Enregistrer</button>
                </footer>
            </form>
        </dialog>

        <dialog class="fnr-dialog fnr-dialog--wide" id="fng-dlg-import" aria-labelledby="fng-dlg-import-title">
            <form method="post" action="<?= $h(url($base . '/import')) ?>" enctype="multipart/form-data" class="fnr-dialog__card">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <header class="fnr-dialog__head">
                    <div>
                        <h2 id="fng-dlg-import-title">Importer un référentiel</h2>
                        <p class="fnr-desc">Un fichier exporté d’ici. Les étiquettes du fichier sont créées ou réglées ; rien n’est supprimé.</p>
                    </div>
                    <button type="button" class="fnr-dialog__close" data-fnr-close aria-label="Fermer la fenêtre"><?= $close ?></button>
                </header>
                <div class="fnr-dialog__body">
                    <label class="fnr-field" for="fng-import-file">Fichier JSON (400 Ko au plus)
                        <input id="fng-import-file" type="file" name="fichier" accept="application/json,.json">
                    </label>
                    <label class="fnr-field" for="fng-import-json">Ou collez son contenu
                        <textarea id="fng-import-json" class="fng-code" name="json" rows="8" spellcheck="false" placeholder='{"format": "comspec.sse.fiches.referentiel", "version": 1, "tags": []}'></textarea>
                    </label>
                    <p class="fng-help">Contrôle strict : format, familles, codes, longueurs, couleurs, états. Un nom d’organisation réelle fait refuser tout le fichier.</p>
                </div>
                <footer class="fnr-dialog__foot">
                    <button type="button" class="btn btn--ghost" data-fnr-close>Annuler</button>
                    <button type="submit" class="btn">Vérifier et importer</button>
                </footer>
            </form>
        </dialog>
    <?php endif; ?>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
