<?php
declare(strict_types=1);
/**
 * Guide JNET repliable. $guideMode : 'org' (espace commun) ou 'unit' (espace d’unité).
 * Ouvert d’office tant que l’espace n’a encore aucun échange ($guideOpen).
 * @var string $guideMode
 * @var bool $guideOpen
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$mode = ($guideMode ?? 'org') === 'unit' ? 'unit' : 'org';
?>
<details class="jn-guide"<?= !empty($guideOpen) ? ' open' : '' ?>>
    <summary>
        <span class="jn-guide__icon" aria-hidden="true">?</span>
        <span class="jn-guide__title"><?= $mode === 'org' ? 'Comment fonctionne JNET' : 'Comment fonctionne cet espace' ?></span>
        <span class="jn-guide__toggle">Afficher / masquer</span>
    </summary>
    <?php if ($mode === 'org'): ?>
        <ol class="jn-guide__steps">
            <li>
                <strong>Un espace par unité.</strong>
                Chaque unité de votre chaîne de commandement a son propre espace. Vos espaces sont listés à gauche, dans « Mes espaces », du sommet jusqu’à votre unité.
            </li>
            <li>
                <strong>On échange dans la chaîne.</strong>
                Depuis l’espace d’une unité, on publie un ordre, un compte rendu, un renseignement ou un document. On l’adresse à l’unité, à son échelon supérieur ou à ses sous-unités.
            </li>
            <li>
                <strong>Chacun voit ce qui le concerne.</strong>
                Le commandement voit tout ce qui se passe en dessous de lui. Une unité reçoit ce qui est adressé à elle ou au-dessus d’elle. Une unité voisine ne voit pas vos échanges internes.
            </li>
            <li>
                <strong>Ici, la vue d’ensemble.</strong>
                L’espace commun montre la situation de toutes les unités et le flux de tous les échanges que vous pouvez voir. Cliquez sur une unité pour entrer dans son espace.
            </li>
        </ol>
    <?php else: ?>
        <ol class="jn-guide__steps">
            <li>
                <strong>Reçu</strong> : ce que le commandement (ou une unité partenaire) a adressé à cette unité. Les ordres demandent un « Accuser réception ».
            </li>
            <li>
                <strong>Dans l’unité</strong> : le fil de l’unité et de ses sous-unités. Pour publier, choisissez le type, écrivez un titre clair, cochez les destinataires, puis « Publier ».
            </li>
            <li>
                <strong>Remonté</strong> : ce que l’unité a transmis à son échelon supérieur (comptes rendus, renseignement). Cochez l’échelon supérieur dans « Diffuser à » pour remonter une information.
            </li>
            <li>
                <strong>Naviguer</strong> : le fil en haut de page remonte la chaîne ; « sous-unités » descend d’un échelon.
            </li>
        </ol>
    <?php endif; ?>
</details>
