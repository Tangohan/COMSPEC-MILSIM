<?php
declare(strict_types=1);

/**
 * Back-office « Remontées » : les pages vivent désormais au bureau SSE. Affichée (403) à la place de la redirection
 * quand la session ne peut pas entrer au bureau : elle dit pourquoi et comment y entrer, au lieu d'une page vide.
 * Le titre de la page vient du shell du back-office (variable title).
 *
 * @var string $movedTarget adresse de la page au bureau SSE
 * @var bool $movedHadAccess la session avait le droit du back-office Remontées
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<section class="ath-card bo-moved" aria-labelledby="bo-moved-title" role="alert">
    <h2 id="bo-moved-title">Accès au bureau SSE requis</h2>
    <p>Les remontées du terrain (comptes rendus, fiches de renseignement, suivi par joueur et réglages) se traitent désormais dans le bureau SSE. Votre session n’y est pas encore entrée.</p>
    <?php if (!empty($movedHadAccess)): ?>
        <p>Votre droit sur les remontées est conservé : entrez au bureau avec votre compte ou un code d’accès, puis rouvrez cette page.</p>
    <?php else: ?>
        <p>Demandez l’accès au bureau SSE à un responsable du renseignement de votre communauté.</p>
    <?php endif; ?>
    <p><a class="ath-btn ath-btn--solid" href="<?= $h(url('atak/sse')) ?>">Entrer au bureau SSE</a></p>
    <p class="bo-moved__dim">Adresse de cette page au bureau SSE :<span translate="no"><?= $h((string) ($movedTarget ?? '')) ?></span></p>
</section>
