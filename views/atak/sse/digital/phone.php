<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $device */
/** @var list<array<string,mixed>> $acquisitions */
/** @var array<string,int> $contentCounts */
require __DIR__ . '/_subnav.php';
$id = (int) ($device['id'] ?? 0);
$acquisitions = is_array($acquisitions ?? null) ? $acquisitions : [];
$reserves = '';
foreach ($acquisitions as $acq) {
    if (trim((string) ($acq['reserves'] ?? '')) !== '') {
        $reserves = (string) $acq['reserves'];
        break;
    }
}
$contentTotal = array_sum(array_map('intval', is_array($contentCounts ?? null) ? $contentCounts : []));
// Dit quoi faire pour avoir des données quand une rubrique est vide : l'écran ne reste jamais muet.
$howTo = 'Pour remplir cette rubrique : en jeu, exploitez le téléphone à fond (outil d’exploitation numérique du SSE, extraction complète), puis transmettez-le avec le terminal SEEK ou l’app SSE de l’ATAK. Le contenu relevé arrive ici ; rien n’est inventé.';
$empty = static fn (string $what): string => '<p class="muted">' . htmlspecialchars($what, ENT_QUOTES, 'UTF-8') . '</p>';
?>
<div class="page-heading">
    <div>
        <h1><?= $h($device['reference_code'] ?? '') ?></h1>
        <p>Vue structurée de l’extraction mobile : ce que le joueur a relevé en jeu, ou une acquisition simulée par la gestion.</p>
    </div>
</div>
<div class="metrics-grid">
    <div class="metric"><div class="metric-label">Contacts</div><div class="metric-value"><?= count($contacts) ?></div></div>
    <div class="metric"><div class="metric-label">Messages</div><div class="metric-value"><?= count($messages) ?></div></div>
    <div class="metric"><div class="metric-label">Appels</div><div class="metric-value"><?= count($calls) ?></div></div>
    <div class="metric"><div class="metric-label">Médias</div><div class="metric-value"><?= count($media) ?></div></div>
</div>
<?php if ($contentTotal === 0): ?>
<section class="panel" id="phone-empty"><div class="panel-body">
    <strong>Aucun contenu relevé sur ce support</strong>
    <p class="muted"><?= $h($reserves !== '' ? $reserves : $howTo) ?></p>
    <?php if ($reserves !== ''): ?><p class="muted"><?= $h($howTo) ?></p><?php endif; ?>
    <p class="muted">Les extractions de téléphones ATAK (contacts, messages, positions, photos envoyés avec l’accord du joueur) sont dans Fichiers › Téléphones.</p>
    <p><a class="link" href="<?= $h(url('atak/sse/fichiers/telephones')) ?>">Téléphones des fichiers SSE</a></p>
</div></section>
<?php elseif ($reserves !== ''): ?>
<section class="panel"><div class="panel-body"><p class="muted"><?= $h($reserves) ?></p></div></section>
<?php endif; ?>
<section class="panel"><div class="panel-header"><div class="panel-title">Résumé de l’appareil</div></div>
<div class="panel-body"><?= $h(trim((string) ($device['device_type_label'] ?? '')) ?: 'Inconnu') ?> · <?= $h(trim(($device['manufacturer'] ?? '') . ' ' . ($device['model'] ?? '')) ?: '—') ?> · <?= $h($device['status_label'] ?? '') ?></div></section>

<section class="panel" style="margin-top:10px"><div class="panel-header"><div class="panel-title">Contacts</div></div>
<?php if ($contacts === []): ?><div class="panel-body"><?= $empty('Aucun contact relevé.') ?></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Nom</th><th>Numéro</th><th>Alias</th></tr></thead><tbody>
<?php foreach ($contacts as $c): ?><tr><td><?= $h($c['display_name'] ?? '') ?></td><td><?= $h($c['phone_number'] ?? '—') ?></td><td><?= $h($c['alias_label'] ?? '—') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>

