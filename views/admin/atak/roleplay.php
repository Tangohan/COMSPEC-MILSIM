<?php
declare(strict_types=1);

/** @var array $config Configuration roleplay actuelle */
/** @var array $tenant Informations de la communauté */
/** @var list<array{center_x: float, center_y: float, radius: float, effect: string}> $zoneRows */
/** @var array<string, string> $zoneEffectOptions */

$config = $config ?? [];
$tenant = $tenant ?? [];
$zoneRows = is_array($zoneRows ?? null) ? $zoneRows : [];
$zoneEffectOptions = is_array($zoneEffectOptions ?? null) ? $zoneEffectOptions : [];
$csrfToken = (string) ($csrfToken ?? '');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="max-w-[1040px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">

        <header class="relative overflow-hidden rounded-2xl border border-blue-200/80 bg-gradient-to-br from-blue-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-8 py-7">
                <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-900/80 mb-2">
                    <span class="h-px w-6 bg-blue-400" aria-hidden="true"></span>
                    COMSPEC ATAK — Configuration avancée
                </p>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Mode Roleplay</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-3xl leading-relaxed">
                    Simulez des dysfonctionnements réalistes (délais réseau, pertes de liaison, défauts de capteurs) pour renforcer l'immersion tactique.
                    Les paramètres sont appliqués côté serveur et visibles dans l'interface web ATAK.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="<?= url('admin') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
                        Tableau de bord admin
                    </a>
                    <a href="<?= url('atak') ?>" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-950 shadow-sm hover:bg-blue-50/80 transition-colors">
                        Carte tactique ATAK
                    </a>
                </div>
            </div>
        </header>

        <form method="POST" action="<?= url('admin/atak/roleplay') ?>" class="space-y-6">
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">

            <!-- Simulation réseau -->
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-black text-slate-900 tracking-tight">Simulation réseau</h2>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Active des délais, pertes de paquets et déconnexions temporaires pour simuler des conditions dégradées.
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="network_enabled" class="sr-only peer" <?= ($config['network_enabled'] ?? false) ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ml-3 text-sm font-medium text-slate-900">Activer</span>
                        </label>
                    </div>
                </div>
                <div class="px-5 py-5 space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="network_mode" class="block text-sm font-semibold text-slate-700 mb-2">
                                Mode de simulation
                            </label>
                            <select name="network_mode" id="network_mode" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                                <option value="normal" <?= ($config['network_mode'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Conditions normales</option>
                                <option value="hostile" <?= ($config['network_mode'] ?? 'normal') === 'hostile' ? 'selected' : '' ?>>Zone hostile (interférences)</option>
                                <option value="degraded" <?= ($config['network_mode'] ?? 'normal') === 'degraded' ? 'selected' : '' ?>>Réseau dégradé</option>
                                <option value="equipment" <?= ($config['network_mode'] ?? 'normal') === 'equipment' ? 'selected' : '' ?>>Défaut matériel</option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">Définit les messages d'erreur affichés</p>
                        </div>
                        <div>
                            <label for="packet_loss_percent" class="block text-sm font-semibold text-slate-700 mb-2">
                                Perte de liaison (%)
                            </label>
                            <input type="number" name="packet_loss_percent" id="packet_loss_percent" 
                                   value="<?= htmlspecialchars((string) ($config['packet_loss_percent'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" 
                                   min="0" max="100" step="0.1" 
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                            <p class="mt-1 text-xs text-slate-500">Probabilité qu'une mise à jour ne parvienne pas</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="latency_min_ms" class="block text-sm font-semibold text-slate-700 mb-2">
                                Latence minimum (ms)
                            </label>
                            <input type="number" name="latency_min_ms" id="latency_min_ms" 
                                   value="<?= htmlspecialchars((string) ($config['latency_min_ms'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" 
                                   min="0" max="10000" 
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div>
                            <label for="latency_max_ms" class="block text-sm font-semibold text-slate-700 mb-2">
                                Latence maximum (ms)
                            </label>
                            <input type="number" name="latency_max_ms" id="latency_max_ms" 
                                   value="<?= htmlspecialchars((string) ($config['latency_max_ms'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" 
                                   min="0" max="10000" 
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-slate-700">Déconnexions temporaires</h3>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="disconnect_enabled" class="sr-only peer" <?= ($config['disconnect_enabled'] ?? false) ? 'checked' : '' ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label for="disconnect_min_sec" class="block text-sm font-medium text-slate-600 mb-2">
                                    Durée min. (sec)
                                </label>
                                <input type="number" name="disconnect_min_sec" id="disconnect_min_sec" 
                                       value="<?= htmlspecialchars((string) ($config['disconnect_min_sec'] ?? 5), ENT_QUOTES, 'UTF-8') ?>" 
                                       min="1" max="300" 
                                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                            </div>
                            <div>
                                <label for="disconnect_max_sec" class="block text-sm font-medium text-slate-600 mb-2">
                                    Durée max. (sec)
                                </label>
                                <input type="number" name="disconnect_max_sec" id="disconnect_max_sec" 
                                       value="<?= htmlspecialchars((string) ($config['disconnect_max_sec'] ?? 30), ENT_QUOTES, 'UTF-8') ?>" 
                                       min="1" max="300" 
                                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                            </div>
                            <div>
                                <label for="disconnect_interval_sec" class="block text-sm font-medium text-slate-600 mb-2">
                                    Intervalle (sec)
                                </label>
                                <input type="number" name="disconnect_interval_sec" id="disconnect_interval_sec" 
                                       value="<?= htmlspecialchars((string) ($config['disconnect_interval_sec'] ?? 600), ENT_QUOTES, 'UTF-8') ?>" 
                                       min="60" max="3600" 
                                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Temps entre deux coupures aléatoires</p>
                    </div>
                </div>
            </section>

            <!-- Défauts capteurs -->
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-black text-slate-900 tracking-tight">Défauts capteurs médicaux</h2>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Simule des dysfonctionnements du capteur de rythme cardiaque (valeurs manquantes, erronées ou nulles).
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="sensor_enabled" class="sr-only peer" <?= ($config['sensor_enabled'] ?? false) ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                            <span class="ml-3 text-sm font-medium text-slate-900">Activer</span>
                        </label>
                    </div>
                </div>
                <div class="px-5 py-5 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label for="sensor_failure_percent" class="block text-sm font-semibold text-slate-700 mb-2">
                                Panne complète (%)
                            </label>
                            <input type="number" name="sensor_failure_percent" id="sensor_failure_percent" 
                                   value="<?= htmlspecialchars((string) ($config['sensor_failure_percent'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" 
                                   min="0" max="100" step="0.1" 
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20">
                            <p class="mt-1 text-xs text-slate-500">Affiche un rythme cardiaque à 0</p>
                        </div>
                        <div>
                            <label for="sensor_error_percent" class="block text-sm font-semibold text-slate-700 mb-2">
                                Valeur erronée (%)
                            </label>
                            <input type="number" name="sensor_error_percent" id="sensor_error_percent" 
                                   value="<?= htmlspecialchars((string) ($config['sensor_error_percent'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" 
                                   min="0" max="100" step="0.1" 
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20">
                            <p class="mt-1 text-xs text-slate-500">Rythme cardiaque aberrant (± 30–200 %)</p>
                        </div>
                        <div>
                            <label for="sensor_missing_percent" class="block text-sm font-semibold text-slate-700 mb-2">
                                Données manquantes (%)
                            </label>
                            <input type="number" name="sensor_missing_percent" id="sensor_missing_percent" 
                                   value="<?= htmlspecialchars((string) ($config['sensor_missing_percent'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" 
                                   min="0" max="100" step="0.1" 
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20">
                            <p class="mt-1 text-xs text-slate-500">Aucune valeur de rythme cardiaque</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Zones géographiques -->
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-black text-slate-900 tracking-tight">Zones de dégradation géographique</h2>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Définissez des zones circulaires sur la carte où la liaison radio est dégradée. Les coordonnées correspondent au centre de zone en mètres (repère carte Arma).
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="zones_enabled" class="sr-only peer" <?= ($config['zones_enabled'] ?? false) ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                            <span class="ml-3 text-sm font-medium text-slate-900">Activer</span>
                        </label>
                    </div>
                </div>
                <div class="px-5 py-5 space-y-4">
                    <div id="roleplay-zones-list" class="space-y-3">
                        <?php if ($zoneRows === []): ?>
                            <p id="roleplay-zones-empty" class="text-sm text-slate-500">Aucune zone pour le moment. Ajoutez-en une ci-dessous.</p>
                        <?php endif; ?>
                        <?php foreach ($zoneRows as $idx => $row): ?>
                            <div class="roleplay-zone-row rounded-xl border border-slate-200 bg-slate-50/60 p-4" data-zone-row>
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-800" data-zone-title>Zone <?= (int) $idx + 1 ?></p>
                                    <button type="button" class="text-xs font-semibold text-rose-700 hover:text-rose-900" data-remove-zone>Retirer</button>
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Centre — axe Est (m)</label>
                                        <input type="number" name="zone_center_x[]" step="1" value="<?= $h($row['center_x']) ?>"
                                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Centre — axe Nord (m)</label>
                                        <input type="number" name="zone_center_y[]" step="1" value="<?= $h($row['center_y']) ?>"
                                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Rayon (m)</label>
                                        <input type="number" name="zone_radius[]" min="5" step="1" value="<?= $h($row['radius']) ?>"
                                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold text-slate-600">Effet</label>
                                        <select name="zone_effect[]" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20">
                                            <?php foreach ($zoneEffectOptions as $effectValue => $effectLabel): ?>
                                                <option value="<?= $h($effectValue) ?>" <?= ($row['effect'] ?? '') === $effectValue ? 'selected' : '' ?>><?= $h($effectLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="roleplay-add-zone"
                            class="inline-flex items-center gap-2 rounded-lg border border-purple-200 bg-white px-4 py-2 text-sm font-semibold text-purple-950 shadow-sm hover:bg-purple-50 transition-colors">
                        Ajouter une zone
                    </button>
                    <template id="roleplay-zone-template">
                        <div class="roleplay-zone-row rounded-xl border border-slate-200 bg-slate-50/60 p-4" data-zone-row>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-slate-800" data-zone-title>Nouvelle zone</p>
                                <button type="button" class="text-xs font-semibold text-rose-700 hover:text-rose-900" data-remove-zone>Retirer</button>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">Centre — axe Est (m)</label>
                                    <input type="number" name="zone_center_x[]" step="1" value="0"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">Centre — axe Nord (m)</label>
                                    <input type="number" name="zone_center_y[]" step="1" value="0"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">Rayon (m)</label>
                                    <input type="number" name="zone_radius[]" min="5" step="1" value="500"
                                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-600">Effet</label>
                                    <select name="zone_effect[]" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20">
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

            <!-- Données chiffrées -->
            <section id="intel-scramble" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-black text-slate-900 tracking-tight">Données chiffrées</h2>
                            <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                Sans certificat valide, ou si un appareil est capturé, le journal radio, les ordres, les marqueurs et les détails d’unités apparaissent illisibles.
                                Le brouillage corrompt partiellement le signal. Le poste de commandement connecté conserve une lecture claire.
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="intel_scramble_enabled" class="sr-only peer" <?= ($config['intel_scramble_enabled'] ?? false) ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                            <span class="ml-3 text-sm font-medium text-slate-900">Activer</span>
                        </label>
                    </div>
                </div>
                <div class="px-5 py-5 space-y-3 text-xs text-slate-600 leading-relaxed">
                    <p>Les certificats se gèrent dans <a class="font-semibold text-slate-900 underline" href="<?= url('back-office/atak/certificats') ?>">Certificats &amp; data packages</a>. Zeus peut marquer un appareil comme capturé in-game.</p>
                    <p>Par défaut cette option est désactivée pour ne pas surprendre les opérations déjà en cours.</p>
                </div>
            </section>

            <!-- Actions -->
            <div class="flex items-center justify-between gap-4 pt-4">
                <a href="<?= url('admin') ?>" class="text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors">
                    Annuler
                </a>
                <div class="flex gap-3">
                    <button type="button" onclick="if(confirm('Réinitialiser toute la configuration roleplay ?')) { window.location.href='<?= url('admin/atak/roleplay/reset') ?>'; }" 
                            class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
                        Réinitialiser
                    </button>
                    <button type="submit" class="rounded-lg border border-blue-600 bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors">
                        Enregistrer la configuration
                    </button>
                </div>
            </div>
        </form>

        <div class="rounded-lg border border-blue-100 bg-blue-50/50 px-5 py-4">
            <h3 class="text-sm font-semibold text-blue-900 mb-2">Activation côté client</h3>
            <p class="text-xs text-blue-800 leading-relaxed">
                Les joueurs doivent activer le mode roleplay dans les paramètres CBA Arma (section <strong>COMSPEC Overwatch — Roleplay</strong>).
                Les effets visuels web sont activés automatiquement si des simulations sont configurées.
            </p>
        </div>

    </div>
</div>
<script>
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
            var title = row.querySelector('[data-zone-title]') || row.querySelector('.text-sm.font-semibold');
            if (title) title.textContent = 'Zone ' + (i + 1);
        });
    }

    function bindRow(row) {
        var btn = row.querySelector('[data-remove-zone]');
        if (!btn) return;
        btn.addEventListener('click', function () {
            row.remove();
            renumber();
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
})();
</script>
