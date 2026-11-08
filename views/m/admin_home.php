<?php

declare(strict_types=1);

/**
 * Accueil de l'espace d'administration : un choix à la fois, grandes tuiles.
 *
 * @var string $adminRole manager | platform
 * @var bool $adminSessions
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_admin_head.php';
?>
    <div class="mm-tiles mm-tiles--home">
      <a class="mm-tile" href="<?= $h(url('m/admin/joueurs')) ?>"><strong>Joueurs et ATAK</strong><span>Voir, écrire, bloquer, déconnecter</span></a>
      <?php if ($adminSessions): ?>
        <a class="mm-tile" href="<?= $h(url('m/admin/sessions')) ?>"><strong>Sessions</strong><span>Créer une session de jeu</span></a>
      <?php endif; ?>
      <a class="mm-tile" href="<?= $h(url('m/admin/cr')) ?>"><strong>Compte rendu de fin d’opération</strong><span>L’envoyer aux joueurs connectés</span></a>
    </div>
<?php require __DIR__ . '/_admin_foot.php'; ?>
