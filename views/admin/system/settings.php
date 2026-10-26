<?php
declare(strict_types=1);

/** @var list<array{title: string, description?: string, rows: list<array{label: string, value: string, note?: string}>}> $adminSettingsSections */
/** @var list<array{label: string, value: string, note?: string}> $adminSettingsPlatformRows */
/** @var array{link: string, atak: string, link_raw: string, atak_raw: string, active: bool, message: string}|null $adminGameClientGate */

$sections = $adminSettingsSections ?? [];
$platformRows = $adminSettingsPlatformRows ?? [];
$envLabel = $adminSettingsEnvLabel ?? '—';
$envRaw = (string) ($adminSettingsEnvRaw ?? '');
$gate = is_array($adminGameClientGate ?? null) ? $adminGameClientGate : null;
$gateCsrf = $gate !== null ? \App\Core\Csrf::token() : '';
$gateAction = url('admin/settings/version-dll');
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="max-w-[960px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">

        <header class="pa-head relative overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-amber-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-8 py-7 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
                <div class="min-w-0">
                    <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-900/80 mb-2">
                        <span class="h-px w-6 bg-amber-400" aria-hidden="true"></span>
                        Administration plateforme
                    </p>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Paramètres système</h1>
                    <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">
                        Synthèse <strong class="font-semibold text-slate-800">lecture seule</strong> de la configuration effective au démarrage du site,
                        sauf la version minimale des DLL du jeu, modifiable ci-dessous.
                        Les mots de passe, clés secrètes et jetons ne sont jamais affichés.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="<?= url('admin') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
                            Tableau de bord admin
                        </a>
                        <a href="<?= url('admin/maintenance') ?>" class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-4 py-2 text-sm font-semibold text-amber-950 shadow-sm hover:bg-amber-50/80 transition-colors">
                            Maintenance planifiée
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
            <?php if ($gate !== null): ?>
                <section id="version-dll" class="rounded-2xl border border-amber-200/80 bg-white shadow-sm overflow-hidden" aria-labelledby="version-dll-heading">
                    <div class="px-5 py-4 border-b border-slate-100 bg-amber-50/90">
                        <h2 id="version-dll-heading" class="text-sm font-black text-slate-900 tracking-tight">Version minimale des DLL du jeu</h2>
                        <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                            Les DLL COMSPEC Link et ATAK natif plus anciennes que ce numéro sont refusées par Athena (sur les deux domaines),
                            avec un message qui demande la mise à jour depuis le Workshop Steam. Vide : aucune DLL n’est refusée.
                            À régler seulement une fois la version corrigée publiée sur Steam.
                        </p>
                    </div>
                    <div class="px-5 py-4 space-y-4">
                        <p class="text-sm text-slate-700">
                            État :
                            <?php if ($gate['active']): ?>
                                <strong class="font-semibold text-slate-900">blocage actif</strong>, DLL antérieures à
                                <span class="font-mono text-[13px]"><?= htmlspecialchars($gate['link'], ENT_QUOTES, 'UTF-8') ?></span> refusées.
                            <?php else: ?>
                                <strong class="font-semibold text-slate-900">aucun blocage</strong>, toutes les versions sont acceptées.
                            <?php endif; ?>
                        </p>
                        <?php if ($gate['active'] && $gate['message'] !== ''): ?>
                            <p class="text-xs text-slate-500 leading-relaxed">Message envoyé aux joueurs : <span class="font-mono text-[13px] break-all"><?= htmlspecialchars($gate['message'], ENT_QUOTES, 'UTF-8') ?></span></p>
                        <?php endif; ?>
                        <?php if ($gate['link_raw'] !== '' && $gate['link'] === ''): ?>
                            <p class="text-xs text-slate-500">Valeur enregistrée illisible (« <?= htmlspecialchars($gate['link_raw'], ENT_QUOTES, 'UTF-8') ?> ») : ignorée, aucune DLL n’est refusée.</p>
                        <?php endif; ?>
                        <form method="post" action="<?= htmlspecialchars($gateAction, ENT_QUOTES, 'UTF-8') ?>" class="grid gap-4 sm:grid-cols-2">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($gateCsrf, ENT_QUOTES, 'UTF-8') ?>">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="min-link-version">Version minimale de la DLL</label>
                                <input id="min-link-version" name="min_link_version" type="text" autocomplete="off" placeholder="2.0.96" value="<?= htmlspecialchars($gate['link'], ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono">
                                <p class="mt-1 text-xs text-slate-500">Numéro COMSPEC Link (la DLL native doit annoncer le même). Vide : aucun blocage.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1" for="min-atak-version">Version COMSPEC ATAK citée</label>
                                <input id="min-atak-version" name="min_atak_version" type="text" autocomplete="off" placeholder="2.4.1" value="<?= htmlspecialchars($gate['atak'], ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono">
                                <p class="mt-1 text-xs text-slate-500">Facultatif : numéro du Workshop repris dans le message (« 2.4.1 or later »).</p>
                            </div>
                            <div class="sm:col-span-2">
                                <button type="submit" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                        <?php if ($gate['active']): ?>
                            <form method="post" action="<?= htmlspecialchars($gateAction, ENT_QUOTES, 'UTF-8') ?>" class="border-t border-slate-100 pt-4">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($gateCsrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="clear" value="1">
                                <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
                                    Désactiver le blocage (accepter toutes les versions)
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

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
