<?php
declare(strict_types=1);

/**
 * Back-office — Tableau opérationnel (pilotage du mur que lisent les membres).
 * Ordre de lecture : à quoi sert la page, état actuel, fiches du mur, création, modèles, partage, outils liés.
 * Styles : public/assets/css/back-office-tableau-operationnel.css (préfixe bo-tbo).
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$boardFilters = $boardFilters ?? [];
$boardPanels = array_merge([
    'permanences' => [],
    'infos' => [],
    'manifestations' => [],
    'flash' => [],
    'activites' => [],
], $boardPanels ?? []);
$boardToday = $boardToday ?? date('Y-m-d');
$boardTemplates = $boardTemplates ?? [];
$boardPosture = $boardPosture ?? ['posture_level' => 'NORMAL'];
$boardLogs = $boardLogs ?? [];
$boardTags = $boardTags ?? [];
$boardQualificationAlerts = $boardQualificationAlerts ?? [];
$boardFireConflicts = $boardFireConflicts ?? [];
$boardSchemaReady = $boardSchemaReady ?? true;
$boardShareSchemaReady = $boardShareSchemaReady ?? false;
$boardPublicWallUrl = trim((string) ($boardPublicWallUrl ?? ''));
$boardDraftCount = (int) ($boardDraftCount ?? 0);
$boardEntryCount = (int) ($boardEntryCount ?? (
    count($boardPanels['permanences']) + count($boardPanels['infos']) + count($boardPanels['manifestations'])
    + count($boardPanels['flash']) + count($boardPanels['activites'])
));
$csrf = $h(\App\Core\Csrf::token());

$posture = (string) ($boardPosture['posture_level'] ?? 'NORMAL');
$postureOptions = [
    'NORMAL' => ['label' => 'Normale', 'option' => 'Normale : rien de particulier'],
    'VIGILANCE' => ['label' => 'Vigilance', 'option' => 'Vigilance : restez attentifs aux consignes'],
    'ALERTE' => ['label' => 'Alerte', 'option' => 'Alerte : situation tendue, suivez les flashs'],
    'CRISE' => ['label' => 'Crise', 'option' => 'Crise : tout le monde est mobilisé'],
];
if (!isset($postureOptions[$posture])) {
    $posture = 'NORMAL';
}
$postureTone = match ($posture) {
    'VIGILANCE' => 'warn',
    'ALERTE' => 'alert',
    'CRISE' => 'danger',
    default => 'ok',
};

$entryTypeLabels = [
    'permanence' => 'Permanence',
    'info' => 'Information pratique',
    'manifestation' => 'Manifestation',
    'mission' => 'Mission',
    'task' => 'Tâche',
    'formation' => 'Formation',
    'flash_info' => 'Flash information',
    'flash_info_detailed' => 'Flash information détaillé',
];
$operationalLabels = [
    'planned' => 'Planifié',
    'in_progress' => 'En cours',
    'suspended' => 'Suspendu',
    'completed' => 'Terminé',
    'cancelled' => 'Annulé',
];
$phaseLabels = [
    'phase_1' => 'Phase 1',
    'phase_2' => 'Phase 2',
    'phase_3' => 'Phase 3',
];
$priorityShort = [
    'critical' => 'Critique',
    'high' => 'Élevée',
    'normal' => 'Normale',
    'low' => 'Faible',
];
$familleModeleLabels = [
    'permanence_opj' => 'Permanence judiciaire',
    'mission_judiciaire' => 'Mission judiciaire',
    'instruction' => 'Instruction ou formation',
    'dispositif_securite' => 'Dispositif sécurité',
    'exercice' => 'Exercice',
    'custom' => 'Sur mesure',
];

$temporalBucket = static function (array $e, string $today): string {
    $op = (string) ($e['operational_status'] ?? 'planned');
    if ($op === 'in_progress') {
        return 'en_cours';
    }
    $start = isset($e['start_date']) && $e['start_date'] !== null && $e['start_date'] !== '' ? (string) $e['start_date'] : '';
    $end = isset($e['end_date']) && $e['end_date'] !== null && $e['end_date'] !== '' ? (string) $e['end_date'] : '';
    if ($start !== '' && $start > $today) {
        return 'a_venir';
    }
    if ($end !== '' && $end < $today) {
        return 'passe';
    }
    if ($start !== '' && $start <= $today && ($end === '' || $end >= $today)) {
        return 'aujourdhui';
    }

    return 'sans_date';
};
/* Permanences : aujourd’hui et en cours d’abord, puis à venir, puis le reste. */
$bucketRank = ['en_cours' => 0, 'aujourdhui' => 1, 'a_venir' => 2, 'sans_date' => 3, 'passe' => 4];
usort($boardPanels['permanences'], static function (array $a, array $b) use ($temporalBucket, $boardToday, $bucketRank): int {
    return [$bucketRank[$temporalBucket($a, $boardToday)] ?? 9, (string) ($a['start_date'] ?? '')]
        <=> [$bucketRank[$temporalBucket($b, $boardToday)] ?? 9, (string) ($b['start_date'] ?? '')];
});

