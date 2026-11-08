<?php

declare(strict_types=1);

/**
 * Sous-menu d'un espace (un seul écran à la fois) : boutons larges côte à côte.
 *
 * @var string $subSet cr | radio
 * @var string $subActive clé du bouton allumé
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$all = [
    'cr' => [
        'new' => ['m/cr', 'Nouveau'],
        'sent' => ['m/cr/envoyes', 'Envoyés'],
        'received' => ['m/cr/recus', 'Reçus'],
    ],
    'radio' => [
        'channels' => ['m/radio', 'Canaux'],
        'private' => ['m/radio/prives', 'Privés'],
        'absent' => ['m/radio/absent', 'SMS, MPU, musique'],
    ],
];
?>
    <nav class="mm-sub" aria-label="Espace">
      <?php foreach ($all[$subSet] ?? [] as $key => [$path, $label]): ?>
        <a class="mm-sub__btn<?= ($subActive ?? '') === $key ? ' is-active' : '' ?>" href="<?= $h(url($path)) ?>"<?= ($subActive ?? '') === $key ? ' aria-current="page"' : '' ?>><?= $h($label) ?></a>
      <?php endforeach; ?>
    </nav>
