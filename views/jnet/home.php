<?php
/**
 * JNET — Espace commun : centre de commandement de l'organisation, à la Discord.
 * À gauche la barre des salons (sections repliables, pastilles de non-lus), au centre le salon actif en pleine largeur,
 * à droite (grands écrans, repliable) les éléments épinglés et l'activité récente des unités.
 * Chaque salon est une page servie par le serveur (?salon=) ; jnet_commun.js ne fait que changer de salon sans rechargement.
 * Tout vient de données réelles : un bloc sans donnée affiche un état vide honnête.
 *
 * @var array<string, mixed> $cm données de JnetCommonSpaceService::page()
 */

use App\Services\Jnet\JnetCommonRules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cm = is_array($cm ?? null) ? $cm : [];
$salon = JnetCommonRules::salon((string) ($cm['salon'] ?? ''));
$meta = JnetCommonRules::SALONS[$salon];
$salons = is_array($cm['salons'] ?? null) ? $cm['salons'] : [];
$view = is_array($cm['view'] ?? null) ? $cm['view'] : [];
$pins = is_array($cm['pins'] ?? null) ? $cm['pins'] : [];
$canPin = !empty($cm['canPin']) && !empty($cm['pinsReady']);
?>
<div class="jc" data-jc data-salon="<?= $h($salon) ?>">
    <details class="jc-drawer">
        <summary class="jc-drawer__btn">
            <span class="jc-drawer__cur"><?= JnetCommonRules::icon($meta['icon']) ?><span><?= $h($meta['label']) ?></span></span>
            <span class="jc-drawer__more">Changer de salon</span>
        </summary>
        <nav class="jc-drawer__list" aria-label="Salons (mobile)">
            <?php foreach ($salons as $s): ?>
                <a class="jc-ch<?= !empty($s['active']) ? ' is-active' : '' ?>" href="<?= $h((string) $s['href']) ?>"<?= !empty($s['active']) ? ' aria-current="page"' : '' ?>>
                    <?= JnetCommonRules::icon((string) $s['icon']) ?><span class="jc-ch__t"><?= $h((string) $s['label']) ?></span>
                    <?php if ((int) $s['badge'] > 0): ?><em class="jc-badge<?= !empty($s['badgeIsUnread']) ? ' is-unread' : '' ?>"><?= min(99, (int) $s['badge']) ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </details>

    <?php require base_path('views/jnet/commun/_rail.php'); ?>

    <section class="jc-main" id="jc-view" data-jc-view tabindex="-1" aria-labelledby="jc-title">
        <header class="jc-head">
            <div class="jc-head__id">
                <span class="jc-head__ic"><?= JnetCommonRules::icon($meta['icon']) ?></span>
                <div>
                    <h1 id="jc-title" class="jc-head__title"><?= $h($meta['label']) ?></h1>
                    <p class="jc-head__desc"><?= $h($meta['desc']) ?></p>
                </div>
            </div>
            <div class="jc-head__act">
                <button type="button" class="jc-iconbtn" data-jc-aside-toggle aria-pressed="true" aria-controls="jc-aside" title="Afficher ou masquer le panneau des épinglés">
                    <?= JnetCommonRules::icon('panel') ?><span class="jc-iconbtn__t">Panneau</span>
                </button>
            </div>
        </header>

        <?php
        // Les épinglés du salon, en tête (le salon « Épinglés » les liste tous, plus bas).
        if ($salon !== 'epingles') {
            require base_path('views/jnet/commun/_pinned_top.php');
        }
        switch ($salon) {
            case 'annonces':
                require base_path('views/jnet/commun/_annonces.php');
                break;
            case 'taches':
                require base_path('views/jnet/commun/_taches.php');
                break;
            case 'voie':
                require base_path('views/jnet/commun/_voie.php');
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
