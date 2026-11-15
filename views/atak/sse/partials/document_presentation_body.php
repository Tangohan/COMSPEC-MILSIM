<?php
declare(strict_types=1);
/**
 * Présentation des documents (menu Production) — page de référence du système de design SSE (sse-ds.css / sse-ds.js).
 * Un en-tête (le fil d'Ariane vient du layout), des tuiles de chiffres, UNE file de modèles type ticketing (onglets à
 * compteur, recherche, filtre, tri, lignes de 64 px), UN tiroir d'aperçu au clic et des modales (modifier / créer,
 * utiliser). Mêmes routes, mêmes champs de formulaire, mêmes droits que l'ancienne page.
 *
 * @var list<array<string,mixed>> $prefabs
 * @var string $activePrefabCode
 * @var array<string,mixed>|null $editPrefab
 * @var array<string,string> $paperStyles
 * @var bool $canManage
 */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
/** @var list<array<string,mixed>> $prefabs */
$prefabs = is_array($prefabs ?? null) ? array_values($prefabs) : [];
/** @var array<string,string> $paperStyles */
$paperStyles = is_array($paperStyles ?? null) ? $paperStyles : [];
$activePrefabCode = (string) ($activePrefabCode ?? '');
$canManage = (bool) ($canManage ?? false);
$csrf = (string) \App\Core\Csrf::token();
$deepLink = trim((string) ($_GET['modifier'] ?? ''));
$formFields = [
    'label', 'description', 'paper_style', 'banner', 'title_person', 'title_docs', 'subtitle_dossier', 'subtitle_feuille',
    'subtitle_docs', 'footer', 'quality_prefix', 'org_line', 'access_note', 'seal_top', 'seal_bottom',
    'btn_consult', 'btn_transmit', 'btn_close',
];

// Chiffres et modèle en service.
$activePrefab = null;
$customCount = 0;
$usedStyles = [];
foreach ($prefabs as $p) {
    if ((string) ($p['code'] ?? '') === $activePrefabCode) {
        $activePrefab = $p;
    }
    if (empty($p['is_builtin'])) {
        ++$customCount;
    }
    $usedStyles[(string) ($p['paper_style'] ?? 'clean')] = true;
}
$total = count($prefabs);
$builtinCount = $total - $customCount;
$activeCount = $activePrefab !== null ? 1 : 0;
$styleLabel = static fn (string $k): string => (string) ($paperStyles[$k] ?? $k);
// Une seule feuille complète est rendue (le gabarit papier est lourd) : celle du modèle demandé (?modifier=) ou en service.
$fullPrefab = is_array($editPrefab ?? null) ? $editPrefab : ($activePrefab ?? ($prefabs[0] ?? \App\Support\SseDocumentChromeCatalog::defaultChrome()));
$fullCode = (string) ($fullPrefab['code'] ?? '');

// Enregistrements pour la fenêtre « modifier » (JSON), dont une base vide partant du modèle en service.
$records = [];
foreach ($prefabs as $p) {
    $rec = [];
    foreach ($formFields as $f) {
        $rec[$f] = (string) ($p[$f] ?? '');
    }
    $rec['code'] = (string) ($p['code'] ?? '');
    $rec['make_active'] = (string) ($p['code'] ?? '') === $activePrefabCode ? '1' : '0';
    $rec['is_active'] = (string) ($p['code'] ?? '') === $activePrefabCode ? '1' : '';
    $rec['is_builtin'] = !empty($p['is_builtin']) ? '1' : '';
    $rec['is_full'] = (string) ($p['code'] ?? '') === $fullCode ? '1' : '';
    $rec['url'] = url('atak/sse/presentation?modifier=' . rawurlencode((string) ($p['code'] ?? '')));
    $records[(string) ($p['code'] ?? '')] = $rec;
}
$blank = [];
$base = $activePrefab ?? ($prefabs[0] ?? []);
foreach ($formFields as $f) {
    $blank[$f] = (string) ($base[$f] ?? '');
}
$blank['label'] = '';
$blank['description'] = '';
$blank['code'] = '';
$blank['make_active'] = '0';
$records[''] = $blank;

