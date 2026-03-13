<?php
$base = url('');
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
$ref = 'JTFO-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrôlement — Athena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #0f172a; }
        .input-field { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.8rem; width: 100%; outline: none; transition: all 0.3s; font-size: 0.8rem; }
        .input-field:focus { border-color: #0f172a; box-shadow: 0 0 0 2px rgba(15,23,42,0.05); }
        .section-title { font-size: 9px; font-weight: 900; letter-spacing: 0.3em; text-transform: uppercase; color: #94a3b8; margin-bottom: 2rem; display: flex; align-items: center; gap: 15px; }
        .section-title::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; }
        @keyframes scan { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        .scan-line { animation: scan 4s linear infinite; opacity: 0.1; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

    <div id="preamble" class="fixed inset-0 z-[100] bg-slate-900 flex items-center justify-center p-6 transition-all duration-1000">
        <div class="max-w-2xl w-full">
            <div class="mb-12 flex items-center gap-4 border-b border-white/10 pb-6">
                <div class="w-12 h-12 bg-emerald-500 rounded-lg flex items-center justify-center font-black text-slate-900 text-xl">F</div>
                <div>
                    <h2 class="text-white text-xs font-black tracking-[0.4em] uppercase">Portail de Recrutement</h2>
                    <p class="text-white/40 text-[9px] font-mono uppercase">Infrastructure sécurisée — Athena COMSPEC</p>
                </div>
            </div>
            <div class="space-y-8">
                <div class="space-y-4">
                    <h1 class="text-white text-4xl font-black tracking-tighter uppercase">Accès Contrôlé</h1>
                    <p class="text-slate-400 text-sm leading-relaxed font-medium">
                        Vous allez accéder à l’interface de candidature. Ce formulaire constitue un dossier d’évaluation préalable.
                    </p>
                    <div class="bg-white/5 p-4 border-l-2 border-emerald-500 text-[11px] text-emerald-400/80 font-mono leading-relaxed">
                        Vérification de session : conforme<br>Canal de transmission : sécurisé<br>Journalisation des accès : active
                    </div>
                </div>
                <button type="button" onclick="startApp()" class="group relative w-full overflow-hidden bg-white text-slate-900 py-5 rounded-xl font-black tracking-[0.45em] uppercase transition-all hover:bg-emerald-500 hover:text-white active:scale-95">
                    <span class="relative z-10">Accéder au Formulaire</span>
                    <div class="absolute inset-0 bg-emerald-600 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                </button>
                <p class="text-center text-[8px] text-white/20 tracking-widest uppercase italic">La poursuite vaut prise de connaissance des conditions de traitement des données.</p>
            </div>
        </div>
    </div>

    <nav class="w-full bg-slate-900 text-white h-10 px-6 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-6">
            <span class="text-[9px] font-black tracking-[0.3em] text-emerald-400">JNET v2.4.0</span>
            <div class="h-4 w-[1px] bg-white/10"></div>
            <a href="<?= $base ?>/" class="text-[8px] font-mono text-white/40 hover:text-white tracking-widest uppercase">Accueil</a>
        </div>
        <div class="flex items-center gap-8">
            <div id="clock" class="text-[9px] font-mono font-bold tracking-tighter">--:--:-- Z</div>
        </div>
    </nav>

    <main class="max-w-[1400px] mx-auto py-8 px-6 grid grid-cols-1 lg:grid-cols-12 gap-8">
        <aside class="lg:col-span-3 space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <p class="text-[8px] font-black text-slate-400 tracking-[0.3em] uppercase mb-4">Statut Session</p>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold">Réf.</span>
                        <span class="text-[10px] font-mono bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($ref) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold">Sécurité</span>
                        <span class="text-[10px] text-emerald-600 font-bold">Encrypted</span>
                    </div>
                    <div class="w-full bg-slate-100 h-1 rounded-full overflow-hidden mt-4">
                        <div class="bg-slate-900 h-full transition-all duration-300" id="progress-bar" style="width:0%"></div>
                    </div>
                    <p id="progress-text" class="text-[8px] text-slate-400 text-center font-bold">FORMULAIRE : 0 / 20 RÉPONSES</p>
                </div>
            </div>
            <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                <p class="text-[8px] font-black text-white/30 tracking-[0.3em] uppercase mb-4">Règles d'Engagement (ROE)</p>
                <ul class="space-y-4">
                    <li class="flex gap-3"><span class="text-emerald-400 font-mono text-[10px]">01</span><p class="text-[10px] leading-relaxed text-white/70">Réponses détaillées obligatoires.</p></li>
                    <li class="flex gap-3"><span class="text-emerald-400 font-mono text-[10px]">02</span><p class="text-[10px] leading-relaxed text-white/70">Microphone de qualité requis.</p></li>
                    <li class="flex gap-3"><span class="text-emerald-400 font-mono text-[10px]">03</span><p class="text-[10px] leading-relaxed text-white/70">Disponibilité mercredi et samedi soir attendue.</p></li>
                    <li class="flex gap-3"><span class="text-emerald-400 font-mono text-[10px]">04</span><p class="text-[10px] leading-relaxed text-white/70">Ne pas relancer l'état-major après soumission.</p></li>
                </ul>
            </div>
        </aside>

        <div class="lg:col-span-9">
            <?php if ($success): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-medium"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl text-sm font-medium"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="bg-white border-x border-t border-slate-200 p-8 rounded-t-3xl border-b-2 border-slate-900">
                <div class="flex justify-between items-end mb-8 gap-6 flex-wrap">
                    <div>
                        <span class="text-[8px] font-black tracking-[0.5em] text-slate-400 uppercase">Document Control</span>
                        <h1 class="text-2xl font-black tracking-tighter uppercase leading-none">Candidature Olympus</h1>
                        <div class="flex items-center gap-4 text-[9px] font-bold tracking-widest uppercase text-slate-400 mt-3">
                            <span class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                                File d'attente active
                            </span>
                            <span>Réf: <?= htmlspecialchars($ref) ?></span>
                        </div>
                    </div>
                    <span class="text-[14px] font-black tracking-[0.2em] uppercase px-4 py-1 border-2 border-slate-900">CLASSIFIED</span>
                </div>
            </div>

            <div class="bg-white border-x border-b border-slate-200 shadow-2xl rounded-b-3xl relative overflow-hidden">
                <div class="w-full h-[2px] bg-slate-100 overflow-hidden relative">
                    <div class="absolute inset-0 bg-slate-900 w-1/2 scan-line"></div>
                </div>
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-[0.02] select-none rotate-12">
                    <span class="text-[120px] font-black">OLYMPUS</span>
                </div>
                <div class="p-8 border-b border-slate-100 bg-slate-50/70 relative z-10">
                    <div class="grid md:grid-cols-2 gap-8 text-[11px] leading-relaxed">
                        <div class="space-y-3">
                            <p class="font-black uppercase tracking-[0.25em] text-slate-400 text-[9px]">Note Opérationnelle</p>
                            <p class="text-slate-600">Toute soumission est examinée par la cellule de recrutement.</p>
                            <p class="text-red-600 font-black uppercase text-[10px] tracking-wide">L'utilisation de l'IA est strictement interdite.</p>
                        </div>
                        <div class="space-y-3 md:border-l border-slate-200 md:pl-8">
                            <p class="text-slate-600">Les candidats retenus seront contactés directement.</p>
                        </div>
                    </div>
                </div>

                <form method="post" action="<?= url('enlistment') ?>" class="p-8 md:p-12 space-y-12 relative z-10" id="recruitment-form">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="p-6 bg-slate-50 border-l-4 border-slate-900 mb-10">
                        <p class="text-[11px] leading-relaxed text-slate-600 font-medium">
                            <span class="text-slate-900 font-black uppercase">Note :</span>
                            Chaque réponse incomplète ou assistée par IA entraîne l'archivage du dossier.
                        </p>
                    </div>

                    <section>
                        <div class="section-title">Section I — Identité & Temps</div>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">01 Nom & Prénom</label>
                                <input type="text" name="full_name" class="input-field track-field" placeholder="ex: Jonathan King" required>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">02 Âge</label>
                                <input type="number" name="age" class="input-field track-field" placeholder="Âge minimum requis" min="16" max="99">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">03 Fuseau Horaire</label>
                                <input type="text" name="timezone" class="input-field track-field" placeholder="ex: Paris (UTC+1)">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">04 Disponibilités Hebdomadaires</label>
                                <input type="text" name="weekly_availability" class="input-field track-field" placeholder="Jours de la semaine">
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">Email (obligatoire)</label>
                                <input type="email" name="email" class="input-field track-field" placeholder="email@exemple.fr" required>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="section-title">Section II — Performance & Background</div>
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">05 Configuration (CPU/GPU/RAM)</label>
                                <input type="text" name="system_config" class="input-field track-field" placeholder="Configuration système">
                            </div>
                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black tracking-wider uppercase">06 Microphone de Haute Qualité ?</label>
                                    <select name="microphone_quality" class="input-field bg-white track-field">
                                        <option value="">Sélectionner</option>
                                        <option value="Oui">Oui</option>
                                        <option value="Non">Non</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black tracking-wider uppercase">08 Maîtrise ACE / ACRE</label>
                                    <select name="ace_acre_level" class="input-field bg-white track-field">
                                        <option value="">Sélectionner</option>
                                        <option value="Aucune">Aucune</option>
                                        <option value="Basique">Basique</option>
                                        <option value="Expérimenté">Expérimenté</option>
                                        <option value="Avancé">Avancé</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">07 Expériences MilSim Passées</label>
                                <textarea name="past_milsim_experience" class="input-field h-32 track-field" placeholder="Unités, rôles, durées..."></textarea>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="section-title">Section III — Intention & Mentalité</div>
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">09 Pourquoi rejoindre ?</label>
                                <textarea name="motivation_why_join" class="input-field h-24 track-field" placeholder="Motivation, engagement..."></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">10 Qu'est-ce que l'Accountability ?</label>
                                <textarea name="motivation_accountability" class="input-field h-24 track-field" placeholder="Responsabilité individuelle dans une unité..."></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="bg-slate-50 p-6 rounded-2xl space-y-6 border border-slate-100">
                        <div class="section-title">Section IV — Engagement</div>
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <span class="text-[11px] font-medium">13 Je comprends l'investissement temps/effort requis</span>
                            <select name="commitment_effort" class="input-field w-full md:w-40 track-field">
                                <option value="">Sélectionner</option>
                                <option value="Oui">Oui</option>
                                <option value="Non">Non</option>
                            </select>
                        </div>
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <span class="text-[11px] font-medium">15 Disponible mercredi & samedi soir</span>
                            <select name="availability_wed_sat" class="input-field w-full md:w-40 track-field">
                                <option value="">Sélectionner</option>
                                <option value="Oui">Oui</option>
                                <option value="Non">Non</option>
                                <option value="Variable">Variable</option>
                            </select>
                        </div>
                    </section>

                    <div class="pt-10 border-t border-slate-100">
                        <div class="flex items-center gap-4 mb-8">
                            <input type="checkbox" name="no_ai_confirmed" id="no-ai-check" value="1" class="w-5 h-5 rounded border-slate-300 accent-slate-900 track-field">
                            <label for="no-ai-check" class="text-[10px] font-black tracking-widest uppercase text-slate-500 cursor-pointer">20 Je confirme l'absence d'IA dans ce rapport</label>
                        </div>
                        <button type="submit" class="w-full bg-slate-900 text-white p-6 rounded-2xl font-black tracking-[0.5em] uppercase hover:bg-emerald-600 transition-all duration-500 shadow-xl active:scale-[0.98]">Soumettre au Commandement</button>
                        <p class="text-[8px] text-center mt-4 text-slate-400 tracking-widest uppercase italic">Transmission sécurisée</p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function updateClock() {
            var t = new Date().toISOString().split('T')[1].split('.')[0] + ' Z';
            var el = document.getElementById('clock');
            if (el) el.textContent = t;
        }
        function updateProgress() {
            var fields = document.querySelectorAll('.track-field');
            var completed = 0;
            fields.forEach(function(field) {
                if (field.type === 'checkbox') { if (field.checked) completed++; }
                else if (field.value && field.value.trim() !== '') completed++;
            });
            var total = fields.length;
            var percent = total ? Math.round((completed / total) * 100) : 0;
            var bar = document.getElementById('progress-bar');
            var text = document.getElementById('progress-text');
            if (bar) bar.style.width = percent + '%';
            if (text) text.textContent = 'FORMULAIRE : ' + completed + ' / ' + total + ' RÉPONSES';
        }
        function startApp() {
            var p = document.getElementById('preamble');
            if (p) { p.style.opacity = '0'; p.style.pointerEvents = 'none'; setTimeout(function() { p.classList.add('hidden'); }, 1000); }
        }
        setInterval(updateClock, 1000);
        updateClock();
        document.querySelectorAll('.track-field').forEach(function(f) {
            f.addEventListener('input', updateProgress);
            f.addEventListener('change', updateProgress);
        });
        updateProgress();
    </script>
</body>
</html>
