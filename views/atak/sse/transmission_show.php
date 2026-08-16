<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $event */
/** @var array<string,mixed>|null $entity */
/** @var string|null $relatedHref */
/** @var string|null $relatedLabel */
/** @var list<array{label:string,value:string}> $payloadRows */
$when = substr((string) ($event['event_time'] ?? ''), 0, 19);
$entityTitle = is_array($entity) ? (string) ($entity['display_label'] ?? $entity['display_name'] ?? $entity['title'] ?? '') : '';
?>
<div class="breadcrumb">Athena / SSE / <a href="<?= $h(url('atak/sse/transmissions')) ?>">Transmissions terrain</a> / <strong>Fiche</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Pilotage // Transmission</div>
        <h1><?= $h($event['event_type_label'] ?? 'Transmission') ?></h1>
        <p><?= $h($event['summary'] ?? 'Sans résumé') ?></p>
    </div>
    <div class="page-reference"><strong>Fiche // TX-<?= $h((string) (int) ($event['id'] ?? 0)) ?></strong><?= $h($when) ?></div>
</div>
<div class="toolbar">
    <div class="toolbar-actions">
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/transmissions')) ?>">Retour au journal</a>
        <?php if ($relatedHref && $relatedLabel): ?>
            <a class="btn" href="<?= $h($relatedHref) ?>"><?= $h($relatedLabel) ?></a>
        <?php endif; ?>
    </div>
</div>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">TX</span> En-tête de transmission</div>
        <div class="panel-meta"><?= $h($event['source_system_label'] ?? '') ?></div>
    </div>
    <div class="table-wrap">
        <table>
            <tbody>
            <tr>
                <th scope="row">Horodatage</th>
                <td><?= $h($when) ?></td>
            </tr>
            <tr>
                <th scope="row">Nature</th>
                <td><?= $h($event['event_type_label'] ?? '') ?></td>
            </tr>
            <tr>
                <th scope="row">Origine</th>
                <td><?= $h($event['source_system_label'] ?? '') ?></td>
            </tr>
            <tr>
                <th scope="row">Opérateur</th>
                <td><?= $h(($event['author_label'] ?? '') !== '' ? $event['author_label'] : 'Non renseigné') ?></td>
            </tr>
            <tr>
                <th scope="row">Unité</th>
                <td><?= $h(($event['unit_label'] ?? '') !== '' ? $event['unit_label'] : 'Non renseignée') ?></td>
            </tr>
            <tr>
                <th scope="row">Cotation</th>
                <td><?= $h($event['confidence_code'] ?? '') ?></td>
            </tr>
            <?php if ($entityTitle !== ''): ?>
                <tr>
                    <th scope="row">Entité indexée</th>
                    <td><?= $h($entityTitle) ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($event['lat'] !== null && $event['lng'] !== null): ?>
                <tr>
                    <th scope="row">Position rapportée</th>
                    <td class="record-id"><?= $h(number_format((float) $event['lat'], 1, '.', '') . ' / ' . number_format((float) $event['lng'], 1, '.', '')) ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($payloadRows !== []): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">TX.2</span> Compléments transmis</div>
    </div>
    <div class="table-wrap">
        <table>
            <tbody>
            <?php foreach ($payloadRows as $row): ?>
                <tr>
                    <th scope="row"><?= $h($row['label']) ?></th>
                    <td><?= $h($row['value']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
