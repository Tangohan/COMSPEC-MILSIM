<?php
declare(strict_types=1);
/**
 * Guide « Comment fonctionne JNET », repliable. Le choix « masquer » est mémorisé dans le navigateur.
 * @var string $guideContext 'org' | 'unit'
 */
$guideContext = ($guideContext ?? 'org') === 'unit' ? 'unit' : 'org';
?>
<details class="jn-guide" data-jn-guide open>
    <summary>
        <span class="jn-guide__icon" aria-hidden="true">?</span>
        <span class="jn-guide__title">Comment fonctionne JNET</span>
        <span class="jn-guide__toggle" data-open="Masquer le guide" data-closed="Afficher le guide"></span>
    </summary>
    <ol class="jn-guide__steps">
        <li>
            <strong>Un espace par unité</strong>
            <span>
                L’espace commun réunit toute l’organisation. Chaque unité de l’organigramme a aussi son propre espace.
                <?= $guideContext === 'org'
                    ? 'Ouvrez une unité dans l’arbre ci-dessous, ou dans « Mes espaces » à gauche (votre chaîne de commandement).'
                    : 'Le fil en haut permet de remonter vers l’échelon supérieur ou de descendre vers les sous-unités.' ?>
            </span>
        </li>
        <li>
            <strong>Des échanges qui suivent la chaîne</strong>
            <span>
                Un échange part d’un espace et vise l’unité elle-même, son échelon supérieur ou ses subordonnés.
                Dans un espace d’unité, il se range dans <b>Reçu</b> (vient d’en haut), <b>Dans l’unité</b> (fil interne) ou <b>Remonté</b> (envoyé vers le haut).
            </span>
        </li>
        <li>
            <strong>Qui voit quoi</strong>
            <span>
                Les membres d’une unité voient ses échanges ; l’encadrement au-dessus voit aussi ce qui se passe en dessous ;
                les unités voisines ne voient rien. Un <b>ordre</b> demande un accusé de lecture : « lu par 3 / 8 ».
            </span>
        </li>
    </ol>
</details>
