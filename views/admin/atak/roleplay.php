<?php
declare(strict_types=1);

/** @var array $config Configuration roleplay actuelle */
/** @var array $tenant Informations de la communauté */

$config = $config ?? [];
$tenant = $tenant ?? [];
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
                    Simulez des dysfonctionnements réalistes (délais réseau, pertes de paquets, défauts de capteurs) pour renforcer l'immersion tactique.
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

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-5 py-4">
                <p class="text-sm font-medium text-emerald-900"><?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <form method="POST" action="<?= url('admin/atak/roleplay') ?>" class="space-y-6">

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
                                Perte de paquets (%)
                            </label>
                            <input type="number" name="packet_loss_percent" id="packet_loss_percent" 
                                   value="<?= htmlspecialchars((string) ($config['packet_loss_percent'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" 
                                   min="0" max="100" step="0.1" 
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                            <p class="mt-1 text-xs text-slate-500">Probabilité qu'une requête soit perdue</p>
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
                            <p class="mt-1 text-xs text-slate-500">Affiche FC = 0</p>
                        </div>
                        <div>
                            <label for="sensor_error_percent" class="block text-sm font-semibold text-slate-700 mb-2">
                                Valeur erronée (%)
                            </label>
                            <input type="number" name="sensor_error_percent" id="sensor_error_percent" 
                                   value="<?= htmlspecialchars((string) ($config['sensor_error_percent'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" 
                                   min="0" max="100" step="0.1" 
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20">
                            <p class="mt-1 text-xs text-slate-500">FC aberrant (± 30-200%)</p>
                        </div>
                        <div>
                            <label for="sensor_missing_percent" class="block text-sm font-semibold text-slate-700 mb-2">
                                Données manquantes (%)
                            </label>
                            <input type="number" name="sensor_missing_percent" id="sensor_missing_percent" 
                                   value="<?= htmlspecialchars((string) ($config['sensor_missing_percent'] ?? 0), ENT_QUOTES, 'UTF-8') ?>" 
                                   min="0" max="100" step="0.1" 
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20">
                            <p class="mt-1 text-xs text-slate-500">Aucune valeur FC</p>
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
                                Créez des zones via les modules Zeus/Eden in-game (section COMSPEC Roleplay). Types disponibles : sans couverture, interférence, dégradée, brouilleur.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-5">
                    <div class="rounded-lg border border-purple-200 bg-purple-50/50 px-4 py-3">
                        <p class="text-xs text-purple-900 leading-relaxed">
                            <strong>Configuration in-game uniquement</strong> — Utilisez les modules Zeus/Eden pour placer des zones de dégradation réseau sur la carte.
                            Les joueurs verront des alertes et subiront des effets selon le type de zone (déconnexion, interférences, packet loss).
                        </p>
                    </div>
                </div>
            </section>

            <!-- Mode Troll -->
            <section class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50/50 to-orange-50/30 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-amber-100 bg-amber-50/80">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-sm font-black text-amber-900 tracking-tight flex items-center gap-2">
                                🤖 Mode Troll (Captcha)
                            </h2>
                            <p class="mt-1 text-xs text-amber-800 leading-relaxed">
                                Force les joueurs à valider des captcha, tests anti-robot et autres vérifications absurdes avant d'accéder à l'ATAK Enhanced in-game.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-5 space-y-4">
                    <div class="rounded-lg border border-orange-200 bg-orange-50/50 px-4 py-3">
                        <p class="text-xs text-orange-900 leading-relaxed mb-3">
                            <strong>⚠ Configuration côté client uniquement</strong> — Les joueurs doivent activer le mode dans leurs paramètres CBA Arma :
                        </p>
                        <div class="bg-white rounded border border-orange-200 px-3 py-2 font-mono text-[11px] text-slate-800 space-y-1">
                            <p><strong>Paramètre CBA :</strong> comspec_overwatch_troll_mode</p>
                            <p class="text-slate-600">• 0 = Désactivé (par défaut)</p>
                            <p class="text-slate-600">• 1 = Occasionnel (10% de chance)</p>
                            <p class="text-slate-600">• 2 = Fréquent (40% de chance)</p>
                            <p class="text-slate-600">• 3 = Systématique (100%, toujours)</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-xs font-bold text-amber-900">Types de captcha disponibles :</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                <p class="text-xs font-semibold text-slate-900">Vérification de l'âge</p>
                                <p class="text-[11px] text-slate-600 mt-0.5">Confirmer une date de naissance aléatoire</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                <p class="text-xs font-semibold text-slate-900">Test anti-robot</p>
                                <p class="text-[11px] text-slate-600 mt-0.5">"Êtes-vous un robot ?" et questions absurdes</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                <p class="text-xs font-semibold text-slate-900">Maths impossibles</p>
                                <p class="text-[11px] text-slate-600 mt-0.5">Équations complexes ou pièges logiques</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                <p class="text-xs font-semibold text-slate-900">Sélection d'images</p>
                                <p class="text-[11px] text-slate-600 mt-0.5">"Cliquez sur tous les feux tricolores"</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                <p class="text-xs font-semibold text-slate-900">CGU illisibles</p>
                                <p class="text-[11px] text-slate-600 mt-0.5">847 pages de conditions générales absurdes</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                                <p class="text-xs font-semibold text-slate-900">Questions de sécurité</p>
                                <p class="text-[11px] text-slate-600 mt-0.5">Informations impossibles à connaître</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-red-200 bg-red-50/50 px-4 py-3">
                        <p class="text-xs font-bold text-red-900 mb-1">Mécanisme de troll</p>
                        <p class="text-[11px] text-red-800 leading-relaxed">
                            30% de chance que même la bonne réponse soit refusée. Messages d'erreur variés : "Erreur 418 : Je suis une théière", "Réponse trop rapide", etc.
                            Cooldown de 60s entre deux captchas.
                        </p>
                    </div>

                    <div class="rounded-lg border border-blue-200 bg-blue-50/50 px-4 py-3">
                        <p class="text-xs font-bold text-blue-900 mb-1">Cas d'usage recommandés</p>
                        <p class="text-[11px] text-blue-800 leading-relaxed">
                            <strong>Niveau 1 (Occasionnel) :</strong> Ajoute une touche d'humour rare sans gêner le gameplay<br/>
                            <strong>Niveau 2 (Fréquent) :</strong> Pour des missions "funny" ou événements communautaires<br/>
                            <strong>Niveau 3 (Systématique) :</strong> ⚠ Très contraignant — réservé aux sessions de test ou punitions humoristiques
                        </p>
                    </div>
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

        <div class="rounded-lg border border-blue-100 bg-blue-50/50 px-5 py-4 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-blue-900 mb-2">📋 Activation côté client (CBA Settings)</h3>
                <p class="text-xs text-blue-800 leading-relaxed mb-3">
                    Les joueurs doivent configurer les paramètres CBA Arma dans la section <strong>COMSPEC Overwatch — Roleplay</strong> :
                </p>
                <div class="bg-white rounded border border-blue-200 px-4 py-3 space-y-2 text-xs">
                    <div class="flex items-start gap-2">
                        <span class="text-blue-600 font-bold">✓</span>
                        <div>
                            <p class="font-semibold text-slate-900">comspec_overwatch_roleplay_enabled</p>
                            <p class="text-slate-600 text-[11px]">Active/désactive tous les effets roleplay (base)</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-blue-600 font-bold">✓</span>
                        <div>
                            <p class="font-semibold text-slate-900">comspec_overwatch_roleplay_network_failures</p>
                            <p class="text-slate-600 text-[11px]">Active les simulations réseau (délais, packet loss, déconnexions)</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-blue-600 font-bold">✓</span>
                        <div>
                            <p class="font-semibold text-slate-900">comspec_overwatch_roleplay_sensor_failures</p>
                            <p class="text-slate-600 text-[11px]">Active les défauts de capteur de rythme cardiaque</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-blue-600 font-bold">✓</span>
                        <div>
                            <p class="font-semibold text-slate-900">comspec_overwatch_roleplay_visual_effects</p>
                            <p class="text-slate-600 text-[11px]">Affiche les glitchs et parasites dans l'ATAK Enhanced in-game</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-blue-600 font-bold">✓</span>
                        <div>
                            <p class="font-semibold text-slate-900">comspec_overwatch_atak_realism (0-3)</p>
                            <p class="text-slate-600 text-[11px]">Niveau de dommages physiques à l'ATAK (blessures au torse)</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-amber-600 font-bold">🤖</span>
                        <div>
                            <p class="font-semibold text-slate-900">comspec_overwatch_troll_mode (0-3)</p>
                            <p class="text-slate-600 text-[11px]">Mode captcha troll (0=désactivé, 1=10%, 2=40%, 3=100%)</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 px-3 py-2">
                <p class="text-[11px] text-emerald-900 leading-relaxed">
                    <strong>Configuration recommandée pour démarrer :</strong> Activez uniquement <code class="bg-white px-1 py-0.5 rounded text-[10px]">roleplay_enabled</code> et 
                    <code class="bg-white px-1 py-0.5 rounded text-[10px]">roleplay_network_failures</code>, puis ajustez les paramètres serveur ci-dessus.
                </p>
            </div>
        </div>

    </div>
</div>
