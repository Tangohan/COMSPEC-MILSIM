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
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-transmissions.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '')
    . '<script defer src="' . $h(asset_url('assets/js/ui_confirm_modal.js')) . '"></script>'
    . '<script defer src="' . $h(asset_url('assets/js/sse-transmissions.js')) . '"></script>';

$eid = (int) ($event['id'] ?? 0);
$base = url('atak/sse/transmissions');
$when = substr((string) ($event['event_time'] ?? ''), 0, 19);
$entityTitle = is_array($entity ?? null) ? (string) ($entity['display_label'] ?? $entity['display_name'] ?? $entity['title'] ?? '') : '';
$payloadSections = is_array($payloadSections ?? null) ? $payloadSections : [];
$payloadImages = is_array($payloadImages ?? null) ? $payloadImages : [];
$linkedPerson = is_array($linkedPerson ?? null) ? $linkedPerson : null;
$clientLabel = trim((string) ($clientLabel ?? ''));
$canManage = !empty($canManage);
$fieldCount = 0;
foreach ($payloadSections as $rows) {
    $fieldCount += count($rows);
}
$personPhotos = $linkedPerson !== null && is_array($linkedPerson['photos'] ?? null) ? $linkedPerson['photos'] : [];
$personPrimary = $linkedPerson !== null ? (string) ($linkedPerson['photo'] ?? '') : '';
$mediaCount = count($payloadImages) + ($personPrimary !== '' ? 1 : 0) + max(0, count($personPhotos) - ($personPrimary !== '' ? 1 : 0));
$dots = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="5" cy="12" r="1.9" fill="currentColor"/><circle cx="12" cy="12" r="1.9" fill="currentColor"/><circle cx="19" cy="12" r="1.9" fill="currentColor"/></svg>';

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
$facts = [
    ['Horodatage', $when],
    ['Nature', (string) ($event['event_type_label'] ?? '')],
    ['Origine', (string) ($event['source_system_label'] ?? '')],
    ['Opérateur', ($event['author_label'] ?? '') !== '' ? (string) $event['author_label'] : 'Non renseigné'],
    ['Unité', ($event['unit_label'] ?? '') !== '' ? (string) $event['unit_label'] : 'Non renseignée'],
    ['Cotation', (string) ($event['confidence_code'] ?? '')],
];
if ($clientLabel !== '') {
    $facts[] = ['Logiciel terrain', $clientLabel];
}
if ($entityTitle !== '') {
    $facts[] = ['Entité indexée', $entityTitle];
}
if (($event['lat'] ?? null) !== null && ($event['lng'] ?? null) !== null) {
    $facts[] = ['Position rapportée', number_format((float) $event['lat'], 1, '.', '') . ' / ' . number_format((float) $event['lng'], 1, '.', '')];
}
if (!empty($event['raw_source_id'])) {
    $facts[] = ['Référence source', (string) $event['raw_source_id']];
}
$bioChip = static function (string $label, string $value) use ($h): string {
    $tone = \App\Support\SseTransmissionView::bioTone($value);

    return '<li class="tx-bio tx-bio--' . $tone . '"><span>' . $h($label) . '</span><strong>' . $h($value !== '' ? $value : '—') . '</strong></li>';
};
$tabs = [['resume', 'Résumé', null], ['donnees', 'Données', $fieldCount]];
if ($mediaCount > 0) {
    $tabs[] = ['medias', 'Médias', $mediaCount];
}
?>
<div class="txp txp--show" data-txp>
<div class="page-heading txp-head">
    <div>
        <a class="txp-back" href="<?= $h($base) ?>">← Retour au journal</a>
        <h1><?= $h($event['event_type_label'] ?? 'Transmission') ?></h1>
        <p><?= $h(($event['summary'] ?? '') !== '' ? $event['summary'] : 'Cette transmission n’a pas de résumé.') ?></p>
        <p class="txp-chips">
            <span class="badge">TX-<?= $eid ?></span>
            <?php if (($event['source_system_label'] ?? '') !== ''): ?><span class="badge"><?= $h($event['source_system_label']) ?></span><?php endif; ?>
            <?php if (($event['confidence_code'] ?? '') !== ''): ?><span class="badge">Cotation <?= $h($event['confidence_code']) ?></span><?php endif; ?>
            <span class="txp-chips__when"><?= $h($when) ?></span>
        </p>
    </div>
    <div class="txp-head__actions">
        <a class="btn btn--ghost" href="<?= $h($base . '/' . $eid . '/pdf') ?>">Télécharger en PDF</a>
        <?php if ($relatedHref && $relatedLabel): ?>
            <a class="btn" href="<?= $h($relatedHref) ?>"><?= $h($relatedLabel) ?></a>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <div class="txp-menu txp-menu--end" data-txp-menu>
                <button type="button" class="txp-menu__btn txp-menu__btn--boxed" aria-haspopup="menu" aria-expanded="false" aria-label="Plus d’actions"><?= $dots ?></button>
                <div class="txp-menu__list" role="menu" hidden>
                    <form method="post" action="<?= $h($base . '/' . $eid . '/discord') ?>" data-ui-confirm="1" data-ui-confirm-title="Envoyer vers Discord ?" data-ui-confirm-body="Cette transmission sera publiée dans les salons Discord reliés.">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" role="menuitem">Envoyer vers Discord</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php \App\Services\Sse\Links\SseLinksWidget::render('transmission', $eid); ?>
