<?php

declare(strict_types=1);

/**
 * Fiche d'un joueur et de son ATAK : état, puis une action à la fois (message, blocage, déconnexion, compromission).
 *
 * @var array<string, mixed>|null $row
 * @var string $adminRole
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$levels = \App\Repositories\AtakAdminControlRepository::LEVELS;
require __DIR__ . '/_admin_head.php';
?>
<?php if ($row === null): ?>
    <section class="mm-card mm-card--warn">
      <h1 class="mm-h1">Joueur introuvable</h1>
      <p class="mm-muted">Cet ATAK n’existe pas ou n’est pas dans votre communauté.</p>
      <a class="mm-btn" href="<?= $h(url('m/admin/joueurs')) ?>">RETOUR AUX JOUEURS</a>
    </section>
<?php else: ?>
    <?php
    $id = (int) $row['id'];
    $blocked = (int) $row['block_id'] > 0;
    // Un blocage posé pour toutes les communautés ne se lève que depuis la plateforme (règle de l'app Admin du jeu).
    $canLift = $blocked && ($row['block_scope'] === 'community' || $adminRole === 'platform');
    $compromised = $row['compromise'] !== 'none';
    ?>
    <section class="mm-card">
      <h1 class="mm-h1" translate="no"><?= $h($row['label']) ?></h1>
      <p class="mm-muted">
        <?= $blocked ? 'Bloqué' . ($row['block_scope'] === 'all' ? ' par la plateforme' : '') . ($row['block_reason'] !== '' ? ' : ' : '') : 'Non bloqué' ?><?php if ($blocked && $row['block_reason'] !== ''): ?><span translate="no"><?= $h($row['block_reason']) ?></span><?php endif; ?>
        · <?= $compromised ? ($row['compromise'] === 'captured' ? 'Capturé' : 'Compromis') : 'Contrôle normal' ?>
        <?php if ($row['account'] === 'none'): ?>· Sans compte Athena<?php endif; ?>
        <?php if ($row['account'] === 'disabled'): ?>· Compte fermé<?php endif; ?>
      </p>
    </section>

    <details class="mm-acc" name="act" open>
      <summary>Envoyer un message</summary>
      <form class="mm-form" data-admin-form="message" data-reset>
        <input type="hidden" name="terminal_id" value="<?= $id ?>">
        <div class="mm-field"><label class="mm-label" for="m-title">Titre</label><input class="mm-input" id="m-title" name="title" maxlength="120" required></div>
        <div class="mm-field"><label class="mm-label" for="m-body">Message</label><textarea class="mm-input" id="m-body" name="body" rows="4" maxlength="2000" required></textarea></div>
        <div class="mm-field"><label class="mm-label" for="m-level">Importance</label>
          <select class="mm-input" id="m-level" name="level">
            <?php foreach ($levels as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <button class="mm-btn mm-btn--send" type="submit">ENVOYER</button>
        <p class="mm-muted" data-admin-result></p>
      </form>
    </details>

    <details class="mm-acc" name="act">
      <summary>Bloquer ou débloquer</summary>
      <?php if ($blocked): ?>
        <?php if ($canLift): ?>
          <form class="mm-form" data-admin-form="unblock" data-reload>
            <input type="hidden" name="block_id" value="<?= (int) $row['block_id'] ?>">
            <button class="mm-btn mm-btn--send" type="submit">LEVER LE BLOCAGE</button>
            <p class="mm-muted" data-admin-result></p>
          </form>
        <?php else: ?>
          <p class="mm-muted">Ce blocage vient de la plateforme : seule elle peut le lever.</p>
        <?php endif; ?>
      <?php else: ?>
        <form class="mm-form" data-admin-form="block" data-reload data-confirm="Bloquer cet ATAK dans la communauté ?">
          <input type="hidden" name="terminal_id" value="<?= $id ?>">
          <div class="mm-field"><label class="mm-label" for="b-days">Durée en jours (0 = sans fin)</label><input class="mm-input" id="b-days" name="days" inputmode="numeric" pattern="[0-9]*" value="0" maxlength="4"></div>
          <div class="mm-field"><label class="mm-label" for="b-reason">Motif (pour la gestion)</label><input class="mm-input" id="b-reason" name="reason" maxlength="500"></div>
          <div class="mm-field"><label class="mm-label" for="b-msg">Message au joueur</label><input class="mm-input" id="b-msg" name="player_message" maxlength="500"></div>
          <button class="mm-btn mm-btn--danger" type="submit">BLOQUER</button>
          <p class="mm-muted" data-admin-result></p>
        </form>
      <?php endif; ?>
    </details>

    <details class="mm-acc" name="act">
      <summary>Déconnecter</summary>
      <form class="mm-form" data-admin-form="disconnect" data-confirm="Fermer les sessions de jeu de ce joueur ?">
        <input type="hidden" name="terminal_id" value="<?= $id ?>">
        <p class="mm-muted">Ferme ses sessions de jeu. Son ATAK se reconnecte tout seul s’il n’est pas bloqué.</p>
        <button class="mm-btn" type="submit">DÉCONNECTER</button>
        <p class="mm-muted" data-admin-result></p>
      </form>
    </details>

    <details class="mm-acc" name="act">
      <summary>Appareil perdu ou capturé</summary>
      <?php if ($compromised): ?>
        <form class="mm-form" data-admin-form="undo_compromise" data-reload data-confirm="Rétablir le contrôle de cet appareil ?">
          <input type="hidden" name="terminal_id" value="<?= $id ?>">
          <button class="mm-btn mm-btn--send" type="submit">RÉTABLIR LE CONTRÔLE</button>
          <p class="mm-muted" data-admin-result></p>
        </form>
      <?php else: ?>
        <form class="mm-form" data-admin-form="compromise" data-reload data-confirm="Déclarer cet appareil perdu ou capturé ? Ses certificats seront révoqués.">
          <input type="hidden" name="terminal_id" value="<?= $id ?>">
          <div class="mm-field"><label class="mm-label" for="c-state">Situation</label>
            <select class="mm-input" id="c-state" name="state"><option value="captured">Capturé par l’ennemi</option><option value="compromised">Compromis</option></select>
          </div>
          <div class="mm-field"><label class="mm-label" for="c-reason">Motif</label><input class="mm-input" id="c-reason" name="reason" maxlength="255"></div>
          <button class="mm-btn mm-btn--danger" type="submit">DÉCLARER</button>
          <p class="mm-muted" data-admin-result></p>
        </form>
      <?php endif; ?>
    </details>
<?php endif; ?>
<?php require __DIR__ . '/_admin_foot.php'; ?>
