<?php
declare(strict_types=1);

/** @var array $tenant */
/** @var list<array> $relays */
/** @var array $stats */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Fonction helper pour formatter la date
$formatDate = function($timestamp) {
    if (!$timestamp) return '—';
    $dt = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($dt);
    
    if ($diff->days === 0 && $diff->h === 0 && $diff->i < 5) {
        return '<span class="text-emerald-600 font-semibold">● En direct</span>';
    } elseif ($diff->days === 0) {
        return '<span class="text-blue-600">Il y a ' . $diff->h . 'h ' . $diff->i . 'min</span>';
    } elseif ($diff->days === 1) {
        return '<span class="text-amber-600">Hier</span>';
    } else {
        return '<span class="text-slate-500">' . $diff->days . ' jours</span>';
    }
};

// Couleur de statut
$statusColor = function($alive, $reliability) {
    if (!$alive) return 'bg-slate-700 text-white';
    if ($reliability >= 90) return 'bg-emerald-500 text-white';
    if ($reliability >= 70) return 'bg-blue-500 text-white';
    if ($reliability >= 50) return 'bg-amber-500 text-white';
    return 'bg-rose-500 text-white';
};

$statusLabel = function($alive, $reliability) {
    if (!$alive) return 'HORS LIGNE';
    if ($reliability >= 90) return 'EXCELLENT';
    if ($reliability >= 70) return 'BON';
    if ($reliability >= 50) return 'DÉGRADÉ';
    return 'CRITIQUE';
};
?>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">
    <!-- Header style Germinal -->
    <div class="border-b border-amber-500/30 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900">
        <div class="max-w-[1600px] mx-auto px-6 py-8">
            <div class="flex items-start justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center shadow-lg shadow-amber-500/50">
                            <svg class="w-7 h-7 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.3em] text-amber-400 mb-1">COMSPEC ATAK — Réseau tactique</p>
                            <h1 class="text-3xl font-black text-white tracking-tight">Tours de réseau radio</h1>
                        </div>
                    </div>
                    <p class="text-sm text-slate-400 max-w-2xl">
                        Supervision en temps réel du réseau de relais radio tactique. 
                        Couverture, capacité, état de santé et performances.
                    </p>
                </div>

                <!-- Stats globales (style Germinal) -->
                <div class="grid grid-cols-4 gap-3">
                    <div class="bg-slate-800/50 border border-emerald-500/30 rounded-lg px-4 py-3 backdrop-blur-sm">
                        <div class="text-2xl font-black text-emerald-400"><?= $stats['alive'] ?></div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Actifs</div>
                    </div>
                    <div class="bg-slate-800/50 border border-slate-600/30 rounded-lg px-4 py-3 backdrop-blur-sm">
                        <div class="text-2xl font-black text-slate-400"><?= $stats['dead'] ?></div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Hors ligne</div>
                    </div>
                    <div class="bg-slate-800/50 border border-blue-500/30 rounded-lg px-4 py-3 backdrop-blur-sm">
                        <div class="text-2xl font-black text-blue-400"><?= $stats['used_slots'] ?>/<?= $stats['total_slots'] ?></div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Connexions</div>
                    </div>
                    <div class="bg-slate-800/50 border border-amber-500/30 rounded-lg px-4 py-3 backdrop-blur-sm">
                        <div class="text-2xl font-black text-amber-400"><?= $stats['network_load'] ?>%</div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Charge réseau</div>
                    </div>
                </div>
            </div>

            <!-- Navigation rapide -->
            <div class="mt-6 flex gap-2">
                <a href="<?= url('back-office/atak') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-700 transition-colors">
                    ← Poste de situation
                </a>
                <a href="<?= url('back-office/atak/roleplay') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-700 transition-colors">
                    Mode Roleplay
                </a>
                <a href="<?= url('admin/atak/realism/config') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-700 transition-colors">
                    Config réalisme
                </a>
                <a href="<?= url('atak') ?>" class="inline-flex items-center gap-2 rounded-lg border border-amber-600 bg-amber-600/20 px-3 py-1.5 text-xs font-semibold text-amber-400 hover:bg-amber-600/30 transition-colors">
                    🗺️ Carte tactique
                </a>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="max-w-[1600px] mx-auto px-6 py-8 space-y-6">

        <?php if (empty($relays)): ?>
        <!-- État vide -->
        <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-12 text-center backdrop-blur-sm">
            <svg class="w-16 h-16 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
            </svg>
            <h3 class="text-lg font-bold text-slate-300 mb-2">Aucun relais déployé</h3>
            <p class="text-sm text-slate-500 mb-6">Les relais apparaîtront ici une fois déployés en jeu par Zeus ou les joueurs.</p>
            <a href="<?= url('docs/TUTORIEL-JOUEUR-REALISME.md') ?>" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 transition-colors">
                📖 Guide relais radio
            </a>
        </div>
        <?php else: ?>

        <!-- Metrics bar -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-4 backdrop-blur-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Portée moyenne</div>
                <div class="text-2xl font-black text-white"><?= number_format($stats['avg_range'], 0, ',', ' ') ?> <span class="text-base text-slate-500">m</span></div>
            </div>
            <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-4 backdrop-blur-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Puissance totale</div>
                <div class="text-2xl font-black text-white"><?= $stats['total_power'] ?> <span class="text-base text-slate-500">W</span></div>
            </div>
            <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-4 backdrop-blur-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Débit total</div>
                <div class="text-2xl font-black text-white"><?= $stats['total_throughput'] ?> <span class="text-base text-slate-500">Mbps</span></div>
            </div>
            <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-4 backdrop-blur-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Fiabilité moyenne</div>
                <div class="text-2xl font-black text-white"><?= $stats['avg_reliability'] ?> <span class="text-base text-slate-500">%</span></div>
            </div>
            <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-4 backdrop-blur-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Slots libres</div>
                <div class="text-2xl font-black text-white"><?= $stats['free_slots'] ?> <span class="text-base text-slate-500">/ <?= $stats['total_slots'] ?></span></div>
            </div>
            <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-4 backdrop-blur-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Relais total</div>
                <div class="text-2xl font-black text-white"><?= $stats['total'] ?></div>
            </div>
        </div>

        <!-- Table des relais (style Germinal terminal) -->
        <div class="rounded-xl border border-slate-700 bg-slate-900/80 overflow-hidden backdrop-blur-sm">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700 bg-slate-800/80">
                            <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-wider text-amber-400">Statut</th>
                            <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-wider text-amber-400">Identifiant</th>
                            <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-wider text-amber-400">Position</th>
                            <th class="px-4 py-3 text-right text-[11px] font-black uppercase tracking-wider text-amber-400">Portée</th>
                            <th class="px-4 py-3 text-center text-[11px] font-black uppercase tracking-wider text-amber-400">Connexions</th>
                            <th class="px-4 py-3 text-right text-[11px] font-black uppercase tracking-wider text-amber-400">Puissance</th>
                            <th class="px-4 py-3 text-right text-[11px] font-black uppercase tracking-wider text-amber-400">Débit</th>
                            <th class="px-4 py-3 text-right text-[11px] font-black uppercase tracking-wider text-amber-400">Fiabilité</th>
                            <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-wider text-amber-400">Réseau</th>
                            <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-wider text-amber-400">Dernière activité</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php foreach ($relays as $relay): ?>
                        <?php
                        $alive = (bool) ($relay['alive'] ?? false);
                        $reliability = (int) ($relay['reliability_pct'] ?? 0);
                        $slots = (int) ($relay['slots'] ?? 0);
                        $slotsUsed = (int) ($relay['slots_used'] ?? 0);
                        $loadPct = $slots > 0 ? round(($slotsUsed / $slots) * 100) : 0;
                        ?>
                        <tr class="hover:bg-slate-800/50 transition-colors">
                            <!-- Statut -->
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded px-2 py-1 text-[10px] font-black uppercase tracking-wider <?= $statusColor($alive, $reliability) ?>">
                                    <?php if ($alive): ?>
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                    <?php else: ?>
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 8 8"><rect width="8" height="8"/></svg>
                                    <?php endif; ?>
                                    <?= $statusLabel($alive, $reliability) ?>
                                </span>
                            </td>

                            <!-- Identifiant -->
                            <td class="px-4 py-4">
                                <div class="font-mono text-sm font-semibold text-white">
                                    <?= $h($relay['display_name'] ?: $relay['relay_uid']) ?>
                                </div>
                                <?php if (!empty($relay['identity'])): ?>
                                <div class="text-xs text-slate-500 font-mono mt-0.5"><?= $h($relay['identity']) ?></div>
                                <?php endif; ?>
                            </td>

                            <!-- Position -->
                            <td class="px-4 py-4">
                                <div class="text-xs font-mono text-slate-300">
                                    <div>X: <span class="text-white"><?= number_format($relay['pos_x'], 0) ?></span></div>
                                    <div>Y: <span class="text-white"><?= number_format($relay['pos_y'], 0) ?></span></div>
                                    <?php if (($relay['pos_z'] ?? 0) > 0): ?>
                                    <div>Z: <span class="text-white"><?= number_format($relay['pos_z'], 0) ?></span></div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Portée -->
                            <td class="px-4 py-4 text-right">
                                <div class="text-base font-bold text-white"><?= number_format($relay['range_m'], 0) ?> <span class="text-xs text-slate-500">m</span></div>
                            </td>

                            <!-- Connexions -->
                            <td class="px-4 py-4">
                                <div class="flex flex-col items-center gap-1">
                                    <div class="text-base font-bold text-white"><?= $slotsUsed ?> / <?= $slots ?></div>
                                    <div class="w-20 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                        <div class="h-full <?= $loadPct > 80 ? 'bg-rose-500' : ($loadPct > 50 ? 'bg-amber-500' : 'bg-emerald-500') ?>" style="width: <?= $loadPct ?>%"></div>
                                    </div>
                                    <div class="text-[10px] text-slate-500"><?= $loadPct ?>%</div>
                                </div>
                            </td>

                            <!-- Puissance -->
                            <td class="px-4 py-4 text-right">
                                <div class="text-base font-bold text-white"><?= $relay['power_w'] ?? 0 ?> <span class="text-xs text-slate-500">W</span></div>
                            </td>

                            <!-- Débit -->
                            <td class="px-4 py-4 text-right">
                                <div class="text-base font-bold text-white"><?= number_format($relay['throughput_mbps'] ?? 0, 1) ?> <span class="text-xs text-slate-500">Mbps</span></div>
                            </td>

                            <!-- Fiabilité -->
                            <td class="px-4 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <div class="w-12 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                        <div class="h-full <?= $reliability >= 90 ? 'bg-emerald-500' : ($reliability >= 70 ? 'bg-blue-500' : ($reliability >= 50 ? 'bg-amber-500' : 'bg-rose-500')) ?>" style="width: <?= $reliability ?>%"></div>
                                    </div>
                                    <span class="text-base font-bold text-white"><?= $reliability ?>%</span>
                                </div>
                            </td>

                            <!-- Réseau -->
                            <td class="px-4 py-4">
                                <div class="text-xs font-mono text-slate-300 space-y-0.5">
                                    <?php if (!empty($relay['ip_addr'])): ?>
                                    <div title="IP">🔸 <?= $h($relay['ip_addr']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($relay['gateway'])): ?>
                                    <div title="Gateway" class="text-slate-500">→ <?= $h($relay['gateway']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($relay['certificate'])): ?>
                                    <div title="Certificat" class="text-emerald-500">🔒 Cert OK</div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Dernière activité -->
                            <td class="px-4 py-4">
                                <div class="text-xs">
                                    <?= $formatDate($relay['last_seen_at']) ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Légende -->
        <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-4 backdrop-blur-sm">
            <div class="flex items-start gap-6 text-xs">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full bg-emerald-500"></span>
                    <span class="text-slate-400">EXCELLENT (≥90%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full bg-blue-500"></span>
                    <span class="text-slate-400">BON (70-89%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full bg-amber-500"></span>
                    <span class="text-slate-400">DÉGRADÉ (50-69%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full bg-rose-500"></span>
                    <span class="text-slate-400">CRITIQUE (<50%)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full bg-slate-700"></span>
                    <span class="text-slate-400">HORS LIGNE</span>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </div>
</div>
