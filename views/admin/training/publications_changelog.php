<?php
declare(strict_types=1);
$publication = is_array($publication ?? null) ? $publication : [];
$revisions = is_array($revisions ?? null) ? $revisions : [];
$pubId = (int) ($publication['id'] ?? 0);
$courseTitle = trim((string) ($publication['course_title'] ?? ''));
if ($courseTitle === '') {
    $courseTitle = 'Parcours n°' . (int) ($publication['course_id'] ?? 0);
}

$revisionDate = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $t = strtotime($raw);

    return $t ? date('d/m/Y, H:i', $t) : '—';
};

$prettyJson = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '{}';
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: $raw;
    }

    return $raw;
};
?>
<section class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Pilotage des formations</p>
    <h1 class="tc-hero-title mb-2">Historique des versions</h1>
    <p class="text-sm text-slate-600 mb-4">
        Publication n°<?= $pubId ?> · <span class="font-medium text-slate-800"><?= htmlspecialchars($courseTitle) ?></span>
        <?php if (!empty($publication['version_label'])): ?>
            · version affichée&nbsp;: <span class="font-mono text-xs"><?= htmlspecialchars((string) $publication['version_label']) ?></span>
        <?php endif; ?>
    </p>
    <p class="text-sm text-slate-600 max-w-3xl leading-relaxed mb-4">
        Chaque bloc ci-dessous correspond à une <strong class="font-semibold text-slate-800">révision enregistrée</strong> (numéro, date, résumé saisi par l’équipe). Les données structurées en bas de carte sont conservées pour audit et traçabilité (comparaison de contenus, filigranes, cibles de diffusion, etc.).
    </p>
    <p>
        <a href="<?= htmlspecialchars(training_lms_admin_url('publications')) ?>" class="text-sm font-semibold text-emerald-700 hover:underline">← Retour à la liste des publications</a>
    </p>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-6 mt-6 space-y-4">
    <?php foreach ($revisions as $revision): ?>
        <?php
        $revNum = (int) ($revision['revision_number'] ?? 0);
        $summary = trim((string) ($revision['change_summary'] ?? ''));
        if ($summary === '') {
            $summary = 'Aucun libellé de changement pour cette révision.';
        }
        $diffRaw = (string) ($revision['diff_payload_json'] ?? '{}');
        $diffPretty = $prettyJson($diffRaw);
        ?>
        <article class="rounded-xl border border-slate-200 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-black text-slate-900">Révision n°<?= $revNum ?></h2>
                <span class="text-xs text-slate-500"><?= htmlspecialchars($revisionDate($revision['created_at'] ?? null)) ?></span>
            </div>
            <p class="text-sm text-slate-700 mt-2 leading-relaxed"><?= htmlspecialchars($summary) ?></p>
            <details class="mt-4 group">
                <summary class="cursor-pointer text-xs font-bold uppercase tracking-wide text-slate-500 hover:text-emerald-700">Voir les données enregistrées pour cette révision</summary>
                <pre class="mt-3 text-xs bg-slate-50 rounded-lg p-3 overflow-auto max-h-80 border border-slate-100"><?= htmlspecialchars($diffPretty) ?></pre>
            </details>
        </article>
    <?php endforeach; ?>
    <?php if ($revisions === []): ?>
        <p class="text-slate-600 py-4">Aucune révision n’a encore été enregistrée pour cette publication. Le journal se remplira lors des prochaines étapes (relecture, validation ou republication).</p>
    <?php endif; ?>
</section>
