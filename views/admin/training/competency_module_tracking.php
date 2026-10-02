<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<?php
/**
 * Pilotage — suivi d’un module compétences : statut de chaque membre, validation groupée.
 * @var array<string, mixed> $competencyModule
 * @var list<string> $competencyPrereqNames
 * @var list<array<string, mixed>> $competencyTracking
 * @var string $competencyStatusFilter
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$module = (array) ($competencyModule ?? []);
$rows = is_array($competencyTracking ?? null) ? $competencyTracking : [];
$prereqNames = is_array($competencyPrereqNames ?? null) ? $competencyPrereqNames : [];
$filter = (string) ($competencyStatusFilter ?? '');
$labels = \App\Services\Training\CompetencyModuleService::STATUS_LABELS;
$phases = \App\Services\Training\CompetencyModuleService::PHASE_LABELS;
$phase = (string) ($module['module_type'] ?? 'ALPHA');
$moduleId = (int) ($module['id'] ?? 0);
$base = training_lms_admin_url('competences/modules/' . $moduleId . '/suivi');
$error = \App\Core\Session::getFlash('error');
$success = \App\Core\Session::getFlash('success');

$counts = array_fill_keys(array_keys($labels), 0);
foreach ($rows as $r) {
    $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
}
$visible = $filter === '' ? $rows : array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === $filter));
$total = count($rows);
$done = (int) $counts['COMPLETED'];
$pct = $total > 0 ? (int) round($done / $total * 100) : 0;
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/competency.css')) ?>">

<div class="cp cp-phase--<?= $h($phase) ?>">
    <p><a class="cp-link" href="<?= $h(training_lms_admin_url('competences/modules') . '#module-' . $moduleId) ?>">← Tous les modules</a></p>

    <header class="cp-head" style="border-top-color: var(--cp-phase)">
        <div>
            <p class="cp-kicker" style="color: var(--cp-phase)"><?= $h($phases[$phase]['label'] ?? $phase) ?> · <?= $h($phases[$phase]['subtitle'] ?? '') ?> · <?= $h((string) ($module['code'] ?? '')) ?></p>
            <h1 class="cp-title"><?= $h((string) ($module['name'] ?? 'Module')) ?></h1>
            <p class="cp-lead">
                <?= $module['recurrence_days'] !== null
                    ? 'Une validation expire après ' . (int) $module['recurrence_days'] . ' jours : le membre repasse alors « à renouveler ».'
                    : 'Une validation n’expire pas.' ?>
                <?php if ($prereqNames !== []): ?>
                    Prérequis : <b><?= $h(implode(', ', $prereqNames)) ?></b>.
                <?php endif; ?>
            </p>
        </div>
        <div style="min-width: 240px">
            <div class="cp-progress">
                <span class="cp-bar" style="--cp-phase: var(--cp-ok)"><span style="width: <?= $pct ?>%"></span></span>
                <span class="cp-progress__num"><?= $done ?>/<?= $total ?></span>
            </div>
            <p class="cp-help" style="margin:0.3rem 0 0">membres ont validé ce module</p>
        </div>
    </header>

    <?php if ($error): ?><div class="cp-flash cp-flash--err" role="alert"><?= $h((string) $error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="cp-flash" role="status"><?= $h((string) $success) ?></div><?php endif; ?>

    <nav class="cp-filters" aria-label="Filtrer par statut">
        <a class="cp-filter<?= $filter === '' ? ' is-active' : '' ?>" href="<?= $h($base) ?>"<?= $filter === '' ? ' aria-current="true"' : '' ?>>Tous <span class="cp-count"><?= $total ?></span></a>
        <?php foreach (['EXPIRED', 'IN_PROGRESS', 'FAILED', 'NOT_STARTED', 'COMPLETED'] as $st): ?>
            <a class="cp-filter<?= $filter === $st ? ' is-active' : '' ?>" href="<?= $h($base . '?statut=' . $st) ?>"<?= $filter === $st ? ' aria-current="true"' : '' ?>><?= $h($labels[$st]) ?> <span class="cp-count"><?= (int) $counts[$st] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($rows === []): ?>
        <div class="cp-empty"><p><strong>Aucun membre actif dans l’organisation.</strong></p></div>
    <?php elseif ($visible === []): ?>
        <div class="cp-empty"><p>Aucun membre avec ce statut.</p></div>
    <?php else: ?>
        <form method="post" action="<?= $h($base) ?>" class="cp-section" data-cp-bulk>
            <?= \App\Core\Csrf::field() ?>
            <div class="cp-bulk">
                <span class="cp-bulk__count" data-cp-count>Cochez des membres</span>
                <label class="sr-only" for="cp-status">Nouveau statut</label>
                <select id="cp-status" name="status" class="cp-input" required>
                    <option value="COMPLETED">Valider le module</option>
                    <option value="IN_PROGRESS">Passer en cours</option>
                    <option value="FAILED">Non validé</option>
                    <option value="EXPIRED">À renouveler</option>
                    <option value="NOT_STARTED">Remettre à zéro</option>
                </select>
                <button type="submit" class="cp-btn cp-btn--primary" data-cp-apply disabled>Appliquer</button>
                <span class="cp-help">Valider enregistre votre nom et la date<?= $module['recurrence_days'] !== null ? ', et fixe l’échéance' : '' ?>.</span>
            </div>
            <div class="cp-table-wrap">
                <table class="cp-table">
                    <thead>
                        <tr>
                            <th scope="col"><label class="sr-only" for="cp-all">Tout cocher</label><input id="cp-all" type="checkbox" data-cp-all></th>
                            <th scope="col">Membre</th>
                            <th scope="col">Statut</th>
                            <th scope="col">Validé le</th>
                            <th scope="col">Par</th>
                            <th scope="col">Échéance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visible as $r): ?>
                            <tr>
                                <td><label class="sr-only" for="cp-u<?= (int) $r['user_id'] ?>">Sélectionner <?= $h($r['name']) ?></label><input id="cp-u<?= (int) $r['user_id'] ?>" type="checkbox" name="user_ids[]" value="<?= (int) $r['user_id'] ?>" data-cp-row></td>
                                <td class="cp-table__who">
                                    <strong><?= $h($r['name']) ?></strong>
                                    <?php if ($r['sub'] !== ''): ?><span><?= $h($r['sub']) ?></span><?php endif; ?>
                                    <?php if ((int) $r['missing_prereqs'] > 0): ?>
                                        <span class="cp-warn"><?= (int) $r['missing_prereqs'] ?> prérequis non validé<?= (int) $r['missing_prereqs'] > 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="cp-status cp-status--<?= $h($r['status']) ?>"><?= $h($r['status_label']) ?></span></td>
                                <td class="cp-mono"><?= $h($r['validated_at'] !== '' ? $r['validated_at'] : '—') ?></td>
                                <td><?= $h($r['validator'] !== '' ? $r['validator'] : '—') ?></td>
                                <td class="cp-mono"><?= $h($r['expires_at'] !== '' ? $r['expires_at'] : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
        <script>
        (function () {
            var form = document.querySelector('[data-cp-bulk]');
            if (!form) { return; }
            var all = form.querySelector('[data-cp-all]');
            var rows = Array.prototype.slice.call(form.querySelectorAll('[data-cp-row]'));
            var count = form.querySelector('[data-cp-count]');
            var apply = form.querySelector('[data-cp-apply]');
            function refresh() {
                var n = rows.filter(function (r) { return r.checked; }).length;
                count.textContent = n === 0 ? 'Cochez des membres' : (n === 1 ? '1 membre sélectionné' : n + ' membres sélectionnés');
                apply.disabled = n === 0;
                all.checked = n > 0 && n === rows.length;
                all.indeterminate = n > 0 && n < rows.length;
            }
            all.addEventListener('change', function () { rows.forEach(function (r) { r.checked = all.checked; }); refresh(); });
            rows.forEach(function (r) { r.addEventListener('change', refresh); });
            form.addEventListener('submit', function () { apply.textContent = 'Enregistrement…'; window.setTimeout(function () { apply.disabled = true; }, 0); });
            refresh();
        })();
        </script>
    <?php endif; ?>
</div>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
