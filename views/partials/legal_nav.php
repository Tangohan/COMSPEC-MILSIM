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
?>
<aside class="legal-side" aria-label="Documentation juridique">
    <a href="<?= htmlspecialchars(url(''), ENT_QUOTES, 'UTF-8') ?>" class="legal-logo">
        <span class="legal-logo-title"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="legal-logo-sub">PORTAIL</span>
    </a>

    <div class="legal-nav-label">Documentation</div>
    <nav class="legal-nav" aria-label="Pages">
        <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>" class="<?= $legalActivePage === 'site' ? 'active' : '' ?>">Politique &amp; conditions</a>
        <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $legalActivePage === 'droits' ? 'active' : '' ?>">Exercer vos droits</a>
    </nav>

    <div class="legal-nav-label">Rubriques juridiques</div>
    <nav class="legal-nav" aria-label="Rubriques">
        <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>#presentation" class="legal-topic<?= $legalActiveSection === 'presentation' ? ' is-active' : '' ?>">
            Présentation
            <span class="legal-topic-desc">Le service Athena</span>
        </a>
        <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>#mentions" class="legal-topic<?= $legalActiveSection === 'mentions' ? ' is-active' : '' ?>">
            Mentions légales
            <span class="legal-topic-desc">Éditeur et hébergement</span>
        </a>
        <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>#cgu" class="legal-topic<?= $legalActiveSection === 'cgu' ? ' is-active' : '' ?>">
            CGU
            <span class="legal-topic-desc">Conditions d’utilisation</span>
        </a>
        <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>#cgv" class="legal-topic<?= $legalActiveSection === 'cgv' ? ' is-active' : '' ?>">
            CGV
            <span class="legal-topic-desc">Offres payantes</span>
        </a>
        <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>#rgpd" class="legal-topic<?= $legalActiveSection === 'rgpd' ? ' is-active' : '' ?>">
            Données personnelles
            <span class="legal-topic-desc">Protection et droits</span>
        </a>
        <a href="<?= htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8') ?>#cookies" class="legal-topic<?= $legalActiveSection === 'cookies' ? ' is-active' : '' ?>">
            Cookies
            <span class="legal-topic-desc">Traceurs et préférences</span>
        </a>
        <a href="<?= htmlspecialchars(url('demande-donnees'), ENT_QUOTES, 'UTF-8') ?>" class="legal-topic<?= $legalActivePage === 'droits' ? ' is-active' : '' ?>">
            Exercer vos droits
            <span class="legal-topic-desc">Formulaire de demande</span>
        </a>
    </nav>
</aside>
