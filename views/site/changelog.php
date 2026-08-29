<?php
declare(strict_types=1);
/** @var array<string, mixed> $catalog */
$catalog = is_array($catalog ?? null) ? $catalog : [];
$h = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$releases = is_array($catalog['releases'] ?? null) ? $catalog['releases'] : [];
$featured = is_array($catalog['featured'] ?? null) ? $catalog['featured'] : null;
$modules = is_array($catalog['modules'] ?? null) ? $catalog['modules'] : [];
$roadmap = is_array($catalog['roadmap'] ?? null) ? $catalog['roadmap'] : [];
$dispatches = is_array($dispatches ?? null) ? $dispatches : [];

$moduleFromGroups = static function (array $groups): array {
    if (in_array('atak', $groups, true)) {
        return ['label' => 'ATAK', 'class' => 'atak'];
    }
    if (in_array('intel', $groups, true) || in_array('sse', $groups, true)) {
        return ['label' => 'SSE', 'class' => 'intel'];
    }
    if (in_array('command', $groups, true) || in_array('c2', $groups, true)) {
        return ['label' => 'C2', 'class' => 'c2'];
    }
    if (in_array('personnel', $groups, true)) {
        return ['label' => 'PERSONNEL', 'class' => 'personnel'];
    }
    if (in_array('training', $groups, true)) {
        return ['label' => 'LMS', 'class' => 'platform'];
    }

    return ['label' => 'PLATFORM', 'class' => 'platform'];
};

