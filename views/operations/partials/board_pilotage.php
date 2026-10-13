<?php
declare(strict_types=1);

/**
 * Outils liés au mur (bas du tableau opérationnel) : événements, articles, ATAK et autres pages de pilotage.
 *
 * @var array<string, mixed> $boardCockpit
 */
$cockpit = is_array($boardCockpit ?? null) ? $boardCockpit : [];
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$canEvents = !empty($cockpit['can_events']);
$canArticles = !empty($cockpit['can_articles']);
$canAtak = !empty($cockpit['can_atak']);
$canEffectifs = !empty($cockpit['can_effectifs']);
$canMissions = !empty($cockpit['can_missions']);
$canAlerts = !empty($cockpit['can_alerts']);
$canOpsCenter = !empty($cockpit['can_ops_center']);
$canSlides = !empty($cockpit['can_slides']);
$slidesTotal = (int) ($cockpit['slides_total'] ?? 0);
$slidesActive = (int) ($cockpit['slides_active'] ?? 0);
$events = is_array($cockpit['events'] ?? null) ? $cockpit['events'] : [];
$articles = is_array($cockpit['articles'] ?? null) ? $cockpit['articles'] : [];
$missions = is_array($cockpit['missions'] ?? null) ? $cockpit['missions'] : [];
$alertsCount = (int) ($cockpit['alerts_count'] ?? 0);

$formatWhen = static function (mixed $raw): string {
    $s = trim((string) ($raw ?? ''));
    if ($s === '') {
        return '';
    }
    $ts = strtotime($s);

    return $ts ? date('d/m/Y H:i', $ts) : $s;
};

