<?php

declare(strict_types=1);

$actions = $opsCenterActions ?? [];
$roleCounts = $opsCenterRoleCounts ?? ['moderator' => 0, 'support' => 0, 'admin' => 0];
$templates = $opsCenterTemplates ?? [];
$statusDictionary = $opsCenterStatusDictionary ?? [];
$crossLinks = $opsCenterCrossLinks ?? [];

$roleLabels = [
    'moderator' => 'Modérateur',
    'support' => 'Support',
    'admin' => 'Admin système',
];

$statusBadge = [
    'open' => 'bg-rose-100 text-rose-800 border-rose-200',
    'monitor' => 'bg-amber-100 text-amber-900 border-amber-200',
    'done' => 'bg-emerald-100 text-emerald-900 border-emerald-200',
];

$priorityBadge = [
    'high' => 'bg-rose-100 text-rose-800 border-rose-200',
    'medium' => 'bg-amber-100 text-amber-900 border-amber-200',
    'low' => 'bg-slate-100 text-slate-700 border-slate-200',
];
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">
        <header class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-slate-50 p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-indigo-700">Ops Center V1</p>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-black text-slate-900">Administration par rôle</h1>
                    <p class="mt-2 text-sm text-slate-600 max-w-3xl">
                        Vue opérationnelle unifiée pour <strong>modération</strong>, <strong>support</strong> et <strong>admin système</strong> : actions en attente,
                        statuts normalisés, templates incident et liens transverses lookup ↔ audit ↔ modération.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row lg:flex-col gap-2">
                    <a href="<?= url('admin') ?>" class="inline-flex justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Dashboard plateforme</a>
                    <a href="<?= url('admin/audit') ?>" class="inline-flex justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Audit</a>
                </div>
            </div>
        </header>

        <section class="grid grid-cols-1 sm:grid-cols-3 gap-4" aria-label="Compteurs par rôle">
            <?php foreach ($roleLabels as $roleKey => $roleLabel): ?>
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wider text-slate-500"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-2 text-3xl font-black text-slate-900"><?= (int) ($roleCounts[$roleKey] ?? 0) ?></p>
                <p class="text-xs text-slate-500">actions suivies</p>
            </article>
            <?php endforeach; ?>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="ops-actions-heading">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-5">
                <div>
                    <h2 id="ops-actions-heading" class="text-lg font-bold text-slate-900">Mes actions en attente</h2>
                    <p class="text-sm text-slate-600">Filtrer les tâches par rôle et par statut métier unifié.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <label class="text-sm text-slate-700">
                        <span class="sr-only">Filtrer par rôle</span>
                        <select id="ops-role-filter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="all">Tous les rôles</option>
                            <option value="moderator">Modérateur</option>
                            <option value="support">Support</option>
                            <option value="admin">Admin système</option>
                        </select>
                    </label>
                    <label class="text-sm text-slate-700">
                        <span class="sr-only">Filtrer par statut</span>
                        <select id="ops-status-filter" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="all">Tous les statuts</option>
                            <option value="open">Ouvert</option>
                            <option value="monitor">Surveillance</option>
                            <option value="done">Terminé</option>
                        </select>
                    </label>
                </div>
            </div>

            <div id="ops-actions-list" class="space-y-3">
                <?php foreach ($actions as $item): ?>
                    <?php
                        $role = (string) ($item['role'] ?? '');
                        $status = (string) ($item['status'] ?? 'open');
                        $priority = (string) ($item['priority'] ?? 'medium');
                    ?>
                    <article class="rounded-xl border border-slate-200 p-4" data-role="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>" data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="mt-1 text-sm text-slate-600"><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 <?= $statusBadge[$status] ?? $statusBadge['open'] ?>"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 <?= $priorityBadge[$priority] ?? $priorityBadge['medium'] ?>">Priorité <?= htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-indigo-800"><?= htmlspecialchars($roleLabels[$role] ?? $role, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" href="<?= htmlspecialchars((string) ($item['link'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string) ($item['link_label'] ?? 'Ouvrir'), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <p id="ops-actions-empty" class="hidden mt-3 text-sm text-slate-500">Aucune action ne correspond aux filtres sélectionnés.</p>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">Templates support & incident</h2>
                <p class="mt-1 text-sm text-slate-600">Messages standardisés pour accélérer la première réponse et l’escalade.</p>
                <div class="mt-4 space-y-3">
                    <?php foreach ($templates as $template): ?>
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) ($template['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <button type="button" class="ops-template-copy rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100" data-template="<?= htmlspecialchars((string) ($template['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Copier</button>
                        </div>
                        <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars((string) ($template['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">Statuts métier unifiés</h2>
                <p class="mt-1 text-sm text-slate-600">Dictionnaire partagé incident / modération / maintenance.</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <?php foreach ($statusDictionary as $status): ?>
                    <li class="rounded-lg border border-slate-200 p-3">
                        <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($status['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <span class="text-xs text-slate-500">(<?= htmlspecialchars((string) ($status['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</span></p>
                        <p class="text-slate-600"><?= htmlspecialchars((string) ($status['usage'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                    <p class="font-semibold text-slate-800 mb-1">Liens transverses</p>
                    <ul class="space-y-1 text-slate-600">
                        <li><a class="underline" href="<?= htmlspecialchars((string) ($crossLinks['user_lookup'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">Lookup utilisateur (API)</a></li>
                        <li><a class="underline" href="<?= htmlspecialchars((string) ($crossLinks['audit'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">Journal d’audit</a></li>
                        <li><a class="underline" href="<?= htmlspecialchars((string) ($crossLinks['moderation'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>">Console modération</a></li>
                    </ul>
                </div>
            </article>
        </section>
    </div>
</div>

<script id="ops-center-data" type="application/json"><?= json_encode(['actions' => $actions], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= asset('assets/js/admin-ops-center.js') ?>" defer></script>
