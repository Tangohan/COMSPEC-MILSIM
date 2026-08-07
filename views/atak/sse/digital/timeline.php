<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var list<array<string,mixed>> $events */
/** @var list<array<string,mixed>> $devices */
require __DIR__ . '/_subnav.php';
?>
<div class="breadcrumb">Athena / SSE / <a class="link" href="<?= $h(url('atak/sse/exploitation-numerique')) ?>">Exploitation numérique</a> / <strong>Chronologie</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Laboratoire // Chronologie unifiée</div>
        <h1>Chronologie numérique</h1>
        <p>Appels, messages, photos, connexions, acquisitions et actions opérateur.</p>
    </div>
    <div class="page-reference"><strong>Vue // Timeline</strong> ATH-SSE-LABNUM-TL</div>
</div>
<form class="toolbar" method="get">
    <div class="toolbar-field"><label for="device_id">Support</label>
        <select id="device_id" name="device_id"><option value="">Tous</option>
            <?php foreach ($devices as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (int) ($filters['device_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= $h($d['reference_code'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field"><label for="event_type">Type</label>
        <select id="event_type" name="event_type">
            <option value="">Tous</option>
            <?php foreach (['message'=>'Message','call'=>'Appel','photo'=>'Photo','wifi'=>'Wi-Fi','acquisition'=>'Acquisition','seizure'=>'Saisie'] as $k=>$lab): ?>
                <option value="<?= $h($k) ?>" <?= ($filters['event_type'] ?? '') === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="toolbar-field"><label for="validated">Validation</label>
        <select id="validated" name="validated">
            <option value="">Tous</option>
            <option value="1" <?= ($filters['validated'] ?? '') === '1' ? 'selected' : '' ?>>Validé</option>
            <option value="0" <?= ($filters['validated'] ?? '') === '0' ? 'selected' : '' ?>>Non validé</option>
        </select>
    </div>
    <div class="toolbar-actions"><button class="btn" type="submit">Filtrer</button></div>
</form>
<section class="panel">
    <div class="panel-body">
        <?php if ($events === []): ?><p class="muted">Aucun événement.</p>
        <?php else: ?><ul class="iw-feed"><?php foreach ($events as $e): ?>
            <li>
                <time><?= $h(substr((string) ($e['event_at'] ?? ''), 0, 16)) ?></time>
                <span><strong><?= $h($e['title'] ?? '') ?></strong> — <?= $h($e['detail'] ?? '') ?>
                    <em> · <?= $h($e['validated_label'] ?? '') ?> · <?= $h($e['interest_level_label'] ?? '') ?></em>
                </span>
            </li>
        <?php endforeach; ?></ul><?php endif; ?>
    </div>
</section>
<?php $sseContent = ob_get_clean(); require dirname(__DIR__) . '/_layout.php';
