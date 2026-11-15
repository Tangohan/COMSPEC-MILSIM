<?php
declare(strict_types=1);

// Bandeau « Extranet JNET » en haut du tableau de bord (inclus par dashboard_command_center.php, avant le briefing du jour).
// Visible seulement pour qui a accès à JNET (JnetAnnouncement::viewerHasJnet : organisation réelle et profil de communauté
// qui propose JNET, comme l'arrivée par défaut des membres). Le membre peut le masquer (croix) : l'état est gardé côté
// serveur (user_ui_tours) et un lien discret le réaffiche. Sans JavaScript, le bandeau reste affiché (la croix ne se montre qu'avec lui).

use App\Core\Csrf;
use App\Core\Session;
use App\Support\JnetAnnouncement;

$jnbUserId = (int) (Session::get('user_id') ?? 0);
if ($jnbUserId < 1 || !JnetAnnouncement::viewerHasJnet()) {
    return;
}
$jnbMode = JnetAnnouncement::bannerMode(true, JnetAnnouncement::isDismissed($jnbUserId, JnetAnnouncement::BANNER_KEY));
$jnbH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$jnbCollapsed = $jnbMode === 'collapsed';
?>
<link href="<?= $jnbH(asset_url('assets/css/jnet-announce.css')) ?>" rel="stylesheet">
<section class="jnb" id="jnet-banner" aria-labelledby="jnb-title"<?= $jnbCollapsed ? ' hidden' : '' ?>
         data-jnb data-csrf="<?= $jnbH(Csrf::token()) ?>"
         data-hide-url="<?= $jnbH(url('api/jnet/annonce/bandeau/vu')) ?>"
         data-show-url="<?= $jnbH(url('api/jnet/annonce/bandeau/rappel')) ?>">
    <div class="jnb__in">
        <div class="jnb__text">
            <h2 class="jnb__title" id="jnb-title">Extranet JNET : ton espace d’unité, tes ordres et ton profil</h2>
            <p class="jnb__lead">Une unité. Un espace. Tout ce qui te concerne, au même endroit.</p>
        </div>
        <div class="jnb__actions">
            <a class="jnb__btn" href="<?= $jnbH(url('jnet')) ?>">Ouvrir mon extranet</a>
            <a class="jnb__link" href="<?= $jnbH(url('jnet/guide')) ?>">Guide</a>
        </div>
        <button type="button" class="jnb__x" data-jnb-hide aria-label="Masquer ce bandeau" title="Masquer ce bandeau" hidden>×</button>
    </div>
</section>
<p class="jnb-back" data-jnb-back<?= $jnbCollapsed ? '' : ' hidden' ?>>
    <button type="button" data-jnb-show>Afficher le bandeau JNET</button>
</p>
<script src="<?= $jnbH(asset_url('assets/js/jnet-announce.js')) ?>" defer></script>
