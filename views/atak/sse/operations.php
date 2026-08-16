<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $tower */
$kpi = is_array($tower['kpi'] ?? null) ? $tower['kpi'] : [];
$activity = is_array($tower['activity'] ?? null) ? $tower['activity'] : [];
$alerts = is_array($tower['alerts'] ?? null) ? $tower['alerts'] : [];
$queue = is_array($tower['operator_queue'] ?? null) ? $tower['operator_queue'] : [];
$recent = is_array($tower['recent_objects'] ?? null) ? $tower['recent_objects'] : [];
$quality = is_array($tower['data_quality'] ?? null) ? $tower['data_quality'] : [];
$charts = is_array($tower['charts'] ?? null) ? $tower['charts'] : [];
$clock = is_array($tower['clock'] ?? null) ? $tower['clock'] : [];
$activityChart = is_array($charts['activity_24h'] ?? null) ? $charts['activity_24h'] : ['labels' => [], 'values' => [], 'max' => 1];
$workload = is_array($charts['workload'] ?? null) ? $charts['workload'] : [];
$queueMax = max(1, (int) ($charts['queue_max'] ?? 1));
$workloadMax = 1;
foreach ($workload as $w) {
    $workloadMax = max($workloadMax, (int) ($w['value'] ?? 0));
}

$levelLabel = static function (string $level): string {
    return match (strtolower(trim($level))) {
        'critique' => 'Critique',
        'elevee', 'élevée' => 'Élevée',
        'faible' => 'Faible',
        default => 'Modérée',
    };
};