<section class="panel" style="margin-top:10px"><div class="panel-header"><div class="panel-title">Messages (chronologie)</div></div>
<div class="panel-body">
<?php if ($messages === []): ?><?= $empty('Aucun message relevé.') ?><?php else: ?><ul class="iw-feed">
<?php foreach ($messages as $m): ?>
<li><time><?= $h(substr((string) ($m['sent_at'] ?? ''), 0, 16)) ?></time>
<span><strong><?= $h($m['sender_label'] ?? '') ?></strong><?= ($m['recipient_label'] ?? '') !== '' ? ' → ' . $h($m['recipient_label']) : '' ?> :
<?= $h($m['body'] ?? '') ?><?php if (!empty($m['is_deleted'])): ?> <em>(supprimé)</em><?php endif; ?></span></li>
<?php endforeach; ?>
</ul><?php endif; ?></div></section>

<section class="panel" style="margin-top:10px"><div class="panel-header"><div class="panel-title">Appels</div></div>
<?php if ($calls === []): ?><div class="panel-body"><?= $empty('Aucun appel relevé.') ?></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Correspondant</th><th>Sens</th><th>Durée</th></tr></thead><tbody>
<?php foreach ($calls as $c): ?><tr>
<td><?= $h(substr((string) ($c['started_at'] ?? ''), 0, 16) ?: '—') ?></td>
<td><?= $h(trim(($c['peer_label'] ?? '') . ' ' . ($c['peer_number'] ?? ''))) ?></td>
<td><?= ($c['direction'] ?? '') === 'inbound' ? 'Entrant' : (($c['direction'] ?? '') === 'missed' ? 'Manqué' : 'Sortant') ?></td>
<td><?= (int) ($c['duration_sec'] ?? 0) ?> s</td>
</tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>

<section class="panel" style="margin-top:10px"><div class="panel-header"><div class="panel-title">Médias (images)</div></div>
<?php if ($media === []): ?><div class="panel-body"><?= $empty('Aucune image relevée.') ?></div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Image</th><th>Type</th></tr></thead><tbody>
<?php foreach ($media as $m): ?><tr><td><?= $h(substr((string) ($m['captured_at'] ?? ''), 0, 16) ?: '—') ?></td><td><?= $h($m['name'] ?? '') ?></td><td><?= $h($m['media_type'] ?? 'image') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>

<section class="panel" style="margin-top:10px"><div class="panel-header"><div class="panel-title">Comptes · Wi-Fi · Applications · Localisations</div></div>
<div class="panel-body" style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
<div><strong>Comptes</strong><?php if ($accounts === []): ?><?= $empty('Aucun compte relevé.') ?><?php else: ?><ul><?php foreach ($accounts as $a): ?><li><?= $h(($a['service_label'] ?? '') . ' — ' . ($a['username'] ?? $a['email'] ?? '')) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
<div><strong>Réseaux</strong><?php if ($networks === []): ?><?= $empty('Aucun réseau relevé.') ?><?php else: ?><ul><?php foreach ($networks as $n): ?><li><?= $h($n['ssid_or_name'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?></div>
<div><strong>Applications</strong><?php if ($applications === []): ?><?= $empty('Aucune application relevée.') ?><?php else: ?><ul><?php foreach ($applications as $a): ?><li><?= $h($a['app_name'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?></div>
<div><strong>Localisations</strong><?php if ($locations === []): ?><?= $empty('Aucune position relevée.') ?><?php else: ?><ul><?php foreach ($locations as $l): ?><li><?= $h(trim(($l['label'] ?? '') . ' ' . ($l['source_label'] ?? '') . ' ' . (($l['lat'] ?? null) !== null ? ($l['lat'] . ',' . ($l['lng'] ?? '')) : ''))) ?></li><?php endforeach; ?></ul><?php endif; ?></div>
</div></section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
