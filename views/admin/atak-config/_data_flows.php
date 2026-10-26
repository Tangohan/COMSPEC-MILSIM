<?php
/**
 * Données remontées du jeu (AtakDataFlowsService) : la communauté choisit ce que le mod envoie à Athena.
 * Variables : $dataFlows (flows, preset, chosen, updated_at), $dataFlowsCatalog, $baseUrl.
 */
$dataFlows = is_array($dataFlows ?? null) ? $dataFlows : ['flows' => [], 'preset' => 'full', 'chosen' => false, 'updated_at' => ''];
$dataFlowsCatalog = is_array($dataFlowsCatalog ?? null) ? $dataFlowsCatalog : [];
$dfFlows = is_array($dataFlows['flows'] ?? null) ? $dataFlows['flows'] : [];
$dfPreset = (string) ($dataFlows['preset'] ?? 'full');
$dfChosen = !empty($dataFlows['chosen']);
$dfUpdatedAt = (string) ($dataFlows['updated_at'] ?? '');
$dfPresets = [
    'full' => [
        'title' => 'Tout remonter',
        'text' => 'Carte web en direct : positions des joueurs, véhicules et drones, pertes, météo. Le plus complet, le plus de trafic.',
    ],
    'reports' => [
        'title' => 'Comptes rendus seulement',
        'text' => 'Pas de suivi en direct : seuls les rapports, photos, MEDEVAC, 9-line et messages partent. Le mod et la liaison sont les plus légers.',
    ],
    'custom' => [
        'title' => 'Sur mesure',
        'text' => 'Vous cochez vous-même ce qui est envoyé.',
    ],
];
?>
<section id="data-flows" class="mb-8 rounded-xl border <?= $dfChosen ? 'border-slate-200 bg-white' : 'border-amber-300 bg-amber-50/60' ?> p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-1">
        <h2 class="text-sm font-bold text-slate-800">Données remontées du jeu</h2>
        <?php if (!$dfChosen): ?>
            <span class="inline-flex items-center rounded-full bg-amber-200 px-2.5 py-0.5 text-xs font-bold text-amber-950">À choisir</span>
        <?php endif; ?>
    </div>
    <p class="text-xs text-slate-600 mb-4 leading-relaxed max-w-3xl">
        <?php if (!$dfChosen): ?>
            <strong class="text-amber-950">Votre communauté a-t-elle besoin du suivi en direct&nbsp;?</strong>
            Tant que vous n’avez pas choisi, tout est envoyé.
        <?php endif; ?>
        Ce que le mod envoie à Athena pendant les parties. Moins de données, c’est un jeu plus léger et une liaison plus stable.
        Les comptes rendus partent toujours. Vous pouvez changer d’avis à tout moment&nbsp;: les joueurs en liaison appliquent le choix sous une minute, sans relancer la partie.
    </p>
    <form action="<?= $baseUrl ?>/back-office/ressources/atak-config/data-flows" method="post" class="space-y-4" data-flows-form>
        <?= \App\Core\Csrf::field() ?>
        <div class="grid gap-3 sm:grid-cols-3">
            <?php foreach ($dfPresets as $pid => $p): ?>
                <label class="flex items-start gap-3 rounded-lg border bg-white px-3 py-3 cursor-pointer hover:border-emerald-300">
                    <input type="radio" name="preset" value="<?= htmlspecialchars($pid, ENT_QUOTES, 'UTF-8') ?>" class="mt-1" <?= $dfPreset === $pid ? 'checked' : '' ?> data-flows-preset />
                    <span>
                        <span class="block text-sm font-semibold text-slate-900"><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="block text-xs text-slate-500 mt-0.5 leading-relaxed"><?= htmlspecialchars($p['text'], ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <fieldset class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
            <legend class="px-1 text-xs font-bold uppercase tracking-wide text-slate-500">Détail</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                <?php foreach ($dataFlowsCatalog as $flow): ?>
                    <?php
                    $fid = (string) ($flow['id'] ?? '');
                    if ($fid === '') {
                        continue;
                    }
                    $fon = (bool) ($dfFlows[$fid] ?? true);
                    ?>
                    <label class="flex items-start gap-3 rounded-lg px-2 py-2 cursor-pointer hover:bg-white">
                        <input type="hidden" name="flow_<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>" value="0" />
                        <input type="checkbox" name="flow_<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>" value="1" class="mt-1 rounded border-slate-300" <?= $fon ? 'checked' : '' ?> data-flows-flow="<?= htmlspecialchars($fid, ENT_QUOTES, 'UTF-8') ?>" />
                        <span>
                            <span class="block text-sm font-medium text-slate-800"><?= htmlspecialchars((string) ($flow['label'] ?? $fid), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="block text-xs text-slate-500 mt-0.5 leading-relaxed"><?= htmlspecialchars((string) ($flow['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 px-2 text-xs text-slate-500">Toujours envoyés&nbsp;: rapports, photos, MEDEVAC, 9-line, messages et connexion des joueurs.</p>
        </fieldset>
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex px-4 py-2 bg-emerald-700 text-white text-sm font-semibold rounded-lg hover:bg-emerald-800">Enregistrer ce choix</button>
            <?php if ($dfUpdatedAt !== ''): ?>
                <span class="text-xs text-slate-400">Dernier choix&nbsp;: <?= htmlspecialchars($dfUpdatedAt, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
    </form>
    <script>
    (function () {
        var form = document.querySelector('[data-flows-form]');
        if (!form) { return; }
        var presets = { full: true, reports: false };
        var boxes = form.querySelectorAll('[data-flows-flow]');
        form.querySelectorAll('[data-flows-preset]').forEach(function (r) {
            r.addEventListener('change', function () {
                if (!(r.value in presets)) { return; }
                boxes.forEach(function (b) { b.checked = presets[r.value]; });
            });
        });
        boxes.forEach(function (b) {
            b.addEventListener('change', function () {
                var all = Array.prototype.every.call(boxes, function (x) { return x.checked; });
                var none = Array.prototype.every.call(boxes, function (x) { return !x.checked; });
                var v = all ? 'full' : (none ? 'reports' : 'custom');
                var r = form.querySelector('[data-flows-preset][value="' + v + '"]');
                if (r) { r.checked = true; }
            });
        });
    })();
    </script>
</section>
