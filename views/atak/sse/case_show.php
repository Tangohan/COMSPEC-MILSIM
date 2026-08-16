<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $case */
/** @var list<array<string,mixed>> $people */
/** @var list<array<string,mixed>> $availablePeople */
/** @var list<array<string,mixed>> $armaInbox */
/** @var list<array<string,mixed>> $armaSeizures */
/** @var array<string,mixed> $casePresets */
/** @var list<array<string,mixed>> $notes */
/** @var list<array<string,mixed>> $evidence */
$armaInbox = is_array($armaInbox ?? null) ? $armaInbox : [];
$armaSeizures = is_array($armaSeizures ?? null) ? $armaSeizures : [];
$casePresets = is_array($casePresets ?? null) ? $casePresets : [];
$identityQuick = is_array($casePresets['identity_quick'] ?? null) ? $casePresets['identity_quick'] : [];
$identityStatuses = is_array($casePresets['identity_status'] ?? null) ? $casePresets['identity_status'] : [
    ['key' => 'civil', 'label' => 'Civil', 'hint' => ''],
    ['key' => 'combattant', 'label' => 'Combattant', 'hint' => ''],
    ['key' => 'detenu', 'label' => 'Détenu', 'hint' => ''],
    ['key' => 'prioritaire', 'label' => 'Prioritaire', 'hint' => ''],
];
$evidencePresets = is_array($casePresets['evidence'] ?? null) ? $casePresets['evidence'] : [];
if ($identityStatuses !== [] && !isset($identityStatuses[0]) && is_string(array_key_first($identityStatuses) ?: null)) {
    $tmp = [];
    foreach ($identityStatuses as $k => $lab) {
        $tmp[] = ['key' => (string) $k, 'label' => (string) $lab, 'hint' => ''];
    }
    $identityStatuses = $tmp;
}
/** @var array<string,mixed> $mapState */
$mapState = is_array($mapState ?? null) ? $mapState : [];
/** @var list<array<string,mixed>> $mapFeatures */
$mapFeatures = is_array($mapFeatures ?? null) ? $mapFeatures : [];
/** @var array<string,string> $classifications */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
/** @var bool $canExport */
$linkedIds = array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $people);
$classKey = (string) ($case['classification'] ?? '');
$classBadge = match ($classKey) {
    'confidentiel' => 'badge badge--amber',
    'tres_restreint' => 'badge badge--red',
    'interne' => 'badge badge--gray',
    default => 'badge',
};
$caseUrl = url('atak/sse/dossiers/' . (int) $case['id']);
$frDate = static function (mixed $raw): string {
    $stamp = $raw !== null && (string) $raw !== '' ? strtotime((string) $raw) : false;

    return $stamp ? date('d/m/Y \à H\hi', $stamp) : '';
};
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <strong><?= $h($case['reference_code']) ?></strong>
</div>

<div class="page-heading sse-case-head">
    <div>
        <div class="page-heading-overline">Dossier // <?= $h($case['reference_code']) ?></div>
        <h1><?= $h($case['title']) ?></h1>
        <p>
            <span class="<?= $h($classBadge) ?>"><?= $h($case['classification_label']) ?></span>
            &nbsp;
            <span class="badge"><?= $h($case['status_label']) ?></span>
            <?php if (!empty($case['has_unlock_code'])): ?>
                &nbsp;<span class="badge badge--gray">Ouverture protégée par un mot de passe</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="sse-case-actions">
        <span class="sse-case-actions__label">Ce que l’on peut faire de ce dossier</span>
        <div class="sse-case-actions__row">
            <a class="btn" href="<?= $h($caseUrl . '/compte-rendu') ?>">Compte rendu</a>
            <a class="btn btn--ghost" href="<?= $h($caseUrl . '/correlations') ?>">Liens entre les éléments</a>
            <a class="btn btn--ghost" href="<?= $h($caseUrl . '/declassification') ?>">Version expurgée</a>
            <?php if ($canManage): ?>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/toiles/nouveau?case=' . (int) $case['id'])) ?>">Ouvrir une investigation</a>
            <?php endif; ?>
            <?php if ($canExport || $canManage): ?>
                <a class="btn btn--ghost" href="<?= $h($caseUrl . '/pdf') ?>">Exporter en PDF</a>
            <?php endif; ?>
            <?php if ($canManage || $canExport): ?>
                <a class="btn btn--ghost" href="<?= $h($caseUrl . '/emport?format=athena') ?>">Emporter le pack dossier</a>
                <a class="btn btn--ghost" href="<?= $h($caseUrl . '/emport?format=arma') ?>">Pack terrain Arma</a>
                <a class="btn btn--ghost" href="<?= $h($caseUrl . '/emport?format=sqf') ?>">Script Arma (.sqf)</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/case_progress.php'; ?>

