<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique')) ?>">Exploitation numérique</a> / <strong>Communications</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire // Communications</div>
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
<section class="panel"><div class="panel-body"><p class="muted">Sélectionnez un support acquis.</p></div></section>
<?php else: ?>
<section class="panel">
    <div class="panel-header"><div class="panel-title">Messages</div></div>
    <div class="panel-body"><ul class="iw-feed">
        <?php foreach ($messages as $m): ?>
            <li><time><?= $h(substr((string) ($m['sent_at'] ?? ''), 0, 16)) ?></time>
            <span><?= $h($m['sender_label'] ?? '') ?> : <?= $h($m['body'] ?? '') ?></span></li>
        <?php endforeach; ?>
    </ul></div>
</section>
<section class="panel" style="margin-top:10px">
    <div class="panel-header"><div class="panel-title">Appels</div></div>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Correspondant</th><th>Durée</th></tr></thead><tbody>
    <?php foreach ($calls as $c): ?>
        <tr><td><?= $h(substr((string) ($c['started_at'] ?? ''), 0, 16)) ?></td><td><?= $h($c['peer_label'] ?? '') ?></td><td><?= (int) ($c['duration_sec'] ?? 0) ?> s</td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
