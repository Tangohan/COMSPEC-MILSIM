<?php
declare(strict_types=1);

use App\Services\Community\RegistryDirectory;

/** @var list<array<string, mixed>> $registryRows */
$rows = is_array($registryRows ?? null) ? $registryRows : [];
$pinRows = is_array($registryPinRows ?? null) ? $registryPinRows : [];
$trending = is_array($registryTrending ?? null) ? $registryTrending : [];
$base = (string) ($registryPinsBase ?? 'admin/system/annuaire');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$ok = \App\Core\Session::getFlash('success');
$err = \App\Core\Session::getFlash('error');
$days = RegistryDirectory::TREND_DAYS;
$activePins = count(array_filter($pinRows, static fn (array $p): bool => $p['active'] && $p['listed']));
$fmtDate = static function (?string $d): string {
    if ($d === null || $d === '') {
        return '';
    }
    $t = strtotime($d);

    return $t ? date('d/m/Y', $t) : $d;
};
$pinByTenant = [];
foreach ($pinRows as $p) {
    $pinByTenant[(int) $p['pin']['tenant_id']] = $p['pin'];
}
$signalsLine = static function (array $trend): string {
    $parts = [];
    $parts[] = (int) ($trend['visitors'] ?? 0) . ' visiteur(s)';
    $parts[] = '+' . (int) ($trend['recruits'] ?? 0) . ' recrue(s)';
    $parts[] = (int) ($trend['active'] ?? 0) . ' actif(s)';

    return implode(' · ', $parts);
};