<section class="panel sse-desk-panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.01</span>
            Chemise du dossier
        </div>
        <div class="panel-header__end">
            <div class="panel-meta">Pièce de garde — à joindre à toute transmission</div>
            <?php $sectionKey = '01.01'; require __DIR__ . '/partials/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php
        $coverStats = [
            'Personnes rattachées' => count($people ?? []),
            'Notes classifiées' => count($notes ?? []),
            'Preuves versées' => count($evidence ?? []),
            'Sites exploités' => count($caseSites ?? []),
        ];
        require __DIR__ . '/partials/case_cover.php';
        ?>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.02</span>
            Synthèse du dossier
        </div>
        <div class="panel-header__end">
            <?php if (!empty($case['updated_at'])): ?>
                <div class="panel-meta">Dernière modification le <?= $h($frDate($case['updated_at'])) ?></div>
            <?php endif; ?>
            <?php $sectionKey = '01.02'; require __DIR__ . '/partials/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php $summary = trim((string) ($case['summary'] ?? '')); ?>
        <?php if ($summary !== ''): ?>
            <p class="sse-case-summary"><?= nl2br($h($summary)) ?></p>
        <?php else: ?>
            <p class="muted">Aucune synthèse rédigée. C’est le premier texte que lira quiconque ouvre ce dossier.</p>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <button type="button" class="sse-inline-action" data-sse-modal-open="sse-modal-summary">
                Modifier l’intitulé, la synthèse ou la classification
            </button>
        <?php endif; ?>
    </div>
</section>

