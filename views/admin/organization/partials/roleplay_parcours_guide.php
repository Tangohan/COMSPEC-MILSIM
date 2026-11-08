<?php
declare(strict_types=1);

/*
 * Repère commun aux onglets du bureau de suivi : trois choses portent le mot « étape », voici laquelle est laquelle.
 *
 * @var string $rpGuideHere 'suivi' | 'immersion' | 'phases'
 */
$rpGuideHere = (string) ($rpGuideHere ?? 'suivi');
$rpGuideCards = [
    'suivi' => [
        'href' => url('back-office/roleplay-followup'),
        'kicker' => 'Au quotidien',
        'title' => 'Bureau de suivi',
        'text' => 'Qui a besoin de quoi aujourd’hui : tuteur, étape, bilan, échéance.',
    ],
    'immersion' => [
        'href' => url('back-office/roleplay-followup?onglet=immersion'),
        'kicker' => 'L’arrivée du membre',
        'title' => 'Parcours d’immersion',
        'text' => 'Les étapes que le staff fait avancer à la main, et le rythme des bilans.',
    ],
    'phases' => [
        'href' => url('back-office/roleplay-followup?onglet=phases'),
        'kicker' => 'La suite de sa carrière',
        'title' => 'Parcours RH',
        'text' => 'Les étapes avec conditions : heures, qualifications, présences. Validées par un responsable ou automatiques.',
    ],
];
?>
<nav class="rps-guide" aria-label="Les trois parties du suivi">
    <?php foreach ($rpGuideCards as $key => $card): ?>
    <?php if ($key === $rpGuideHere): ?>
    <div class="rps-guide__card is-here" aria-current="page">
        <span class="rps-guide__kicker"><?= htmlspecialchars($card['kicker'], ENT_QUOTES, 'UTF-8') ?> · vous êtes ici</span>
        <strong><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></strong>
        <span><?= htmlspecialchars($card['text'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php else: ?>
    <a class="rps-guide__card" href="<?= htmlspecialchars($card['href'], ENT_QUOTES, 'UTF-8') ?>">
        <span class="rps-guide__kicker"><?= htmlspecialchars($card['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
        <strong><?= htmlspecialchars($card['title'], ENT_QUOTES, 'UTF-8') ?></strong>
        <span><?= htmlspecialchars($card['text'], ENT_QUOTES, 'UTF-8') ?></span>
    </a>
    <?php endif; ?>
    <?php endforeach; ?>
</nav>
