<?php

declare(strict_types=1);

$vault = is_array($vault ?? null) ? $vault : [];
$items = is_array($vault['items'] ?? null) ? $vault['items'] : [];
$counts = is_array($vault['counts'] ?? null) ? $vault['counts'] : [];
$sections = is_array($vault['sections'] ?? null) ? $vault['sections'] : [];
$total = (int) ($counts['total'] ?? count($items));
$hrCount = (int) ($counts['hr'] ?? 0);
$brevetCount = (int) ($counts['brevet'] ?? 0);
$trainingCount = (int) ($counts['training'] ?? 0);
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$formatDate = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts !== false ? date('d/m/Y', $ts) : $raw;
};

$sectionMeta = [
    'hr' => [
        'label' => 'Dossier RH',
        'hint' => 'Chartes, certificats, affectations et évaluations partagés avec vous',
        'kind' => 'RH',
    ],
    'brevet' => [
        'label' => 'Brevets',
        'hint' => 'Pièces liées à vos qualifications',
        'kind' => 'Brevet',
    ],
    'training' => [
        'label' => 'Formations',
        'hint' => 'Attestations délivrées après une formation',
        'kind' => 'Attestation',
    ],
];
?>
<div class="bo-member-situation bo-member-situation--dossier">
    <header class="bo-dossier-hero">
        <div>
            <p class="bo-dossier-hero__kicker">Coffre fort personnel</p>
            <h2 class="bo-dossier-hero__title">Mon coffre</h2>
            <p class="bo-dossier-hero__lead">
                Toutes les pièces qui vous concernent, au même endroit : dossier RH, brevets et attestations.
                Les documents réservés à l’encadrement restent hors de ce coffre.
            </p>
        </div>
        <div class="bo-dossier-hero__stats" aria-label="Répartition du coffre">
            <div>
                <strong><?= $total ?></strong>
                <span>pièce<?= $total > 1 ? 's' : '' ?></span>
            </div>
            <div>
                <strong><?= $hrCount ?></strong>
                <span>RH</span>
            </div>
            <div>
                <strong><?= $brevetCount ?></strong>
                <span>brevet<?= $brevetCount > 1 ? 's' : '' ?></span>
            </div>
            <div>
                <strong><?= $trainingCount ?></strong>
                <span>formation<?= $trainingCount > 1 ? 's' : '' ?></span>
            </div>
        </div>
    </header>

    <div class="bo-member-situation__actions">
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/qualifications')) ?>">Mes qualifications</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/mes-demarches')) ?>">Mes démarches</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche')) ?>">Ma fiche</a>
    </div>

    <?php if ($items === []): ?>
        <section class="bo-doc-empty">
            <div class="bo-doc-sheet bo-doc-sheet--ghost" aria-hidden="true">
                <span class="bo-doc-sheet__seal">CF</span>
                <span class="bo-doc-sheet__line"></span>
                <span class="bo-doc-sheet__line bo-doc-sheet__line--short"></span>
            </div>
            <div>
                <h3>Coffre encore vide</h3>
                <p>
                    Dès qu’une charte, un certificat, un brevet ou une attestation vous est partagé,
                    la pièce apparaît ici avec son aperçu et un accès direct au fichier.
                </p>
            </div>
        </section>
    <?php else: ?>
        <?php foreach (['hr', 'brevet', 'training'] as $sourceKey): ?>
            <?php
            $rows = is_array($sections[$sourceKey] ?? null) ? $sections[$sourceKey] : [];
            if ($rows === []) {
                continue;
            }
            $meta = $sectionMeta[$sourceKey];
            ?>
            <section class="bo-doc-section" aria-labelledby="coffre-<?= $h($sourceKey) ?>">
                <div class="bo-doc-section__head">
                    <div>
                        <p class="bo-member-situation__kicker"><?= $h($meta['label']) ?></p>
                        <h2 id="coffre-<?= $h($sourceKey) ?>"><?= count($rows) ?> pièce<?= count($rows) > 1 ? 's' : '' ?></h2>
                        <p><?= $h($meta['hint']) ?></p>
                    </div>
                </div>

                <div class="bo-doc-grid">
                    <?php foreach ($rows as $item): ?>
                        <?php if (!is_array($item)) {
                            continue;
                        }
                        $title = (string) ($item['title'] ?? 'Document');
                        $subtitle = (string) ($item['subtitle'] ?? $item['source_label'] ?? '');
                        $detail = trim((string) ($item['detail'] ?? ''));
                        $issued = isset($item['issued_at']) ? (string) $item['issued_at'] : null;
                        $status = (string) ($item['status_label'] ?? 'Pièce');
                        $downloadUrl = trim((string) ($item['download_url'] ?? ''));
                        $downloadLabel = (string) ($item['download_label'] ?? 'Ouvrir');
                        ?>
                        <?php
                        // Ne pas répéter une mention générique déjà portée par la section / le statut.
                        $extraDetail = $detail;
                        if ($extraDetail !== '') {
                            $genericDetails = [
                                'Attestation de formation',
                                'Mention au dossier',
                                (string) $meta['kind'],
                                (string) $meta['label'],
                                $status,
                                $subtitle,
                            ];
                            foreach ($genericDetails as $generic) {
                                if ($generic !== '' && strcasecmp($extraDetail, $generic) === 0) {
                                    $extraDetail = '';
                                    break;
                                }
                            }
                        }
                        ?>
                        <article class="bo-doc-card">
                            <div class="bo-doc-sheet">
                                <div class="bo-doc-sheet__top">
                                    <span class="bo-doc-sheet__seal"><?= $h((string) $meta['kind']) ?></span>
                                    <span class="bo-doc-sheet__kind"><?= $h((string) $meta['label']) ?></span>
                                </div>
                                <?php if ($subtitle !== ''): ?>
                                    <p class="bo-doc-sheet__org"><?= $h($subtitle) ?></p>
                                <?php endif; ?>
                                <h3 class="bo-doc-sheet__title"><?= $h($title) ?></h3>
                                <?php if ($extraDetail !== ''): ?>
                                    <p class="bo-doc-sheet__level"><?= $h($extraDetail) ?></p>
                                <?php endif; ?>
                                <div class="bo-doc-sheet__meta">
                                    <span><?= $h($formatDate($issued)) ?></span>
                                    <span><?= $h($status) ?></span>
                                </div>
                            </div>
                            <div class="bo-doc-card__body bo-doc-card__body--actions">
                                <div class="bo-doc-card__actions">
                                    <?php if ($downloadUrl !== ''): ?>
                                        <a class="ath-btn ath-btn--solid" href="<?= $h($downloadUrl) ?>"><?= $h($downloadLabel) ?></a>
                                    <?php else: ?>
                                        <p class="bo-doc-card__hint">Mention au dossier — fichier non téléchargeable.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
