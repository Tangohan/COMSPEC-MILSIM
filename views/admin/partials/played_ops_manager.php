<?php
declare(strict_types=1);

/**
 * Parties détectées en jeu : liste (cases à cocher, actions groupées), fenêtre « Gérer » par partie (renommer, type,
 * catégorie, rattachement, ignorer, supprimer) et réglages (types, règles d'exclusion, catégories, corbeille).
 * Partagé par le suivi des CR (onglet « suivi » des comptes rendus) et le cycle de mission.
 *
 * @var array<string, mixed> $playedOps   PlayedOperationsService::overview
 * @var bool                 $playedCanManage
 * @var string               $playedCsrf  jeton CSRF
 * @var string               $playedBack  suivi | cycle (page de retour après une action)
 * @var bool                 $playedSteps afficher l'état des étapes du cycle
 * @var string               $playedId    préfixe des ancres (dbf-parties | bo-mcycle)
 */

use App\Services\Atak\PlayedOperationsService as Svc;
use App\Support\AtakPlayedOpRules as Rules;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$po = is_array($playedOps ?? null) ? $playedOps : ['ops' => [], 'types' => [], 'all_types' => [], 'rules' => [], 'categories' => [], 'candidates' => [], 'deleted' => [], 'unlinked' => 0, 'ready' => false];
$ops = $po['ops'];
$canManage = !empty($playedCanManage);
$back = ($playedBack ?? 'suivi') === 'cycle' ? 'cycle' : 'suivi';
$steps = !empty($playedSteps);
$anchor = (string) ($playedId ?? 'dbf-parties');
$csrfField = '<input type="hidden" name="_csrf_token" value="' . $h($playedCsrf ?? '') . '"><input type="hidden" name="retour" value="' . $back . '">';
$act = static fn (string $path): string => $h(url('back-office/atak/parties/' . $path));
$when = static fn (?int $ts, string $f = 'd/m/y H:i'): string => $ts !== null ? date($f, $ts) : '—';
$dur = static function (?int $s): string {
    if ($s === null || $s < 60) {
        return '—';
    }

    return intdiv($s, 3600) > 0 ? intdiv($s, 3600) . ' h ' . str_pad((string) intdiv($s % 3600, 60), 2, '0', STR_PAD_LEFT) : intdiv($s, 60) . ' min';
};
$kindLabel = ['plan' => 'Opération planifiée', 'cycle' => 'Cycle de mission', 'event' => 'Événement'];
$kindUrl = static fn (array $c): string => match ($c['kind'] ?? '') {
    'plan' => url('back-office/planification/' . (int) $c['id']),
    'cycle' => url('back-office/atak/cycle-mission') . '?mission=' . (int) $c['id'],
    default => url('back-office/events'),
};
$stepLabel = [
    'plan' => 'Planification', 'brief' => 'Briefing', 'start' => 'Début détecté', 'run' => 'Déroulé',
    'end' => 'Fin', 'debrief' => 'Débriefing', 'effects' => 'Heures et présence',
];
$stateLabel = [
    'done' => 'fait', 'auto' => 'détecté automatiquement', 'live' => 'en cours', 'todo' => 'à faire', 'none' => 'rien', 'off' => 'non comptée',
];
$typeChip = static function (?array $t) use ($h): string {
    if ($t === null) {
        return '<span class="bo-po-muted">—</span>';
    }
    $type = $t['type'];

    return '<span class="bo-po-chip" style="--po-c:' . $h(Rules::normalizeColor((string) ($type['color'] ?? ''))) . '">' . $h($type['name']) . '</span>'
        . ($t['source'] === 'rule' ? ' <small class="bo-po-muted" title="Détecté par la règle du type">auto</small>' : '');
};
$counts = ['all' => count($ops), 'unlinked' => 0, 'ignored' => 0, 'live' => 0];
foreach ($ops as $o) {
    $counts['unlinked'] += $o['link'] === null && !$o['ignored']['ignored'] ? 1 : 0;
    $counts['ignored'] += $o['ignored']['ignored'] ? 1 : 0;
    $counts['live'] += $o['live'] ? 1 : 0;
}
?>
<section class="ath-panel bo-po" id="<?= $h($anchor) ?>" aria-labelledby="<?= $h($anchor) ?>-title" data-po>
  <header class="bo-po__head">
    <div>
      <h2 class="bo-po__title" id="<?= $h($anchor) ?>-title">Parties détectées en jeu</h2>
      <p class="bo-po__lead">Chaque lancement de mission sur le serveur Arma crée une partie : l’ATAK lui donne une clé (mission, carte, heure UTC) et Athena la voit dès le premier envoi de temps de jeu ou de remontée. Rattachez-la à ce qui était prévu, typez-la, ou ignorez-la pour qu’elle ne compte pas.</p>
    </div>
    <?php if ($canManage): ?>
      <a class="ath-btn" href="#<?= $h($anchor) ?>-reglages" data-po-details="<?= $h($anchor) ?>-reglages">Types, règles et catégories</a>
    <?php endif; ?>
  </header>

  <?php if (empty($po['ready'])): ?>
    <p class="bo-po__warn">Les réglages des parties (types, règles, rattachement) ne sont pas encore en place : lancez <code>run-migrations.php</code> (étape <code>atak_played_operations</code>).</p>
  <?php endif; ?>

  <?php if ($counts['unlinked'] > 0): ?>
    <p class="bo-po__alert" role="status"><b><?= (int) $counts['unlinked'] ?></b> partie(s) détectée(s) sans opération prévue : rattachez-les ou marquez-les « aucune » dans « Gérer ».</p>
  <?php endif; ?>

  <div class="bo-aar-seg bo-po__filters" role="group" aria-label="Filtrer les parties">
    <button type="button" class="bo-aar-seg__btn is-active" data-po-filter="all" aria-pressed="true">Toutes <b><?= $counts['all'] ?></b></button>
    <button type="button" class="bo-aar-seg__btn" data-po-filter="unlinked" aria-pressed="false">Non rattachées <b><?= $counts['unlinked'] ?></b></button>
    <button type="button" class="bo-aar-seg__btn" data-po-filter="live" aria-pressed="false">En cours <b><?= $counts['live'] ?></b></button>
    <button type="button" class="bo-aar-seg__btn" data-po-filter="ignored" aria-pressed="false">Ignorées <b><?= $counts['ignored'] ?></b></button>
  </div>

  <?php if ($ops === []): ?>
    <p class="bo-po-muted">Aucune partie remontée sur les 90 derniers jours. Une partie apparaît quand le serveur Arma (ATAK natif, temps de jeu activé) envoie son premier relevé, au plus tard 5 minutes après le lancement de la mission.</p>
  <?php else: ?>
    <?php if ($canManage): ?>
    <form class="bo-po__bulk" id="<?= $h($anchor) ?>-bulk" method="post" action="<?= $act('groupe') ?>">
      <?= $csrfField ?>
      <span class="bo-po__bulk-count"><span data-po-checked>0</span> cochée(s)</span>
      <label>Action
        <select name="action" data-po-bulk-action>
          <option value="">Choisir…</option>
          <option value="categoriser">Catégoriser</option>
          <option value="ignorer">Ignorer</option>
          <option value="compter">Compter à nouveau</option>
          <option value="supprimer">Supprimer</option>
        </select>
      </label>
      <label data-po-bulk-show="categoriser" hidden>Catégorie
        <select name="categorie">
          <option value="">Aucune</option>
          <?php foreach ($po['categories'] as $c): ?><option value="<?= $h($c) ?>"><?= $h($c) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="bo-po__confirm" data-po-bulk-show="supprimer" hidden><input type="checkbox" name="confirme" value="1"> Je confirme : les parties cochées ne compteront plus (restaurables)</label>
      <button class="ath-btn ath-btn--solid" type="submit" data-po-bulk-submit disabled>Appliquer</button>
    </form>
    <?php endif; ?>

    <div class="bo-po__table-wrap">
      <table class="bo-po__table">
        <thead>
          <tr>
            <?php if ($canManage): ?><th scope="col" class="bo-po__check"><input type="checkbox" aria-label="Tout cocher" data-po-all></th><?php endif; ?>
            <th scope="col">Partie</th>
            <th scope="col">Lancée</th>
            <th scope="col">Joueurs</th>
            <th scope="col">Durée</th>
            <th scope="col">Type · catégorie</th>
            <th scope="col">Opération prévue</th>
            <?php if ($steps): ?><th scope="col">Cycle</th><?php endif; ?>
            <th scope="col"><span class="bo-po-sr">Actions</span></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($ops as $i => $o):
            $dlg = $anchor . '-dlg-' . $i;
            $flags = trim(($o['link'] === null && !$o['ignored']['ignored'] ? 'unlinked ' : '') . ($o['ignored']['ignored'] ? 'ignored ' : '') . ($o['live'] ? 'live' : ''));
        ?>
          <tr class="bo-po__row<?= $o['ignored']['ignored'] ? ' is-ignored' : '' ?>" data-po-flags="<?= $h($flags) ?>">
            <?php if ($canManage): ?><td class="bo-po__check"><input type="checkbox" name="ops[]" value="<?= $h($o['key']) ?>" form="<?= $h($anchor) ?>-bulk" aria-label="Cocher <?= $h($o['label']) ?>" data-po-row></td><?php endif; ?>
            <td>
              <span class="bo-po__name"><?= $h($o['label']) ?></span>
              <?php if ($o['live']): ?> <span class="ath-tag ath-tag--ok">En cours</span><?php endif; ?>
              <?php if ($o['ignored']['ignored']): ?> <span class="ath-tag ath-tag--warn"><?= $o['ignored']['by'] === 'rule' ? 'Ignorée par une règle' : 'Ignorée' ?></span><?php endif; ?>
              <span class="bo-po__key" translate="no"><?= $h($o['mission']) ?> · <?= $h($o['map']) ?></span>
            </td>
            <td><?= $h($when($o['start_ts'])) ?></td>
            <td><?= (int) $o['players'] ?></td>
            <td><?= $h($dur($o['duration'])) ?></td>
            <td><?= $typeChip($o['type']) ?><?php if ($o['category'] !== null): ?> <span class="bo-po-muted">· <?= $h($o['category']['name']) ?></span><?php endif; ?></td>
            <td>
              <?php if ($o['link'] !== null): $c = $o['link']['cand']; ?>
                <a href="<?= $h($kindUrl($c)) ?>"><?= $h($c['title']) ?></a>
                <small class="bo-po-muted"><?= $h($kindLabel[$c['kind']] ?? '') ?> · <?= $o['link']['source'] === 'manual' ? 'Rattachée' : 'Proposée' ?></small>
              <?php elseif ($o['link_mode'] === 'none'): ?>
                <span class="bo-po-muted">Aucune (partie libre)</span>
              <?php else: ?>
                <span class="bo-po__unlinked">Non rattachée</span>
              <?php endif; ?>
            </td>
            <?php if ($steps): ?>
            <td>
              <ol class="bo-po__steps" aria-label="Étapes du cycle">
                <?php foreach (Svc::STEPS as $st): $state = $o['steps'][$st] ?? 'none'; ?>
                  <li class="bo-po__step is-<?= $h($state) ?>" title="<?= $h($stepLabel[$st]) ?> : <?= $h($stateLabel[$state] ?? $state) ?>"><span class="bo-po-sr"><?= $h($stepLabel[$st]) ?> : <?= $h($stateLabel[$state] ?? $state) ?></span></li>
                <?php endforeach; ?>
              </ol>
            </td>
            <?php endif; ?>
            <td class="bo-po__actions">
              <?php if ($canManage): ?><button type="button" class="ath-btn" data-po-open="<?= $h($dlg) ?>">Gérer</button><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($steps): ?>
      <p class="bo-po__legend"><span class="bo-po__step is-done"></span> fait <span class="bo-po__step is-auto"></span> détecté automatiquement <span class="bo-po__step is-live"></span> en cours <span class="bo-po__step is-todo"></span> à faire <span class="bo-po__step is-off"></span> non comptée <span class="bo-po__step is-none"></span> rien — dans l’ordre : planification, briefing, début, déroulé, fin, débriefing, heures et présence.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($canManage): foreach ($ops as $i => $o):
      $dlg = $anchor . '-dlg-' . $i;
      $near = array_values(array_filter($po['candidates'], static fn (array $c): bool => $o['start_ts'] === null || $c['start_ts'] === null || abs($c['start_ts'] - $o['start_ts']) <= 7 * 86400));
      $current = '';
      if ($o['link_mode'] === 'none') {
          $current = 'none';
      } elseif ($o['link'] !== null && $o['link']['source'] === 'manual') {
          $current = $o['link']['cand']['kind'] . ':' . (int) $o['link']['cand']['id'];
          if (Svc::findCand($near, $o['link']['cand']['kind'], (int) $o['link']['cand']['id']) === null) {
              $near[] = $o['link']['cand'];
          }
      }
      $manualType = $o['type'] !== null && $o['type']['source'] === 'manual' ? (int) $o['type']['type']['id'] : 0;
      $sug = $o['suggestion'];
  ?>
  <dialog class="ath-dialog ath-dialog--wide bo-po-dlg" id="<?= $h($dlg) ?>" aria-labelledby="<?= $h($dlg) ?>-t">
    <div class="ath-dialog__head bo-po-dlg__head">
      <div>
        <h2 class="ath-dialog__title" id="<?= $h($dlg) ?>-t">Gérer la partie · <?= $h($o['label']) ?></h2>
        <p class="ath-dialog__sub">Lancée le <?= $h($when($o['start_ts'], 'd/m/Y H:i')) ?> · <?= (int) $o['players'] ?> joueur(s) · <?= $h($dur($o['duration'])) ?> · <span translate="no"><?= $h($o['key']) ?></span></p>
      </div>
      <button type="button" class="ath-dialog__close" data-po-close aria-label="Fermer">✕</button>
    </div>
    <div class="ath-dialog__body bo-po-dlg__body">

      <form method="post" action="<?= $act('enregistrer') ?>" class="bo-po-dlg__form">
        <?= $csrfField ?><input type="hidden" name="op" value="<?= $h($o['key']) ?>">
        <h3 class="bo-po-dlg__h">Nom, type et rattachement</h3>
        <div class="bo-po-dlg__grid">
          <label>Nom de l’opération
            <input type="text" name="nom" maxlength="200" value="<?= $h($o['custom'] ? $o['label'] : '') ?>" placeholder="<?= $h($o['default_label']) ?>">
            <small>Vide : reprend le nom de la mission.</small>
          </label>
          <label>Type
            <select name="type_id">
              <option value="0"><?php if ($o['type'] !== null && $o['type']['source'] === 'rule'): ?>Détecté : <?= $h($o['type']['type']['name']) ?><?php else: ?>Aucun<?php endif; ?></option>
              <?php foreach ($po['types'] as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $manualType === (int) $t['id'] ? ' selected' : '' ?>><?= $h($t['name']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>Catégorie
            <select name="categorie">
              <option value=""><?php if ($o['category'] !== null && $o['category']['source'] === 'type'): ?>Celle du type : <?= $h($o['category']['name']) ?><?php else: ?>Aucune<?php endif; ?></option>
              <?php foreach ($po['categories'] as $c): ?><option value="<?= $h($c) ?>"<?= $o['category'] !== null && $o['category']['source'] === 'manual' && $o['category']['name'] === $c ? ' selected' : '' ?>><?= $h($c) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>Opération prévue
            <select name="lien">
              <option value="auto"<?= $current === '' ? ' selected' : '' ?>><?php if ($o['link'] !== null && $o['link']['source'] === 'auto'): ?>Proposition automatique : <?= $h($o['link']['cand']['title']) ?><?php else: ?>Proposition automatique (aucune trouvée)<?php endif; ?></option>
              <option value="none"<?= $current === 'none' ? ' selected' : '' ?>>Aucune : partie libre</option>
              <?php foreach (['plan', 'cycle', 'event'] as $kind): $list = array_values(array_filter($near, static fn (array $c): bool => $c['kind'] === $kind)); if ($list === []) { continue; } ?>
                <optgroup label="<?= $h($kindLabel[$kind]) ?>">
                  <?php foreach ($list as $c): $v = $kind . ':' . (int) $c['id']; ?><option value="<?= $h($v) ?>"<?= $current === $v ? ' selected' : '' ?>><?= $h($when($c['start_ts'], 'd/m H:i')) ?> · <?= $h($c['title']) ?></option><?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <?php if ($o['link'] !== null && $o['link']['signals'] !== []): ?>
          <p class="bo-po-dlg__why"><?php if ($o['link']['source'] === 'auto'): ?><span>Proposée d’après :</span><?php else: ?><span>Rapprochement :</span><?php endif; ?>
            <?php foreach ($o['link']['signals'] as $si => $sg): $v = (int) ($sg['value'] ?? 0); ?><?= $si > 0 ? ' · ' : '' ?><span><?php if ($sg['code'] === 'window'): ?>lancée pendant le créneau prévu<?php elseif ($sg['code'] === 'slot' && $v === 0): ?>lancée à l’heure prévue<?php elseif ($sg['code'] === 'slot' && $v > 0): ?>lancée <?= $v ?> min après l’heure prévue<?php elseif ($sg['code'] === 'slot'): ?>lancée <?= -$v ?> min avant l’heure prévue<?php elseif ($sg['code'] === 'map'): ?>même carte<?php elseif ($sg['code'] === 'map_other'): ?>autre carte<?php elseif ($sg['code'] === 'players'): ?><?= $v ?>/<?= (int) ($sg['total'] ?? 0) ?> joueurs prévus présents<?php endif; ?></span><?php endforeach; ?>
            <span>(score <?= (int) $o['link']['score'] ?>/100)</span></p>
        <?php endif; ?>
        <?php if ($o['link'] !== null && !empty($o['link']['cand']['campaign'])): ?><p class="bo-po-dlg__why">Campagne : <?= $h($o['link']['cand']['campaign']) ?></p><?php endif; ?>
        <p class="bo-po-dlg__actions"><button class="ath-btn ath-btn--solid" type="submit">Enregistrer</button></p>
      </form>

      <details class="bo-po-dlg__sec"<?= $sug !== null ? ' open' : '' ?>>
        <summary><span>Enregistrer un type d’opération</span><?php if ($sug !== null): ?> · <span>Proposé : <?= $h($sug['category']) ?></span><?php endif; ?></summary>
        <?php if ($sug !== null): ?>
          <p class="bo-po-dlg__why">Type proposé d’après
            <?= implode(', ', array_map(static fn (string $r): string => match ($r) { 'keyword' => 'le nom de la mission', 'short' => 'une partie courte à 1 ou 2 joueurs', 'crowd' => 'au moins 8 joueurs', default => '' }, $sug['reasons'])) ?>. Rien n’est enregistré tant que vous ne validez pas.</p>
        <?php endif; ?>
        <form method="post" action="<?= $act('types') ?>" class="bo-po-dlg__form">
          <?= $csrfField ?><input type="hidden" name="op" value="<?= $h($o['key']) ?>">
          <div class="bo-po-dlg__grid">
            <label>Nom du type <input type="text" name="nom" maxlength="80" required value="<?= $h($sug['name'] ?? '') ?>"></label>
            <label>Couleur <input type="color" name="couleur" value="#3b6ea5"></label>
            <label>Détecter d’après
              <select name="champ">
                <option value="mission">le nom de la mission</option>
                <option value="map">la carte</option>
                <option value="name">le nom affiché</option>
              </select>
            </label>
            <label>Motif <input type="text" name="motif" maxlength="190" value="<?= $h($sug['pattern'] ?? $o['mission']) ?>" translate="no">
              <small>* = n’importe quelle suite, ? = un caractère. Ex. « Train_* ».</small>
            </label>
            <label>Catégorie appliquée
              <select name="categorie">
                <option value="">Aucune</option>
                <?php foreach ($po['categories'] as $c): ?><option value="<?= $h($c) ?>"<?= ($sug['category'] ?? '') === $c ? ' selected' : '' ?>><?= $h($c) ?></option><?php endforeach; ?>
              </select>
            </label>
          </div>
          <p class="bo-po-dlg__actions"><button class="ath-btn" type="submit">Enregistrer le type</button> <small class="bo-po-muted">Les prochaines parties qui correspondent seront typées automatiquement.</small></p>
        </form>
      </details>

      <details class="bo-po-dlg__sec"<?= $o['ignored']['ignored'] ? ' open' : '' ?>>
        <summary><span>Ignorer</span><?php if ($o['ignored']['ignored']): ?> · <span><?= $o['ignored']['by'] === 'rule' ? 'Ignorée par une règle' : 'Ignorée' ?></span><?php endif; ?></summary>
        <p class="bo-po-dlg__why">Une partie ignorée reste visible ici mais ne compte plus dans le temps de jeu, le temps par rôle, les heures de vol, les statistiques et les heures prises en compte pour les qualifications et l’avancement. La présence pointée dans « Sessions de jeu » se gère sur sa propre page.</p>
        <?php if ($o['ignored']['ignored']): ?>
          <?php if ($o['ignored']['by'] === 'rule' && $o['ignored']['rule'] !== null): ?><p class="bo-po-dlg__why"><span>Règle :</span> <span><?= $h(Rules::FIELDS[$o['ignored']['rule']['match_field']] ?? '') ?></span> = « <span translate="no"><?= $h($o['ignored']['rule']['pattern']) ?></span> ».</p><?php endif; ?>
          <form method="post" action="<?= $act('compter') ?>"><?= $csrfField ?><input type="hidden" name="op" value="<?= $h($o['key']) ?>"><button class="ath-btn ath-btn--solid" type="submit">Compter à nouveau cette partie</button></form>
        <?php else: ?>
          <div class="bo-po-dlg__split">
            <form method="post" action="<?= $act('ignorer') ?>" class="bo-po-dlg__form">
              <?= $csrfField ?><input type="hidden" name="op" value="<?= $h($o['key']) ?>"><input type="hidden" name="portee" value="partie">
              <p><b>Cette partie seulement</b></p>
              <button class="ath-btn" type="submit">Ignorer cette partie</button>
            </form>
            <form method="post" action="<?= $act('ignorer') ?>" class="bo-po-dlg__form">
              <?= $csrfField ?><input type="hidden" name="op" value="<?= $h($o['key']) ?>"><input type="hidden" name="portee" value="regle">
              <p><b>À l’avenir</b> : toutes les parties qui correspondent</p>
              <label>Même
                <select name="champ">
                  <option value="mission">nom de mission</option>
                  <option value="map">carte</option>
                </select>
              </label>
              <label>Motif <input type="text" name="motif" maxlength="190" value="<?= $h($o['mission']) ?>" translate="no"></label>
              <label>Note <input type="text" name="note" maxlength="200" placeholder="Ex. serveur de test, mission d’essai"></label>
              <button class="ath-btn" type="submit">Ignorer à l’avenir</button>
            </form>
          </div>
        <?php endif; ?>
      </details>

      <details class="bo-po-dlg__sec bo-po-dlg__sec--danger">
        <summary>Supprimer</summary>
        <p class="bo-po-dlg__why">La partie disparaît des listes et ne compte plus. Seront retirés des comptes :</p>
        <ul>
          <li><?= $h($dur($o['play_seconds'])) ?> de temps de jeu de <?= (int) $o['players'] ?> joueur(s), et leur temps par rôle</li>
          <li><?= (int) $o['flights'] ?> vol(s) au carnet (<?= $h($dur($o['flight_seconds'])) ?>)</li>
          <li><?= (int) $o['reports'] ?> remontée(s) et <?= (int) $o['debriefs'] ?> CR imposé(s) restent consultables mais ne sont plus rattachés à une partie listée</li>
          <li><?= count($o['aars']) ?> compte(s) rendu(s) post-op lié(s) à l’opération rattachée : ils restent, rattachés à leur opération</li>
        </ul>
        <p class="bo-po-dlg__why">Rien n’est effacé de la base : la partie se restaure depuis « Types, règles et catégories ».</p>
        <form method="post" action="<?= $act('supprimer') ?>" class="bo-po-dlg__form">
          <?= $csrfField ?><input type="hidden" name="op" value="<?= $h($o['key']) ?>">
          <label class="bo-po__confirm"><input type="checkbox" name="confirme" value="1" required> Je confirme la suppression de cette partie</label>
          <button class="ath-btn bo-aar-danger" type="submit">Supprimer la partie</button>
        </form>
      </details>
    </div>
    <div class="ath-dialog__foot"><button type="button" class="ath-btn" data-po-close>Fermer</button></div>
  </dialog>
  <?php endforeach; endif; ?>

  <?php if ($canManage): ?>
  <details class="bo-po__settings" id="<?= $h($anchor) ?>-reglages">
    <summary>Types, règles « ignorer à l’avenir », catégories et parties supprimées</summary>
    <div class="bo-po__settings-grid">
      <section>
        <h3 class="bo-po-dlg__h">Types d’opération</h3>
        <?php if ($po['all_types'] === []): ?><p class="bo-po-muted">Aucun type : enregistrez-en un depuis « Gérer » d’une partie.</p><?php endif; ?>
        <ul class="bo-po__list">
          <?php foreach ($po['all_types'] as $t): ?>
            <li>
              <span class="bo-po-chip" style="--po-c:<?= $h(Rules::normalizeColor((string) $t['color'])) ?>"><?= $h($t['name']) ?></span>
              <small class="bo-po-muted"><span><?= $h(Rules::FIELDS[$t['match_field']] ?? '') ?></span> « <span translate="no"><?= $h($t['pattern']) ?></span> »<?= !empty($t['category']) ? ' · ' . $h($t['category']) : '' ?><?= !empty($t['archived_at']) ? ' · archivé' : '' ?></small>
              <form method="post" action="<?= $act('types/' . (int) $t['id'] . '/archiver') ?>"><?= $csrfField ?><input type="hidden" name="archiver" value="<?= empty($t['archived_at']) ? '1' : '0' ?>"><button class="ath-btn" type="submit"><?= empty($t['archived_at']) ? 'Archiver' : 'Réactiver' ?></button></form>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <section>
        <h3 class="bo-po-dlg__h">Règles « ignorer à l’avenir »</h3>
        <?php if ($po['rules'] === []): ?><p class="bo-po-muted">Aucune règle : créez-en une depuis « Gérer » › Ignorer › À l’avenir.</p><?php endif; ?>
        <ul class="bo-po__list">
          <?php foreach ($po['rules'] as $r): ?>
            <li>
              <span><span><?= $h(Rules::FIELDS[$r['match_field']] ?? '') ?></span> « <span translate="no"><?= $h($r['pattern']) ?></span> »</span>
              <small class="bo-po-muted"><?= !empty($r['note']) ? $h($r['note']) . ' · ' : '' ?><?= !empty($r['disabled_at']) ? 'désactivée' : 'active' ?></small>
              <form method="post" action="<?= $act('regles/' . (int) $r['id'] . '/basculer') ?>"><?= $csrfField ?><input type="hidden" name="desactiver" value="<?= empty($r['disabled_at']) ? '1' : '0' ?>"><button class="ath-btn" type="submit"><?= empty($r['disabled_at']) ? 'Désactiver' : 'Réactiver' ?></button></form>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <section>
        <h3 class="bo-po-dlg__h">Catégories</h3>
        <form method="post" action="<?= $act('categories') ?>" class="bo-po-dlg__form">
          <?= $csrfField ?>
          <label>Une par ligne
            <textarea name="liste" rows="6" maxlength="3000"><?= $h(implode("\n", $po['categories'])) ?></textarea>
          </label>
          <button class="ath-btn" type="submit">Enregistrer les catégories</button>
        </form>
      </section>
      <section>
        <h3 class="bo-po-dlg__h">Parties supprimées</h3>
        <?php if ($po['deleted'] === []): ?><p class="bo-po-muted">Aucune.</p><?php endif; ?>
        <ul class="bo-po__list">
          <?php foreach ($po['deleted'] as $o): ?>
            <li>
              <span><?= $h($o['label']) ?></span> <small class="bo-po-muted"><?= $h($when($o['start_ts'])) ?></small>
              <form method="post" action="<?= $act('restaurer') ?>"><?= $csrfField ?><input type="hidden" name="op" value="<?= $h($o['key']) ?>"><button class="ath-btn" type="submit">Restaurer</button></form>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
  </details>
  <?php endif; ?>
</section>
<script>
(function () {
  var root = document.getElementById(<?= json_encode($anchor) ?>);
  if (!root) { return; }
  root.querySelectorAll('[data-po-open]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById(b.getAttribute('data-po-open'));
      if (d && typeof d.showModal === 'function') { d.showModal(); d.returnFocusTo = b; }
    });
  });
  root.querySelectorAll('dialog').forEach(function (d) {
    d.querySelectorAll('[data-po-close]').forEach(function (c) { c.addEventListener('click', function () { d.close(); }); });
    d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
    d.addEventListener('close', function () { if (d.returnFocusTo) { d.returnFocusTo.focus(); } });
  });
  root.querySelectorAll('[data-po-details]').forEach(function (a) {
    a.addEventListener('click', function () { var p = document.getElementById(a.getAttribute('data-po-details')); if (p) { p.open = true; } });
  });
  var rows = root.querySelectorAll('.bo-po__row');
  root.querySelectorAll('[data-po-filter]').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = b.getAttribute('data-po-filter');
      root.querySelectorAll('[data-po-filter]').forEach(function (x) { x.classList.toggle('is-active', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      rows.forEach(function (tr) { tr.hidden = f !== 'all' && (' ' + tr.getAttribute('data-po-flags') + ' ').indexOf(' ' + f + ' ') < 0; });
    });
  });
  var bulk = root.querySelector('.bo-po__bulk');
  if (!bulk) { return; }
  var boxes = root.querySelectorAll('[data-po-row]');
  var all = root.querySelector('[data-po-all]');
  var out = bulk.querySelector('[data-po-checked]');
  var act = bulk.querySelector('[data-po-bulk-action]');
  var submit = bulk.querySelector('[data-po-bulk-submit]');
  var sync = function () {
    var n = 0;
    boxes.forEach(function (c) { n += c.checked ? 1 : 0; });
    out.textContent = n;
    bulk.querySelectorAll('[data-po-bulk-show]').forEach(function (el) { el.hidden = el.getAttribute('data-po-bulk-show') !== act.value; });
    submit.disabled = n === 0 || act.value === '';
  };
  boxes.forEach(function (c) { c.addEventListener('change', sync); });
  if (all) { all.addEventListener('change', function () { boxes.forEach(function (c) { if (!c.closest('tr').hidden) { c.checked = all.checked; } }); sync(); }); }
  act.addEventListener('change', sync);
  sync();
})();
</script>