$statusFilter = (string) ($boardFilters['status'] ?? 'active');
$periodStart = (string) ($boardFilters['period_start'] ?? '');
$periodEnd = (string) ($boardFilters['period_end'] ?? '');
$fmtDay = static function (string $d): string {
    $ts = $d !== '' ? strtotime($d) : false;

    return $ts ? date('d/m/Y', $ts) : $d;
};
$boardUrl = url('back-office/tableau-operationnel');
$filterUrl = static function (array $over) use ($boardFilters, $boardUrl): string {
    $q = array_merge([
        'status' => (string) ($boardFilters['status'] ?? 'active'),
        'period_start' => (string) ($boardFilters['period_start'] ?? ''),
        'period_end' => (string) ($boardFilters['period_end'] ?? ''),
        'entry_type' => (string) ($boardFilters['entry_type'] ?? ''),
        'operational_status' => (string) ($boardFilters['operational_status'] ?? ''),
        'tag' => (string) ($boardFilters['tag'] ?? ''),
        'mode' => (string) ($boardFilters['mode'] ?? 'standard'),
        'critical_only' => (int) ($boardFilters['critical_only'] ?? 0),
    ], $over);

    return $boardUrl . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986) . '#tbo-fiches';
};
$statusTabs = [
    'active' => ['label' => 'En ligne', 'hint' => 'Ce que les membres voient en ce moment.'],
    'draft' => ['label' => 'Brouillons', 'hint' => 'Fiches en préparation, invisibles pour les membres.'],
    'cancelled' => ['label' => 'Retirées', 'hint' => 'Fiches enlevées du mur, gardées pour l’historique.'],
    'all' => ['label' => 'Toutes', 'hint' => 'Toutes les fiches, quel que soit leur état.'],
];
if (!isset($statusTabs[$statusFilter])) {
    $statusFilter = 'active';
}

$nouvelleFiche = url('back-office/tableau-operationnel/fiche/nouvelle');
$lienNouvelle = static function (string $type) use ($nouvelleFiche, $boardToday): string {
    return $nouvelleFiche . '/' . rawurlencode($type) . '?debut=' . rawurlencode((string) $boardToday);
};

/* Groupes du mur, dans l’ordre où les membres les lisent. */
$groups = [
    'flash' => [
        'title' => 'Messages urgents',
        'hint' => 'Flashs courts affichés tout en haut du mur. À réserver à ce qui ne peut pas attendre.',
        'new' => 'flash_info',
        'newLabel' => 'Nouveau flash',
    ],
    'permanences' => [
        'title' => 'Permanences',
        'hint' => 'Qui est de service, où et quand. Les permanences du jour passent en premier.',
        'new' => 'permanence',
        'newLabel' => 'Nouvelle permanence',
    ],
    'infos' => [
        'title' => 'Infos pratiques',
        'hint' => 'Consignes qui durent : horaires, contacts, règles du serveur.',
        'new' => 'info',
        'newLabel' => 'Nouvelle info',
    ],
    'manifestations' => [
        'title' => 'Manifestations',
        'hint' => 'Événements ponctuels à annoncer : opérations, soirées, cérémonies.',
        'new' => 'manifestation',
        'newLabel' => 'Nouvelle manifestation',
    ],
    'activites' => [
        'title' => 'Missions, tâches et formations',
        'hint' => 'Le travail à faire : missions de l’unité, tâches internes, stages.',
        'new' => 'mission',
        'newLabel' => 'Nouvelle mission',
    ],
];