/** Formulaire d'épinglage (création ou modification). */
$pinForm = static function (int $tenantId, ?array $pin) use ($h, $base): string {
    $pos = (int) ($pin['position'] ?? 1);
    $until = (string) ($pin['until'] ?? '');
    $note = (string) ($pin['note'] ?? '');
    ob_start(); ?>
    <form class="par-form" method="post" action="<?= $h(url($base . '/epingler')) ?>">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="tenant_id" value="<?= $tenantId ?>">
        <div class="par-form__grid">
            <div class="pa-field">
                <label for="par-pos-<?= $tenantId ?>">Ordre</label>
                <select id="par-pos-<?= $tenantId ?>" name="position">
                    <?php for ($i = 1; $i <= RegistryDirectory::MAX_POSITION; $i++): ?>
                        <option value="<?= $i ?>"<?= $i === $pos ? ' selected' : '' ?>><?= $i === 1 ? '1 (en premier)' : $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="pa-field">
                <label for="par-dur-<?= $tenantId ?>">Durée</label>
                <select id="par-dur-<?= $tenantId ?>" name="duration" data-par-duration>
                    <option value=""<?= $until === '' ? ' selected' : '' ?>>Sans limite</option>
                    <option value="7">7 jours</option>
                    <option value="14">14 jours</option>
                    <option value="30">30 jours</option>
                    <option value="90">90 jours</option>
                    <option value="date"<?= $until !== '' ? ' selected' : '' ?>>Jusqu’au…</option>
                </select>
            </div>
            <div class="pa-field" data-par-date<?= $until === '' ? ' hidden' : '' ?>>
                <label for="par-until-<?= $tenantId ?>">Dernier jour</label>
                <input id="par-until-<?= $tenantId ?>" type="date" name="until" value="<?= $h($until) ?>" min="<?= $h(date('Y-m-d')) ?>">
            </div>
        </div>
        <div class="pa-field">
            <label for="par-note-<?= $tenantId ?>">Note interne <span class="par-optional">(facultatif, jamais affichée)</span></label>
            <input id="par-note-<?= $tenantId ?>" type="text" name="note" maxlength="140" value="<?= $h($note) ?>" placeholder="Ex. partenaire de l’opération d’automne">
        </div>
        <div class="pa-actions">
            <button type="submit" class="pa-btn pa-btn--solid"><?= $pin !== null ? 'Enregistrer' : 'Épingler' ?></button>
        </div>
    </form>
    <?php
    return (string) ob_get_clean();
};
?>
<div class="pa par">
    <div class="pa__frame">
        <header class="pa-hero">
            <p class="pa-crumb">
                <a href="<?= $h(url('admin')) ?>">Administration du site</a>
                <span aria-hidden="true"> / </span>
                <a href="<?= $h(url('admin/tenants')) ?>">Communautés</a>
                <span aria-hidden="true"> / </span>
                Annuaire public
            </p>
            <h1 class="pa-hero__title">Épinglage et tendance de l’annuaire</h1>
            <p class="pa-hero__lead">
                Choisissez les communautés affichées en premier dans l’annuaire public, avec le badge « Épinglée ».
                Seules les communautés qui ont choisi d’apparaître dans l’annuaire peuvent être épinglées : une épingle ne rend jamais visible une communauté masquée.
            </p>
            <div class="pa-hero__actions">
                <a class="pa-btn pa-btn--solid" href="<?= $h(url('communities')) ?>" target="_blank" rel="noopener">Voir l’annuaire public</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/tenants')) ?>">Annuaire des communautés</a>
                <a class="pa-btn pa-btn--ghost" href="<?= $h(url('admin/system/annuaire/discord')) ?>">Vitrine Discord</a>
            </div>
        </header>

        <div class="pa-panel">
            <?php if ($ok): ?>
                <p class="pa-flash pa-flash--ok"><?= $h((string) $ok) ?></p>
            <?php endif; ?>
            <?php if ($err): ?>
                <p class="pa-flash pa-flash--err"><?= $h((string) $err) ?></p>
            <?php endif; ?>

            <dl class="pa-stats">
                <div class="pa-stat"><dt>Listées dans l’annuaire</dt><dd><?= count($rows) ?></dd></div>
                <div class="pa-stat"><dt>Épinglées</dt><dd><?= $activePins ?></dd></div>
                <div class="pa-stat"><dt>En tendance</dt><dd><?= count($trending) ?></dd></div>
                <div class="pa-stat"><dt>Fenêtre de tendance</dt><dd><?= $days ?> jours</dd></div>
            </dl>

            <div class="par-columns">
                <section class="pa-card" id="epinglees" aria-labelledby="par-pins-title">
                    <p class="pa-card__kicker">Épinglées</p>
                    <h2 class="pa-card__title" id="par-pins-title">Ordre d’affichage dans l’annuaire</h2>
                    <p class="pa-card__help">Les épinglées passent avant les autres, dans l’ordre choisi. Une épingle arrivée à son dernier jour disparaît seule.</p>
                    <?php if ($pinRows === []): ?>
                        <p class="par-empty">Aucune communauté épinglée. Utilisez « Épingler » dans la liste ci-dessous.</p>
                    <?php else: ?>
                        <ol class="par-pins">
                            <?php foreach ($pinRows as $p): ?>
                                <?php
                                $pin = $p['pin'];
                                $tid = (int) $pin['tenant_id'];
                                if (!$p['exists']) {
                                    [$stateLabel, $tone] = ['Communauté supprimée', 'rose'];
                                } elseif (!$p['listed']) {
                                    [$stateLabel, $tone] = ['Masquée de l’annuaire (sans effet)', 'amber'];
                                } elseif (!$p['active']) {
                                    [$stateLabel, $tone] = ['Terminée', 'slate'];
                                } else {
                                    [$stateLabel, $tone] = ['Affichée', 'mint'];
                                }
                                ?>
                                <li class="par-pin<?= $p['active'] && $p['listed'] ? '' : ' par-pin--off' ?>">
                                    <span class="par-pin__pos" aria-label="Ordre <?= (int) $pin['position'] ?>"><?= (int) $pin['position'] ?></span>
                                    <div class="par-pin__body">
                                        <p class="pa-name"><?= $h((string) $p['name']) ?></p>
                                        <p class="pa-sub">
                                            <span class="pa-pill pa-pill--<?= $h($tone) ?>"><?= $h($stateLabel) ?></span>
                                            <?= $pin['until'] !== null ? 'jusqu’au ' . $h($fmtDate($pin['until'])) . ' inclus' : 'sans limite' ?>
                                            <?php if ($pin['at'] !== ''): ?> · épinglée le <?= $h($fmtDate($pin['at'])) ?><?php endif; ?>
                                        </p>
                                        <?php if ($pin['note'] !== ''): ?>
                                            <p class="par-pin__note"><?= $h($pin['note']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($p['listed']): ?>
                                            <details class="par-edit">
                                                <summary>Modifier</summary>
                                                <?= $pinForm($tid, $pin) ?>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                    <form method="post" action="<?= $h(url($base . '/retirer')) ?>" class="par-pin__remove">
                                        <?= \App\Core\Csrf::field() ?>
                                        <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                        <button type="submit" class="pa-btn pa-btn--line">Désépingler</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>

                <section class="pa-card" aria-labelledby="par-trend-title">
                    <p class="pa-card__kicker">Tendance</p>
                    <h2 class="pa-card__title" id="par-trend-title">Unités en tendance (<?= $days ?> derniers jours)</h2>
                    <p class="pa-card__help">
                        Score = visiteurs de la fiche publique + <?= RegistryDirectory::RECRUIT_WEIGHT ?> × nouvelles recrues + membres connectés
                        + hausse des visiteurs par rapport aux <?= $days ?> jours précédents. Les <?= RegistryDirectory::TREND_LIMIT ?> premières au-dessus de <?= RegistryDirectory::TREND_MIN_SCORE ?> points sont affichées dans l’annuaire. Calcul rafraîchi toutes les 15 minutes.
                    </p>
                    <?php if ($trending === []): ?>
                        <p class="par-empty">Pas encore assez d’activité pour afficher des unités en tendance : le bandeau reste masqué dans l’annuaire.</p>
                    <?php else: ?>
                        <ol class="par-trend">
                            <?php foreach ($trending as $t): ?>
                                <?php $tr = (array) ($t['registry_trend'] ?? []); ?>
                                <li>
                                    <span class="par-trend__rank">#<?= (int) $t['registry_trending_rank'] ?></span>
                                    <span class="par-trend__name"><?= $h((string) ($t['name'] ?? '')) ?></span>
                                    <span class="par-trend__signals"><?= $h($signalsLine($tr)) ?></span>
                                    <span class="par-trend__score"><?= (int) ($tr['score'] ?? 0) ?> pts</span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            </div>

            <section class="pa-card" aria-labelledby="par-all-title">
                <div class="par-all__head">
                    <div>
                        <p class="pa-card__kicker">Communautés listées</p>
                        <h2 class="pa-card__title" id="par-all-title">Épingler une communauté</h2>
                    </div>
                    <label class="par-search">
                        <span class="sr-only">Rechercher une communauté</span>
                        <input type="search" placeholder="Rechercher par nom…" data-par-search autocomplete="off">
                    </label>
                </div>
                <?php if ($rows === []): ?>
                    <p class="par-empty">Aucune communauté n’apparaît dans l’annuaire public pour l’instant.</p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <thead>
                                <tr>
                                    <th>Communauté</th>
                                    <th>Activité (<?= $days ?> j)</th>
                                    <th style="text-align:right;">Score</th>
                                    <th>Épinglage</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $tid = (int) ($r['id'] ?? 0);
                                    $slug = (string) ($r['slug'] ?? '');
                                    $name = (string) ($r['name'] ?? $slug);
                                    $tr = (array) ($r['registry_trend'] ?? []);
                                    $pin = $pinByTenant[$tid] ?? null;
                                    $rank = (int) ($r['registry_trending_rank'] ?? 0);
                                    ?>
                                    <tr data-par-row data-par-name="<?= $h(mb_strtolower($name . ' ' . $slug)) ?>">
                                        <td>
                                            <p class="pa-name"><?= $h($name) ?></p>
                                            <p class="pa-sub"><a href="<?= $h(url('c/' . rawurlencode($slug))) ?>" target="_blank" rel="noopener">Page publique</a></p>
                                        </td>
                                        <td><?= $h($signalsLine($tr)) ?></td>
                                        <td style="text-align:right;">
                                            <span class="pa-num"><?= (int) ($tr['score'] ?? 0) ?></span>
                                            <?php if ($rank > 0): ?><span class="pa-pill pa-pill--blue">Tendance #<?= $rank ?></span><?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['registry_pinned'])): ?>
                                                <span class="pa-pill pa-pill--amber">Épinglée · <?= (int) $r['registry_pin_position'] ?></span>
                                            <?php elseif ($pin !== null): ?>
                                                <span class="pa-pill pa-pill--slate">Épingle terminée</span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <details class="par-edit par-edit--row">
                                                <summary class="pa-btn pa-btn--line"><?= $pin !== null ? 'Modifier' : 'Épingler' ?></summary>
                                                <?= $pinForm($tid, $pin) ?>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="par-empty" data-par-nomatch hidden>Aucune communauté ne correspond.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<script>
(function () {
  document.querySelectorAll('[data-par-duration]').forEach(function (sel) {
    var form = sel.closest('form');
    var dateField = form && form.querySelector('[data-par-date]');
    if (!dateField) return;
    sel.addEventListener('change', function () {
      dateField.hidden = sel.value !== 'date';
      var input = dateField.querySelector('input');
      if (input) input.required = sel.value === 'date';
    });
  });
  var search = document.querySelector('[data-par-search]');
  var rows = Array.prototype.slice.call(document.querySelectorAll('[data-par-row]'));
  var nomatch = document.querySelector('[data-par-nomatch]');
  if (search) {
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase();
      var visible = 0;
      rows.forEach(function (row) {
        var show = !q || (row.getAttribute('data-par-name') || '').indexOf(q) !== -1;
        row.hidden = !show;
        if (show) visible++;
      });
      if (nomatch) nomatch.hidden = visible !== 0;
    });
  }
})();
</script>
