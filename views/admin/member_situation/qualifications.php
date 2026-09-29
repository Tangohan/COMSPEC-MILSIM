<?php

declare(strict_types=1);

use App\Services\Personnel\QualificationTemporalStatusService;
use App\Support\QualificationAdminStatus;

$awards = is_array($awards ?? null) ? $awards : [];
$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$temporalTone = static function (string $code): string {
    return match ($code) {
        QualificationTemporalStatusService::VALID => 'ok',
        QualificationTemporalStatusService::EXPIRING_SOON,
        QualificationTemporalStatusService::EXPIRED_GRACE => 'warn',
        QualificationTemporalStatusService::EXPIRED => 'bad',
        default => 'info',
    };
};

$count = count($awards);
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
            <h2 class="bo-dossier-hero__title">Mes qualifications</h2>
            <p class="bo-dossier-hero__lead">
                Vos titres enregistrés sur le dossier. Vous pouvez générer ou télécharger le brevet PDF de chaque qualification obtenue.
            </p>
        </div>
        <div class="bo-member-situation__actions bo-dossier-hero__actions">
            <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/coffre')) ?>">Ouvrir mon coffre</a>
            <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche') . '?onglet=formation') ?>">Voir dans ma fiche</a>
        </div>
    </header>

    <?php if ($awards === []): ?>
        <section class="bo-doc-empty">
            <div class="bo-doc-sheet bo-doc-sheet--ghost" aria-hidden="true">
                <span class="bo-doc-sheet__seal">—</span>
                <span class="bo-doc-sheet__line"></span>
                <span class="bo-doc-sheet__line bo-doc-sheet__line--short"></span>
            </div>
            <div>
                <h3>Aucune qualification pour l’instant</h3>
                <p>Dès qu’un responsable attribue une qualification à votre dossier, le titre et le brevet apparaissent ici.</p>
            </div>
        </section>
    <?php else: ?>
        <div class="bo-doc-list-head">
            <h3 class="bo-doc-list-head__title">
                <?= (int) $count ?> qualification<?= $count > 1 ? 's' : '' ?>
                <?php if ($withCert > 0): ?>
                    <span class="bo-doc-list-head__meta">· <?= (int) $withCert ?> brevet<?= $withCert > 1 ? 's' : '' ?> PDF</span>
                <?php endif; ?>
            </h3>
        </div>

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
                $obtained = trim((string) ($award['obtained_at'] ?? ''));
                $expires = trim((string) ($award['expires_at'] ?? ''));
                $hasCert = trim((string) ($award['certificate_document_path'] ?? '')) !== '';
                $canGenerate = !empty($award['can_generate_brevet']);
                $badgeUrl = trim((string) ($award['badge_url'] ?? ''));
                $sealLetters = trim((string) ($award['seal_letters'] ?? 'Q'));
                $obtainedTs = $obtained !== '' ? strtotime($obtained) : false;
                $expiresTs = $expires !== '' ? strtotime($expires) : false;
                $isPermanent = !empty($award['is_permanent_flag']);
                $temporalCode = (string) ($award['temporal_code'] ?? '');
                $temporalLabel = trim((string) ($award['temporal_label'] ?? ''));
                $daysRemaining = $award['days_remaining'] ?? null;
                $adminNorm = (string) ($award['admin_status_normalized'] ?? '');

                if ($temporalLabel !== '' && $temporalCode !== QualificationTemporalStatusService::NOT_APPLICABLE) {
                    $statusLabel = $temporalLabel;
                    $tone = $temporalTone($temporalCode);
                } else {
                    $statusLabel = QualificationAdminStatus::label($adminNorm !== '' ? $adminNorm : (string) ($award['status'] ?? ''));
                    $tone = match ($adminNorm) {
                        QualificationAdminStatus::OBTAINED => 'ok',
                        QualificationAdminStatus::SUSPENDED, QualificationAdminStatus::FAILED => 'warn',
                        QualificationAdminStatus::REVOKED => 'bad',
                        default => 'info',
                    };
                }

                $validityLine = '';
                if ($isPermanent && ($expires === '' || !empty($award['is_permanent']))) {
                    $validityLine = 'Sans échéance · permanente';
                } elseif ($expiresTs !== false) {
                    if (is_int($daysRemaining) && $daysRemaining > 0) {
                        $validityLine = 'Expire dans ' . $daysRemaining . ' jour' . ($daysRemaining > 1 ? 's' : '')
                            . ' · jusqu’au ' . date('d/m/Y', $expiresTs);
                    } elseif (is_int($daysRemaining) && $daysRemaining === 0) {
                        $validityLine = 'Expire aujourd’hui';
                    } elseif (is_int($daysRemaining) && $daysRemaining < 0) {
                        $validityLine = 'Échéance dépassée · ' . date('d/m/Y', $expiresTs);
                    } else {
                        $validityLine = 'Jusqu’au ' . date('d/m/Y', $expiresTs);
                    }
                }

                $orgParts = [];
                if ($issuer !== '') {
                    $orgParts[] = $issuer;
                }
                if ($category !== '' && strcasecmp($category, $issuer) !== 0) {
                    $orgParts[] = $category;
                }
                $orgLine = implode(' · ', $orgParts);
                ?>
                <article class="bo-doc-card">
                    <div class="bo-doc-sheet">
                        <div class="bo-doc-sheet__top">
                            <?php if ($badgeUrl !== ''): ?>
                                <span class="bo-doc-sheet__seal bo-doc-sheet__seal--badge">
                                    <img src="<?= $h($badgeUrl) ?>" alt="" width="36" height="36">
                                </span>
                            <?php else: ?>
                                <span class="bo-doc-sheet__seal" title="<?= $h($category !== '' ? $category : $name) ?>"><?= $h($sealLetters) ?></span>
                            <?php endif; ?>
                            <span class="bo-doc-sheet__kind"><?= $hasCert ? 'Brevet' : 'Qualification' ?></span>
                        </div>
                        <?php if ($orgLine !== ''): ?>
                            <p class="bo-doc-sheet__org"><?= $h($orgLine) ?></p>
                        <?php endif; ?>
                        <h3 class="bo-doc-sheet__title"><?= $h($name) ?></h3>
                        <?php if ($level !== ''): ?>
                            <p class="bo-doc-sheet__level"><?= $h($level) ?></p>
                        <?php endif; ?>
                        <div class="bo-doc-sheet__meta">
                            <span><?= $obtainedTs !== false ? 'Obtenue le ' . $h(date('d/m/Y', $obtainedTs)) : 'Date d’obtention non renseignée' ?></span>
                            <span class="bo-doc-sheet__status<?= $tone !== '' ? ' is-' . $h($tone) : '' ?>"><?= $h($statusLabel) ?></span>
                        </div>
                        <?php if ($validityLine !== '' || $certNumber !== ''): ?>
                            <div class="bo-doc-sheet__meta bo-doc-sheet__meta--secondary">
                                <span><?= $validityLine !== '' ? $h($validityLine) : '—' ?></span>
                                <span><?= $certNumber !== '' ? $h($certNumber) : '' ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="bo-doc-card__body bo-doc-card__body--actions">
                        <div class="bo-doc-card__actions">
                            <?php if ($hasCert && $id > 0): ?>
                                <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/qualifications/' . $id . '/brevet')) ?>">Télécharger le brevet</a>
                            <?php elseif ($canGenerate && $id > 0): ?>
                                <form method="post" action="<?= $h(url('back-office/ma-situation/qualifications/' . $id . '/generer-brevet')) ?>" class="bo-doc-card__form">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="ath-btn ath-btn--solid">Générer le brevet</button>
                                </form>
                            <?php else: ?>
                                <p class="bo-doc-card__hint">Brevet non disponible pour ce statut.</p>
                            <?php endif; ?>
                            <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche') . '?onglet=formation') ?>">Voir le détail</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
