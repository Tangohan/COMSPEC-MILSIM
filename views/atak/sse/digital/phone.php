<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $device */
require __DIR__ . '/_subnav.php';
$id = (int) ($device['id'] ?? 0);
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique/supports/' . $id)) ?>"><?= $h($device['reference_code'] ?? '') ?></a> / <strong>Extraction mobile</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Téléphone // Extraction</div>
        <h1><?= $h($device['reference_code'] ?? '') ?></h1>
        <p>Vue structurée de type extraction mobile (simulation / import).</p>
    </div>
</div>
<div class="metrics-grid">
    <div class="metric"><div class="metric-label">Contacts</div><div class="metric-value"><?= count($contacts) ?></div></div>
    <div class="metric"><div class="metric-label">Messages</div><div class="metric-value"><?= count($messages) ?></div></div>
    <div class="metric"><div class="metric-label">Appels</div><div class="metric-value"><?= count($calls) ?></div></div>
    <div class="metric"><div class="metric-label">Médias</div><div class="metric-value"><?= count($media) ?></div></div>
</div>
<section class="panel"><div class="panel-header"><div class="panel-title">Résumé de l’appareil</div></div>
<div class="panel-body"><?= $h($device['device_type_label'] ?? '') ?> · <?= $h(trim(($device['manufacturer'] ?? '') . ' ' . ($device['model'] ?? '')) ?: '—') ?> · <?= $h($device['status_label'] ?? '') ?></div></section>

<section class="panel" style="margin-top:10px"><div class="panel-header"><div class="panel-title">Contacts</div></div>
<div class="table-wrap"><table><thead><tr><th>Nom</th><th>Numéro</th><th>Alias</th></tr></thead><tbody>
<?php foreach ($contacts as $c): ?><tr><td><?= $h($c['display_name'] ?? '') ?></td><td><?= $h($c['phone_number'] ?? '—') ?></td><td><?= $h($c['alias_label'] ?? '—') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<section class="panel" style="margin-top:10px"><div class="panel-header"><div class="panel-title">Messages (chronologie)</div></div>
<div class="panel-body"><ul class="iw-feed">
<?php foreach (array_reverse($messages) as $m): ?>
<li><time><?= $h(substr((string) ($m['sent_at'] ?? ''), 0, 16)) ?></time>
<span><strong><?= $h($m['sender_label'] ?? '') ?></strong> → <?= $h($m['recipient_label'] ?? '') ?> :
<?= $h($m['body'] ?? '') ?><?php if (!empty($m['is_deleted'])): ?> <em>(supprimé)</em><?php endif; ?></span></li>
<?php endforeach; ?>
</ul></div></section>

<section class="panel" style="margin-top:10px"><div class="panel-header"><div class="panel-title">Appels</div></div>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Correspondant</th><th>Sens</th><th>Durée</th></tr></thead><tbody>
<?php foreach ($calls as $c): ?><tr>
<td><?= $h(substr((string) ($c['started_at'] ?? ''), 0, 16)) ?></td>
<td><?= $h(($c['peer_label'] ?? '') . ' ' . ($c['peer_number'] ?? '')) ?></td>
<td><?= ($c['direction'] ?? '') === 'inbound' ? 'Entrant' : 'Sortant' ?></td>
<td><?= (int) ($c['duration_sec'] ?? 0) ?> s</td>
</tr><?php endforeach; ?>
</tbody></table></div></section>

<section class="panel" style="margin-top:10px"><div class="panel-header"><div class="panel-title">Comptes · Wi-Fi · Applications · Localisations</div></div>
<div class="panel-body" style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
<div><strong>Comptes</strong><ul><?php foreach ($accounts as $a): ?><li><?= $h(($a['service_label'] ?? '') . ' — ' . ($a['username'] ?? $a['email'] ?? '')) ?></li><?php endforeach; ?></ul></div>
<div><strong>Réseaux</strong><ul><?php foreach ($networks as $n): ?><li><?= $h($n['ssid_or_name'] ?? '') ?></li><?php endforeach; ?></ul></div>
<div><strong>Applications</strong><ul><?php foreach ($applications as $a): ?><li><?= $h($a['app_name'] ?? '') ?></li><?php endforeach; ?></ul></div>
<div><strong>Localisations</strong><ul><?php foreach ($locations as $l): ?><li><?= $h(($l['label'] ?? '') . ' ' . ($l['lat'] ?? '') . ',' . ($l['lng'] ?? '')) ?></li><?php endforeach; ?></ul></div>
</div></section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
