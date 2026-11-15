<?php
declare(strict_types=1);

// Fenêtre « JNET arrive » : présente l'extranet aux membres, une seule fois par membre, pendant la période fixée par
// JnetAnnouncement::MODAL_STARTS_AT / MODAL_ENDS_AT. Seulement pour qui a accès à JNET (organisation réelle, profil de
// communauté qui le propose) ; jamais sur la connexion, la sécurité du compte, l'API ni les écrans d'administration.
// Elle s'ouvre avec JavaScript : sans lui, rien ne bloque la page (le bandeau du tableau de bord reste l'accès).
// L'état « vu » est une ligne de user_ui_tours (api/jnet/annonce/fenetre/vu) ; localStorage ne sert que de secours si la table manque.
// Une autre fenêtre (formule offerte, retours de signalement, rétrospective) passe d'abord ; celle-ci viendra à la page suivante.

use App\Core\Csrf;
use App\Core\Session;
use App\Support\JnetAnnouncement;

$jnaUserId = (int) (Session::get('user_id') ?? 0);
if ($jnaUserId < 1) {
    return;
}
$jnaCtx = [
    'loggedIn' => true,
    'hasJnet' => true,
    'seen' => !empty(Session::get(JnetAnnouncement::SESSION_MODAL_SEEN)),
    'now' => time(),
    'path' => JnetAnnouncement::currentPath(),
    'otherModal' => !empty($planGiftNotice) || !empty($modReplyItems) || !empty($yrpPlayer),
    'shell' => !empty($isBackOfficeShell) || !empty($isPlatformAdminShell) || !empty($isFormationWorkspace)
        || !empty($communityReelsPage) || !empty($communityShowcasePage) || !empty($usesAdminSidebarShell),
];
// Les contrôles sans base d'abord ; l'accès à JNET et l'état « vu » ne sont lus que si la fenêtre peut encore s'afficher.
if (!JnetAnnouncement::shouldShowModal($jnaCtx) || !JnetAnnouncement::viewerHasJnet()) {
    return;
}
if (JnetAnnouncement::isDismissed($jnaUserId, JnetAnnouncement::MODAL_KEY)) {
    Session::set(JnetAnnouncement::SESSION_MODAL_SEEN, 1);

    return;
}

$jnaH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<link href="<?= $jnaH(asset_url('assets/css/jnet-announce.css')) ?>" rel="stylesheet">
<div class="jna" id="jnet-announce" hidden
     data-csrf="<?= $jnaH(Csrf::token()) ?>"
     data-save-url="<?= $jnaH(url('api/jnet/annonce/fenetre/vu')) ?>"
     data-storage-key="<?= $jnaH(JnetAnnouncement::MODAL_KEY . '.u' . $jnaUserId) ?>">
    <div class="jna__backdrop" data-jna-close></div>
    <div class="jna__card" role="dialog" aria-modal="true" aria-labelledby="jna-title" aria-describedby="jna-lead" tabindex="-1">
        <button type="button" class="jna__x" data-jna-close aria-label="Fermer">×</button>
        <h2 class="jna__title" id="jna-title">JNET, l’extranet de ton unité, est arrivé.</h2>
        <p class="jna__lead" id="jna-lead">Une unité. Un espace. Tout ce qui te concerne, au même endroit.</p>
        <ul class="jna__list">
            <li><span class="jna__n" aria-hidden="true">01</span><span>Ton espace d’unité : accueil, tâches, pages, photos et documents.</span></li>
            <li><span class="jna__n" aria-hidden="true">02</span><span>Tes ordres et échanges : ce que le commandement t’envoie, avec accusé de lecture.</span></li>
            <li><span class="jna__n" aria-hidden="true">03</span><span>Ton profil, ton ATAK et tes données, au même endroit.</span></li>
        </ul>
        <div class="jna__actions">
            <a class="jna__btn jna__btn--solid" href="<?= $jnaH(url('jnet')) ?>" data-jna-go>Découvrir JNET</a>
            <a class="jna__btn" href="<?= $jnaH(url('jnet/guide')) ?>" data-jna-go>Lire le guide</a>
            <button type="button" class="jna__btn jna__btn--ghost" data-jna-close>Plus tard</button>
        </div>
        <p class="jna__hint">Le guide reste accessible depuis ton tableau de bord et depuis JNET.</p>
    </div>
</div>
<script src="<?= $jnaH(asset_url('assets/js/jnet-announce.js')) ?>" defer></script>