// Aperçu : feuille de démonstration (même gabarit papier que la rédaction), au niveau Secret (S) du bureau.
$previewDoc = [
    'title' => 'Aperçu — présentation du modèle',
    'reference_code' => 'DOC-PREVIEW-0001',
    'classification' => 'confidentiel',
    'classification_label' => 'Secret (S)',
    'document_type_label' => 'Note',
    'status' => 'brouillon',
    'status_label' => 'Brouillon',
    'author_label' => 'Bureau SSE',
    'body' => "Ceci est un aperçu de la feuille telle qu’elle apparaît à la rédaction.\n\nLe bandeau, les titres, le pied de page et l’aspect du papier viennent du modèle choisi.",
    'created_at' => date('Y-m-d H:i:s'),
];
$sseIcon = static fn (string $d): string => '<svg class="sse-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $d . '</svg>';
$icoClose = $sseIcon('<path d="M6 6l12 12M18 6L6 18"/>');
?>
<div class="sse-page sse-pres">
    <header class="sse-head">
        <div class="sse-head__copy">
            <h1 class="sse-title sse-title--page">Présentation des documents</h1>
            <p class="sse-lede">Le modèle en service habille tous les documents du bureau : bandeau, titres, pied de page et aspect du papier.</p>
        </div>
        <div class="sse-head__actions">
            <a class="sse-btn" href="<?= $h(url('atak/sse/documents')) ?>">Retour à la rédaction</a>
            <?php if ($canManage): ?>
                <button type="button" class="sse-btn sse-btn--primary" data-sse-open="#sse-pres-edit" data-sse-record="">Nouveau modèle</button>
            <?php endif; ?>
        </div>
    </header>

    <section class="sse-tiles" aria-label="Chiffres des modèles">
        <div class="sse-tile">
            <span class="sse-tile__label">Modèles</span>
            <span class="sse-tile__value"><?= (int) $total ?></span>
            <span class="sse-tile__hint">Préfaits : <?= (int) $builtinCount ?></span>
        </div>
        <div class="sse-tile">
            <span class="sse-tile__label">En service</span>
            <span class="sse-tile__value sse-tile__value--text" title="<?= $h($activePrefab !== null ? (string) ($activePrefab['label'] ?? $activePrefabCode) : '') ?>"><?= $h($activePrefab !== null ? (string) ($activePrefab['label'] ?? $activePrefabCode) : '—') ?></span>
            <span class="sse-tile__hint"><?= $h($activePrefab !== null ? $styleLabel((string) ($activePrefab['paper_style'] ?? 'clean')) : 'Aucun modèle choisi') ?></span>
        </div>
        <div class="sse-tile">
            <span class="sse-tile__label">Personnalisés</span>
            <span class="sse-tile__value"><?= (int) $customCount ?></span>
            <span class="sse-tile__hint">créés par le bureau</span>
        </div>
        <div class="sse-tile">
            <span class="sse-tile__label">Aspects de papier</span>
            <span class="sse-tile__value"><?= count($usedStyles) ?></span>
            <span class="sse-tile__hint">sur <?= count($paperStyles) ?> disponibles</span>
        </div>
    </section>

    <section class="sse-panel" data-sse-list data-sse-page-size="50" aria-labelledby="sse-pres-title">
        <h2 id="sse-pres-title" class="sr-only">Liste des modèles</h2>
        <?php if ($total > 0): ?>
            <div class="sse-tabs" data-sse-tabs data-sse-hash role="tablist" aria-label="Filtrer les modèles">
                <button type="button" class="sse-tab" role="tab" id="sse-tab-tous" data-sse-tab="all" aria-selected="true" aria-controls="sse-pres-table">Tous <span class="sse-count"><?= (int) $total ?></span></button>
                <button type="button" class="sse-tab" role="tab" id="sse-tab-actif" data-sse-tab="actif" aria-selected="false" tabindex="-1" aria-controls="sse-pres-table">En service <span class="sse-count"><?= (int) $activeCount ?></span></button>
                <button type="button" class="sse-tab" role="tab" id="sse-tab-prefait" data-sse-tab="prefait" aria-selected="false" tabindex="-1" aria-controls="sse-pres-table">Préfaits <span class="sse-count"><?= (int) $builtinCount ?></span></button>
                <button type="button" class="sse-tab" role="tab" id="sse-tab-perso" data-sse-tab="perso" aria-selected="false" tabindex="-1" aria-controls="sse-pres-table">Personnalisés <span class="sse-count"><?= (int) $customCount ?></span></button>
            </div>
            <div class="sse-toolbar">
                <div class="sse-search">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <label class="sr-only" for="sse-pres-q">Rechercher un modèle</label>
                    <input id="sse-pres-q" class="sse-input" type="search" data-sse-search placeholder="Rechercher un modèle, un bandeau, un titre…" autocomplete="off">
                </div>
                <label class="sr-only" for="sse-pres-style">Aspect du papier</label>
                <select id="sse-pres-style" class="sse-input sse-input--select" data-sse-filter="style">
                    <option value="">Tous les aspects de papier</option>
                    <?php foreach ($paperStyles as $key => $label): ?>
                        <option value="<?= $h($key) ?>"><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="sse-toolbar__meta" role="status" aria-live="polite"><span data-sse-count data-template="%1 / %2">0</span> <span>modèles</span></p>
            </div>

            <div class="sse-table-wrap" id="sse-pres-table">
                <table class="sse-table sse-table--cards" data-sse-drawer="#sse-pres-drawer" data-sse-keep<?= $deepLink !== '' && isset($records[$deepLink]) ? ' data-sse-open-item="' . $h($deepLink) . '"' : '' ?>>
                    <caption class="sr-only">Modèles de présentation des documents</caption>
                    <thead>
                        <tr>
                            <th scope="col" data-sse-sort="name">Modèle</th>
                            <th scope="col" data-sse-sort="style">Aspect du papier</th>
                            <th scope="col" data-sse-sort="status">Statut</th>
                            <th scope="col" class="sse-hide-md" data-sse-sort="origin">Origine</th>
                            <th scope="col" class="sse-end"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prefabs as $prefab): ?>
                            <?php
                            $code = (string) ($prefab['code'] ?? '');
                            $name = (string) ($prefab['label'] ?? $code);
                            $isActive = $code === $activePrefabCode;
                            $isBuiltin = !empty($prefab['is_builtin']);
                            $styleKey = (string) ($prefab['paper_style'] ?? 'clean');
                            $groups = 'all' . ($isActive ? ' actif' : '') . ($isBuiltin ? ' prefait' : ' perso');
                            $search = $name . ' ' . $code . ' ' . (string) ($prefab['description'] ?? '') . ' ' . (string) ($prefab['banner'] ?? '') . ' ' . $styleLabel($styleKey);
                            ?>
                            <tr data-sse-row data-sse-item="<?= $h($code) ?>" data-groups="<?= $h($groups) ?>" data-style="<?= $h($styleKey) ?>"
                                data-search="<?= $h($search) ?>" data-sort-name="<?= $h(mb_strtolower($name)) ?>" data-sort-style="<?= $h($styleLabel($styleKey)) ?>"
                                data-sort-status="<?= $isActive ? '0' : '1' ?>" data-sort-origin="<?= $isBuiltin ? '0' : '1' ?>">
                                <td class="sse-main" data-label="Modèle">
                                    <a class="sse-rowlink" href="<?= $h(url('atak/sse/presentation?modifier=' . rawurlencode($code))) ?>"><?= $h($name) ?></a>
                                    <?php if (!empty($prefab['description'])): ?>
                                        <small><?= $h((string) $prefab['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="sse-nowrap" data-label="Aspect du papier"><span class="sse-tag"><?= $h($styleLabel($styleKey)) ?></span></td>
                                <td class="sse-nowrap" data-label="Statut">
                                    <?php if ($isActive): ?>
                                        <span class="sse-status" data-tone="ok">En service</span>
                                    <?php else: ?>
                                        <span class="sse-status" data-tone="neut">Disponible</span>
                                    <?php endif; ?>
                                </td>
                                <td class="sse-nowrap sse-hide-md" data-label="Origine"><?= $isBuiltin ? 'Préfait' : 'Personnalisé' ?></td>
                                <td class="sse-end sse-nowrap sse-cell-actions">
                                    <div class="sse-row-actions">
                                        <?php if ($canManage && !$isActive): ?>
                                            <button type="button" class="sse-btn sse-btn--sm sse-btn--quiet sse-hide-md" data-sse-open="#sse-pres-activate" data-sse-field-prefab_code="<?= $h($code) ?>" data-sse-text-name="<?= $h($name) ?>">Utiliser</button>
                                        <?php endif; ?>
                                        <span class="sse-chevron" aria-hidden="true"><?= $sseIcon('<path d="m9 6 6 6-6 6"/>') ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="sse-empty" data-sse-empty-filtered hidden>
                <h3>Aucun modèle ne correspond</h3>
                <p>Essayez un autre mot, un autre onglet ou un autre aspect de papier.</p>
                <button type="button" class="sse-btn" data-sse-reset>Réinitialiser les filtres</button>
            </div>
            <div class="sse-more" data-sse-more hidden>
                <button type="button" class="sse-btn"><span>Afficher la suite · <b data-sse-more-count>0</b> restants</span></button>
            </div>
        <?php else: ?>
            <div class="sse-empty">
                <h3>Aucun modèle de présentation</h3>
                <p>Un modèle règle le bandeau, les titres, le pied de page et l’aspect du papier des documents du bureau. Créez le premier pour qu’il habille la rédaction et l’aperçu papier.</p>
                <?php if ($canManage): ?>
                    <button type="button" class="sse-btn sse-btn--primary" data-sse-open="#sse-pres-edit" data-sse-record="">Créer un modèle</button>
                <?php else: ?>
                    <p class="sse-hint">La création des modèles est réservée aux personnels habilités du bureau.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="sse-alert" data-tone="info">
        <div class="sse-alert-body">
            <div class="sse-alert-title">En mission, dans Eden et Zeus</div>
            <div class="sse-alert-text">Posez le module « Présentation des documents SSE » (catégorie COMSPEC SSE) et choisissez le même modèle, ou saisissez le bandeau et le pied de page librement : les opérateurs voient alors la même feuille sur le terminal.</div>
        </div>
    </div>

    <!-- Tiroir d'aperçu : un seul gabarit, rempli au clic sur une ligne avec l'enregistrement du modèle (bloc JSON ci-dessous). -->
    <dialog class="sse-drawer sse-drawer--wide" id="sse-pres-drawer" data-sse-records="#sse-pres-records" aria-labelledby="sse-pres-drawer-title">
        <div class="sse-dialog__head">
            <div>
                <h2 id="sse-pres-drawer-title" data-sse-drawer-title>Modèle</h2>
            </div>
            <button type="button" class="sse-iconbtn" data-sse-close aria-label="Fermer l’aperçu"><?= $icoClose ?></button>
        </div>
        <div class="sse-dialog__body" data-sse-drawer-body></div>
        <template data-sse-template>
            <p class="sse-lede sse-lede--flush" data-sse-if="description" data-sse-text="description"></p>
            <dl class="sse-meta">
                <div><dt>Statut</dt>
                    <dd>
                        <span class="sse-status" data-tone="ok" data-sse-if="is_active">En service</span>
                        <span class="sse-status" data-tone="neut" data-sse-unless="is_active">Disponible</span>
                    </dd></div>
                <div><dt>Aspect du papier</dt>
                    <dd>
                        <?php foreach ($paperStyles as $styleKey => $label): ?>
                            <span class="sse-tag" data-sse-if="paper_style=<?= $h($styleKey) ?>"><?= $h($label) ?></span>
                        <?php endforeach; ?>
                    </dd></div>
                <div><dt>Origine</dt>
                    <dd><span data-sse-if="is_builtin">Préfait</span><span data-sse-unless="is_builtin">Personnalisé</span></dd></div>
                <div><dt>Code</dt><dd class="sse-mono" data-sse-text="code"></dd></div>
                <div data-sse-if="banner"><dt>Bandeau</dt><dd data-sse-text="banner"></dd></div>
                <div data-sse-if="title_person"><dt>Titre de la fiche personne</dt><dd data-sse-text="title_person"></dd></div>
                <div data-sse-if="title_docs"><dt>Titre du dossier documentaire</dt><dd data-sse-text="title_docs"></dd></div>
                <div data-sse-if="subtitle_dossier"><dt>Sous-titre du compte rendu</dt><dd data-sse-text="subtitle_dossier"></dd></div>
                <div data-sse-if="subtitle_feuille"><dt>Sous-titre de la feuille</dt><dd data-sse-text="subtitle_feuille"></dd></div>
                <div data-sse-if="subtitle_docs"><dt>Sous-titre des pièces</dt><dd data-sse-text="subtitle_docs"></dd></div>
                <div data-sse-if="footer"><dt>Pied de page</dt><dd data-sse-text="footer"></dd></div>
                <div data-sse-if="org_line"><dt>Organisme</dt><dd data-sse-text="org_line"></dd></div>
                <div data-sse-if="access_note"><dt>Note d’accès</dt><dd data-sse-text="access_note"></dd></div>
            </dl>
            <div class="sse-block sse-block--paper">
                <h3>Aperçu de la feuille <span class="sse-classif" data-classif="secret" title="Niveau de classification de l’aperçu : Secret (S)">SECRET//NOFORN</span></h3>
                <div class="sse-sheet" data-sse-class-from="paper_style" data-sse-class-prefix="sse-sheet--">
                    <div class="sse-sheet__banner"><span data-sse-text="banner"></span><strong>SECRET//NOFORN</strong></div>
                    <div class="sse-sheet__head">
                        <div>
                            <p class="sse-sheet__org" data-sse-text="org_line"></p>
                            <p class="sse-sheet__title" data-sse-text="title_person"></p>
                            <p class="sse-sheet__sub" data-sse-text="subtitle_feuille"></p>
                        </div>
                        <div class="sse-sheet__seal"><span data-sse-text="seal_top"></span><span data-sse-text="seal_bottom"></span></div>
                    </div>
                    <div class="sse-sheet__lines" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                    <div class="sse-sheet__btns"><span data-sse-text="btn_consult"></span><span data-sse-text="btn_transmit"></span><span data-sse-text="btn_close"></span></div>
                    <p class="sse-sheet__foot" data-sse-text="footer"></p>
                </div>
                <button type="button" class="sse-btn sse-btn--sm" data-sse-open="#sse-pres-paper" data-sse-if="is_full">Voir la feuille complète</button>
                <a class="sse-btn sse-btn--sm" data-sse-unless="is_full" data-sse-attrs="href=url">Voir la feuille complète</a>
            </div>
            <?php if ($canManage): ?>
                <div class="sse-dialog__actions">
                    <button type="button" class="sse-btn" data-sse-open="#sse-pres-edit" data-sse-attrs="data-sse-record=code">Modifier ce modèle</button>
                    <button type="button" class="sse-btn sse-btn--primary" data-sse-open="#sse-pres-activate" data-sse-unless="is_active" data-sse-attrs="data-sse-field-prefab_code=code,data-sse-text-name=label">Utiliser ce modèle</button>
                </div>
            <?php endif; ?>
        </template>
    </dialog>
    <script type="application/json" id="sse-pres-records"><?= json_encode($records, $jsonFlags) ?></script>
    <?php if ($total > 0): ?>
    <!-- Feuille complète : le gabarit papier de la rédaction, rendu une seule fois pour le modèle choisi (?modifier=) ou en service. -->
    <dialog class="sse-modal sse-modal--paper" id="sse-pres-paper" aria-labelledby="sse-pres-paper-title">
        <div class="sse-dialog__head">
            <div>
                <h2 id="sse-pres-paper-title">Feuille complète — <?= $h((string) ($fullPrefab['label'] ?? '')) ?></h2>
                <p>Telle qu’elle apparaît à la rédaction, au niveau Secret (S).</p>
            </div>
            <button type="button" class="sse-iconbtn" data-sse-close aria-label="Fermer"><?= $icoClose ?></button>
        </div>
        <div class="sse-dialog__body">
            <?php
            $document = $previewDoc;
            $documentChrome = $fullPrefab;
            $livePreview = false;
            require __DIR__ . '/document_paper.php';
            ?>
        </div>
    </dialog>

    <?php endif; ?>

    <?php if ($canManage): ?>
        <!-- Créer / modifier : même formulaire, même route, mêmes champs que l'ancienne page. -->
        <dialog class="sse-modal sse-modal--wide" id="sse-pres-edit" data-sse-records="#sse-pres-records" data-sse-mode="new" aria-labelledby="sse-pres-edit-title">
            <form method="post" action="<?= $h(url('atak/sse/presentation')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="code" value="">
                <div class="sse-dialog__head">
                    <div>
                        <h2 id="sse-pres-edit-title"><span data-sse-when="new">Nouveau modèle de présentation</span><span data-sse-when="edit">Modifier le modèle</span></h2>
                        <p>Les documents du bureau reprennent ces textes et cet aspect de papier dès l’enregistrement du modèle en service.</p>
                    </div>
                    <button type="button" class="sse-iconbtn" data-sse-close aria-label="Fermer"><?= $icoClose ?></button>
                </div>
                <div class="sse-dialog__body">
                    <div class="sse-form-grid">
                        <div class="sse-field">
                            <label for="pres-label">Nom du modèle</label>
                            <input id="pres-label" class="sse-input" name="label" type="text" required maxlength="160" data-sse-focus>
                        </div>
                        <div class="sse-field">
                            <label for="pres-style">Aspect du papier</label>
                            <select id="pres-style" class="sse-input" name="paper_style">
                                <?php foreach ($paperStyles as $styleKey => $label): ?>
                                    <option value="<?= $h($styleKey) ?>"><?= $h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sse-field sse-span">
                            <label for="pres-desc">Description</label>
                            <textarea id="pres-desc" class="sse-input" name="description" rows="2" maxlength="400"></textarea>
                        </div>
                        <div class="sse-field sse-span">
                            <label for="pres-banner">Bandeau (en-tête de feuille)</label>
                            <input id="pres-banner" class="sse-input" name="banner" type="text" required maxlength="220">
                        </div>
                        <div class="sse-field">
                            <label for="pres-title-person">Titre — fiche personne</label>
                            <input id="pres-title-person" class="sse-input" name="title_person" type="text" maxlength="120">
                        </div>
                        <div class="sse-field">
                            <label for="pres-title-docs">Titre — dossier documentaire</label>
                            <input id="pres-title-docs" class="sse-input" name="title_docs" type="text" maxlength="120">
                        </div>
                        <div class="sse-field sse-span">
                            <label for="pres-sub-dossier">Sous-titre — compte rendu</label>
                            <input id="pres-sub-dossier" class="sse-input" name="subtitle_dossier" type="text" maxlength="180">
                        </div>
                        <div class="sse-field sse-span">
                            <label for="pres-sub-feuille">Sous-titre — feuille détaillée</label>
                            <input id="pres-sub-feuille" class="sse-input" name="subtitle_feuille" type="text" maxlength="180">
                        </div>
                        <div class="sse-field sse-span">
                            <label for="pres-sub-docs">Sous-titre — pièces</label>
                            <input id="pres-sub-docs" class="sse-input" name="subtitle_docs" type="text" maxlength="180">
                        </div>
                        <div class="sse-field sse-span">
                            <label for="pres-footer">Pied de page (mention RP / preuve)</label>
                            <textarea id="pres-footer" class="sse-input" name="footer" rows="2" required maxlength="400"></textarea>
                        </div>
                        <div class="sse-field">
                            <label for="pres-quality">Libellé qualité d’exploitation</label>
                            <input id="pres-quality" class="sse-input" name="quality_prefix" type="text" maxlength="80">
                        </div>
                        <div class="sse-field">
                            <label for="pres-org">Ligne d’organisme</label>
                            <input id="pres-org" class="sse-input" name="org_line" type="text" maxlength="120">
                        </div>
                        <div class="sse-field sse-span">
                            <label for="pres-access">Note d’accès</label>
                            <input id="pres-access" class="sse-input" name="access_note" type="text" maxlength="400">
                        </div>
                        <div class="sse-field">
                            <label for="pres-seal-top">Sceau — haut</label>
                            <input id="pres-seal-top" class="sse-input" name="seal_top" type="text" maxlength="80">
                        </div>
                        <div class="sse-field">
                            <label for="pres-seal-bottom">Sceau — bas</label>
                            <input id="pres-seal-bottom" class="sse-input" name="seal_bottom" type="text" maxlength="80">
                        </div>
                    </div>
                    <div class="sse-form-grid sse-form-grid--3">
                        <div class="sse-field">
                            <label for="pres-btn-c">Bouton consultation</label>
                            <input id="pres-btn-c" class="sse-input" name="btn_consult" type="text" maxlength="40">
                        </div>
                        <div class="sse-field">
                            <label for="pres-btn-t">Bouton transmettre</label>
                            <input id="pres-btn-t" class="sse-input" name="btn_transmit" type="text" maxlength="40">
                        </div>
                        <div class="sse-field">
                            <label for="pres-btn-x">Bouton fermer</label>
                            <input id="pres-btn-x" class="sse-input" name="btn_close" type="text" maxlength="40">
                        </div>
                    </div>
                    <label class="sse-option">
                        <input type="checkbox" name="make_active" value="1">
                        <span>Utiliser ce modèle dès l’enregistrement</span>
                    </label>
                </div>
                <div class="sse-dialog__foot">
                    <button type="button" class="sse-btn" data-sse-close>Annuler</button>
                    <button type="submit" class="sse-btn sse-btn--primary">Enregistrer la présentation</button>
                </div>
            </form>
        </dialog>

        <!-- Utiliser un modèle : confirmation (change l'aspect de tous les documents du bureau). -->
        <dialog class="sse-modal" id="sse-pres-activate" aria-labelledby="sse-pres-activate-title">
            <form method="post" action="<?= $h(url('atak/sse/presentation/actif')) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrf) ?>">
                <input type="hidden" name="prefab_code" value="">
                <div class="sse-dialog__head">
                    <div>
                        <h2 id="sse-pres-activate-title">Utiliser « <span data-sse-bind="name"></span> » ?</h2>
                    </div>
                    <button type="button" class="sse-iconbtn" data-sse-close aria-label="Fermer"><?= $icoClose ?></button>
                </div>
                <div class="sse-dialog__body">
                    <p class="sse-lede sse-lede--flush">Les documents du bureau et l’aperçu papier reprendront aussitôt le bandeau, les titres, le pied de page et l’aspect du papier de ce modèle. Vous pourrez revenir à un autre modèle à tout moment.</p>
                </div>
                <div class="sse-dialog__foot">
                    <button type="button" class="sse-btn" data-sse-close>Annuler</button>
                    <button type="submit" class="sse-btn sse-btn--primary" data-sse-focus>Utiliser ce modèle</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>
</div>
