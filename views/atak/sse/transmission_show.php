<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $event */
/** @var array<string,mixed>|null $entity */
/** @var string|null $relatedHref */
/** @var string|null $relatedLabel */
/** @var list<array{section:string,label:string,value:string}> $payloadRows */
/** @var array<string,list<array{section:string,label:string,value:string}>> $payloadSections */
/** @var string $clientLabel */
$when = substr((string) ($event['event_time'] ?? ''), 0, 19);
$entityTitle = is_array($entity) ? (string) ($entity['display_label'] ?? $entity['display_name'] ?? $entity['title'] ?? '') : '';
$payloadSections = is_array($payloadSections ?? null) ? $payloadSections : [];
$clientLabel = trim((string) ($clientLabel ?? ''));
$sectionIndex = 2;
?>
<div class="breadcrumb">Athena / SSE / <a href="<?= $h(url('atak/sse/transmissions')) ?>">Transmissions terrain</a> / <strong>Fiche</strong></div>
<div class="page-heading">
    <div>
        <div class="page-heading-overline">Pilotage // Transmission</div>
        <h1><?= $h($event['event_type_label'] ?? 'Transmission') ?></h1>
        <p><?= $h($event['summary'] ?? 'Sans résumé') ?></p>
        <?php if ($clientLabel !== ''): ?>
            <p class="muted" style="margin-top:.35rem">Logiciel : <strong><?= $h($clientLabel) ?></strong></p>
        <?php endif; ?>
    </div>
    <div class="page-reference">
        <strong>Fiche // TX-<?= $h((string) (int) ($event['id'] ?? 0)) ?></strong>
        <?= $h($when) ?>
    </div>
</div>
<div class="toolbar">
    <div class="toolbar-actions">
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/transmissions')) ?>">Retour au journal</a>
        <a class="btn" href="<?= $h(url('atak/sse/transmissions/' . (int) ($event['id'] ?? 0) . '/pdf')) ?>">Télécharger en PDF</a>
        <?php if (!empty($canManage)): ?>
            <form method="post" action="<?= $h(url('atak/sse/transmissions/' . (int) ($event['id'] ?? 0) . '/discord')) ?>" style="display:inline">
                <?= \App\Core\Csrf::field() ?>
                <button class="btn btn--ghost" type="submit">Envoyer vers Discord</button>
            </form>
        <?php endif; ?>
        <?php if ($relatedHref && $relatedLabel): ?>
            <a class="btn" href="<?= $h($relatedHref) ?>"><?= $h($relatedLabel) ?></a>
        <?php endif; ?>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">TX.01</span> En-tête de transmission</div>
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
            <?php if ($clientLabel !== ''): ?>
                <tr>
                    <th scope="row">Logiciel terrain</th>
                    <td>
                        <span class="record-name"><?= $h($clientLabel) ?></span>
                        <span class="record-sub">Version lue depuis le pack chargé en jeu (CfgPatches)</span>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ($entityTitle !== ''): ?>
                <tr>
                    <th scope="row">Entité indexée</th>
                    <td><?= $h($entityTitle) ?></td>
                </tr>
            <?php endif; ?>
            <?php if (($event['lat'] ?? null) !== null && ($event['lng'] ?? null) !== null): ?>
                <tr>
                    <th scope="row">Position rapportée</th>
                    <td class="record-id"><?= $h(number_format((float) $event['lat'], 1, '.', '') . ' / ' . number_format((float) $event['lng'], 1, '.', '')) ?></td>
                </tr>
            <?php endif; ?>
            <?php if (!empty($event['raw_source_id'])): ?>
                <tr>
                    <th scope="row">Référence source</th>
                    <td class="record-id"><?= $h((string) $event['raw_source_id']) ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($payloadSections === []): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">TX.02</span> Contenu transmis</div>
    </div>
    <div class="panel-body">
        <p class="muted">
            Cette entrée est antérieure à l’enregistrement détaillé du contenu.
            Les prochaines transmissions depuis Arma afficheront ici l’identité,
            les relevés et la version du pack (ex. COMSPEC Overwatch v1.4.17).
        </p>
    </div>
</section>
<?php else: ?>
    <?php foreach ($payloadSections as $sectionTitle => $rows): ?>
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <span class="panel-index">TX.<?= $h(str_pad((string) $sectionIndex, 2, '0', STR_PAD_LEFT)) ?></span>
                    <?= $h($sectionTitle) ?>
                </div>
                <div class="panel-meta"><?= count($rows) ?> champ<?= count($rows) > 1 ? 's' : '' ?></div>
            </div>
            <div class="table-wrap">
                <table>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <th scope="row"><?= $h($row['label'] ?? '') ?></th>
                            <td><?= $h($row['value'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php $sectionIndex++; ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
