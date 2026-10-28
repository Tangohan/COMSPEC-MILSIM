<?php
declare(strict_types=1);

/**
 * Back-office — Comptes rendus post-opération (/back-office/atak/comptes-rendus).
 * En-tête : coque back-office (config/back_office_pages.php). Vues : rapports, suivi des actions, missions sans compte rendu.
 *
 * @var list<array<string, mixed>> $aarReports  liste filtrée
 * @var list<array<string, mixed>> $aarAll
 * @var array<string, mixed> $aarFilters
 * @var array<string, int> $aarCounts
 * @var list<string> $aarAuthors
 * @var array<string, mixed> $aarKpis
 * @var list<array<string, mixed>> $aarActions
 * @var list<array<string, mixed>> $aarMissing
 */
use App\Services\Aar\AarReportAdminService as Aar;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = url('back-office/atak/comptes-rendus');
$reports = $aarReports ?? [];
$all = $aarAll ?? [];
$f = $aarFilters ?? [];
$counts = $aarCounts ?? [];
$k = $aarKpis ?? [];
$tab = $aarTab ?? 'rapports';
$canManage = !empty($aarCanManage);
$missing = $aarMissing ?? [];
$actions = $aarActions ?? [];
$actionState = $aarActionState ?? 'ouvertes';
$csrf = '<input type="hidden" name="_csrf_token" value="' . $h($csrfToken ?? '') . '">';
$fmt = static function (mixed $raw, string $format = 'd/m/Y'): string {
    $t = is_string($raw) && $raw !== '' ? strtotime($raw) : false;

    return $t ? date($format, $t) : '—';
};
$qs = static function (array $over = []) use ($f): string {
    $q = array_filter([
        'q' => $f['q'] ?? '',
        'status' => $f['status'] ?? '',
        'auteur' => $f['author'] ?? '',
        'periode' => (int) ($f['period'] ?? 0) ?: '',
        'open_actions' => !empty($f['open_actions']) ? '1' : '',
    ], static fn ($v) => $v !== '' && $v !== null);
    $q = array_filter(array_merge($q, $over), static fn ($v) => $v !== '' && $v !== null);

    return $q === [] ? '' : '?' . http_build_query($q);
};
$delayHours = $k['avg_delay_hours'] ?? null;
$filtersOn = ($f['q'] ?? '') !== '' || ($f['status'] ?? '') !== '' || ($f['author'] ?? '') !== '' || (int) ($f['period'] ?? 0) > 0 || !empty($f['open_actions']);
?>
<div class="bo-aar" id="bo-aar">

  <div class="ath-kpi-grid bo-aar-kpis">
    <article class="ath-card ath-kpi<?= (int) ($k['to_review'] ?? 0) > 0 ? ' is-warn' : '' ?>">
      <p class="ath-kpi__label">À relire</p>
      <p class="ath-kpi__value"><?= (int) ($k['to_review'] ?? 0) ?></p>
      <p class="ath-kpi__note"><?= (int) ($k['pending'] ?? 0) ?> en attente · <?= (int) ($k['in_review'] ?? 0) ?> en relecture</p>
    </article>
    <article class="ath-card ath-kpi">
      <p class="ath-kpi__label">Validés</p>
      <p class="ath-kpi__value"><?= (int) ($k['validated'] ?? 0) ?> <small>/ <?= (int) ($k['total'] ?? 0) ?></small></p>
      <p class="ath-kpi__note"><?= $k['validated_pct'] !== null ? (int) $k['validated_pct'] . ' % des comptes rendus' : 'aucun compte rendu' ?></p>
    </article>
    <article class="ath-card ath-kpi<?= $delayHours !== null && $delayHours > Aar::TARGET_DELAY_HOURS ? ' is-warn' : '' ?>">
      <p class="ath-kpi__label">Délai moyen de dépôt</p>
      <p class="ath-kpi__value"><?= $h(Aar::formatDelay($delayHours)) ?></p>
      <p class="ath-kpi__note"><?= (int) ($k['measured'] ?? 0) > 0 ? 'cible ' . Aar::TARGET_DELAY_HOURS . ' h · ' . (int) $k['late'] . ' hors délai' : 'mesuré quand une mission est liée' ?></p>
    </article>
    <article class="ath-card ath-kpi<?= (int) ($k['actions_overdue'] ?? 0) > 0 ? ' is-bad' : '' ?>">
      <p class="ath-kpi__label">Actions ouvertes</p>
      <p class="ath-kpi__value"><?= (int) ($k['actions_open'] ?? 0) ?> <small>/ <?= (int) ($k['actions_total'] ?? 0) ?></small></p>
      <p class="ath-kpi__note"><?= (int) ($k['actions_overdue'] ?? 0) > 0 ? '<a href="' . $h($base . '?vue=actions&etat=retard') . '">' . (int) $k['actions_overdue'] . ' en retard</a>' : 'aucune en retard' ?></p>
    </article>
    <article class="ath-card ath-kpi<?= (int) ($k['missing'] ?? 0) > 0 ? ' is-bad' : '' ?>">
      <p class="ath-kpi__label">Manquants</p>
      <p class="ath-kpi__value"><?= (int) ($k['missing'] ?? 0) ?></p>
      <p class="ath-kpi__note"><?= count($missing) > 0 ? '<a href="' . $h($base . '?vue=missions') . '">' . count($missing) . ' mission(s) clôturée(s) sans retour</a>' : 'toutes les missions ont leur retour' ?></p>
    </article>
  </div>

  <nav class="bo-aar-tabs" aria-label="Vues">
    <?php foreach (['rapports' => ['Comptes rendus', count($all)], 'actions' => ['Suivi des actions', (int) ($k['actions_open'] ?? 0)], 'missions' => ['Missions sans retour', count($missing)]] as $key => [$label, $n]): ?>
      <a href="<?= $h($base . ($key === 'rapports' ? '' : '?vue=' . $key)) ?>" class="bo-aar-tabs__tab<?= $tab === $key ? ' is-active' : '' ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= $h($label) ?> <span class="bo-aar-tabs__n"><?= (int) $n ?></span></a>
    <?php endforeach; ?>
    <span class="bo-aar-tabs__spacer"></span>
    <a class="ath-btn" href="<?= $h(url('back-office/atak/suivi-cr')) ?>">CR des joueurs (fin d’op)</a>
    <?php if ($canManage): ?>
      <a class="ath-btn" href="<?= $h($base . '/modeles') ?>">Modèles<?= (int) ($aarTemplateCount ?? 0) > 0 ? ' · ' . (int) $aarTemplateCount : '' ?></a>
      <a class="ath-btn ath-btn--solid" href="<?= $h($base . '/nouveau') ?>">+ Nouveau compte rendu</a>
    <?php endif; ?>
  </nav>

