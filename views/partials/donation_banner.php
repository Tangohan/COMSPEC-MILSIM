<?php
declare(strict_types=1);

// Bandeau de don (réglage Admin > Paiements > Dons > Bandeau de don) : App\Services\Platform\DonationBanner.
// Inclus par layout.main (pages portail hors administration, et en tête de /dashboard et /back-office
// avec $dnbShellHome) et layout.marketing (site public).
// Jamais sur l'administration, la connexion, le paiement, la page de dons, l'ATAK web et les outils plein écran.
// Fermé par le visiteur : masqué 5 jours (localStorage), revient si l'équipe change le message.

use App\Services\Platform\DonationBanner;

$dnbLocale = function_exists('locale') ? (string) locale() : 'fr';
try {
    $dnb = DonationBanner::forRequest(function_exists('back_office_path_suffix') ? back_office_path_suffix() : '', $dnbLocale, !empty($dnbShellHome));
} catch (\Throwable) {
    $dnb = null;
}
if ($dnb === null || $dnb['message'] === '') {
    return;
}
$dnbE = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$dnbT = static fn (string $k, array $r = []): string => htmlspecialchars(__('site.dnb_' . $k, $r, $dnbLocale), ENT_QUOTES, 'UTF-8');
?>
<?php require base_path('views/partials/donation_banner_markup.php'); ?>
<script>
(function () {
    var el = document.getElementById('athena-donation-banner');
    if (!el) { return; }
    var KEY = 'athena.donationBanner';
    var key = el.getAttribute('data-dnb-key') || '';
    try {
        var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
        if (saved && saved.k === key && saved.until > Date.now()) { el.remove(); return; }
    } catch (e) {}
    var btn = el.querySelector('[data-dnb-close]');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        try { localStorage.setItem(KEY, JSON.stringify({ k: key, until: Date.now() + 5 * 86400000 })); } catch (e) {}
        el.classList.add('is-leaving');
        var done = function () { el.remove(); };
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) { done(); } else { setTimeout(done, 260); }
    });
})();
</script>
