<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $fireTeams */
/** @var string $fireTeamsTab */
/** @var array<int,string> $fireTeamsMaps */
/** @var bool $fireTeamsReady */
/** @var bool $fireTeamsIncludeDissolved */
/** @var array{total:int,active:int} $fireTeamsStats */

$teams = is_array($fireTeams ?? null) ? $fireTeams : [];
$tab = (string) ($fireTeamsTab ?? 'mission');
$maps = is_array($fireTeamsMaps ?? null) ? $fireTeamsMaps : [];
$ready = !empty($fireTeamsReady);
$includeDissolved = !empty($fireTeamsIncludeDissolved);
$stats = is_array($fireTeamsStats ?? null) ? $fireTeamsStats : [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$kindLabel = static function (string $kind): string {
    return $kind === 'permanent' ? 'Organigramme' : 'Mission';
};

$statusLabel = static function (array $t): string {
    if (!empty($t['deleted_at'])) {
        return 'Retirée';
    }
    if (!empty($t['dissolved_at'])) {
        return 'Dissoute';
    }

    return 'Active';
};
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-6">
    <header class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Tactique · ATAK</p>
                <h1 class="mt-2 text-2xl font-black text-slate-900">Équipes de feu</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-2xl">
                    Constituez des équipes pour une mission cartographique, ou rattachez-les durablement à une unité de l’organigramme.
                    Distinct des équipes RH classiques et de l’appui-feu.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?= $h(url('back-office/atak/fire-teams/create?type=mission')) ?>" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Nouvelle (mission)</a>
                <a href="<?= $h(url('back-office/atak/fire-teams/create?type=organigramme')) ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Nouvelle (organigramme)</a>
                <a href="<?= $h(url('back-office/atak/operateurs')) ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Effectifs en liaison</a>
                <a href="<?= $h(url('admin/atak-config')) ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Config ATAK</a>
            </div>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-slate-50 p-3">
                <p class="text-xs uppercase text-slate-500">Liste affichée</p>
                <p class="text-2xl font-black text-slate-900"><?= (int) ($stats['total'] ?? count($teams)) ?></p>
            </div>
            <div class="rounded-xl bg-emerald-50 p-3">
                <p class="text-xs uppercase text-emerald-700">Actives</p>
                <p class="text-2xl font-black text-emerald-900"><?= (int) ($stats['active'] ?? 0) ?></p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3 col-span-2 sm:col-span-1">
                <p class="text-xs uppercase text-slate-500">Raccourcis</p>
                <p class="mt-1 text-sm text-slate-700">
                    <a class="underline font-semibold" href="<?= $h(url('back-office/teams')) ?>">Équipes ORBAT</a>
                    ·
                    <a class="underline font-semibold" href="<?= $h(url('orbat')) ?>">Organigramme</a>
                </p>
            </div>
        </div>
    </header>

    <?php if ($flashSuccess): ?>
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status"><?= $h($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <p class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800" role="alert"><?= $h($flashError) ?></p>
    <?php endif; ?>

    <?php if (!$ready): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            Les tables des équipes de feu ne sont pas encore créées. Un administrateur plateforme doit exécuter les migrations
            (<span class="font-semibold">run-migrations.php</span> ou l’écran de migrations).
        </div>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2" aria-label="Filtres des équipes de feu">
        <?php
        $tabs = [
            'mission' => 'De mission',
            'organigramme' => 'Organigramme',
            'toutes' => 'Toutes',
        ];
        foreach ($tabs as $key => $label):
            $href = url('back-office/atak/fire-teams?vue=' . $key . ($includeDissolved ? '&inclure_dissoutes=1' : ''));
            $active = $tab === $key;
            ?>
            <a href="<?= $h($href) ?>"
               class="rounded-lg px-3 py-2 text-sm font-semibold <?= $active ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' ?>">
                <?= $h($label) ?>
            </a>
        <?php endforeach; ?>
        <a href="<?= $h(url('back-office/atak/fire-teams?vue=' . $tab . ($includeDissolved ? '' : '&inclure_dissoutes=1'))) ?>"
           class="rounded-lg px-3 py-2 text-sm font-semibold border border-slate-300 bg-white text-slate-600 hover:bg-slate-50">
            <?= $includeDissolved ? 'Masquer les dissoutes' : 'Inclure les dissoutes' ?>
        </a>
    </nav>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <?php if ($teams === []): ?>
            <div class="p-6 text-sm text-slate-500">Aucune équipe de feu dans cette vue.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Équipe</th>
                            <th class="px-4 py-3 text-left">Portée</th>
                            <th class="px-4 py-3 text-left">Rattachement</th>
                            <th class="px-4 py-3 text-left">Effectif</th>
                            <th class="px-4 py-3 text-left">État</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($teams as $t):
                            $tid = (int) ($t['id'] ?? 0);
                            $color = (string) ($t['color'] ?? '#2563EB');
                            $kind = (string) ($t['kind'] ?? 'ephemeral');
                            $mapId = isset($t['map_id']) ? (int) $t['map_id'] : 0;
                            $attach = $kind === 'permanent'
                                ? ((string) ($t['unit_name'] ?? '') !== '' ? (string) $t['unit_name'] : 'Sans unité liée')
                                : ($maps[$mapId] ?? ('Carte #' . $mapId));
                            if ($kind === 'ephemeral' && !empty($t['mission_key'])) {
                                $attach .= ' · ' . (string) $t['mission_key'];
                            }
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block h-3 w-3 rounded-full border border-slate-200" style="background:<?= $h($color) ?>" aria-hidden="true"></span>
                                        <span class="font-semibold text-slate-900"><?= $h($t['label'] ?? '') ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700"><?= $h($kindLabel($kind)) ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= $h($attach) ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= (int) ($t['member_count'] ?? 0) ?></td>
                                <td class="px-4 py-3">
                                    <?php $st = $statusLabel($t); ?>
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold
                                        <?= $st === 'Active' ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
                                        <?= $h($st) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100" href="<?= $h(url('back-office/atak/fire-teams/' . $tid)) ?>">Ouvrir</a>
                                        <?php if (!empty($t['is_active'])): ?>
                                            <form method="post" action="<?= $h(url('back-office/atak/fire-teams/' . $tid . '/dissolve')) ?>" onsubmit="return confirm('Dissoudre cette équipe de feu ?');">
                                                <input type="hidden" name="_csrf_token" value="<?= $h(\App\Core\Csrf::token()) ?>">
                                                <button type="submit" class="rounded-md border border-amber-300 px-2.5 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-50">Dissoudre</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
