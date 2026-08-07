<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $artifact */
/** @var array<string,string> $statuses */
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/artefacts')) ?>">Artefacts</a> / <strong><?= $h($artifact['name'] ?? '') ?></strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline"><?= $h($artifact['category_label'] ?? '') ?> // <?= $h($artifact['status_label'] ?? '') ?></div>
        <h1><?= $h($artifact['name'] ?? '') ?></h1>
        <p><?= $h($artifact['path'] ?? '—') ?></p>
    </div>
    <div class="page-reference"><strong><?= $h($artifact['interest_level_label'] ?? '') ?></strong></div>
</div>
<div class="metrics-grid">
    <div class="metric"><div class="metric-label">Taille</div><div class="metric-value" style="font-size:1rem"><?= $h($artifact['size_label'] ?? '—') ?></div></div>
    <div class="metric"><div class="metric-label">Auteur</div><div class="metric-value" style="font-size:1rem"><?= $h($artifact['presumed_author'] ?? '—') ?></div></div>
    <div class="metric"><div class="metric-label">Application</div><div class="metric-value" style="font-size:1rem"><?= $h($artifact['source_app'] ?? '—') ?></div></div>
    <div class="metric"><div class="metric-label">Compte</div><div class="metric-value" style="font-size:1rem"><?= $h($artifact['account_label'] ?? '—') ?></div></div>
</div>
<section class="panel">
    <div class="panel-header"><div class="panel-title">Métadonnées</div></div>
    <div class="panel-body" style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        <div><span class="muted">Création</span><br><?= $h($artifact['created_at_device'] ?? '—') ?></div>
        <div><span class="muted">Modification</span><br><?= $h($artifact['modified_at_device'] ?? '—') ?></div>
        <div><span class="muted">Géo</span><br><?= $h(($artifact['geo_lat'] ?? '') !== null && $artifact['geo_lat'] !== '' ? ($artifact['geo_lat'] . ', ' . $artifact['geo_lng']) : '—') ?></div>
        <div><span class="muted">Identifiants</span><br><?= $h($artifact['associated_identifiers'] ?? '—') ?></div>
        <div><span class="muted">Personnes détectées</span><br><?= $h($artifact['detected_persons'] ?? '—') ?></div>
    </div>
</section>
<?php if (!empty($canManage)): ?>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Analyse opérateur</div></div>
    <form method="post" action="<?= $h(url('atak/sse/exploitation-numerique/artefacts/' . (int) $artifact['id'])) ?>" class="panel-body" style="display:grid;gap:12px;max-width:520px">
        <?= \App\Core\Csrf::field() ?>
        <label>Statut
            <select name="status">
                <?php foreach ($statuses as $k => $lab): ?>
                    <option value="<?= $h($k) ?>" <?= ($artifact['status'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Commentaire analyste
            <textarea name="analyst_comment" rows="4"><?= $h($artifact['analyst_comment'] ?? '') ?></textarea>
        </label>
        <button class="btn" type="submit">Enregistrer</button>
    </form>
</section>
<?php endif; ?>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
