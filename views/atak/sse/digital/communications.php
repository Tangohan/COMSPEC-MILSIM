<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_subnav.php';
?>
<div class="page-heading">
    <div>
        <h1>Communications</h1>
        <p>Messages et appels extraits — vue par fil et chronologie.</p>
    </div>
</div>
<form class="toolbar" method="get">
    <div class="toolbar-field"><label for="device_id">Support</label>
        <select id="device_id" name="device_id">
            <?php foreach ($devices as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (int) $deviceId === (int) $d['id'] ? 'selected' : '' ?>><?= $h($d['reference_code'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Afficher</button></div>
</form>
<?php if ((int) $deviceId < 1): ?>
<section class="panel"><div class="panel-body"><p class="muted">Aucun support acquis à afficher. Un support arrive ici quand un joueur exploite un téléphone ou un ordinateur en jeu puis le transmet (terminal SEEK ou app SSE de l’ATAK), ou quand la gestion enregistre un support dans le laboratoire.</p></div></section>
<?php else: ?>
<section class="panel">
    <div class="panel-header"><div class="panel-title">Messages</div></div>
    <div class="panel-body">
    <?php if ($messages === []): ?>
        <p class="muted">Aucun message relevé sur ce support. Pour en avoir : en jeu, exploitez le téléphone à fond (extraction complète) puis transmettez-le ; les SMS et messages relevés arrivent ici, avec leur date quand le support en porte une.</p>
    <?php else: ?><ul class="iw-feed">
        <?php foreach ($messages as $m): ?>
            <li><time><?= $h(substr((string) ($m['sent_at'] ?? ''), 0, 16)) ?></time>
            <span><?= $h($m['sender_label'] ?? '') ?> : <?= $h($m['body'] ?? '') ?><?php if (!empty($m['is_deleted'])): ?> <em>(supprimé)</em><?php endif; ?></span></li>
        <?php endforeach; ?>
    </ul><?php endif; ?></div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Appels</div></div>
    <?php if ($calls === []): ?><div class="panel-body"><p class="muted">Aucun appel relevé sur ce support. Même démarche que pour les messages : extraction complète en jeu, puis transmission.</p></div>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th>Date</th><th>Correspondant</th><th>Sens</th><th>Durée</th></tr></thead><tbody>
    <?php foreach ($calls as $c): ?>
        <tr><td><?= $h(substr((string) ($c['started_at'] ?? ''), 0, 16) ?: '—') ?></td><td><?= $h(trim(($c['peer_label'] ?? '') . ' ' . ($c['peer_number'] ?? ''))) ?></td><td><?= ($c['direction'] ?? '') === 'inbound' ? 'Entrant' : (($c['direction'] ?? '') === 'missed' ? 'Manqué' : 'Sortant') ?></td><td><?= (int) ($c['duration_sec'] ?? 0) ?> s</td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php endif; ?>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
