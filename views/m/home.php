<?php

declare(strict_types=1);
/**
 * @var list<string> $aircrew
 * @var int $unread comptes rendus reçus non lus
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_head.php';
?>
    <div class="mm-tiles mm-tiles--home">
      <a class="mm-tile" href="<?= $h(url('m/cr')) ?>"><strong>Comptes rendus</strong><span>Envoyer, relire l’historique<?php if ($unread > 0): ?> · <b class="mm-badge mm-badge--new"><?= (int) $unread ?> à lire</b><?php endif; ?></span></a>
      <a class="mm-tile" href="<?= $h(url('m/compte-rendu')) ?>"><strong>Fin d’opération</strong><span>Le compte rendu de fin de partie</span></a>
      <a class="mm-tile" href="<?= $h(url('m/carte')) ?>"><strong>Carte</strong><span>Amis, marqueurs, poser un marqueur</span></a>
      <a class="mm-tile" href="<?= $h(url('m/radio')) ?>"><strong>Radio et messages</strong><span>Canaux de tchat, messages privés</span></a>
      <a class="mm-tile" href="<?= $h(url('m/radio/mpu')) ?>"><strong>Radio et MPU5</strong><span>Mon poste, MPU5 de mon camp</span></a>
      <a class="mm-tile" href="<?= $h(url('m/musique')) ?>"><strong>Musique</strong><span>Écouter la bibliothèque d’Athena</span></a>
      <a class="mm-tile" href="<?= $h(url('m/controle')) ?>"><strong>Contrôle d’identité</strong><span>Mes contrôles de la partie</span></a>
      <?php if ($aircrew !== []): ?>
        <a class="mm-tile mm-tile--air" href="<?= $h(url('m/briefs')) ?>"><strong>Briefs air</strong><span>Reçus par <span translate="no"><?= $h(implode(', ', $aircrew)) ?></span></span></a>
      <?php endif; ?>
    </div>
<?php require __DIR__ . '/_foot.php'; ?>
