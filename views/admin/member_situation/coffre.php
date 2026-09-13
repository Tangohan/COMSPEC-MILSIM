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
    'hr' => ['label' => 'Dossier RH', 'hint' => 'Chartes, certificats, affectations et évaluations partagés avec vous'],
    'brevet' => ['label' => 'Brevets', 'hint' => 'Pièces liées à vos qualifications'],
    'training' => ['label' => 'Formations', 'hint' => 'Attestations délivrées après une formation'],
];
?>
<div class="bo-member-situation">
    <p class="bo-member-situation__lead">
        Ce coffre regroupe les fichiers de votre dossier personnel.
        Les pièces réservées à l’encadrement n’apparaissent pas ici.
        <a href="<?= $h(url('back-office/ma-situation/mes-demarches')) ?>">Voir mes démarches</a>
    </p>

    <div class="bo-member-situation__actions">
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/qualifications')) ?>">Mes qualifications</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/mes-demarches')) ?>">Mes démarches</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche')) ?>">Ma fiche</a>
    </div>

    <div class="bo-member-situation__grid bo-member-situation__grid--stats" aria-label="Répartition du coffre">
        <article class="bo-member-situation__card">
            <p class="bo-member-situation__kicker">Coffre</p>
            <h2><?= $total ?></h2>
            <p>pièce<?= $total > 1 ? 's' : '' ?> accessible<?= $total > 1 ? 's' : '' ?></p>
        </article>
        <article class="bo-member-situation__card">
            <p class="bo-member-situation__kicker">Dossier RH</p>
            <h2><?= $hrCount ?></h2>
            <p>document<?= $hrCount > 1 ? 's' : '' ?> partagé<?= $hrCount > 1 ? 's' : '' ?></p>
        </article>
        <article class="bo-member-situation__card">
            <p class="bo-member-situation__kicker">Brevets</p>
            <h2><?= $brevetCount ?></h2>
            <p>qualification<?= $brevetCount > 1 ? 's' : '' ?></p>
        </article>
        <article class="bo-member-situation__card">
            <p class="bo-member-situation__kicker">Formations</p>
            <h2><?= $trainingCount ?></h2>
            <p>attestation<?= $trainingCount > 1 ? 's' : '' ?></p>
        </article>
    </div>

    <?php if ($items === []): ?>
        <section class="bo-member-situation__card bo-member-situation__empty">
            <h2>Aucune pièce pour l’instant</h2>
            <p>
                Dès qu’une charte, un certificat, un brevet ou une attestation vous est partagé,
                il apparaît automatiquement dans ce coffre.
            </p>
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
            <section class="bo-member-situation__card" aria-labelledby="coffre-<?= $h($sourceKey) ?>">
                <div class="bo-member-situation__card-head">
                    <div>
                        <p class="bo-member-situation__kicker"><?= $h($meta['label']) ?></p>
                        <h2 id="coffre-<?= $h($sourceKey) ?>"><?= count($rows) ?> pièce<?= count($rows) > 1 ? 's' : '' ?></h2>
                        <p><?= $h($meta['hint']) ?></p>
                    </div>
                </div>
                <div class="bo-member-situation__list" role="list">
                    <?php foreach ($rows as $item): ?>
                        <?php if (!is_array($item)) {
                            continue;
                        } ?>
                        <article class="bo-member-situation__list-item" role="listitem">
                            <div>
                                <p class="bo-member-situation__kicker"><?= $h((string) ($item['subtitle'] ?? $item['source_label'] ?? '')) ?></p>
                                <h3><?= $h((string) ($item['title'] ?? 'Document')) ?></h3>
                                <?php if (trim((string) ($item['detail'] ?? '')) !== ''): ?>
                                    <p><?= $h((string) $item['detail']) ?></p>
                                <?php endif; ?>
                                <p>Date · <?= $h($formatDate(isset($item['issued_at']) ? (string) $item['issued_at'] : null)) ?></p>
                            </div>
                            <div class="bo-member-situation__list-actions">
                                <span class="bo-member-situation__badge"><?= $h((string) ($item['status_label'] ?? 'Pièce')) ?></span>
                                <?php if (!empty($item['download_url'])): ?>
                                    <a class="ath-btn ath-btn--solid" href="<?= $h((string) $item['download_url']) ?>">
                                        <?= $h((string) ($item['download_label'] ?? 'Ouvrir')) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
