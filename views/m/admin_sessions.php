<?php

declare(strict_types=1);

/**
 * Sessions de jeu : les dernières, et la création d'une session (gestion de la communauté seulement).
 *
 * @var bool $adminSessions
 * @var list<array<string, mixed>> $list
 * @var array<string, string> $kinds
 */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$when = static function (mixed $s): string {
    $t = strtotime((string) $s);

    return $t ? date('d/m H:i', $t) : '';
};
$states = ['open' => 'En cours', 'closed' => 'Terminée', 'discovered' => 'Repérée', 'cancelled' => 'Annulée'];
require __DIR__ . '/_admin_head.php';
?>
<?php if (!$adminSessions): ?>
    <section class="mm-card mm-card--warn">
      <h1 class="mm-h1">Sessions</h1>
      <p class="mm-muted">Créer une session est réservé à la gestion de la communauté. Vos droits ne le permettent pas.</p>
    </section>
<?php else: ?>
    <details class="mm-acc" name="act" open>
      <summary>Créer une session</summary>
      <form class="mm-form" data-admin-form="session_create" data-reload>
        <div class="mm-field"><label class="mm-label" for="s-name">Nom de la mission</label><input class="mm-input" id="s-name" name="mission_name" maxlength="191"></div>
        <div class="mm-field"><label class="mm-label" for="s-kind">Type</label>
          <select class="mm-input" id="s-kind" name="session_kind">
            <?php foreach ($kinds as $k => $label): ?><option value="<?= $h($k) ?>"><?= $h($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <input type="hidden" name="when" value="now">
        <button class="mm-btn mm-btn--send" type="submit">CRÉER ET DÉMARRER</button>
        <p class="mm-muted" data-admin-result></p>
      </form>
    </details>
    <details class="mm-acc" name="act">
      <summary>Dernières sessions</summary>
      <?php if ($list === []): ?>
        <p class="mm-muted">Aucune session pour le moment.</p>
      <?php else: ?>
        <ul class="mm-list">
          <?php foreach ($list as $s): ?>
            <li class="mm-row-card">
              <span class="mm-row-card__top"><strong translate="no"><?= $h(($s['mission_name'] ?? '') !== '' ? $s['mission_name'] : 'Session') ?></strong>
                <span class="mm-badge"><?= $h($states[(string) ($s['status'] ?? '')] ?? (string) ($s['status'] ?? '')) ?></span></span>
              <span class="mm-row-card__meta mm-mono"><?= $h($when($s['started_at'] ?? $s['planned_starts_at'] ?? $s['created_at'] ?? '')) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </details>
<?php endif; ?>
<?php require __DIR__ . '/_admin_foot.php'; ?>