$showCockpit = $canEvents || $canArticles || $canAtak || $canSlides || $canEffectifs || $canMissions || $canAlerts || $canOpsCenter;
?>
<?php if ($showCockpit): ?>
<section id="ops-zone-pilotage" class="bo-tbo-section bo-tbo-tools" aria-labelledby="ops-zone-pilotage-title">
    <header class="bo-tbo-section__head">
        <div>
            <h2 id="ops-zone-pilotage-title">Outils liés</h2>
            <p>Les autres contenus que voient les membres ont leur propre page. Voici un aperçu et un accès direct.</p>
        </div>
    </header>
    <div class="bo-tbo-tools__grid">
        <?php if ($canEvents): ?>
        <article class="bo-tbo-panel bo-tbo-tool">
            <header>
                <h3>Événements</h3>
                <span class="bo-tbo-group__count"><?= count($events) ?> à venir</span>
            </header>
            <p class="bo-tbo-muted">Les créneaux auxquels les membres s’inscrivent (opérations, entraînements).</p>
            <?php if ($events === []): ?>
                <p class="bo-tbo-tool__empty">Aucun événement à venir.</p>
            <?php else: ?>
                <ul class="bo-tbo-tool__list">
                    <?php foreach ($events as $event):
                        $eid = (int) ($event['id'] ?? 0);
                        if ($eid < 1) {
                            continue;
                        }
                        $when = $formatWhen($event['starts_at'] ?? null);
                        ?>
                    <li>
                        <a class="bo-tbo-tool__item" href="<?= $h(url('back-office/events/' . $eid)) ?>">
                            <strong><?= $h((string) ($event['title'] ?? 'Événement')) ?></strong>
                            <?php if ($when !== ''): ?><em><?= $h($when) ?></em><?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <footer>
                <a href="<?= $h(url('back-office/events')) ?>" class="bo-tbo-btn bo-tbo-btn--sm">Ouvrir le registre</a>
                <a href="<?= $h(url('back-office/events') . '?vue=calendrier') ?>" class="bo-tbo-btn bo-tbo-btn--ghost bo-tbo-btn--sm">Agenda</a>
            </footer>
        </article>
        <?php endif; ?>

        <?php if ($canArticles): ?>
        <article class="bo-tbo-panel bo-tbo-tool">
            <header>
                <h3>Articles</h3>
                <span class="bo-tbo-group__count"><?= count($articles) ?> récent<?= count($articles) > 1 ? 's' : '' ?></span>
            </header>
            <p class="bo-tbo-muted">Les textes longs publiés pour les membres (nouvelles, comptes rendus, règlements).</p>
            <?php if (empty($cockpit['articles_ready'])): ?>
                <p class="bo-tbo-tool__empty">Les articles ne sont pas encore installés sur ce serveur.</p>
            <?php elseif ($articles === []): ?>
                <p class="bo-tbo-tool__empty">Aucun article pour le moment.</p>
            <?php else: ?>
                <ul class="bo-tbo-tool__list">
                    <?php foreach ($articles as $row):
                        $aid = (int) ($row['id'] ?? 0);
                        if ($aid < 1) {
                            continue;
                        }
                        $statusLabel = (string) ($row['status'] ?? 'draft') === 'published' ? 'Publié' : 'Brouillon';
                        ?>
                    <li>
                        <a class="bo-tbo-tool__item" href="<?= $h(url('back-office/articles/' . $aid . '/edit')) ?>">
                            <strong><?= $h((string) ($row['title'] ?? 'Article')) ?></strong>
                            <em><?= $h($statusLabel) ?><?= !empty($row['pinned']) ? ' · Épinglé' : '' ?></em>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <footer>
                <a href="<?= $h(url('back-office/articles')) ?>" class="bo-tbo-btn bo-tbo-btn--sm">Ouvrir les articles</a>
                <a href="<?= $h(url('back-office/articles/create')) ?>" class="bo-tbo-btn bo-tbo-btn--ghost bo-tbo-btn--sm">Nouvel article</a>
            </footer>
        </article>
        <?php endif; ?>

        <?php if ($canSlides): ?>
        <article class="bo-tbo-panel bo-tbo-tool" id="tbo-diapos">
            <header>
                <h3>Diapositives de briefing</h3>
                <span class="bo-tbo-group__count">En jeu : <?= $slidesActive ?></span>
            </header>
            <p class="bo-tbo-muted">Les images montrées pendant le briefing. Celles mises en jeu s’affichent dans Arma et sur les téléphones ATAK.</p>
            <?php if ($slidesTotal === 0): ?>
                <p class="bo-tbo-tool__empty">Aucune diapositive pour l’instant. Ajoutez vos images ou un lien Google Slides.</p>
            <?php else: ?>
                <ul class="bo-tbo-tool__list">
                    <li><span class="bo-tbo-tool__item"><strong>Diapositives préparées</strong><em><?= $slidesTotal ?></em></span></li>
                    <li><span class="bo-tbo-tool__item"><strong>Visibles en jeu</strong><em><?= $slidesActive ?></em></span></li>
                </ul>
            <?php endif; ?>
            <footer>
                <a href="<?= $h(url('back-office/atak/briefing-slides')) ?>" class="bo-tbo-btn bo-tbo-btn--sm">Ouvrir les diapositives</a>
            </footer>
        </article>
        <?php endif; ?>

        <?php if ($canAtak): ?>
        <article class="bo-tbo-panel bo-tbo-tool">
            <header>
                <h3>ATAK</h3>
                <span class="bo-tbo-group__count">Terrain</span>
            </header>
            <p class="bo-tbo-muted">Ce qui se passe en jeu : positions, liaisons et comptes rendus des joueurs.</p>
            <ul class="bo-tbo-tool__list">
                <li><a class="bo-tbo-tool__item" href="<?= $h(url('back-office/atak')) ?>"><strong>Poste de situation</strong><em>Vue d’ensemble en direct</em></a></li>
                <li><a class="bo-tbo-tool__item" href="<?= $h(url('tacmap')) ?>"><strong>Carte tactique</strong><em>Positions et marqueurs</em></a></li>
                <li><a class="bo-tbo-tool__item" href="<?= $h(url('back-office/atak/operateurs')) ?>"><strong>Sessions et liaisons</strong><em>Qui est connecté</em></a></li>
                <li><a class="bo-tbo-tool__item" href="<?= $h(url('back-office/atak/cycle-mission')) ?>"><strong>Cycle de mission</strong><em>Préparation et suivi</em></a></li>
                <li><a class="bo-tbo-tool__item" href="<?= $h(url('back-office/atak/comptes-rendus')) ?>"><strong>Comptes rendus</strong><em>Remontés depuis le jeu</em></a></li>
            </ul>
            <footer>
                <a href="<?= $h(url('back-office/atak')) ?>" class="bo-tbo-btn bo-tbo-btn--sm">Ouvrir le poste ATAK</a>
            </footer>
        </article>
        <?php endif; ?>
    </div>

    <?php if ($canMissions || $canAlerts || $canEffectifs || $canOpsCenter): ?>
    <nav class="bo-tbo-levers" aria-label="Autres pages de pilotage">
        <span class="bo-tbo-levers__label">Aussi :</span>
        <?php if ($canMissions): ?>
            <a class="bo-tbo-lever" href="<?= $h(url('back-office/missions')) ?>">Portail missions<?php if ($missions !== []): ?> <strong><?= count($missions) ?></strong><?php endif; ?></a>
            <?php
            $firstMissionId = $missions !== [] ? (int) ($missions[0]['id'] ?? 0) : 0;
            if ($firstMissionId > 0): ?>
                <a class="bo-tbo-lever" href="<?= $h(url('back-office/cooperation/missions/' . $firstMissionId)) ?>">Coopération en cours</a>
            <?php else: ?>
                <a class="bo-tbo-lever" href="<?= $h(url('back-office/cooperation')) ?>">Coopération</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($canAlerts): ?>
            <a class="bo-tbo-lever<?= $alertsCount > 0 ? ' is-alert' : '' ?>" href="<?= $h(url('back-office/alerts')) ?>">Annonces<?php if ($alertsCount > 0): ?> <strong><?= $alertsCount ?></strong><?php endif; ?></a>
        <?php endif; ?>
        <?php if ($canEffectifs): ?>
            <a class="bo-tbo-lever" href="<?= $h(effectifs_workspace_url()) ?>">Effectifs</a>
        <?php endif; ?>
        <?php if ($canOpsCenter): ?>
            <a class="bo-tbo-lever" href="<?= $h(url('back-office/centre-operations')) ?>">Centre d’opérations</a>
            <a class="bo-tbo-lever" href="<?= $h(url('back-office/courrier/traceabilite')) ?>">Courrier</a>
            <a class="bo-tbo-lever" href="<?= $h(url('back-office/planification')) ?>">Planification</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</section>
<?php endif; ?>
