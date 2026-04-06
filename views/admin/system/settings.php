<?php
declare(strict_types=1);

/** @var list<array{title: string, description?: string, rows: list<array{label: string, value: string, note?: string}>}> $adminSettingsSections */
/** @var list<array{label: string, value: string, note?: string}> $adminSettingsPlatformRows */

$sections = $adminSettingsSections ?? [];
$platformRows = $adminSettingsPlatformRows ?? [];
$envLabel = $adminSettingsEnvLabel ?? '—';
$envRaw = (string) ($adminSettingsEnvRaw ?? '');
?>
<div class="bg-slate-50 min-h-[calc(100vh-3.5rem)]">
    <div class="max-w-[960px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">

        <header class="relative overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-amber-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-8 py-7 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
                <div class="min-w-0">
                    <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-900/80 mb-2">
                        <span class="h-px w-6 bg-amber-400" aria-hidden="true"></span>
                        Administration plateforme
                    </p>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Paramètres système</h1>
                    <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">
                        Synthèse <strong class="font-semibold text-slate-800">lecture seule</strong> de la configuration effective au démarrage du site.
                        Les mots de passe, clés secrètes et jetons ne sont jamais affichés.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="<?= url('admin') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
                            Tableau de bord admin
                        </a>
                        <a href="<?= url('admin/maintenance') ?>" class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-4 py-2 text-sm font-semibold text-amber-950 shadow-sm hover:bg-amber-50/80 transition-colors">
                            Maintenance planifiée
                        </a>
                        <a href="<?= url('admin/system/brief') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
                            Paramètres du brief
                        </a>
                    </div>
                </div>
                <div class="shrink-0 w-full sm:w-56 rounded-xl border border-slate-200/80 bg-white/90 backdrop-blur-sm p-4 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Environnement</p>
                    <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($envLabel, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($envRaw !== ''): ?>
                        <p class="mt-1 text-[11px] font-mono text-slate-500 break-all"><?= htmlspecialchars($envRaw, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="space-y-6">
            <?php foreach ($sections as $sec): ?>
                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
                        <h2 class="text-sm font-black text-slate-900 tracking-tight"><?= htmlspecialchars($sec['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if (!empty($sec['description'])): ?>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed"><?= htmlspecialchars((string) $sec['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                    <dl class="divide-y divide-slate-100">
                        <?php foreach ($sec['rows'] as $row): ?>
                            <div class="px-5 py-3.5 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 sm:gap-6">
                                <dt class="text-sm font-medium text-slate-700 shrink-0 sm:max-w-[45%]"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd class="min-w-0 text-sm text-slate-900 sm:text-right">
                                    <span class="font-mono text-[13px] break-all"><?= htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($row['note'])): ?>
                                        <p class="mt-1 text-xs text-slate-500 font-sans text-left sm:text-right leading-snug"><?= htmlspecialchars((string) $row['note'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>
            <?php endforeach; ?>

            <?php if ($platformRows !== []): ?>
                <section class="rounded-2xl border border-emerald-200/90 bg-white shadow-sm overflow-hidden" aria-labelledby="platform-settings-heading">
                    <div class="px-5 py-4 border-b border-emerald-100 bg-emerald-50/50">
                        <h2 id="platform-settings-heading" class="text-sm font-black text-emerald-950 tracking-tight">Réglages stockés en base (plateforme)</h2>
                        <p class="mt-1 text-xs text-emerald-900/80 leading-relaxed">
                            Valeurs enregistrées dans la table des paramètres globaux. Certains champs se modifient aussi depuis l’écran « Paramètres du brief ».
                        </p>
                    </div>
                    <dl class="divide-y divide-slate-100">
                        <?php foreach ($platformRows as $row): ?>
                            <div class="px-5 py-3.5 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 sm:gap-6">
                                <dt class="text-sm font-medium text-slate-700 shrink-0 sm:max-w-[45%]"><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd class="min-w-0 text-sm text-slate-900 sm:text-right">
                                    <span class="font-mono text-[13px] break-all whitespace-pre-wrap"><?= htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($row['note'])): ?>
                                        <p class="mt-1 text-xs text-slate-500 font-sans text-left sm:text-right"><?= htmlspecialchars((string) $row['note'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>
            <?php endif; ?>
        </div>

        <p class="text-xs text-slate-500 leading-relaxed px-1">
            En production, l’équipe technique peut brancher un service externe de suivi des erreurs sur le point d’entrée de l’application — ce n’est pas activé par défaut dans ce dépôt.
        </p>
    </div>
</div>
