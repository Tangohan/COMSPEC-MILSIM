<?php
declare(strict_types=1);

/**
 * Onglets communs du back-office Remontées : fil des remontées (CR et fiches) / tableau par joueur.
 *
 * @var string $rmtNav 'list' | 'players'
 */
$rmtNavActive = (string) ($rmtNav ?? 'list');
$rmtNavH = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<nav class="rmt__views" aria-label="Vues des remontées">
    <a href="<?= $rmtNavH(url('back-office/remontees')) ?>" class="rmt__view<?= $rmtNavActive === 'list' ? ' is-active' : '' ?>"<?= $rmtNavActive === 'list' ? ' aria-current="page"' : '' ?>>
        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 4h10M3 8h10M3 12h6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        Comptes rendus et fiches
    </a>
    <a href="<?= $rmtNavH(url('back-office/remontees/joueurs')) ?>" class="rmt__view<?= $rmtNavActive === 'players' ? ' is-active' : '' ?>"<?= $rmtNavActive === 'players' ? ' aria-current="page"' : '' ?>>
        <svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="5.4" r="2.6" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 13.5c.6-2.6 2.6-4 5-4s4.4 1.4 5 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        Par joueur
    </a>
</nav>
