<?php
declare(strict_types=1);
/**
 * Onglet Diagnostic : lecture seule. Aucun secret, jeton ni adresse e-mail (tout texte libre passe par
 * TenantEditRules::redact). Le rapport copiable est construit à partir des mêmes données que l'écran.
 *
 * @var array<string, mixed> $platformDiag
 */
$d = is_array($platformDiag ?? null) ? $platformDiag : [];
$mig = is_array($d['migrations'] ?? null) ? $d['migrations'] : null;
$ints = is_array($d['integrations'] ?? null) ? $d['integrations'] : [];
$auditRows = is_array($d['audit'] ?? null) ? $d['audit'] : [];
$mailRows = is_array($d['mails'] ?? null) ? $d['mails'] : [];
$cronRows = is_array($d['cron_errors'] ?? null) ? $d['cron_errors'] : [];
$linkRows = is_array($d['link'] ?? null) ? $d['link'] : [];
$sumCounts = is_array($platformMemberCounts ?? null) ? $platformMemberCounts : [];
$limitsAtteintes = [];
foreach ((is_array($platformDiagLimits ?? null) ? $platformDiagLimits : []) as $l) {
    if (!empty($l['reached'])) {
        $limitsAtteintes[] = (string) $l['key'];
    }
}
$stateWord = ['ok' => 'présente', 'missing' => 'manquante', 'na' => 'non disponible'];

$report = [
    'title' => 'Diagnostic communauté n° ' . $tenantId . ' (' . $slug . ')',
    'generated' => date('d/m/Y H:i'),
    'sections' => [
        ['title' => 'Identité', 'lines' => [
            'Type : ' . $currentTypeLabel,
            'Formule : ' . $currentPlanLabel . ' (base : ' . $basePlanLabel . '), abonnement : ' . (string) ($statusLabels[$currentStatus] ?? $currentStatus),
            'Statut : ' . ($archived ? 'archivée' : 'active'),
            'Comptes : ' . (int) ($sumCounts['all'] ?? 0) . ' au total, ' . (int) ($sumCounts['active'] ?? 0) . ' actifs, ' . (int) ($sumCounts['never'] ?? 0) . ' jamais connectés',
        ]],
        ['title' => 'Configuration', 'lines' => array_merge(
            [$mig === null ? 'Migrations : état inconnu' : 'Migrations en attente : ' . (int) $mig['pending'] . ' dont ' . (int) $mig['failed'] . ' en échec'],
            array_map(static fn (array $i): string => $i['label'] . ' : ' . ($stateWord[$i['state']] ?? '?') . ' (' . $i['detail'] . ')', $ints)
        )],
        ['title' => 'Liaison jeu et terminaux', 'lines' => array_map(static fn (array $r): string => $r['label'] . ' : ' . $r['value'], $linkRows) ?: ['Aucune donnée']],
        ['title' => 'Derniers événements d’audit', 'lines' => array_map(static fn (array $r): string => $r['at'] . ' ' . $r['label'], $auditRows) ?: ['Aucun']],
        ['title' => 'Derniers e-mails', 'lines' => array_map(static fn (array $r): string => $r['at'] . ' ' . $r['event'] . ' ' . $r['status'] . ($r['error'] !== '' ? ' : ' . $r['error'] : ''), $mailRows) ?: ['Aucun']],
        ['title' => 'Dernières erreurs de tâches planifiées (plateforme)', 'lines' => array_map(static fn (array $r): string => $r['at'] . ' ' . $r['job'], $cronRows) ?: ['Aucune']],
        ['title' => 'Limites atteintes', 'lines' => $limitsAtteintes ?: ['Aucune']],
    ],
];
$reportText = \App\Services\Admin\TenantEditRules::reportText($report);
?>
<section class="pa-card">
    <p class="pa-card__kicker">Rapport</p>
    <h2 class="pa-card__title">Rapport de diagnostic</h2>
    <p class="pa-card__help">Texte sans secret ni adresse e-mail, prêt à coller dans un ticket.</p>
    <textarea id="pa-diag-report" class="pa-diag__report" readonly rows="10" aria-label="Rapport de diagnostic"><?= $h($reportText) ?></textarea>
    <div class="pa-actions">
        <button type="button" class="pa-btn pa-btn--ink" data-pa-copy-report data-done="Copié">Copier le rapport de diagnostic</button>
    </div>
</section>

