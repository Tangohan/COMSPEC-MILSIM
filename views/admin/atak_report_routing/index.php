<?php
declare(strict_types=1);

/**
 * Back-office — Diffusion des rapports tactiques (/back-office/ressources/atak-diffusion-rapports).
 * En-tête de page : coque back-office (config/back_office_pages.php). Interactions : atak-report-routing.js.
 *
 * @var list<array<string, mixed>> $routingRules
 * @var array<string, array<string, string>> $routingTypeGroups
 * @var array<string, string> $routingPriorities
 * @var array{roles: list<array>, units: list<array>, users: list<array>} $routingDirectory
 * @var array{rules: array<int, array>, totals: array} $routingStats
 * @var list<array<string, mixed>> $routingJournal
 * @var list<array<string, mixed>> $routingRecentReports
 * @var string $routingTab
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rules = $routingRules ?? [];
$groups = $routingTypeGroups ?? [];
$typeLabels = array_merge(...array_values($groups ?: [[]]));
$prios = $routingPriorities ?? [];
$dir = $routingDirectory ?? ['roles' => [], 'units' => [], 'users' => []];
$stats = $routingStats ?? ['rules' => [], 'totals' => []];
$tot = $stats['totals'];
$tab = $routingTab ?? 'regles';
$base = url('back-office/ressources/atak-diffusion-rapports');
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken ?? '') . '">';
$active = count(array_filter($rules, static fn ($r) => !empty($r['is_active'])));
$pct = static fn ($a, $b): string => (int) $b > 0 ? (string) round(100 * (int) $a / (int) $b) . ' %' : '—';
$fmt = static function (?string $raw): string {
    $t = $raw ? strtotime($raw) : false;

    return $t ? date('d/m/Y H:i', $t) : '—';
};
$prioTag = static fn (string $p): string => ['FLASH' => 'bad', 'IMMEDIATE' => 'warn', 'PRIORITY' => 'info'][$p] ?? 'neut';
$chips = static function (array $items, string $tone = 'neut') use ($h): string {
    if ($items === []) {
        return '';
    }

    return implode(' ', array_map(static fn ($i) => '<span class="ath-tag ath-tag--' . (!empty($i['known']) || !isset($i['known']) ? $tone : 'warn') . '"'
        . (isset($i['known']) && !$i['known'] ? ' title="Ne correspond à aucune fonction, unité ou membre actuel : personne ne recevra le rapport par ce biais."' : '')
        . '>' . $h($i['label'] ?? $i) . (isset($i['known']) && !$i['known'] ? ' ⚠' : '') . '</span>', $items));
};
// Données de l'éditeur (règles existantes) pour le JS.
$rulesJson = array_map(static fn ($r) => [
    'id' => (int) $r['id'],
    'rule_name' => (string) $r['rule_name'],
    'is_active' => !empty($r['is_active']),
    'priority_order' => (int) $r['priority_order'],
    'report_types' => $r['_conditions']['report_types'] ?? [],
    'priorities' => $r['_conditions']['priorities'] ?? [],
    'keywords' => $r['_conditions']['keywords'] ?? [],
    'roles' => array_column($r['_auto_assign_to_roles'], 'id'),
    'units' => array_column($r['_auto_assign_to_units'], 'id'),
    'users' => array_column($r['_auto_assign_to_users'], 'id'),
    'escalate_after_minutes' => (int) ($r['escalate_after_minutes'] ?? 0),
    'escalate_roles' => array_column($r['_escalate_to_roles'], 'id'),
], $rules);
?>
<div class="bo-rt" id="bo-rt" data-base="<?= $h($base) ?>" data-csrf="<?= $h($csrfToken ?? '') ?>">

  <div class="ath-kpi-grid bo-rt-kpis">
    <article class="ath-card ath-kpi bo-rt-kpi<?= $active > 0 ? ' is-ok' : ' is-warn' ?>"><p class="ath-kpi__label">Règles actives</p><p class="ath-kpi__value"><?= $active ?> <small>/ <?= count($rules) ?></small></p><p class="ath-kpi__note"><?= $active > 0 ? 'appliquées à chaque rapport reçu' : 'aucune : les rapports arrivent sans destinataire' ?></p></article>
    <article class="ath-card ath-kpi bo-rt-kpi"><p class="ath-kpi__label">Rapports diffusés · 30 j</p><p class="ath-kpi__value"><?= (int) ($tot['reports'] ?? 0) ?> <small>/ <?= (int) ($tot['all_reports'] ?? 0) ?></small></p><p class="ath-kpi__note"><?= $pct($tot['reports'] ?? 0, $tot['all_reports'] ?? 0) ?> des rapports reçus</p></article>
    <article class="ath-card ath-kpi bo-rt-kpi"><p class="ath-kpi__label">Destinataires prévenus</p><p class="ath-kpi__value"><?= (int) ($tot['notified'] ?? 0) ?> <small>/ <?= (int) ($tot['routes'] ?? 0) ?></small></p><p class="ath-kpi__note">alerte en jeu envoyée</p></article>
    <article class="ath-card ath-kpi bo-rt-kpi"><p class="ath-kpi__label">Accusés de réception</p><p class="ath-kpi__value"><?= $pct($tot['acked'] ?? 0, $tot['routes'] ?? 0) ?></p><p class="ath-kpi__note"><?= isset($tot['ack_min']) && $tot['ack_min'] !== null ? 'en ' . (int) $tot['ack_min'] . ' min en moyenne' : 'aucun accusé' ?></p></article>
    <article class="ath-card ath-kpi bo-rt-kpi<?= (int) ($tot['waiting'] ?? 0) > 0 ? ' is-warn' : '' ?>"><p class="ath-kpi__label">En attente de lecture</p><p class="ath-kpi__value"><?= (int) ($tot['waiting'] ?? 0) ?></p><p class="ath-kpi__note"><a href="<?= $h($base . '?vue=journal&attente=1') ?>">voir le journal</a></p></article>
  </div>

  <nav class="bo-rt-tabs" aria-label="Vues">
    <?php foreach (['regles' => 'Règles', 'journal' => 'Journal des diffusions', 'simulateur' => 'Tester une règle'] as $k => $l): ?>
      <a href="<?= $h($base . ($k === 'regles' ? '' : '?vue=' . $k)) ?>" class="bo-rt-tabs__tab<?= $tab === $k ? ' is-active' : '' ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= $h($l) ?></a>
    <?php endforeach; ?>
    <span class="bo-rt-tabs__spacer"></span>
    <button type="button" class="ath-btn ath-btn--solid" data-edit="new">+ Nouvelle règle</button>
  </nav>

<?php if ($tab === 'regles'): ?>
  <?php if ($rules === []): ?>
    <section class="ath-panel bo-rt-empty">
      <h2 class="ath-panel__title">Aucune règle pour l’instant</h2>
      <p class="ath-panel__lead">Sans règle, les comptes rendus arrivent sans destinataire désigné. Une règle dit : « quand tel type de rapport arrive, prévenir telle fonction ou unité », et qui alerter si personne ne réagit.</p>
      <div class="bo-rt-presets">
        <button type="button" class="ath-btn" data-preset='{"rule_name":"Contacts et opérateurs à terre vers le PC","report_types":["TIC","EAGLE_DOWN","CONTACT"],"priorities":[],"escalate_after_minutes":5}'>Contacts → PC</button>
        <button type="button" class="ath-btn" data-preset='{"rule_name":"Tous les rapports FLASH","report_types":[],"priorities":["FLASH"],"escalate_after_minutes":3}'>Tout FLASH</button>
        <button type="button" class="ath-btn" data-preset='{"rule_name":"Renseignement (SALUTE, SPOTREP)","report_types":["SALUTE","SPOTREP"],"priorities":[]}'>Renseignement</button>
        <button type="button" class="ath-btn" data-preset='{"rule_name":"Logistique","report_types":["LOGREP"],"priorities":[]}'>Logistique</button>
      </div>
    </section>
  <?php else: ?>
    <form method="post" action="<?= $h($base . '/ordre') ?>" id="bo-rt-order"><?= $csrf ?>
    <ol class="bo-rt-rules">
      <?php foreach ($rules as $i => $r):
          $on = !empty($r['is_active']);
          $c = $r['_conditions'];
          $st = $stats['rules'][(int) $r['id']] ?? null;
          $types = array_map(static fn ($t) => ['label' => $typeLabels[$t] ?? $t], $c['report_types'] ?? []);
          $pr = $c['priorities'] ?? [];
          $kw = $c['keywords'] ?? [];
          $dest = array_merge($r['_auto_assign_to_roles'], $r['_auto_assign_to_units'], $r['_auto_assign_to_users']);
          $esc = $r['_escalate_to_roles'];
          ?>
      <li class="bo-rt-rule<?= $on ? '' : ' is-off' ?>" data-id="<?= (int) $r['id'] ?>">
        <input type="hidden" name="ids[]" value="<?= (int) $r['id'] ?>">
        <div class="bo-rt-rule__order">
          <span class="bo-rt-rule__n"><?= $i + 1 ?></span>
          <button type="button" class="bo-rt-move" data-move="-1" aria-label="Monter" <?= $i === 0 ? 'disabled' : '' ?>>▲</button>
          <button type="button" class="bo-rt-move" data-move="1" aria-label="Descendre" <?= $i === count($rules) - 1 ? 'disabled' : '' ?>>▼</button>
        </div>
        <div class="bo-rt-rule__body">
          <header class="bo-rt-rule__head">
            <h3><?= $h($r['rule_name']) ?></h3>
            <span class="ath-tag ath-tag--<?= $on ? 'ok' : 'neut' ?>"><?= $on ? 'Active' : 'Désactivée' ?></span>
          </header>
          <div class="bo-rt-flow">
            <div class="bo-rt-flow__step">
              <p class="bo-rt-flow__label">Quand arrive</p>
              <p><?= $types === [] ? '<span class="ath-tag ath-tag--info">Tous les types</span>' : $chips($types, 'info') ?>
                <?php foreach ($pr as $p): ?> <span class="ath-tag ath-tag--<?= $prioTag($p) ?>"><?= $h($prios[$p] ?? $p) ?></span><?php endforeach; ?></p>
              <?php if ($kw !== []): ?><p class="bo-rt-muted">contenant : <?= $h(implode(', ', $kw)) ?></p><?php endif; ?>
            </div>
            <div class="bo-rt-flow__arrow" aria-hidden="true">→</div>
            <div class="bo-rt-flow__step">
              <p class="bo-rt-flow__label">Diffuser à</p>
              <p><?= $chips($dest, 'ok') ?: '<span class="ath-tag ath-tag--bad">Personne</span>' ?></p>
            </div>
            <div class="bo-rt-flow__arrow" aria-hidden="true">→</div>
            <div class="bo-rt-flow__step">
              <p class="bo-rt-flow__label">Sans accusé</p>
              <p><?php if ((int) ($r['escalate_after_minutes'] ?? 0) > 0): ?>après <b><?= (int) $r['escalate_after_minutes'] ?> min</b> → <?= $chips($esc, 'warn') ?: '<span class="ath-tag ath-tag--bad">personne (escalade inactive)</span>' ?><?php else: ?><span class="bo-rt-muted">pas d’escalade</span><?php endif; ?></p>
            </div>
          </div>
          <footer class="bo-rt-rule__foot">
            <span class="bo-rt-muted"><?php if ($st): ?>30 j : <b><?= (int) $st['reports'] ?></b> rapport(s) · <?= $pct($st['acked'], $st['routes']) ?> accusés<?= $st['ack_min'] !== null ? ' en ' . (int) $st['ack_min'] . ' min' : '' ?> · dernier le <?= $h($fmt($st['last_at'])) ?> · <a href="<?= $h($base . '?vue=journal&regle=' . (int) $r['id']) ?>">journal</a><?php else: ?>Aucune diffusion sur 30 jours<?php endif; ?></span>
            <span class="bo-rt-actions">
              <button type="button" class="ath-btn" data-edit="<?= (int) $r['id'] ?>">Modifier</button>
              <button type="submit" class="ath-btn" form="bo-rt-dup-<?= (int) $r['id'] ?>">Dupliquer</button>
              <button type="submit" class="ath-btn" form="bo-rt-tog-<?= (int) $r['id'] ?>"><?= $on ? 'Désactiver' : 'Activer' ?></button>
              <button type="submit" class="ath-btn bo-rt-danger" form="bo-rt-del-<?= (int) $r['id'] ?>">Supprimer</button>
            </span>
          </footer>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>
    <p class="bo-rt-order-bar" id="bo-rt-order-bar" hidden><span>Ordre modifié.</span> <button type="submit" class="ath-btn ath-btn--solid">Enregistrer l’ordre</button> <a class="ath-btn" href="<?= $h($base) ?>">Annuler</a></p>
    </form>
    <?php foreach ($rules as $r): $rid = (int) $r['id']; $on = !empty($r['is_active']); ?>
      <form id="bo-rt-dup-<?= $rid ?>" method="post" action="<?= $h($base . '/' . $rid . '/dupliquer') ?>" hidden><?= $csrf ?></form>
      <form id="bo-rt-tog-<?= $rid ?>" method="post" action="<?= $h($base . '/' . $rid . '/etat') ?>" hidden><?= $csrf ?><input type="hidden" name="active" value="<?= $on ? '0' : '1' ?>"></form>
      <form id="bo-rt-del-<?= $rid ?>" method="post" action="<?= $h($base . '/' . $rid . '/supprimer') ?>" hidden onsubmit="return confirm('Supprimer cette règle ? Les diffusions déjà effectuées restent au journal.');"><?= $csrf ?></form>
    <?php endforeach; ?>
    <p class="bo-rt-note">Les règles s’appliquent toutes, dans l’ordre : un rapport peut en déclencher plusieurs. Chaque destinataire reçoit une alerte en jeu (ATAK et carte) ; s’il n’accuse pas réception dans le délai, les fonctions d’escalade sont prévenues à leur tour.</p>
  <?php endif; ?>

<?php elseif ($tab === 'journal'): ?>
  <section class="ath-table-panel">
    <form class="ath-table-toolbar" method="get" action="<?= $h($base) ?>">
      <input type="hidden" name="vue" value="journal">
      <span class="ath-table-toolbar__title">Diffusions</span>
      <span class="ath-table-toolbar__count"><?= count($routingJournal) ?></span>
      <span class="ath-table-toolbar__spacer"></span>
      <select name="regle" class="bo-rt-select" onchange="this.form.submit()" aria-label="Règle">
        <option value="">Toutes les règles</option>
        <?php foreach ($rules as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) ($routingJournalRule ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= $h($r['rule_name']) ?></option><?php endforeach; ?>
      </select>
      <label class="bo-rt-check"><input type="checkbox" name="attente" value="1" onchange="this.form.submit()" <?= !empty($routingJournalWaiting) ? 'checked' : '' ?>> En attente de lecture</label>
    </form>
    <?php if ($routingJournal === []): ?>
      <p class="bo-rt-emptyline">Aucune diffusion pour ces filtres.</p>
    <?php else: ?>
    <div class="ath-table-wrap">
      <table class="ath-table bo-rt-table">
        <thead><tr><th>Diffusé</th><th>Rapport</th><th>Règle</th><th>Destinataire</th><th>Alerte</th><th>Lecture</th></tr></thead>
        <tbody>
        <?php foreach ($routingJournal as $j): ?>
          <tr class="<?= $j['overdue'] ? 'is-overdue' : '' ?>">
            <td class="bo-rt-mono"><?= $h($fmt($j['routed_at'])) ?></td>
            <td>
              <a href="<?= $h(url('back-office/remontees/cr/' . (int) $j['report_id'])) ?>"><b><?= $h(($typeLabels[$j['report_type']] ?? $j['report_type']) . ' ' . ($j['report_number'] ?: '#' . $j['report_id'])) ?></b></a>
              <span class="ath-tag ath-tag--<?= $prioTag((string) $j['priority']) ?>"><?= $h($prios[$j['priority']] ?? $j['priority']) ?></span>
              <small><?= $h(mb_strimwidth((string) ($j['summary'] ?? ''), 0, 90, '…')) ?><?= $j['submitter_callsign'] ? ' · ' . $h($j['submitter_callsign']) : '' ?></small>
            </td>
            <td><?= $h($j['rule_name'] ?? 'Règle supprimée') ?></td>
            <td><?= $chips([$j['recipient']], 'neut') ?><small><?= $h(['ROLE' => 'Fonction', 'UNIT' => 'Unité', 'USER' => 'Membre'][$j['routed_to_type']] ?? $j['routed_to_type']) ?></small></td>
            <td><?= (int) $j['notification_sent'] === 1 ? '<span class="ath-tag ath-tag--ok">Envoyée</span><small>' . $h($fmt($j['notification_sent_at'])) . '</small>' : '<span class="ath-tag ath-tag--warn">Non envoyée</span>' ?></td>
            <td><?php if ((int) $j['acknowledged'] === 1): ?><span class="ath-tag ath-tag--ok">Lu en <?= (int) $j['delay_min'] ?> min</span><small><?= $h($j['ack_by'] ?? '') ?></small>
                <?php else: ?><span class="ath-tag ath-tag--<?= $j['overdue'] ? 'bad' : 'warn' ?>">Attend depuis <?= (int) $j['delay_min'] ?> min</span><?= $j['overdue'] ? '<small>délai d’escalade dépassé</small>' : '' ?><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

<?php else: ?>
  <div class="bo-rt-sim">
    <form class="ath-panel bo-rt-sim__form" id="bo-rt-sim">
      <h2 class="ath-panel__title">Tester les règles</h2>
      <p class="ath-panel__lead">Prenez un rapport déjà reçu, ou décrivez-en un : vous voyez quelles règles se déclenchent, pourquoi les autres non, et qui serait prévenu. Rien n’est envoyé.</p>
      <label class="bo-rt-field"><span>Rapport reçu</span>
        <select name="report_id" class="bo-rt-select">
          <option value="">— Décrire un rapport fictif —</option>
          <?php foreach ($routingRecentReports as $rp): ?><option value="<?= (int) $rp['id'] ?>"><?= $h(($typeLabels[$rp['report_type']] ?? $rp['report_type']) . ' ' . ($rp['report_number'] ?: '#' . $rp['id']) . ' · ' . ($prios[$rp['priority']] ?? $rp['priority']) . ' · ' . mb_strimwidth((string) $rp['summary'], 0, 60, '…')) ?></option><?php endforeach; ?>
        </select>
      </label>
      <div class="bo-rt-sim__fake">
        <label class="bo-rt-field"><span>Type</span><select name="report_type" class="bo-rt-select">
          <?php foreach ($groups as $g => $opts): ?><optgroup label="<?= $h($g) ?>"><?php foreach ($opts as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
        </select></label>
        <label class="bo-rt-field"><span>Priorité</span><select name="priority" class="bo-rt-select"><?php foreach ($prios as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label>
        <label class="bo-rt-field bo-rt-field--wide"><span>Texte du rapport</span><textarea name="text" rows="3" placeholder="Contact avec 2 blindés, embuscade sur l’axe NEPTUNE…"></textarea></label>
      </div>
      <button type="submit" class="ath-btn ath-btn--solid">Tester</button>
    </form>
    <section class="bo-rt-sim__out" id="bo-rt-sim-out" aria-live="polite"><p class="bo-rt-emptyline">Le résultat s’affiche ici.</p></section>
  </div>
<?php endif; ?>

  <!-- Éditeur de règle -->
  <div class="bo-rt-drawer" id="bo-rt-editor" hidden>
    <form class="bo-rt-drawer__panel" method="post" action="<?= $h($base) ?>" role="dialog" aria-modal="true" aria-labelledby="bo-rt-ed-title">
      <?= $csrf ?>
      <input type="hidden" name="id" value="">
      <header class="bo-rt-drawer__head">
        <div><p class="bo-rt-drawer__kicker">Règle de diffusion</p><h2 id="bo-rt-ed-title">Nouvelle règle</h2></div>
        <button type="button" class="ath-btn" data-close>Fermer</button>
      </header>
      <div class="bo-rt-drawer__body">
        <label class="bo-rt-field"><span>Nom</span><input name="rule_name" type="text" maxlength="200" required placeholder="Contacts vers le PC"></label>

        <fieldset class="bo-rt-step"><legend><b>1</b> Quand arrive…</legend>
          <?php foreach ($groups as $g => $opts): ?>
            <p class="bo-rt-sub"><?= $h($g) ?></p>
            <div class="bo-rt-pills"><?php foreach ($opts as $k => $l): ?><label class="bo-rt-pill"><input type="checkbox" name="report_types[]" value="<?= $h($k) ?>"><span><?= $h($l) ?></span></label><?php endforeach; ?></div>
          <?php endforeach; ?>
          <p class="bo-rt-hint">Rien de coché = tous les types.</p>
          <p class="bo-rt-sub">Priorité</p>
          <div class="bo-rt-pills"><?php foreach ($prios as $k => $l): ?><label class="bo-rt-pill is-<?= $prioTag($k) ?>"><input type="checkbox" name="priorities[]" value="<?= $h($k) ?>"><span><?= $h($l) ?></span></label><?php endforeach; ?></div>
          <label class="bo-rt-field"><span>Contenant l’un de ces mots (facultatif)</span><input name="keywords" type="text" placeholder="embuscade, IED, blindé"></label>
        </fieldset>

        <fieldset class="bo-rt-step"><legend><b>2</b> Diffuser à…</legend>
          <?php foreach (['roles' => ['Fonctions', $dir['roles']], 'units' => ['Unités', $dir['units']], 'users' => ['Membres', $dir['users']]] as $key => [$lbl, $list]): ?>
            <div class="bo-rt-picker" data-picker="<?= $h($key) ?>">
              <p class="bo-rt-sub"><?= $h($lbl) ?> <span class="bo-rt-count" data-count></span></p>
              <?php if ($list === []): ?><p class="bo-rt-hint">Aucune <?= $h(mb_strtolower($lbl)) ?> dans la communauté.</p><?php else: ?>
              <input type="search" class="bo-rt-filter" placeholder="Filtrer <?= $h(mb_strtolower($lbl)) ?>…" aria-label="Filtrer <?= $h($lbl) ?>">
              <div class="bo-rt-list">
                <?php foreach ($list as $o): ?><label data-text="<?= $h(mb_strtolower($o['label'] . ' ' . $o['sub'])) ?>"><input type="checkbox" name="<?= $h($key) ?>[]" value="<?= $h($o['id']) ?>"> <?= $h($o['label']) ?><?= $o['sub'] !== '' ? ' <small>' . $h($o['sub']) . '</small>' : '' ?></label><?php endforeach; ?>
              </div><?php endif; ?>
              <?php if ($key !== 'users'): ?><input type="hidden" name="<?= $h($key) ?>_extra" value=""><?php endif; ?>
              <div class="bo-rt-legacy" data-legacy hidden></div>
            </div>
          <?php endforeach; ?>
          <p class="bo-rt-hint">Au moins un destinataire. Chacun reçoit une alerte en jeu (ATAK et carte).</p>
        </fieldset>

        <fieldset class="bo-rt-step"><legend><b>3</b> Si personne n’accuse réception…</legend>
          <label class="bo-rt-field bo-rt-field--inline"><span>Prévenir après</span><input name="escalate_after_minutes" type="number" min="0" max="1440" value="0"> <span>minutes (0 = jamais)</span></label>
          <div class="bo-rt-picker" data-picker="escalate_roles">
            <p class="bo-rt-sub">Fonctions à prévenir <span class="bo-rt-count" data-count></span></p>
            <input type="search" class="bo-rt-filter" placeholder="Filtrer les fonctions…" aria-label="Filtrer les fonctions d’escalade">
            <div class="bo-rt-list">
              <?php foreach ($dir['roles'] as $o): ?><label data-text="<?= $h(mb_strtolower($o['label'] . ' ' . $o['sub'])) ?>"><input type="checkbox" name="escalate_roles[]" value="<?= $h($o['id']) ?>"> <?= $h($o['label']) ?></label><?php endforeach; ?>
            </div>
          </div>
        </fieldset>

        <div class="bo-rt-opts">
          <label class="bo-rt-field bo-rt-field--inline"><span>Ordre</span><input name="priority_order" type="number" min="1" max="9999" value="100"></label>
          <label class="bo-rt-check"><input type="checkbox" name="is_active" value="1" checked> Règle active</label>
        </div>
      </div>
      <footer class="bo-rt-drawer__foot"><button type="button" class="ath-btn" data-close>Annuler</button><button type="submit" class="ath-btn ath-btn--solid">Enregistrer la règle</button></footer>
    </form>
  </div>
  <script type="application/json" id="bo-rt-data"><?= json_encode(['rules' => $rulesJson], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</div>
<script src="<?= $h(asset_url('assets/js/atak-report-routing.js')) ?>" defer></script>
