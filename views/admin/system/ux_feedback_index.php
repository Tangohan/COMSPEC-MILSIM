<?php
declare(strict_types=1);

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$schemaReady = !empty($uxFeedbackSchemaReady);
$aggregates = is_array($uxPageAggregates ?? null) ? $uxPageAggregates : [];
$recentRatings = is_array($uxRecentRatings ?? null) ? $uxRecentRatings : [];
$recentSurveys = is_array($uxRecentSurveys ?? null) ? $uxRecentSurveys : [];
$tenantFilter = isset($uxTenantFilter) ? (int) $uxTenantFilter : 0;
$tenantOptions = is_array($uxTenantOptions ?? null) ? $uxTenantOptions : [];

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

$filterUrl = static function (?int $tenantId) use ($h): string {
    if ($tenantId === null || $tenantId < 1) {
        return url('admin/system/retours-interface');
    }

    return url('admin/system/retours-interface') . '?tenant=' . $tenantId;
};
?>
<div class="max-w-6xl mx-auto px-4 py-8 space-y-8">
    <header class="space-y-2">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-amber-400/90">Administration site</p>
        <h1 class="text-2xl font-black tracking-tight text-white">Retours interface &amp; notations</h1>
        <p class="text-sm text-slate-400 max-w-3xl leading-relaxed">
            Notes rapides et questionnaires remontés via le widget « Retour UI » affiché dans le back-office des communautés.
            Vue transversale pour prioriser les améliorations produit.
        </p>
    </header>

    <?php if (!$schemaReady): ?>
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            Les tables de retour UI ne sont pas encore créées. Lancez <code class="rounded bg-black/30 px-1">php run-migrations.php</code> sur l’environnement.
        </div>
    <?php else: ?>

    <form method="get" action="<?= $h(url('admin/system/retours-interface')) ?>" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-800 bg-slate-900/60 p-4">
        <div>
            <label for="ux-tenant-filter" class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Communauté</label>
            <select id="ux-tenant-filter" name="tenant" class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 min-w-[220px]">
                <option value="">Toutes les communautés</option>
                <?php foreach ($tenantOptions as $opt): ?>
                <option value="<?= (int) ($opt['id'] ?? 0) ?>" <?= $tenantFilter === (int) ($opt['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= $h((string) ($opt['name'] ?? 'Communauté')) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-white">Filtrer</button>
        <?php if ($tenantFilter > 0): ?>
        <a href="<?= $h($filterUrl(null)) ?>" class="text-sm font-semibold text-slate-400 hover:text-white">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <section class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
        <h2 class="text-lg font-bold text-white">Notations par page (toutes communautés)</h2>
        <p class="text-sm text-slate-400 mt-1">Moyenne des notes rapides (1–5) agrégées par écran.</p>
        <?php if ($aggregates === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucune notation pour l’instant.</p>
        <?php else: ?>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="text-xs uppercase tracking-wide text-slate-500 border-b border-slate-800">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">Page</th>
                        <th class="py-2 pr-4 font-semibold">Votes</th>
                        <th class="py-2 pr-4 font-semibold">Moyenne</th>
                        <th class="py-2 font-semibold">Chemin</th>
                    </tr>
                </thead>
                <tbody class="text-slate-200">
                    <?php foreach ($aggregates as $row): ?>
                    <tr class="border-b border-slate-800/80">
                        <td class="py-2.5 pr-4"><?= $h((string) ($row['page_title'] ?? $row['page_key'] ?? '—')) ?></td>
                        <td class="py-2.5 pr-4 tabular-nums"><?= (int) ($row['votes'] ?? 0) ?></td>
                        <td class="py-2.5 pr-4 tabular-nums"><?= $h(number_format((float) ($row['avg_rating'] ?? 0), 1, ',', ' ')) ?> / 5</td>
                        <td class="py-2.5"><code class="text-xs text-slate-400"><?= $h((string) ($row['page_path'] ?? $row['page_key'] ?? '')) ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
        <h2 class="text-lg font-bold text-white">Derniers avis rapides</h2>
        <?php if ($recentRatings === []): ?>
            <p class="mt-4 text-sm text-slate-500">Aucun avis rapide enregistré.</p>
        <?php else: ?>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="text-xs uppercase tracking-wide text-slate-500 border-b border-slate-800">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">Date</th>
                        <th class="py-2 pr-4 font-semibold">Communauté</th>
                        <th class="py-2 pr-4 font-semibold">Page</th>
                        <th class="py-2 pr-4 font-semibold">Note</th>
                        <th class="py-2 pr-4 font-semibold">Auteur</th>
                        <th class="py-2 font-semibold">Commentaire</th>
                    </tr>
                </thead>
                <tbody class="text-slate-200">
                    <?php foreach ($recentRatings as $row): ?>
                    <tr class="border-b border-slate-800/80">
                        <td class="py-2.5 pr-4 whitespace-nowrap"><?= $h($formatDt((string) ($row['updated_at'] ?? $row['created_at'] ?? ''))) ?></td>
                        <td class="py-2.5 pr-4"><?= $h((string) ($row['tenant_name'] ?? '—')) ?></td>
                        <td class="py-2.5 pr-4"><?= $h((string) ($row['page_title'] ?? $row['page_key'] ?? '—')) ?></td>
                        <td class="py-2.5 pr-4 tabular-nums"><?= (int) ($row['rating'] ?? 0) ?>/5</td>
                        <td class="py-2.5 pr-4"><?= $h((string) ($row['author_name'] ?? '—')) ?></td>
                        <td class="py-2.5"><?= $h(trim((string) ($row['comment'] ?? '')) !== '' ? (string) $row['comment'] : '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="rounded-xl border border-slate-800 bg-slate-900/50 p-5">
        <h2 class="text-lg font-bold text-white">Questionnaires détaillés</h2>
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
            <article class="rounded-xl border border-slate-700/80 bg-slate-950/60 p-4 text-sm text-slate-200">
                <header class="flex flex-wrap items-baseline justify-between gap-2">
                    <strong class="text-white"><?= $h((string) ($row['page_title'] ?? $row['page_key'] ?? 'Page')) ?></strong>
                    <span class="text-xs text-slate-500">
                        <?= $h($formatDt((string) ($row['updated_at'] ?? $row['created_at'] ?? ''))) ?>
                        · <?= $h((string) ($row['tenant_name'] ?? '—')) ?>
                        · <?= $h((string) ($row['author_name'] ?? '—')) ?>
                    </span>
                </header>
                <p class="mt-2 text-slate-300">
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
                    <li class="rounded-full bg-slate-900 px-2.5 py-1 text-xs font-semibold text-slate-300 ring-1 ring-slate-700"><?= $h($label) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if (trim((string) ($row['improvement_text'] ?? '')) !== ''): ?>
                <p class="mt-3 text-slate-100"><?= nl2br($h((string) $row['improvement_text'])) ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
