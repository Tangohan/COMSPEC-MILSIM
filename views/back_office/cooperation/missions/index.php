<?php
declare(strict_types=1);

/** @var list<array{mission: array<string, mixed>, progress: array<string, mixed>, units: list<string>, action_required: bool, filter: string}> $cooperationRows */
$rows = is_array($cooperationRows ?? null) ? $cooperationRows : [];
$kpis = $cooperationKpis ?? [];
$actions = $cooperationActionsRequired ?? [];
$canManage = function_exists('can') && (can('interteam.missions.manage') || can('cooperation.missions.manage') || can('cooperation.missions.create'));
$gate = \App\Core\Gate::getInstance();
$canManage = $canManage || $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('admin.system');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$filters = [
    'all' => 'Toutes',
    'todo' => 'Action requise',
    'draft' => 'Brouillons',
    'pending' => 'Propositions',
    'active' => 'En cours',
    'closed' => 'Clôturées',
    'cancelled' => 'Annulées',
];
$counts = array_fill_keys(array_keys($filters), 0);
foreach ($rows as $r) {
    $counts['all']++;
    $counts[$r['filter']] = ($counts[$r['filter']] ?? 0) + 1;
    if ($r['action_required']) {
        $counts['todo']++;
    }
}
?>
<div class="max-w-5xl mx-auto px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Coopérations inter-unités</h1>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">Propositions conjointes, validation mutuelle, espace d’échange sur le brief et coordination opérationnelle.</p>
        </div>
        <?php if ($canManage): ?>
        <a href="<?= $h(cooperation_mission_create_url()) ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Nouvelle coopération</a>
        <?php endif; ?>
    </div>

    <?php if ($actions !== []): ?>
    <section class="mb-8 rounded-xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm" aria-labelledby="coop-actions-title">
        <h2 id="coop-actions-title" class="text-sm font-black uppercase tracking-wider text-amber-950">Actions attendues de votre part</h2>
        <ul class="mt-3 space-y-2 text-sm text-amber-950">
            <?php foreach ($actions as $a): ?>
            <li class="flex flex-wrap items-baseline justify-between gap-2">
                <span><?= $h((string) ($a['reason'] ?? '')) ?> — <strong><?= $h((string) ($a['title'] ?? '')) ?></strong></span>
                <a href="<?= $h(cooperation_mission_show_url((int) ($a['mission_id'] ?? 0))) ?>" class="text-xs font-semibold text-amber-900 underline shrink-0">Ouvrir</a>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($rows === []): ?>
    <?php
    $ui_empty_title = 'Aucune coopération pour l’instant';
    $ui_empty_description = 'Une coopération réunit plusieurs unités autour d’un exercice, d’une formation ou d’un appui. Vous verrez ici celles que vous pilotez et celles auxquelles vous êtes invités.';
    $ui_empty_primary_label = $canManage ? 'Proposer une coopération' : '';
    $ui_empty_primary_href = $canManage ? cooperation_mission_create_url() : '';
    require base_path('views/partials/ui/empty_state.php');
    ?>
    <?php else: ?>
    <div class="coop-list" data-coop-list>
        <div class="coop-list__tools">
            <div class="coop-list__filters" role="group" aria-label="Filtrer par état">
                <?php foreach ($filters as $fk => $flabel): ?>
                <?php if ($fk !== 'all' && ($counts[$fk] ?? 0) === 0) { continue; } ?>
                <button type="button" class="coop-tag<?= $fk === 'all' ? ' is-active' : '' ?><?= $fk === 'todo' ? ' coop-tag--todo' : '' ?>" data-coop-filter="<?= $h($fk) ?>" aria-pressed="<?= $fk === 'all' ? 'true' : 'false' ?>">
                    <?= $h($flabel) ?> <span class="coop-tag__n"><?= (int) ($counts[$fk] ?? 0) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <label class="coop-list__search">
                <span class="sr-only">Rechercher une coopération par titre ou par unité</span>
                <input type="search" placeholder="Rechercher un titre, une unité…" autocomplete="off" data-coop-search>
            </label>
        </div>
        <p class="sr-only" aria-live="polite" data-coop-count></p>

        <ul class="coop-list__items">
            <?php foreach ($rows as $r):
                $m = $r['mission'];
                $mid = (int) ($m['id'] ?? 0);
                $prog = $r['progress'];
                $dl = trim((string) ($m['proposal_deadline_at'] ?? ''));
                $dlTs = $dl !== '' ? strtotime($dl) : false;
                $dlPassed = $dlTs !== false && $dlTs < time() && (string) ($m['status'] ?? '') === 'pending';
                $hay = mb_strtolower((string) ($m['title'] ?? '') . ' ' . implode(' ', $r['units']));
                ?>
            <li class="coop-row" data-filter="<?= $h($r['filter']) ?>" data-todo="<?= $r['action_required'] ? '1' : '0' ?>" data-search="<?= $h($hay) ?>">
                <a class="coop-row__link" href="<?= $h(cooperation_mission_show_url($mid)) ?>">
                    <span class="coop-row__main">
                        <span class="coop-row__title"><?= $h((string) ($m['title'] ?? '')) ?></span>
                        <span class="coop-row__meta">
                            <?php $ui_badge_label = (string) $prog['state']['label']; $ui_badge_variant = (string) $prog['state']['variant']; require base_path('views/partials/ui/badge.php'); ?>
                            <span class="coop-row__step" title="<?= $h((string) $prog['heading']) ?>">
                                <span class="coop-row__dots" aria-hidden="true"><?php for ($i = 1; $i <= 5; $i++): ?><span class="<?= $i < (int) $prog['current'] || ($i === 5 && !empty($prog['steps'][4]['done'])) ? 'is-done' : ($i === (int) $prog['current'] ? 'is-active' : '') ?>"></span><?php endfor; ?></span>
                                <span class="sr-only">Étape </span><?= $h((string) $prog['short']) ?> · <?= $h((string) $prog['current_label']) ?>
                            </span>
                        </span>
                        <span class="coop-row__units">
                            <?= $r['units'] !== [] ? 'Avec ' . $h(implode(', ', array_slice($r['units'], 0, 4))) . (count($r['units']) > 4 ? ' et ' . (count($r['units']) - 4) . ' autre(s)' : '') : 'Aucune autre unité pour l’instant' ?>
                        </span>
                    </span>
                    <span class="coop-row__side">
                        <?php if ($r['action_required']): ?>
                        <span class="coop-row__todo"><span aria-hidden="true">●</span> Action requise<span class="sr-only"> : <?= $h((string) ($prog['next_action']['label'] ?? '')) ?></span></span>
                        <?php endif; ?>
                        <?php if ($dlTs !== false): ?>
                        <span class="coop-row__deadline<?= $dlPassed ? ' is-late' : '' ?>">Réponse <?= $dlPassed ? 'attendue depuis le' : 'avant le' ?> <?= $h(date('d/m/Y', $dlTs)) ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="coop-list__empty" hidden data-coop-empty>Aucune coopération ne correspond à ces critères.</p>
    </div>
    <script defer src="<?= $h(asset_url('assets/js/cooperation/list.js')) ?>"></script>
    <?php endif; ?>

    <?php if ($kpis !== []): ?>
    <p class="mt-8 text-xs text-slate-500">Au total : <?= (int) ($kpis['active'] ?? 0) ?> en cours · <?= (int) ($kpis['pending'] ?? 0) ?> proposition(s) · <?= (int) ($kpis['draft'] ?? 0) ?> brouillon(s) · <?= (int) ($kpis['archived'] ?? 0) ?> clôturée(s) ou annulée(s).</p>
    <?php endif; ?>
</div>
