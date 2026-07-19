<?php
declare(strict_types=1);

$boardFilters = $boardFilters ?? [];
$boardPanels = array_merge([
    'permanences' => [],
    'infos' => [],
    'manifestations' => [],
    'flash' => [],
    'activites' => [],
], $boardPanels ?? []);
$boardPosture = $boardPosture ?? ['posture_level' => 'NORMAL'];
$boardSchemaReady = $boardSchemaReady ?? true;
$boardToday = $boardToday ?? date('Y-m-d');
$boardEntryCount = (int) ($boardEntryCount ?? 0);
$boardCanEdit = !empty($boardCanEdit);

$posture = (string) ($boardPosture['posture_level'] ?? 'NORMAL');
$posturePresentation = [
    'NORMAL' => ['label' => 'Normale', 'pill' => 'ops-board__pill--ok'],
    'VIGILANCE' => ['label' => 'Vigilance', 'pill' => 'ops-board__pill--warn'],
    'ALERTE' => ['label' => 'Alerte', 'pill' => 'ops-board__pill--warn'],
    'CRISE' => ['label' => 'Crise', 'pill' => 'ops-board__pill--danger'],
];
$postureUi = $posturePresentation[$posture] ?? $posturePresentation['NORMAL'];

$entryTypeLabels = [
    'permanence' => 'Permanence',
    'info' => 'Information pratique',
    'manifestation' => 'Manifestation',
    'mission' => 'Mission',
    'task' => 'Tâche',
    'formation' => 'Formation',
    'flash_info' => 'Flash information',
];
$operationalLabels = [
    'planned' => 'Planifié',
    'in_progress' => 'En cours',
    'suspended' => 'Suspendu',
    'completed' => 'Terminé',
    'cancelled' => 'Annulé',
];
$phaseLabels = ['phase_1' => 'Phase 1', 'phase_2' => 'Phase 2', 'phase_3' => 'Phase 3'];
$priorityClass = [
    'critical' => 'border-l-rose-500 border-rose-200 bg-white text-slate-900',
    'high' => 'border-l-orange-400 border-orange-100 bg-white text-slate-900',
    'normal' => 'border-l-slate-400 border-slate-200 bg-white text-slate-900',
    'low' => 'border-l-slate-300 border-slate-100 bg-slate-50 text-slate-800',
];
$priorityShort = ['critical' => 'Critique', 'high' => 'Élevée', 'normal' => 'Normale', 'low' => 'Faible'];

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

$renderBoardCard = static function (array $entry) use ($priorityClass, $priorityShort, $operationalLabels, $phaseLabels, $entryTypeLabels): void {
    $showAdminActions = false;
    require __DIR__ . '/board_card.php';
};

