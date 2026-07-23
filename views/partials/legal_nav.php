<?php
declare(strict_types=1);
/**
 * Navigation latérale du hub juridique.
 * Variables : $legalActivePage (site|droits), $legalActiveSection (optionnel)
 */
$legalActivePage = $legalActivePage ?? 'site';
$legalActiveSection = $legalActiveSection ?? '';
$brand = email_brand_name();
$siteUrl = url('legal/site');
$onSitePage = $legalActivePage === 'site';

/** Ancre same-page si déjà sur le hub, sinon URL complète (depuis « Exercer vos droits », etc.). */
$legalSectionHref = static function (string $section) use ($onSitePage, $siteUrl): string {
    return $onSitePage ? '#' . $section : $siteUrl . '#' . $section;
};
?>
<aside class="legal-side" aria-label="<?= htmlspecialchars(__('legal.docs_aria'), ENT_QUOTES, 'UTF-8') ?>">
    <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="legal-logo">
        <span class="legal-logo-title"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="legal-logo-sub"><?= htmlspecialchars(__('legal.portal'), ENT_QUOTES, 'UTF-8') ?></span>
    </a>

    <div class="legal-nav-label"><?= htmlspecialchars(__('legal.docs_label'), ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="legal-nav" aria-label="<?= htmlspecialchars(__('legal.pages_aria'), ENT_QUOTES, 'UTF-8') ?>">
        <a href="<?= htmlspecialchars($onSitePage ? '#' : $siteUrl, ENT_QUOTES, 'UTF-8') ?>" class="<?= $onSitePage ? 'active' : '' ?>"><?= htmlspecialchars(__('legal.policy_conditions'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $legalActivePage === 'droits' ? 'active' : '' ?>"><?= htmlspecialchars(__('legal.exercise_rights'), ENT_QUOTES, 'UTF-8') ?></a>
    </nav>

    <div class="legal-nav-label"><?= htmlspecialchars(__('legal.topics_label'), ENT_QUOTES, 'UTF-8') ?></div>
    <nav class="legal-nav" aria-label="<?= htmlspecialchars(__('legal.topics_aria'), ENT_QUOTES, 'UTF-8') ?>">
        <a href="<?= htmlspecialchars($legalSectionHref('presentation'), ENT_QUOTES, 'UTF-8') ?>" class="legal-topic<?= $legalActiveSection === 'presentation' ? ' is-active' : '' ?>">
            <?= htmlspecialchars(__('legal.presentation'), ENT_QUOTES, 'UTF-8') ?>
            <span class="legal-topic-desc"><?= htmlspecialchars(__('legal.presentation_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <a href="<?= htmlspecialchars($legalSectionHref('mentions'), ENT_QUOTES, 'UTF-8') ?>" class="legal-topic<?= $legalActiveSection === 'mentions' ? ' is-active' : '' ?>">
            <?= htmlspecialchars(__('legal.mentions'), ENT_QUOTES, 'UTF-8') ?>
            <span class="legal-topic-desc"><?= htmlspecialchars(__('legal.mentions_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <a href="<?= htmlspecialchars($legalSectionHref('cgu'), ENT_QUOTES, 'UTF-8') ?>" class="legal-topic<?= $legalActiveSection === 'cgu' ? ' is-active' : '' ?>">
            <?= htmlspecialchars(__('legal.cgu'), ENT_QUOTES, 'UTF-8') ?>
            <span class="legal-topic-desc"><?= htmlspecialchars(__('legal.cgu_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <a href="<?= htmlspecialchars($legalSectionHref('cgv'), ENT_QUOTES, 'UTF-8') ?>" class="legal-topic<?= $legalActiveSection === 'cgv' ? ' is-active' : '' ?>">
            <?= htmlspecialchars(__('legal.cgv'), ENT_QUOTES, 'UTF-8') ?>
            <span class="legal-topic-desc"><?= htmlspecialchars(__('legal.cgv_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <a href="<?= htmlspecialchars($legalSectionHref('rgpd'), ENT_QUOTES, 'UTF-8') ?>" class="legal-topic<?= $legalActiveSection === 'rgpd' ? ' is-active' : '' ?>">
            <?= htmlspecialchars(__('legal.rgpd'), ENT_QUOTES, 'UTF-8') ?>
            <span class="legal-topic-desc"><?= htmlspecialchars(__('legal.rgpd_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <a href="<?= htmlspecialchars($legalSectionHref('cookies'), ENT_QUOTES, 'UTF-8') ?>" class="legal-topic<?= $legalActiveSection === 'cookies' ? ' is-active' : '' ?>">
            <?= htmlspecialchars(__('legal.cookies'), ENT_QUOTES, 'UTF-8') ?>
            <span class="legal-topic-desc"><?= htmlspecialchars(__('legal.cookies_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>" class="legal-topic<?= $legalActivePage === 'droits' ? ' is-active' : '' ?>">
            <?= htmlspecialchars(__('legal.exercise_rights'), ENT_QUOTES, 'UTF-8') ?>
            <span class="legal-topic-desc"><?= htmlspecialchars(__('legal.rights_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    </nav>
</aside>
