<?php
/** @var list<string> $aircrew */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_head.php';
?>
    <section class="mm-card">
      <h1 class="mm-h1">Sur mon téléphone</h1>
      <p class="mm-muted">Vos apps de l’ATAK, sur votre vrai téléphone, pour la partie en cours.</p>
    </section>
    <div class="mm-tiles">
      <a class="mm-tile" href="<?= $h(url('m/cr')) ?>"><strong>Comptes rendus</strong><span>Contact, SALUTE, SPOTREP, blessé…</span></a>
      <a class="mm-tile" href="<?= $h(url('m/compte-rendu')) ?>"><strong>CR fin d’op</strong><span>Le compte rendu de fin d’opération</span></a>
      <a class="mm-tile" href="<?= $h(url('m/carte')) ?>"><strong>Carte</strong><span>Amis, marqueurs, poser un marqueur</span></a>
      <a class="mm-tile" href="<?= $h(url('m/controle')) ?>"><strong>Contrôle ID</strong><span>Mes contrôles d’identité de la partie</span></a>
      <?php if ($aircrew !== []): ?>
        <a class="mm-tile mm-tile--air" href="<?= $h(url('m/briefs')) ?>"><strong>Briefs air</strong><span>9-LINE reçus par <span translate="no"><?= $h(implode(', ', $aircrew)) ?></span></span></a>
      <?php endif; ?>
    </div>
<?php require __DIR__ . '/_foot.php'; ?>
