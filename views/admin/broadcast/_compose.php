<?php
declare(strict_types=1);

/**
 * Formulaire « Nouveau message » du centre de diffusion. Sans script : vrai formulaire, « Aperçu » recharge la page avec
 * l'aperçu, « Diffuser » envoie. Avec back-office-broadcast.js : ouvert dans la fenêtre, aperçu à chaque changement,
 * canaux et cibles proposés selon le type. Le serveur revérifie tout (BroadcastCenter::validate).
 *
 * @var array<string, mixed> $form
 * @var array<string, mixed>|null $prefill
 * @var list<string> $allowed
 */

use App\Support\BroadcastCenter as BC;

$h ??= static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bcBase ??= url('back-office/alerts');
$options = is_array($form['options'] ?? null) ? $form['options'] : [];
$ready = is_array($options['ready'] ?? null) ? $options['ready'] : [];
$p = is_array($prefill ?? null) ? $prefill : [];
$pType = isset(BC::TYPES[(string) ($p['type'] ?? '')]) ? (string) $p['type'] : 'annonce';
if (!array_intersect(BC::TYPE_CHANNELS[$pType], $allowed)) {
    foreach (BC::TYPE_CHANNELS as $t => $cs) {
        if (array_intersect($cs, $allowed)) {
            $pType = $t;
            break;
        }
    }
}
$pChannels = is_array($p['channels'] ?? null) ? $p['channels'] : [];
if ($pChannels === []) {
    foreach (BC::TYPE_CHANNELS[$pType] as $c) {
        if (in_array($c, $allowed, true) && !empty($ready[$c])) {
            $pChannels = [$c];
            break;
        }
    }
}
$pTarget = isset(BC::TARGETS[(string) ($p['target'] ?? '')]) ? (string) $p['target'] : 'all';
$pImportance = isset(BC::IMPORTANCE[(string) ($p['importance'] ?? '')]) ? (string) $p['importance'] : 'info';
$pick = static fn (string $k): array => array_map('strval', is_array($p[$k] ?? null) ? $p[$k] : []);
$typeHints = [
    'annonce' => 'Bandeau en haut du portail des membres.',
    'consigne' => 'Règle à suivre, lue dans l’app Communauté du téléphone.',
    'ordre' => 'Ordre suivi dans l’app Tâches (reçu, accepté, exécuté), ou ordre permanent de l’app Communauté.',
    'tip' => 'Astuce, lue dans l’app Communauté du téléphone.',
    'message' => 'Message affiché sur l’ATAK du joueur à sa prochaine présence en jeu.',
    'alerte' => 'Notification de l’app Tâches ; plein écran si urgent.',
];
$channelHints = [
    'site' => 'Bandeau du portail, toute la communauté. Seul canal qui sait attendre une date de début.',
    'phone' => 'Note permanente de l’app Communauté, toute la communauté, jusqu’à son retrait. Titre : 60 caractères, texte : 600.',
    'tasks' => 'App Tâches : ordre suivi (reçu, accepté, en cours, exécuté), pour tous, une équipe ou des personnes.',
    'game' => 'Message sur l’ATAK de chaque joueur visé, à sa prochaine présence en jeu, avec accusé de lecture.',
    'discord' => 'Salon « Annonces » du Discord de la communauté. Ne se retire pas depuis Athena.',
];
$sel = static fn (bool $on): string => $on ? ' selected' : '';
$chk = static fn (bool $on): string => $on ? ' checked' : '';
?>
<form method="post" action="<?= $h($bcBase . '/diffuser') ?>" class="bo-brief-form bo-bc-form" data-bc-form data-bc-compose-form>
  <?= \App\Core\Csrf::field() ?>
  <div class="bo-bc-form__grid">
    <div class="bo-bc-form__main">
      <fieldset class="bo-brief-f">
        <legend>Type</legend>
        <div class="bo-bc-choices">
          <?php foreach (BC::TYPES as $value => $label):
              $usable = array_values(array_intersect(BC::TYPE_CHANNELS[$value], $allowed)); ?>
            <label class="bo-bc-choice" title="<?= $h($typeHints[$value]) ?>">
              <input type="radio" name="type" value="<?= $h($value) ?>" data-channels="<?= $h(implode(',', BC::TYPE_CHANNELS[$value])) ?>"<?= $chk($pType === $value) ?><?= $usable === [] ? ' disabled' : '' ?>>
              <span><?= $h($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <small data-bc-type-hint><?= $h($typeHints[$pType]) ?></small>
      </fieldset>

      <label class="bo-brief-f"><span>Titre <small data-count-out><?= BC::MAX_TITLE ?> caractères au plus</small></span>
        <input type="text" name="title" maxlength="<?= BC::MAX_TITLE ?>" required value="<?= $h($p['title'] ?? '') ?>" translate="no" autocomplete="off" data-count>
      </label>
      <label class="bo-brief-f"><span>Texte <small data-count-out><?= BC::MAX_BODY ?> caractères au plus</small></span>
        <textarea name="body" rows="5" maxlength="<?= BC::MAX_BODY ?>" translate="no" data-count><?= $h($p['body'] ?? '') ?></textarea>
        <small>Texte brut, sans balise : le jeu et le site l’affichent tel quel.</small>
      </label>

      <fieldset class="bo-brief-f">
        <legend>Importance</legend>
        <div class="bo-bc-choices">
          <?php foreach (BC::IMPORTANCE as $value => $label): ?>
            <label class="bo-bc-choice bo-bc-imp is-<?= $h($value) ?>"><input type="radio" name="importance" value="<?= $h($value) ?>"<?= $chk($pImportance === $value) ?>><span><?= $h($label) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="bo-brief-f">
        <legend>Canaux</legend>
        <div class="bo-bc-channels">
          <?php foreach (BC::CHANNELS as $value => $label):
              $can = in_array($value, $allowed, true);
              $up = !empty($ready[$value]);
              $why = !$can
                  ? ($value === 'site' && !empty($form['siteDenied']) ? 'Votre formule ne comprend pas les annonces du site.' : 'Votre rôle ne permet pas ce canal.')
                  : (!$up ? ($value === 'discord' ? 'Aucun salon Discord réglé pour les annonces (Intégrations externes).' : 'Indisponible : base de données pas à jour.') : '');
              ?>
            <label class="bo-bc-channel" data-bc-channel="<?= $h($value) ?>">
              <input type="checkbox" name="channels[]" value="<?= $h($value) ?>"<?= $chk(in_array($value, $pChannels, true)) ?><?= $can && $up ? '' : ' disabled' ?>>
              <span><b><?= $h($label) ?></b><small><?= $h($why !== '' ? $why : $channelHints[$value]) ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
        <small>Chaque type a ses canaux : un canal qui ne convient pas au type est refusé à l’envoi.</small>
      </fieldset>

      <fieldset class="bo-brief-f">
        <legend>Qui le reçoit</legend>
        <select name="target" data-bc-target>
          <?php foreach (BC::TARGETS as $value => $label): ?>
            <option value="<?= $h($value) ?>"<?= $sel($pTarget === $value) ?>><?= $h($label) ?></option>
          <?php endforeach; ?>
        </select>
        <small>Le site, l’app Communauté et Discord s’adressent toujours à toute la communauté. Le message en jeu sait viser une unité, un rôle, un groupe, une équipe ou des personnes ; l’app Tâches, une équipe ou des personnes.</small>
        <div class="bo-bc-target" data-bc-target-for="unit">
          <label class="bo-brief-f"><span>Unités (sous-unités comprises)</span>
            <select name="unit_ids[]" multiple size="6">
              <?php foreach ($options['units'] ?? [] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $sel(in_array((string) $o['id'], $pick('unit_ids'), true)) ?> translate="no"><?= $h($o['label']) ?></option><?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="bo-bc-target" data-bc-target-for="role">
          <label class="bo-brief-f"><span>Rôles</span>
            <select name="role_slugs[]" multiple size="6">
              <?php foreach ($options['roles'] ?? [] as $o): ?><option value="<?= $h($o['id']) ?>"<?= $sel(in_array((string) $o['id'], $pick('role_slugs'), true)) ?>><?= $h($o['label']) ?></option><?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="bo-bc-target" data-bc-target-for="group">
          <label class="bo-brief-f"><span>Groupe de destinataires</span>
            <select name="group_id">
              <option value="">Choisir…</option>
              <?php foreach ($options['groups'] ?? [] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $sel((string) ($p['group_id'] ?? '') === (string) $o['id']) ?> translate="no"><?= $h($o['label']) ?></option><?php endforeach; ?>
            </select>
            <small>Les groupes enregistrés pour les e-mails aux membres (unités, rôles et personnes).</small>
          </label>
        </div>
        <div class="bo-bc-target" data-bc-target-for="fire_team">
          <label class="bo-brief-f"><span>Équipe</span>
            <select name="fire_team_id">
              <option value="">Choisir…</option>
              <?php foreach ($options['fire_teams'] ?? [] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $sel((string) ($p['fire_team_id'] ?? '') === (string) $o['id']) ?> translate="no"><?= $h($o['label']) ?></option><?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="bo-bc-target" data-bc-target-for="person">
          <fieldset class="bo-brief-f">
            <legend>Personnes</legend>
            <input type="search" placeholder="Chercher un nom ou un indicatif…" aria-label="Chercher une personne" autocomplete="off" data-bc-person-q hidden>
            <div class="bo-brief-opts bo-bc-people" data-bc-people>
              <?php foreach ($options['members'] ?? [] as $o): ?>
                <label class="bo-brief-opt"><input type="checkbox" name="user_ids[]" value="<?= (int) $o['id'] ?>"<?= $chk(in_array((string) $o['id'], $pick('user_ids'), true)) ?>><span class="bo-brief-opt__t" translate="no"><?= $h($o['label']) ?></span></label>
              <?php endforeach; ?>
            </div>
            <?php if (($options['members'] ?? []) === []): ?><small>Aucun membre actif.</small><?php endif; ?>
          </fieldset>
        </div>
      </fieldset>

      <fieldset class="bo-brief-f">
        <legend>Durée</legend>
        <div class="bo-brief-form__row">
          <label class="bo-brief-f bo-brief-f--grow"><span>Début</span>
            <input type="datetime-local" name="starts_at" value="<?= $h($p['starts_at'] ?? '') ?>">
            <small>Vide : tout de suite. Une date future programme le bandeau du site.</small>
          </label>
          <label class="bo-brief-f bo-brief-f--grow"><span>Fin</span>
            <input type="datetime-local" name="ends_at" value="<?= $h($p['ends_at'] ?? '') ?>">
            <small>Vide : bandeau sans fin ; message en jeu valable 3 jours.</small>
          </label>
        </div>
      </fieldset>

      <div class="bo-brief-form__row bo-bc-extra">
        <label class="bo-brief-f" data-bc-only="game"><span>Message en jeu</span>
          <span class="bo-bc-check"><input type="hidden" name="require_ack" value="0"><input type="checkbox" name="require_ack" value="1"<?= $chk(($p['require_ack'] ?? '1') !== '0') ?>> Le joueur doit valider pour fermer (accusé de lecture)</span>
        </label>
        <label class="bo-brief-f" data-bc-only="tasks"><span>Carte de l’opération (app Tâches)</span>
          <input type="number" name="map_id" min="1" max="99" value="<?= (int) ($p['map_id'] ?? 1) ?: 1 ?>">
          <small>Le numéro réglé dans le mod (Overwatch, « Numéro de carte de l’opération ») : 1 si vous n’en avez qu’une.</small>
        </label>
      </div>
    </div>

    <aside class="bo-bc-form__side" aria-label="Aperçu">
      <h3>Aperçu, par cible</h3>
      <div data-bc-preview aria-live="polite">
        <?php if (is_array($form['preview'] ?? null)): ?>
          <?php require __DIR__ . '/_preview.php'; ?>
        <?php else: ?>
          <p class="bo-brief-hint">Appuyez sur « Aperçu » pour voir ce que recevra chaque cible, canal par canal, avant d’envoyer.</p>
        <?php endif; ?>
      </div>
    </aside>
  </div>

  <div class="bo-brief-form__f">
    <button type="button" class="ath-btn" data-bc-close hidden>Annuler</button>
    <button type="submit" class="ath-btn" formaction="<?= $h($bcBase . '/apercu') ?>" data-bc-preview-btn>Aperçu</button>
    <button type="submit" class="ath-btn ath-btn--solid">Diffuser</button>
  </div>
</form>
