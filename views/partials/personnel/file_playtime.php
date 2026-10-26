<?php
/**
 * Temps de jeu de la fiche personnel.
 * Variables attendues : $armaPlaytime, $atakPlayStats, $atakRoleTimes, $armaSessionActivity,
 * $canCorrectRoleTime, $roleTimeMoves, $roleTimePathSegment, $steamId, $viewerIsPersonnelSubject.
 * Définit $roleDur pour les blocs distances / pilote plus bas dans la fiche.
 */
$atakRoleTimes = is_array($atakRoleTimes ?? null) ? $atakRoleTimes : [];
$roleDur = static function (int $s): string {
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);

    return $h > 0 ? $h . ' h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : max(1, $m) . ' min';
};
$canCorrectRoleTime = !empty($canCorrectRoleTime);
$roleTimeMoves = is_array($roleTimeMoves ?? null) ? $roleTimeMoves : [];
$ptBlock = is_array($armaPlaytime ?? null) ? $armaPlaytime : null;
$playStats = is_array($atakPlayStats ?? null) ? $atakPlayStats : null;
$armaAct = is_array($armaSessionActivity ?? null) ? $armaSessionActivity : null;
$ptReady = $ptBlock !== null && !empty($ptBlock['schema_ready']) && ($steamId ?? null) !== null && ($ptBlock['hours_label'] ?? null) !== null;
$showRoleColumn = $atakRoleTimes !== [] || ($canCorrectRoleTime && $roleTimeMoves !== []);
$roleTotal = max(1, array_sum(array_map(static fn (array $r): int => (int) $r['seconds'], $atakRoleTimes)));
$roleBadge = [
    'crew' => 'bg-amber-100 text-amber-800',
    'team' => 'bg-sky-100 text-sky-800',
    'specialty' => 'bg-violet-100 text-violet-800',
    'slot' => 'bg-slate-200/80 text-slate-700',
    'manual' => 'bg-rose-100 text-rose-700',
];
if ($ptBlock === null && !$showRoleColumn && $armaAct === null) {
    return;
}
$ptParts = [];
$ptBase = 1;
if ($ptReady) {
    $ptTotal = (int) ($ptBlock['total_seconds'] ?? 0);
    $ptParts = array_values(array_filter([
        ['label' => 'En serveur', 'seconds' => (int) ($ptBlock['server_seconds'] ?? 0), 'dot' => 'bg-emerald-500'],
        ['label' => 'En Zeus', 'seconds' => (int) ($ptBlock['zeus_seconds'] ?? 0), 'dot' => 'bg-sky-500'],
        ['label' => 'Dans l’éditeur', 'seconds' => (int) ($ptBlock['editor_seconds'] ?? 0), 'dot' => 'bg-amber-400'],
        ['label' => 'Avant le détail', 'seconds' => (int) ($ptBlock['unsplit_seconds'] ?? 0), 'dot' => 'bg-slate-400'],
    ], static fn (array $p): bool => $p['seconds'] > 0));
    $ptBase = max(1, $ptTotal, array_sum(array_column($ptParts, 'seconds')));
}
?>
<section id="temps-par-role" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
        <div>
            <h2 class="text-xs font-black uppercase tracking-[0.35em] text-slate-900">Temps de jeu</h2>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-600">Cumul des parties reliées à ce dossier, puis le détail des 30 derniers jours.</p>
        </div>
        <?php if ($ptReady && !empty($ptBlock['last_sync_label'])): ?>
        <p class="text-xs text-slate-500">Dernière remontée le <?= htmlspecialchars((string) $ptBlock['last_sync_label'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>

    <?php if ($ptBlock !== null && !empty($ptBlock['show_steam_hint_self'])): ?>
        <p class="mt-5 text-sm text-slate-700">Indiquez dans les préférences du compte le même identifiant que dans le jeu pour que le cumul puisse être rattaché à votre dossier.</p>
    <?php elseif ($ptBlock !== null && !empty($ptBlock['no_steam_staff'])): ?>
        <p class="mt-5 text-sm text-slate-600">Non renseigné sur ce dossier.</p>
    <?php elseif ($ptReady): ?>
        <div class="mt-6 flex flex-wrap items-end gap-x-4 gap-y-1">
            <p class="text-4xl font-semibold tabular-nums tracking-tight text-slate-900"><?= htmlspecialchars((string) $ptBlock['hours_label'], ENT_QUOTES, 'UTF-8') ?></p>
            <p class="pb-1 text-sm text-slate-500">cumul en mission</p>
        </div>
        <?php if ($playStats !== null): ?>
        <?php
        $lastPlayTs = ($playStats['last_day'] ?? '') !== '' ? strtotime((string) $playStats['last_day']) : false;
        $kpis = [
            ['7 derniers jours', (int) $playStats['play_7d'] > 0 ? $roleDur((int) $playStats['play_7d']) : '—'],
            ['30 derniers jours', (int) $playStats['play_30d'] > 0 ? $roleDur((int) $playStats['play_30d']) : '—'],
            ['Sessions sur 30 jours', (string) (int) $playStats['sessions_30d']],
            ['Dernière partie', $lastPlayTs ? date('d/m/Y', $lastPlayTs) : '—'],
        ];
        ?>
        <dl class="mt-5 grid grid-cols-2 border-y border-slate-100 sm:grid-cols-4">
            <?php foreach ($kpis as $i => [$kLabel, $kValue]): ?>
            <div class="px-0 py-3 <?= $i % 2 === 1 ? 'pl-4 sm:pl-5' : '' ?> <?= $i > 0 ? 'sm:border-l sm:border-slate-100 sm:pl-5' : '' ?> <?= $i >= 2 ? 'border-t border-slate-100 sm:border-t-0' : '' ?>">
                <dt class="text-[11px] font-medium text-slate-500"><?= htmlspecialchars($kLabel, ENT_QUOTES, 'UTF-8') ?></dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums text-slate-900"><?= htmlspecialchars($kValue, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
        <?php endif; ?>
        <?php if ($ptParts !== []): ?>
        <div class="mt-6">
            <p class="text-xs font-medium text-slate-500">Où ce temps a été joué</p>
            <div class="mt-2 flex h-3 w-full overflow-hidden rounded-full bg-slate-100" role="img" aria-label="<?= htmlspecialchars(implode(', ', array_map(static fn (array $p): string => $p['label'] . ' ' . $roleDur($p['seconds']), $ptParts)), ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ($ptParts as $part): ?>
                <div class="h-full <?= $part['dot'] ?>" style="width: <?= max(1, (int) round(100 * $part['seconds'] / $ptBase)) ?>%" title="<?= htmlspecialchars($part['label'] . ' : ' . $roleDur($part['seconds']), ENT_QUOTES, 'UTF-8') ?>"></div>
                <?php endforeach; ?>
            </div>
            <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-slate-600">
                <?php foreach ($ptParts as $part): ?>
                <li class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full <?= $part['dot'] ?>" aria-hidden="true"></span><?= htmlspecialchars($part['label'], ENT_QUOTES, 'UTF-8') ?> <span class="font-semibold tabular-nums text-slate-900"><?= htmlspecialchars($roleDur($part['seconds']), ENT_QUOTES, 'UTF-8') ?></span> <span class="tabular-nums text-slate-400"><?= (int) round(100 * $part['seconds'] / $ptBase) ?> %</span></li>
                <?php endforeach; ?>
            </ul>
            <?php if ((int) ($ptBlock['unsplit_seconds'] ?? 0) > 0): ?>
            <p class="mt-2 text-xs leading-relaxed text-slate-500">« Avant le détail » : temps remonté avant que le jeu distingue serveur, Zeus et éditeur. Il compte dans le cumul.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php elseif ($ptBlock !== null && ($steamId ?? null) !== null): ?>
        <p class="mt-5 text-sm text-slate-600">Le cumul sera affiché après mise à jour du suivi côté portail.</p>
    <?php endif; ?>

    <?php if ($armaAct): ?>
    <?php
    $armaKpis = [
        ['Temps brut', function_exists('format_arma_playtime_french') ? format_arma_playtime_french((int) ($armaAct['raw'] ?? 0)) : ((int) ($armaAct['raw'] ?? 0) . ' s')],
        ['Temps retenu', function_exists('format_arma_playtime_french') ? format_arma_playtime_french((int) ($armaAct['validated'] ?? 0)) : ((int) ($armaAct['validated'] ?? 0) . ' s')],
        ['Sessions', (string) (int) ($armaAct['sessions'] ?? 0)],
        ['Présences pointées', (string) (int) ($armaAct['presences'] ?? 0)],
        ['Taux de présence', (string) ($armaAct['rate'] ?? 0) . ' %'],
    ];
    ?>
    <div class="<?= $ptReady ? 'mt-8 border-t border-slate-100 pt-6' : 'mt-6' ?>">
        <p class="text-xs font-medium text-slate-500">Activité des 30 derniers jours</p>
        <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-5">
            <?php foreach ($armaKpis as [$aLabel, $aValue]): ?>
            <div>
                <dt class="text-[11px] text-slate-500"><?= htmlspecialchars($aLabel, ENT_QUOTES, 'UTF-8') ?></dt>
                <dd class="mt-1 text-base font-semibold tabular-nums text-slate-900"><?= htmlspecialchars($aValue, ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
    </div>
    <?php endif; ?>

    <?php
    $playWeeks = ($ptReady && is_array($playStats['weeks'] ?? null)) ? $playStats['weeks'] : [];
    $showWeeks = $playWeeks !== [];
    ?>
    <?php if ($showWeeks): ?>
    <?php $weekMax = max(1, ...array_map(static fn (array $w): int => (int) ($w['seconds'] ?? 0), $playWeeks)); ?>
    <div class="mt-8 <?= $showRoleColumn ? 'lg:grid lg:grid-cols-5 lg:gap-8' : '' ?>">
        <div class="<?= $showRoleColumn ? 'lg:col-span-3' : '' ?>">
            <p class="text-xs font-medium text-slate-500">8 dernières semaines</p>
            <div class="mt-3 flex items-end gap-2" role="img" aria-label="Temps de jeu par semaine sur les 8 dernières semaines">
                <?php foreach ($playWeeks as $w): ?>
                <?php $wTs = strtotime((string) $w['start']); $wSec = (int) $w['seconds']; ?>
                <div class="flex min-w-0 flex-1 flex-col items-center gap-1.5" title="<?= htmlspecialchars('Semaine du ' . ($wTs ? date('d/m', $wTs) : '') . ' : ' . ($wSec > 0 ? $roleDur($wSec) : 'pas de partie'), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="flex h-24 w-full items-end rounded-md bg-slate-100">
                        <div class="w-full rounded-md <?= $wSec > 0 ? 'bg-emerald-500' : '' ?>" style="height: <?= $wSec > 0 ? max(8, (int) round(100 * $wSec / $weekMax)) : 0 ?>%"></div>
                    </div>
                    <span class="text-[10px] tabular-nums text-slate-400"><?= $wTs ? date('d/m', $wTs) : '' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($showRoleColumn): ?>
    <div class="mt-8">
    <?php endif; ?>
        <?php if ($showRoleColumn): ?>
        <div class="<?= $showWeeks ? 'mt-8 lg:col-span-2 lg:mt-0' : '' ?>">
            <p class="text-xs font-medium text-slate-500">Par rôle tenu</p>
            <?php if ($atakRoleTimes !== []): ?>
            <ul class="mt-3 space-y-3">
                <?php $roleMax = max(1, (int) ($atakRoleTimes[0]['seconds'] ?? 1)); ?>
                <?php foreach (array_slice($atakRoleTimes, 0, 8) as $rt): ?>
                <?php $det = (string) ($rt['detection'] ?? ''); ?>
                <li>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="min-w-0">
                            <span class="font-semibold text-slate-800"><?= htmlspecialchars((string) $rt['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($det !== ''): ?>
                            <span class="ml-1 inline-block rounded-full px-1.5 py-px text-[10px] font-semibold <?= $roleBadge[$det] ?? $roleBadge['team'] ?>" title="<?= htmlspecialchars(\App\Support\AtakRoleDetection::hint($det), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($rt['detection_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="shrink-0 tabular-nums"><span class="font-semibold text-slate-900"><?= htmlspecialchars($roleDur((int) $rt['seconds']), ENT_QUOTES, 'UTF-8') ?></span> <span class="text-xs text-slate-400"><?= (int) round(100 * (int) $rt['seconds'] / $roleTotal) ?> %</span></span>
                    </div>
                    <div class="mt-1.5 h-1.5 rounded-full bg-slate-100" aria-hidden="true"><div class="h-1.5 rounded-full bg-emerald-500" style="width: <?= max(4, (int) round(100 * (int) $rt['seconds'] / $roleMax)) ?>%"></div></div>
                    <?php if (($rt['last_day'] ?? '') !== '' && ($ts = strtotime((string) $rt['last_day'])) !== false): ?>
                    <p class="mt-0.5 text-xs text-slate-500">Dernière fois le <?= date('d/m/Y', $ts) ?></p>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($atakRoleTimes) > 8): ?>
            <p class="mt-2 text-xs text-slate-500">+ <?= count($atakRoleTimes) - 8 ?> autre(s) rôle(s).</p>
            <?php endif; ?>
            <?php else: ?>
            <p class="mt-2 text-sm text-slate-500">Aucun temps par rôle pour l’instant.</p>
            <?php endif; ?>
            <?php if ($canCorrectRoleTime): ?>
            <?php $personSeg = rawurlencode((string) ($roleTimePathSegment ?? '')); ?>
            <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-sm text-slate-700">
                <summary class="cursor-pointer font-semibold text-slate-800">Corriger le temps par rôle</summary>
                <p class="mt-2 text-xs leading-relaxed text-slate-500">Après une perte de synchro, déplacez le temps vers le bon rôle. Rien n’est perdu, chaque correction est journalisée et peut être annulée.</p>
                <?php if ($atakRoleTimes !== []): ?>
                <form method="post" action="<?= htmlspecialchars(url('personnel/' . $personSeg . '/temps-par-role/deplacer'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3 space-y-2">
                    <?= \App\Core\Csrf::field() ?>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-500">Déplacer le temps de</span>
                        <select name="from_key" required class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm">
                            <?php foreach ($atakRoleTimes as $rt): ?>
                            <option value="<?= htmlspecialchars((string) $rt['key'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $rt['label'] . ' (' . $roleDur((int) $rt['seconds']) . ')', ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-500">Vers le rôle</span>
                        <select name="to_key" required class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm">
                            <?php foreach ($atakRoleTimes as $rt): ?>
                            <option value="<?= htmlspecialchars((string) $rt['key'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $rt['label'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                            <option value="__new">Autre rôle (saisir ci-dessous)…</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-500">Nom du rôle, si autre</span>
                        <input type="text" name="to_label" maxlength="60" placeholder="ex. TACP" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-500">Minutes à déplacer</span>
                        <input type="number" name="minutes" min="1" step="1" placeholder="vide = tout" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-slate-500">Motif</span>
                        <input type="text" name="reason" maxlength="255" placeholder="ex. perte de synchro, rôle tombé" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm">
                    </label>
                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">Déplacer le temps</button>
                </form>
                <?php endif; ?>
                <?php if ($roleTimeMoves !== []): ?>
                <p class="mt-4 text-xs font-medium text-slate-500">Journal des corrections</p>
                <ul class="mt-2 space-y-1.5">
                    <?php foreach ($roleTimeMoves as $mv): ?>
                    <?php $mvTs = strtotime((string) ($mv['created_at'] ?? '')); ?>
                    <li class="rounded-md border border-slate-200 bg-white px-2 py-1.5 text-xs <?= $mv['reverted_at'] !== null ? 'opacity-60' : '' ?>">
                        <p><span class="font-semibold"><?= htmlspecialchars($roleDur((int) $mv['seconds']), ENT_QUOTES, 'UTF-8') ?></span> de <?= htmlspecialchars((string) $mv['from_label'], ENT_QUOTES, 'UTF-8') ?> vers <?= htmlspecialchars((string) $mv['to_label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-[11px] text-slate-500">Par <?= htmlspecialchars((string) ($mv['actor_name'] ?? '') !== '' ? (string) $mv['actor_name'] : 'inconnu', ENT_QUOTES, 'UTF-8') ?><?= $mvTs ? ' le ' . date('d/m/Y \à H:i', $mvTs) : '' ?><?php if (trim((string) ($mv['reason'] ?? '')) !== ''): ?> · <?= htmlspecialchars((string) $mv['reason'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p>
                        <?php if ($mv['reverted_at'] !== null): ?>
                        <?php $rvTs = strtotime((string) $mv['reverted_at']); ?>
                        <p class="text-[11px] text-slate-500">Annulée<?= trim((string) ($mv['reverted_by_name'] ?? '')) !== '' ? ' par ' . htmlspecialchars((string) $mv['reverted_by_name'], ENT_QUOTES, 'UTF-8') : '' ?><?= $rvTs ? ' le ' . date('d/m/Y \à H:i', $rvTs) : '' ?></p>
                        <?php else: ?>
                        <form method="post" action="<?= htmlspecialchars(url('personnel/' . $personSeg . '/temps-par-role/' . (int) $mv['id'] . '/annuler'), ENT_QUOTES, 'UTF-8') ?>" class="mt-1">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="text-xs font-semibold text-sky-700 underline">Annuler cette correction</button>
                        </form>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php if ($showWeeks || $showRoleColumn): ?>
    </div>
    <?php endif; ?>

    <?php if ($ptReady): ?>
    <p class="mt-6 text-xs leading-relaxed text-slate-500">Cumul issu des sessions reliées au portail. Les 7 et 30 jours, les sessions et les semaines viennent du temps compté pendant les parties. Le temps par rôle est compté par le serveur, ou par le téléphone quand le serveur ne remonte pas.</p>
    <?php if (!empty($viewerIsPersonnelSubject)): ?>
    <p class="mt-3 rounded-xl border border-sky-200/80 bg-sky-50/90 px-3 py-2 text-xs leading-relaxed text-slate-700">Pour forcer une remontée depuis le jeu : téléphone Athena, « Remonter le temps », ou Resynch Athena. Rechargez ensuite cette page.</p>
    <?php endif; ?>
    <?php endif; ?>
</section>
