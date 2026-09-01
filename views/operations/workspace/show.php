<?php
declare(strict_types=1);
/** @var array<string, mixed> $workspace */
$workspace = $workspace ?? [];
$op = $workspace['operation'] ?? [];
$tab = (string) ($tab ?? 'overview');
$canPlan = !empty($canPlan);
$canIntel = !empty($canIntel);
$canOrders = !empty($canOrders);
$canPublish = !empty($canPublish);
$canChangePhase = !empty($canChangePhase);
$csrfToken = (string) ($csrfToken ?? '');
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$uuid = (string) ($op['uuid'] ?? '');
$base = url('operations/' . $uuid);
$classCode = (string) ($op['classification'] ?? 'restricted');
$tabs = [
    'overview' => 'Synthèse',
    'tactical' => 'Vue terrain',
    'tasks' => 'Tâches',
];
if ($canPlan) {
    $tabs = [
        'overview' => 'Synthèse',
        'planning' => 'Plan',
        'intel' => 'Renseignement',
        'targets' => 'Objectifs',
        'orders' => 'Ordres',
        'personnel' => 'Personnel',
        'tasks' => 'Tâches',
        'activity' => 'Journal',
        'tactical' => 'Vue terrain',
    ];
} elseif ($canIntel) {
    $tabs['intel'] = 'Renseignement';
    $tabs['targets'] = 'Objectifs';
} elseif ($canOrders) {
    $tabs['orders'] = 'Ordres';
}
$phases = $workspace['phases'] ?? [];
$overlays = $workspace['overlays'] ?? [];
$objects = $workspace['objects'] ?? [];
$tasks = $workspace['tasks'] ?? [];
$targets = $workspace['targets'] ?? [];
$orders = $workspace['orders'] ?? [];
$elements = $workspace['elements'] ?? [];
$members = $workspace['members'] ?? [];
$activity = $workspace['activity'] ?? [];
$graphics = $workspace['graphics'] ?? [];
$versions = $workspace['versions'] ?? [];
?>
<div class="ops-ws ops-ws--workspace class-<?= $h($classCode) ?>">
    <header class="ops-ws__ophead">
        <div>
            <p class="ops-ws__crumb"><a href="<?= $h(url('operations')) ?>">Opérations</a> · <?= $h($op['code'] ?? '') ?></p>
            <h1><?= $h($op['name'] ?? '') ?></h1>
            <p class="ops-ws__badges">
                <span><?= $h($op['status_label'] ?? '') ?></span>
                <span><?= $h($op['classification_label'] ?? '') ?></span>
                <span><?= $h($op['phase_label'] ?? '') ?></span>
            </p>
        </div>
        <div class="ops-ws__opactions">
            <a class="ops-ws__btn ops-ws__btn--ghost" href="<?= $h(url('tactical/' . $uuid)) ?>">Ouvrir la vue terrain</a>
            <?php if ($canChangePhase && $phases !== []): ?>
            <form method="post" action="<?= $h($base . '/phase') ?>" class="ops-ws__inline">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <label class="sr-only" for="ops-phase">Phase en cours</label>
                <select id="ops-phase" name="phase_id" class="bo-select" onchange="this.form.submit()">
                    <?php foreach ($phases as $ph): ?>
                    <option value="<?= (int) $ph['id'] ?>" <?= ((int) ($op['current_phase_id'] ?? 0) === (int) $ph['id']) ? 'selected' : '' ?>>
                        <?= $h(($ph['code'] ?? '') . ' — ' . ($ph['name'] ?? '')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($flashOk !== ''): ?><p class="ops-ws__flash ops-ws__flash--ok"><?= $h($flashOk) ?></p><?php endif; ?>
    <?php if ($flashErr !== ''): ?><p class="ops-ws__flash ops-ws__flash--err"><?= $h($flashErr) ?></p><?php endif; ?>

    <nav class="ops-ws__tabs" aria-label="Sections de l’opération">
        <?php foreach ($tabs as $id => $label): ?>
        <a class="<?= $tab === $id ? 'is-active' : '' ?>" href="<?= $h($base . '?tab=' . $id) ?>"><?= $h($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="ops-ws__body">
        <?php if ($tab === 'overview'): ?>
            <section class="ops-ws__panel">
                <h2>Vue d’ensemble</h2>
                <?php if (trim((string) ($op['description'] ?? '')) !== ''): ?>
                <p><?= nl2br($h($op['description'])) ?></p>
                <?php else: ?>
                <p class="ops-ws__muted">Aucune intention rédigée pour cette opération.</p>
                <?php endif; ?>
                <?php if ($canPlan): ?>
                <form method="post" action="<?= $h($base . '/statut') ?>" class="ops-ws__inline">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <label>Statut
                        <select name="status" class="bo-select">
                            <?php foreach ($statusOptions as $opt): ?>
                            <option value="<?= $h($opt['value']) ?>" <?= (($op['status'] ?? '') === $opt['value']) ? 'selected' : '' ?>><?= $h($opt['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="ops-ws__btn">Enregistrer</button>
                </form>
                <?php endif; ?>
                <dl class="ops-ws__stats">
                    <div><dt>Calques</dt><dd><?= count($overlays) ?></dd></div>
                    <div><dt>Graphiques</dt><dd><?= count($objects) ?></dd></div>
                    <div><dt>Tâches</dt><dd><?= count($tasks) ?></dd></div>
                    <div><dt>Objectifs</dt><dd><?= count($targets) ?></dd></div>
                    <div><dt>Ordres</dt><dd><?= count($orders) ?></dd></div>
                </dl>
            </section>
        <?php elseif ($tab === 'planning' && $canPlan): ?>
            <div class="ops-ws__planner" id="ops-planner"
                 data-uuid="<?= $h($uuid) ?>"
                 data-csrf="<?= $h($csrfToken) ?>"
                 data-snapshot="<?= $h($base . '/snapshot') ?>"
                 data-store="<?= $h($base . '/objets') ?>">
                <aside class="ops-ws__palette">
                    <h2>Graphiques</h2>
                    <input type="search" id="ops-graphic-search" placeholder="Rechercher un graphique…">
                    <?php foreach ($graphics as $groupId => $group): ?>
                    <details open>
                        <summary><?= $h($group['label'] ?? $groupId) ?></summary>
                        <ul>
                            <?php foreach ($group['items'] ?? [] as $item): ?>
                            <li>
                                <button type="button" class="ops-ws__graphic" data-type="<?= $h($item['id']) ?>" data-label="<?= $h($item['label']) ?>">
                                    <?= $h($item['label']) ?>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    <?php endforeach; ?>
                </aside>
                <div class="ops-ws__canvas-wrap">
                    <svg id="ops-canvas" viewBox="0 0 1000 1000" role="img" aria-label="Carte de planification">
                        <defs>
                            <pattern id="ops-grid" width="50" height="50" patternUnits="userSpaceOnUse">
                                <path d="M 50 0 L 0 0 0 50" fill="none" stroke="rgba(148,163,184,.18)" stroke-width="1"/>
                            </pattern>
                        </defs>
                        <rect width="1000" height="1000" fill="#0b1220"/>
                        <rect width="1000" height="1000" fill="url(#ops-grid)"/>
                        <g id="ops-objects"></g>
                    </svg>
                    <p class="ops-ws__hint">Choisissez un graphique à gauche, puis cliquez sur la carte. Seuls les calques publiés apparaissent ensuite sur la vue terrain et en session.</p>
                </div>
                <aside class="ops-ws__layers">
                    <h2>Calques</h2>
                    <?php foreach ($overlays as $ov): ?>
                    <article class="ops-ws__layer">
                        <label>
                            <input type="checkbox" class="ops-layer-toggle" data-overlay="<?= (int) $ov['id'] ?>" checked>
                            <strong><?= $h($ov['name']) ?></strong>
                        </label>
                        <p><?= $h($ov['kind_label'] ?? '') ?> · <?= $h($ov['workflow_label'] ?? '') ?></p>
                        <?php if ($canPublish || $canPlan): ?>
                        <form method="post" action="<?= $h($base . '/calques/' . (int) $ov['id'] . '/workflow') ?>">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                            <select name="workflow" class="bo-select">
                                <option value="draft" <?= ($ov['workflow'] ?? '') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                                <option value="review" <?= ($ov['workflow'] ?? '') === 'review' ? 'selected' : '' ?>>En revue</option>
                                <option value="approved" <?= ($ov['workflow'] ?? '') === 'approved' ? 'selected' : '' ?>>Approuvé</option>
                                <?php if ($canPublish): ?>
                                <option value="published" <?= ($ov['workflow'] ?? '') === 'published' ? 'selected' : '' ?>>Publier sur la vue terrain</option>
                                <?php endif; ?>
                            </select>
                            <button type="submit" class="ops-ws__btn ops-ws__btn--tiny">Appliquer</button>
                        </form>
                        <?php endif; ?>
                        <?php $vers = $versions[(int) $ov['id']] ?? []; ?>
                        <?php if ($vers !== []): ?>
                        <details>
                            <summary>Historique</summary>
                            <ul class="ops-ws__versions">
                                <?php foreach ($vers as $ver): ?>
                                <li>
                                    v<?= (int) $ver['version'] ?>
                                    · <?= $h(\App\Support\OperationLabels::workflow((string) ($ver['workflow'] ?? 'draft'))) ?>
                                    <form method="post" action="<?= $h($base . '/calques/' . (int) $ov['id'] . '/restaurer') ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <input type="hidden" name="version" value="<?= (int) $ver['version'] ?>">
                                        <button type="submit" class="ops-ws__link">Restaurer</button>
                                    </form>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                    <div id="ops-props" class="ops-ws__props" hidden>
                        <h3>Objet sélectionné</h3>
                        <form id="ops-props-form">
                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                            <label>Nom <input type="text" name="name"></label>
                            <label>Élément <input type="text" name="element_code" placeholder="INDIA"></label>
                            <label>Toutes les phases <input type="checkbox" name="all_phases" value="1"></label>
                            <button type="submit" class="ops-ws__btn">Enregistrer</button>
                            <button type="button" id="ops-props-delete" class="ops-ws__btn ops-ws__btn--danger">Retirer</button>
                        </form>
                    </div>
                </aside>
            </div>
            <script>
            window.OPS_PLANNING = <?= $planningJson ?? '{}' ?>;
            </script>
            <script src="<?= htmlspecialchars(asset_url('assets/js/ops-workspace-planning.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
        <?php elseif ($tab === 'intel' && $canIntel): ?>
            <section class="ops-ws__panel">
                <h2>Renseignement</h2>
                <p>Les dossiers SSE restent la source des identités. Reliez-les ici à un objectif de l’opération.</p>
                <p><a class="ops-ws__btn" href="<?= $h(url('atak/sse/workspace')) ?>">Ouvrir l’espace renseignement</a></p>
            </section>
        <?php elseif ($tab === 'targets' && $canIntel): ?>
            <section class="ops-ws__panel">
                <h2>Objectifs</h2>
                <form method="post" action="<?= $h($base . '/objectifs') ?>" class="ops-ws__create">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <label>Nom <input name="name" required placeholder="OBJ LION"></label>
                    <label>Type
                        <select name="target_type" class="bo-select">
                            <option value="">Non précisé</option>
                            <option value="person">Personne</option>
                            <option value="building">Bâtiment</option>
                            <option value="vehicle">Véhicule</option>
                            <option value="equipment">Matériel</option>
                            <option value="location">Lieu</option>
                        </select>
                    </label>
                    <label>Position <input name="mgrs" placeholder="Carré de référence"></label>
                    <label>Confiance
                        <select name="confidence" class="bo-select">
                            <option value="">Non renseignée</option>
                            <option value="high">Élevée</option>
                            <option value="medium">Moyenne</option>
                            <option value="low">Faible</option>
                        </select>
                    </label>
                    <label>Notes <textarea name="notes" rows="2"></textarea></label>
                    <button class="ops-ws__btn" type="submit">Ajouter</button>
                </form>
                <?php if ($targets === []): ?>
                <p class="ops-ws__empty">Aucun objectif pour l’instant.</p>
                <?php else: ?>
                <ul class="ops-ws__cards">
                    <?php foreach ($targets as $tgt): ?>
                    <?php
                    $tgtType = match ((string) ($tgt['target_type'] ?? '')) {
                        'person' => 'Personne',
                        'building' => 'Bâtiment',
                        'vehicle' => 'Véhicule',
                        'equipment' => 'Matériel',
                        'location' => 'Lieu',
                        default => '',
                    };
                    ?>
                    <li>
                        <strong><?= $h($tgt['target_code'] ?? '') ?> · <?= $h($tgt['name'] ?? '') ?></strong>
                        <p><?= $h($tgtType) ?> <?= $h($tgt['mgrs'] ?? '') ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
        <?php elseif ($tab === 'orders' && $canOrders): ?>
            <section class="ops-ws__panel">
                <h2>Ordres</h2>
                <p>Les calques ne sont pas collés dans l’ordre : ils sont référencés. Si un calque évolue après publication, l’ordre reste lié à la version utilisée.</p>
                <form method="post" action="<?= $h($base . '/ordres') ?>" class="ops-ws__create">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <label>Type
                        <select name="kind" class="bo-select">
                            <option value="opord">Ordre d’opération</option>
                            <option value="warnord">Ordre d’alerte</option>
                            <option value="frago">Ordre fragmentaire</option>
                        </select>
                    </label>
                    <label>Titre <input name="title" placeholder="Ordre Aegis-01"></label>
                    <label>1. Situation <textarea name="situation" rows="3"></textarea></label>
                    <label>2. Mission <textarea name="mission" rows="2"></textarea></label>
                    <label>3. Exécution <textarea name="execution" rows="3"></textarea></label>
                    <label>4. Soutien <textarea name="sustainment" rows="2"></textarea></label>
                    <label>5. Commandement et transmissions <textarea name="command" rows="2"></textarea></label>
                    <?php if ($overlays !== []): ?>
                    <fieldset>
                        <legend>Calques cités</legend>
                        <?php foreach ($overlays as $ov): ?>
                        <label class="ops-ws__check">
                            <input type="checkbox" name="overlay_ids[]" value="<?= (int) $ov['id'] ?>">
                            <?= $h($ov['name']) ?> (v<?= (int) ($ov['current_version'] ?? 1) ?> · <?= $h($ov['workflow_label'] ?? '') ?>)
                        </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php endif; ?>
                    <button class="ops-ws__btn" type="submit">Enregistrer l’ordre</button>
                </form>
                <?php foreach ($orders as $ord): ?>
                <article class="ops-ws__order">
                    <h3><?= $h($ord['kind_label'] ?? '') ?> · <?= $h($ord['title'] ?? '') ?></h3>
                    <p><?= $h($ord['workflow_label'] ?? '') ?></p>
                    <?php
                    $cited = $ord['overlay_ids'] ?? [];
                    foreach ($overlays as $ov) {
                        if (!in_array((int) $ov['id'], array_map('intval', $cited), true)) {
                            continue;
                        }
                        $used = (int) ($ov['current_version'] ?? 1);
                        $pub = (int) ($ov['published_version'] ?? 0);
                        if ($pub > 0 && $used < $pub) {
                            echo '<p class="ops-ws__warn">Annexe périmée : ' . $h($ov['name']) . ' a une version plus récente publiée sur la vue terrain.</p>';
                        }
                    }
                    ?>
                </article>
                <?php endforeach; ?>
            </section>
        <?php elseif ($tab === 'personnel' && $canPlan): ?>
            <section class="ops-ws__panel">
                <h2>Organisation de l’opération</h2>
                <p>Cette structure est temporaire. Elle ne modifie pas l’organigramme administratif de la communauté.</p>
                <ul class="ops-ws__cards">
                    <?php foreach ($elements as $el): ?>
                    <li><strong><?= $h($el['code'] ?? '') ?></strong> <?= $h($el['name'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
                <h3>Personnel</h3>
                <?php if ($members === []): ?>
                <p class="ops-ws__empty">Personne n’est encore affecté à cette opération.</p>
                <?php else: ?>
                <table class="ops-ws__table">
                    <thead><tr><th>Indicatif</th><th>Poste</th><th>Élément</th></tr></thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?= $h($m['callsign'] ?: ($m['display_name'] ?? '')) ?></td>
                        <td><?= $h($m['billet'] ?? '') ?></td>
                        <td><?= $h($m['element_code'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </section>
        <?php elseif ($tab === 'tasks'): ?>
            <section class="ops-ws__panel">
                <h2>Tâches</h2>
                <?php if ($canPlan): ?>
                <form method="post" action="<?= $h($base . '/taches') ?>" class="ops-ws__create">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <label>Libellé <input name="title" required placeholder="Assaut OBJ LION"></label>
                    <label>Élément <input name="assigned_element" placeholder="INDIA"></label>
                    <label>Soutien <input name="supporting_element" placeholder="JULIET"></label>
                    <label>Horaire <input name="h_offset" placeholder="H+35"></label>
                    <button class="ops-ws__btn" type="submit">Ajouter</button>
                </form>
                <?php endif; ?>
                <ul class="ops-ws__cards">
                    <?php foreach ($tasks as $task): ?>
                    <li>
                        <strong><?= $h($task['code'] ?? '') ?></strong>
                        <?= $h($task['title'] ?? '') ?>
                        <p><?= $h(\App\Support\OperationLabels::taskStatus((string) ($task['status'] ?? ''))) ?>
                           · <?= $h($task['assigned_element'] ?? '') ?>
                           · <?= $h($task['h_offset'] ?? '') ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php elseif ($tab === 'activity' && $canPlan): ?>
            <section class="ops-ws__panel">
                <h2>Journal</h2>
                <ol class="ops-ws__log">
                    <?php foreach ($activity as $row): ?>
                    <li>
                        <time><?= $h($row['created_at'] ?? '') ?></time>
                        <?= $h($row['callsign'] ?: ($row['display_name'] ?? 'Système')) ?>
                        — <?= $h($row['object_label'] ?? $row['action'] ?? '') ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php elseif ($tab === 'tactical'): ?>
            <section class="ops-ws__panel">
                <h2>Vue terrain</h2>
                <p>Cette vue ne montre que les calques publiés. Le travail encore en brouillon reste dans le plan.</p>
                <p><a class="ops-ws__btn" href="<?= $h(url('tactical/' . $uuid)) ?>">Ouvrir en plein écran</a></p>
            </section>
        <?php endif; ?>
    </div>
</div>
