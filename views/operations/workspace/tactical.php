<?php
declare(strict_types=1);
/** @var array<string, mixed> $tactical */
$tactical = $tactical ?? [];
$op = $tactical['operation'] ?? [];
$objects = $tactical['objects'] ?? [];
$tasks = $tactical['tasks'] ?? [];
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$uuid = (string) ($op['uuid'] ?? '');
$live = (($op['status'] ?? '') === 'active');
?>
<div class="ops-tac">
    <header class="ops-tac__bar">
        <div>
            <strong><?= $h($op['code'] ?? '') ?></strong>
            <span class="ops-tac__live <?= $live ? 'is-live' : '' ?>"><?= $live ? 'En cours' : $h($op['status_label'] ?? '') ?></span>
            <p><?= $h($op['phase_label'] ?? '') ?></p>
        </div>
        <a href="<?= $h(url('operations/' . $uuid)) ?>" class="ops-tac__menu" aria-label="Espace opérationnel">☰</a>
    </header>
    <div class="ops-tac__map">
        <svg viewBox="0 0 1000 1000" role="img" aria-label="Vue terrain">
            <rect width="1000" height="1000" fill="#071018"/>
            <g id="tac-objects"></g>
        </svg>
    </div>
    <nav class="ops-tac__dock">
        <button type="button" class="is-active" data-pane="map">Carte</button>
        <button type="button" data-pane="tasks">Tâches</button>
        <button type="button" data-pane="intel">Renseignement</button>
        <a href="<?= $h(url('operations/' . $uuid . '?tab=orders')) ?>">Ordres</a>
    </nav>
    <aside class="ops-tac__pane" id="tac-pane" hidden>
        <div data-pane-body="tasks">
            <h2>Tâches</h2>
            <?php if ($tasks === []): ?>
            <p>Aucune tâche publiée pour cette phase.</p>
            <?php else: ?>
            <ul>
                <?php foreach ($tasks as $task): ?>
                <li>
                    <strong><?= $h(\App\Support\OperationLabels::taskStatus((string) ($task['status'] ?? ''))) ?></strong>
                    <?= $h($task['title'] ?? '') ?>
                    <span><?= $h($task['assigned_element'] ?? '') ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <div data-pane-body="intel" hidden>
            <h2>Renseignement</h2>
            <p>Les produits publiés pour cette opération apparaîtront ici. Le travail d’analyse reste dans l’espace renseignement.</p>
        </div>
    </aside>
</div>
<script>
window.OPS_TACTICAL = <?= $tacticalJson ?? '{}' ?>;
</script>
<script src="<?= htmlspecialchars(asset_url('assets/js/ops-workspace-planning.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
