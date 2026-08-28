<?php
declare(strict_types=1);

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$schemaReady = !empty($uxFeedbackSchemaReady);
$aggregates = is_array($uxPageAggregates ?? null) ? $uxPageAggregates : [];
$recentRatings = is_array($uxRecentRatings ?? null) ? $uxRecentRatings : [];
$recentSurveys = is_array($uxRecentSurveys ?? null) ? $uxRecentSurveys : [];

$issueLabels = [
    'navigation' => 'Navigation confuse',
    'labels' => 'Libellés peu clairs',
    'performance' => 'Lenteur / chargement',
    'mobile' => 'Affichage mobile',
    'accessibility' => 'Accessibilité',
    'missing_info' => 'Information manquante',
    'workflow' => 'Parcours trop long',
    'visual_noise' => 'Interface chargée',
];

$formatDt = static function (?string $sql): string {
    if ($sql === null || trim($sql) === '') {
        return '—';
    }
    $t = strtotime($sql);

    return $t ? date('d/m/Y H:i', $t) : '—';
};
?>
<div class="ux-feedback-admin ath-rise space-y-6">
    <?php if (!$schemaReady): ?>
        <?php
        $notice_tone = 'warning';
        $notice_title = 'Migration en attente';
        $notice_body = 'Les tables de retour UI ne sont pas encore créées. Lancez <code>php run-migrations.php</code> sur l’environnement.';
        include base_path('views/partials/bo_dsfr_notice.php');
        ?>
    <?php else: ?>

    <section class="ath-card ath-rise" style="padding:16px 18px;">
        <h2 class="ath-section-title">Notations par page</h2>
        <p class="text-sm text-slate-600 mt-1">Moyenne des notes rapides (1–5) remontées via le widget flottant du back-office.</p>
        <?php if ($aggregates === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucune notation pour l’instant.</p>
        <?php else: ?>
        <div class="ath-table-wrap mt-4">
            <table class="ath-table" style="min-width:720px">
                <thead>
                    <tr>
                        <th scope="col">Page</th>
                        <th scope="col" data-ath-num="1">Votes</th>
                        <th scope="col" data-ath-num="1">Moyenne</th>
                        <th scope="col">Chemin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aggregates as $row): ?>
                    <tr class="ath-row">
                        <td><?= $h((string) ($row['page_title'] ?? $row['page_key'] ?? '—')) ?></td>
                        <td><?= (int) ($row['votes'] ?? 0) ?></td>
                        <td><?= $h(number_format((float) ($row['avg_rating'] ?? 0), 1, ',', ' ')) ?> / 5</td>
                        <td><code><?= $h((string) ($row['page_path'] ?? $row['page_key'] ?? '')) ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="ath-card ath-rise" style="padding:16px 18px;">
        <h2 class="ath-section-title">Derniers avis rapides</h2>
        <?php if ($recentRatings === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucun avis rapide enregistré.</p>
        <?php else: ?>
        <div class="ath-table-wrap mt-4">
            <table class="ath-table" style="min-width:860px">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Page</th>
                        <th scope="col" data-ath-num="1">Note</th>
                        <th scope="col">Auteur</th>
                        <th scope="col">Commentaire</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRatings as $row): ?>
                    <tr class="ath-row">
                        <td><?= $h($formatDt((string) ($row['updated_at'] ?? $row['created_at'] ?? ''))) ?></td>
                        <td><?= $h((string) ($row['page_title'] ?? $row['page_key'] ?? '—')) ?></td>
                        <td><?= (int) ($row['rating'] ?? 0) ?>/5</td>
                        <td><?= $h((string) ($row['author_name'] ?? '—')) ?></td>
                        <td><?= $h(trim((string) ($row['comment'] ?? '')) !== '' ? (string) $row['comment'] : '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="ath-card ath-rise" style="padding:16px 18px;">
        <h2 class="ath-section-title">Questionnaires détaillés</h2>
        <?php if ($recentSurveys === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucun questionnaire rempli.</p>
        <?php else: ?>
        <div class="space-y-4 mt-4">
            <?php foreach ($recentSurveys as $row):
                $issues = [];
                $rawIssues = $row['issues_json'] ?? null;
                if (is_string($rawIssues) && $rawIssues !== '') {
                    $decoded = json_decode($rawIssues, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $slug) {
                            $issues[] = $issueLabels[(string) $slug] ?? (string) $slug;
                        }
                    }
                }
                ?>
            <article class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 text-sm">
                <header class="flex flex-wrap items-baseline justify-between gap-2">
                    <strong><?= $h((string) ($row['page_title'] ?? $row['page_key'] ?? 'Page')) ?></strong>
                    <span class="text-xs text-slate-500"><?= $h($formatDt((string) ($row['updated_at'] ?? $row['created_at'] ?? ''))) ?> · <?= $h((string) ($row['author_name'] ?? '—')) ?></span>
                </header>
                <p class="mt-2 text-slate-700">
                    Facilité <?= (int) ($row['ease_rating'] ?? 0) ?>/5 ·
                    Clarté <?= (int) ($row['clarity_rating'] ?? 0) ?>/5 ·
                    Design <?= (int) ($row['design_rating'] ?? 0) ?>/5 ·
                    Utilité <?= (int) ($row['usefulness_rating'] ?? 0) ?>/5
                    <?php if ($row['would_recommend'] !== null): ?>
                        · Recommandation : <?= !empty($row['would_recommend']) ? 'Oui' : 'Non' ?>
                    <?php endif; ?>
                </p>
                <?php if ($issues !== []): ?>
                <p class="mt-2 text-xs uppercase tracking-wide text-slate-500">Points signalés</p>
                <ul class="mt-1 flex flex-wrap gap-2">
                    <?php foreach ($issues as $label): ?>
                    <li class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200"><?= $h($label) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if (trim((string) ($row['improvement_text'] ?? '')) !== ''): ?>
                <p class="mt-3 text-slate-800"><?= nl2br($h((string) $row['improvement_text'])) ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
