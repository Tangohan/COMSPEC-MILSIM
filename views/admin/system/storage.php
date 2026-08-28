<?php
declare(strict_types=1);

use App\Services\Platform\PlatformStorageService;

/** @var array{path: string, total: ?int, free: ?int, used: ?int, percent_used: ?float} $disk */
/** @var list<array{path: string, label: string, purgeable: bool, bytes: ?int, exists: bool}> $directories */
/** @var list<array{name: string, rows: int, bytes: int, engine: string}> $largestTables */
/** @var list<array<string, mixed>> $purgeGroups */
/** @var string $confirmWord */

$disk = is_array($disk ?? null) ? $disk : [];
$directories = is_array($directories ?? null) ? $directories : [];
$largestTables = is_array($largestTables ?? null) ? $largestTables : [];
$purgeGroups = is_array($purgeGroups ?? null) ? $purgeGroups : [];
$confirmWord = (string) ($confirmWord ?? 'VIDER');
$fmt = static fn (?int $b): string => PlatformStorageService::formatBytes($b);
$pct = $disk['percent_used'] ?? null;
$bar = is_numeric($pct) ? max(0, min(100, (float) $pct)) : 0;
$barTone = $bar >= 90 ? 'bg-rose-600' : ($bar >= 75 ? 'bg-amber-500' : 'bg-emerald-600');
$flashErr = \App\Core\Session::getFlash('error');
$flashOk = \App\Core\Session::getFlash('success');
$csrf = \App\Core\Csrf::token();
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 space-y-10">
    <header>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-800 mb-1">Administration du site — pas une communauté</p>
        <h1 class="text-2xl font-black text-slate-900">Espace disque et historiques</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-3xl">
            Occupation du serveur et vidage des historiques qui gonflent avec le temps (positions, relief, photos).
            Les comptes, les communautés et les documents officiels ne peuvent pas être vidés ici.
            Un vidage s’applique à <strong class="font-semibold text-slate-800">toutes</strong> les communautés.
        </p>
        <a href="<?= url('admin') ?>" class="inline-block mt-4 text-sm text-slate-600 hover:underline">Retour au centre opérateur site</a>
    </header>

    <?php if ($flashErr): ?>
        <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($flashOk): ?>
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Disque du serveur</h2>
        <?php if (($disk['total'] ?? null) === null): ?>
            <p class="text-sm text-slate-600">L’occupation du disque n’a pas pu être lue sur cette machine.</p>
        <?php else: ?>
            <p class="text-sm text-slate-700">
                Occupé <?= htmlspecialchars($fmt(isset($disk['used']) ? (int) $disk['used'] : null), ENT_QUOTES, 'UTF-8') ?>
                sur <?= htmlspecialchars($fmt(isset($disk['total']) ? (int) $disk['total'] : null), ENT_QUOTES, 'UTF-8') ?>
                — libre <?= htmlspecialchars($fmt(isset($disk['free']) ? (int) $disk['free'] : null), ENT_QUOTES, 'UTF-8') ?>
                <?php if (is_numeric($pct)): ?>
                    (<?= htmlspecialchars((string) $pct, ENT_QUOTES, 'UTF-8') ?> %)
                <?php endif; ?>
            </p>
            <div class="h-3 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($bar) ?>">
                <div class="h-full <?= htmlspecialchars($barTone, ENT_QUOTES, 'UTF-8') ?>" style="width: <?= htmlspecialchars((string) $bar, ENT_QUOTES, 'UTF-8') ?>%"></div>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500 mb-4">Dossiers sur le disque</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-2 pr-4">Emplacement</th>
                        <th class="py-2 pr-4">Taille</th>
                        <th class="py-2">Vidable ici</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($directories as $dir): ?>
                        <tr>
                            <td class="py-2.5 pr-4">
                                <p class="font-semibold text-slate-800"><?= htmlspecialchars((string) $dir['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (empty($dir['exists'])): ?>
                                    <p class="text-xs text-slate-400">Absent pour le moment</p>
                                <?php endif; ?>
                            </td>
                            <td class="py-2.5 pr-4 tabular-nums text-slate-700"><?= htmlspecialchars($fmt(isset($dir['bytes']) ? (int) $dir['bytes'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2.5 text-slate-600"><?= !empty($dir['purgeable']) ? 'Oui' : 'Non (conservé)' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500 mb-2">Plus volumineux en base</h2>
        <p class="text-sm text-slate-600 mb-4">Estimation. Les comptes et les communautés n’apparaissent ici qu’à titre d’information : ils ne sont pas vidables.</p>
        <?php if ($largestTables === []): ?>
            <p class="text-sm text-slate-500">Aucune mesure disponible.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <th class="py-2 pr-4">Jeu de données</th>
                            <th class="py-2 pr-4">Lignes (approx.)</th>
                            <th class="py-2">Taille</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($largestTables as $t): ?>
                            <?php
                            $name = (string) ($t['name'] ?? '');
                            $label = match ($name) {
                                'atak_unit_motion_samples' => 'Échantillons de déplacement',
                                'atak_unit_motion' => 'Mouvements',
                                'atak_terrain_chunks' => 'Morceaux de relief',
                                'atak_terrain_grids' => 'Grilles de relief',
                                'atak_chat_messages' => 'Messagerie du poste',
                                'atak_units' => 'Unités en liaison',
                                'recon_images' => 'Photos de reconnaissance',
                                'sse_intel_events' => 'Transmissions de renseignement',
                                'audit_logs' => 'Journal d’audit (non vidable ici)',
                                'users' => 'Comptes (non vidable ici)',
                                'tenants' => 'Communautés (non vidable ici)',
                                default => 'Données ' . $name,
                            };
                            ?>
                            <tr>
                                <td class="py-2.5 pr-4 font-medium text-slate-800"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="py-2.5 pr-4 tabular-nums text-slate-600"><?= number_format((int) ($t['rows'] ?? 0), 0, ',', ' ') ?></td>
                                <td class="py-2.5 tabular-nums text-slate-700"><?= htmlspecialchars($fmt((int) ($t['bytes'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="space-y-4">
        <div>
            <h2 class="text-sm font-black uppercase tracking-[0.18em] text-slate-500">Vider un lot</h2>
            <p class="mt-1 text-sm text-slate-600 max-w-3xl">
                Irréversible. Pour confirmer, cochez la case et tapez
                <span class="font-mono font-bold text-slate-900"><?= htmlspecialchars($confirmWord, ENT_QUOTES, 'UTF-8') ?></span>.
            </p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <?php foreach ($purgeGroups as $group): ?>
                <?php
                $key = (string) ($group['key'] ?? '');
                $title = (string) ($group['title'] ?? '');
                $blurb = (string) ($group['blurb'] ?? '');
                $severity = (string) ($group['severity'] ?? 'high');
                $bytes = (int) ($group['bytes'] ?? 0);
                $border = $severity === 'critical' ? 'border-rose-200' : 'border-slate-200';
                $badge = $severity === 'critical' ? 'Critique' : 'Volumineux';
                $badgeCls = $severity === 'critical' ? 'bg-rose-100 text-rose-900' : 'bg-amber-100 text-amber-950';
                ?>
                <article class="rounded-2xl border <?= htmlspecialchars($border, ENT_QUOTES, 'UTF-8') ?> bg-white p-5 shadow-sm space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-base font-black text-slate-900"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
                        <span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider <?= htmlspecialchars($badgeCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($blurb, ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-xs font-semibold tabular-nums text-slate-500">Environ <?= htmlspecialchars($fmt($bytes), ENT_QUOTES, 'UTF-8') ?></p>
                    <form method="post" action="<?= htmlspecialchars(url('admin/system/storage/purge'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-3 border-t border-slate-100 pt-3" onsubmit="return confirm('Confirmer le vidage de ce lot pour toutes les communautés ?');">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="group_key" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="acknowledge" value="1" class="mt-1 rounded border-slate-300" required>
                            <span>Je comprends que toutes les communautés sont concernées et que l’action est irréversible.</span>
                        </label>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="confirm-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">Tapez <?= htmlspecialchars($confirmWord, ENT_QUOTES, 'UTF-8') ?></label>
                            <input id="confirm-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" name="confirm_word" type="text" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                        </div>
                        <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Vider ce lot
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
