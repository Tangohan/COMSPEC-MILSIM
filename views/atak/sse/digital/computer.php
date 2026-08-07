<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $device */
require __DIR__ . '/_subnav.php';
$id = (int) ($device['id'] ?? 0);
$systemArtifacts = array_values(array_filter($artifacts, static fn (array $a): bool => ($a['category'] ?? '') === 'system'));
$docs = array_values(array_filter($artifacts, static fn (array $a): bool => ($a['category'] ?? '') === 'document'));
$deleted = array_values(array_filter($artifacts, static fn (array $a): bool => !empty($a['is_deleted']) || ($a['category'] ?? '') === 'deleted'));
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports/' . $id)) ?>"><?= $h($device['reference_code'] ?? '') ?></a> / <strong>Vue ordinateur</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Ordinateur / disque // Exploitation</div>
        <h1><?= $h($device['reference_code'] ?? '') ?></h1>
        <p>Utilisateurs, documents, réseaux, historiques et chronologie système.</p>
    </div>
</div>
<div class="iw-tower-grid">
    <section class="panel">
        <div class="panel-header"><div class="panel-title">Utilisateurs locaux / système</div></div>
        <div class="panel-body"><ul><?php foreach ($systemArtifacts as $a): ?><li><?= $h($a['name'] ?? '') ?></li><?php endforeach; ?></ul></div>
    </section>
    <section class="panel">
        <div class="panel-header"><div class="panel-title">Comptes & applications</div></div>
        <div class="panel-body">
            <ul><?php foreach ($accounts as $a): ?><li><?= $h(($a['service_label'] ?? '') . ' — ' . ($a['username'] ?? '')) ?></li><?php endforeach; ?></ul>
            <ul><?php foreach ($applications as $a): ?><li><?= $h($a['app_name'] ?? '') ?></li><?php endforeach; ?></ul>
        </div>
    </section>
</div>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Documents</div></div>
    <div class="table-wrap"><table><thead><tr><th>Nom</th><th>Chemin</th><th>Intérêt</th></tr></thead><tbody>
    <?php foreach ($docs as $a): ?><tr><td><?= $h($a['name'] ?? '') ?></td><td><?= $h($a['path'] ?? '') ?></td><td><?= $h($a['interest_level_label'] ?? '') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Fichiers supprimés / corbeille</div></div>
    <div class="panel-body"><ul><?php foreach ($deleted as $a): ?><li><?= $h($a['name'] ?? '') ?> — <?= $h($a['path'] ?? '') ?></li><?php endforeach; ?></ul></div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Réseaux connus</div></div>
    <div class="panel-body"><ul><?php foreach ($networks as $n): ?><li><?= $h($n['ssid_or_name'] ?? '') ?></li><?php endforeach; ?></ul></div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Chronologie système</div></div>
    <div class="panel-body"><ul class="iw-feed"><?php foreach ($timeline as $e): ?>
        <li><time><?= $h(substr((string) ($e['event_at'] ?? ''), 0, 16)) ?></time><span><?= $h($e['title'] ?? '') ?></span></li>
    <?php endforeach; ?></ul></div>
</section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
