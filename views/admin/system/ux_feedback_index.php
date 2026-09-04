<?php
declare(strict_types=1);

use App\Support\UxFeedbackAdminPresentation as Ux;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$schemaReady = !empty($uxFeedbackSchemaReady);
$aggregates = is_array($uxPageAggregates ?? null) ? $uxPageAggregates : [];
$recentRatings = is_array($uxRecentRatings ?? null) ? $uxRecentRatings : [];
$recentSurveys = is_array($uxRecentSurveys ?? null) ? $uxRecentSurveys : [];
$tenantFilter = isset($uxTenantFilter) ? (int) $uxTenantFilter : 0;
$tenantOptions = is_array($uxTenantOptions ?? null) ? $uxTenantOptions : [];
$typeFilter = (string) ($uxTypeFilter ?? '');
$satisfactionFilter = (string) ($uxSatisfactionFilter ?? '');
$screenFilter = (string) ($uxScreenFilter ?? '');
$screenOptions = is_array($uxScreenOptions ?? null) ? $uxScreenOptions : [];
$showRatings = ($uxShowRatings ?? true) !== false;
$showSurveys = ($uxShowSurveys ?? true) !== false;
$stats = is_array($uxStats ?? null) ? $uxStats : ['ratings' => 0, 'surveys' => 0, 'avg' => 0, 'weak' => 0];

$indexUrl = url('admin/system/retours-interface');

$queryUrl = static function (array $overrides = []) use ($tenantFilter, $typeFilter, $satisfactionFilter, $screenFilter, $indexUrl): string {
    $params = [
        'tenant' => array_key_exists('tenant', $overrides) ? $overrides['tenant'] : ($tenantFilter > 0 ? (string) $tenantFilter : ''),
        'type' => array_key_exists('type', $overrides) ? $overrides['type'] : $typeFilter,
        'satisfaction' => array_key_exists('satisfaction', $overrides) ? $overrides['satisfaction'] : $satisfactionFilter,
        'ecran' => array_key_exists('ecran', $overrides) ? $overrides['ecran'] : $screenFilter,
    ];
    $bits = [];
    foreach ($params as $key => $value) {
        $value = trim((string) $value);
        if ($value === '' || $value === '0') {
            continue;
        }
        $bits[] = rawurlencode($key) . '=' . rawurlencode($value);
    }

    return $bits === [] ? $indexUrl : $indexUrl . '?' . implode('&', $bits);
};

