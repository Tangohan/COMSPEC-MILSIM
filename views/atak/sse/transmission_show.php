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

<?php
$payloadImages = is_array($payloadImages ?? null) ? $payloadImages : [];
$linkedPerson = is_array($linkedPerson ?? null) ? $linkedPerson : null;
$kvValue = static function (string $value) use ($h): string {
    $value = trim($value);
    if ($value === '') {
        return '<span class="tx-kv__empty">—</span>';
    }
    if (\App\Support\SseTransmissionView::isLong($value)) {
        return '<details class="tx-kv__long"><summary>' . $h(\App\Support\SseTransmissionView::excerpt($value))
            . ' <em>Afficher tout (' . mb_strlen($value) . ' caractères)</em></summary><div>' . nl2br($h($value)) . '</div></details>';
    }

    return nl2br($h($value));
};
$headerRows = [
    ['Horodatage', $when],
    ['Nature', (string) ($event['event_type_label'] ?? '')],
    ['Origine', (string) ($event['source_system_label'] ?? '')],
    ['Opérateur', ($event['author_label'] ?? '') !== '' ? (string) $event['author_label'] : 'Non renseigné'],
    ['Unité', ($event['unit_label'] ?? '') !== '' ? (string) $event['unit_label'] : 'Non renseignée'],
    ['Cotation', (string) ($event['confidence_code'] ?? '')],
];
if ($entityTitle !== '') {
    $headerRows[] = ['Entité indexée', $entityTitle];
}
if (($event['lat'] ?? null) !== null && ($event['lng'] ?? null) !== null) {
    $headerRows[] = ['Position rapportée', number_format((float) $event['lat'], 1, '.', '') . ' / ' . number_format((float) $event['lng'], 1, '.', '')];
}
if (!empty($event['raw_source_id'])) {
    $headerRows[] = ['Référence source', (string) $event['raw_source_id']];
}
$bioChip = static function (string $label, string $value) use ($h): string {
    $tone = \App\Support\SseTransmissionView::bioTone($value);

    return '<li class="tx-bio tx-bio--' . $tone . '"><span>' . $h($label) . '</span><strong>' . $h($value !== '' ? $value : '—') . '</strong></li>';
};
?>
<div class="tx-layout<?= ($linkedPerson !== null || $payloadImages !== []) ? ' has-aside' : '' ?>">
    <?php if ($linkedPerson !== null || $payloadImages !== []): ?>
    <aside class="tx-aside">
        <?php if ($linkedPerson !== null): ?>
        <section class="panel tx-person">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">TX.ID</span> Identité liée</div>
                <div class="panel-meta"><?= $h($linkedPerson['ref']) ?></div>
            </div>
            <div class="tx-person__body">
                <?php
                $pPhotos = is_array($linkedPerson['photos'] ?? null) ? $linkedPerson['photos'] : [];
                $pPrimary = (string) ($linkedPerson['photo'] ?? '');
                ?>
                <div class="tx-person__photos" data-sse-gallery data-gallery-title="<?= $h(($linkedPerson['name'] ?: $linkedPerson['ref']) . ' — photographies') ?>">
                    <?php if ($pPrimary !== ''): ?>
                        <figure class="tx-person__photo"><img src="<?= $h($pPrimary) ?>" alt="Photographie faciale" loading="lazy"><figcaption><strong><?= $h($linkedPerson['ref']) ?></strong><span>Photographie faciale</span></figcaption></figure>
                    <?php else: ?>
                        <div class="tx-person__photo tx-person__photo--none" aria-hidden="true"><span>Pas de photo</span></div>
                    <?php endif; ?>
                    <?php foreach ($pPhotos as $ph): ?>
                        <?php if ((string) $ph['src'] === $pPrimary) { continue; } ?>
                        <figure class="tx-person__thumb"><img src="<?= $h($ph['src']) ?>" alt="<?= $h($ph['label']) ?>" loading="lazy"><figcaption><span><?= $h($ph['label']) ?></span></figcaption></figure>
                    <?php endforeach; ?>
                </div>
                <div class="tx-person__id">
                    <strong><?= $h($linkedPerson['name'] !== '' ? $linkedPerson['name'] : 'Identité sans nom') ?></strong>
                    <?php if ($linkedPerson['alias'] !== ''): ?><span>« <?= $h($linkedPerson['alias']) ?> »</span><?php endif; ?>
                </div>
                <ul class="tx-bios">
                    <?= $bioChip('Photo visage', $pPrimary !== '' ? 'Disponible' : 'Absente') ?>
                    <?= $bioChip('Empreintes', (string) $linkedPerson['prints']) ?>
                    <?= $bioChip('Iris', (string) $linkedPerson['iris']) ?>
                    <?= $bioChip('ADN', (string) $linkedPerson['dna']) ?>
                    <?= $bioChip('Qualité capture', $linkedPerson['quality'] !== null ? (int) $linkedPerson['quality'] . ' %' : '—') ?>
                    <?= $bioChip('Terminal', (string) $linkedPerson['terminal']) ?>
                </ul>
                <?php $samples = is_array($linkedPerson['samples'] ?? null) ? $linkedPerson['samples'] : []; ?>
                <?php if ($samples !== []): ?>
                    <ul class="tx-samples" aria-label="Relevés biométriques">
                        <?php foreach ($samples as $sample): ?>
                            <?php
                            $sq = $sample['quality'] ?? null;
                            $lat = (string) ($sample['laterality_label'] ?? '');
                            $ref = trim((string) ($sample['lab_reference'] ?? ''));
                            ?>
                            <li>
                                <strong><?= $h($sample['kind_label'] ?? 'Relevé') ?></strong>
                                <span class="tx-samples__q"><?= $sq !== null && $sq !== '' ? (int) $sq . ' %' : '—' ?></span>
                                <?php if (($lat !== '' && $lat !== '—') || $ref !== ''): ?>
                                    <em><?= $h(implode(' · ', array_filter([$lat !== '—' ? $lat : '', $ref]))) ?></em>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="btn tx-person__open" href="<?= $h($linkedPerson['href']) ?>">Ouvrir la fiche identité →</a>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($payloadImages !== []): ?>
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">TX.IMG</span> Images transmises</div>
                <div class="panel-meta"><?= count($payloadImages) ?></div>
            </div>
            <div class="tx-images" data-sse-gallery data-gallery-title="Images de la transmission TX-<?= $h((string) (int) ($event['id'] ?? 0)) ?>">
                <?php foreach ($payloadImages as $img): ?>
                    <figure><img src="<?= $h($img['src']) ?>" alt="<?= $h($img['label']) ?>" loading="lazy"><figcaption><span><?= $h($img['label']) ?></span></figcaption></figure>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </aside>
    <?php endif; ?>

    <div class="tx-main">
        <section class="panel">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">TX.01</span> En-tête de transmission</div>
                <div class="panel-meta"><?= $h($event['source_system_label'] ?? '') ?></div>
            </div>
            <dl class="tx-kv">
                <?php foreach ($headerRows as [$label, $value]): ?>
                    <div><dt><?= $h($label) ?></dt><dd><?= $kvValue((string) $value) ?></dd></div>
                <?php endforeach; ?>
                <?php if ($clientLabel !== ''): ?>
                    <div><dt>Logiciel terrain</dt><dd><span class="record-name"><?= $h($clientLabel) ?></span><span class="record-sub">Version lue depuis le pack chargé en jeu (CfgPatches)</span></dd></div>
                <?php endif; ?>
            </dl>
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
            <dl class="tx-kv">
                <?php foreach ($rows as $row): ?>
                    <div><dt><?= $h($row['label'] ?? '') ?></dt><dd><?= $kvValue((string) ($row['value'] ?? '')) ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>
        <?php $sectionIndex++; ?>
    <?php endforeach; ?>
<?php endif; ?>
    </div>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
