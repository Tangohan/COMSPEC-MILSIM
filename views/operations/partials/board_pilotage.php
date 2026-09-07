<?php
declare(strict_types=1);

/**
 * Bandeau de pilotage : événements, articles, ATAK et leviers déjà présents.
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

$showCockpit = $canEvents || $canArticles || $canAtak || $canEffectifs || $canMissions || $canAlerts || $canOpsCenter;
?>
<section id="ops-zone-pilotage" class="ops-board__cockpit" aria-labelledby="ops-zone-pilotage-title">
    <header class="ops-board__cockpit-head">
        <p class="ops-board__zone-kicker">Poste de commandement</p>
        <h2 id="ops-zone-pilotage-title">Piloter depuis cet écran</h2>
        <p><?= $showCockpit
            ? 'Ouvrez les événements, les articles et le poste ATAK sans quitter le tableau. Le mur de consignes reste plus bas.'
            : 'Le mur de consignes se trouve plus bas. Les événements, articles et le poste ATAK s’affichent ici lorsque votre rôle le permet.' ?></p>
    </header>

    <?php if ($showCockpit): ?>
    <div class="ops-board__cockpit-grid">
        <?php if ($canEvents): ?>
        <article class="ops-board__cockpit-card">
            <header>
                <h3>Événements</h3>
                <span><?= count($events) ?> à venir</span>
            </header>
            <?php if ($events === []): ?>
                <p class="ops-board__cockpit-empty">Aucun événement à venir. Créez un créneau dans le registre pour le suivre ici.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($events as $event):
                        $eid = (int) ($event['id'] ?? 0);
                        if ($eid < 1) {
                            continue;
                        }
                        $when = $formatWhen($event['starts_at'] ?? null);
                        ?>
                    <li>
                        <a href="<?= $h(url('back-office/events/' . $eid)) ?>">
                            <strong><?= $h((string) ($event['title'] ?? 'Événement')) ?></strong>
                            <?php if ($when !== ''): ?><em><?= $h($when) ?></em><?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <footer>
                <a href="<?= $h(url('back-office/events')) ?>" class="ath-btn ath-btn--solid">Ouvrir le registre</a>
                <a href="<?= $h(url('back-office/events') . '?vue=calendrier') ?>" class="ath-btn">Agenda</a>
            </footer>
        </article>
        <?php endif; ?>

        <?php if ($canArticles): ?>
        <article class="ops-board__cockpit-card">
            <header>
                <h3>Articles</h3>
                <span><?= count($articles) ?> récent<?= count($articles) > 1 ? 's' : '' ?></span>
            </header>
            <?php if (empty($cockpit['articles_ready'])): ?>
                <p class="ops-board__cockpit-empty">Les articles ne sont pas encore disponibles sur cet environnement.</p>
            <?php elseif ($articles === []): ?>
                <p class="ops-board__cockpit-empty">Aucun article pour le moment. Rédigez-en un pour le publier auprès des membres.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($articles as $row):
                        $aid = (int) ($row['id'] ?? 0);
                        if ($aid < 1) {
                            continue;
                        }
                        $status = (string) ($row['status'] ?? 'draft');
                        $statusLabel = $status === 'published' ? 'Publié' : 'Brouillon';
                        ?>
                    <li>
                        <a href="<?= $h(url('back-office/articles/' . $aid . '/edit')) ?>">
                            <strong><?= $h((string) ($row['title'] ?? 'Article')) ?></strong>
                            <em><?= $h($statusLabel) ?><?= !empty($row['pinned']) ? ' · Épinglé' : '' ?></em>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <footer>
                <a href="<?= $h(url('back-office/articles')) ?>" class="ath-btn ath-btn--solid">Ouvrir les articles</a>
                <a href="<?= $h(url('back-office/articles/create')) ?>" class="ath-btn">Nouvel article</a>
            </footer>
        </article>
        <?php endif; ?>

        <?php if ($canAtak): ?>
        <article class="ops-board__cockpit-card">
            <header>
                <h3>ATAK</h3>
                <span>Poste et carte</span>
            </header>
            <ul class="ops-board__cockpit-links">
                <li><a href="<?= $h(url('back-office/atak')) ?>">Poste de situation</a></li>
                <li><a href="<?= $h(url('back-office/atak/operateurs')) ?>">Sessions et liaisons</a></li>
                <li><a href="<?= $h(url('tacmap')) ?>">Carte tactique</a></li>
                <li><a href="<?= $h(url('back-office/atak/cycle-mission')) ?>">Cycle de mission</a></li>
                <li><a href="<?= $h(url('back-office/atak/comptes-rendus')) ?>">Comptes rendus</a></li>
            </ul>
            <footer>
                <a href="<?= $h(url('back-office/atak')) ?>" class="ath-btn ath-btn--solid">Ouvrir le poste ATAK</a>
            </footer>
        </article>
        <?php endif; ?>
    </div>

    <?php if ($canMissions || $canAlerts || $canEffectifs || $canOpsCenter): ?>
    <nav class="ops-board__cockpit-levers" aria-label="Autres leviers de pilotage">
        <?php if ($canMissions): ?>
            <a href="<?= $h(url('back-office/missions')) ?>">Portail missions<?php if ($missions !== []): ?> <strong><?= count($missions) ?></strong><?php endif; ?></a>
            <?php
            $firstMissionId = $missions !== [] ? (int) ($missions[0]['id'] ?? 0) : 0;
            if ($firstMissionId > 0): ?>
                <a href="<?= $h(url('back-office/cooperation/missions/' . $firstMissionId)) ?>">Coopération en cours</a>
            <?php else: ?>
                <a href="<?= $h(url('back-office/cooperation')) ?>">Coopération</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($canAlerts): ?>
            <a href="<?= $h(url('back-office/alerts')) ?>"<?= $alertsCount > 0 ? ' class="is-alert"' : '' ?>>Annonces<?php if ($alertsCount > 0): ?> <strong><?= $alertsCount ?></strong><?php endif; ?></a>
        <?php endif; ?>
        <?php if ($canEffectifs): ?>
            <a href="<?= $h(effectifs_workspace_url()) ?>">Effectifs</a>
        <?php endif; ?>
        <?php if ($canOpsCenter): ?>
            <a href="<?= $h(url('back-office/centre-operations')) ?>">Centre d’opérations</a>
            <a href="<?= $h(url('back-office/courrier/traceabilite')) ?>">Courrier</a>
            <a href="<?= $h(url('back-office/planification')) ?>">Planification</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</section>
