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

$iconHr = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>';
$iconBrevet = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5"/><path d="M8.2 13.2 7 22l5-2 5 2-1.2-8.8"/></svg>';
$iconTraining = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 19 8-4 8 4"/><path d="m4 15 8-4 8 4"/><path d="M12 3v4"/><path d="m8 7 4-4 4 4"/></svg>';
$iconDown = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 19h16"/></svg>';

$sectionMeta = [
    'hr' => [
        'label' => 'Dossier RH',
        'hint' => 'Chartes, certificats, affectations et évaluations partagés avec vous.',
        'kind' => 'RH',
        'icon' => $iconHr,
    ],
    'brevet' => [
        'label' => 'Brevets',
        'hint' => 'Pièces liées à vos qualifications obtenues.',
        'kind' => 'Brevet',
        'icon' => $iconBrevet,
    ],
    'training' => [
        'label' => 'Formations',
        'hint' => 'Attestations délivrées après une formation.',
        'kind' => 'Attestation',
        'icon' => $iconTraining,
    ],
];
$summary = [
    'kicker' => 'Dossier individuel · Coffre',
    'title' => 'Mon coffre',
    'lead' => 'Toutes les pièces qui vous concernent, au même endroit : dossier RH, brevets et attestations. Les documents réservés à l’encadrement restent hors de ce coffre.',
    'links' => [
        ['label' => 'Mes qualifications', 'href' => url('back-office/ma-situation/qualifications'), 'primary' => true],
        ['label' => 'Ma fiche', 'href' => url('back-office/ma-situation/ma-fiche')],
        ['label' => 'Dossier de carrière', 'href' => url('back-office/ma-situation/carriere')],
        ['label' => 'Mes démarches', 'href' => url('back-office/ma-situation/mes-demarches')],
    ],
    'stats' => [
        ['label' => 'Pièces', 'value' => $total, 'note' => 'Dans votre coffre.'],
        ['label' => 'Dossier RH', 'value' => $hrCount, 'note' => 'Chartes, certificats, évaluations.'],
        ['label' => 'Brevets', 'value' => $brevetCount, 'note' => 'Liés à vos qualifications.'],
        ['label' => 'Formations', 'value' => $trainingCount, 'note' => 'Attestations délivrées.'],
    ],
];
?>
<div class="bo-member-situation bo-member-situation--dossier bo-vault">
    <?php require __DIR__ . '/_summary.php'; ?>

    <?php if ($items === []): ?>
        <section class="bo-doc-empty bo-vault-empty">
            <div class="bo-doc-sheet bo-doc-sheet--ghost bo-vault-empty__mark" aria-hidden="true">
                <span class="bo-doc-sheet__seal">CF</span>
            </div>
            <div>
                <h3>Coffre encore vide</h3>
                <p>
                    Dès qu’une charte, un certificat, un brevet ou une attestation vous est partagé,
                    la pièce apparaît ici avec un accès direct.
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
            $rowCount = count($rows);
            ?>
            <section class="bo-vault-section bo-vault-section--<?= $h($sourceKey) ?>" aria-labelledby="coffre-<?= $h($sourceKey) ?>">
                <div class="bo-doc-section__head bo-vault-section__head">
                    <div class="bo-vault-section__title">
                        <span class="bo-vault-section__icon" aria-hidden="true"><?= $meta['icon'] ?></span>
                        <div>
                            <h2 id="coffre-<?= $h($sourceKey) ?>"><?= $h($meta['label']) ?></h2>
                            <p><?= $h($meta['hint']) ?></p>
                        </div>
                    </div>
                    <p class="bo-vault-section__count"><?= $rowCount ?> pièce<?= $rowCount > 1 ? 's' : '' ?></p>
                </div>

                <div class="bo-vault-list">
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
                        $statusTone = 'info';
                        $statusNorm = mb_strtolower($status, 'UTF-8');
                        if (in_array($statusNorm, ['disponible', 'valide', 'brevet'], true)) {
                            $statusTone = 'ok';
                        } elseif (in_array($statusNorm, ['expirée', 'expiree', 'mention au dossier'], true)) {
                            $statusTone = $statusNorm === 'mention au dossier' ? 'info' : 'warn';
                        }
                        $bits = array_values(array_filter([
                            $subtitle !== '' && strcasecmp($subtitle, $title) !== 0 ? $subtitle : '',
                            $extraDetail,
                        ], static fn (string $bit): bool => $bit !== ''));
                        ?>
                        <article class="bo-doc-card bo-vault-item">
                            <div class="bo-doc-sheet bo-vault-item__mark">
                                <span class="bo-doc-sheet__seal bo-doc-sheet__seal--icon" title="<?= $h((string) $meta['label']) ?>"><?= $meta['icon'] ?></span>
                            </div>
                            <div class="bo-vault-item__copy">
                                <p class="bo-doc-sheet__kind"><?= $h((string) $meta['label']) ?></p>
                                <h3 class="bo-doc-sheet__title"><?= $h($title) ?></h3>
                                <?php if ($bits !== []): ?>
                                    <p class="bo-vault-item__detail"><?= $h(implode(' · ', $bits)) ?></p>
                                <?php endif; ?>
                                <p class="bo-vault-item__meta">
                                    <span><?= $h($formatDate($issued)) ?></span>
                                    <span class="bo-vault-item__status is-<?= $h($statusTone) ?>"><?= $h($status) ?></span>
                                </p>
                            </div>
                            <div class="bo-doc-card__body bo-doc-card__body--actions">
                                <div class="bo-doc-card__actions">
                                    <?php if ($downloadUrl !== ''): ?>
                                        <a class="ath-btn ath-btn--solid" href="<?= $h($downloadUrl) ?>"><?= $iconDown ?> <?= $h($downloadLabel) ?></a>
                                    <?php else: ?>
                                        <p class="bo-doc-card__hint">Mention au dossier — pièce non téléchargeable.</p>
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
