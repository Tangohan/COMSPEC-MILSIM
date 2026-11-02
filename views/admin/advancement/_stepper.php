<?php
/**
 * Les cinq étapes d’une campagne. $stepperCurrent (0 = schéma général, 1 à 5 = étape en cours), $stepperDone,
 * $stepperCompact (pastilles sans texte, pour les cartes).
 */
$stepperCurrent = (int) ($stepperCurrent ?? 0);
$stepperDone = !empty($stepperDone);
$stepperCompact = !empty($stepperCompact);
?>
<ol class="advc-steps<?= $stepperCompact ? ' advc-steps--compact' : '' ?>" aria-label="Étapes de la campagne">
    <?php foreach (\App\Support\AdvancementCampaignProgress::STEPS as $n => $step): ?>
        <?php
        $state = '';
        if ($stepperCurrent > 0) {
            $state = ($n < $stepperCurrent || ($stepperDone && $n <= $stepperCurrent)) ? ' is-done' : ($n === $stepperCurrent ? ' is-current' : '');
        }
        ?>
        <li class="advc-step<?= $state ?>"<?= $n === $stepperCurrent && !$stepperDone ? ' aria-current="step"' : '' ?>>
            <span class="advc-step__n" aria-hidden="true"><?= $n ?></span>
            <span class="advc-step__body">
                <strong><?= adv_h($step['label']) ?></strong>
                <?php if (!$stepperCompact): ?>
                    <span><?= adv_h($step['text']) ?></span>
                <?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
</ol>
