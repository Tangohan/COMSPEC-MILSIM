<?php

declare(strict_types=1);

/**
 * Bloc « Affichage des qualifications » : deux interrupteurs par qualification (visible sur Arma, visible sur le profil),
 * enregistrés sans recharger la page (public/assets/js/qualification-display.js), et « Tout afficher / Tout masquer »
 * par colonne. Sert à la fiche Effectifs d'un membre et à « Mes qualifications » (JNET).
 *
 * Attendu : $qualificationDisplay (QualificationDisplayService::panel()) ; $qdHeading ('h2' ou 'h3') ; $qdClass (classes
 * de la carte hôte) ; $qdId (ancre). Un lecteur sans droit, ou un site pas encore mis à jour, voit l'état sans pouvoir
 * le changer, avec la raison écrite.
 */
$qd = is_array($qualificationDisplay ?? null) ? $qualificationDisplay : null;
if ($qd === null) {
    return;
}
$qdH = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
$qdRows = is_array($qd['rows'] ?? null) ? $qd['rows'] : [];
$qdReady = !empty($qd['ready']);
$qdActive = $qdReady && !empty($qd['can_toggle']);
// « Tout afficher / Tout masquer » : seulement s'il y a plusieurs qualifications à basculer.
$qdBulk = $qdActive && count($qdRows) > 1;
$qdTag = in_array(($qdHeading ?? 'h3'), ['h2', 'h3'], true) ? $qdHeading : 'h3';
$qdAnchor = $qdH($qdId ?? 'qualifications-affichage');
$qdColumns = [
    \App\Services\Personnel\QualificationDisplayService::GAME => 'Visible sur Arma',
    \App\Services\Personnel\QualificationDisplayService::PROFILE => 'Visible sur le profil',
];
?>
<article class="qd <?= $qdH($qdClass ?? '') ?>" id="<?= $qdAnchor ?>" aria-labelledby="<?= $qdAnchor ?>-titre"
    <?php if ($qdActive): ?>data-qd data-qd-url="<?= $qdH($qd['url'] ?? '') ?>" data-qd-csrf="<?= $qdH(\App\Core\Csrf::token()) ?>"<?php endif; ?>>
    <<?= $qdTag ?> class="qd__title" id="<?= $qdAnchor ?>-titre">Affichage des qualifications</<?= $qdTag ?>>
    <p class="qd__lead">Masquer une qualification la retire de l’affichage, sur Arma ou sur le profil vu par les autres : elle reste acquise et compte toujours pour les accès et les formations.</p>

    <?php if (!$qdReady): ?>
        <p class="qd__note" role="note">Réglage indisponible tant que la mise à jour du site n’est pas appliquée : toutes les qualifications restent visibles.</p>
    <?php elseif (!$qdActive): ?>
        <p class="qd__note" role="note">Lecture seule : seuls le membre et les responsables des fiches peuvent changer ces choix.</p>
    <?php endif; ?>

    <?php if ($qdRows === []): ?>
        <p class="qd__empty">Aucune qualification enregistrée : rien à afficher ni à masquer pour l’instant.</p>
    <?php else: ?>
        <div class="qd__grid" role="table" aria-labelledby="<?= $qdAnchor ?>-titre">
            <div class="qd__head<?= $qdBulk ? '' : ' qd__head--plain' ?>" role="row">
                <span class="qd__col qd__col--name" role="columnheader">Qualification</span>
                <?php foreach ($qdColumns as $qdSurface => $qdLabel): ?>
                    <div class="qd__col" role="columnheader">
                        <span class="qd__col-label"><?= $qdH($qdLabel) ?></span>
                        <?php if ($qdBulk): ?>
                            <span class="qd__bulk" role="group" aria-label="<?= $qdH($qdLabel) ?>">
                                <button type="button" class="qd__bulk-btn" data-qd-bulk="<?= $qdH($qdSurface) ?>" data-qd-visible="1">Tout afficher</button>
                                <button type="button" class="qd__bulk-btn" data-qd-bulk="<?= $qdH($qdSurface) ?>" data-qd-visible="0">Tout masquer</button>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <span class="qd__col qd__col--state" role="columnheader"><span class="qd__sr">Enregistrement</span></span>
            </div>
            <?php foreach ($qdRows as $qdRow):
                $qdRowId = (int) ($qdRow['id'] ?? 0);
                $qdName = (string) ($qdRow['name'] ?? '');
                ?>
                <div class="qd__row" role="row" data-qd-row="<?= $qdRowId ?>">
                    <div class="qd__name" role="cell">
                        <b translate="no" id="<?= $qdAnchor ?>-q<?= $qdRowId ?>"><?= $qdH($qdName) ?></b>
                        <?php if (($qdRow['level'] ?? '') !== '' || ($qdRow['date'] ?? '') !== ''): ?>
                            <small>
                                <?php if (($qdRow['level'] ?? '') !== ''): ?><span translate="no"><?= $qdH($qdRow['level']) ?></span><?php endif; ?>
                                <?php if (($qdRow['level'] ?? '') !== '' && ($qdRow['date'] ?? '') !== ''): ?><span aria-hidden="true"> · </span><?php endif; ?>
                                <?php if (($qdRow['date'] ?? '') !== ''): ?><span>Obtenue le <?= $qdH($qdRow['date']) ?></span><?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <?php foreach ($qdColumns as $qdSurface => $qdLabel):
                        $qdOn = !empty($qdRow[$qdSurface]);
                        ?>
                        <div class="qd__cell" role="cell">
                            <span class="qd__cell-label" aria-hidden="true"><?= $qdH($qdLabel) ?></span>
                            <button type="button" class="qd-sw" role="switch" aria-checked="<?= $qdOn ? 'true' : 'false' ?>"
                                aria-label="<?= $qdH($qdLabel) ?>" aria-describedby="<?= $qdAnchor ?>-q<?= $qdRowId ?>"
                                data-qd-switch="<?= $qdH($qdSurface) ?>" <?= $qdActive ? '' : 'disabled' ?>>
                                <span class="qd-sw__track" aria-hidden="true"><span class="qd-sw__thumb"></span></span>
                                <span class="qd-sw__txt qd-sw__txt--on" aria-hidden="true">Oui</span>
                                <span class="qd-sw__txt qd-sw__txt--off" aria-hidden="true">Non</span>
                            </button>
                        </div>
                    <?php endforeach; ?>
                    <div class="qd__state" role="cell"><span role="status" aria-live="polite" data-qd-state></span></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($qdActive): ?>
            <p class="qd__status" role="status" aria-live="polite" data-qd-status></p>
            <div hidden>
                <span data-qd-msg="saved">Enregistré</span>
                <span data-qd-msg="saved_all">Colonne enregistrée</span>
                <span data-qd-msg="forbidden">Refusé : vous ne pouvez pas changer l’affichage des qualifications de ce membre.</span>
                <span data-qd-msg="not_ready">Non enregistré : la mise à jour du site n’est pas encore appliquée.</span>
                <span data-qd-msg="not_found">Non enregistré : qualification introuvable. Rechargez la page.</span>
                <span data-qd-msg="csrf">Non enregistré : session expirée. Rechargez la page.</span>
                <span data-qd-msg="error">Non enregistré : réessayez dans un instant.</span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</article>
