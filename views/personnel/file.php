<?php
$base = url('');
$targetUser = $targetUser ?? null;
$personnelExtras = $personnelExtras ?? null;
$userProfile = $userProfile ?? null;
$grade = $grade ?? null;
$grades = $grades ?? [];
$adminPanels = $adminPanels ?? [];
$adminDataByPanel = $adminDataByPanel ?? [];
if (!$targetUser) {
    echo '<p>Utilisateur non trouvé.</p>';
    return;
}
$matricule = $personnelExtras['service_number'] ?? null;
$callsign = $targetUser['callsign'] ?? null;
$displayName = $targetUser['display_name'] ?: $targetUser['email'];
$readiness = isset($personnelExtras['readiness_percent']) ? (int) $personnelExtras['readiness_percent'] : null;
$adminNotes = $personnelExtras['admin_notes'] ?? null;
$avatarUrl = !empty($targetUser['avatar_url']) ? $targetUser['avatar_url'] : null;
$baseUrl = url('');
if ($avatarUrl && strpos($avatarUrl, 'http') !== 0) {
    $avatarUrl = $baseUrl . '/' . ltrim($avatarUrl, '/');
}
?>
<main class="min-h-screen pt-20 pb-24">
    <div class="max-w-5xl mx-auto px-8">

        <header class="flex flex-col md:flex-row justify-between items-start md:items-end gap-10 mb-20">
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <span class="w-12 h-[1px] bg-slate-900"></span>
                    <span class="text-[9px] font-black uppercase tracking-[0.5em] text-emerald-600 italic">Personnel Dossier</span>
                </div>
                <h1 class="text-6xl md:text-8xl font-black uppercase tracking-tighter leading-[0.8] italic text-slate-900">
                    Fiche <br> Personnel
                </h1>
            </div>

            <div class="flex flex-col items-start md:items-end font-black uppercase tracking-[0.3em]">
                <span class="text-[7px] text-slate-400 mb-1">Système Athena v.2.0.4</span>
                <span class="text-[10px] text-slate-900 italic underline decoration-emerald-500 underline-offset-8">Accès autorisé</span>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">

            <aside class="lg:col-span-4 lg:sticky lg:top-32 h-fit">
                <div class="bg-white rounded-3xl p-8 shadow-[0_20px_50px_rgba(15,23,42,0.04)] border border-slate-200 transition-all hover:shadow-[0_30px_60px_rgba(15,23,42,0.08)] group">
                    <div class="aspect-[4/5] bg-slate-100 rounded-2xl mb-8 overflow-hidden relative border border-slate-200">
                        <?php if ($avatarUrl): ?>
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($displayName) ?>" class="absolute inset-0 w-full h-full object-cover" />
                        <div class="absolute inset-0 bg-slate-900/5 mix-blend-overlay"></div>
                        <?php else: ?>
                        <div class="absolute inset-0 bg-slate-900/10 mix-blend-overlay"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <svg class="w-16 h-16 text-slate-300 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <?php endif; ?>
                        <div class="absolute bottom-4 left-4 flex gap-1">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            <span class="text-[6px] font-black text-white uppercase tracking-widest bg-slate-700 px-2 py-0.5 rounded">Live</span>
                        </div>
                    </div>

                    <div class="space-y-6 uppercase">
                        <div>
                            <p class="text-[7px] font-black text-slate-400 tracking-[0.4em] mb-1 italic">Matricule</p>
                            <?php if ($matricule): ?>
                            <p class="text-xl font-black text-slate-900 tracking-tighter"><?= htmlspecialchars($matricule) ?></p>
                            <?php else: ?>
                            <p class="text-sm font-black text-slate-400 italic">Non attribué</p>
                            <form method="post" action="<?= url('personnel/' . (int)$targetUser['id'] . '/generate-matricule') ?>" class="mt-2">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="text-[9px] font-black uppercase tracking-widest text-emerald-600 hover:text-emerald-700">Générer</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-[7px] font-black text-slate-400 tracking-[0.4em] mb-1 italic">Indicatif</p>
                            <p class="text-3xl font-black text-slate-900 tracking-tighter italic"><?= htmlspecialchars($callsign ?: '—') ?></p>
                        </div>
                        <div class="flex gap-4 pt-4 border-t border-slate-100">
                            <div class="flex-1">
                                <p class="text-[7px] font-black text-slate-400 tracking-[0.2em] mb-1">Rang</p>
                                <p class="text-[10px] font-black text-slate-900 italic"><?= $grade ? htmlspecialchars($grade['short_name'] ?: $grade['name']) : '—' ?></p>
                            </div>
                            <div class="flex-1 text-right">
                                <p class="text-[7px] font-black text-slate-400 tracking-[0.2em] mb-1">Habilitation</p>
                                <p class="text-[10px] font-black text-emerald-600 italic underline"><?= htmlspecialchars($personnelExtras['clearance_level'] ?? '—') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-center">
                    <a href="<?= url('dashboard') ?>" class="text-[9px] font-black uppercase tracking-[0.4em] text-slate-400 hover:text-slate-900 transition-colors inline-flex items-center gap-4">
                        <span class="h-[1px] w-6 bg-slate-200"></span>
                        Dashboard
                    </a>
                </div>
            </aside>

            <div class="lg:col-span-8 space-y-4">

                <div class="grid md:grid-cols-2 gap-4">

                    <div class="bg-white border border-slate-200 rounded-3xl p-8 hover:border-slate-900/10 transition-colors">
                        <div class="flex justify-between items-start mb-10">
                            <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-900">Contact</h3>
                            <span class="text-[8px] font-mono text-slate-300">SEC_01</span>
                        </div>
                        <div class="space-y-6">
                            <div>
                                <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">E-mail Ops</p>
                                <p class="text-[11px] font-bold text-slate-900 italic uppercase"><?= htmlspecialchars($targetUser['email']) ?></p>
                            </div>
                            <div>
                                <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1">Statut Réseau</p>
                                <p class="text-[11px] font-bold <?= ($targetUser['status'] ?? '') === 'active' ? 'text-emerald-600' : 'text-slate-500' ?> uppercase italic"><?= ($targetUser['status'] ?? '') === 'active' ? 'Actif' : htmlspecialchars($targetUser['status'] ?? '—') ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-3xl p-8 hover:border-slate-900/10 transition-colors">
                        <div class="flex justify-between items-start mb-10">
                            <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-900">Affectation</h3>
                            <span class="text-[8px] font-mono text-slate-300">SEC_02</span>
                        </div>
                        <div class="space-y-4">
                            <p class="text-[10px] font-black text-slate-300 uppercase italic tracking-widest leading-loose">
                                <?= $personnelExtras['squadron'] ? htmlspecialchars($personnelExtras['squadron']) : 'Unité non assignée' ?>
                                <br>
                                <span class="text-slate-200">— <?= $personnelExtras['squadron'] ? 'Affecté' : 'Pending Deployment' ?></span>
                            </p>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-3xl p-8 md:col-span-2">
                        <div class="flex justify-between items-center mb-10">
                            <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-900 italic">Operational Readiness</h3>
                            <?php $pct = $readiness !== null ? $readiness : 0; ?>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black italic"><?= $pct ?>%</span>
                                <div class="w-20 h-1 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500" style="width: <?= min(100, max(0, $pct)) ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                            <div>
                                <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1 italic">Psychologie</p>
                                <p class="text-[10px] font-black text-slate-900 uppercase">—</p>
                            </div>
                            <div>
                                <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1 italic">Médical</p>
                                <p class="text-[10px] font-black text-slate-900 uppercase">—</p>
                            </div>
                            <div>
                                <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1 italic">Endurance</p>
                                <p class="text-[10px] font-black text-slate-900 uppercase italic">—</p>
                            </div>
                            <div>
                                <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1 italic">Blessures</p>
                                <p class="text-[10px] font-black text-emerald-600 uppercase italic underline underline-offset-4">Aucune</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-3xl p-8 md:col-span-2 shadow-sm">
                        <div class="flex justify-between items-center mb-10">
                            <h3 class="text-[10px] font-black uppercase tracking-[0.5em] text-slate-500 italic">Certifications & Formations</h3>
                            <span class="text-[8px] font-mono text-slate-300">FILE_MOD</span>
                        </div>
                        <div class="flex flex-wrap gap-4">
                            <div class="px-6 py-4 border border-slate-200 rounded-2xl flex flex-col gap-2 group hover:border-emerald-500/50 transition-colors">
                                <span class="text-[7px] font-black tracking-widest text-slate-400 uppercase">Expertise</span>
                                <span class="text-xs font-black uppercase italic tracking-tighter text-slate-900">—</span>
                            </div>
                            <div class="px-6 py-4 border border-slate-100 bg-slate-50 rounded-2xl flex flex-col gap-2">
                                <span class="text-[7px] font-black tracking-widest text-slate-300 uppercase italic">Non Renseigné</span>
                                <span class="text-xs font-black uppercase italic tracking-tighter text-slate-400">Module Tactique 02</span>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 bg-white border border-slate-200 rounded-3xl p-8">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-1.5 h-1.5 bg-rose-500 rounded-full"></div>
                            <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-900 italic">Notes du Commandement</h3>
                        </div>
                        <p class="text-xs text-slate-500 font-medium leading-loose italic uppercase tracking-wider">
                            <?= $adminNotes ? nl2br(htmlspecialchars($adminNotes)) : '— Aucune note enregistrée.' ?>
                        </p>
                    </div>

                    <?php foreach ($adminPanels as $panel): ?>
                    <?php
                        $panelId = (int) $panel['id'];
                        $data = $adminDataByPanel[$panelId] ?? [];
                    ?>
                    <div class="bg-white border border-slate-200 rounded-3xl p-8 md:col-span-2 hover:border-slate-900/10 transition-colors">
                        <div class="flex justify-between items-start mb-6">
                            <h3 class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-900"><?= htmlspecialchars($panel['name']) ?></h3>
                            <span class="text-[8px] font-mono text-slate-300"><?= htmlspecialchars($panel['slug']) ?></span>
                        </div>
                        <?php if (empty($data)): ?>
                        <p class="text-[10px] text-slate-400 italic uppercase tracking-wider">Non renseigné</p>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($data as $key => $value): ?>
                            <?php if ($value === null || $value === '') continue; ?>
                            <div>
                                <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest mb-1"><?= htmlspecialchars(is_string($key) ? $key : 'Champ') ?></p>
                                <p class="text-[11px] font-bold text-slate-900"><?= nl2br(htmlspecialchars(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value)) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>
    </div>
</main>
