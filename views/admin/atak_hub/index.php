<?php
declare(strict_types=1);

$cases = is_array($atakHubCases ?? null) ? $atakHubCases : [];
$livePeople = is_array($atakHubLivePeople ?? null) ? $atakHubLivePeople : [];
$maps = is_array($atakHubMaps ?? null) ? $atakHubMaps : [];
$mapId = (int) ($atakHubMapId ?? 1);
$canManage = !empty($canManageAtakHub);
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$apiBase = rtrim((string) url(''), '/');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$statusFr = static function (?string $status): string {
    return \App\Repositories\SsePersonRepository::statusLabel((string) $status);
};
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-8">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">ATAK · Poste</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Poste de situation</h1>
        <p class="mt-2 text-sm text-slate-600 max-w-3xl">Vue rapide des dossiers SSE déjà pourvus d’une identité, et placement d’un téléphone sous localisation — le contact apparaît alors sur la carte, comme depuis Zeus.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="<?= $h(url('atak')) ?>" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ouvrir la carte</a>
            <a href="<?= $h(url('back-office/atak/controle-serveur')) ?>" class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-950 hover:bg-emerald-100">Contrôle de mission</a>
            <a href="<?= $h(url('atak/sse/dossiers')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Tous les dossiers SSE</a>
            <a href="<?= $h(url('back-office/atak/realisme')) ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Parc de terminaux</a>
            <a href="<?= $h(url('back-office/atak/roleplay')) ?>" class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-950 hover:bg-blue-100">Mode roleplay</a>
        </div>
    </header>

    <?php if (count($maps) > 1): ?>
        <form method="get" action="<?= $h(url('back-office/atak')) ?>" class="mb-6 flex flex-wrap items-center gap-2">
            <label for="atak-hub-carte" class="text-sm font-semibold text-slate-700">Carte suivie</label>
            <select id="atak-hub-carte" name="carte" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
                <?php foreach ($maps as $m): ?>
                    <option value="<?= (int) $m['id'] ?>"<?= (int) $m['id'] === $mapId ? ' selected' : '' ?>><?= $h($m['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <div class="lg:col-span-8 space-y-8">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Dossiers SSE avec identité</h2>
                    <p class="mt-1 text-xs text-slate-500">Uniquement les dossiers ouverts qui ont au moins une personne rattachée. <?= count($cases) ?> dossier<?= count($cases) > 1 ? 's' : '' ?>.</p>
                </div>
                <?php if ($cases === []): ?>
                    <p class="px-6 py-8 text-sm text-slate-500">Aucun dossier avec une identité pour le moment. Les fiches se remplissent depuis le recueil terrain.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($cases as $case):
                            $cid = (int) ($case['id'] ?? 0);
                            $title = trim((string) ($case['title'] ?? ''));
                            if ($title === '') {
                                $title = 'Dossier sans titre';
                            }
                            $ref = trim((string) ($case['reference_code'] ?? ''));
                            $idents = is_array($case['identities'] ?? null) ? $case['identities'] : [];
                            ?>
                            <li class="px-6 py-4 space-y-3">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-slate-900"><?= $h($title) ?></p>
                                        <p class="text-xs text-slate-500"><?= $h($case['status_label'] ?? 'Ouvert') ?><?= $ref !== '' ? ' · ' . $h($ref) : '' ?> · <?= count($idents) ?> identité<?= count($idents) > 1 ? 's' : '' ?></p>
                                    </div>
                                    <?php if ($cid > 0): ?>
                                        <a class="text-sm font-semibold text-slate-900 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h(url('atak/sse/dossiers/' . $cid)) ?>">Ouvrir le dossier</a>
                                    <?php endif; ?>
                                </div>
                                <ul class="space-y-2">
                                    <?php foreach ($idents as $person):
                                        $pid = (int) ($person['id'] ?? 0);
                                        $pname = trim((string) ($person['display_name'] ?? 'Personne sans nom'));
                                        $hasNet = trim((string) ($person['target_unit_netid'] ?? '')) !== '';
                                        ?>
                                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900"><?= $h($pname) ?></p>
                                                <p class="text-xs text-slate-500"><?= $h($statusFr($person['status'] ?? null)) ?><?= $hasNet ? '' : ' · personne encore à rattacher au terrain' ?></p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <?php if ($pid > 0): ?>
                                                    <a class="text-xs font-semibold uppercase tracking-wide text-slate-700 underline decoration-slate-300 hover:decoration-slate-700" href="<?= $h(url('atak/sse/identites/' . $pid)) ?>">Fiche</a>
                                                <?php endif; ?>
                                                <?php if ($canManage && $pid > 0): ?>
                                                    <form method="post" action="<?= $h(url('back-office/atak/localisation-telephone')) ?>" onsubmit="return confirm('Placer le téléphone de <?= $h($pname) ?> sous localisation ? Le contact apparaîtra sur la carte.');">
                                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                                        <input type="hidden" name="map_id" value="<?= $mapId ?>">
                                                        <input type="hidden" name="source" value="person">
                                                        <input type="hidden" name="person_id" value="<?= $pid ?>">
                                                        <input type="hidden" name="action" value="on">
                                                        <button type="submit" class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-800 hover:bg-slate-100">Localiser le téléphone</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h2 class="text-sm font-black uppercase tracking-widest text-slate-900">Contacts actuellement visibles</h2>
                    <p class="mt-1 text-xs text-slate-500">Personnes déjà sur la carte. Vous pouvez placer un téléphone sous localisation, ou l’arrêter s’il l’est déjà.</p>
                </div>
                <?php if ($livePeople === []): ?>
                    <p class="px-6 py-8 text-sm text-slate-500">Aucun contact en liaison sur cette carte pour le moment.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($livePeople as $row):
                            $uid = (int) ($row['id'] ?? 0);
                            $ulabel = (string) ($row['label'] ?? 'Contact');
                            $tracked = !empty($row['tracked']);
                            ?>
                            <li class="flex flex-wrap items-center justify-between gap-2 px-6 py-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900"><?= $h($ulabel) ?></p>
                                    <p class="text-xs text-slate-500"><?= $tracked ? 'Téléphone déjà sous localisation' : 'Pas encore localisé comme téléphone' ?></p>
                                </div>
                                <?php if ($canManage && $uid > 0): ?>
                                    <form method="post" action="<?= $h(url('back-office/atak/localisation-telephone')) ?>" onsubmit="return confirm(<?= $h(json_encode($tracked ? ('Arrêter la localisation de « ' . $ulabel . ' » ?') : ('Placer le téléphone de « ' . $ulabel . ' » sous localisation ?'), JSON_UNESCAPED_UNICODE)) ?>);">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <input type="hidden" name="map_id" value="<?= $mapId ?>">
                                        <input type="hidden" name="source" value="unit">
                                        <input type="hidden" name="unit_id" value="<?= $uid ?>">
                                        <input type="hidden" name="action" value="<?= $tracked ? 'off' : 'on' ?>">
                                        <button type="submit" class="inline-flex rounded-md border <?= $tracked ? 'border-rose-200 text-rose-800 hover:bg-rose-50' : 'border-slate-300 text-slate-800 hover:bg-slate-100' ?> bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-wide"><?= $tracked ? 'Arrêter' : 'Localiser' ?></button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!$canManage): ?>
                    <p class="border-t border-slate-100 px-6 py-3 text-xs text-slate-500">Le placement sous localisation est réservé aux responsables ATAK de la communauté.</p>
                <?php endif; ?>
            </section>
        </div>

        <aside class="lg:col-span-4 space-y-5 lg:sticky lg:top-6" aria-label="Charge de liaison">
            <div id="atak-hub-sync-health" class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-slate-50 p-5 shadow-sm" data-map-id="<?= $mapId ?>">
                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-emerald-800/80">Charge de liaison</p>
                <h2 class="mt-1.5 text-base font-semibold text-slate-900">Données échangées</h2>
                <p class="mt-1 text-xs text-slate-500 leading-relaxed">Volume et débit entre le jeu, le poste et le site. Actualisé toutes les 10 secondes.</p>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl border border-slate-200/90 bg-white/90 px-3 py-2.5">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Dernière remontée</dt>
                        <dd class="mt-1 font-semibold text-slate-900" id="atak-hub-sync-ago">—</dd>
                    </div>
                    <div class="rounded-xl border border-slate-200/90 bg-white/90 px-3 py-2.5">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Débit récent</dt>
                        <dd class="mt-1 font-semibold text-slate-900 tabular-nums" id="atak-hub-sync-kbps">—</dd>
                    </div>
                    <div class="rounded-xl border border-slate-200/90 bg-white/90 px-3 py-2.5">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Volume 15 min</dt>
                        <dd class="mt-1 font-semibold text-slate-900 tabular-nums" id="atak-hub-sync-vol">—</dd>
                    </div>
                    <div class="rounded-xl border border-slate-200/90 bg-white/90 px-3 py-2.5">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Dont photos</dt>
                        <dd class="mt-1 font-semibold text-slate-900 tabular-nums" id="atak-hub-sync-photo">—</dd>
                    </div>
                    <div class="rounded-xl border border-slate-200/90 bg-white/90 px-3 py-2.5">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Opérateurs en liaison</dt>
                        <dd class="mt-1 font-semibold text-slate-900 tabular-nums" id="atak-hub-sync-ops">—</dd>
                    </div>
                    <div class="rounded-xl border border-slate-200/90 bg-white/90 px-3 py-2.5">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Repères carte</dt>
                        <dd class="mt-1 font-semibold text-slate-900 tabular-nums" id="atak-hub-sync-mk">—</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-slate-900 mb-2">Accès rapides</h2>
                <ul class="text-sm space-y-2">
                    <li><a href="<?= $h(url('atak-overwatch-beta')) ?>" class="text-emerald-900 underline font-medium hover:no-underline">Overwatch Beta</a></li>
                    <li><a href="<?= $h(url('admin/atak-config')) ?>" class="text-emerald-900 underline font-medium hover:no-underline">Réglages liaison (Tacmap)</a></li>
                    <li><a href="<?= $h(url('back-office/atak/operateurs')) ?>" class="text-emerald-900 underline font-medium hover:no-underline">Opérateurs en liaison</a></li>
                    <li><a href="<?= $h(url('atak/setup')) ?>" class="text-emerald-900 underline font-medium hover:no-underline">Assistant d’installation</a></li>
                </ul>
            </div>
        </aside>
    </div>
</div>
<script>
(function () {
  var root = document.getElementById('atak-hub-sync-health');
  if (!root) return;
  var mapId = root.getAttribute('data-map-id') || '1';
  var base = <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES) ?>;
  function fmtMo(bytes) {
    var n = Number(bytes || 0);
    if (n < 1024) return n + ' o';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' ko';
    return (n / (1024 * 1024)).toFixed(2) + ' Mo';
  }
  function tick() {
    fetch(base + '/api/atak/ingest-traffic?mapId=' + encodeURIComponent(mapId), { credentials: 'include' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        var ago = document.getElementById('atak-hub-sync-ago');
        var kbps = document.getElementById('atak-hub-sync-kbps');
        var vol = document.getElementById('atak-hub-sync-vol');
        var ops = document.getElementById('atak-hub-sync-ops');
        var mk = document.getElementById('atak-hub-sync-mk');
        var ph = document.getElementById('atak-hub-sync-photo');
        if (ago) ago.textContent = d.last_sync_ago || 'Aucune remontée';
        if (kbps) {
          var k = Number(d.kbps_now || 0);
          kbps.textContent = k >= 10 ? Math.round(k) + ' ko/s' : (k > 0 ? k.toFixed(1) + ' ko/s' : '0');
        }
        if (vol) vol.textContent = fmtMo(d.bytes_15m);
        if (ph) ph.textContent = fmtMo(d.photo_bytes_15m);
        var load = d.load || {};
        if (ops) {
          var live = Number(load.operators_live || 0);
          var total = Number(load.operators_total || 0);
          ops.textContent = live + (total > live ? ' / ' + total : '');
        }
        if (mk) mk.textContent = String(Number(load.markers || 0));
      })
      .catch(function () {});
  }
  tick();
  window.setInterval(tick, 10000);
})();
</script>
