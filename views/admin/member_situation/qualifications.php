<?php

declare(strict_types=1);

use App\Services\Personnel\QualificationTemporalStatusService;
use App\Support\QualificationAdminStatus;

$awards = is_array($awards ?? null) ? $awards : [];
$advancementBanner = is_array($advancementBanner ?? null) ? $advancementBanner : null;
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
$success = trim((string) ($success ?? ''));
$error = trim((string) ($error ?? ''));
?>
<div class="bo-member-situation bo-member-situation--dossier bo-member-situation--qualif">
    <?php if ($success !== ''): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--ok"><?= $h($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="bo-member-situation__flash bo-member-situation__flash--err"><?= $h($error) ?></p>
    <?php endif; ?>

    <header class="bo-dossier-hero">
        <div class="intro-text">
            <p class="bo-dossier-hero__kicker">Dossier individuel</p>
            <h2 class="bo-dossier-hero__title">Mes qualifications</h2>
            <p class="bo-dossier-hero__lead">
                Vos titres enregistrés sur le dossier, avec les brevets PDF quand ils ont été établis.
                Ouvrez aussi Mon coffre pour toutes les pièces qui vous concernent.
            </p>
        </div>
        <div class="bo-dossier-hero__stats" aria-label="Nombre de qualifications">
            <div class="count">
                <strong><?= (int) $count ?></strong>
                <span>Qualifications</span>
            </div>
            <?php if ($withCert > 0): ?>
                <div>
                    <strong><?= (int) $withCert ?></strong>
                    <span>Brevet<?= $withCert > 1 ? 's' : '' ?> PDF</span>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if (is_array($advancementBanner) && !empty($advancementBanner['next'])): ?>
        <?php
        $nextLabel = trim((string) ($advancementBanner['next']['label'] ?? $advancementBanner['next']['short_label'] ?? ''));
        $eval = is_array($advancementBanner['eval'] ?? null) ? $advancementBanner['eval'] : [];
        $campaign = is_array($advancementBanner['campaign'] ?? null) ? $advancementBanner['campaign'] : null;
        ?>
        <aside class="bo-adv-banner">
            <?php if (!empty($eval['is_eligible']) && $campaign): ?>
                <p class="bo-adv-banner__title">Vous êtes éligible à l’avancement au grade de <?= $h($nextLabel) ?></p>
                <?php if (empty($eval['already'])): ?>
                    <form method="post" action="<?= $h(url('back-office/ma-situation/avancement/candidater')) ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="campaign_id" value="<?= (int) ($campaign['id'] ?? 0) ?>">
                        <button type="submit" class="ath-btn ath-btn--solid">Me porter volontaire</button>
                    </form>
                <?php else: ?>
                    <p class="bo-adv-banner__meta">Candidature déjà déposée.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="bo-adv-banner__title">Avancement au grade de <?= $h($nextLabel) ?></p>
                <p class="bo-adv-banner__meta"><?= $h((string) ($eval['eligibility_reason'] ?? 'Conditions non réunies pour l’instant.')) ?></p>
            <?php endif; ?>
        </aside>
    <?php endif; ?>

    <div class="bo-member-situation__actions bo-dossier-hero__actions">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/coffre')) ?>">Ouvrir mon coffre</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche') . '?onglet=formation') ?>">Voir dans ma fiche</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/carriere')) ?>">Dossier de carrière</a>
    </div>

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

        <div class="bo-doc-grid bo-qual-grid">
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
                $toneCard = (string) ($award['card_tone'] ?? 'def');

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
                $validSmall = '';
                if ($isPermanent && ($expires === '' || !empty($award['is_permanent']))) {
                    $validityLine = 'Sans échéance · permanente';
                    $validSmall = 'qualification permanente';
                } elseif ($expiresTs !== false) {
                    if (is_int($daysRemaining) && $daysRemaining > 0) {
                        $validityLine = 'Expire dans ' . $daysRemaining . ' jour' . ($daysRemaining > 1 ? 's' : '')
                            . ' · jusqu’au ' . date('d/m/Y', $expiresTs);
                        $validSmall = 'expire dans ' . $daysRemaining . ' jour' . ($daysRemaining > 1 ? 's' : '');
                    } elseif (is_int($daysRemaining) && $daysRemaining === 0) {
                        $validityLine = 'Expire aujourd’hui';
                        $validSmall = 'expire aujourd’hui';
                    } elseif (is_int($daysRemaining) && $daysRemaining < 0) {
                        $validityLine = 'Échéance dépassée · ' . date('d/m/Y', $expiresTs);
                        $validSmall = 'échéance dépassée';
                    } else {
                        $validityLine = 'Jusqu’au ' . date('d/m/Y', $expiresTs);
                    }
                }
                ?>
                <article class="bo-doc-card bo-qual-card <?= $h($toneCard) ?>">
                    <div class="bo-doc-sheet bo-qual-card__head">
                        <div class="bo-qual-card__icon-row">
                            <div class="bo-qual-card__icon">
                                <?php if ($badgeUrl !== ''): ?>
                                    <span class="bo-doc-sheet__seal bo-doc-sheet__seal--badge">
                                        <img src="<?= $h($badgeUrl) ?>" alt="" width="36" height="36">
                                    </span>
                                <?php else: ?>
                                    <span class="bo-doc-sheet__seal" title="<?= $h($category !== '' ? $category : $name) ?>"><?= $h($sealLetters) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($category !== ''): ?>
                                    <div class="bo-qual-card__cat"><?= $h($category) ?></div>
                                <?php endif; ?>
                                <h3 class="bo-doc-sheet__title bo-qual-card__title"><?= $h($name) ?></h3>
                                <?php if ($level !== ''): ?>
                                    <p class="bo-doc-sheet__level"><?= $h($level) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bo-qual-card__meta">
                            <div>Obtenue<b><?= $obtainedTs !== false ? $h(date('d/m/Y', $obtainedTs)) : '—' ?></b></div>
                            <div>Délivré par<b><?= $issuer !== '' ? $h($issuer) : ($category !== '' ? $h($category) : '—') ?></b></div>
                        </div>
                        <div class="bo-qual-card__valid valid-row">
                            <span class="dot is-<?= $h($tone) ?>"></span>
                            <span><?= $h($statusLabel !== '' ? $statusLabel : 'Statut') ?></span>
                            <?php if ($validSmall !== ''): ?>
                                <small>— <?= $h($validSmall) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php if ($validityLine !== '' || $certNumber !== ''): ?>
                            <div class="bo-doc-sheet__meta bo-doc-sheet__meta--secondary">
                                <span><?= $validityLine !== '' ? $h($validityLine) : '—' ?></span>
                                <span><?= $certNumber !== '' ? $h($certNumber) : '' ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="bo-doc-card__body bo-doc-card__body--actions bo-qual-card__foot">
                        <span class="foot-txt"><?= $hasCert ? 'Brevet PDF établi' : 'Brevet PDF non généré' ?></span>
                        <div class="bo-doc-card__actions">
                            <?php if ($hasCert && $id > 0): ?>
                                <a class="ath-btn ath-btn--solid foot-btn download" href="<?= $h(url('back-office/ma-situation/qualifications/' . $id . '/brevet')) ?>">Télécharger le brevet</a>
                            <?php elseif ($canGenerate && $id > 0): ?>
                                <form method="post" action="<?= $h(url('back-office/ma-situation/qualifications/' . $id . '/generer-brevet')) ?>" class="bo-doc-card__form">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="ath-btn ath-btn--solid foot-btn generate">Générer le brevet</button>
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
