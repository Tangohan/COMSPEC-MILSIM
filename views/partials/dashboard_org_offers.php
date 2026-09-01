<?php
declare(strict_types=1);

/**
 * Offres publiées de la communauté (recrutement existant — pas un second registre).
 *
 * @var list<array<string,mixed>> $dashboard_published_openings
 * @var string $dashboard_tenant_slug
 * @var bool $can_manage_recruitment_offers
 */

use App\Services\Recruitment\RecruitmentOpeningPresentation;

$openings = is_array($dashboard_published_openings ?? null) ? $dashboard_published_openings : [];
$tenantSlug = trim((string) ($dashboard_tenant_slug ?? ''));
$canManageOffers = !empty($can_manage_recruitment_offers);
$enlistBase = $tenantSlug !== ''
    ? url('c/' . rawurlencode($tenantSlug) . '/enlistment')
    : url('enlistment');
?>
<section class="dash-hub-panel" id="dashboard-org-offers" aria-labelledby="dash-org-offers-title">
    <div class="dash-hub-panel__head">
        <div>
            <p class="dash-hub-panel__kicker">Recrutement</p>
            <h2 id="dash-org-offers-title" class="dash-hub-panel__title">Offres de l’organisation</h2>
            <p class="dash-hub-panel__lead">Les postes actuellement ouverts dans votre communauté. Une candidature s’ouvre sur la fiche publique déjà en place.</p>
        </div>
        <?php if ($canManageOffers): ?>
        <a href="<?= htmlspecialchars(url('back-office/recruitment/offers'), ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__ghost">Gérer les offres</a>
        <?php endif; ?>
    </div>

    <?php if ($openings === []): ?>
        <p class="dash-hub-panel__empty">Aucune offre n’est publiée pour le moment.</p>
        <?php if ($canManageOffers): ?>
        <p class="dash-hub-panel__hint">
            <a href="<?= htmlspecialchars(url('back-office/recruitment/offers/create'), ENT_QUOTES, 'UTF-8') ?>">Ouvrir un nouveau poste</a>
            depuis le registre des offres.
        </p>
        <?php endif; ?>
    <?php else: ?>
        <ul class="dash-offer-list">
            <?php foreach ($openings as $ro): ?>
                <?php
                $title = trim((string) ($ro['title'] ?? ''));
                if ($title === '') {
                    $title = 'Poste ouvert';
                }
                $pc = RecruitmentOpeningPresentation::personnelCategoryLabel((string) ($ro['personnel_category'] ?? 'other'));
                $arm = RecruitmentOpeningPresentation::armDomainLabel(isset($ro['arm_domain']) ? (string) $ro['arm_domain'] : null);
                $unitName = trim((string) ($ro['unit_name'] ?? ''));
                $sum = trim((string) ($ro['summary'] ?? ''));
                if ($sum === '') {
                    $sum = trim(strip_tags((string) ($ro['description'] ?? '')));
                    if (mb_strlen($sum) > 180) {
                        $sum = mb_substr($sum, 0, 177) . '…';
                    }
                }
                $avisSlug = trim((string) ($ro['public_page_slug'] ?? ''));
                $detailUrl = ($tenantSlug !== '' && $avisSlug !== '')
                    ? url('c/' . rawurlencode($tenantSlug) . '/avis/' . rawurlencode($avisSlug))
                    : $enlistBase . '?ouverture=' . (int) ($ro['id'] ?? 0);
                $applyUrl = $enlistBase . '?ouverture=' . (int) ($ro['id'] ?? 0);
                ?>
                <li class="dash-offer-card">
                    <div class="dash-offer-card__meta">
                        <span><?= htmlspecialchars($pc, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($arm !== '—'): ?>
                            <span><?= htmlspecialchars($arm, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($unitName !== ''): ?>
                            <span><?= htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                    <h3 class="dash-offer-card__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
                    <?php if ($sum !== ''): ?>
                        <p class="dash-offer-card__sum"><?= htmlspecialchars($sum, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <div class="dash-offer-card__actions">
                        <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__cta">Voir l’offre</a>
                        <a href="<?= htmlspecialchars($applyUrl, ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__ghost">Candidater</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
