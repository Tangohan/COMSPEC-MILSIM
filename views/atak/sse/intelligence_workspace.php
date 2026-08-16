<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string, mixed> $ws */
$ws = is_array($ws ?? null) ? $ws : [];
$inbox = is_array($ws['inbox'] ?? null) ? $ws['inbox'] : [];
$timeline = is_array($ws['timeline'] ?? null) ? $ws['timeline'] : [];
$relations = is_array($ws['relations'] ?? null) ? $ws['relations'] : [];
$entities = is_array($ws['entities'] ?? null) ? $ws['entities'] : [];
$cases = is_array($ws['cases'] ?? null) ? $ws['cases'] : [];
$graph = is_array($ws['graph'] ?? null) ? $ws['graph'] : ['nodes' => [], 'edges' => []];
$folder = is_array($ws['folder'] ?? null) ? $ws['folder'] : null;
$context = is_array($ws['context'] ?? null) ? $ws['context'] : null;
$cycle = is_array($ws['cycle'] ?? null) ? $ws['cycle'] : [];
$analysis = is_array($ws['analysis'] ?? null) ? $ws['analysis'] : [];
$liaison = is_array($ws['liaison'] ?? null) ? $ws['liaison'] : [];
$counts = is_array($ws['counts'] ?? null) ? $ws['counts'] : [];
$lifecycleOptions = is_array($ws['lifecycle_options'] ?? null) ? $ws['lifecycle_options'] : [];
$selectedCaseId = (int) ($ws['selected_case_id'] ?? 0);
$canManage = (bool) ($canManage ?? false);
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$mode = $folder !== null ? 'case' : 'overview';

$toneClass = static function (string $tone): string {
    return match ($tone) {
        'danger' => 'iw-tone-danger',
        'warn' => 'iw-tone-warn',
        'ok' => 'iw-tone-ok',
        default => '',
    };
};
?>
<div class="iw-intel" data-sse-intel-workspace data-mode="<?= $h($mode) ?>"
     data-summary-url="<?= $h(url('api/sse/v1/workspace/summary')) ?>"
     data-inbox-url="<?= $h(url('api/sse/v1/inbox')) ?>"
     data-graph-url="<?= $h(url('api/sse/v1/graph')) ?>"
     data-search-url="<?= $h(url('api/sse/v1/search')) ?>"
     data-suggest-url="<?= $h(url('atak/sse/recherche/suggestions')) ?>"
     data-case-id="<?= (int) $selectedCaseId ?>"
     data-csrf="<?= $h($csrfToken) ?>">
    <header class="iw-intel-hero">
        <div>
            <div class="page-heading-overline">Exploitation // Workspace</div>
            <h1>Intelligence Workspace</h1>
            <p>
                Inbox, chemise dossier, chronologie et graphe — une seule surface d’exploitation.
                Les propositions automatiques restent distinctes des faits confirmés.
            </p>
        </div>
        <div class="iw-intel-hero-meta">
            <span><strong><?= (int) ($counts['inbox'] ?? 0) ?></strong> à traiter</span>
            <span><strong><?= (int) ($counts['requirements_open'] ?? 0) ?></strong> exigences</span>
            <span><strong><?= (int) ($counts['contradictions'] ?? 0) ?></strong> contradictions</span>
            <span><strong><?= (int) ($counts['timeline'] ?? 0) ?></strong> événements</span>
            <span><strong><?= (int) ($counts['graph_nodes'] ?? 0) ?></strong> nœuds</span>
            <button type="button" class="iw-btn iw-btn--solid" data-iw-palette-open title="Ctrl+K">Recherche rapide</button>
        </div>
    </header>

    <nav class="iw-intel-tabs" aria-label="Vues Workspace">
        <button type="button" class="is-active" data-iw-tab="inbox">Inbox</button>
        <button type="button" data-iw-tab="cycle">Cycle</button>
        <button type="button" data-iw-tab="analyse">Analyse</button>
        <button type="button" data-iw-tab="timeline">Chronologie</button>
        <button type="button" data-iw-tab="graph">Graphe</button>
        <button type="button" data-iw-tab="search">Recherche</button>
        <?php if ($folder !== null): ?>
            <button type="button" data-iw-tab="folder" class="is-active-case">Chemise</button>
        <?php endif; ?>
    </nav>

    <div class="iw-intel-grid">
        <section class="iw-intel-col" aria-label="Navigation">
            <?php require __DIR__ . '/workspace/_inbox.php'; ?>

            <header class="iw-intel-col-head" style="margin-top:1.25rem">
                <h2>Dossiers</h2>
                <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Tous</a>
            </header>
            <ul class="iw-intel-list iw-intel-list--compact">
                <?php foreach ($cases as $c): ?>
                    <?php if (!is_array($c)) {
                        continue;
                    } ?>
                    <li class="<?= ((int) ($c['id'] ?? 0) === $selectedCaseId) ? 'is-selected' : '' ?>">
                        <a href="<?= $h((string) ($c['href'] ?? '#')) ?>">
                            <span class="iw-intel-kicker"><?= $h((string) ($c['lifecycle_label'] ?? '')) ?></span>
                            <strong><?= $h(trim((string) ($c['reference_code'] ?? '') . ' ' . (string) ($c['title'] ?? ''))) ?></strong>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php if ($cases === []): ?>
                    <li class="iw-intel-empty">Aucun dossier visible.</li>
                <?php endif; ?>
            </ul>
        </section>

        <section class="iw-intel-col iw-intel-col--main" aria-label="Espace de travail">
            <div class="iw-panel" data-iw-panel="folder" <?= $folder === null ? 'hidden' : '' ?>>
                <?php if ($folder !== null) {
                    require __DIR__ . '/workspace/_case_folder.php';
                } ?>
            </div>
            <div class="iw-panel" data-iw-panel="timeline" <?= $folder !== null ? 'hidden' : '' ?>>
                <?php require __DIR__ . '/workspace/_timeline.php'; ?>
            </div>
            <div class="iw-panel" data-iw-panel="cycle" hidden>
                <?php require __DIR__ . '/workspace/_cycle.php'; ?>
            </div>
            <div class="iw-panel" data-iw-panel="analyse" hidden>
                <?php require __DIR__ . '/workspace/_analyse.php'; ?>
            </div>
            <div class="iw-panel" data-iw-panel="graph" hidden>
                <?php require __DIR__ . '/workspace/_graph.php'; ?>
            </div>
            <div class="iw-panel" data-iw-panel="search" hidden>
                <header class="iw-intel-col-head"><h2>Recherche universelle</h2></header>
                <form class="iw-univ-search" data-iw-univ-search>
                    <input type="search" name="q" placeholder="FALCON, UNKNOWN-0042, SITE ALPHA, référence…" autocomplete="off">
                    <button type="submit" class="iw-btn">Chercher</button>
                </form>
                <div class="iw-univ-results" data-iw-univ-results></div>
            </div>
            <div class="iw-panel" data-iw-panel="inbox" hidden>
                <p class="iw-intel-empty">Utilisez la colonne de gauche pour traiter l’inbox.</p>
            </div>
        </section>

        <aside class="iw-intel-col" aria-label="Contexte">
            <?php require __DIR__ . '/workspace/_context.php'; ?>
        </aside>
    </div>
