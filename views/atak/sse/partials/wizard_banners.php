<?php
declare(strict_types=1);
/** Bandeaux communs des assistants de création : brouillon retrouvé, saisie restaurée après une erreur, mots utilisés par le récapitulatif. */
?>
<div class="wz-banner" data-wz-draft role="status" hidden>
    <span>Brouillon retrouvé : vous reprenez là où vous vous étiez arrêté.</span>
    <button type="button" class="wz-linkbtn" data-wz-reset>Repartir de zéro</button>
</div>
<div class="wz-banner" data-wz-restored role="status" hidden>
    <span>L’enregistrement a échoué, mais votre saisie a été conservée : vérifiez-la puis recommencez.</span>
    <button type="button" class="wz-linkbtn" data-wz-reset>Repartir de zéro</button>
</div>
<span hidden data-wz-none>Non renseigné</span>
<span hidden data-wz-yes>Oui</span>
<span hidden data-wz-no>Non</span>
