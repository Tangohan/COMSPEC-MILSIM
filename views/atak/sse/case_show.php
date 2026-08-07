<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var array<string,mixed> $case */
/** @var list<array<string,mixed>> $people */
/** @var list<array<string,mixed>> $availablePeople */
/** @var list<array<string,mixed>> $notes */
/** @var list<array<string,mixed>> $evidence */
/** @var array<string,string> $classifications */
/** @var array<string,string> $statuses */
/** @var bool $canManage */
/** @var bool $canExport */
$linkedIds = array_map(static fn (array $p): int => (int) ($p['id'] ?? 0), $people);
$classKey = (string) ($case['classification'] ?? '');
$classBadge = match ($classKey) {
    'confidentiel' => 'badge badge--amber',
    'tres_restreint' => 'badge badge--red',
    'interne' => 'badge badge--gray',
    default => 'badge',
};
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <strong><?= $h($case['reference_code']) ?></strong>
</div>

<div class="page-heading">
    <div>
        <div class="page-heading-overline">Dossier // <?= $h($case['reference_code']) ?></div>
        <h1><?= $h($case['title']) ?></h1>
        <p>
            <span class="<?= $h($classBadge) ?>"><?= $h($case['classification_label']) ?></span>
            &nbsp;
            <span class="badge"><?= $h($case['status_label']) ?></span>
            <?php if (!empty($case['has_unlock_code'])): ?>
                &nbsp;<span class="badge badge--gray">Code dossier défini</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-reference">
        <strong>Vue // Fiche dossier</strong>
        <?= $h($case['reference_code']) ?>
        <?php if ($canExport): ?>
            <div style="margin-top:.5rem">
                <a class="btn" href="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/pdf')) ?>">Exporter PDF</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.10</span>
            Mettre à jour
        </div>
    </div>
    <div class="panel-body">
        <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $case['id'])) ?>">
            <?= \App\Core\Csrf::field() ?>
            <label for="title">Intitulé</label>
            <input id="title" name="title" type="text" required value="<?= $h($case['title']) ?>">
            <div class="grid-2">
                <div>
                    <label for="classification">Classification</label>
                    <select id="classification" name="classification">
                        <?php foreach ($classifications as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= $case['classification'] === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <?php foreach ($statuses as $k => $lab): ?>
                            <option value="<?= $h($k) ?>" <?= $case['status'] === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label for="summary">Synthèse</label>
            <textarea id="summary" name="summary"><?= $h($case['summary'] ?? '') ?></textarea>
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </div>
</section>
<?php else: ?>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.10</span>
            Synthèse
        </div>
    </div>
    <div class="panel-body">
        <p><?= nl2br($h($case['summary'] ?? '—')) ?></p>
    </div>
</section>
<?php endif; ?>

<section id="tacmap" class="panel sse-tacmap-panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.14</span>
            Carte tactique — capture
        </div>
        <div class="panel-meta">Versée comme preuve</div>
    </div>
    <div class="panel-body">
        <p class="sse-note">
            Positionnez la vue, puis capturez-la pour l’ajouter aux preuves du dossier.
            La carte utilise le fond cartographique standard Athena.
        </p>
        <div id="sse-tacmap" class="sse-tacmap" role="img" aria-label="Carte tactique du dossier"></div>
        <?php if ($canManage): ?>
            <form id="sse-tacmap-form" method="post" action="<?= $h(url('atak/sse/dossiers/' . (int) $case['id'] . '/tacmap-capture')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="image_data" id="sse-tacmap-data" value="">
                <label for="sse-tacmap-caption">Légende de la capture</label>
                <input id="sse-tacmap-caption" name="caption" type="text" maxlength="200" placeholder="Ex. Approche nord du site">
                <button class="btn" type="button" id="sse-tacmap-capture-btn">Capturer la vue</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<div class="grid-2">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">01.11</span>
                Personnes rattachées
            </div>
        </div>
        <div class="panel-body">
            <?php if ($people === []): ?>
                <p class="muted">Aucune personne liée.</p>
            <?php else: ?>
                <?php foreach ($people as $p): ?>
                    <div class="note-item">
                        <span class="record-name"><?= $h($p['display_name'] ?? '') ?></span>
                        <span class="record-sub"><?= $h($p['status_label'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/personnes')) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="person_id">Rattacher une fiche</label>
                    <select id="person_id" name="person_id" required>
                        <option value="">Choisir…</option>
                        <?php foreach ($availablePeople as $p): ?>
                            <?php if (in_array((int) $p['id'], $linkedIds, true)) {
                                continue;
                            } ?>
                            <option value="<?= (int) $p['id'] ?>"><?= $h($p['display_name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn" type="submit">Rattacher</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">01.12</span>
                Preuves
            </div>
        </div>
        <div class="panel-body">
            <?php foreach ($evidence as $e): ?>
                <div class="ev-item">
                    <strong><?= $h($e['label']) ?></strong>
                    <?php if (!empty($e['caption'])): ?><div class="muted"><?= $h($e['caption']) ?></div><?php endif; ?>
                    <?php if (!empty($e['url'])): ?>
                        <div><a class="link" href="<?= $h($e['url']) ?>" target="_blank" rel="noopener">Voir l’image</a></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($evidence === []): ?><p class="muted">Aucune preuve.</p><?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/preuves')) ?>" enctype="multipart/form-data">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="label">Libellé</label>
                    <input id="label" name="label" type="text" required value="Preuve">
                    <label for="caption">Légende</label>
                    <input id="caption" name="caption" type="text">
                    <label for="image">Image (optionnel)</label>
                    <input id="image" name="image" type="file" accept="image/*">
                    <button class="btn" type="submit">Ajouter</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.13</span>
            Notes classifiées
        </div>
    </div>
    <div class="panel-body">
        <?php foreach ($notes as $n): ?>
            <div class="note-item">
                <span class="badge"><?= $h($n['classification_label']) ?></span>
                <span class="muted"> · <?= $h($n['author_label'] ?? 'Opérateur') ?>
                    <?php if (!empty($n['created_at'])): ?> · <?= $h($n['created_at']) ?><?php endif; ?></span>
                <p><?= nl2br($h($n['body'])) ?></p>
            </div>
        <?php endforeach; ?>
        <?php if ($notes === []): ?><p class="muted">Aucune note.</p><?php endif; ?>
        <?php if ($canManage): ?>
            <form method="post" action="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/notes')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <label for="body">Nouvelle note</label>
                <textarea id="body" name="body" required></textarea>
                <label for="note_class">Classification de la note</label>
                <select id="note_class" name="classification">
                    <?php foreach ($classifications as $k => $lab): ?>
                        <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Ajouter la note</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<p style="margin-top:1rem"><a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">← Retour aux dossiers</a></p>
<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.05</span>
            Sites exploités
        </div>
        <div class="panel-meta">Rattachés à ce dossier</div>
    </div>

    <?php if (($caseSites ?? []) === []): ?>
        <div class="panel-body">
            <p class="muted">
                Aucun site rattaché. Un site ouvert depuis le terrain avec la référence
                de ce dossier y apparaîtra automatiquement.
            </p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Référence</th>
                    <th>Site</th>
                    <th>Statut</th>
                    <th>Fouille</th>
                    <th>Saisies</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($caseSites as $s):
                    $cnt = ($siteCounts ?? [])[(int) ($s['id'] ?? 0)] ?? ['rooms' => 0, 'rooms_checked' => 0, 'seizures' => 0];
                    $pct = $cnt['rooms'] > 0 ? (int) round(($cnt['rooms_checked'] / $cnt['rooms']) * 100) : 0;
                    $pctClass = $pct >= 100 ? 'is-good' : ($pct >= 50 ? 'is-fair' : '');
                ?>
                    <tr>
                        <td><span class="record-id"><?= $h($s['reference_code'] ?? '') ?></span></td>
                        <td>
                            <span class="record-name"><?= $h($s['name'] ?? '') ?></span>
                            <span class="record-sub"><?= $h($s['site_type_label'] ?? '') ?></span>
                        </td>
                        <td><span class="badge"><?= $h($s['status_label'] ?? '') ?></span></td>
                        <td>
                            <span class="sse-score-cell">
                                <span class="sse-gauge <?= $h($pctClass) ?>">
                                    <span style="width: <?= $h((string) $pct) ?>%"></span>
                                </span>
                                <span class="sse-sample-score"><?= (int) $cnt['rooms_checked'] ?>/<?= (int) $cnt['rooms'] ?></span>
                            </span>
                        </td>
                        <td class="record-id"><?= (int) $cnt['seizures'] ?></td>
                        <td>
                            <a class="btn-open" href="<?= $h(url('atak/sse/sites/' . (int) ($s['id'] ?? 0))) ?>">Ouvrir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.06</span>
            Produits de renseignement
        </div>
    </div>
    <div class="panel-body">
        <p>
            Flash et compte rendu initial, générés depuis les éléments déjà versés au
            dossier — personnes, relevés, verdicts, sites et saisies.
        </p>
        <a class="btn" href="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/compte-rendu')) ?>">
            Ouvrir le compte rendu
        </a>
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/correlations')) ?>">
            Voir les corrélations
        </a>
        <?php if ($canManage): ?>
            <a class="btn btn--ghost" href="<?= $h(url('atak/sse/toiles/nouveau?case=' . (int) $case['id'])) ?>">
                Créer une toile depuis ce dossier
            </a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/toiles')) ?>">
            Toiles de données
        </a>
        <a class="btn btn--ghost" href="<?= $h(url('atak/sse/dossiers/' . $case['id'] . '/declassification')) ?>">
            Version expurgée
        </a>
    </div>
</section>

<?php
$sseNeedLeaflet = true;
$sseExtraScripts = <<<'JS'
<script>
(function () {
  var el = document.getElementById('sse-tacmap');
  if (!el || typeof L === 'undefined') return;
  var map = L.map(el, { zoomControl: true, attributionControl: true }).setView([48.8566, 2.3522], 6);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    crossOrigin: true,
    attribution: '&copy; OpenStreetMap &copy; CARTO'
  }).addTo(map);
  setTimeout(function () { map.invalidateSize(); }, 120);

  var btn = document.getElementById('sse-tacmap-capture-btn');
  var form = document.getElementById('sse-tacmap-form');
  var dataInput = document.getElementById('sse-tacmap-data');
  if (!btn || !form || !dataInput) return;

  btn.addEventListener('click', function () {
    btn.disabled = true;
    btn.textContent = 'Capture…';
    var size = map.getSize();
    var bounds = map.getBounds();
    var nw = map.project(bounds.getNorthWest(), map.getZoom());
    var canvas = document.createElement('canvas');
    canvas.width = size.x;
    canvas.height = size.y;
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#0b0f18';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    var tiles = [];
    el.querySelectorAll('.leaflet-tile-pane img').forEach(function (img) {
      if (!img.complete || !img.naturalWidth) return;
      var t = img.getBoundingClientRect();
      var p = el.getBoundingClientRect();
      tiles.push({ img: img, x: t.left - p.left, y: t.top - p.top, w: t.width, h: t.height });
    });
    tiles.forEach(function (t) {
      try { ctx.drawImage(t.img, t.x, t.y, t.w, t.h); } catch (e) {}
    });

    // Légende classification
    ctx.fillStyle = 'rgba(194,48,48,.92)';
    ctx.fillRect(0, 0, canvas.width, 22);
    ctx.fillStyle = '#fff';
    ctx.font = '700 11px monospace';
    ctx.fillText('ATHENA // SSE // CAPTURE TACMAP', 10, 15);
    ctx.fillStyle = 'rgba(0,0,0,.55)';
    ctx.fillRect(0, canvas.height - 24, canvas.width, 24);
    ctx.fillStyle = '#c8d4e4';
    ctx.font = '500 11px monospace';
    var c = map.getCenter();
    ctx.fillText('Z' + map.getZoom() + '  ' + c.lat.toFixed(5) + ', ' + c.lng.toFixed(5) + '  ' + new Date().toISOString().slice(0, 16).replace('T', ' '), 10, canvas.height - 8);

    try {
      dataInput.value = canvas.toDataURL('image/png');
    } catch (err) {
      btn.disabled = false;
      btn.textContent = 'Capturer la vue';
      alert('Impossible de capturer la carte (restriction navigateur). Réessayez après un instant.');
      return;
    }
    form.submit();
  });
})();
</script>
JS;
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