$kpis = [
    ['key' => 'active_cases', 'label' => 'Dossiers actifs', 'hint' => 'Exploitation en cours', 'tone' => 'ok'],
    ['key' => 'pressee_pending', 'label' => 'Dossiers d’intérêt', 'hint' => 'À instruire', 'tone' => 'warn'],
    ['key' => 'people', 'label' => 'Identités', 'hint' => 'Registre visible', 'tone' => 'ok'],
    ['key' => 'cross_pending', 'label' => 'Rapprochements', 'hint' => 'À confirmer', 'tone' => 'danger'],
    ['key' => 'stale_cases', 'label' => 'Sans activité', 'hint' => 'Plus de 3 jours', 'tone' => 'warn'],
    ['key' => 'sources', 'label' => 'Sources', 'hint' => (string) ($quality['sync_label'] ?? 'Synchronisé'), 'tone' => 'ok', 'text' => ((string) ($quality['sources_ok'] ?? 0)) . '/' . ((string) ($quality['sources_total'] ?? 0))],
];
?>
<div class="iw-tower" data-tower>
    <header class="iw-tower-hero">
        <div>
            <div class="page-heading-overline">Pilotage // Situation</div>
            <h1>Vue opérationnelle</h1>
            <p>
                Synthèse du centre SSE : dossiers, objets, signaux analytiques et file opérateur.
                Horodatage en heure Zulu (UTC).
            </p>
        </div>
        <aside class="iw-tower-clock" aria-live="polite">
            <span class="iw-tower-clock__label">Heure Zulu</span>
            <strong class="iw-tower-clock__time" id="iw-zulu-clock"><?= $h((string) ($clock['zulu'] ?? gmdate('H:i:s') . 'Z')) ?></strong>
            <em class="iw-tower-clock__date" id="iw-zulu-date"><?= $h((string) ($clock['zulu_date'] ?? gmdate('d/m/Y'))) ?></em>
            <span class="iw-tower-clock__meta">
                ATH-SSE-TOWER · Fraîcheur <?= $h((string) ($quality['freshness'] ?? '—')) ?>
                · Généré <?= $h((string) ($clock['generated_at'] ?? '')) ?>
            </span>
        </aside>
    </header>

    <div class="iw-tower-kpis">
        <?php foreach ($kpis as $i => $card): ?>
            <div class="iw-kpi iw-kpi--<?= $h($card['tone']) ?> iw-anim" style="--d:<?= $i * 60 ?>ms">
                <span><?= $h($card['label']) ?></span>
                <?php if (isset($card['text'])): ?>
                    <strong><?= $h($card['text']) ?></strong>
                <?php else: ?>
                    <strong data-count="<?= (int) ($kpi[$card['key']] ?? 0) ?>">0</strong>
                <?php endif; ?>
                <em><?= $h($card['hint']) ?></em>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="iw-tower-charts">
        <section class="panel iw-anim" style="--d:120ms">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">00.00</span> Activité 24 h</div>
                <div class="panel-meta">Créneaux 3 h · Zulu</div>
            </div>
            <div class="panel-body">
                <div class="iw-bars" role="img" aria-label="Activité sur 24 heures">
                    <?php
                    $labels = is_array($activityChart['labels'] ?? null) ? $activityChart['labels'] : [];
                    $values = is_array($activityChart['values'] ?? null) ? $activityChart['values'] : [];
                    $max = max(1, (int) ($activityChart['max'] ?? 1));
                    foreach ($labels as $i => $lab):
                        $val = (int) ($values[$i] ?? 0);
                        $pct = (int) round(($val / $max) * 100);
                        ?>
                        <div class="iw-bars__col">
                            <div class="iw-bars__track">
                                <i style="--h:<?= $pct ?>%" data-bar></i>
                            </div>
                            <b><?= $val ?></b>
                            <span><?= $h((string) $lab) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="panel iw-anim" style="--d:180ms">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">00.00b</span> Charge de travail</div>
                <div class="panel-meta">Répartition courante</div>
            </div>
            <div class="panel-body">
                <ul class="iw-workmix">
                    <?php foreach ($workload as $w): ?>
                        <?php
                        $val = (int) ($w['value'] ?? 0);
                        $pct = (int) round(($val / $workloadMax) * 100);
                        $color = (string) ($w['color'] ?? '#34d399');
                        ?>
                        <li>
                            <div class="iw-workmix__head">
                                <span><?= $h((string) ($w['label'] ?? '')) ?></span>
                                <b><?= $val ?></b>
                            </div>
                            <div class="iw-workmix__bar" aria-hidden="true">
                                <i style="--w:<?= $pct ?>%; --c:<?= $h($color) ?>" data-bar></i>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>

    <div class="iw-tower-grid">
        <section class="panel iw-anim" style="--d:220ms">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">00.01</span> Activité récente</div>
                <div class="panel-meta">Heure Zulu</div>
            </div>
            <div class="panel-body">
                <?php if ($activity === []): ?>
                    <div class="empty-state" style="min-height:180px;padding:1.5rem">
                        <div class="empty-state-inner">
                            <strong>Aucune activité récente</strong>
                            <p>Les acquisitions terrain et mises à jour de dossiers apparaîtront ici.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <ul class="iw-feed">
                        <?php foreach ($activity as $row): ?>
                            <li class="iw-feed__item is-<?= $h((string) ($row['kind'] ?? 'case')) ?>">
                                <time datetime="<?= $h((string) ($row['at_full'] ?? $row['at'] ?? '')) ?>" title="<?= $h((string) ($row['at_full'] ?? '')) ?>">
                                    <?= $h((string) ($row['at'] ?? '')) ?>
                                </time>
                                <?php if (!empty($row['href'])): ?>
                                    <a href="<?= $h((string) $row['href']) ?>"><?= $h((string) ($row['text'] ?? '')) ?></a>
                                <?php else: ?>
                                    <span><?= $h((string) ($row['text'] ?? '')) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel iw-anim" style="--d:280ms">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">00.02</span> Alertes &amp; signaux</div>
                <div class="panel-meta"><?= count($alerts) ?> active<?= count($alerts) > 1 ? 's' : '' ?></div>
            </div>
            <div class="panel-body">
                <?php foreach ($alerts as $a): ?>
                    <?php $lvl = (string) ($a['level'] ?? 'moderee'); ?>
                    <article class="iw-alert is-<?= $h($lvl) ?> <?= $lvl === 'critique' || $lvl === 'elevee' ? 'iw-alert--pulse' : '' ?>">
                        <header>
                            <span class="iw-alert__badge"><?= $h($levelLabel($lvl)) ?></span>
                            <strong><?= $h((string) ($a['title'] ?? '')) ?></strong>
                        </header>
                        <p><?= $h((string) ($a['detail'] ?? '')) ?></p>
                        <?php if (!empty($a['href'])): ?>
                            <a class="iw-alert__action" href="<?= $h((string) $a['href']) ?>">
                                <?= $h((string) ($a['action'] ?? 'Ouvrir')) ?> →
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel iw-anim" style="--d:340ms">
            <div class="panel-header">
                <div class="panel-title"><span class="panel-index">00.03</span> File opérateur</div>
            </div>
            <div class="panel-body iw-queue">
                <?php if ($queue === []): ?>
                    <p class="muted">File vide.</p>
                <?php else: ?>
                    <?php foreach ($queue as $q): ?>
                        <?php
                        $count = (int) ($q['count'] ?? 0);
                        $pct = (int) round(($count / $queueMax) * 100);
                        $tone = (string) ($q['tone'] ?? 'accent');
                        ?>
                        <a class="iw-queue__row is-<?= $h($tone) ?>" href="<?= $h((string) ($q['href'] ?? '#')) ?>">
                            <span><?= $h((string) ($q['label'] ?? '')) ?></span>
                            <b data-count="<?= $count ?>">0</b>
                            <i class="iw-queue__meter" aria-hidden="true"><i style="--w:<?= $pct ?>%" data-bar></i></i>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="panel iw-anim" style="margin-top:14px;--d:400ms">
        <div class="panel-header">
            <div class="panel-title"><span class="panel-index">00.04</span> Objets récemment détectés</div>
            <div class="panel-meta"><?= count($recent) ?> entrées</div>
        </div>
        <?php if ($recent === []): ?>
            <div class="panel-body"><p class="muted">Aucun objet récent sur le périmètre de session.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Type</th><th>Référence</th><th>Libellé</th><th>Heure Zulu</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr>
                            <td><?= $h((string) ($o['type'] ?? '')) ?></td>
                            <td class="record-id"><?= $h((string) ($o['ref'] ?? '')) ?></td>
                            <td><?= $h((string) ($o['label'] ?? '')) ?></td>
                            <td class="iw-zulu-cell"><?= $h((string) ($o['at'] ?? '—')) ?></td>
                            <td><a class="btn-open" href="<?= $h((string) ($o['href'] ?? '#')) ?>">Ouvrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function () {
  function pad(n) { return n < 10 ? '0' + n : String(n); }
  function tickZulu() {
    var now = new Date();
    var el = document.getElementById('iw-zulu-clock');
    var dEl = document.getElementById('iw-zulu-date');
    if (el) {
      el.textContent = pad(now.getUTCHours()) + ':' + pad(now.getUTCMinutes()) + ':' + pad(now.getUTCSeconds()) + 'Z';
    }
    if (dEl) {
      dEl.textContent = pad(now.getUTCDate()) + '/' + pad(now.getUTCMonth() + 1) + '/' + now.getUTCFullYear();
    }
  }
  tickZulu();
  setInterval(tickZulu, 1000);

  function animateCount(el) {
    var target = parseInt(el.getAttribute('data-count') || '0', 10);
    if (!target) { el.textContent = '0'; return; }
    var start = performance.now();
    var dur = 700;
    function frame(t) {
      var p = Math.min(1, (t - start) / dur);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = String(Math.round(target * eased));
      if (p < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }
  document.querySelectorAll('[data-count]').forEach(animateCount);

  requestAnimationFrame(function () {
    document.querySelectorAll('[data-bar]').forEach(function (bar) {
      bar.classList.add('is-on');
    });
    document.querySelectorAll('.iw-anim').forEach(function (el) {
      el.classList.add('is-in');
    });
  });
})();
</script>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
