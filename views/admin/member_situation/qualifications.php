<?php

declare(strict_types=1);

$awards = is_array($awards ?? null) ? $awards : [];
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$statusFr = static function (?string $s): array {
    $raw = strtolower(trim((string) $s));

    return match ($raw) {
        'active', 'valid', 'granted' => ['label' => 'Valide', 'tone' => 'ok'],
        'expired' => ['label' => 'Expirée', 'tone' => 'warn'],
        'revoked' => ['label' => 'Retirée', 'tone' => 'bad'],
        'pending' => ['label' => 'En attente', 'tone' => 'info'],
        'suspended' => ['label' => 'Suspendue', 'tone' => 'warn'],
        default => ['label' => $s !== null && trim($s) !== '' ? trim($s) : '—', 'tone' => ''],
    };
};

$withCert = 0;
foreach ($awards as $row) {
    if (is_array($row) && trim((string) ($row['certificate_document_path'] ?? '')) !== '') {
        $withCert++;
    }
}
?>
<div class="bo-member-situation bo-member-situation--dossier">
    <header class="bo-dossier-hero">
        <div>
            <p class="bo-dossier-hero__kicker">Dossier individuel</p>
            <h2 class="bo-dossier-hero__title">Qualifications &amp; brevets</h2>
            <p class="bo-dossier-hero__lead">
                Vos titres enregistrés sur le dossier, avec les brevets PDF quand ils ont été établis.
                Ouvrez aussi <a href="<?= $h(url('back-office/ma-situation/coffre')) ?>">Mon coffre</a> pour toutes les pièces qui vous concernent.
            </p>
        </div>
        <div class="bo-dossier-hero__stats" aria-label="Synthèse">
            <div>
                <strong><?= count($awards) ?></strong>
                <span>qualification<?= count($awards) > 1 ? 's' : '' ?></span>
            </div>
            <div>
                <strong><?= $withCert ?></strong>
                <span>brevet<?= $withCert > 1 ? 's' : '' ?> PDF</span>
            </div>
        </div>
    </header>

    <div class="bo-member-situation__actions">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/coffre')) ?>">Ouvrir mon coffre</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche') . '?onglet=formation') ?>">Voir dans ma fiche</a>
    </div>

    <?php if ($awards === []): ?>
        <section class="bo-doc-empty">
            <div class="bo-doc-sheet bo-doc-sheet--ghost" aria-hidden="true">
                <span class="bo-doc-sheet__seal">RH</span>
                <span class="bo-doc-sheet__line"></span>
                <span class="bo-doc-sheet__line bo-doc-sheet__line--short"></span>
            </div>
            <div>
                <h3>Aucune qualification pour l’instant</h3>
                <p>Dès qu’un responsable attribue une qualification à votre dossier, le titre et le brevet apparaissent ici comme des pièces officielles.</p>
            </div>
        </section>
    <?php else: ?>
        <div class="bo-doc-grid">
            <?php foreach ($awards as $award): ?>
                <?php
                if (!is_array($award)) {
                    continue;
                }
                $id = (int) ($award['id'] ?? 0);
                $name = trim((string) ($award['definition_name'] ?? $award['qualification_name'] ?? ''));
                if ($name === '') {
                    $name = 'Qualification';
                }
                $level = trim((string) ($award['level_name'] ?? $award['level_short_name'] ?? ''));
                $category = trim((string) ($award['category_name'] ?? ''));
                $issuer = trim((string) ($award['issuer_name'] ?? ''));
                $certNumber = trim((string) ($award['certificate_number'] ?? ''));
                $status = $statusFr((string) ($award['status'] ?? ''));
                $obtained = trim((string) ($award['obtained_at'] ?? ''));
                $expires = trim((string) ($award['expires_at'] ?? ''));
                $hasCert = trim((string) ($award['certificate_document_path'] ?? '')) !== '';
                $obtainedTs = $obtained !== '' ? strtotime($obtained) : false;
                $expiresTs = $expires !== '' ? strtotime($expires) : false;
                $tone = (string) ($status['tone'] ?? '');
                ?>
                <article class="bo-doc-card">
                    <div class="bo-doc-sheet" aria-hidden="true">
                        <div class="bo-doc-sheet__top">
                            <span class="bo-doc-sheet__seal">RH</span>
                            <span class="bo-doc-sheet__kind">Brevet</span>
                        </div>
                        <p class="bo-doc-sheet__org"><?= $h($issuer !== '' ? $issuer : 'Communauté') ?></p>
                        <p class="bo-doc-sheet__title"><?= $h($name) ?></p>
                        <?php if ($level !== ''): ?>
                            <p class="bo-doc-sheet__level"><?= $h($level) ?></p>
                        <?php endif; ?>
                        <div class="bo-doc-sheet__meta">
                            <span><?= $obtainedTs !== false ? $h(date('d/m/Y', $obtainedTs)) : '—' ?></span>
                            <span><?= $certNumber !== '' ? $h($certNumber) : 'Sans n°' ?></span>
                        </div>
                    </div>

                    <div class="bo-doc-card__body">
                        <div class="bo-doc-card__head">
                            <?php if ($category !== ''): ?>
                                <p class="bo-member-situation__kicker"><?= $h($category) ?></p>
                            <?php endif; ?>
                            <span class="bo-member-situation__badge<?= $tone !== '' ? ' is-' . $h($tone) : '' ?>"><?= $h((string) $status['label']) ?></span>
                        </div>
                        <h3><?= $h($name) ?></h3>
                        <dl class="bo-doc-card__dl">
                            <?php if ($level !== ''): ?>
                            <div>
                                <dt>Niveau</dt>
                                <dd><?= $h($level) ?></dd>
                            </div>
                            <?php endif; ?>
                            <?php if ($issuer !== ''): ?>
                            <div>
                                <dt>Délivré par</dt>
                                <dd><?= $h($issuer) ?></dd>
                            </div>
                            <?php endif; ?>
                            <?php if ($obtainedTs !== false): ?>
                            <div>
                                <dt>Obtenue le</dt>
                                <dd><?= $h(date('d/m/Y', $obtainedTs)) ?></dd>
                            </div>
                            <?php endif; ?>
                            <?php if ($expiresTs !== false): ?>
                            <div>
                                <dt>Valable jusqu’au</dt>
                                <dd><?= $h(date('d/m/Y', $expiresTs)) ?></dd>
                            </div>
                            <?php endif; ?>
                            <?php if ($certNumber !== ''): ?>
                            <div>
                                <dt>Référence</dt>
                                <dd><?= $h($certNumber) ?></dd>
                            </div>
                            <?php endif; ?>
                        </dl>
                        <div class="bo-doc-card__actions">
                            <?php if ($hasCert && $id > 0): ?>
                                <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/qualifications/' . $id . '/brevet')) ?>">Ouvrir le brevet PDF</a>
                            <?php else: ?>
                                <p class="bo-doc-card__hint">Brevet PDF pas encore versé au dossier.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
