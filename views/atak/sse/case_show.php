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
$caseUrl = url('atak/sse/dossiers/' . (int) $case['id']);
$frDate = static function (mixed $raw): string {
    $stamp = $raw !== null && (string) $raw !== '' ? strtotime((string) $raw) : false;

    return $stamp ? date('d/m/Y \à H\hi', $stamp) : '';
};
?>
<div class="breadcrumb">
    Athena / SSE /
    <a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">Dossiers</a> /
    <strong><?= $h($case['reference_code']) ?></strong>
</div>

<div class="page-heading sse-case-head">
    <div>
        <div class="page-heading-overline">Dossier // <?= $h($case['reference_code']) ?></div>
        <h1><?= $h($case['title']) ?></h1>
        <p>
            <span class="<?= $h($classBadge) ?>"><?= $h($case['classification_label']) ?></span>
            &nbsp;
            <span class="badge"><?= $h($case['status_label']) ?></span>
            <?php if (!empty($case['has_unlock_code'])): ?>
                &nbsp;<span class="badge badge--gray">Ouverture protégée par un mot de passe</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="sse-case-actions">
        <span class="sse-case-actions__label">Ce que l’on peut faire de ce dossier</span>
        <div class="sse-case-actions__row">
            <a class="btn" href="<?= $h($caseUrl . '/compte-rendu') ?>">Compte rendu</a>
            <a class="btn btn--ghost" href="<?= $h($caseUrl . '/correlations') ?>">Liens entre les éléments</a>
            <a class="btn btn--ghost" href="<?= $h($caseUrl . '/declassification') ?>">Version expurgée</a>
            <?php if ($canManage): ?>
                <a class="btn btn--ghost" href="<?= $h(url('atak/sse/toiles/nouveau?case=' . (int) $case['id'])) ?>">Ouvrir une investigation</a>
            <?php endif; ?>
            <?php if ($canExport): ?>
                <a class="btn btn--ghost" href="<?= $h($caseUrl . '/pdf') ?>">Exporter en PDF</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/case_progress.php'; ?>

