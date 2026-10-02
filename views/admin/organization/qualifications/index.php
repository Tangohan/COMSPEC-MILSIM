<?php
declare(strict_types=1);

$definitions = is_array($definitions ?? null) ? $definitions : [];
$categories = is_array($categories ?? null) ? $categories : [];
$types = is_array($types ?? null) ? $types : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$activeCount = 0;
$archivedCount = 0;
$holdersTotal = 0;
$unheldCount = 0;
foreach ($definitions as $d) {
    if (!empty($d['archived_at'])) {
        $archivedCount++;
        continue;
    }
    $activeCount++;
    $holders = (int) ($d['holders_count'] ?? 0);
    $holdersTotal += $holders;
    if ($holders === 0) {
        $unheldCount++;
    }
}

$athKpis = [
    ['label' => 'Qualifications actives', 'value' => (string) $activeCount, 'pct' => $definitions !== [] ? round($activeCount / count($definitions) * 100) . '%' : '0%'],
    ['label' => 'Attributions en cours', 'value' => (string) $holdersTotal, 'pct' => $holdersTotal > 0 ? '100%' : '0%', 'note' => 'Toutes qualifications confondues'],
    ['label' => 'Jamais attribuées', 'value' => (string) $unheldCount, 'tone' => $unheldCount > 0 ? '#c27a1a' : '#0b8a5c', 'pct' => $activeCount > 0 ? round($unheldCount / $activeCount * 100) . '%' : '0%', 'note' => $unheldCount > 0 ? 'Personne ne les détient encore' : 'Toutes ont au moins un titulaire'],
    ['label' => 'Archivées', 'value' => (string) $archivedCount, 'tone' => '#6b7780', 'pct' => $definitions !== [] ? round($archivedCount / count($definitions) * 100) . '%' : '0%', 'note' => 'Historique conservé'],
];
?>
<div class="bo-qual">
    <div class="bo-qual__toolbar ath-rise" role="group" aria-label="Actions sur le référentiel">
        <a href="<?= $h(url('back-office/referentiels/qualifications/create')) ?>" class="ath-btn ath-btn--solid">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nouvelle qualification
        </a>
        <a href="<?= $h(url('back-office/referentiels/qualifications/attribuer')) ?>" class="ath-btn">Attribuer à un membre</a>
        <a href="<?= $h(url('back-office/referentiels/qualifications/emetteurs')) ?>" class="ath-btn">Organismes émetteurs</a>
    </div>

    <?php require base_path('views/partials/ath_kpis.php'); ?>

    <?php
    $athTableRows = [];
    $athTableRowHrefs = [];
    foreach ($definitions as $d) {
        $archived = !empty($d['archived_at']);
        $validite = !empty($d['is_permanent'])
            ? 'Permanente'
            : ((isset($d['default_validity_months']) && $d['default_validity_months'] !== null)
                ? ((int) $d['default_validity_months'] . ' mois')
                : '—');
        $athTableRows[] = [
            (string) ($d['code'] ?? ''),
            (string) ($d['name'] ?? ''),
            trim((string) ($d['category_name'] ?? '')) !== '' ? (string) $d['category_name'] : '—',
            trim((string) ($d['type_name'] ?? '')) !== '' ? (string) $d['type_name'] : '—',
            (string) (int) ($d['levels_count'] ?? 0),
            $validite,
            (string) (int) ($d['holders_count'] ?? 0),
            $archived ? 'Archivée' : 'Active',
        ];
        $athTableRowHrefs[] = url('back-office/referentiels/qualifications/' . (int) ($d['id'] ?? 0) . '/edit');
    }
    $athTableTitle = 'Qualifications';
    $athTableCount = count($definitions);
    $athTableCols = ['CODE|m', 'QUALIFICATION', 'CATÉGORIE', 'TYPE', 'NIVEAUX|r', 'VALIDITÉ', 'TITULAIRES|r', 'STATUT|b'];
    $athTableFilters = [];
    $athTableMinWidth = '860px';
    $athTableFilterName = 'q';
    $athTableFilterValue = '';
    $athTableFoot = $definitions === []
        ? 'Le référentiel est vide : créez une première qualification.'
        : 'Cliquez sur une ligne pour la modifier, gérer ses niveaux et ses titulaires.';
    $athTablePager = null;
    $athTableExportUrl = null;
    $athTableRowActions = [];
    require base_path('views/partials/ath_table.php');
    ?>

    <div class="bo-qual__grid">
        <section class="ath-card bo-qual__panel" aria-labelledby="bo-qual-cat-title">
            <header class="bo-qual__panel-head">
                <h2 id="bo-qual-cat-title">Catégories</h2>
                <span class="bo-qual__count"><?= count($categories) ?></span>
            </header>
            <p class="bo-qual__lead">Regroupent les qualifications par domaine (combat, santé, transmissions…).</p>
            <?php if ($categories === []): ?>
            <p class="bo-qual__empty">Aucune catégorie pour l’instant.</p>
            <?php else: ?>
            <ul class="bo-qual__chips">
                <?php foreach ($categories as $c): ?>
                <li><?= $h((string) ($c['name'] ?? '')) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/categories')) ?>" class="bo-qual__add">
                <?= \App\Core\Csrf::field() ?>
                <label class="sr-only" for="bo-qual-cat-name">Nom de la nouvelle catégorie</label>
                <input type="text" id="bo-qual-cat-name" name="name" required maxlength="120" placeholder="Nouvelle catégorie">
                <button type="submit" class="ath-btn">Ajouter</button>
            </form>
        </section>

        <section class="ath-card bo-qual__panel" aria-labelledby="bo-qual-type-title">
            <header class="bo-qual__panel-head">
                <h2 id="bo-qual-type-title">Types</h2>
                <span class="bo-qual__count"><?= count($types) ?></span>
            </header>
            <p class="bo-qual__lead">Nature de la qualification. Le code court apparaît dans les listes.</p>
            <?php if ($types === []): ?>
            <p class="bo-qual__empty">Aucun type pour l’instant.</p>
            <?php else: ?>
            <ul class="bo-qual__chips">
                <?php foreach ($types as $t): ?>
                <li><b><?= $h((string) ($t['code'] ?? '')) ?></b><?= $h((string) ($t['name'] ?? '')) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/types')) ?>" class="bo-qual__add bo-qual__add--type">
                <?= \App\Core\Csrf::field() ?>
                <label class="sr-only" for="bo-qual-type-name">Nom du nouveau type</label>
                <input type="text" id="bo-qual-type-name" name="name" required maxlength="120" placeholder="Nom (ex. Technique)">
                <label class="sr-only" for="bo-qual-type-code">Code court</label>
                <input type="text" id="bo-qual-type-code" name="code" required maxlength="16" placeholder="Code" class="bo-qual__code">
                <button type="submit" class="ath-btn">Ajouter</button>
            </form>
        </section>

        <section class="ath-card bo-qual__panel" aria-labelledby="bo-qual-tpl-title">
            <header class="bo-qual__panel-head">
                <h2 id="bo-qual-tpl-title">Brevets PDF</h2>
            </header>
            <p class="bo-qual__lead">Chaque attribution peut produire un brevet. Deux gabarits sont disponibles ; voici leur version vierge.</p>
            <ul class="bo-qual__tpls">
                <li>
                    <a href="<?= $h(asset_url('docs/qualification-certificate-templates/template_moderne_vierge.pdf')) ?>" target="_blank" rel="noopener noreferrer">
                        <strong>Moderne</strong>
                        <span>Bandeau couleur, code de vérification</span>
                    </a>
                </li>
                <li>
                    <a href="<?= $h(asset_url('docs/qualification-certificate-templates/template_classique_vierge.pdf')) ?>" target="_blank" rel="noopener noreferrer">
                        <strong>Classique</strong>
                        <span>Cadre ornementé, en-tête institutionnel</span>
                    </a>
                </li>
            </ul>
            <p class="bo-qual__note">
                Les attestations de formation ont leur propre
                <a href="<?= $h(url('formation/certificates/gabarit')) ?>">gabarit</a>.
            </p>
        </section>
    </div>

    <p class="bo-qual__hint">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
        « Expire bientôt » et « Expirée » sont calculés à partir des dates de chaque attribution. Archiver une qualification la retire des nouvelles attributions sans effacer l’historique.
    </p>
</div>
