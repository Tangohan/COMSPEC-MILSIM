<?php
declare(strict_types=1);

/**
 * Aperçu d'un nouveau message, canal par canal : ce que voit chaque cible et ce qui bloquerait l'envoi.
 * Calculé par le serveur (BroadcastCenter::validate puis BroadcastCenterService::plan), sans rien écrire.
 * Tout texte saisi est échappé et marqué translate="no".
 *
 * @var array<string, mixed> $form clé « preview » : errors, warnings, channels, data
 */

use App\Support\BroadcastCenter as BC;

$h ??= static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pv = is_array($form['preview'] ?? null) ? $form['preview'] : [];
$errors = is_array($pv['errors'] ?? null) ? $pv['errors'] : [];
$warnings = is_array($pv['warnings'] ?? null) ? $pv['warnings'] : [];
$channels = is_array($pv['channels'] ?? null) ? $pv['channels'] : [];
$d = is_array($pv['data'] ?? null) ? $pv['data'] : [];
$title = (string) ($d['title'] ?? '');
$body = (string) ($d['body'] ?? '');
$imp = (string) ($d['importance'] ?? 'info');
$impLabel = BC::IMPORTANCE[$imp] ?? 'Info';
?>
<div class="bo-bc-pv">
  <?php if ($errors !== []): ?>
    <div class="bo-brief-note-box is-bad" role="alert">
      <p><b>À corriger avant d’envoyer :</b></p>
      <?php foreach ($errors as $e): ?><p><?= $h($e) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php foreach ($warnings as $w): ?>
    <p class="bo-brief-note-box is-warn"><?= $h($w) ?></p>
  <?php endforeach; ?>
  <?php if ($channels === [] && $errors === []): ?>
    <p class="bo-brief-hint">Choisissez au moins un canal.</p>
  <?php endif; ?>

  <?php foreach ($channels as $c => $info): ?>
    <section class="bo-bc-pv__c">
      <h4><?= $h(BC::CHANNELS[$c] ?? $c) ?></h4>
      <p class="bo-bc-pv__who">Reçu par : <?= $h($info['audience'] ?? '') ?></p>
      <?php if ($c === 'site'): ?>
        <div class="bo-bc-mock-site is-<?= $h($imp) ?>">
          <span class="bo-bc-mock-site__k"><?= $h(\App\Support\TenantAlertVisuals::kindLabel((string) ($info['kind'] ?? 'info'))) ?></span>
          <b translate="no"><?= $h($title) ?></b>
          <?php if ($body !== ''): ?><p translate="no"><?= nl2br($h($body), false) ?></p><?php endif; ?>
        </div>
        <?php if (($info['state'] ?? '') === 'scheduled' && !empty($d['starts_at'])): ?>
          <p class="bo-brief-hint">Programmé : visible à partir du <?= $h(date('d/m/Y H:i', (int) $d['starts_at'])) ?>.</p>
        <?php endif; ?>
        <?php if (!empty($d['ends_at'])): ?><p class="bo-brief-hint">Retiré du portail le <?= $h(date('d/m/Y H:i', (int) $d['ends_at'])) ?>.</p><?php endif; ?>
      <?php elseif ($c === 'phone'): $note = is_array($info['note'] ?? null) ? $info['note'] : ['title' => '', 'body' => '']; ?>
        <div class="bo-brief-phone">
          <h3><?= $h(mb_strtoupper(BC::TYPES[$d['type'] ?? 'consigne'] ?? 'Consigne')) ?></h3>
          <div class="bo-brief-phone__row">
            <?php if ($note['title'] !== ''): ?><b translate="no"><?= $h($note['title']) ?></b><?php endif; ?>
            <?php if ($note['body'] !== ''): ?><p translate="no"><?= $h($note['body']) ?></p><?php endif; ?>
          </div>
        </div>
        <p class="bo-brief-hint">Une seule ligne sur le téléphone : les retours à la ligne deviennent des espaces. Volume après ajout : <?= (int) ($info['bytes'] ?? 0) ?> / <?= \App\Support\AtakCommunityBrief::MAX_PHONE_BYTES ?> octets.</p>
      <?php elseif ($c === 'game'): ?>
        <div class="bo-bc-mock-atak is-<?= $h($imp) ?>">
          <span class="bo-bc-mock-atak__k"><?= $h(\App\Repositories\AtakAdminControlRepository::LEVELS[(string) ($info['level'] ?? 'info')] ?? 'Information') ?></span>
          <b translate="no"><?= $h($title) ?></b>
          <p translate="no"><?= nl2br($h($body), false) ?></p>
          <?php if (!empty($d['require_ack'])): ?><span class="bo-bc-mock-atak__btn">VALIDER</span><?php endif; ?>
        </div>
        <p class="bo-brief-hint">Affiché sur l’ATAK à la prochaine présence en jeu, jusqu’au <?= $h($info['expires_local'] ?? '') ?>.<?= !empty($d['require_ack']) ? ' Le joueur doit valider : l’accusé remonte ici.' : '' ?></p>
      <?php elseif ($c === 'tasks'): ?>
        <div class="bo-bc-mock-task">
          <span class="bo-bc-mock-task__k"><?= $h((string) ($info['priority'] ?? '')) ?> · <?= $h(($info['order_type'] ?? '') === 'CUSTOM' ? 'Ordre' : (($info['order_type'] ?? '') === 'NOTIFY_FULL' ? 'Alerte plein écran' : 'Notification')) ?></span>
          <b translate="no"><?= $h($title) ?></b>
          <p translate="no"><?= nl2br($h($body), false) ?></p>
          <?php if (($info['order_type'] ?? '') === 'CUSTOM'): ?><span class="bo-bc-mock-task__steps">Reçu · Accepté · En cours · Exécuté</span><?php endif; ?>
        </div>
        <p class="bo-brief-hint"><?= ($info['order_type'] ?? '') === 'CUSTOM' ? 'Chaque réponse des joueurs remonte dans la liste.' : 'Message de la Messagerie et notification du téléphone' . (($info['order_type'] ?? '') === 'NOTIFY_FULL' ? ', plus une alerte sur tout l’écran.' : '.') ?></p>
      <?php elseif ($c === 'discord'): ?>
        <pre class="bo-bc-mock-discord" translate="no"><?= $h($info['text'] ?? '') ?></pre>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  <?php if ($channels !== [] && $errors === []): ?>
    <p class="bo-brief-hint">Importance : <?= $h($impLabel) ?>. Rien n’est envoyé tant que vous n’appuyez pas sur « Diffuser ».</p>
  <?php endif; ?>
</div>