<?php if ($tab === 'rapports'): ?>
  <div class="bo-aar-seg" role="group" aria-label="Statut">
    <a href="<?= $h($base . $qs(['status' => ''])) ?>" class="bo-aar-seg__btn<?= ($f['status'] ?? '') === '' ? ' is-active' : '' ?>">Tous <b><?= count($all) ?></b></a>
    <?php foreach (Aar::STATUSES as $code => $label): ?>
      <a href="<?= $h($base . $qs(['status' => $code])) ?>" class="bo-aar-seg__btn<?= ($f['status'] ?? '') === $code ? ' is-active' : '' ?>"><span class="bo-aar-dot bo-aar-dot--<?= Aar::tone($code) ?>"></span><?= $h($label) ?> <b><?= (int) ($counts[$code] ?? 0) ?></b></a>
    <?php endforeach; ?>
  </div>

  <section class="ath-table-panel">
    <form class="ath-table-toolbar bo-aar-toolbar" method="get" action="<?= $h($base) ?>">
      <?php if (($f['status'] ?? '') !== ''): ?><input type="hidden" name="status" value="<?= $h($f['status']) ?>"><?php endif; ?>
      <span class="ath-table-toolbar__title">Comptes rendus</span>
      <span class="ath-table-toolbar__count"><?= count($reports) ?><?= $filtersOn ? ' / ' . count($all) : '' ?></span>
      <input class="ath-table-toolbar__search" type="search" name="q" value="<?= $h($f['q'] ?? '') ?>" placeholder="Référence, opération, auteur…" aria-label="Rechercher">
      <select class="ath-table-toolbar__filter" name="auteur" aria-label="Auteur" onchange="this.form.submit()">
        <option value="">Tous les auteurs</option>
        <?php foreach ($aarAuthors ?? [] as $a): ?><option value="<?= $h($a) ?>"<?= ($f['author'] ?? '') === $a ? ' selected' : '' ?>><?= $h($a) ?></option><?php endforeach; ?>
      </select>
      <select class="ath-table-toolbar__filter" name="periode" aria-label="Période" onchange="this.form.submit()">
        <?php foreach ([0 => 'Toute période', 30 => '30 derniers jours', 90 => '3 derniers mois', 365 => '12 derniers mois'] as $d => $l): ?><option value="<?= $d ?>"<?= (int) ($f['period'] ?? 0) === $d ? ' selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?>
      </select>
      <label class="bo-aar-check"><input type="checkbox" name="open_actions" value="1"<?= !empty($f['open_actions']) ? ' checked' : '' ?> onchange="this.form.submit()"> Actions ouvertes</label>
      <span class="ath-table-toolbar__spacer"></span>
      <?php if ($filtersOn): ?><a class="ath-btn" href="<?= $h($base) ?>">Effacer</a><?php endif; ?>
      <a class="ath-btn ath-table-toolbar__export" href="<?= $h($base . '/export.csv' . $qs()) ?>">Exporter (Excel)</a>
    </form>

    <?php if ($reports === []): ?>
      <div class="bo-aar-empty">
        <?php if ($all === []): ?>
          <h2 class="ath-panel__title">Aucun compte rendu pour l’instant</h2>
          <p class="ath-panel__lead">Après chaque opération, un responsable dépose son retour : ce qui a fonctionné, ce qui doit progresser et les actions décidées. Un brouillon s’ouvre aussi automatiquement à la clôture d’une mission planifiée.</p>
          <?php if ($canManage): ?><p><a class="ath-btn ath-btn--solid" href="<?= $h($base . '/nouveau') ?>">Rédiger le premier compte rendu</a></p><?php endif; ?>
        <?php else: ?>
          <p class="ath-panel__lead">Aucun compte rendu ne correspond à ces filtres. <a href="<?= $h($base) ?>">Tout afficher</a></p>
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="ath-table-wrap">
      <table class="ath-table bo-aar-table">
        <thead><tr>
          <th>Réf.</th><th>Opération</th><th>Auteur</th><th>Déposé</th><th>Délai</th>
          <th class="ath-th-num" title="Points forts / points faibles">Points</th><th class="ath-th-num">Actions</th><th>Statut</th><th>Relecture</th>
        </tr></thead>
        <tbody>
        <?php foreach ($reports as $r):
            $rid = (int) $r['id'];
            $st = (string) ($r['status'] ?? 'pending');
            $op = trim((string) ($r['operation_label'] ?? '')) !== '' ? (string) $r['operation_label'] : (string) ($r['title'] ?? '—');
            $rep = !empty($r['reported_at']) ? strtotime((string) $r['reported_at']) : false;
            $end = !empty($r['mission_ended_at']) ? strtotime((string) $r['mission_ended_at']) : false;
            $delay = $rep && $end && $rep >= $end ? ($rep - $end) / 3600 : null;
            $open = (int) ($r['totals']['open_actions'] ?? 0);
            $closed = (int) ($r['totals']['closed_actions'] ?? 0);
            $sCount = count($r['strengths'] ?? []);
            $wCount = count($r['weaknesses'] ?? []);
            ?>
          <tr class="ath-row--link" data-href="<?= $h($base . '/' . $rid) ?>">
            <td class="bo-aar-mono"><a href="<?= $h($base . '/' . $rid) ?>"><?= $h($r['reference_code'] ?? '') ?></a></td>
            <td>
              <span class="bo-aar-cell-title"><?= $h($op) ?></span>
              <?php if (trim((string) ($r['title'] ?? '')) !== '' && $r['title'] !== $op): ?><span class="bo-aar-cell-sub"><?= $h($r['title']) ?></span><?php endif; ?>
              <?php if (!empty($r['is_custom'])): ?><span class="ath-tag ath-tag--neut bo-aar-mini">Questionnaire</span><?php endif; ?>
            </td>
            <td><?= $h(trim((string) ($r['author_name'] ?? '')) ?: '—') ?></td>
            <td class="bo-aar-mono"><?= $h($fmt($r['reported_at'] ?? null)) ?></td>
            <td class="bo-aar-mono<?= $delay !== null && $delay > Aar::TARGET_DELAY_HOURS ? ' bo-aar-late' : '' ?>"><?= $h(Aar::formatDelay($delay)) ?></td>
            <td class="ath-td-num"><?php if ($sCount + $wCount > 0): ?><span class="bo-aar-ok">+<?= $sCount ?></span> / <span class="bo-aar-warn">−<?= $wCount ?></span><?php else: ?>—<?php endif; ?></td>
            <td class="ath-td-num"><?php if ($open + $closed > 0): ?>
              <span class="bo-aar-progress" title="<?= $closed ?> close(s) sur <?= $open + $closed ?>"><span style="width:<?= (int) round(100 * $closed / ($open + $closed)) ?>%"></span></span>
              <?= $closed ?>/<?= $open + $closed ?><?php else: ?>—<?php endif; ?></td>
            <td><span class="ath-tag ath-tag--<?= Aar::tone($st) ?>"><?= $h(Aar::statusLabel($st)) ?></span></td>
            <td><?= $st === 'validated' ? $h(trim((string) ($r['validator_name'] ?? '')) ?: '—') . ' <span class="bo-aar-cell-sub">' . $h($fmt($r['validated_at'] ?? null)) . '</span>' : '<span class="bo-aar-muted">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

