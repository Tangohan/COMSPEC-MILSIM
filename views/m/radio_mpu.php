<?php

declare(strict_types=1);

/**
 * Radio et MPU5 du joueur, et MPU5 de son camp : seules les valeurs remontées par le jeu, sinon un état clair.
 *
 * @var array<string, mixed>|null $me
 * @var list<array<string, mixed>> $peers
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dash = static fn ($v): string => trim((string) $v) === '' ? '—' : (string) $v;
$subSet = 'radio';
$subActive = 'mpu';
require __DIR__ . '/_head.php';
require __DIR__ . '/_sub.php';
?>
    <section class="mm-card">
      <h1 class="mm-h1">Mon poste</h1>
      <?php if ($me === null): ?>
        <p class="mm-muted">Votre personnage n’envoie pas de position sur cette carte pour le moment : aucun état à afficher.</p>
        <p class="mm-state" data-st="pending">Hors liaison</p>
      <?php else: ?>
        <dl class="mm-facts">
          <dt>Indicatif</dt><dd translate="no"><?= $h($dash($me['callsign'])) ?></dd>
          <dt>Liaison</dt><dd><?= $h($dash($me['link'])) ?></dd>
          <dt>Radio</dt><dd><?= !empty($me['radio_module']) ? 'Module actif' : 'Module non détecté' ?></dd>
          <dt>Réseau</dt><dd translate="no"><?= $h($dash($me['radio_net'])) ?></dd>
          <dt>Canal</dt><dd translate="no"><?= $h($dash($me['radio_channel'])) ?></dd>
          <dt>Latence</dt><dd><?= $me['latency_ms'] !== null ? (int) $me['latency_ms'] . ' ms' : '—' ?></dd>
          <dt>Perte</dt><dd><?= $me['packet_loss'] !== null ? $h((string) $me['packet_loss']) . ' %' : '—' ?></dd>
        </dl>
        <h2 class="mm-h2">MPU5</h2>
        <?php if (empty($me['mpu'])): ?>
          <p class="mm-state" data-st="pending">Aucune MPU5 transmise par le jeu</p>
        <?php else: ?>
          <dl class="mm-facts">
            <dt>Nœud</dt><dd translate="no"><?= $h($dash($me['node'])) ?></dd>
            <dt>Fréquence</dt><dd translate="no"><?= $h($dash($me['freq'])) ?></dd>
            <dt>Groupe</dt><dd translate="no"><?= $h($dash($me['tg'])) ?></dd>
            <dt>Passerelle</dt><dd><?= !empty($me['gateway']) ? 'Oui' : 'Non' ?></dd>
            <dt>Pont radio</dt><dd><?= !empty($me['bridge']) ? 'Actif' : 'Inactif' ?></dd>
          </dl>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <section class="mm-card">
      <h2 class="mm-h1">MPU5 de mon camp</h2>
      <?php if ($peers === []): ?>
        <p class="mm-muted">Aucune autre MPU5 transmise pour votre camp.</p>
      <?php else: ?>
        <ul class="mm-rows">
          <?php foreach ($peers as $p): ?>
            <li>
              <strong translate="no"><?= $h($dash($p['callsign'])) ?></strong>
              <span class="mm-mono" translate="no"><?= $h($dash($p['node'])) ?> · <?= $h($dash($p['freq'])) ?> · TG <?= $h($dash($p['tg'])) ?></span>
              <?php if (!empty($p['gateway'])): ?><span class="mm-badge">Passerelle</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="mm-muted">Le maillage du téléphone ATAK (voisins, groupes, chiffrement, qualité des liens) reste dans le jeu : il n’est pas encore transmis à Athena.</p>
    </section>
    <a class="mm-btn" href="<?= $h(url('m/radio/mpu')) ?>">Actualiser</a>
<?php require __DIR__ . '/_foot.php'; ?>