</div>

<dialog class="iw-palette" id="iw-command-palette" aria-label="Palette de commandes">
    <div class="iw-palette-form">
        <input type="search" id="iw-palette-q" placeholder="Rechercher ou lancer une action…" autocomplete="off">
        <p class="iw-palette-hint">↑↓ naviguer · Entrée ouvrir · Esc fermer · Ctrl+K</p>
        <ul class="iw-palette-results" id="iw-palette-results" role="listbox"></ul>
        <ul class="iw-palette-actions" id="iw-palette-actions">
            <li><a href="<?= $h(url('atak/sse/dossiers/nouveau')) ?>"><kbd>D</kbd> Créer un dossier</a></li>
            <li><a href="<?= $h(url('atak/sse/interet/nouveau')) ?>"><kbd>P</kbd> Créer une piste</a></li>
            <li><a href="<?= $h(url('atak/sse/workspace')) ?>#analyse"><kbd>N</kbd> Analyse</a></li>
            <li><a href="<?= $h(url('atak/sse/workspace')) ?>#timeline"><kbd>T</kbd> Chronologie</a></li>
            <li><a href="<?= $h(url('atak/sse/workspace')) ?>#graph"><kbd>G</kbd> Graphe</a></li>
            <li><a href="<?= $h(url('atak/sse/rapports')) ?>"><kbd>R</kbd> Rapports</a></li>
            <li><a href="<?= $h(url('atak')) ?>"><kbd>A</kbd> Ouvrir ATAK</a></li>
            <li><a href="<?= $h(url('atak/sse/exploitation-numerique')) ?>"><kbd>L</kbd> Labo numérique</a></li>
        </ul>
    </div>
</dialog>

<script>
window.SSE_IW = {
  graph: <?= json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  csrf: <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>,
  canManage: <?= $canManage ? 'true' : 'false' ?>,
  caseId: <?= (int) $selectedCaseId ?>,
  searchUrl: <?= json_encode(url('api/sse/v1/search'), JSON_UNESCAPED_UNICODE) ?>,
  graphUrl: <?= json_encode(url('api/sse/v1/graph'), JSON_UNESCAPED_UNICODE) ?>,
  decideUrl: <?= json_encode(url('api/sse/v1/inbox/decide'), JSON_UNESCAPED_UNICODE) ?>,
  relationDeleteTpl: <?= json_encode(url('api/sse/v1/relations/__ID__/supprimer'), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= $h(asset_url('assets/js/sse-iw-graph.js')) ?>?v=202608161840" defer></script>
<script src="<?= $h(asset_url('assets/js/sse-command-palette.js')) ?>?v=202608161840" defer></script>
<script src="<?= $h(asset_url('assets/js/sse-intelligence-workspace.js')) ?>?v=202608161840" defer></script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
