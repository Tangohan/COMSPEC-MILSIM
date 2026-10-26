<?php
declare(strict_types=1);

// Fenêtre « ta rétrospective est prête » : à la connexion, pendant les premières semaines après la publication
// (réglage de l'administration du site), une seule fois par joueur et par année. Seulement si le joueur a joué.
// Fermée (croix, « Plus tard » ou ouverte) : marquée vue ; elle se rouvre depuis Mon compte.
// Vérifiée au plus toutes les 10 minutes par session.

use App\Services\Community\YearReviewAccess;
use App\Support\YearReviewCalendar;
use App\Support\YearReviewStats;

$yrpUserId = (int) (\App\Core\Session::get('user_id') ?? 0);
$yrpTenantId = (int) (\App\Core\Session::get('tenant_id') ?? 0);
if ($yrpUserId < 1 || $yrpTenantId < 1 || !empty($planGiftNotice) || !empty($modReplyItems)) {
    // Une autre fenêtre passe d'abord ; celle-ci viendra à la page suivante.
    return;
}
$yrpCheckKey = 'year_review_notice_checked_at';
if ((int) (\App\Core\Session::get($yrpCheckKey) ?? 0) > time() - 600) {
    return;
}
\App\Core\Session::set($yrpCheckKey, time());

$yrpYear = null;
$yrpPlayer = null;
try {
    $yrpCalendar = YearReviewAccess::calendar();
    $yrpYear = $yrpCalendar->currentYear(time());
    if ($yrpYear !== null
        && $yrpCalendar->inPopupWindow($yrpYear, time())
        && empty(\App\Core\Session::get('year_review_seen_' . $yrpYear))
        && !(new \App\Repositories\UserUiTourRepository())->isDismissed($yrpUserId, YearReviewCalendar::seenKey($yrpYear))
    ) {
        $yrpPlayer = (new \App\Services\Community\YearReviewService())->forUser($yrpTenantId, $yrpUserId, $yrpYear);
    }
} catch (\Throwable) {
    $yrpPlayer = null;
}
if ($yrpPlayer === null || $yrpYear === null) {
    return;
}

$yrpH = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$yrpTeaser = [];
if ((int) $yrpPlayer['play_s'] > 0) {
    $yrpTeaser[] = [YearReviewStats::hours((int) $yrpPlayer['play_s']), 'de jeu'];
}
if ((int) $yrpPlayer['total_m'] > 0) {
    $yrpTeaser[] = [YearReviewStats::km((int) $yrpPlayer['total_m']), 'parcourus'];
}
if ((int) $yrpPlayer['missions'] > 0) {
    $yrpTeaser[] = [YearReviewStats::int((int) $yrpPlayer['missions']), 'missions'];
}
if ((int) $yrpPlayer['sse_total'] > 0) {
    $yrpTeaser[] = [YearReviewStats::int((int) $yrpPlayer['sse_total']), 'actions SSE'];
}
$yrpTeaser = array_slice($yrpTeaser, 0, 3);
$yrpAction = url('retrospective/' . $yrpYear . '/vu');
?>
<link href="<?= $yrpH(asset_url('assets/css/year-review.css')) ?>" rel="stylesheet">
<div class="yrp-overlay" id="year-review-notice" role="dialog" aria-modal="true" aria-labelledby="yrp-title">
    <form class="yrp-card" method="post" action="<?= $yrpH($yrpAction) ?>" data-yrp-form>
        <?= \App\Core\Csrf::field() ?>
        <button type="submit" class="yrp-x" aria-label="Fermer">×</button>
        <p class="yrp-kicker">Rétrospective</p>
        <p class="yrp-year"><?= (int) $yrpYear ?></p>
        <h2 class="yrp-title" id="yrp-title">Ton année sur le terrain est prête.</h2>
        <p class="yrp-lead">Heures de jeu, kilomètres, blessures, SSE, comptes rendus : revis ton année en quelques écrans et partage tes cartes sur Discord.</p>
        <?php if ($yrpTeaser !== []): ?>
            <ul class="yrp-teaser">
                <?php foreach ($yrpTeaser as [$v, $l]): ?>
                    <li><strong translate="no"><?= $yrpH($v) ?></strong><span><?= $yrpH($l) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="yrp-actions">
            <button type="submit" name="go" value="open" class="yr-btn yr-btn--solid">Voir ma rétrospective</button>
            <button type="submit" class="yr-btn">Plus tard</button>
        </div>
        <p class="yrp-hint">Elle reste disponible dans Mon compte.</p>
    </form>
</div>
<script>
(function () {
    var box = document.getElementById('year-review-notice');
    if (!box) { return; }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && box.parentNode) {
            box.querySelector('.yrp-x').click();
        }
    });
})();
</script>