<?php elseif ($tab === 'actions'): ?>
  <div class="bo-aar-seg" role="group" aria-label="État">
    <?php foreach (['ouvertes' => 'En cours', 'retard' => 'En retard', 'closes' => 'Closes', 'toutes' => 'Toutes'] as $code => $label): ?>
      <a href="<?= $h($base . '?vue=actions&etat=' . $code) ?>" class="bo-aar-seg__btn<?= $actionState === $code ? ' is-active' : '' ?>"><?= $h($label) ?></a>
    <?php endforeach; ?>
  </div>
  <section class="ath-table-panel">
    <div class="ath-table-toolbar">
      <span class="ath-table-toolbar__title">Actions décidées</span>
      <span class="ath-table-toolbar__count"><?= count($actions) ?></span>
      <span class="ath-table-toolbar__spacer"></span>
      <span class="bo-aar-muted">Toutes les actions issues des comptes rendus, en retard d’abord.</span>
    </div>
    <?php if ($actions === []): ?>
      <div class="bo-aar-empty"><p class="ath-panel__lead"><?= $actionState === 'retard' ? 'Aucune action en retard.' : 'Aucune action dans cette vue. Les actions se saisissent dans chaque compte rendu, avec un responsable et une échéance.' ?></p></div>
    <?php else: ?>
    <div class="ath-table-wrap">
      <table class="ath-table bo-aar-table">
        <thead><tr><th>Action</th><th>Responsable</th><th>Échéance</th><th>Compte rendu</th><th>État</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($actions as $a): ?>
          <tr>
            <td><span class="bo-aar-cell-title"><?= $h($a['label']) ?></span></td>
            <td><?= $h($a['owner'] !== '' ? $a['owner'] : '—') ?></td>
            <td class="bo-aar-mono<?= $a['overdue'] ? ' bo-aar-late' : '' ?>"><?= $h($fmt($a['due_at'])) ?></td>
            <td><a href="<?= $h($base . '/' . $a['report_id'] . '#actions') ?>" class="bo-aar-mono"><?= $h($a['reference']) ?></a> <span class="bo-aar-cell-sub"><?= $h($a['operation']) ?></span></td>
            <td><?php if ($a['state'] === 'closed'): ?><span class="ath-tag ath-tag--ok">Close<?= !empty($a['closed_at']) ? ' le ' . $h($fmt($a['closed_at'])) : '' ?></span><?php elseif ($a['overdue']): ?><span class="ath-tag ath-tag--bad">En retard</span><?php else: ?><span class="ath-tag ath-tag--warn">En cours</span><?php endif; ?></td>
            <?php if ($canManage): ?>
            <td class="bo-aar-right">
              <form method="post" action="<?= $h($base . '/' . $a['report_id'] . '/actions/basculer') ?>"><?= $csrf ?>
                <input type="hidden" name="state" value="<?= $h($a['state']) ?>"><input type="hidden" name="index" value="<?= (int) $a['index'] ?>"><input type="hidden" name="retour" value="suivi">
                <button type="submit" class="ath-btn"><?= $a['state'] === 'open' ? 'Clore' : 'Rouvrir' ?></button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