<section class="panel sse-desk-panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.01</span>
            Chemise du dossier
        </div>
        <div class="panel-meta">Pièce de garde — à joindre à toute transmission</div>
    </div>
    <div class="panel-body">
        <?php
        $coverStats = [
            'Personnes rattachées' => count($people ?? []),
            'Notes classifiées' => count($notes ?? []),
            'Preuves versées' => count($evidence ?? []),
            'Sites exploités' => count($caseSites ?? []),
        ];
        require __DIR__ . '/partials/case_cover.php';
        ?>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.02</span>
            Synthèse du dossier
        </div>
        <?php if (!empty($case['updated_at'])): ?>
            <div class="panel-meta">Dernière modification le <?= $h($frDate($case['updated_at'])) ?></div>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <?php $summary = trim((string) ($case['summary'] ?? '')); ?>
        <?php if ($summary !== ''): ?>
            <p class="sse-case-summary"><?= nl2br($h($summary)) ?></p>
        <?php else: ?>
            <p class="muted">Aucune synthèse rédigée. C’est le premier texte que lira quiconque ouvre ce dossier.</p>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <details class="sse-fold">
                <summary>Modifier l’intitulé, la synthèse ou la classification</summary>
                <form method="post" action="<?= $h($caseUrl) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="title">Intitulé</label>
                    <input id="title" name="title" type="text" required value="<?= $h($case['title']) ?>">
                    <div class="grid-2">
                        <div>
                            <label for="classification">Qui a le droit de le lire</label>
                            <select id="classification" name="classification">
                                <?php foreach ($classifications as $k => $lab): ?>
                                    <option value="<?= $h($k) ?>" <?= $case['classification'] === $k ? 'selected' : '' ?>><?= $h($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="status">Où en est le dossier</label>
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
            </details>
        <?php endif; ?>
    </div>
</section>

<div class="grid-2">
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">01.03</span>
                Identités rattachées
            </div>
            <div class="panel-meta"><?= count($people) ?></div>
        </div>
        <div class="panel-body">
            <?php if ($people === []): ?>
                <p class="muted">
                    Personne n’est rattaché à ce dossier. Tant qu’il en est ainsi, le dossier
                    ne désigne personne.
                </p>
            <?php else: ?>
                <ul class="sse-case-people">
                    <?php foreach ($people as $p): ?>
                        <li>
                            <a class="sse-case-person" href="<?= $h(url('atak/sse/identites/' . (int) ($p['id'] ?? 0))) ?>">
                                <span class="record-name"><?= $h($p['display_name'] ?? '') ?></span>
                                <?php if (!empty($p['status_label'])): ?>
                                    <span class="record-sub"><?= $h($p['status_label']) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <details class="sse-fold" <?= $people === [] ? 'open' : '' ?>>
                    <summary>Rattacher une identité</summary>
                    <form method="post" action="<?= $h($caseUrl . '/personnes') ?>">
                        <?= \App\Core\Csrf::field() ?>
                        <label for="person_id">Fiche à rattacher</label>
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
                </details>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <span class="panel-index">01.04</span>
                Pièces versées
            </div>
            <div class="panel-meta"><?= count($evidence) ?></div>
        </div>
        <div class="panel-body">
            <?php if ($evidence === []): ?>
                <p class="muted">Aucune pièce versée : ni photographie, ni saisie, ni capture.</p>
            <?php else: ?>
                <ul class="sse-ev-grid">
                    <?php foreach ($evidence as $e): ?>
                        <li class="sse-ev-card">
                            <?php if (!empty($e['url'])): ?>
                                <a class="sse-ev-card__shot" href="<?= $h($e['url']) ?>" target="_blank" rel="noopener">
                                    <img src="<?= $h($e['url']) ?>" alt="<?= $h($e['label'] ?? 'Pièce versée') ?>" loading="lazy">
                                </a>
                            <?php else: ?>
                                <span class="sse-ev-card__shot is-empty">Sans image</span>
                            <?php endif; ?>
                            <strong><?= $h($e['label'] ?? '') ?></strong>
                            <?php if (!empty($e['caption'])): ?>
                                <span><?= $h($e['caption']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($e['created_at'])): ?>
                                <em>Versée le <?= $h($frDate($e['created_at'])) ?></em>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <details class="sse-fold">
                    <summary>Verser une pièce</summary>
                    <form method="post" action="<?= $h($caseUrl . '/preuves') ?>" enctype="multipart/form-data">
                        <?= \App\Core\Csrf::field() ?>
                        <label for="label">De quoi s’agit-il</label>
                        <input id="label" name="label" type="text" required placeholder="Ex. Téléphone saisi au point nord">
                        <label for="caption">Précision utile</label>
                        <input id="caption" name="caption" type="text" placeholder="Où, quand, par qui">
                        <label for="image">Photographie</label>
                        <input id="image" name="image" type="file" accept="image/*">
                        <button class="btn" type="submit">Verser au dossier</button>
                    </form>
                </details>
            <?php endif; ?>
        </div>
    </section>
</div>

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
            Notes classifiées
        </div>
        <div class="panel-meta"><?= count($notes) ?></div>
    </div>
    <div class="panel-body">
        <?php if ($notes === []): ?>
            <p class="muted">Aucune note. C’est ici que se consignent les observations qui n’ont pas leur place dans la synthèse.</p>
        <?php else: ?>
            <ul class="sse-case-notes">
                <?php foreach ($notes as $n): ?>
                    <li class="sse-case-note">
                        <div class="sse-case-note__head">
                            <span class="badge"><?= $h($n['classification_label']) ?></span>
                            <span><?= $h($n['author_label'] ?? 'Opérateur') ?><?php
                                $noteDate = $frDate($n['created_at'] ?? null);
                                echo $noteDate !== '' ? ' — ' . $h($noteDate) : '';
                            ?></span>
                        </div>
                        <p><?= nl2br($h($n['body'])) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <details class="sse-fold">
                <summary>Ajouter une note</summary>
                <form method="post" action="<?= $h($caseUrl . '/notes') ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <label for="body">Ce que vous voulez consigner</label>
                    <textarea id="body" name="body" required></textarea>
                    <label for="note_class">Qui a le droit de lire cette note</label>
                    <select id="note_class" name="classification">
                        <?php foreach ($classifications as $k => $lab): ?>
                            <option value="<?= $h($k) ?>"><?= $h($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn" type="submit">Ajouter la note</button>
                </form>
            </details>
        <?php endif; ?>
    </div>
</section>

<section id="tacmap" class="panel sse-tacmap-panel">
    <div class="panel-header">
        <div class="panel-title">
            <span class="panel-index">01.07</span>
            Carte tactique
        </div>
        <div class="panel-meta">La capture est versée aux pièces du dossier</div>
    </div>
    <div class="panel-body">
        <p class="sse-note">
            Positionnez la vue, puis capturez-la pour l’ajouter aux pièces du dossier.
        </p>
        <div id="sse-tacmap" class="sse-tacmap" role="img" aria-label="Carte tactique du dossier"></div>
        <?php if ($canManage): ?>
            <form id="sse-tacmap-form" class="sse-tacmap-form" method="post" action="<?= $h($caseUrl . '/tacmap-capture') ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="image_data" id="sse-tacmap-data" value="">
                <label for="sse-tacmap-caption">Légende de la capture</label>
                <input id="sse-tacmap-caption" name="caption" type="text" maxlength="200" placeholder="Ex. Approche nord du site">
                <button class="btn" type="button" id="sse-tacmap-capture-btn">Capturer la vue</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<p class="sse-case-back"><a class="link" href="<?= $h(url('atak/sse/dossiers')) ?>">← Retour aux dossiers</a></p>

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