$hasActiveFilters = $tenantFilter > 0 || $typeFilter !== '' || $satisfactionFilter !== '' || $screenFilter !== '';
$selectedScreenTitle = '';
foreach ($screenOptions as $opt) {
    if ((string) ($opt['key'] ?? '') === $screenFilter) {
        $selectedScreenTitle = (string) ($opt['title'] ?? '');
        break;
    }
}
?>
<div class="pa">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                Retours sur l’interface
            </p>
            <div class="pa-hero__row">
                <div>
                    <p class="pa-hero__kicker">Administration du site</p>
                    <h1 class="pa-hero__title">Retours sur l’interface</h1>
                    <p class="pa-hero__lead">
                        Notes et questionnaires laissés depuis le bouton d’avis du back-office.
                        Repérez les écrans à améliorer, communauté par communauté.
                    </p>
                    <div class="pa-hero__actions">
                        <a class="pa-btn pa-btn--solid" href="#ux-filtres">Filtrer les retours</a>
                        <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin')) ?>">Retour au tableau de bord</a>
                    </div>
                </div>
                <aside class="pa-hero__meta">
                    <p class="pa-hero__meta-kicker">Aperçu</p>
                    <dl>
                        <div class="pa-hero__meta-row">
                            <dt>Avis rapides</dt>
                            <dd><?= (int) ($stats['ratings'] ?? 0) ?></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Questionnaires</dt>
                            <dd><?= (int) ($stats['surveys'] ?? 0) ?></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>Moyenne</dt>
                            <dd><?= $h((int) ($stats['ratings'] ?? 0) > 0 ? Ux::scoreLabel((float) ($stats['avg'] ?? 0)) : '—') ?></dd>
                        </div>
                        <div class="pa-hero__meta-row">
                            <dt>À améliorer</dt>
                            <dd><?= (int) ($stats['weak'] ?? 0) ?></dd>
                        </div>
                    </dl>
                </aside>
            </div>
        </header>

        <nav class="pa-jump" aria-label="Sections de la page">
            <a href="#ux-filtres">Filtres</a>
            <a href="#ux-synthese">Écrans notés</a>
            <a href="#ux-avis">Avis rapides</a>
            <a href="#ux-questionnaires">Questionnaires</a>
        </nav>

        <div class="pa-panel">
            <?php if (!$schemaReady): ?>
                <p class="pa-flash pa-flash--warn" role="status">
                    Les retours ne sont pas encore disponibles sur cet environnement.
                    Relancez la mise à jour du portail, puis revenez sur cette page.
                </p>
            <?php else: ?>

            <form id="ux-filtres" method="get" action="<?= $h($indexUrl) ?>" class="pa-filters scroll-mt-24" aria-labelledby="ux-filters-heading">
                <div class="pa-filters__head">
                    <p class="pa-card__kicker">Recherche</p>
                    <h2 id="ux-filters-heading" class="pa-card__title">Filtrer les retours</h2>
                    <p class="pa-card__help">Les listes ci-dessous suivent ces choix. Les moyennes par écran restent calculées sur l’ensemble des communautés.</p>
                </div>
                <div class="pa-filters__grid">
                    <div class="pa-field">
                        <label for="ux-tenant-filter">Communauté</label>
                        <select id="ux-tenant-filter" name="tenant">
                            <option value="">Toutes les communautés</option>
                            <?php foreach ($tenantOptions as $opt): ?>
                            <option value="<?= (int) ($opt['id'] ?? 0) ?>" <?= $tenantFilter === (int) ($opt['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= $h((string) ($opt['name'] ?? 'Communauté')) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="pa-field">
                        <label for="ux-type-filter">Type de retour</label>
                        <select id="ux-type-filter" name="type">
                            <option value="" <?= $typeFilter === '' ? 'selected' : '' ?>>Avis et questionnaires</option>
                            <option value="<?= $h(Ux::TYPE_RATINGS) ?>" <?= $typeFilter === Ux::TYPE_RATINGS ? 'selected' : '' ?>>Avis rapides seulement</option>
                            <option value="<?= $h(Ux::TYPE_SURVEYS) ?>" <?= $typeFilter === Ux::TYPE_SURVEYS ? 'selected' : '' ?>>Questionnaires seulement</option>
                        </select>
                    </div>
                    <div class="pa-field">
                        <label for="ux-sat-filter">Niveau de satisfaction</label>
                        <select id="ux-sat-filter" name="satisfaction">
                            <option value="" <?= $satisfactionFilter === '' ? 'selected' : '' ?>>Tous les niveaux</option>
                            <option value="<?= $h(Ux::SAT_WEAK) ?>" <?= $satisfactionFilter === Ux::SAT_WEAK ? 'selected' : '' ?>>À améliorer</option>
                            <option value="<?= $h(Ux::SAT_OK) ?>" <?= $satisfactionFilter === Ux::SAT_OK ? 'selected' : '' ?>>Correct</option>
                            <option value="<?= $h(Ux::SAT_GOOD) ?>" <?= $satisfactionFilter === Ux::SAT_GOOD ? 'selected' : '' ?>>Satisfaisant</option>
                        </select>
                    </div>
                    <div class="pa-field">
                        <label for="ux-screen-filter">Écran</label>
                        <select id="ux-screen-filter" name="ecran">
                            <option value="">Tous les écrans</option>
                            <?php foreach ($screenOptions as $opt): ?>
                            <option value="<?= $h((string) ($opt['key'] ?? '')) ?>" <?= $screenFilter === (string) ($opt['key'] ?? '') ? 'selected' : '' ?>>
                                <?= $h((string) ($opt['title'] ?? 'Écran')) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="pa-actions">
                    <button type="submit" class="pa-btn pa-btn--mint">Appliquer les filtres</button>
                    <?php if ($hasActiveFilters): ?>
                    <a class="pa-btn pa-btn--line" href="<?= $h($indexUrl) ?>">Réinitialiser</a>
                    <?php endif; ?>
                </div>
            </form>

            <dl class="pa-stats" aria-label="Synthèse des retours filtrés">
                <div class="pa-stat">
                    <dt>Avis rapides</dt>
                    <dd><?= (int) ($stats['ratings'] ?? 0) ?></dd>
                </div>
                <div class="pa-stat">
                    <dt>Questionnaires</dt>
                    <dd><?= (int) ($stats['surveys'] ?? 0) ?></dd>
                </div>
                <div class="pa-stat">
                    <dt>Moyenne des avis</dt>
                    <dd><?= $h((int) ($stats['ratings'] ?? 0) > 0 ? Ux::scoreLabel((float) ($stats['avg'] ?? 0)) : '—') ?></dd>
                </div>
                <div class="pa-stat">
                    <dt>Avis à améliorer</dt>
                    <dd><?= (int) ($stats['weak'] ?? 0) ?></dd>
                </div>
            </dl>

            <section id="ux-synthese" class="scroll-mt-24" aria-labelledby="ux-agg-heading">
                <div class="pa-map__head">
                    <div>
                        <p class="pa-map__kicker">Priorités</p>
                        <h2 id="ux-agg-heading" class="pa-map__title">Écrans les plus notés</h2>
                        <p class="pa-map__lead">Moyenne des notes rapides, toutes communautés confondues. Ouvrez les avis d’un écran pour lire le détail.</p>
                    </div>
                </div>
                <div class="pa-table-wrap">
                    <table class="pa-table pa-table--feedback">
                        <thead>
                            <tr>
                                <th>Écran</th>
                                <th>Statut</th>
                                <th class="pa-num">Avis</th>
                                <th class="pa-num">Moyenne</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($aggregates === []): ?>
                            <tr>
                                <td colspan="5" class="pa-empty">Aucune note n’a encore été déposée.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($aggregates as $row):
                                $pageKey = (string) ($row['page_key'] ?? '');
                                $title = (string) ($row['page_title'] ?? $pageKey);
                                if ($title === '') {
                                    $title = 'Écran';
                                }
                                $avg = (float) ($row['avg_rating'] ?? 0);
                                $status = Ux::satisfactionFromScore($avg);
                                $place = Ux::screenLocation((string) ($row['page_path'] ?? ''));
                                ?>
                            <tr>
                                <td>
                                    <p class="pa-name"><?= $h($title) ?></p>
                                    <?php if ($place !== ''): ?>
                                    <p class="pa-sub"><?= $h($place) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td><span class="pa-pill pa-pill--<?= $h($status['pill']) ?>"><?= $h($status['label']) ?></span></td>
                                <td class="pa-num"><?= (int) ($row['votes'] ?? 0) ?></td>
                                <td class="pa-num"><?= $h(Ux::scoreLabel($avg)) ?></td>
                                <td>
                                    <?php if ($pageKey !== ''): ?>
                                    <a class="pa-btn pa-btn--line" href="<?= $h($queryUrl(['ecran' => $pageKey, 'type' => ''])) ?>">Voir les avis</a>
                                    <?php else: ?>
                                    —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if ($showRatings): ?>
            <section id="ux-avis" class="pa-card" style="margin-top:1.35rem;" aria-labelledby="ux-ratings-heading">
                <p class="pa-card__kicker">Notes</p>
                <h2 id="ux-ratings-heading" class="pa-card__title">Avis rapides</h2>
                <p class="pa-card__help">
                    Derniers avis déposés
                    <?= $tenantFilter > 0 ? 'pour la communauté choisie' : 'toutes communautés' ?>
                    <?= $selectedScreenTitle !== '' ? ' · écran « ' . $h($selectedScreenTitle) . ' »' : '' ?>.
                </p>
                <div class="pa-table-wrap" style="margin-top:1rem;box-shadow:none;">
                    <table class="pa-table pa-table--feedback">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Communauté</th>
                                <th>Écran</th>
                                <th>Statut</th>
                                <th class="pa-num">Note</th>
                                <th>Auteur</th>
                                <th>Commentaire</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentRatings === []): ?>
                            <tr>
                                <td colspan="7" class="pa-empty">Aucun avis rapide pour ces filtres.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentRatings as $row):
                                $score = (int) ($row['rating'] ?? 0);
                                $status = Ux::satisfactionFromScore($score);
                                $comment = trim((string) ($row['comment'] ?? ''));
                                $pageKey = (string) ($row['page_key'] ?? '');
                                $title = (string) ($row['page_title'] ?? $pageKey);
                                if ($title === '') {
                                    $title = 'Écran';
                                }
                                ?>
                            <tr>
                                <td><?= $h(Ux::formatDateTime((string) ($row['updated_at'] ?? $row['created_at'] ?? ''))) ?></td>
                                <td><?= $h((string) ($row['tenant_name'] ?? '—')) ?></td>
                                <td>
                                    <p class="pa-name"><?= $h($title) ?></p>
                                </td>
                                <td><span class="pa-pill pa-pill--<?= $h($status['pill']) ?>"><?= $h($status['label']) ?></span></td>
                                <td class="pa-num"><?= $h(Ux::scoreLabel($score, 0)) ?></td>
                                <td><?= $h((string) ($row['author_name'] ?? '—')) ?></td>
                                <td><?= $h($comment !== '' ? $comment : '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($showSurveys): ?>
            <section id="ux-questionnaires" class="scroll-mt-24" style="margin-top:1.35rem;" aria-labelledby="ux-surveys-heading">
                <div class="pa-map__head">
                    <div>
                        <p class="pa-map__kicker">Détail</p>
                        <h2 id="ux-surveys-heading" class="pa-map__title">Questionnaires</h2>
                        <p class="pa-map__lead">
                            Facilité, clarté, présentation et utilité, avec les points signalés et le texte libre.
                        </p>
                    </div>
                </div>
                <?php if ($recentSurveys === []): ?>
                    <div class="pa-card">
                        <p class="pa-card__help">Aucun questionnaire pour ces filtres.</p>
                    </div>
                <?php else: ?>
                <div class="pa-survey-list">
                    <?php foreach ($recentSurveys as $row):
                        $score = Ux::surveyScore($row);
                        $status = Ux::satisfactionFromScore($score);
                        $issues = Ux::decodeIssues($row['issues_json'] ?? null);
                        $title = (string) ($row['page_title'] ?? $row['page_key'] ?? 'Écran');
                        if ($title === '') {
                            $title = 'Écran';
                        }
                        $improvement = trim((string) ($row['improvement_text'] ?? ''));
                        $recommend = $row['would_recommend'] ?? null;
                        ?>
                    <article class="pa-card pa-survey">
                        <header class="pa-survey__head">
                            <div>
                                <p class="pa-card__kicker"><?= $h((string) ($row['tenant_name'] ?? 'Communauté')) ?></p>
                                <h3 class="pa-card__title"><?= $h($title) ?></h3>
                                <p class="pa-card__help">
                                    <?= $h(Ux::formatDateTime((string) ($row['updated_at'] ?? $row['created_at'] ?? ''))) ?>
                                    · <?= $h((string) ($row['author_name'] ?? '—')) ?>
                                </p>
                            </div>
                            <div class="pa-survey__status">
                                <span class="pa-pill pa-pill--<?= $h($status['pill']) ?>"><?= $h($status['label']) ?></span>
                                <p class="pa-survey__avg"><?= $h(Ux::scoreLabel($score)) ?></p>
                            </div>
                        </header>
                        <dl class="pa-survey__axes">
                            <div>
                                <dt>Facilité</dt>
                                <dd><?= $h(Ux::scoreLabel((int) ($row['ease_rating'] ?? 0), 0)) ?></dd>
                            </div>
                            <div>
                                <dt>Clarté</dt>
                                <dd><?= $h(Ux::scoreLabel((int) ($row['clarity_rating'] ?? 0), 0)) ?></dd>
                            </div>
                            <div>
                                <dt>Présentation</dt>
                                <dd><?= $h(Ux::scoreLabel((int) ($row['design_rating'] ?? 0), 0)) ?></dd>
                            </div>
                            <div>
                                <dt>Utilité</dt>
                                <dd><?= $h(Ux::scoreLabel((int) ($row['usefulness_rating'] ?? 0), 0)) ?></dd>
                            </div>
                        </dl>
                        <?php if ($recommend !== null): ?>
                        <p class="pa-survey__reco">
                            Recommandation :
                            <strong><?= !empty($recommend) ? 'Oui' : 'Non' ?></strong>
                        </p>
                        <?php endif; ?>
                        <?php if ($issues !== []): ?>
                        <p class="pa-survey__issues-label">Points signalés</p>
                        <ul class="pa-chips">
                            <?php foreach ($issues as $label): ?>
                            <li class="pa-pill pa-pill--slate"><?= $h($label) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <?php if ($improvement !== ''): ?>
                        <p class="pa-survey__text"><?= nl2br($h($improvement)) ?></p>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>
