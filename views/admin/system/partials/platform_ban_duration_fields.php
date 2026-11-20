<?php

declare(strict_types=1);

$bTomorrow = gmdate('Y-m-d', time() + 86400);
$durations = [
    'definitif' => 'Sans fin',
    '1j' => '1 jour',
    '7j' => '7 jours',
    '30j' => '30 jours',
    '90j' => '90 jours',
    'date' => 'Jusqu’à une date',
];
?>
<fieldset class="pu-dialog__field pu-duration">
    <legend>Durée de la sanction</legend>
    <div class="pu-duration__choices">
        <?php foreach ($durations as $value => $text): $value = (string) $value; ?>
            <?php $disabled = !$bSupportsEnd && $value !== 'definitif'; ?>
            <label class="pu-check<?= $disabled ? ' is-disabled' : '' ?>">
                <input type="radio" name="duree" value="<?= $bh($value) ?>"<?= $value === $defaultBanDuration ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?>>
                <?= $bh($text) ?>
            </label>
        <?php endforeach; ?>
    </div>
    <label class="pu-dialog__sub">
        <span>Date de fin (la sanction court jusqu’à la fin de ce jour, heure de Paris)</span>
        <input type="date" name="fin" min="<?= $bh($bTomorrow) ?>" data-ban-date<?= $bSupportsEnd ? '' : ' disabled' ?>>
    </label>
    <?php if (!$bSupportsEnd): ?>
        <p class="pu-muted">Les sanctions avec date de fin arrivent avec la prochaine mise à jour de la base : pour l’instant, seul le bannissement sans fin est possible.</p>
    <?php endif; ?>
</fieldset>
