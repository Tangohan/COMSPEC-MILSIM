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
$validCount = 0;
foreach ($awards as $row) {
    if (!is_array($row)) {
        continue;
    }
    if (trim((string) ($row['certificate_document_path'] ?? '')) !== '') {
        $withCert++;
    }
    $code = (string) ($row['temporal_code'] ?? '');
    if ($code === QualificationTemporalStatusService::VALID || $code === QualificationTemporalStatusService::NOT_APPLICABLE) {
        $validCount++;
    } elseif ($code === '' && QualificationAdminStatus::normalize((string) ($row['admin_status_normalized'] ?? $row['status'] ?? '')) === QualificationAdminStatus::OBTAINED) {
        $validCount++;
    }
}

$iconDown = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 19h16"/></svg>';
$iconRefresh = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.6-6.3"/><path d="M21 3v6h-6"/></svg>';
?>
<div class="bo-member-situation bo-member-situation--dossier">
    <aside class="bo-qual-beta" role="status">
        <span class="bo-qual-beta__badge">Version d’essai</span>
        <div class="bo-qual-beta__body">
            <p class="bo-qual-beta__title">Les brevets et cette page sont encore en cours de mise au point.</p>
            <p class="bo-qual-beta__text">
                Chaque document doit porter votre <strong>pseudo</strong> de communauté, pas un numéro de compte.
                Si un brevet déjà établi affiche encore un nom incorrect, mettez-le à jour depuis la carte correspondante.
            </p>
        </div>
    </aside>

    <section class="bo-dossier-hero bo-qual-intro">
        <div>
            <p class="bo-dossier-hero__kicker">Dossier individuel</p>
            <h2 class="bo-dossier-hero__title">Qualifications &amp; brevets</h2>
            <p class="bo-dossier-hero__lead">
                Vos titres enregistrés sur le dossier, avec les brevets lorsque la qualification est obtenue.
                Ouvrez aussi Mon coffre pour toutes les pièces qui vous concernent.
            </p>
        </div>
        <div class="bo-dossier-hero__stats" aria-label="Nombre de qualifications">
            <div class="bo-qual-count"><strong><?= (int) $count ?></strong><span>Qualification<?= $count > 1 ? 's' : '' ?></span></div>
            <div><strong><?= (int) $validCount ?></strong><span>Valide<?= $validCount > 1 ? 's' : '' ?></span></div>
            <div><strong><?= (int) $withCert ?></strong><span>Brevet<?= $withCert > 1 ? 's' : '' ?></span></div>
        </div>
    </section>

    <nav class="bo-member-situation__actions bo-dossier-hero__actions bo-qual-links" aria-label="Autres pages du dossier">
        <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/coffre')) ?>">Ouvrir mon coffre</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche') . '?onglet=formation') ?>">Voir dans ma fiche</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/carriere')) ?>">Dossier de carrière</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/decorations')) ?>">Mes décorations</a>
        <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/avancement')) ?>">Mon avancement</a>
    </nav>

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
                    <span class="bo-doc-list-head__meta">· <?= (int) $withCert ?> brevet<?= $withCert > 1 ? 's' : '' ?></span>
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
                $canRegenerate = !empty($award['can_regenerate_brevet']);
                $badgeUrl = trim((string) ($award['badge_url'] ?? ''));
                $sealLetters = trim((string) ($award['seal_letters'] ?? 'Q'));
                $holderName = trim((string) ($award['holder_name'] ?? ''));
                $obtainedTs = $obtained !== '' ? strtotime($obtained) : false;
                $expiresTs = $expires !== '' ? strtotime($expires) : false;
                $isPermanent = !empty($award['is_permanent_flag']);
                $temporalCode = (string) ($award['temporal_code'] ?? '');
                $temporalLabel = trim((string) ($award['temporal_label'] ?? ''));
                $daysRemaining = $award['days_remaining'] ?? null;
                $adminNorm = (string) ($award['admin_status_normalized'] ?? '');
                $haystack = mb_strtolower($category . ' ' . $name);
                $family = (string) ($award['card_tone'] ?? '');
                if ($family === '') {
                    $family = str_contains($haystack, 'atak') || str_contains($haystack, 'radio') || str_contains($haystack, 'liaison')
                        ? 'tak'
                        : 'rh';
                }

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

                $validitySmall = '';
                if ($isPermanent && ($expires === '' || !empty($award['is_permanent']))) {
                    $validitySmall = 'qualification permanente';
                } elseif (is_int($daysRemaining) && $daysRemaining > 0) {
                    $validitySmall = 'Expire dans ' . $daysRemaining . ' jour' . ($daysRemaining > 1 ? 's' : '');
                } elseif (is_int($daysRemaining) && $daysRemaining === 0) {
                    $validitySmall = 'expire aujourd’hui';
                } elseif (is_int($daysRemaining) && $daysRemaining < 0) {
                    $validitySmall = 'échéance dépassée';
                } elseif ($expiresTs !== false) {
                    $validitySmall = 'jusqu’au ' . date('d/m/Y', $expiresTs);
                }

                $shortStatus = $tone === 'ok' ? 'Valide' : $statusLabel;
                ?>
                <article class="bo-doc-card bo-qual-card bo-qual-card--<?= $h($family) ?>">
                    <div class="bo-qual-card__head">
                        <div class="bo-doc-sheet">
                            <div class="bo-doc-sheet__top">
                                <?php if ($badgeUrl !== ''): ?>
                                    <span class="bo-doc-sheet__seal bo-doc-sheet__seal--badge">
                                        <img src="<?= $h($badgeUrl) ?>" alt="" width="48" height="48">
                                    </span>
                                <?php else: ?>
                                    <span class="bo-doc-sheet__seal" title="<?= $h($category !== '' ? $category : $name) ?>"><?= $h($sealLetters) ?></span>
                                <?php endif; ?>
                                <div class="bo-qual-card__head-copy">
                                    <span class="bo-doc-sheet__kind bo-qual-card__cat"><?= $h($category !== '' ? $category : ($hasCert ? 'Brevet' : 'Qualification')) ?></span>
                                    <h3 class="bo-doc-sheet__title"><?= $h($name) ?></h3>
                                    <?php if ($level !== ''): ?>
                                        <p class="bo-doc-sheet__level"><?= $h($level) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bo-qual-card__valid">
                        <span class="bo-qual-card__dot is-<?= $h($tone) ?>"></span>
                        <span class="bo-doc-sheet__status is-<?= $h($tone) ?>"><?= $h($shortStatus) ?></span>
                        <?php if ($validitySmall !== ''): ?>
                            <small>— <?= $h($validitySmall) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="bo-qual-card__meta">
                        <div>Obtenue<b><?= $obtainedTs !== false ? $h(date('d/m/Y', $obtainedTs)) : '—' ?></b></div>
                        <div>Délivré par<b><?= $issuer !== '' ? $h($issuer) : '—' ?></b></div>
                    </div>
                    <?php if ($holderName !== ''): ?>
                        <p class="bo-qual-card__holder">Brevet au nom de <strong><?= $h($holderName) ?></strong></p>
                    <?php endif; ?>
                    <?php if ($certNumber !== ''): ?>
                        <p class="bo-qual-card__ref"><?= $h($certNumber) ?></p>
                    <?php endif; ?>
                    <div class="bo-doc-card__body bo-doc-card__body--actions bo-qual-card__foot">
                        <span class="bo-qual-card__foot-txt"><?= $hasCert ? 'Brevet disponible' : 'Brevet non généré' ?></span>
                        <div class="bo-doc-card__actions">
                            <?php if ($hasCert && $id > 0): ?>
                                <a class="ath-btn ath-btn--solid bo-qual-card__download" href="<?= $h(url('back-office/ma-situation/qualifications/' . $id . '/brevet')) ?>"><?= $iconDown ?> Télécharger le brevet</a>
                            <?php endif; ?>
                            <?php if ($canGenerate && $id > 0): ?>
                                <form method="post" action="<?= $h(url('back-office/ma-situation/qualifications/' . $id . '/generer-brevet')) ?>" class="bo-doc-card__form">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="ath-btn ath-btn--solid bo-qual-card__generate"><?= $iconDown ?> Générer le brevet</button>
                                </form>
                            <?php elseif ($canRegenerate && $id > 0): ?>
                                <form method="post" action="<?= $h(url('back-office/ma-situation/qualifications/' . $id . '/generer-brevet')) ?>" class="bo-doc-card__form">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="ath-btn bo-qual-card__generate" title="Recalcule le document avec votre pseudo actuel"><?= $iconRefresh ?> Mettre à jour le brevet</button>
                                </form>
                            <?php elseif (!$hasCert): ?>
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
