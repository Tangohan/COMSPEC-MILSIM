<?php
declare(strict_types=1);

$baseUrl = url('');
$title = $title ?? 'Mur opérationnel';
$boardTenantName = trim((string) ($boardTenantName ?? 'Communauté'));
$boardPanels = array_merge([
    'permanences' => [],
    'infos' => [],
    'manifestations' => [],
    'flash' => [],
    'activites' => [],
], $boardPanels ?? []);
$boardPosture = $boardPosture ?? ['posture_level' => 'NORMAL'];
$boardToday = $boardToday ?? date('Y-m-d');
$boardEntryCount = (int) ($boardEntryCount ?? 0);
$boardFilters = $boardFilters ?? [];
$boardAuthors = $boardAuthors ?? [];
$boardPresent = $boardPresent ?? [];
$boardViewerLabel = isset($boardViewerLabel) ? trim((string) $boardViewerLabel) : '';

$posture = (string) ($boardPosture['posture_level'] ?? 'NORMAL');
$postureLabels = [
    'NORMAL' => 'Normale',
    'VIGILANCE' => 'Vigilance',
    'ALERTE' => 'Alerte',
    'CRISE' => 'Crise',
];
$postureLabel = $postureLabels[$posture] ?? 'Normale';
$posturePill = match ($posture) {
    'VIGILANCE', 'ALERTE' => 'ops-pub__pill--warn',
    'CRISE' => 'ops-pub__pill--danger',
    default => 'ops-pub__pill--ok',
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
$phaseLabels = ['phase_1' => 'Phase 1', 'phase_2' => 'Phase 2', 'phase_3' => 'Phase 3'];
$priorityClass = [
    'critical' => 'border-l-rose-500 border-rose-200 bg-white text-slate-900',
    'high' => 'border-l-orange-400 border-orange-100 bg-white text-slate-900',
    'normal' => 'border-l-slate-400 border-slate-200 bg-white text-slate-900',
    'low' => 'border-l-slate-300 border-slate-100 bg-slate-50 text-slate-800',
];
$priorityShort = ['critical' => 'Critique', 'high' => 'Élevée', 'normal' => 'Normale', 'low' => 'Faible'];

$renderBoardCard = static function (array $entry) use ($priorityClass, $priorityShort, $operationalLabels, $phaseLabels, $entryTypeLabels): void {
    $showAdminActions = false;
    require __DIR__ . '/board_card.php';
};

$sections = [
    ['key' => 'flash', 'title' => 'Flash infos', 'flash' => true],
    ['key' => 'permanences', 'title' => 'Permanences', 'flash' => false],
    ['key' => 'infos', 'title' => 'Informations pratiques', 'flash' => false],
    ['key' => 'manifestations', 'title' => 'Manifestations', 'flash' => false],
    ['key' => 'activites', 'title' => 'Missions et activités', 'flash' => false],
];

$periodStart = (string) ($boardFilters['period_start'] ?? '');
$periodEnd = (string) ($boardFilters['period_end'] ?? '');
$viewerText = $boardViewerLabel !== ''
    ? 'Ouvert par ' . $boardViewerLabel
    : 'Consultation anonyme — vous n’êtes pas connecté à Athéna';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($boardTenantName, ENT_QUOTES, 'UTF-8') ?> · Athéna</title>
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/tailwind.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link href="<?= htmlspecialchars(asset_url('assets/css/halo-loader.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <link href="<?= htmlspecialchars(asset_url('assets/css/operational-board.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
</head>
<body class="ops-pub">
<?php
$haloLoaderHint = 'Athéna · Lecture seule';
require base_path('views/partials/halo_loader.php');
?>
<p class="ops-pub__loader-note" id="ops-pub-loader-note" aria-hidden="true">Page en lecture seule · Module Athéna</p>
<script>
(function () {
  var note = document.getElementById('ops-pub-loader-note');
  var root = document.getElementById('halo-loader');
  if (note && root) {
    root.appendChild(note);
    note.removeAttribute('aria-hidden');
  }
})();
</script>

<header class="ops-pub__top">
    <div class="ops-pub__top-inner">
        <p class="ops-pub__brand">Athéna</p>
        <p class="ops-pub__readonly">Lecture seule</p>
    </div>
</header>

<main class="ops-pub__main">
    <section class="ops-pub__hero">
        <p class="ops-pub__eyebrow">Mur opérationnel</p>
        <h1 class="ops-pub__title"><?= htmlspecialchars($boardTenantName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="ops-pub__lead">Situation publiée pour la période affichée. Cette page ne permet aucune modification.</p>
        <div class="ops-pub__meta">
            <span class="ops-pub__pill <?= htmlspecialchars($posturePill, ENT_QUOTES, 'UTF-8') ?>">Posture <?= htmlspecialchars($postureLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="ops-pub__pill"><?= (int) $boardEntryCount ?> fiche<?= $boardEntryCount > 1 ? 's' : '' ?></span>
            <?php if ($periodStart !== '' && $periodEnd !== ''): ?>
                <span class="ops-pub__pill"><?= htmlspecialchars($periodStart, ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($periodEnd, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
    </section>

    <aside class="ops-pub__banner" role="status">
        <div>
            <p class="ops-pub__banner-kicker">Qui consulte</p>
            <p class="ops-pub__banner-text"><?= htmlspecialchars($viewerText, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div>
            <p class="ops-pub__banner-kicker">Rédacteurs</p>
            <p class="ops-pub__banner-text">
                <?php if ($boardAuthors === []): ?>
                    Aucun rédacteur identifié sur les fiches affichées.
                <?php else: ?>
                    <?= htmlspecialchars(implode(' · ', $boardAuthors), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <p class="ops-pub__banner-kicker">Présents sur le tableau</p>
            <p class="ops-pub__banner-text">
                <?php if ($boardPresent === []): ?>
                    Aucune identité nominative sur les fiches affichées.
                <?php else: ?>
                    <?= htmlspecialchars(implode(' · ', $boardPresent), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </p>
        </div>
    </aside>

    <section class="ops-pub__sheet" aria-label="Feuille opérationnelle">
        <header class="ops-pub__sheet-head">
            <h2>Feuille du jour</h2>
            <p>Contenu publié · mise à jour selon le pilotage de la communauté</p>
        </header>

        <?php if ($boardEntryCount < 1): ?>
            <div class="ops-board__empty" role="status">
                <p>Aucune fiche publiée sur cette période</p>
                <span>Dès que l’état-major mettra des fiches en ligne, elles apparaîtront ici.</span>
            </div>
        <?php else: ?>
            <div class="ops-pub__columns">
                <?php foreach ($sections as $sec):
                    $items = $boardPanels[$sec['key']] ?? [];
                    $n = count($items);
                    if ($n < 1) {
                        continue;
                    }
                    ?>
                    <details class="ops-board__panel<?= !empty($sec['flash']) ? ' ops-board__flash-panel' : '' ?>" open>
                        <summary class="ops-board__panel-summary">
                            <h2><?= htmlspecialchars($sec['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <span class="ops-board__count"><?= $n ?></span>
                        </summary>
                        <div class="ops-board__panel-body ops-board__stack">
                            <?php foreach ($items as $entry) {
                                $renderBoardCard($entry);
                            } ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="ops-pub__foot">
    <p>Athéna · Mur opérationnel en lecture seule</p>
</footer>
</body>
</html>