<section class="pa-card">
    <p class="pa-card__kicker">Voir comme cette communauté</p>
    <h2 class="pa-card__title">Aperçu sans élévation de droits</h2>
    <p class="pa-card__help">L’aperçu ne change ni vos droits, ni les données : lecture seule.</p>
    <div class="pa-actions">
        <a class="pa-btn pa-btn--line" href="<?= $h((string) ($platformInterventionUrl ?? '#')) ?>">Ouvrir l’espace d’intervention (journalisé)</a>
    </div>
    <?php if (!empty($platformPreviewPossible)): ?>
        <?php $viewTypes = \App\Services\Platform\EffectiveTenantType::previewable($currentType); ?>
        <form method="post" action="<?= $h(url('admin/apercu-formule/vue')) ?>" class="pa-diag__preview">
            <?= \App\Core\Csrf::field() ?>
            <div class="pa-field">
                <label for="view_type">Aperçu de la vue</label>
                <select id="view_type" name="view_type">
                    <?php foreach ($viewTypes as $vt): ?>
                        <option value="<?= $h($vt) ?>"><?= $h(\App\Services\Community\TenantTypeConfig::label($vt)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pa-actions"><button type="submit" class="pa-btn pa-btn--line">Voir cette vue</button></div>
        </form>
    <?php else: ?>
        <p class="pa-card__help">Les aperçus de formule et de vue s’appliquent à la communauté de votre session ; pour celle-ci, passez par l’espace d’intervention.</p>
    <?php endif; ?>
</section>

<section class="pa-card">
    <p class="pa-card__kicker">Configuration</p>
    <h2 class="pa-card__title">Migrations et intégrations</h2>
    <p class="pa-card__help">
        <?php if ($mig === null): ?>
            État des migrations inconnu.
        <?php else: ?>
            Migrations en attente sur la plateforme : <strong><?= (int) $mig['pending'] ?></strong>, dont <?= (int) $mig['failed'] ?> en échec.
        <?php endif; ?>
    </p>
    <div class="pa-sheet__scroll">
        <table class="pa-sheet__grid">
            <thead><tr><th>Intégration</th><th>État</th><th>Détail</th></tr></thead>
            <tbody>
                <?php foreach ($ints as $it): ?>
                    <tr>
                        <td><?= $h((string) $it['label']) ?></td>
                        <td><span class="pa-pill pa-pill--<?= $it['state'] === 'ok' ? 'mint' : ($it['state'] === 'missing' ? 'amber' : 'slate') ?>"><?= $h(ucfirst($stateWord[$it['state']] ?? '?')) ?></span></td>
                        <td><?= $h((string) $it['detail']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="pa-card">
    <p class="pa-card__kicker">Liaison</p>
    <h2 class="pa-card__title">Jeu, Overwatch et terminaux ATAK</h2>
    <?php if ($linkRows === []): ?>
        <p class="pa-card__help">Aucune donnée de liaison pour cette communauté.</p>
    <?php else: ?>
        <dl class="pa-stats">
            <?php foreach ($linkRows as $lr): ?>
                <div class="pa-stat"><dt><?= $h((string) $lr['label']) ?></dt><dd style="font-size:1rem;"><?= $h($lr['value'] === 'jamais' ? 'Jamais' : (ctype_digit((string) $lr['value']) ? (string) $lr['value'] : $fmtDate($lr['value']))) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
</section>

<section class="pa-card">
    <p class="pa-card__kicker">Journaux</p>
    <h2 class="pa-card__title">Derniers événements, e-mails et erreurs</h2>
    <h3 class="pa-diag__h">Audit</h3>
    <?php if ($auditRows === []): ?><p class="pa-card__help">Aucun événement.</p><?php else: ?>
        <ul class="pa-diag__list">
            <?php foreach ($auditRows as $r): ?><li><?= $h($fmtDate($r['at'])) ?> · <?= $h((string) $r['label']) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <h3 class="pa-diag__h">E-mails</h3>
    <?php if ($mailRows === []): ?><p class="pa-card__help">Aucun e-mail enregistré.</p><?php else: ?>
        <ul class="pa-diag__list">
            <?php foreach ($mailRows as $r): ?>
                <li><?= $h($fmtDate($r['at'])) ?> · <?= $h($r['event']) ?> · <span class="pa-pill pa-pill--<?= $r['status'] === 'failed' ? 'rose' : 'mint' ?>"><?= $h($r['status']) ?></span><?= $r['error'] !== '' ? ' · ' . $h($r['error']) : '' ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <h3 class="pa-diag__h">Tâches planifiées en erreur (plateforme)</h3>
    <?php if ($cronRows === []): ?><p class="pa-card__help">Aucune erreur récente.</p><?php else: ?>
        <ul class="pa-diag__list">
            <?php foreach ($cronRows as $r): ?><li><?= $h($fmtDate($r['at'])) ?> · <?= $h($r['job']) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<script src="<?= $h(asset_url('assets/js/platform-tenant-diagnostic.js')) ?>"></script>
