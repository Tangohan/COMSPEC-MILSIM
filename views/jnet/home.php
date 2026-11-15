<?php
/**
 * JNET — Espace commun : centre de commandement de l'organisation.
 * Un bandeau (titre, organisation, quatre indicateurs, photo d'équipe en fond), une barre de salons collée sous l'en-tête,
 * le salon actif en pleine largeur et, à droite sur grand écran (repliable), le fil d'activité et les épinglés.
 * Chaque salon est une page servie par le serveur (?salon=) ; jnet_commun.js ne fait que changer de salon sans rechargement,
 * ouvrir le détail d'une unité (tiroir), la recherche rapide (touche /) et répondre aux événements.
 * Tout vient de données réelles : un bloc sans donnée affiche un état vide honnête.
 *
 * @var array<string, mixed> $cm données de JnetCommonSpaceService::page()
 */

use App\Services\Jnet\JnetCommonRules;
use App\Support\JnetUi;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cm = is_array($cm ?? null) ? $cm : [];
$salon = JnetCommonRules::salon((string) ($cm['salon'] ?? ''));
$meta = JnetCommonRules::SALONS[$salon];
$salons = is_array($cm['salons'] ?? null) ? $cm['salons'] : [];
$view = is_array($cm['view'] ?? null) ? $cm['view'] : [];
$pins = is_array($cm['pins'] ?? null) ? $cm['pins'] : [];
$canPin = !empty($cm['canPin']) && !empty($cm['pinsReady']);
$kpis = is_array($cm['kpis'] ?? null) ? $cm['kpis'] : [];
$hero = is_array($cm['hero'] ?? null) ? $cm['hero'] : JnetUi::FORWARD_GROUP_PHOTOS[0];
$orgLabel = (string) ($cm['orgLabel'] ?? 'Organisation');
$postureKey = strtolower((string) ($cm['posture']['key'] ?? 'green'));
$postureClass = in_array($postureKey, ['red', 'amber'], true) ? $postureKey : 'green';
$kpiHref = static fn (string $key): string => url(JnetCommonRules::path((string) ($kpis[$key]['salon'] ?? 'apercu')));
$unitIndex = is_array($cm['unitIndex'] ?? null) ? $cm['unitIndex'] : [];
$ackN = (int) ($kpis['ack']['value'] ?? 0);
$lateN = (int) ($kpis['late']['value'] ?? 0);
?>
<div class="jc" data-jc data-salon="<?= $h($salon) ?>">
    <section class="jc-hero" aria-labelledby="jc-h1">
        <img class="jc-hero__bg" src="<?= $h(asset_url((string) $hero[0])) ?>" srcset="<?= $h(asset_url((string) $hero[1])) ?> 960w, <?= $h(asset_url((string) $hero[0])) ?> <?= (int) $hero[2] ?>w" sizes="100vw" width="<?= (int) $hero[2] ?>" height="<?= (int) $hero[3] ?>" alt="" loading="lazy" decoding="async">
        <div class="jc-hero__in">
            <div class="jc-hero__id">
                <h1 id="jc-h1" class="jc-hero__t">Espace commun<span aria-hidden="true">.</span></h1>
                <p class="jc-hero__lead">Ce qui descend, ce qui circule, qui répond à qui. Un seul endroit pour toute l’organisation.</p>
                <div class="jc-org">
                    <?= JnetUi::crest(mb_strtoupper(mb_substr($orgLabel, 0, 2)), (string) ($cm['orgBadge'] ?? ''), 'jc-org__logo') ?>
                    <strong class="jc-org__name" translate="no"><?= $h($orgLabel) ?></strong>
                    <span class="jc-pill jc-pill--<?= $h($postureClass) ?>"><?= $h((string) ($cm['posture']['label'] ?? '')) ?></span>
                </div>
            </div>
            <ul class="jc-kpis" aria-label="Indicateurs">
                <li><a class="jc-kpi<?= $ackN > 0 ? ' is-al' : '' ?>" href="<?= $h($kpiHref('ack')) ?>" data-jc-salon="<?= $h((string) ($kpis['ack']['salon'] ?? 'annonces')) ?>"><b><?= $ackN ?></b><span>À accuser</span></a></li>
                <li><a class="jc-kpi<?= $lateN > 0 ? ' is-al' : '' ?>" href="<?= $h($kpiHref('late')) ?>" data-jc-salon="<?= $h((string) ($kpis['late']['salon'] ?? 'taches')) ?>"><b><?= !empty($kpis['late']['known']) ? $lateN : '—' ?></b><span>Tâches en retard</span></a></li>
                <li><a class="jc-kpi is-ok" href="<?= $h($kpiHref('active')) ?>" data-jc-salon="<?= $h((string) ($kpis['active']['salon'] ?? 'taches')) ?>"><b><?= (int) ($kpis['active']['value'] ?? 0) ?><em>/ <?= (int) ($kpis['active']['of'] ?? 0) ?></em></b><span>Unités actives · 7 j</span></a></li>
                <li><a class="jc-kpi" href="<?= $h($kpiHref('next')) ?>" data-jc-salon="<?= $h((string) ($kpis['next']['salon'] ?? 'agenda')) ?>"<?= (string) ($kpis['next']['sub'] ?? '') !== '' ? ' title="' . $h((string) $kpis['next']['sub']) . '"' : '' ?>><b><?= (string) ($kpis['next']['value'] ?? '') !== '' ? $h((string) $kpis['next']['value']) : '—' ?></b><span>Prochaine opération</span></a></li>
            </ul>
        </div>
    </section>

    <?php require base_path('views/jnet/commun/_bar.php'); ?>

    <div class="jc-grid">
        <section class="jc-main" id="jc-view" data-jc-view tabindex="-1" aria-labelledby="jc-title">
            <?php if ((string) $meta['tag'] !== ''): ?>
                <header class="jc-vh">
                    <h2 id="jc-title" class="jc-vh__t"><?= $h((string) $meta['tag']) ?></h2>
                    <p class="jc-vh__d"><?= $h((string) $meta['desc']) ?></p>
                </header>
            <?php else: ?>
                <h2 id="jc-title" class="ja-sr"><?= $h((string) $meta['label']) ?></h2>
            <?php endif; ?>

            <?php
            // Les épinglés du salon, en tête (le salon « Épinglés » les liste tous, plus bas).
            if ($salon !== 'epingles') {
                require base_path('views/jnet/commun/_pinned_top.php');
            }
            switch ($salon) {
                case 'apercu':
                    require base_path('views/jnet/commun/_apercu.php');
                    break;
                case 'annonces':
                    require base_path('views/jnet/commun/_annonces.php');
                    break;
                case 'taches':
                    require base_path('views/jnet/commun/_taches.php');
                    break;
                case 'voie':
                    require base_path('views/jnet/commun/_voie.php');
                    break;
                case 'agenda':
                    require base_path('views/jnet/commun/_agenda.php');
                    break;
                case 'renseignements':
                    require base_path('views/jnet/commun/_renseignements.php');
                    break;
                case 'cibles':
                    require base_path('views/jnet/commun/_cibles.php');
                    break;
                default:
                    require base_path('views/jnet/commun/_epingles.php');
            }
            ?>
        </section>

        <?php require base_path('views/jnet/commun/_aside.php'); ?>
    </div>

    <div class="jc-scrim" data-jc-scrim hidden></div>
    <aside class="jc-dw" data-jc-drawer role="dialog" aria-modal="true" aria-label="Détail de l’unité" tabindex="-1" hidden>
        <div class="jc-dw__in" data-jc-drawer-body></div>
    </aside>
    <div class="jc-pal" data-jc-pal role="dialog" aria-modal="true" aria-label="Recherche rapide" hidden>
        <div class="jc-pal__box">
            <label class="jc-pal__in">
                <?= JnetCommonRules::icon('search') ?>
                <span class="ja-sr">Chercher un salon ou une unité</span>
                <input type="search" data-jc-pal-q placeholder="Salon, unité, chef, indicatif…" autocomplete="off" role="combobox" aria-expanded="true" aria-controls="jc-pal-res">
                <kbd>Échap</kbd>
            </label>
            <div class="jc-pal__res" id="jc-pal-res" role="listbox" aria-label="Résultats" data-jc-pal-res></div>
        </div>
    </div>
    <div class="jc-toast" data-jc-toast role="status" aria-live="polite"></div>

    <div hidden data-jc-t>
        <span data-t="salons">Salons</span>
        <span data-t="units">Unités</span>
        <span data-t="none">Aucun résultat.</span>
        <span data-t="copied">Indicatif copié</span>
        <span data-t="rsvp">Réponse enregistrée</span>
        <span data-t="rsvpOff">Réponse retirée</span>
        <span data-t="net">Erreur de connexion, rien n’a été enregistré.</span>
        <span data-t="expand">Tout déplier</span>
        <span data-t="collapse">Tout replier</span>
        <span data-t="sub">sous-unités</span>
    </div>
    <script type="application/json" data-jc-units><?= json_encode($unitIndex, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
</div>