$sections = [
    ['key' => 'permanences', 'title' => 'Permanences', 'flash' => false],
    ['key' => 'infos', 'title' => 'Informations pratiques', 'flash' => false],
    ['key' => 'manifestations', 'title' => 'Manifestations', 'flash' => false],
    ['key' => 'activites', 'title' => 'Missions et activités', 'flash' => false],
    ['key' => 'flash', 'title' => 'Flash infos', 'flash' => true],
];
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/operational-board.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<div class="ops-board">
    <header class="ops-board__hero">
        <div class="ops-board__hero-inner">
            <div>
                <p class="ops-board__eyebrow">État-major · Diffusion</p>
                <h1 class="ops-board__title">Mur opérationnel</h1>
                <p class="ops-board__lead">
                    Permanences, consignes et activités publiées pour la période affichée.
                    <?php if ($boardCanEdit): ?>
                        <a href="<?= url('back-office/tableau-operationnel') ?>" class="font-semibold text-emerald-300 underline-offset-2 hover:underline">Ouvrir le pilotage</a>
                    <?php endif; ?>
                </p>
            </div>
            <div class="ops-board__hero-meta">
                <span class="ops-board__pill <?= htmlspecialchars($postureUi['pill'], ENT_QUOTES, 'UTF-8') ?>">
                    Posture <?= htmlspecialchars($postureUi['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="ops-board__pill">
                    <?= htmlspecialchars((string) ($boardFilters['period_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    →
                    <?= htmlspecialchars((string) ($boardFilters['period_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="ops-board__pill"><?= (int) $boardEntryCount ?> fiche<?= $boardEntryCount > 1 ? 's' : '' ?></span>
                <a href="<?= htmlspecialchars(url('documentation') . '#mur-operationnel', ENT_QUOTES, 'UTF-8') ?>" class="ops-board__pill hover:underline">Documentation</a>
            </div>
        </div>
    </header>

    <div class="ops-board__deck">
        <?php
        $boardHelpIsPilotage = false;
        require base_path('views/operations/partials/board_help.php');
        ?>
        <?php if (!$boardSchemaReady): ?>
            <div class="ops-board__empty" role="status">
                <p>Le mur n’est pas encore activé ici</p>
                <span>L’équipe d’hébergement doit finaliser l’installation. Actualisez la page une fois ce sera fait.</span>
                <div class="ops-board__actions" style="justify-content:center;margin-top:1rem">
                    <button type="button" class="ops-board__btn ops-board__btn--solid" onclick="location.reload()">Actualiser</button>
                </div>
            </div>
        <?php elseif ($boardEntryCount < 1): ?>
            <div class="ops-board__empty" role="status">
                <p>Aucune fiche publiée sur cette période</p>
                <span>Les permanences et consignes actives apparaîtront ici dès qu’elles seront mises en ligne par l’état-major.</span>
                <?php if ($boardCanEdit): ?>
                    <div class="ops-board__actions" style="justify-content:center;margin-top:1rem">
                        <a href="<?= url('back-office/tableau-operationnel') ?>" class="ops-board__btn ops-board__btn--solid">Publier une fiche</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($sections as $sec):
                $items = $boardPanels[$sec['key']] ?? [];
                $n = count($items);
                ?>
                <details class="ops-board__panel<?= !empty($sec['flash']) ? ' ops-board__flash-panel' : '' ?>" <?= $n > 0 ? 'open' : '' ?>>
                    <summary class="ops-board__panel-summary">
                        <h2><?= htmlspecialchars($sec['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <span class="ops-board__count"><?= $n ?></span>
                    </summary>
                    <div class="ops-board__panel-body">
                        <?php if ($n < 1): ?>
                            <p class="text-sm text-slate-500">Rien à afficher dans cette rubrique.</p>
                        <?php elseif ($sec['key'] === 'permanences'): ?>
                            <div class="ops-board__bucket-grid">
                                <?php foreach (['aujourdhui' => 'Aujourd’hui', 'en_cours' => 'En cours', 'a_venir' => 'À venir'] as $bk => $bl): ?>
                                    <div class="ops-board__bucket">
                                        <p class="ops-board__bucket-title"><?= htmlspecialchars($bl, ENT_QUOTES, 'UTF-8') ?></p>
                                        <div class="ops-board__stack">
                                            <?php
                                            $shown = 0;
                                            foreach ($items as $entry) {
                                                if ($temporalBucket($entry, $boardToday) !== $bk) {
                                                    continue;
                                                }
                                                $renderBoardCard($entry);
                                                $shown++;
                                            }
                                            if ($shown === 0) {
                                                echo '<p class="text-xs text-slate-400">—</p>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="ops-board__stack">
                                <?php foreach ($items as $entry) {
                                    if (in_array($temporalBucket($entry, $boardToday), ['aujourdhui', 'en_cours', 'a_venir'], true)) {
                                        continue;
                                    }
                                    $renderBoardCard($entry);
                                } ?>
                            </div>
                        <?php else: ?>
                            <div class="ops-board__stack">
                                <?php foreach ($items as $entry) {
                                    $renderBoardCard($entry);
                                } ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