$countUpdate = 0;
$countSpot = 0;
$countTech = 0;
foreach ($dispatches as $row) {
    if (!is_array($row)) {
        continue;
    }
    $k = (string) ($row['kind'] ?? '');
    if ($k === 'spotrep') {
        $countSpot++;
    } elseif ($k === 'techrep') {
        $countTech++;
    } else {
        $countUpdate++;
    }
}
$countAll = count($dispatches);
$prodVersion = (string) ($featured['version'] ?? '2026.08d');
$discoverHref = url('a-propos');
$opsJson = [];
foreach ($dispatches as $row) {
    if (!is_array($row)) {
        continue;
    }
    $groups = array_map('strval', $row['filter_groups'] ?? []);
    $mod = $moduleFromGroups($groups);
    $changes = [];
    foreach ($row['sections'] ?? [] as $section) {
        if (!is_array($section)) {
            continue;
        }
        foreach ($section['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $verb = strtoupper((string) ($item['verb'] ?? 'CHANGED'));
            if ($verb === 'TWEAKED') {
                $verb = 'CHANGED';
            }
            $changes[] = [$verb, (string) ($item['text'] ?? '')];
        }
    }
    $opsJson[] = [
        'id' => (string) ($row['number_pad'] ?? ''),
        'kind' => (string) ($row['kind'] ?? 'update'),
        'kind_label' => (string) ($row['kind_label'] ?? 'UPDATE'),
        'title' => (string) ($row['title'] ?? ''),
        'summary' => (string) ($row['activity'] ?? ''),
        'module' => $mod['label'],
        'module_class' => $mod['class'],
        'version' => (string) ($row['size'] ?? $prodVersion),
        'status' => 'DEPLOYED',
        'channel' => 'PROD',
        'date' => (string) ($row['date_label'] ?? ''),
        'author' => (string) ($row['reporter'] ?? 'Ops'),
        'from' => (string) ($row['from'] ?? ''),
        'to' => (string) ($row['to'] ?? ''),
        'href' => (string) ($row['href'] ?? '#'),
        'lead' => (string) ($row['activity'] ?? ''),
        'situation' => implode(' ', array_map('strval', $row['notes'] ?? [])),
        'impact' => (string) ($row['activity'] ?? ''),
        'action' => (string) (($row['notes'][0] ?? '') ?: 'Recharger le portail ou installer le pack indiqué.'),
        'changes' => $changes,
        'tags' => array_map('strtoupper', $groups),
        'search' => (string) ($row['search'] ?? ''),
    ];
}
?>
<div class="cl cl-ops" data-cl-root data-cl-ops>
    <div class="cl-ops-layout">
        <aside class="cl-ops-sidebar" aria-label="<?= $h(__('site.changelog')) ?>">
            <div class="cl-ops-side-label">Journal</div>
            <button type="button" class="cl-ops-side-link is-active" data-cl-ops-nav="updates">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.4 2.4L16.5 8"/></svg>
                <span>Updates</span>
                <span class="cl-ops-side-count"><?= $h((string) $countUpdate) ?></span>
            </button>
            <button type="button" class="cl-ops-side-link" data-cl-ops-nav="spotrep">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v5h5M9 13h6M9 17h4"/></svg>
                <span>SPOTREP</span>
                <span class="cl-ops-side-count"><?= $h((string) $countSpot) ?></span>
            </button>
            <button type="button" class="cl-ops-side-link" data-cl-ops-nav="techrep">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M9 3h6v4H9zM5 7h14v14H5z"/><path d="M9 12h6M9 16h6"/></svg>
                <span>TECHREP</span>
                <span class="cl-ops-side-count"><?= $h((string) $countTech) ?></span>
            </button>
            <button type="button" class="cl-ops-side-link" data-cl-ops-nav="roadmap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 19V5M4 19h16"/><path d="m7 15 4-4 3 2 5-6"/></svg>
                <span>Roadmap</span>
            </button>
            <div class="cl-ops-side-sep"></div>
            <div class="cl-ops-side-label">Produit</div>
            <button type="button" class="cl-ops-side-link" data-cl-ops-nav="modules">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                <span>Modules</span>
            </button>
            <button type="button" class="cl-ops-side-link" data-cl-ops-nav="releases">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="8"/></svg>
                <span>Versions</span>
                <span class="cl-ops-side-count"><?= $h((string) count($releases)) ?></span>
            </button>
            <div class="cl-ops-side-sep"></div>
            <div class="cl-ops-channel">
                <div class="cl-ops-channel-top"><strong>PROD</strong><span class="cl-ops-live-dot" aria-hidden="true"></span></div>
                <p>Canal stable<br><span class="cl-ops-mono">athena-<?= $h($prodVersion) ?></span></p>
            </div>
        </aside>

        <div class="cl-ops-main">
            <section id="cl-ops-list" class="cl-ops-panel is-active" data-cl-ops-panel="list">
                <div class="cl-ops-page-head">
                    <div>
                        <div class="cl-ops-crumb cl-ops-mono">athena / product / updates</div>
                        <h1 class="cl-ops-page-title"><?= $h(__('site.changelog_title')) ?></h1>
                        <p class="cl-ops-page-desc"><?= $h(__('site.changelog_lead')) ?></p>
                    </div>
                    <div class="cl-ops-page-actions">
                        <a href="<?= $h($discoverHref) ?>" class="cl-cta cl-cta--ghost"><?= $h(__('site.cl_cta_discover')) ?></a>
                        <a href="#cl-ops-table" class="cl-cta cl-cta--primary"><?= $h(__('site.cl_cta_latest')) ?></a>
                    </div>
                </div>

                <div class="cl-ops-metric-strip" role="list">
                    <div class="cl-ops-metric" role="listitem">
                        <div class="cl-ops-metric-k">Updates publiées</div>
                        <div class="cl-ops-metric-v"><?= $h((string) $countAll) ?> <small>bulletins</small></div>
                    </div>
                    <div class="cl-ops-metric" role="listitem">
                        <div class="cl-ops-metric-k">Version production</div>
                        <div class="cl-ops-metric-v cl-ops-mono"><?= $h($prodVersion) ?> <small class="ok">STABLE</small></div>
                    </div>
                    <div class="cl-ops-metric" role="listitem">
                        <div class="cl-ops-metric-k">SPOTREP / TECHREP</div>
                        <div class="cl-ops-metric-v"><?= $h((string) $countSpot) ?> / <?= $h((string) $countTech) ?> <small>vagues</small></div>
                    </div>
                    <div class="cl-ops-metric" role="listitem">
                        <div class="cl-ops-metric-k">Modules suivis</div>
                        <div class="cl-ops-metric-v"><?= $h((string) max(1, count($modules))) ?> <small>actifs</small></div>
                    </div>
                </div>

                <nav class="cl-ops-tabs" aria-label="Filtres journal">
                    <button type="button" class="cl-ops-tab is-active" data-cl-ops-tab="all">Updates <span class="count"><?= $h((string) $countAll) ?></span></button>
                    <button type="button" class="cl-ops-tab" data-cl-ops-tab="update">Releases <span class="count"><?= $h((string) $countUpdate) ?></span></button>
                    <button type="button" class="cl-ops-tab" data-cl-ops-tab="spotrep">SPOTREP <span class="count"><?= $h((string) $countSpot) ?></span></button>
                    <button type="button" class="cl-ops-tab" data-cl-ops-tab="techrep">TECHREP <span class="count"><?= $h((string) $countTech) ?></span></button>
                </nav>

                <div class="cl-ops-filterbar" id="cl-ops-table">
                    <label class="cl-ops-search-field">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                        <span class="sr-only"><?= $h(__('site.cl_search_label')) ?></span>
                        <input id="cl-ops-search" type="search" data-cl-ops-search placeholder="<?= $h(__('site.cl_search_ph')) ?>" autocomplete="off">
                    </label>
                    <select class="cl-ops-select" data-cl-ops-module aria-label="Module">
                        <option value="all">Tous modules</option>
                        <option value="ATAK">ATAK</option>
                        <option value="SSE">SSE</option>
                        <option value="C2">C2</option>
                        <option value="PLATFORM">PLATFORM</option>
                        <option value="PERSONNEL">PERSONNEL</option>
                        <option value="LMS">LMS</option>
                    </select>
                    <select class="cl-ops-select" data-cl-ops-kind aria-label="Type">
                        <option value="all">Tous types</option>
                        <option value="update">UPDATE</option>
                        <option value="spotrep">SPOTREP</option>
                        <option value="techrep">TECHREP</option>
                    </select>
                    <span class="cl-ops-filter-result" data-cl-ops-result><?= $h((string) $countAll) ?> résultats</span>
                </div>

                <div class="cl-ops-table-wrap">
                    <table class="cl-ops-table">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th class="col-title">Objet</th>
                                <th class="col-module">Module</th>
                                <th class="col-version">Version</th>
                                <th class="col-status">État</th>
                                <th class="col-channel">Canal</th>
                                <th class="col-date">Date</th>
                                <th class="col-author">Auteur</th>
                            </tr>
                        </thead>
                        <tbody data-cl-ops-body>
                            <?php foreach ($opsJson as $u): ?>
                                <?php
                                $initials = mb_strtoupper(mb_substr((string) $u['author'], 0, 2));
                                ?>
                                <tr
                                    class="cl-ops-row"
                                    tabindex="0"
                                    data-cl-ops-row
                                    data-id="<?= $h($u['id']) ?>"
                                    data-kind="<?= $h($u['kind']) ?>"
                                    data-module="<?= $h($u['module']) ?>"
                                    data-search="<?= $h($u['search'] . ' ' . $u['title'] . ' ' . $u['id'] . ' ' . $u['module']) ?>"
                                >
                                    <td class="col-id"><span class="cl-ops-id">#<?= $h($u['id']) ?></span></td>
                                    <td class="col-title">
                                        <div class="cl-ops-title">
                                            <strong><?= $h($u['title']) ?></strong>
                                            <span><?= $h($u['summary']) ?></span>
                                        </div>
                                    </td>
                                    <td class="col-module"><span class="cl-ops-mod cl-ops-mod--<?= $h($u['module_class']) ?>"><?= $h($u['module']) ?></span></td>
                                    <td class="col-version"><span class="cl-ops-mono"><?= $h($u['version']) ?></span></td>
                                    <td class="col-status"><span class="cl-ops-status cl-ops-status--deployed">● Déployé</span></td>
                                    <td class="col-channel"><span class="cl-ops-chan"><?= $h($u['channel']) ?></span></td>
                                    <td class="col-date"><span class="cl-ops-mono"><?= $h($u['date']) ?></span></td>
                                    <td class="col-author">
                                        <div class="cl-ops-author">
                                            <span class="cl-ops-avatar"><?= $h($initials) ?></span>
                                            <span><?= $h($u['author']) ?></span>
                                            <svg class="cl-ops-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="cl-ops-empty" data-cl-ops-empty hidden><?= $h(__('site.cl_empty')) ?></p>
                <div class="cl-ops-pagination">
                    <span>Affichage du journal d’opérations produit</span>
                    <span class="cl-ops-mono"><?= $h((string) $countAll) ?> entrées</span>
                </div>
            </section>

            <section id="cl-ops-detail" class="cl-ops-panel" data-cl-ops-panel="detail" hidden>
                <div class="cl-ops-detail-shell">
                    <button type="button" class="cl-ops-back" data-cl-ops-back>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        Retour au registre
                    </button>
                    <div class="cl-ops-detail-toolbar">
                        <div class="cl-ops-detail-tabs">
                            <span class="cl-ops-detail-tab is-active" data-cl-ops-kind-label>UPDREP</span>
                        </div>
                        <div class="cl-ops-detail-actions">
                            <a class="cl-ops-detail-action" data-cl-ops-full href="#">Fiche complète</a>
                        </div>
                    </div>
                    <article class="cl-ops-report">
                        <div class="cl-ops-report-topline"></div>
                        <header class="cl-ops-report-head">
                            <div class="cl-ops-report-class">
                                <span>ATHENA // PRODUCT OPERATIONS // <span data-cl-ops-r-kind>UPDREP</span></span>
                                <span>DIFFUSION : COMMUNAUTÉS AUTORISÉES</span>
                            </div>
                            <div class="cl-ops-report-heading">
                                <div>
                                    <div class="cl-ops-report-id" data-cl-ops-r-id></div>
                                    <h2 data-cl-ops-r-title></h2>
                                    <p class="cl-ops-report-lead" data-cl-ops-r-lead></p>
                                </div>
                                <div class="cl-ops-report-seal" aria-hidden="true">
                                    <div class="cl-ops-seal-content"><strong>ATHENA</strong><small>PRODUCT OPS<br>CHANGE CONTROL</small></div>
                                </div>
                            </div>
                        </header>
                        <div class="cl-ops-report-meta">
                            <div class="cl-ops-meta-cell"><span class="cl-ops-meta-k">From</span><span class="cl-ops-meta-v" data-cl-ops-r-from></span></div>
                            <div class="cl-ops-meta-cell"><span class="cl-ops-meta-k">To</span><span class="cl-ops-meta-v" data-cl-ops-r-to></span></div>
                            <div class="cl-ops-meta-cell"><span class="cl-ops-meta-k">Release</span><span class="cl-ops-meta-v" data-cl-ops-r-version></span></div>
                            <div class="cl-ops-meta-cell"><span class="cl-ops-meta-k">Status</span><span class="cl-ops-meta-v" data-cl-ops-r-status></span></div>
                        </div>
                        <div class="cl-ops-report-body">
                            <div class="cl-ops-report-main">
                                <section class="cl-ops-report-section">
                                    <div class="cl-ops-report-num">01 // SITUATION</div>
                                    <h3>Situation / constat</h3>
                                    <p data-cl-ops-r-situation></p>
                                </section>
                                <section class="cl-ops-report-section">
                                    <div class="cl-ops-report-num">02 // MISSION EFFECT</div>
                                    <h3>Effet utilisateur</h3>
                                    <p data-cl-ops-r-impact></p>
                                    <div class="cl-ops-action-box">
                                        <strong>Action requise</strong>
                                        <p data-cl-ops-r-action></p>
                                    </div>
                                </section>
                                <section class="cl-ops-report-section">
                                    <div class="cl-ops-report-num">03 // CHANGE SUMMARY</div>
                                    <h3>Changements appliqués</h3>
                                    <ul class="cl-ops-report-list" data-cl-ops-r-changes></ul>
                                </section>
                            </div>
                            <aside class="cl-ops-report-aside">
                                <div class="cl-ops-aside-block">
                                    <p class="cl-ops-aside-title">Classification produit</p>
                                    <div class="cl-ops-aside-pills" data-cl-ops-r-tags></div>
                                </div>
                                <div class="cl-ops-aside-block">
                                    <p class="cl-ops-aside-title">Responsable</p>
                                    <div class="cl-ops-author"><span class="cl-ops-avatar" data-cl-ops-r-avatar>AO</span><span data-cl-ops-r-author class="cl-ops-mono"></span></div>
                                </div>
                            </aside>
                        </div>
                        <footer class="cl-ops-report-footer">
                            <span>ATHENA // PRODUCT OPS // AUTO-GENERATED RECORD</span>
                            <span data-cl-ops-r-footer></span>
                        </footer>
                    </article>
                </div>
            </section>

            <section id="cl-ops-modules" class="cl-ops-panel" data-cl-ops-panel="modules" hidden>
                <div class="cl-ops-page-head">
                    <div>
                        <div class="cl-ops-crumb cl-ops-mono">athena / product / modules</div>
                        <h2 class="cl-ops-page-title"><?= $h(__('site.cl_plat_title')) ?></h2>
                        <p class="cl-ops-page-desc"><?= $h(__('site.cl_eco_lead')) ?></p>
                    </div>
                </div>
                <div class="cl-ops-cards">
                    <?php foreach ($modules as $module): ?>
                        <article class="cl-ops-card">
                            <h3><?= $h($module['name'] ?? '') ?></h3>
                            <p><?= $h($module['body'] ?? '') ?></p>
                            <p class="cl-ops-card-meta"><?= $h($module['status'] ?? '') ?> · <?= $h($module['update'] ?? '') ?></p>
                            <a href="<?= $h($module['href'] ?? '#') ?>"><?= $h(__('site.cl_mod_discover')) ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="cl-ops-releases" class="cl-ops-panel" data-cl-ops-panel="releases" hidden>
                <div class="cl-ops-page-head">
                    <div>
                        <div class="cl-ops-crumb cl-ops-mono">athena / product / versions</div>
                        <h2 class="cl-ops-page-title"><?= $h(__('site.cl_hist_title')) ?></h2>
                        <p class="cl-ops-page-desc"><?= $h(__('site.cl_feat_kicker')) ?></p>
                    </div>
                </div>
                <div class="cl-ops-cards">
                    <?php foreach ($releases as $release): ?>
                        <?php if (!is_array($release)) {
                            continue;
                        } ?>
                        <article class="cl-ops-card" id="<?= $h((string) ($release['id'] ?? '')) ?>">
                            <p class="cl-ops-card-meta cl-ops-mono"><?= $h(($release['version_label'] ?? '') . ' ' . ($release['version'] ?? '')) ?></p>
                            <h3><?= $h($release['title'] ?? '') ?></h3>
                            <p><?= $h($release['summary'] ?? '') ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="cl-ops-roadmap" class="cl-ops-panel" data-cl-ops-panel="roadmap" hidden>
                <div class="cl-ops-page-head">
                    <div>
                        <div class="cl-ops-crumb cl-ops-mono">athena / product / roadmap</div>
                        <h2 class="cl-ops-page-title"><?= $h(__('site.cl_road_title')) ?></h2>
                    </div>
                </div>
                <div class="cl-ops-cards">
                    <?php foreach ($roadmap as $item): ?>
                        <article class="cl-ops-card">
                            <strong><?= $h($item['when'] ?? '') ?></strong>
                            <p><?= $h($item['body'] ?? '') ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>

    <script type="application/json" id="cl-ops-data"><?= json_encode($opsJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
</div>
