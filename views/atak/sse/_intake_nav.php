<?php
declare(strict_types=1);

/**
 * Bureau SSE — onglets communs des remontées : file (comptes rendus et fiches), tableau par joueur, réglages.
 *
 * @var string $rmtNav 'list' | 'players' | 'settings'
 */
$rmtNavActive = (string) ($rmtNav ?? 'list');
$rmtNavH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rmtNavItems = [
    'list' => ['atak/sse/remontees', 'Comptes rendus et fiches', '<path d="M3 4h10M3 8h10M3 12h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'],
    'players' => ['atak/sse/remontees/joueurs', 'Par joueur', '<circle cx="8" cy="5.4" r="2.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 13.5c.6-2.6 2.6-4 5-4s4.4 1.4 5 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'],
    'settings' => ['atak/sse/remontees/reglages', 'Réglages', '<circle cx="8" cy="8" r="2.2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 1.8v2M8 12.2v2M1.8 8h2M12.2 8h2M3.6 3.6l1.4 1.4M11 11l1.4 1.4M3.6 12.4 5 11M11 5l1.4-1.4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'],
];
?>
<nav class="rmt__views" aria-label="Vues des remontées">
    <?php foreach ($rmtNavItems as $rmtKey => [$rmtPath, $rmtLabel, $rmtIcon]): $rmtOn = $rmtNavActive === $rmtKey; ?>
        <a href="<?= $rmtNavH(url($rmtPath)) ?>" class="rmt__view<?= $rmtOn ? ' is-active' : '' ?>"<?= $rmtOn ? ' aria-current="page"' : '' ?>>
            <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><?= $rmtIcon ?></svg>
            <?= $rmtNavH($rmtLabel) ?>
        </a>
    <?php endforeach; ?>
</nav>
