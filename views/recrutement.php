<?php $base = url(''); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .input-field {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.8rem;
            width: 100%;
            outline: none;
            transition: all 0.3s;
            font-size: 0.8rem;
        }

        .input-field:focus {
            border-color: #0f172a;
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.05);
        }

        .section-title {
            font-size: 9px;
            font-weight: 900;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        @keyframes scan {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .scan-line {
            animation: scan 4s linear infinite;
            opacity: 0.1;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

    <div id="preamble" class="fixed inset-0 z-[100] bg-slate-900 flex items-center justify-center p-6 transition-all duration-1000">
        <div class="max-w-2xl w-full">
            <div class="mb-12 flex items-center gap-4 border-b border-white/10 pb-6">
                <div class="w-12 h-12 bg-emerald-500 rounded-lg flex items-center justify-center font-black text-slate-900 text-xl">F</div>
                <div>
                    <h2 class="text-white text-xs font-black tracking-[0.4em] uppercase">Portail de Recrutement</h2>
                    <p class="text-white/40 text-[9px] font-mono uppercase">Infrastructure sécurisée — Frontline Operations Group</p>
                </div>
            </div>

            <div class="space-y-8">
                <div class="space-y-4">
                    <h1 class="text-white text-4xl font-black tracking-tighter uppercase">Accès Contrôlé</h1>
                    <p class="text-slate-400 text-sm leading-relaxed font-medium">
                        Vous allez accéder à l’interface de candidature de la <span class="text-white font-bold">Joint Task Force Olympus</span>.
                        Ce formulaire constitue un dossier d’évaluation préalable. Toute information transmise peut être examinée dans le cadre du processus de sélection interne.
                    </p>
                    <div class="bg-white/5 p-4 border-l-2 border-emerald-500 text-[11px] text-emerald-400/80 font-mono leading-relaxed">
                        Vérification de session : conforme<br>
                        Canal de transmission : sécurisé<br>
                        Journalisation des accès : active
                    </div>
                </div>

                <button onclick="startApp()" class="group relative w-full overflow-hidden bg-white text-slate-900 py-5 rounded-xl font-black tracking-[0.45em] uppercase transition-all hover:bg-emerald-500 hover:text-white active:scale-95">
                    <span class="relative z-10">Accéder au Formulaire</span>
                    <div class="absolute inset-0 bg-emerald-600 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                </button>

                <p class="text-center text-[8px] text-white/20 tracking-widest uppercase italic">
                    La poursuite de la navigation vaut prise de connaissance des conditions de traitement et d’enregistrement des données transmises.
                </p>
            </div>
        </div>
    </div>

    <nav class="w-full bg-slate-900 text-white h-10 px-6 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-6">
            <span class="text-[9px] font-black tracking-[0.3em] text-emerald-400">JNET v2.4.0</span>
            <div class="h-4 w-[1px] bg-white/10"></div>
            <span class="text-[8px] font-mono text-white/40 tracking-widest uppercase">Node: Paris_HQ_01</span>
        </div>
        <div class="flex items-center gap-8">
            <div class="flex items-center gap-2">
                <span class="text-[8px] font-bold text-white/40 uppercase">Latency:</span>
                <span class="text-[8px] font-mono text-emerald-400">14ms</span>
            </div>
            <div id="clock" class="text-[9px] font-mono font-bold tracking-tighter">17:42:01 Z</div>
        </div>
    </nav>

    <main class="max-w-[1400px] mx-auto py-8 px-6 grid grid-cols-1 lg:grid-cols-12 gap-8">

        <aside class="lg:col-span-3 space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <p class="text-[8px] font-black text-slate-400 tracking-[0.3em] uppercase mb-4">Statut Session</p>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold">Intake ID</span>
                        <span class="text-[10px] font-mono bg-slate-100 px-2 py-0.5 rounded">JTFO-175741</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold">Sécurité</span>
                        <span class="text-[10px] text-emerald-600 font-bold">Encrypted</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-bold">File</span>
                        <span class="text-[10px] text-emerald-600 font-bold flex items-center gap-2">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            Active
                        </span>
                    </div>
                    <div class="w-full bg-slate-100 h-1 rounded-full overflow-hidden mt-4">
                        <div class="bg-slate-900 h-full w-[0%]" id="progress-bar"></div>
                    </div>
                    <p id="progress-text" class="text-[8px] text-slate-400 text-center font-bold">FORMULAIRE : 0 / 20 RÉPONSES</p>
                </div>
            </div>

            <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                <p class="text-[8px] font-black text-white/30 tracking-[0.3em] uppercase mb-4">Règles d'Engagement (ROE)</p>
                <ul class="space-y-4">
                    <li class="flex gap-3">
                        <span class="text-emerald-400 font-mono text-[10px]">01</span>
                        <p class="text-[10px] leading-relaxed text-white/70">Réponses détaillées obligatoires. Aucune initiale, aucun formulaire bâclé.</p>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-emerald-400 font-mono text-[10px]">02</span>
                        <p class="text-[10px] leading-relaxed text-white/70">Microphone de qualité requis pour les évaluations radio et coordination.</p>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-emerald-400 font-mono text-[10px]">03</span>
                        <p class="text-[10px] leading-relaxed text-white/70">Disponibilité mercredi et samedi soir attendue.</p>
                    </li>
                    <li class="flex gap-3">
                        <span class="text-emerald-400 font-mono text-[10px]">04</span>
                        <p class="text-[10px] leading-relaxed text-white/70">Aucun contact avec l'état-major pour relancer le dossier après soumission.</p>
                    </li>
                </ul>
            </div>
        </aside>

        <div class="lg:col-span-9">
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
                            <span>Réf: JTFO-175741</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[14px] font-black tracking-[0.2em] uppercase px-4 py-1 border-2 border-slate-900">CLASSIFIED</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 py-6 border-y border-slate-100">
                    <div>
                        <p class="text-[7px] font-black text-slate-400 tracking-widest uppercase mb-1">Officier Traitant</p>
                        <p class="text-[10px] font-bold">MAJ. V. KANE [RECR-01]</p>
                    </div>
                    <div class="md:border-x border-slate-100 md:px-6">
                        <p class="text-[7px] font-black text-slate-400 tracking-widest uppercase mb-1">Réf. Protocole</p>
                        <p class="text-[10px] font-bold italic">ALPHA-WHISKEY-9</p>
                    </div>
                    <div class="md:text-right">
                        <p class="text-[7px] font-black text-slate-400 tracking-widest uppercase mb-1">Server Origin</p>
                        <p class="text-[10px] font-bold">EU-WEST-01 // HUB</p>
                    </div>
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
                            <p class="text-slate-600">Ceci n'est pas une inscription automatique. Toute soumission est examinée par la cellule de recrutement et validée ou rejetée par le commandement.</p>
                            <p class="text-red-600 font-black uppercase text-[10px] tracking-wide">L'utilisation de l'IA est strictement interdite et entraîne la disqualification immédiate.</p>
                        </div>
                        <div class="space-y-3 md:border-l border-slate-200 md:pl-8">
                            <p class="text-slate-600">Ne contactez pas l'état-major pour demander le statut de votre dossier. Les candidats retenus seront contactés directement.</p>
                            <p class="font-bold text-slate-900">Important : ouvrez votre ticket de recrutement Discord avant transmission de ce formulaire.</p>
                        </div>
                    </div>
                </div>

                <form class="p-8 md:p-12 space-y-12 relative z-10" id="recruitment-form">
                    <div class="p-6 bg-slate-50 border-l-4 border-slate-900 mb-10">
                        <p class="text-[11px] leading-relaxed text-slate-600 font-medium">
                            <span class="text-slate-900 font-black uppercase">Note :</span>
                            L'admission au sein de la Joint Task Force Olympus est un privilège. Chaque réponse incomplète, incohérente ou assistée par IA entraîne l'archivage définitif du dossier.
                        </p>
                    </div>

                    <section>
                        <div class="section-title">Section I — Identité & Temps</div>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">01 Nom & Prénom</label>
                                <input type="text" class="input-field track-field" placeholder="ex: Jonathan King">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">02 Âge</label>
                                <input type="number" class="input-field track-field" placeholder="Âge minimum requis">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">03 Fuseau Horaire</label>
                                <input type="text" class="input-field track-field" placeholder="ex: Paris (UTC+1)">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">04 Disponibilités Hebdomadaires</label>
                                <input type="text" class="input-field track-field" placeholder="Jours de la semaine">
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="section-title">Section II — Performance & Background</div>
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">05 Configuration (CPU/GPU/RAM)</label>
                                <input type="text" class="input-field track-field" placeholder="Configuration système complète">
                            </div>

                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black tracking-wider uppercase">06 Microphone de Haute Qualité ?</label>
                                    <select class="input-field bg-white track-field">
                                        <option value="">Sélectionner</option>
                                        <option>Oui</option>
                                        <option>Non</option>
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-[10px] font-black tracking-wider uppercase">08 Maîtrise ACE / ACRE</label>
                                    <select class="input-field bg-white track-field">
                                        <option value="">Sélectionner</option>
                                        <option>Aucune</option>
                                        <option>Basique</option>
                                        <option>Expérimenté</option>
                                        <option>Avancé</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">07 Expériences MilSim Passées</label>
                                <textarea class="input-field h-32 track-field" placeholder="Unités, rôles, durées, motifs de départ..."></textarea>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="section-title">Section III — Intention & Mentalité</div>
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">09 Pourquoi rejoindre la Joint Task Force Olympus ?</label>
                                <textarea class="input-field h-24 track-field" placeholder="Motivation, recherche de structure, exigence, engagement..."></textarea>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] font-black tracking-wider uppercase">10 Qu'est-ce que l'Accountability ?</label>
                                <textarea class="input-field h-24 track-field" placeholder="Définissez la responsabilité individuelle dans une unité hyper-réaliste."></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="bg-slate-50 p-6 rounded-2xl space-y-6 border border-slate-100">
                        <div class="section-title">Section IV — Engagement</div>

                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <span class="text-[11px] font-medium">13 Je comprends l'investissement temps/effort requis</span>
                            <select class="input-field w-full md:w-40 track-field">
                                <option value="">Sélectionner</option>
                                <option>Oui</option>
                                <option>Non</option>
                            </select>
                        </div>

                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <span class="text-[11px] font-medium">15 Disponible mercredi & samedi soir</span>
                            <select class="input-field w-full md:w-40 track-field">
                                <option value="">Sélectionner</option>
                                <option>Oui</option>
                                <option>Non</option>
                                <option>Variable</option>
                            </select>
                        </div>
                    </section>

                    <div class="pt-10 border-t border-slate-100">
                        <div class="flex items-center gap-4 mb-8">
                            <input type="checkbox" id="no-ai-check" class="w-5 h-5 rounded border-slate-300 accent-slate-900 track-field">
                            <label for="no-ai-check" class="text-[10px] font-black tracking-widest uppercase text-slate-500 cursor-pointer">
                                20 Je confirme l'absence d'IA dans ce rapport
                            </label>
                        </div>

                        <button type="submit" class="w-full bg-slate-900 text-white p-6 rounded-2xl font-black tracking-[0.5em] uppercase hover:bg-emerald-600 transition-all duration-500 shadow-xl active:scale-[0.98]">
                            Soumettre au Commandement
                        </button>

                        <p class="text-[8px] text-center mt-4 text-slate-400 tracking-widest uppercase italic">
                            Transmission via JNET COMSPEC // Webhook Discord Active
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function updateClock() {
            const now = new Date();
            const time = now.toISOString().split('T')[1].split('.')[0] + ' Z';
            document.getElementById('clock').innerText = time;
        }

        function updateProgress() {
            const fields = document.querySelectorAll('.track-field');
            let completed = 0;

            fields.forEach(field => {
                if (field.type === 'checkbox') {
                    if (field.checked) completed++;
                } else if (field.value.trim() !== '') {
                    completed++;
                }
            });

            const total = fields.length;
            const percent = Math.round((completed / total) * 100);

            document.getElementById('progress-text').innerText = `FORMULAIRE : ${completed} / ${total} RÉPONSES`;
            document.getElementById('progress-bar').style.width = `${percent}%`;
        }

        function startApp() {
            const preamble = document.getElementById('preamble');
            preamble.style.opacity = '0';
            preamble.style.pointerEvents = 'none';

            setTimeout(() => {
                preamble.classList.add('hidden');
            }, 1000);
        }

        setInterval(updateClock, 1000);
        updateClock();

        document.querySelectorAll('.track-field').forEach(field => {
            field.addEventListener('input', updateProgress);
            field.addEventListener('change', updateProgress);
        });

        updateProgress();
    </script>
</body>
</html>