$quickTypes = [
    'flash_info' => ['label' => 'Flash information', 'hint' => 'Un message urgent de quelques lignes.', 'tone' => 'flash'],
    'flash_info_detailed' => ['label' => 'Flash détaillé', 'hint' => 'Un message urgent avec actions, restrictions et contact.', 'tone' => 'flash'],
    'permanence' => ['label' => 'Permanence', 'hint' => 'Une personne de service sur un créneau.', 'tone' => 'perm'],
    'info' => ['label' => 'Info pratique', 'hint' => 'Une consigne qui reste affichée.', 'tone' => 'info'],
    'manifestation' => ['label' => 'Manifestation', 'hint' => 'Un événement daté à annoncer.', 'tone' => 'event'],
    'mission' => ['label' => 'Mission', 'hint' => 'Une mission avec responsable et lieu.', 'tone' => 'mission'],
    'task' => ['label' => 'Tâche interne', 'hint' => 'Un travail à faire dans l’équipe.', 'tone' => 'mission'],
    'formation' => ['label' => 'Formation', 'hint' => 'Un stage ou une instruction.', 'tone' => 'mission'],
];

$renderCard = static function (array $entry) use ($boardToday, $entryTypeLabels, $operationalLabels, $priorityShort, $phaseLabels, $temporalBucket): void {
    require __DIR__ . '/partials/board_admin_card.php';
};

$boardCockpit = is_array($boardCockpit ?? null) ? $boardCockpit : [];
$hasTools = false;
foreach (['can_events', 'can_articles', 'can_atak', 'can_effectifs', 'can_missions', 'can_alerts', 'can_ops_center'] as $toolKey) {
    $hasTools = $hasTools || !empty($boardCockpit[$toolKey]);
}

$alertsToFix = count($boardQualificationAlerts) + count($boardFireConflicts);
?>
<div class="bo-tbo">
<?php if (!$boardSchemaReady): ?>
    <section class="bo-tbo-panel bo-tbo-empty" role="status">
        <h2>Le mur opérationnel n’est pas encore disponible ici</h2>
        <p>Les tables du mur ne sont pas installées sur ce serveur. Prévenez l’équipe d’hébergement, puis actualisez cette page. Les événements, articles et le poste ATAK restent accessibles plus bas.</p>
        <button type="button" class="bo-tbo-btn bo-tbo-btn--primary" onclick="location.reload()">Actualiser la page</button>
    </section>
    <?php require base_path('views/operations/partials/board_pilotage.php'); ?>