<?php \App\Services\Sse\Attach\SseAttachWindow::section('transmission', $eid); ?>

<div class="txp-tabs" role="tablist" aria-label="Sections de la fiche">
    <?php foreach ($tabs as $i => [$key, $label, $n]): ?>
        <button type="button" class="txp-tab<?= $i === 0 ? ' is-active' : '' ?>" role="tab" id="txp-tab-<?= $h($key) ?>" aria-controls="txp-panel-<?= $h($key) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i === 0 ? '' : ' tabindex="-1"' ?> data-txp-tab="<?= $h($key) ?>">
            <?= $h($label) ?><?php if ($n !== null): ?> <span class="txp-tab__n"><?= (int) $n ?></span><?php endif; ?>
        </button>
    <?php endforeach; ?>
</div>

<section class="txp-panel" id="txp-panel-resume" role="tabpanel" aria-labelledby="txp-tab-resume" data-txp-panel="resume">
    <h2 class="txp-h2">Résumé de la transmission</h2>
    <p class="txp-lead">Qui a transmis quoi, quand et avec quelle fiabilité, ainsi que l’identité liée s’il y en a une.</p>
    <div class="txp-split<?= $linkedPerson !== null ? ' has-aside' : '' ?>">
        <dl class="txp-kv txp-kv--card">
            <?php foreach ($facts as [$label, $value]): ?>
                <div><dt><?= $h($label) ?></dt><dd><?= $kvValue((string) $value) ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <?php if ($linkedPerson !== null): ?>
            <aside class="txp-person" aria-label="Identité liée">
                <div class="txp-person__top">
                    <?php if ($personPrimary !== ''): ?>
                        <img class="txp-person__photo" src="<?= $h($personPrimary) ?>" alt="Photographie faciale de <?= $h($linkedPerson['name'] !== '' ? $linkedPerson['name'] : $linkedPerson['ref']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="txp-person__photo txp-person__photo--none" aria-hidden="true">Pas de photo</span>
                    <?php endif; ?>
                    <div>
                        <h3><?= $h($linkedPerson['name'] !== '' ? $linkedPerson['name'] : 'Identité sans nom') ?></h3>
                        <?php if ($linkedPerson['alias'] !== ''): ?><p>« <?= $h($linkedPerson['alias']) ?> »</p><?php endif; ?>
                        <p class="txp-person__ref"><?= $h($linkedPerson['ref']) ?></p>
                    </div>
                </div>
                <ul class="tx-bios">
                    <?= $bioChip('Photo visage', $personPrimary !== '' ? 'Disponible' : 'Absente') ?>
                    <?= $bioChip('Empreintes', (string) $linkedPerson['prints']) ?>
                    <?= $bioChip('Iris', (string) $linkedPerson['iris']) ?>
                    <?= $bioChip('ADN', (string) $linkedPerson['dna']) ?>
                    <?= $bioChip('Qualité capture', $linkedPerson['quality'] !== null ? (int) $linkedPerson['quality'] . ' %' : '—') ?>
                    <?= $bioChip('Terminal', (string) $linkedPerson['terminal']) ?>
                </ul>
                <?php $samples = is_array($linkedPerson['samples'] ?? null) ? $linkedPerson['samples'] : []; ?>
                <?php if ($samples !== []): ?>
                    <details class="txp-fold">
                        <summary>Relevés biométriques (<?= count($samples) ?>)</summary>
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
                    </details>
                <?php endif; ?>
                <a class="btn" href="<?= $h($linkedPerson['href']) ?>">Ouvrir la fiche identité</a>
            </aside>
        <?php endif; ?>
    </div>
</section>

