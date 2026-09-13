<?php
declare(strict_types=1);

/** @var array<string, mixed> $summary */
$summary = is_array($summary ?? null) ? $summary : [];
$totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
$anomalies = is_array($summary['anomalies'] ?? null) ? $summary['anomalies'] : [];
$movements = is_array($summary['movements'] ?? null) ? $summary['movements'] : [];
$trash = is_array($summary['trash'] ?? null) ? $summary['trash'] : [];
$snapshots = is_array($summary['snapshots'] ?? null) ? $summary['snapshots'] : [];
$domainModel = is_array($summary['domain_model'] ?? null) ? $summary['domain_model'] : [];
$schemaReady = !empty($schemaReady);
$csrfToken = (string) ($csrfToken ?? '');
?>
<div class="mx-auto max-w-6xl space-y-8 px-4 py-8">
    <header class="space-y-2">
        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-slate-400">Administration ORBAT</p>
        <h1 class="text-3xl font-black tracking-tight text-slate-900">Qualité des données</h1>
        <p class="max-w-3xl text-sm leading-relaxed text-slate-600">
            Synthèse factuelle des incohérences organisationnelles. Athena signale des faits
            (poste vacant, double titulaire, qualification manquante) — sans inventer un jugement
            du type « unité non opérationnelle ».
        </p>
    </header>

    <?php if (!$schemaReady): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            Le schéma postes ORBAT n’est pas encore disponible. Exécutez les migrations pour activer ce centre.
        </div>
    <?php endif; ?>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <?php
        $cards = [
            ['Anomalies', (int) ($totals['anomalies'] ?? 0), 'slate'],
            ['Sévérité haute', (int) ($totals['high'] ?? 0), 'rose'],
            ['Sévérité moyenne', (int) ($totals['medium'] ?? 0), 'amber'],
            ['Postes vacants signalés', (int) ($totals['vacant_billet'] ?? 0), 'sky'],
        ];
        foreach ($cards as [$label, $value, $tone]):
            $toneCls = match ($tone) {
                'rose' => 'border-rose-200 bg-rose-50 text-rose-950',
                'amber' => 'border-amber-200 bg-amber-50 text-amber-950',
                'sky' => 'border-sky-200 bg-sky-50 text-sky-950',
                default => 'border-slate-200 bg-slate-50 text-slate-900',
            };
        ?>
            <div class="rounded-2xl border px-4 py-4 <?= $toneCls ?>">
                <p class="text-[10px] font-black uppercase tracking-wider opacity-70"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="mt-2 text-3xl font-black"><?= (int) $value ?></p>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-black text-slate-900">Modèle métier (6 objets)</h2>
        <p class="mt-1 text-xs text-slate-500">PERSONNEL ↔ AFFECTATION ↔ POSTE ↔ STRUCTURE + QUALIFICATION + HISTORIQUE</p>
        <div class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($domainModel as $obj): ?>
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-3">
                    <p class="text-[11px] font-black uppercase tracking-wide text-slate-800"><?= htmlspecialchars((string) ($obj['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mt-1 text-[11px] leading-snug text-slate-600"><?= htmlspecialchars((string) ($obj['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-black text-slate-900">Anomalies</h2>
                <p class="text-xs text-slate-500">Détection automatique — à traiter côté administration.</p>
            </div>
            <a href="<?= htmlspecialchars(url('orbat'), ENT_QUOTES, 'UTF-8') ?>" class="text-xs font-bold uppercase tracking-wide text-emerald-800 hover:underline">Ouvrir l’ORBAT</a>
        </div>
        <div class="mt-4 max-h-96 space-y-2 overflow-y-auto">
            <?php if ($anomalies === []): ?>
                <p class="text-sm text-slate-500">Aucune anomalie détectée pour le moment.</p>
            <?php else: ?>
                <?php foreach ($anomalies as $a):
                    $sev = (string) ($a['severity'] ?? 'medium');
                    $badge = match ($sev) {
                        'high' => 'bg-rose-100 text-rose-900',
                        'low' => 'bg-slate-100 text-slate-700',
                        default => 'bg-amber-100 text-amber-950',
                    };
                ?>
                    <div class="flex items-start justify-between gap-3 rounded-xl border border-slate-100 px-3 py-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($a['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><?= htmlspecialchars((string) ($a['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black uppercase <?= $badge ?>"><?= htmlspecialchars($sev, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-black text-slate-900">Tableau des mouvements</h2>
            <p class="text-xs text-slate-500">Journal de carrière / affectations récentes.</p>
            <div class="mt-4 max-h-80 space-y-2 overflow-y-auto">
                <?php if ($movements === []): ?>
                    <p class="text-sm text-slate-500">Aucun mouvement enregistré.</p>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <div class="rounded-xl border border-slate-100 px-3 py-2">
                            <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($m['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-[10px] text-slate-500">
                                <?= htmlspecialchars((string) ($m['event_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                · user #<?= (int) ($m['user_id'] ?? 0) ?>
                                · <?= htmlspecialchars((string) ($m['effective_at'] ?? $m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($m['movement_reason'])): ?>
                                    · motif <?= htmlspecialchars((string) $m['movement_reason'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-black text-slate-900">Corbeille des postes</h2>
            <p class="text-xs text-slate-500">Soft-delete — restauration sans perte d’historique.</p>
            <div class="mt-4 max-h-80 space-y-2 overflow-y-auto">
                <?php if ($trash === []): ?>
                    <p class="text-sm text-slate-500">Aucun poste archivé.</p>
                <?php else: ?>
                    <?php foreach ($trash as $b): ?>
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-3 py-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($b['title'] ?? $b['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-[10px] text-slate-500"><?= htmlspecialchars((string) ($b['unit_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <form method="post" action="<?= htmlspecialchars(url('back-office/organisation/qualite-donnees/postes/' . (int) ($b['id'] ?? 0) . '/restaurer'), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="rounded-lg border border-emerald-300 bg-emerald-50 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-900 hover:bg-emerald-100">Restaurer</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-black text-slate-900">Versionnement ORBAT</h2>
        <p class="text-xs text-slate-500">Enregistrez l’état courant avant une réorganisation assistée.</p>
        <form method="post" action="<?= htmlspecialchars(url('back-office/organisation/qualite-donnees/snapshot'), ENT_QUOTES, 'UTF-8') ?>" class="mt-4 grid gap-3 md:grid-cols-4">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="md:col-span-2">
                <label class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Libellé</label>
                <input name="label" type="text" maxlength="200" placeholder="Avant réorg Alpha" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Type</label>
                <select name="kind" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="manual">Manuel</option>
                    <option value="pre_reorg">Pré-réorganisation</option>
                    <option value="scheduled">Programmé</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Date d’effet</label>
                <input name="effective_at" type="datetime-local" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-3">
                <label class="mb-1 block text-[9px] font-black uppercase tracking-wider text-slate-500">Notes</label>
                <input name="notes" type="text" maxlength="500" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-xl bg-slate-900 px-3 py-2 text-xs font-black uppercase tracking-wide text-white hover:bg-slate-800">Créer un snapshot</button>
            </div>
        </form>
        <div class="mt-4 max-h-56 space-y-2 overflow-y-auto">
            <?php if ($snapshots === []): ?>
                <p class="text-sm text-slate-500">Aucun snapshot pour l’instant.</p>
            <?php else: ?>
                <?php foreach ($snapshots as $s): ?>
                    <div class="rounded-xl border border-slate-100 px-3 py-2">
                        <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($s['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-[10px] text-slate-500">
                            <?= htmlspecialchars((string) ($s['snapshot_kind'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            · effet <?= htmlspecialchars((string) ($s['effective_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            · créé <?= htmlspecialchars((string) ($s['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
