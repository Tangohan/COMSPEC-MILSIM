<?php
declare(strict_types=1);

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$groupedRules = is_array($groupedRules ?? null) ? $groupedRules : [];
$groupLabels = is_array($groupLabels ?? null) ? $groupLabels : [];
$bridgeModules = is_array($bridgeModules ?? null) ? $bridgeModules : [];
$relays = is_array($relays ?? null) ? $relays : [];
$roleplay = is_array($roleplay ?? null) ? $roleplay : [];
$enabledModulesCount = (int) ($enabledModulesCount ?? 0);
$bridgeModulesTotal = (int) ($bridgeModulesTotal ?? 0);
$maintenanceEnabled = !empty($maintenanceEnabled);
$experienceSchemaReady = !empty($experienceSchemaReady);
$success = $success ?? null;
$error = $error ?? null;
$linkViaRelays = !empty($roleplay['link_via_relays']);
$networkOn = !empty($roleplay['network_enabled']);
$sensorOn = !empty($roleplay['sensor_enabled']);
$aliveRelays = 0;
foreach ($relays as $r) {
    if (!empty($r['alive'])) {
        $aliveRelays++;
    }
}
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="max-w-[1040px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">

        <header class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-emerald-50/40 shadow-sm">
            <div class="relative px-5 sm:px-8 py-7">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 mb-2">ATAK · Administration serveur</p>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Contrôle de mission</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-3xl leading-relaxed">
                    Ce que vous réglez ici s’applique à toute la communauté en liaison. Les opérateurs n’ont plus à chercher dans les options d’addons.
                    Les réglages personnels du téléphone (sons, indicatif, affichage situation) restent sur l’ATAK.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="<?= $h(url('back-office/atak/roleplay')) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Simulation réseau et capteurs</a>
                    <a href="<?= $h(url('admin/atak-config')) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Liaison technique</a>
                    <a href="<?= $h(url('back-office/atak/realisme')) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Parc de terminaux</a>
                </div>
            </div>
        </header>

        <?php if ($success): ?>
            <p class="text-sm text-green-800 bg-green-50 border border-green-200 rounded-xl px-4 py-3"><?= $h($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="text-sm text-red-800 bg-red-50 border border-red-200 rounded-xl px-4 py-3"><?= $h($error) ?></p>
        <?php endif; ?>

        <?php if ($maintenanceEnabled): ?>
            <div class="rounded-xl border border-amber-300 bg-amber-50 px-5 py-4">
                <p class="text-sm font-bold text-amber-950">Maintenance active</p>
                <p class="text-xs text-amber-900 mt-1">La carte et la liaison jeu sont coupées pour les opérateurs. Vous pouvez les rétablir depuis la liaison technique.</p>
            </div>
        <?php endif; ?>

        <section class="grid sm:grid-cols-3 gap-3">
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Fonctions actives</p>
                <p class="mt-2 text-2xl font-black text-slate-900"><?= $enabledModulesCount ?> / <?= $bridgeModulesTotal ?></p>
                <p class="mt-1 text-xs text-slate-500">Ponts entre le théâtre et le poste</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Relais</p>
                <p class="mt-2 text-2xl font-black text-slate-900"><?= $aliveRelays ?> / <?= count($relays) ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= $linkViaRelays ? 'Liaison exigée via un relais intact' : 'Liaison libre, sans relais obligatoire' ?></p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Simulation</p>
                <p class="mt-2 text-2xl font-black text-slate-900"><?= ($networkOn || $sensorOn) ? 'Active' : 'Off' ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= $networkOn ? 'Réseau dégradé' : 'Réseau normal' ?><?= $sensorOn ? ' · capteurs' : '' ?></p>
            </div>
        </section>

        <section id="regles" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
                <h2 class="text-sm font-black text-slate-900 tracking-tight">Règles de mission</h2>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">Imposez un comportement à toute la communauté, ou laissez chaque opérateur choisir. Les communautés déjà en place ne changent rien tant que vous n’enregistrez pas.</p>
            </div>
            <?php if (!$experienceSchemaReady): ?>
                <p class="px-5 py-6 text-sm text-red-800">Cette page ne peut pas encore enregistrer : la base n’est pas à jour. Contactez le support.</p>
            <?php else: ?>
                <form method="post" action="<?= $h(url('back-office/atak/controle-serveur/regles')) ?>" class="px-5 py-5 space-y-8" id="overwatch-server-rules">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <?php foreach ($groupLabels as $gid => $glabel): ?>
                        <?php $rows = $groupedRules[$gid] ?? []; ?>
                        <?php if ($rows === []) { continue; } ?>
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-3"><?= $h($glabel) ?></h3>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <?php foreach ($rows as $exp): ?>
                                    <?php
                                    $eid = (string) ($exp['id'] ?? '');
                                    $elabel = (string) ($exp['label'] ?? $eid);
                                    $edesc = (string) ($exp['description'] ?? '');
                                    $etype = (string) ($exp['type'] ?? 'bool');
                                    $eval = $exp['value'] ?? false;
                                    if ($eid === '') {
                                        continue;
                                    }
                                    ?>
                                    <?php if ($etype === 'tri' || $etype === 'list'): ?>
                                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
                                            <label class="block text-sm font-medium text-slate-800 mb-1" for="exp-<?= $h($eid) ?>"><?= $h($elabel) ?></label>
                                            <?php if ($edesc !== ''): ?>
                                                <p class="text-xs text-slate-500 mb-2 leading-relaxed"><?= $h($edesc) ?></p>
                                            <?php endif; ?>
                                            <select id="exp-<?= $h($eid) ?>" name="experience_<?= $h($eid) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                                <?php foreach (($exp['choices'] ?? []) as $ch): ?>
                                                    <?php $cv = (string) ($ch['value'] ?? ''); ?>
                                                    <option value="<?= $h($cv) ?>"<?= (string) $eval === $cv ? ' selected' : '' ?>><?= $h((string) ($ch['label'] ?? $cv)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 cursor-pointer hover:border-emerald-300">
                                            <input type="hidden" name="experience_<?= $h($eid) ?>" value="0">
                                            <input type="checkbox" name="experience_<?= $h($eid) ?>" value="1" class="mt-1 rounded border-slate-300"<?= !empty($eval) ? ' checked' : '' ?>>
                                            <span>
                                                <span class="block text-sm font-medium text-slate-800"><?= $h($elabel) ?></span>
                                                <?php if ($edesc !== ''): ?>
                                                    <span class="block text-xs text-slate-500 mt-0.5 leading-relaxed"><?= $h($edesc) ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div>
                        <button type="submit" class="inline-flex px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800">Enregistrer les règles</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section id="fonctions" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
                <h2 class="text-sm font-black text-slate-900 tracking-tight">Fonctions actives</h2>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">Décochez ce que votre mission n’utilise pas. Les opérateurs en liaison s’alignent sous environ une minute.</p>
            </div>
            <form method="post" action="<?= $h(url('back-office/atak/controle-serveur/fonctions')) ?>" class="px-5 py-5 space-y-4">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="grid sm:grid-cols-2 gap-3">
                    <?php foreach ($bridgeModules as $mod): ?>
                        <?php
                        $mid = (string) ($mod['id'] ?? '');
                        if ($mid === '') {
                            continue;
                        }
                        ?>
                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-3 cursor-pointer hover:border-emerald-300">
                            <input type="hidden" name="module_<?= $h($mid) ?>" value="0">
                            <input type="checkbox" name="module_<?= $h($mid) ?>" value="1" class="mt-1 rounded border-slate-300"<?= !empty($mod['enabled']) ? ' checked' : '' ?>>
                            <span>
                                <span class="block text-sm font-medium text-slate-800"><?= $h((string) ($mod['label'] ?? $mid)) ?></span>
                                <span class="block text-xs text-slate-500 mt-0.5 leading-relaxed"><?= $h((string) ($mod['description'] ?? '')) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="inline-flex px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800">Enregistrer les fonctions</button>
            </form>
        </section>

        <section id="relais" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
                <h2 class="text-sm font-black text-slate-900 tracking-tight">Relais de liaison</h2>
                <p class="mt-1 text-xs text-slate-600 leading-relaxed">Les mâts se posent en jeu (éditeur ou Zeus). La configuration des relais obligatoires est disponible dans <a href="<?= $h(url('back-office/atak/roleplay')) ?>" class="underline text-blue-600 hover:text-blue-800">Simulation réseau et capteurs</a>.</p>
            </div>
            <?php if ($relays === []): ?>
                <p class="px-5 py-6 text-sm text-slate-500">Aucun relais n’a encore été vu depuis le théâtre. Posez-en un en mission pour qu’il apparaisse ici.</p>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($relays as $relay): ?>
                        <?php
                        $alive = !empty($relay['alive']);
                        $range = (int) round((float) ($relay['range_m'] ?? 0));
                        $seen = trim((string) ($relay['last_seen_at'] ?? ''));
                        $name = trim((string) ($relay['display_name'] ?? ''));
                        if ($name === '') {
                            $name = trim((string) ($relay['identity'] ?? ''));
                        }
                        if ($name === '') {
                            $name = (string) ($relay['relay_uid'] ?? 'Relais');
                        }
                        $identity = trim((string) ($relay['identity'] ?? ''));
                        $ip = trim((string) ($relay['ip_addr'] ?? ''));
                        $gw = trim((string) ($relay['gateway'] ?? ''));
                        $cert = trim((string) ($relay['certificate'] ?? ''));
                        $slots = (int) ($relay['slots'] ?? 0);
                        $used = (int) ($relay['slots_used'] ?? 0);
                        $power = (int) ($relay['power_w'] ?? 0);
                        $thru = (float) ($relay['throughput_mbps'] ?? 0);
                        $relPct = (int) ($relay['reliability_pct'] ?? 0);
                        $bits = [];
                        if ((string) ($relay['map_label'] ?? '') !== '') {
                            $bits[] = (string) $relay['map_label'];
                        }
                        if ($identity !== '' && $identity !== $name) {
                            $bits[] = $identity;
                        }
                        if ($range > 0) {
                            $bits[] = 'portée ' . $range . ' m';
                        }
                        if ($alive) {
                            $bits[] = rtrim(rtrim(number_format($thru, 1, ',', ''), '0'), ',') . ' Mbit/s';
                            $bits[] = $relPct . ' %';
                            if ($slots > 0) {
                                $bits[] = $used . ' / ' . $slots . ' places';
                            }
                            if ($power > 0) {
                                $bits[] = $power . ' W';
                            }
                        }
                        if ($ip !== '') {
                            $bits[] = 'adresse réseau ' . $ip;
                        }
                        if ($gw !== '') {
                            $bits[] = 'passerelle ' . $gw;
                        }
                        if ($cert !== '') {
                            $bits[] = $cert;
                        }
                        if ($seen !== '') {
                            $bits[] = 'vu ' . $seen;
                        }
                        ?>
                        <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-900"><?= $h($name) ?></p>
                                <p class="text-xs text-slate-500"><?= $h(implode(' · ', $bits)) ?></p>
                            </div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold <?= $alive ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' ?>"><?= $alive ? 'Intact' : 'Hors service' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