<?php else: ?>
  <section class="ath-table-panel">
    <div class="ath-table-toolbar">
      <span class="ath-table-toolbar__title">Missions clôturées sans compte rendu</span>
      <span class="ath-table-toolbar__count"><?= count($missing) ?></span>
      <span class="ath-table-toolbar__spacer"></span>
      <span class="bo-aar-muted">Cible : retour déposé dans les <?= Aar::TARGET_DELAY_HOURS ?> h après la fin.</span>
    </div>
    <?php if ($missing === []): ?>
      <div class="bo-aar-empty"><p class="ath-panel__lead">Chaque mission clôturée a son compte rendu.</p></div>
    <?php else: ?>
    <div class="ath-table-wrap">
      <table class="ath-table bo-aar-table">
        <thead><tr><th>Mission</th><th>Début</th><th>Fin</th><th>Depuis la fin</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($missing as $m): $hrs = $m['hours_since_end']; ?>
          <tr>
            <td><span class="bo-aar-cell-title"><?= $h($m['title'] ?: 'Mission #' . (int) $m['id']) ?></span></td>
            <td class="bo-aar-mono"><?= $h($fmt($m['started_at'] ?? null, 'd/m/Y H:i')) ?></td>
            <td class="bo-aar-mono"><?= $h($fmt($m['ended_at'] ?? null, 'd/m/Y H:i')) ?></td>
            <td><?php if ($hrs === null): ?>—<?php else: ?><span class="ath-tag ath-tag--<?= $m['late'] ? 'bad' : 'warn' ?>"><?= $h(Aar::formatDelay((float) $hrs)) ?><?= $m['late'] ? ' · hors délai' : '' ?></span><?php endif; ?></td>
            <?php if ($canManage): ?><td class="bo-aar-right"><a class="ath-btn ath-btn--solid" href="<?= $h($base . '/nouveau?mission=' . (int) $m['id']) ?>">Rédiger le compte rendu</a></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/back-office-aar.js')) ?>" defer></script>