<section class="txp-panel" id="txp-panel-donnees" role="tabpanel" aria-labelledby="txp-tab-donnees" data-txp-panel="donnees" hidden>
    <h2 class="txp-h2">Contenu transmis</h2>
    <p class="txp-lead">Les champs envoyés depuis le jeu, rangés par rubrique. Ouvrez une rubrique pour la lire.</p>
    <?php if ($payloadSections === []): ?>
        <div class="txp-empty">
            <strong>Pas de contenu détaillé pour cette entrée</strong>
            <p>Cette transmission est antérieure à l’enregistrement du contenu. Les prochaines envoyées depuis Arma 3 afficheront ici l’identité, les relevés et la version du pack.</p>
        </div>
    <?php else: ?>
        <div class="txp-bar txp-bar--tools">
            <div class="txp-field txp-field--grow">
                <label for="txp-fieldfilter">Chercher un champ</label>
                <input id="txp-fieldfilter" type="search" data-txp-fieldfilter placeholder="Nom ou valeur du champ…" autocomplete="off">
            </div>
            <div class="txp-bar__btns">
                <button type="button" class="btn btn--ghost" data-txp-expand="1">Tout ouvrir</button>
                <button type="button" class="btn btn--ghost" data-txp-expand="0">Tout replier</button>
            </div>
        </div>
        <p class="txp-none" data-txp-nomatch hidden>Aucun champ ne correspond à cette recherche.</p>
        <div class="txp-groups">
        <?php $gi = 0; foreach ($payloadSections as $sectionTitle => $rows): ?>
            <details class="txp-group" data-txp-group<?= $gi === 0 ? ' open' : '' ?>>
                <summary><span><?= $h($sectionTitle) ?></span><span class="txp-group__n"><?= count($rows) ?> champ<?= count($rows) > 1 ? 's' : '' ?></span></summary>
                <dl class="txp-kv">
                    <?php foreach ($rows as $row): ?>
                        <div data-txp-field><dt><?= $h($row['label'] ?? '') ?></dt><dd><?= $kvValue((string) ($row['value'] ?? '')) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </details>
        <?php $gi++; endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($mediaCount > 0): ?>
<section class="txp-panel" id="txp-panel-medias" role="tabpanel" aria-labelledby="txp-tab-medias" data-txp-panel="medias" hidden>
    <h2 class="txp-h2">Médias transmis</h2>
    <p class="txp-lead">Photographies et images reçues avec cette transmission. Touchez une image pour l’agrandir.</p>
    <?php if ($linkedPerson !== null && ($personPrimary !== '' || $personPhotos !== [])): ?>
        <h3 class="txp-h3">Photographies de l’identité liée</h3>
        <div class="txp-gallery" data-sse-gallery data-gallery-title="<?= $h(($linkedPerson['name'] ?: $linkedPerson['ref']) . ' — photographies') ?>">
            <?php if ($personPrimary !== ''): ?>
                <figure><img src="<?= $h($personPrimary) ?>" alt="Photographie faciale" loading="lazy"><figcaption><?= $h($linkedPerson['ref']) ?> · photographie faciale</figcaption></figure>
            <?php endif; ?>
            <?php foreach ($personPhotos as $ph): ?>
                <?php if ((string) $ph['src'] === $personPrimary) { continue; } ?>
                <figure><img src="<?= $h($ph['src']) ?>" alt="<?= $h($ph['label']) ?>" loading="lazy"><figcaption><?= $h($ph['label']) ?></figcaption></figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($payloadImages !== []): ?>
        <h3 class="txp-h3">Images de la transmission</h3>
        <div class="txp-gallery" data-sse-gallery data-gallery-title="Images de la transmission TX-<?= $eid ?>">
            <?php foreach ($payloadImages as $img): ?>
                <figure><img src="<?= $h($img['src']) ?>" alt="<?= $h($img['label']) ?>" loading="lazy"><figcaption><?= $h($img['label']) ?></figcaption></figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<dialog class="txp-dialog txp-dialog--small" id="portal-ui-confirm" aria-labelledby="portal-ui-confirm-title" aria-describedby="portal-ui-confirm-body">
    <form method="dialog" class="txp-dialog__card">
        <header class="txp-dialog__head">
            <div>
                <h2 id="portal-ui-confirm-title">Confirmer</h2>
                <p id="portal-ui-confirm-body"></p>
            </div>
        </header>
        <footer class="txp-dialog__foot">
            <button type="submit" value="cancel" class="btn btn--ghost">Annuler</button>
            <button type="submit" value="confirm" class="btn">Confirmer</button>
        </footer>
    </form>
</dialog>
</div>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