<div class="grid-2">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">01.03</span>
                Identités rattachées
            </div>
            <div class="panel-header__end">
                <div class="panel-meta"><?= count($people) ?></div>
                <?php $sectionKey = '01.03'; require __DIR__ . '/partials/panel_section_info.php'; ?>
            </div>
        </div>
        <div class="panel-body">
            <?php if ($people === []): ?>
                <p class="muted">
                    Personne n’est rattaché à ce dossier. Tant qu’il en est ainsi, le dossier
                    ne désigne personne.
                </p>
            <?php else: ?>
                <ul class="sse-case-people">
                    <?php foreach ($people as $p): ?>
                        <li>
                            <a class="sse-case-person" href="<?= $h(url('atak/sse/identites/' . (int) ($p['id'] ?? 0))) ?>">
                                <span class="record-name">
                                    <?= $h($p['display_name'] ?? '') ?>
                                    <?php if (!empty($p['from_arma'])): ?>
                                        <span class="sse-src-pill" title="Remontée terminal / Arma">Terrain</span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($p['status_label'])): ?>
                                    <span class="record-sub"><?= $h($p['status_label']) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <button type="button" class="btn" data-sse-modal-open="sse-modal-identity">
                    Ajouter une identité
                </button>
                <?php if ($armaInbox !== []): ?>
                    <p class="sse-arma-hint"><?= count($armaInbox) ?> remontée(s) Arma disponibles à rattacher.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">01.04</span>
                Pièces versées
            </div>
            <div class="panel-header__end">
                <div class="panel-meta"><?= count($evidence) ?></div>
                <?php $sectionKey = '01.04'; require __DIR__ . '/partials/panel_section_info.php'; ?>
            </div>
        </div>
        <div class="panel-body">
            <?php if ($evidence === []): ?>
                <p class="muted">Aucune pièce versée : ni photographie, ni saisie, ni capture.</p>
            <?php else: ?>
                <ul class="sse-ev-grid">
                    <?php foreach ($evidence as $e): ?>
                        <li class="sse-ev-card">
                            <?php if (!empty($e['url'])): ?>
                                <a class="sse-ev-card__shot" href="<?= $h($e['url']) ?>" target="_blank" rel="noopener">
                                    <img src="<?= $h($e['url']) ?>" alt="<?= $h($e['label'] ?? 'Pièce versée') ?>" loading="lazy">
                                </a>
                            <?php else: ?>
                                <span class="sse-ev-card__shot is-empty">Sans image</span>
                            <?php endif; ?>
                            <strong><?= $h($e['label'] ?? '') ?></strong>
                            <?php if (!empty($e['caption'])): ?>
                                <span><?= $h($e['caption']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($e['created_at'])): ?>
                                <em>Versée le <?= $h($frDate($e['created_at'])) ?></em>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <button type="button" class="btn" data-sse-modal-open="sse-modal-evidence">
                    Verser une pièce
                </button>
                <?php if ($armaSeizures !== []): ?>
                    <p class="sse-arma-hint"><?= count($armaSeizures) ?> saisie(s) terrain des sites rattachés.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require __DIR__ . '/partials/case_modals.php'; ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.05</span>
            Sites exploités
        </div>
        <div class="panel-header__end">
            <div class="panel-meta">Rattachés à ce dossier</div>
            <?php $sectionKey = '01.05'; require __DIR__ . '/partials/panel_section_info.php'; ?>
        </div>
    </div>

    <?php if (($caseSites ?? []) === []): ?>
        <div class="panel-body">
            <p class="muted">
                Aucun site rattaché. Un site ouvert depuis le terrain avec la référence
                de ce dossier y apparaîtra automatiquement.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Référence</th>
                    <th>Site</th>
                    <th>Statut</th>
                    <th>Fouille</th>
                    <th>Saisies</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($caseSites as $s):
                    $cnt = ($siteCounts ?? [])[(int) ($s['id'] ?? 0)] ?? ['rooms' => 0, 'rooms_checked' => 0, 'seizures' => 0];
                    $pct = $cnt['rooms'] > 0 ? (int) round(($cnt['rooms_checked'] / $cnt['rooms']) * 100) : 0;
                    $pctClass = $pct >= 100 ? 'is-good' : ($pct >= 50 ? 'is-fair' : '');
                ?>
                    <tr>
                        <td><span class="record-id"><?= $h($s['reference_code'] ?? '') ?></span></td>
                        <td>
                            <span class="record-name"><?= $h($s['name'] ?? '') ?></span>
                            <span class="record-sub"><?= $h($s['site_type_label'] ?? '') ?></span>
                        </td>
                        <td><span class="badge"><?= $h($s['status_label'] ?? '') ?></span></td>
                        <td>
                            <span class="sse-score-cell">
                                <span class="sse-gauge <?= $h($pctClass) ?>">
                                    <span style="width: <?= $h((string) $pct) ?>%"></span>
                                </span>
                                <span class="sse-sample-score"><?= (int) $cnt['rooms_checked'] ?>/<?= (int) $cnt['rooms'] ?></span>
                            </span>
                        </td>
                        <td class="record-id"><?= (int) $cnt['seizures'] ?></td>
                        <td>
                            <a class="btn-open" href="<?= $h(url('atak/sse/sites/' . (int) ($s['id'] ?? 0))) ?>">Ouvrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.06</span>
            Notes classifiées
        </div>
        <div class="panel-header__end">
            <div class="panel-meta"><?= count($notes) ?></div>
            <?php $sectionKey = '01.06'; require __DIR__ . '/partials/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <?php if ($notes === []): ?>
            <p class="muted">Aucune note. C’est ici que se consignent les observations qui n’ont pas leur place dans la synthèse.</p>
        <?php else: ?>
            <ul class="sse-case-notes">
                <?php foreach ($notes as $n): ?>
                    <li class="sse-case-note">
                        <div class="sse-case-note__head">
                            <span class="badge"><?= $h($n['classification_label']) ?></span>
                            <span><?= $h($n['author_label'] ?? 'Opérateur') ?><?php
                                $noteDate = $frDate($n['created_at'] ?? null);
                                echo $noteDate !== '' ? ' — ' . $h($noteDate) : '';
                            ?></span>
                        </div>
                        <p><?= nl2br($h($n['body'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <details class="sse-fold">
                <summary>Ajouter une note</summary>
                <form method="post" action="<?= $h($caseUrl . '/notes') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="body">Ce que vous voulez consigner</label>
                    <textarea id="body" name="body" required></textarea>
                    <label for="note_class">Qui a le droit de lire cette note</label>
                    <select id="note_class" name="classification">
                        <?php foreach ($classifications as $k => $lab): ?>
                            <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn" type="submit">Ajouter la note</button>
                </form>
            </details>
        <?php endif; ?>
    </div>
</section>

<section id="tacmap" class="panel sse-tacmap-panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.07</span>
            Carte tactique
        </div>
        <div class="panel-header__end">
            <div class="panel-meta">
                Snapshot permanent du dossier
                <?php if (!empty($mapState['updated_at'])): ?>
                    · mémorisé le <?= $h(date('d/m/Y H:i', strtotime((string) $mapState['updated_at']) ?: time())) ?>
                <?php endif; ?>
            </div>
            <?php $sectionKey = '01.07'; require __DIR__ . '/partials/panel_section_info.php'; ?>
        </div>
    </div>
    <div class="panel-body">
        <p class="sse-note">
            Placez des pings propres à ce dossier, mémorisez la vue, puis capturez un instantané
            versé aux pièces. Les points avec coordonnées terrain apparaissent sur la Tacmap ATAK
            via le calque « Dossiers SSE ».
        </p>

        <div class="sse-tacmap-layout">
            <div class="sse-tacmap-main">
                <div class="sse-tacmap-toolbar">
                    <label for="sse-tacmap-basemap">Fond de carte</label>
                    <select id="sse-tacmap-basemap" class="sse-tacmap-basemap">
                        <option value="dark">Sombre</option>
                        <option value="light">Clair</option>
                        <option value="street">Plan</option>
                        <option value="relief">Relief</option>
                    </select>
                </div>
                <div id="sse-tacmap" class="sse-tacmap" role="application" aria-label="Carte tactique du dossier"></div>
                <?php if ($canManage): ?>
                    <p class="sse-tacmap-hint muted">Clic sur la carte pour placer un ping. Glissez pour déplacer la vue — elle est enregistrée automatiquement.</p>
                <?php endif; ?>
            </div>

            <aside class="sse-tacmap-side" aria-label="Points du dossier">
                <div class="sse-tacmap-side__head">
                    <strong>Points mémorisés</strong>
                    <span id="sse-tacmap-count"><?= count($mapFeatures) ?></span>
                </div>
                <ul class="sse-tacmap-list" id="sse-tacmap-list">
                    <?php if ($mapFeatures === []): ?>
                        <li class="sse-tacmap-list__empty" id="sse-tacmap-empty">Aucun ping pour l’instant.</li>
                    <?php else: ?>
                        <?php foreach ($mapFeatures as $feat): ?>
                            <li data-feature-id="<?= (int) $feat['id'] ?>">
                                <span class="sse-tacmap-dot" style="background:<?= $h($feat['color'] ?? '#34d399') ?>"></span>
                                <div>
                                    <strong><?= $h($feat['label'] ?? '') ?></strong>
                                    <em><?= $h($feat['kind_label'] ?? 'Ping') ?>
                                        <?php if (($feat['arma_x'] ?? null) !== null && ($feat['arma_y'] ?? null) !== null): ?>
                                            · terrain <?= $h(number_format((float) $feat['arma_x'], 0, ',', ' ')) ?> / <?= $h(number_format((float) $feat['arma_y'], 0, ',', ' ')) ?>
                                        <?php elseif (($feat['lat'] ?? null) !== null): ?>
                                            · <?= $h(number_format((float) $feat['lat'], 4)) ?>, <?= $h(number_format((float) $feat['lng'], 4)) ?>
                                        <?php endif; ?>
                                    </em>
                                </div>
                                <?php if ($canManage): ?>
                                    <button type="button" class="btn btn--ghost btn--sm" data-sse-del-feature="<?= (int) $feat['id'] ?>">Retirer</button>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <?php if ($canManage): ?>
                    <form id="sse-tacmap-ping-form" class="sse-tacmap-ping-form" autocomplete="off">
                        <label for="sse-ping-label">Libellé du ping</label>
                        <input id="sse-ping-label" name="label" type="text" maxlength="160" placeholder="Ex. Accès nord" required>
                        <label for="sse-ping-note">Note (optionnel)</label>
                        <input id="sse-ping-note" name="note" type="text" maxlength="500" placeholder="Observation courte">
                        <div class="sse-tacmap-ping-coords">
                            <div>
                                <label for="sse-ping-ax">Coordonnée terrain X</label>
                                <input id="sse-ping-ax" name="arma_x" type="number" step="any" placeholder="optionnel">
                            </div>
                            <div>
                                <label for="sse-ping-ay">Coordonnée terrain Y</label>
                                <input id="sse-ping-ay" name="arma_y" type="number" step="any" placeholder="optionnel">
                            </div>
                        </div>
                        <label class="sse-check">
                            <input type="checkbox" id="sse-atak-layer" <?= !empty($mapState['atak_layer_enabled']) ? 'checked' : '' ?>>
                            Publier ce dossier sur le calque ATAK « Dossiers SSE »
                        </label>
                        <p class="muted sse-tacmap-pending" id="sse-tacmap-pending" hidden>
                            Cliquez la carte pour fixer la position, puis validez.
                        </p>
                        <button class="btn btn--sm" type="submit" id="sse-ping-submit" disabled>Placer le ping</button>
                    </form>

                    <form id="sse-tacmap-form" class="sse-tacmap-form" method="post" action="<?= $h($caseUrl . '/tacmap-capture') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="image_data" id="sse-tacmap-data" value="">
                        <input type="hidden" name="center_lat" id="sse-tacmap-lat" value="">
                        <input type="hidden" name="center_lng" id="sse-tacmap-lng" value="">
                        <input type="hidden" name="zoom" id="sse-tacmap-zoom" value="">
                        <input type="hidden" name="map_id" id="sse-tacmap-mapid" value="<?= (int) ($mapState['map_id'] ?? 1) ?>">
                        <input type="hidden" name="basemap" id="sse-tacmap-basemap-field" value="<?= $h((string) (($mapState['snapshot_meta']['basemap'] ?? null) ?: 'dark')) ?>">
                        <input type="hidden" name="atak_layer_enabled" id="sse-tacmap-atakflag" value="<?= !empty($mapState['atak_layer_enabled']) ? '1' : '0' ?>">
                        <label for="sse-tacmap-caption">Légende de la capture</label>
                        <input id="sse-tacmap-caption" name="caption" type="text" maxlength="200" placeholder="Ex. Approche nord du site">
                        <div class="sse-tacmap-actions">
                            <button class="btn btn--ghost" type="button" id="sse-tacmap-save-btn">Mémoriser la vue</button>
                            <button class="btn" type="button" id="sse-tacmap-capture-btn">Capturer la vue</button>
                        </div>
                    </form>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php
require __DIR__ . '/partials/case_analytical.php';
require __DIR__ . '/partials/case_engine.php';
?>

<p class="sse-case-back"><a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">← Retour aux dossiers</a></p>

<?php
$sseNeedLeaflet = true;
$mapBoot = [
    'caseId' => (int) ($case['id'] ?? 0),
    'canManage' => (bool) $canManage,
    'csrf' => \App\Core\Csrf::token(),
    'urls' => [
        'save' => url('atak/sse/dossiers/' . (int) ($case['id'] ?? 0) . '/carte'),
        'add' => url('atak/sse/dossiers/' . (int) ($case['id'] ?? 0) . '/carte/points'),
        'del' => url('atak/sse/dossiers/' . (int) ($case['id'] ?? 0) . '/carte/points/__ID__/supprimer'),
    ],
    'state' => [
        'center_lat' => (float) ($mapState['center_lat'] ?? 48.8566),
        'center_lng' => (float) ($mapState['center_lng'] ?? 2.3522),
        'zoom' => (int) ($mapState['zoom'] ?? 6),
        'map_id' => (int) ($mapState['map_id'] ?? 1),
        'basemap' => (isset($mapState['snapshot_meta']) && is_array($mapState['snapshot_meta']) && !empty($mapState['snapshot_meta']['basemap']))
            ? (string) $mapState['snapshot_meta']['basemap']
            : null,
        'atak_layer_enabled' => !empty($mapState['atak_layer_enabled']),
    ],
    'features' => array_values(array_map(static function (array $f): array {
        return [
            'id' => (int) ($f['id'] ?? 0),
            'kind' => (string) ($f['kind'] ?? 'ping'),
            'kind_label' => (string) ($f['kind_label'] ?? 'Ping'),
            'label' => (string) ($f['label'] ?? ''),
            'note' => (string) ($f['note'] ?? ''),
            'color' => (string) ($f['color'] ?? '#34d399'),
            'lat' => $f['lat'] ?? null,
            'lng' => $f['lng'] ?? null,
            'arma_x' => $f['arma_x'] ?? null,
            'arma_y' => $f['arma_y'] ?? null,
        ];
    }, $mapFeatures)),
];
$sseExtraScripts = '<script src="' . htmlspecialchars(asset_url('assets/js/sse-case-modals.js'), ENT_QUOTES, 'UTF-8') . '?v=202608160430"></script>'
    . '<script>window.SSE_CASE_MAP = ' . json_encode($mapBoot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . ';</script>'
    . '<script src="' . htmlspecialchars(asset_url('assets/js/sse-case-map.js'), ENT_QUOTES, 'UTF-8') . '?v=202608161630"></script>';
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
