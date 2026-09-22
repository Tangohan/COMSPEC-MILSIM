<?php
declare(strict_types=1);

/** @var array $config Configuration roleplay actuelle */
/** @var array $tenant Informations de la communauté */
/** @var list<array{center_x: float, center_y: float, radius: float, effect: string}> $zoneRows */
/** @var array<string, string> $zoneEffectOptions */
/** @var array|null $serverTests Résultats des tests serveur */

$config = $config ?? [];
$tenant = $tenant ?? [];
$zoneRows = is_array($zoneRows ?? null) ? $zoneRows : [];
$zoneEffectOptions = is_array($zoneEffectOptions ?? null) ? $zoneEffectOptions : [];
$serverTests = is_array($serverTests ?? null) ? $serverTests : null;
$csrfToken = (string) ($csrfToken ?? '');
$roleplayFormAction = (string) ($roleplayFormAction ?? url('back-office/atak/roleplay'));
$roleplayResetUrl = (string) ($roleplayResetUrl ?? url('back-office/atak/roleplay/reset'));
$atakHubUrl = (string) ($atakHubUrl ?? url('back-office/atak'));
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Calcul statistiques globales
$totalSettings = 0;
$activeSettings = 0;
if ($config['network_enabled'] ?? false) { $totalSettings++; $activeSettings++; }
if ($config['link_via_relays'] ?? false) { $totalSettings++; $activeSettings++; }
if ($config['sensor_enabled'] ?? false) { $totalSettings++; $activeSettings++; }
if ($config['zones_enabled'] ?? false) { $totalSettings++; $activeSettings++; }
if ($config['intel_scramble_enabled'] ?? false) { $totalSettings++; $activeSettings++; }
$totalSettings = max($totalSettings, 5);
?>
<div class="min-h-0 flex-1 bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">

        <!-- Header avec stats -->
        <header class="relative overflow-hidden rounded-2xl border border-blue-200/80 bg-gradient-to-br from-blue-50/90 via-white to-slate-50 shadow-lg mb-6">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-6 sm:px-8 py-6">
                <div class="flex items-start justify-between gap-6 mb-4">
                    <div>
                        <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-900/80 mb-2">
                            <span class="h-px w-6 bg-blue-400" aria-hidden="true"></span>
                            COMSPEC ATAK — Back-office
                        </p>
                        <h1 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">Mode Roleplay</h1>
                        <p class="mt-2 text-sm text-slate-600 max-w-3xl leading-relaxed">
                            Centre de contrôle centralisé : configuration, tests serveur, visualisation des zones et monitoring en temps réel.
                        </p>
                    </div>
                    
                    <!-- Stats compactes -->
                    <div class="hidden lg:flex flex-col gap-2">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 px-4 py-2 text-center">
                            <div class="text-2xl font-black text-emerald-900"><?= $activeSettings ?>/<?= $totalSettings ?></div>
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700">Modules actifs</div>
                        </div>
                        <div class="rounded-xl border border-purple-200 bg-purple-50/80 px-4 py-2 text-center">
                            <div class="text-2xl font-black text-purple-900"><?= count($zoneRows) ?></div>
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-purple-700">Zones géo</div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation rapide -->
                <div class="flex flex-wrap gap-2">
                    <a href="<?= $h(url('back-office/atak/controle-serveur')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-950 shadow-sm hover:bg-emerald-100 transition-colors">
                        🎮 Contrôle mission
                    </a>
                    <a href="<?= $h($atakHubUrl) ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
                        📊 Poste de situation
                    </a>
                    <a href="<?= url('atak') ?>" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-950 shadow-sm hover:bg-blue-50/80 transition-colors">
                        🗺️ Carte tactique ATAK
                    </a>
                    <a href="<?= url('admin/atak/realism/config') ?>" class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-950 shadow-sm hover:bg-violet-100 transition-colors">
                        ⚙️ Config réalisme centralisée
                    </a>
                    <button onclick="runServerTests()" class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-950 shadow-sm hover:bg-amber-100 transition-colors">
                        🔬 Tests serveur
                    </button>
                </div>
            </div>
        </header>

        <!-- Layout 2 colonnes : Config (gauche) + Tests & Carte (droite)
             Utiliser lg: (pas xl:) : xl:col-span-8 est absent du CSS Tailwind compilé,
             ce qui réduisait la colonne config à 1/12 et rendait le formulaire illisible. -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Colonne gauche : Configuration (8/12) -->
            <div class="lg:col-span-8 min-w-0 space-y-6">
                <form method="POST" action="<?= $h($roleplayFormAction) ?>" class="space-y-6" id="roleplay-form">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">

                    <!-- Warning double pénalité -->
                    <?php
                    $portalDisconnect = !empty($config['disconnect_enabled']);
                    ?>
                    <?php if ($portalDisconnect): ?>
                    <div id="double-penalty-warning" class="rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-3 shadow-sm" role="alert">
                        <div class="flex items-start gap-3">
                            <svg class="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-amber-900">⚠️ Coupures réseau actives</p>
                                <p class="text-xs text-amber-800 mt-1">Double pénalité possible si simulation CBA active côté client.</p>
                            </div>
                            <button type="button" onclick="this.parentElement.parentElement.remove()" class="text-amber-600 hover:text-amber-900">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Section 1 : Simulation réseau (compacte) -->
                    <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-gradient-to-r from-blue-50 to-slate-50 border-b border-slate-100">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-sm font-black text-slate-900">Simulation réseau</h2>
                                        <p class="text-[11px] text-slate-600">Délais, pertes, déconnexions</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="network_enabled" class="sr-only peer" <?= ($config['network_enabled'] ?? false) ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                        </div>
                        <div class="px-4 py-4 space-y-4">
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div>
                                    <label class="text-xs font-semibold text-slate-700 mb-1.5 block">Mode</label>
                                    <select name="network_mode" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                                        <option value="normal" <?= ($config['network_mode'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Normal</option>
                                        <option value="hostile" <?= ($config['network_mode'] ?? 'normal') === 'hostile' ? 'selected' : '' ?>>Hostile</option>
                                        <option value="degraded" <?= ($config['network_mode'] ?? 'normal') === 'degraded' ? 'selected' : '' ?>>Dégradé</option>
                                        <option value="equipment" <?= ($config['network_mode'] ?? 'normal') === 'equipment' ? 'selected' : '' ?>>Défaut matériel</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-700 mb-1.5 block">Perte (%)</label>
                                    <input type="number" name="packet_loss_percent" value="<?= $h($config['packet_loss_percent'] ?? 0) ?>" min="0" max="100" step="0.1" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-700 mb-1.5 block">Latence min (ms)</label>
                                    <input type="number" name="latency_min_ms" value="<?= $h($config['latency_min_ms'] ?? 0) ?>" min="0" max="10000" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-700 mb-1.5 block">Latence max (ms)</label>
                                    <input type="number" name="latency_max_ms" value="<?= $h($config['latency_max_ms'] ?? 0) ?>" min="0" max="10000" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                                </div>
                            </div>
                            <div class="border-t border-slate-100 pt-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-slate-700">Déconnexions temporaires</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="disconnect_enabled" class="sr-only peer" <?= ($config['disconnect_enabled'] ?? false) ? 'checked' : '' ?>>
                                        <div class="w-9 h-5 bg-slate-200 peer-focus:ring-3 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="text-[11px] font-medium text-slate-600 mb-1 block">Min (sec)</label>
                                        <input type="number" name="disconnect_min_sec" value="<?= $h($config['disconnect_min_sec'] ?? 5) ?>" min="1" max="300" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 shadow-sm">
                                    </div>
                                    <div>
                                        <label class="text-[11px] font-medium text-slate-600 mb-1 block">Max (sec)</label>
                                        <input type="number" name="disconnect_max_sec" value="<?= $h($config['disconnect_max_sec'] ?? 30) ?>" min="1" max="300" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 shadow-sm">
                                    </div>
                                    <div>
                                        <label class="text-[11px] font-medium text-slate-600 mb-1 block">Intervalle (sec)</label>
                                        <input type="number" name="disconnect_interval_sec" value="<?= $h($config['disconnect_interval_sec'] ?? 600) ?>" min="60" max="3600" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Section 2 : Liaison ATAK + Capteurs + Intel (regroupées) -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Liaison relais -->
                        <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-4 py-3 bg-gradient-to-r from-purple-50 to-slate-50 border-b border-slate-100">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-xs font-black text-slate-900">Liaison relais</h3>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer w-full">
                                    <input type="checkbox" name="link_via_relays" class="sr-only peer" <?= ($config['link_via_relays'] ?? false) ? 'checked' : '' ?>>
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:ring-3 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
                                    <span class="ml-2 text-[11px] font-medium text-slate-700">Via relais uniquement</span>
                                </label>
                            </div>
                            <div class="px-4 py-3">
                                <p class="text-[11px] text-slate-600 leading-relaxed">Les téléphones transmettent uniquement via relais posés en jeu.</p>
                            </div>
                        </section>

                        <!-- Capteurs médicaux -->
                        <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-4 py-3 bg-gradient-to-r from-amber-50 to-slate-50 border-b border-slate-100">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-xs font-black text-slate-900">Capteurs médicaux</h3>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer w-full">
                                    <input type="checkbox" name="sensor_enabled" class="sr-only peer" <?= ($config['sensor_enabled'] ?? false) ? 'checked' : '' ?>>
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:ring-3 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-600"></div>
                                    <span class="ml-2 text-[11px] font-medium text-slate-700">Défauts activés</span>
                                </label>
                            </div>
                            <div class="px-4 py-3 space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-slate-600">Panne</span>
                                    <input type="number" name="sensor_failure_percent" value="<?= $h($config['sensor_failure_percent'] ?? 0) ?>" min="0" max="100" step="0.1" class="w-16 rounded border border-slate-300 px-2 py-1 text-[11px] text-slate-900">
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-slate-600">Erreur</span>
                                    <input type="number" name="sensor_error_percent" value="<?= $h($config['sensor_error_percent'] ?? 0) ?>" min="0" max="100" step="0.1" class="w-16 rounded border border-slate-300 px-2 py-1 text-[11px] text-slate-900">
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-slate-600">Manquant</span>
                                    <input type="number" name="sensor_missing_percent" value="<?= $h($config['sensor_missing_percent'] ?? 0) ?>" min="0" max="100" step="0.1" class="w-16 rounded border border-slate-300 px-2 py-1 text-[11px] text-slate-900">
                                </div>
                            </div>
                        </section>

                        <!-- Données chiffrées -->
                        <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-4 py-3 bg-gradient-to-r from-red-50 to-slate-50 border-b border-slate-100">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-xs font-black text-slate-900">Données chiffrées</h3>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer w-full">
                                    <input type="checkbox" name="intel_scramble_enabled" class="sr-only peer" <?= ($config['intel_scramble_enabled'] ?? false) ? 'checked' : '' ?>>
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:ring-3 peer-focus:ring-red-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-red-600"></div>
                                    <span class="ml-2 text-[11px] font-medium text-slate-700">Brouillage actif</span>
                                </label>
                            </div>
                            <div class="px-4 py-3">
                                <p class="text-[11px] text-slate-600 leading-relaxed">Sans certificat ou si capturé : données illisibles.</p>
                            </div>
                        </section>
                    </div>

                    <!-- Section 3 : Zones géographiques (compacte avec preview carte) -->
                    <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-4 py-3 bg-gradient-to-r from-purple-50 to-slate-50 border-b border-slate-100">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-sm font-black text-slate-900">Zones géographiques</h2>
                                        <p class="text-[11px] text-slate-600"><?= count($zoneRows) ?> zone(s) de dégradation active(s)</p>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="zones_enabled" class="sr-only peer" <?= ($config['zones_enabled'] ?? false) ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                                </label>
                            </div>
                        </div>
                        <div class="px-4 py-4">
                            <div id="roleplay-zones-list" class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
                                <?php if ($zoneRows === []): ?>
                                    <p id="roleplay-zones-empty" class="text-sm text-slate-500 text-center py-6">Aucune zone. Ajoutez-en une ci-dessous.</p>
                                <?php endif; ?>
                                <?php foreach ($zoneRows as $idx => $row): ?>
                                    <div class="roleplay-zone-row rounded-lg border border-purple-200 bg-purple-50/40 p-3" data-zone-row>
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-xs font-bold text-purple-900" data-zone-title>Zone <?= (int) $idx + 1 ?></p>
                                            <button type="button" class="text-xs font-semibold text-rose-600 hover:text-rose-900" data-remove-zone>🗑️ Retirer</button>
                                        </div>
                                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                                            <div>
                                                <label class="text-[10px] font-medium text-slate-600 mb-1 block">Est (m)</label>
                                                <input type="number" name="zone_center_x[]" step="1" value="<?= $h($row['center_x']) ?>" class="w-full rounded border border-purple-300 px-2 py-1 text-xs text-slate-900 bg-white">
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-medium text-slate-600 mb-1 block">Nord (m)</label>
                                                <input type="number" name="zone_center_y[]" step="1" value="<?= $h($row['center_y']) ?>" class="w-full rounded border border-purple-300 px-2 py-1 text-xs text-slate-900 bg-white">
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-medium text-slate-600 mb-1 block">Rayon (m)</label>
                                                <input type="number" name="zone_radius[]" min="5" step="1" value="<?= $h($row['radius']) ?>" class="w-full rounded border border-purple-300 px-2 py-1 text-xs text-slate-900 bg-white">
                                            </div>
                                            <div>
                                                <label class="text-[10px] font-medium text-slate-600 mb-1 block">Effet</label>
                                                <select name="zone_effect[]" class="w-full rounded border border-purple-300 px-2 py-1 text-xs text-slate-900 bg-white">
                                                    <?php foreach ($zoneEffectOptions as $effectValue => $effectLabel): ?>
                                                        <option value="<?= $h($effectValue) ?>" <?= ($row['effect'] ?? '') === $effectValue ? 'selected' : '' ?>><?= $h($effectLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" id="roleplay-add-zone" class="mt-3 w-full inline-flex items-center justify-center gap-2 rounded-lg border border-purple-300 bg-purple-50 px-4 py-2 text-sm font-semibold text-purple-950 hover:bg-purple-100 transition-colors">
                                ➕ Ajouter une zone
                            </button>
                            <template id="roleplay-zone-template">
                                <div class="roleplay-zone-row rounded-lg border border-purple-200 bg-purple-50/40 p-3" data-zone-row>
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-xs font-bold text-purple-900" data-zone-title>Nouvelle zone</p>
                                        <button type="button" class="text-xs font-semibold text-rose-600 hover:text-rose-900" data-remove-zone>🗑️ Retirer</button>
                                    </div>
                                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                                        <div>
                                            <label class="text-[10px] font-medium text-slate-600 mb-1 block">Est (m)</label>
                                            <input type="number" name="zone_center_x[]" step="1" value="0" class="w-full rounded border border-purple-300 px-2 py-1 text-xs text-slate-900 bg-white">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-medium text-slate-600 mb-1 block">Nord (m)</label>
                                            <input type="number" name="zone_center_y[]" step="1" value="0" class="w-full rounded border border-purple-300 px-2 py-1 text-xs text-slate-900 bg-white">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-medium text-slate-600 mb-1 block">Rayon (m)</label>
                                            <input type="number" name="zone_radius[]" min="5" step="1" value="500" class="w-full rounded border border-purple-300 px-2 py-1 text-xs text-slate-900 bg-white">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-medium text-slate-600 mb-1 block">Effet</label>
                                            <select name="zone_effect[]" class="w-full rounded border border-purple-300 px-2 py-1 text-xs text-slate-900 bg-white">
                                                <?php foreach ($zoneEffectOptions as $effectValue => $effectLabel): ?>
                                                    <option value="<?= $h($effectValue) ?>"><?= $h($effectLabel) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>

                    <!-- Actions formulaire -->
                    <div class="flex items-center justify-between gap-4 pt-2">
                        <a href="<?= $h($atakHubUrl) ?>" class="text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors">
                            ← Retour
                        </a>
                        <div class="flex gap-3">
                            <button type="button" onclick="if(confirm('Réinitialiser toute la configuration ?')) { window.location.href='<?= $h($roleplayResetUrl) ?>'; }" 
                                    class="rounded-lg border border-slate-300 bg-white px-5 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
                                🔄 Réinitialiser
                            </button>
                            <button type="submit" class="rounded-lg border border-blue-600 bg-blue-600 px-6 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors">
                                💾 Enregistrer
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Info activation client -->
                <div class="rounded-lg border border-blue-100 bg-blue-50/50 px-4 py-3">
                    <h3 class="text-xs font-semibold text-blue-900 mb-1.5">📱 Activation côté client</h3>
                    <p class="text-xs text-blue-800 leading-relaxed">
                        Les joueurs doivent activer le mode roleplay dans CBA Arma (<strong>COMSPEC Overwatch — Roleplay</strong>). 
                        Les effets web sont automatiques si configurés.
                    </p>
                </div>
            </div>

            <!-- Colonne droite : Tests serveur + Carte (4/12) -->
            <div class="lg:col-span-4 min-w-0 space-y-6">
                
                <!-- Tests serveur -->
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gradient-to-r from-emerald-50 to-slate-50 border-b border-slate-100">
                        <div class="flex items-center gap-2 mb-1">
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-sm font-black text-slate-900">Tests serveur</h3>
                        </div>
                        <button onclick="runServerTests()" class="w-full text-xs font-semibold text-emerald-700 hover:text-emerald-900">
                            🔬 Lancer les tests
                        </button>
                    </div>
                    <div id="server-tests-results" class="px-4 py-3 space-y-2 max-h-[400px] overflow-y-auto">
                        <?php if ($serverTests !== null): ?>
                            <?php foreach ($serverTests as $test): ?>
                                <div class="flex items-start gap-2 p-2 rounded-lg <?= $test['passed'] ? 'bg-emerald-50/50' : 'bg-rose-50/50' ?>">
                                    <div class="text-sm <?= $test['passed'] ? 'text-emerald-600' : 'text-rose-600' ?>">
                                        <?= $test['passed'] ? '✅' : '❌' ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-slate-900"><?= $h($test['name']) ?></p>
                                        <?php if (!empty($test['message'])): ?>
                                            <p class="text-[11px] text-slate-600 mt-0.5"><?= $h($test['message']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-sm text-slate-500 text-center py-8">Cliquez pour exécuter les tests</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Mini carte des zones -->
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gradient-to-r from-purple-50 to-slate-50 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-sm font-black text-slate-900">Carte des zones</h3>
                        </div>
                    </div>
                    <div class="p-4">
                        <div id="zones-map" class="w-full h-[400px] bg-slate-100 rounded-lg border border-slate-200 relative overflow-hidden">
                            <!-- Carte Canvas -->
                            <canvas id="zones-canvas" class="w-full h-full"></canvas>
                            <div class="absolute bottom-2 left-2 bg-white/90 rounded px-2 py-1 text-[10px] font-mono text-slate-700">
                                <?= count($zoneRows) ?> zone(s)
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <?php
                            $effectColors = [
                                'degraded' => 'bg-yellow-400',
                                'interference' => 'bg-orange-400',
                                'high_loss' => 'bg-red-400',
                                'jammer' => 'bg-purple-500',
                                'no_coverage' => 'bg-slate-700'
                            ];
                            ?>
                            <?php foreach ($zoneEffectOptions as $effectValue => $effectLabel): ?>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-3 h-3 rounded-full <?= $effectColors[$effectValue] ?? 'bg-slate-400' ?>"></div>
                                    <span class="text-[11px] text-slate-700"><?= $h($effectLabel) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

            </div>
        </div>

    </div>
</div>

<script>
// Gestion des zones (ajouter/retirer)
(function () {
    var list = document.getElementById('roleplay-zones-list');
    var addBtn = document.getElementById('roleplay-add-zone');
    var tpl = document.getElementById('roleplay-zone-template');
    if (!list || !addBtn || !tpl) return;

    function renumber() {
        var rows = list.querySelectorAll('[data-zone-row]');
        var empty = document.getElementById('roleplay-zones-empty');
        if (empty) empty.classList.toggle('hidden', rows.length > 0);
        rows.forEach(function (row, i) {
            var title = row.querySelector('[data-zone-title]');
            if (title) title.textContent = 'Zone ' + (i + 1);
        });
        drawZonesMap();
    }

    function bindRow(row) {
        var btn = row.querySelector('[data-remove-zone]');
        if (!btn) return;
        btn.addEventListener('click', function () {
            row.remove();
            renumber();
        });
        
        // Re-dessiner la carte quand les inputs changent
        row.querySelectorAll('input, select').forEach(function(input) {
            input.addEventListener('input', drawZonesMap);
        });
    }

    list.querySelectorAll('[data-zone-row]').forEach(bindRow);

    addBtn.addEventListener('click', function () {
        var node = tpl.content.cloneNode(true);
        var row = node.querySelector('[data-zone-row]');
        list.appendChild(node);
        if (row) bindRow(row);
        renumber();
    });

    // Dessiner initial
    if (list.querySelectorAll('[data-zone-row]').length > 0) {
        drawZonesMap();
    }
})();

// Tests serveur
function runServerTests() {
    var resultsDiv = document.getElementById('server-tests-results');
    if (!resultsDiv) return;
    
    resultsDiv.innerHTML = '<div class="text-center py-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-emerald-200 border-t-emerald-600"></div><p class="text-xs text-slate-600 mt-3">Tests en cours...</p></div>';
    
    fetch('<?= url("api/atak/roleplay/server-tests") ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '<?= $h($csrfToken) ?>'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (!data.ok || !data.tests) {
            resultsDiv.innerHTML = '<p class="text-sm text-rose-600 text-center py-8">❌ Erreur lors des tests</p>';
            return;
        }
        
        var html = '';
        data.tests.forEach(function(test) {
            var bgClass = test.passed ? 'bg-emerald-50/50' : 'bg-rose-50/50';
            var icon = test.passed ? '✅' : '❌';
            var iconColor = test.passed ? 'text-emerald-600' : 'text-rose-600';
            
            html += '<div class="flex items-start gap-2 p-2 rounded-lg ' + bgClass + '">';
            html += '<div class="text-sm ' + iconColor + '">' + icon + '</div>';
            html += '<div class="flex-1 min-w-0">';
            html += '<p class="text-xs font-semibold text-slate-900">' + escapeHtml(test.name) + '</p>';
            if (test.message) {
                html += '<p class="text-[11px] text-slate-600 mt-0.5">' + escapeHtml(test.message) + '</p>';
            }
            html += '</div></div>';
        });
        
        resultsDiv.innerHTML = html;
    })
    .catch(function() {
        resultsDiv.innerHTML = '<p class="text-sm text-rose-600 text-center py-8">❌ Erreur réseau</p>';
    });
}

function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Carte des zones
function drawZonesMap() {
    var canvas = document.getElementById('zones-canvas');
    if (!canvas) return;
    
    var ctx = canvas.getContext('2d');
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * window.devicePixelRatio;
    canvas.height = rect.height * window.devicePixelRatio;
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
    
    // Fond
    ctx.fillStyle = '#f1f5f9';
    ctx.fillRect(0, 0, rect.width, rect.height);
    
    // Grille
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 0.5;
    for (var i = 0; i <= 10; i++) {
        var x = i * rect.width / 10;
        var y = i * rect.height / 10;
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, rect.height);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(rect.width, y);
        ctx.stroke();
    }
    
    // Récupérer les zones
    var zones = [];
    var minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    
    document.querySelectorAll('[data-zone-row]').forEach(function(row) {
        var x = parseFloat(row.querySelector('input[name="zone_center_x[]"]')?.value || 0);
        var y = parseFloat(row.querySelector('input[name="zone_center_y[]"]')?.value || 0);
        var radius = parseFloat(row.querySelector('input[name="zone_radius[]"]')?.value || 500);
        var effect = row.querySelector('select[name="zone_effect[]"]')?.value || 'degraded';
        
        zones.push({ x: x, y: y, radius: radius, effect: effect });
        
        minX = Math.min(minX, x - radius);
        maxX = Math.max(maxX, x + radius);
        minY = Math.min(minY, y - radius);
        maxY = Math.max(maxY, y + radius);
    });
    
    if (zones.length === 0) {
        ctx.fillStyle = '#64748b';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Aucune zone', rect.width / 2, rect.height / 2);
        return;
    }
    
    // Calculer échelle
    var rangeX = maxX - minX || 1000;
    var rangeY = maxY - minY || 1000;
    var scale = Math.min((rect.width - 40) / rangeX, (rect.height - 40) / rangeY);
    var offsetX = rect.width / 2 - (minX + maxX) / 2 * scale;
    var offsetY = rect.height / 2 - (minY + maxY) / 2 * scale;
    
    // Couleurs par effet
    var effectColors = {
        'degraded': 'rgba(250, 204, 21, 0.5)',
        'interference': 'rgba(251, 146, 60, 0.5)',
        'high_loss': 'rgba(239, 68, 68, 0.5)',
        'jammer': 'rgba(168, 85, 247, 0.6)',
        'no_coverage': 'rgba(71, 85, 105, 0.6)'
    };
    
    var effectBorders = {
        'degraded': '#eab308',
        'interference': '#f97316',
        'high_loss': '#ef4444',
        'jammer': '#a855f7',
        'no_coverage': '#475569'
    };
    
    // Dessiner les zones
    zones.forEach(function(zone) {
        var cx = offsetX + zone.x * scale;
        var cy = offsetY + zone.y * scale;
        var r = zone.radius * scale;
        
        // Cercle de zone
        ctx.beginPath();
        ctx.arc(cx, cy, r, 0, 2 * Math.PI);
        ctx.fillStyle = effectColors[zone.effect] || 'rgba(100, 116, 139, 0.3)';
        ctx.fill();
        ctx.strokeStyle = effectBorders[zone.effect] || '#64748b';
        ctx.lineWidth = 2;
        ctx.stroke();
        
        // Point central
        ctx.beginPath();
        ctx.arc(cx, cy, 4, 0, 2 * Math.PI);
        ctx.fillStyle = effectBorders[zone.effect] || '#64748b';
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
    });
    
    // Axes
    ctx.strokeStyle = '#64748b';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(offsetX, rect.height / 2);
    ctx.lineTo(offsetX + rangeX * scale, rect.height / 2);
    ctx.moveTo(rect.width / 2, offsetY);
    ctx.lineTo(rect.width / 2, offsetY + rangeY * scale);
    ctx.stroke();
}

// Redessiner au resize
window.addEventListener('resize', drawZonesMap);
</script>