<?php else: ?>

    <!-- À quoi sert cette page -->
    <section class="bo-tbo-intro" aria-labelledby="tbo-intro-title">
        <div class="bo-tbo-intro__text">
            <h2 id="tbo-intro-title">Le mur opérationnel, c’est le panneau d’affichage de vos membres.</h2>
            <p>Vous y publiez des fiches courtes : qui est de permanence, les consignes pratiques, les événements à venir et les messages urgents. Les membres les lisent sur la page « Mur opérationnel ». Ici, vous les préparez et vous décidez de ce qui est affiché.</p>
            <ol class="bo-tbo-steps" aria-label="Étapes d’une fiche">
                <li><span class="bo-tbo-steps__n">1</span><span><strong>Créer</strong><span>La fiche part en brouillon, invisible pour les membres.</span></span></li>
                <li><span class="bo-tbo-steps__n">2</span><span><strong>Mettre en ligne</strong><span>Les membres concernés la voient sur leur mur.</span></span></li>
                <li><span class="bo-tbo-steps__n">3</span><span><strong>Terminer</strong><span>Quand l’activité est finie, ou retirer la fiche.</span></span></li>
            </ol>
        </div>
        <div class="bo-tbo-intro__actions">
            <a href="#tbo-nouvelle" class="bo-tbo-btn bo-tbo-btn--primary">Ajouter une fiche</a>
            <a href="<?= $h(url('tableau-operationnel')) ?>" class="bo-tbo-btn bo-tbo-btn--outline">Voir le mur comme un membre</a>
            <a href="<?= $h(url('documentation') . '#mur-operationnel') ?>" class="bo-tbo-intro__doc">Guide complet du mur</a>
        </div>
    </section>

    <!-- État actuel -->
    <section class="bo-tbo-stats" aria-label="État actuel du mur">
        <article class="bo-tbo-stat bo-tbo-stat--posture bo-tbo-stat--<?= $h($postureTone) ?>">
            <p class="bo-tbo-stat__label">Niveau d’alerte</p>
            <p class="bo-tbo-stat__value"><?= $h($postureOptions[$posture]['label']) ?></p>
            <p class="bo-tbo-stat__hint">Affiché en haut du mur des membres et sur la page publique.</p>
            <form method="post" action="<?= $h(url('back-office/tableau-operationnel/posture')) ?>" class="bo-tbo-stat__form">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <label class="bo-tbo-sr" for="posture_level">Changer le niveau d’alerte</label>
                <select id="posture_level" name="posture_level" class="bo-tbo-input bo-tbo-input--sm">
                    <?php foreach ($postureOptions as $val => $opt): ?>
                        <option value="<?= $h($val) ?>" <?= $posture === $val ? 'selected' : '' ?>><?= $h($opt['option']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bo-tbo-btn bo-tbo-btn--sm">Changer</button>
            </form>
        </article>
        <a class="bo-tbo-stat bo-tbo-stat--link" href="<?= $h($filterUrl(['status' => 'active'])) ?>">
            <p class="bo-tbo-stat__label">En ligne</p>
            <p class="bo-tbo-stat__value"><?= $statusFilter === 'active' ? $boardEntryCount : '—' ?></p>
            <p class="bo-tbo-stat__hint"><?= $statusFilter === 'active' ? 'fiches visibles par les membres sur la période affichée.' : 'Cliquez pour voir les fiches que les membres lisent.' ?></p>
        </a>
        <a class="bo-tbo-stat bo-tbo-stat--link<?= $boardDraftCount > 0 ? ' bo-tbo-stat--warn' : '' ?>" href="<?= $h($filterUrl(['status' => 'draft'])) ?>">
            <p class="bo-tbo-stat__label">Brouillons</p>
            <p class="bo-tbo-stat__value"><?= $boardDraftCount ?></p>
            <p class="bo-tbo-stat__hint"><?= $boardDraftCount > 0 ? 'fiches en préparation, pas encore visibles. Cliquez pour les finir.' : 'Aucune fiche en attente de publication.' ?></p>
        </a>
        <a class="bo-tbo-stat bo-tbo-stat--link" href="#tbo-partage">
            <p class="bo-tbo-stat__label">Page publique</p>
            <p class="bo-tbo-stat__value"><?= $boardPublicWallUrl !== '' ? 'Active' : 'Désactivée' ?></p>
            <p class="bo-tbo-stat__hint">Copie du mur en lecture seule, pour des personnes sans compte.</p>
        </a>
    </section>

    <?php if ($alertsToFix > 0): ?>
    <section class="bo-tbo-fix" aria-labelledby="tbo-fix-title">
        <h2 id="tbo-fix-title">À corriger : <?= $alertsToFix ?></h2>
        <ul>
            <?php foreach ($boardQualificationAlerts as $a): ?>
                <li><?= $h((string) ($a['person_label'] ?? 'Un membre')) ?> n’a pas la qualification demandée (absente ou expirée) pour « <?= $h((string) ($a['entry_title'] ?? 'mission')) ?> ». Changez la personne ou mettez sa qualification à jour.</li>
            <?php endforeach; ?>
            <?php foreach ($boardFireConflicts as $c): ?>
                <li>« <?= $h((string) ($c['title'] ?? '')) ?> » finit avant de commencer. Corrigez les horaires de la fiche.</li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <nav class="bo-tbo-tabs" aria-label="Sections de la page">
        <a href="#tbo-fiches">Fiches du mur</a>
        <a href="#tbo-nouvelle">Ajouter une fiche</a>
        <a href="#tbo-modeles">Modèles</a>
        <a href="#tbo-partage">Page publique</a>
        <?php if ($hasTools): ?><a href="#ops-zone-pilotage">Outils liés</a><?php endif; ?>
    </nav>

    <!-- Fiches du mur -->
    <section id="tbo-fiches" class="bo-tbo-section" aria-labelledby="tbo-fiches-title">
        <header class="bo-tbo-section__head">
            <div>
                <h2 id="tbo-fiches-title">Fiches du mur</h2>
                <p><span><?= $h($statusTabs[$statusFilter]['hint']) ?></span> <span>Période : du <?= $h($fmtDay($periodStart)) ?> au <?= $h($fmtDay($periodEnd)) ?>.</span></p>
            </div>
            <a href="<?= $h(url('back-office/tableau-operationnel/stream')) ?>" class="bo-tbo-btn bo-tbo-btn--ghost bo-tbo-btn--sm" title="Liste brute des derniers changements">Activité récente</a>
        </header>

        <div class="bo-tbo-bar">
            <nav class="bo-tbo-seg" aria-label="Quelles fiches afficher">
                <?php foreach ($statusTabs as $key => $tab): ?>
                    <a href="<?= $h($filterUrl(['status' => $key])) ?>" class="bo-tbo-seg__item<?= $statusFilter === $key ? ' is-active' : '' ?>"<?= $statusFilter === $key ? ' aria-current="page"' : '' ?>>
                        <?= $h($tab['label']) ?><?php if ($key === 'draft' && $boardDraftCount > 0): ?> <span class="bo-tbo-seg__count"><?= $boardDraftCount ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form method="get" action="<?= $h($boardUrl) ?>" class="bo-tbo-period">
                <input type="hidden" name="status" value="<?= $h($statusFilter) ?>">
                <?php foreach (['entry_type', 'operational_status', 'tag'] as $keep): ?>
                    <input type="hidden" name="<?= $h($keep) ?>" value="<?= $h((string) ($boardFilters[$keep] ?? '')) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="mode" value="<?= $h((string) ($boardFilters['mode'] ?? 'standard')) ?>">
                <input type="hidden" name="critical_only" value="<?= (int) ($boardFilters['critical_only'] ?? 0) ?>">
                <label for="tbo-period-start">Du</label>
                <input id="tbo-period-start" type="date" name="period_start" value="<?= $h($periodStart) ?>" class="bo-tbo-input bo-tbo-input--sm">
                <label for="tbo-period-end">au</label>
                <input id="tbo-period-end" type="date" name="period_end" value="<?= $h($periodEnd) ?>" class="bo-tbo-input bo-tbo-input--sm">
                <button type="submit" class="bo-tbo-btn bo-tbo-btn--sm">Afficher</button>
            </form>
        </div>

        <div class="bo-tbo-filters" id="js-filters">
            <input type="search" data-filter="text" class="bo-tbo-input bo-tbo-filters__search" aria-label="Rechercher une fiche" placeholder="Rechercher : titre, personne, lieu…">
            <select data-filter="entry_type" class="bo-tbo-input" aria-label="Type de fiche">
                <option value="">Tous les types</option>
                <?php foreach ($entryTypeLabels as $k => $lbl): ?>
                    <option value="<?= $h($k) ?>"><?= $h($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select data-filter="priority" class="bo-tbo-input" aria-label="Priorité">
                <option value="">Toutes les priorités</option>
                <?php foreach ($priorityShort as $k => $lbl): ?>
                    <option value="<?= $h($k) ?>"><?= $h($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select data-filter="operational_status" class="bo-tbo-input" aria-label="État de l’activité">
                <option value="">Tous les états</option>
                <?php foreach ($operationalLabels as $k => $lbl): ?>
                    <option value="<?= $h($k) ?>"><?= $h($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($boardTags !== []): ?>
            <select data-filter="tag" class="bo-tbo-input" aria-label="Étiquette">
                <option value="">Toutes les étiquettes</option>
                <?php foreach ($boardTags as $tag): ?>
                    <option value="<?= $h((string) ($tag['tag'] ?? '')) ?>"><?= $h((string) ($tag['tag'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select id="mode-switch" class="bo-tbo-input" aria-label="Présentation">
                <option value="standard">Fiches complètes</option>
                <option value="crise">Seulement l’urgent</option>
                <option value="briefing">Titres seuls</option>
            </select>
            <button type="button" id="filter-reset" class="bo-tbo-btn bo-tbo-btn--ghost bo-tbo-btn--sm">Effacer les filtres</button>
            <p id="filter-live-count" class="bo-tbo-filters__count" aria-live="polite"></p>
        </div>

        <?php if ($boardEntryCount < 1): ?>
            <div class="bo-tbo-empty bo-tbo-empty--inline" role="status">
                <h3><?= $statusFilter === 'draft' ? 'Aucun brouillon' : ($statusFilter === 'cancelled' ? 'Aucune fiche retirée' : 'Aucune fiche sur cette période') ?></h3>
                <p>Changez la période ci-dessus, ou créez une fiche.</p>
                <a href="#tbo-nouvelle" class="bo-tbo-btn bo-tbo-btn--primary bo-tbo-btn--sm">Ajouter une fiche</a>
            </div>
        <?php else: ?>
        <div id="board-columns" class="bo-tbo-groups">
            <?php foreach ($groups as $gKey => $g):
                $list = $boardPanels[$gKey];
                ?>
                <section class="bo-tbo-group bo-tbo-group--<?= $h($gKey) ?><?= $list === [] ? ' is-empty' : '' ?>" data-column="<?= $h($gKey) ?>" aria-labelledby="tbo-g-<?= $h($gKey) ?>">
                    <header class="bo-tbo-group__head">
                        <h3 id="tbo-g-<?= $h($gKey) ?>"><?= $h($g['title']) ?> <span class="bo-tbo-group__count"><?= count($list) ?></span></h3>
                        <p><?= $h($g['hint']) ?></p>
                    </header>
                    <?php if ($list === []): ?>
                        <p class="bo-tbo-group__none">Rien ici pour l’instant. <a class="bo-tbo-card__inline-link" href="<?= $h($lienNouvelle($g['new'])) ?>"><?= $h($g['newLabel']) ?></a></p>
                    <?php else: ?>
                        <div class="bo-tbo-cards">
                            <?php foreach ($list as $entry) {
                                $renderCard($entry);
                            } ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <details class="bo-tbo-journal">
            <summary>Dernières modifications <span class="bo-tbo-group__count"><?= count($boardLogs) ?></span></summary>
            <ul id="live-stream" class="bo-tbo-journal__list">
                <?php foreach ($boardLogs as $log):
                    $ts = strtotime((string) ($log['created_at'] ?? ''));
                    ?>
                    <li>
                        <time><?= $h($ts ? date('d/m H:i', $ts) : (string) ($log['created_at'] ?? '')) ?></time>
                        <span><strong><?= $h((string) ($log['title'] ?? '')) ?></strong><?php if (trim((string) ($log['summary'] ?? '')) !== ''): ?> · <?= $h((string) $log['summary']) ?><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if ($boardLogs === []): ?>
                    <li class="bo-tbo-journal__none">Aucune modification récente.</li>
                <?php endif; ?>
            </ul>
        </details>
    </section>

    <!-- Nouvelle fiche -->
    <section id="tbo-nouvelle" class="bo-tbo-section" aria-labelledby="tbo-nouvelle-title">
        <header class="bo-tbo-section__head">
            <div>
                <h2 id="tbo-nouvelle-title">Ajouter une fiche</h2>
                <p>Choisissez ce que vous voulez afficher. La fiche s’ouvre en brouillon, datée d’aujourd’hui : complétez-la, puis mettez-la en ligne.</p>
            </div>
        </header>
        <div class="bo-tbo-types">
            <?php foreach ($quickTypes as $type => $qt): ?>
                <a class="bo-tbo-type bo-tbo-type--<?= $h($qt['tone']) ?>" href="<?= $h($lienNouvelle($type)) ?>">
                    <strong><?= $h($qt['label']) ?></strong>
                    <span><?= $h($qt['hint']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="bo-tbo-section__foot">
            <span>Vous ne savez pas quel type choisir ?</span> <a class="bo-tbo-card__inline-link" href="<?= $h($nouvelleFiche) ?>">Ouvrir le formulaire complet</a> <span>Vous pouvez aussi partir d’un modèle ci-dessous.</span>
        </p>
    </section>

    <!-- Modèles -->
    <section id="tbo-modeles" class="bo-tbo-section" aria-labelledby="tbo-modeles-title">
        <header class="bo-tbo-section__head">
            <div>
                <h2 id="tbo-modeles-title">Modèles</h2>
                <p>Un modèle est une fiche type que vous réutilisez souvent (même titre, même consigne). Il n’apparaît jamais sur le mur : il sert seulement à créer des brouillons plus vite.</p>
            </div>
        </header>
        <div class="bo-tbo-split">
            <form method="post" action="<?= $h(url('back-office/tableau-operationnel/template')) ?>" class="bo-tbo-panel">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <h3>Créer une fiche depuis un modèle</h3>
                <?php if ($boardTemplates === []): ?>
                    <p class="bo-tbo-muted">Vous n’avez pas encore de modèle. Créez-en un à côté.</p>
                <?php else: ?>
                    <label class="bo-tbo-label" for="template_id">Modèle</label>
                    <select id="template_id" name="template_id" class="bo-tbo-input">
                        <?php foreach ($boardTemplates as $tpl):
                            $fam = $familleModeleLabels[(string) ($tpl['template_type'] ?? 'custom')] ?? 'Sur mesure';
                            ?>
                            <option value="<?= (int) ($tpl['id'] ?? 0) ?>"><?= $h((string) ($tpl['name'] ?? 'Modèle')) ?> (<?= $h($fam) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="bo-tbo-muted">Modèles disponibles : <?= count($boardTemplates) ?></p>
                    <button type="submit" class="bo-tbo-btn bo-tbo-btn--primary">Créer le brouillon</button>
                <?php endif; ?>
            </form>

            <form method="post" action="<?= $h(url('back-office/tableau-operationnel/modele')) ?>" class="bo-tbo-panel">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <h3>Nouveau modèle</h3>
                <label class="bo-tbo-label" for="template_name">Nom du modèle</label>
                <input id="template_name" name="template_name" required maxlength="160" class="bo-tbo-input" autocomplete="off" placeholder="Ex. Permanence recrutement du mercredi">
                <div class="bo-tbo-row">
                    <div>
                        <label class="bo-tbo-label" for="tpl_entry_type">Type de fiche</label>
                        <select id="tpl_entry_type" name="entry_type" class="bo-tbo-input">
                            <?php foreach ($entryTypeLabels as $k => $lbl): ?>
                                <option value="<?= $h($k) ?>"><?= $h($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="bo-tbo-label" for="mission_family">Catégorie</label>
                        <select id="mission_family" name="mission_family" class="bo-tbo-input">
                            <?php foreach ($familleModeleLabels as $val => $lib): ?>
                                <option value="<?= $h($val) ?>"<?= $val === 'custom' ? ' selected' : '' ?>><?= $h($lib) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <label class="bo-tbo-label" for="default_title">Titre de la fiche <span class="bo-tbo-muted">(facultatif)</span></label>
                <input id="default_title" name="default_title" maxlength="180" class="bo-tbo-input" autocomplete="off" placeholder="Vide = le nom du modèle">
                <label class="bo-tbo-label" for="default_description">Texte de la fiche <span class="bo-tbo-muted">(facultatif)</span></label>
                <textarea id="default_description" name="default_description" rows="3" class="bo-tbo-input" placeholder="Consigne reprise sur chaque fiche créée avec ce modèle."></textarea>
                <button type="submit" class="bo-tbo-btn">Enregistrer le modèle</button>
            </form>
        </div>
    </section>

    <!-- Page publique -->
    <section id="tbo-partage" class="bo-tbo-section" aria-labelledby="tbo-partage-title">
        <header class="bo-tbo-section__head">
            <div>
                <h2 id="tbo-partage-title">Page publique</h2>
                <p>Une adresse secrète qui affiche le mur en lecture seule, sans connexion. Pratique pour un écran de briefing ou un partenaire. Toute personne qui a le lien peut la voir.</p>
            </div>
        </header>
        <?php if (!$boardShareSchemaReady): ?>
            <div class="bo-tbo-panel bo-tbo-muted" role="status">La page publique n’est pas encore installée sur ce serveur. Prévenez l’équipe d’hébergement.</div>
        <?php elseif ($boardPublicWallUrl === ''): ?>
            <form method="post" action="<?= $h(url('back-office/tableau-operationnel/lien-public')) ?>" class="bo-tbo-panel bo-tbo-share">
                <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                <p><span class="bo-tbo-dot"></span> Désactivée : personne ne peut voir le mur sans compte.</p>
                <button type="submit" class="bo-tbo-btn bo-tbo-btn--primary">Créer le lien public</button>
            </form>
        <?php else: ?>
            <div class="bo-tbo-panel bo-tbo-share">
                <p><span class="bo-tbo-dot is-on"></span> Active : les fiches en ligne de la période sont visibles à cette adresse.</p>
                <div class="bo-tbo-share__url">
                    <label class="bo-tbo-sr" for="ops-public-url">Adresse de la page publique</label>
                    <input id="ops-public-url" type="text" readonly class="bo-tbo-input" value="<?= $h($boardPublicWallUrl) ?>">
                    <button type="button" class="bo-tbo-btn bo-tbo-btn--primary" data-tbo-copy="ops-public-url">Copier le lien</button>
                    <a href="<?= $h($boardPublicWallUrl) ?>" class="bo-tbo-btn bo-tbo-btn--outline" target="_blank" rel="noopener">Ouvrir</a>
                </div>
                <div class="bo-tbo-share__danger">
                    <form method="post" action="<?= $h(url('back-office/tableau-operationnel/lien-public/renouveler')) ?>" data-confirm="Changer l’adresse ? L’ancien lien ne marchera plus.">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="bo-tbo-btn bo-tbo-btn--ghost bo-tbo-btn--sm">Changer l’adresse</button>
                    </form>
                    <form method="post" action="<?= $h(url('back-office/tableau-operationnel/lien-public/desactiver')) ?>" data-confirm="Désactiver la page publique ? Le lien ne marchera plus.">
                        <input type="hidden" name="_csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="bo-tbo-btn bo-tbo-btn--ghost bo-tbo-btn--sm bo-tbo-btn--danger">Désactiver</button>
                    </form>
                    <p class="bo-tbo-muted">Changez l’adresse si le lien a circulé trop loin.</p>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php require base_path('views/operations/partials/board_pilotage.php'); ?>
<?php endif; ?>
</div>

<?php if ($boardSchemaReady): ?>
<script>
(() => {
    const root = document.querySelector('.bo-tbo');
    if (!root) return;
    const filters = root.querySelectorAll('[data-filter]');
    const cards = root.querySelectorAll('.entry-card');
    const groups = root.querySelectorAll('.bo-tbo-group');
    const modeSwitch = document.getElementById('mode-switch');
    const resetBtn = document.getElementById('filter-reset');
    const live = document.getElementById('live-stream');
    const liveCount = document.getElementById('filter-live-count');
    const boardColumns = document.getElementById('board-columns');
    let sinceId = 0;

    function applyFilters() {
        const state = {};
        filters.forEach(el => { state[el.dataset.filter] = (el.value || '').toLowerCase(); });
        const mode = (modeSwitch?.value || 'standard');
        let visible = 0;
        boardColumns?.classList.toggle('board-mode-briefing', mode === 'briefing');

        cards.forEach(card => {
            const ok = Object.entries(state).every(([k, v]) => {
                if (!v) return true;
                if (k === 'text') return (card.dataset.search || '').includes(v);
                if (k === 'tag') return (card.dataset.tag || '').includes(v);
                return (card.dataset[k] || '').toLowerCase() === v;
            });
            const urgent = (card.dataset.priority || '') === 'critical' || (card.dataset.operational_status || '') === 'in_progress';
            const show = ok && (mode !== 'crise' || urgent);
            card.hidden = !show;
            if (show) visible += 1;
        });
        groups.forEach(g => {
            const list = g.querySelectorAll('.entry-card');
            if (!list.length) return;
            g.classList.toggle('is-filtered-out', Array.from(list).every(c => c.hidden));
        });
        const filtered = mode !== 'standard' || Object.values(state).some(Boolean);
        if (liveCount) {
            liveCount.textContent = filtered
                ? `Fiches affichées : ${visible} sur ${cards.length}`
                : `Fiches affichées : ${cards.length}`;
        }
    }

    filters.forEach(el => el.addEventListener(el.type === 'search' ? 'input' : 'change', applyFilters));
    modeSwitch?.addEventListener('change', applyFilters);
    resetBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        filters.forEach(el => { el.value = ''; });
        if (modeSwitch) modeSwitch.value = 'standard';
        applyFilters();
    });
    applyFilters();

    /* Un seul menu « Plus d’actions » ouvert à la fois. */
    root.addEventListener('toggle', (e) => {
        const d = e.target;
        if (!(d instanceof HTMLDetailsElement) || !d.classList.contains('bo-tbo-more') || !d.open) return;
        root.querySelectorAll('details.bo-tbo-more[open]').forEach(o => { if (o !== d) o.open = false; });
    }, true);
    document.addEventListener('click', (e) => {
        root.querySelectorAll('details.bo-tbo-more[open]').forEach(o => { if (!o.contains(e.target)) o.open = false; });
    });

    root.querySelectorAll('form[data-confirm]').forEach(f => {
        f.addEventListener('submit', (e) => { if (!window.confirm(f.dataset.confirm || '')) e.preventDefault(); });
    });

    root.querySelectorAll('[data-tbo-copy]').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.tboCopy);
            if (!input) return;
            const label = btn.textContent;
            const done = () => { btn.textContent = 'Copié'; setTimeout(() => { btn.textContent = label; }, 1600); };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(done, () => { input.select(); });
            } else {
                input.select();
                document.execCommand('copy');
                done();
            }
        });
    });

    async function poll() {
        try {
            const res = await fetch('<?= url('back-office/tableau-operationnel/stream') ?>?since_id=' + sinceId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            if (!json || !Array.isArray(json.events)) return;
            json.events.forEach(ev => {
                sinceId = Math.max(sinceId, Number(ev.id || 0));
                const row = document.createElement('li');
                const time = document.createElement('time');
                time.textContent = String(ev.created_at || '');
                const text = document.createElement('span');
                text.textContent = String(ev.event_type || 'Événement');
                row.append(time, text);
                live?.querySelector('.bo-tbo-journal__none')?.remove();
                live?.prepend(row);
            });
        } catch (e) {}
        setTimeout(poll, 8000);
    }
    poll();
})();
</script>
<?php endif; ?>
