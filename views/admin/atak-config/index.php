<?php
$config = $config ?? [];
$atakMaps = $atakMaps ?? [];
$baseUrl = url('');
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
$hostHint = htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'votre-domaine.fr', ENT_QUOTES, 'UTF-8');
$defaultMapSlug = $config['default_map_slug'] ?? 'altis';
?>
<div class="max-w-6xl mx-auto px-6 py-10">
    <header class="mb-8">
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Configuration ATAK / Tacmap</h1>
        <p class="text-sm text-slate-600 mt-2 max-w-3xl">
            Réglages de votre communauté pour la carte tactique web, la liaison avec Arma&nbsp;3 (mod COMSPEC Overwatch) et les informations affichées aux opérateurs.
            Les champs ci-dessous alimentent directement la page Tacmap, l’assistant d’installation et le panneau «&nbsp;Configuration pour le jeu&nbsp;».
        </p>
    </header>

    <?php if ($success): ?>
        <p class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <div class="grid lg:grid-cols-12 gap-8 items-start">
        <div class="lg:col-span-8">
            <form action="<?= $baseUrl ?>/admin/atak-config" method="post" class="space-y-6">
                <?= \App\Core\Csrf::field() ?>

                <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 mb-4">Carte et contexte mission</h2>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Carte affichée par défaut</label>
                        <select name="default_map_slug" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-300 focus:border-slate-400">
                            <?php foreach ($atakMaps as $m): ?>
                                <option value="<?= htmlspecialchars($m['slug']) ?>" <?= $defaultMapSlug === $m['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($m['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-1.5">
                            Carte proposée en premier sur la Tacmap. Les opérateurs peuvent encore changer de carte ou d’espace mission depuis l’en-tête lorsque plusieurs cartes sont disponibles.
                        </p>
                    </div>
                </div>

                <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 mb-4">Liaison temps réel (site ↔ Tacmap ↔ jeu)</h2>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Adresse de base du service de liaison (facultatif)</label>
                        <input type="url" name="node_url" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-300 focus:border-slate-400" placeholder="Laisser vide pour utiliser ce site" value="<?= htmlspecialchars((string) ($config['node_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
                        <p class="text-xs text-slate-500 mt-1.5">
                            En général, laissez ce champ vide&nbsp;: la Tacmap utilise alors le même site que celui que vous consultez. Renseignez une adresse distincte seulement si votre hébergeur impose un domaine ou un port dédié pour le service de liaison utilisé par le mod Arma.
                        </p>
                        <div class="mt-4 p-4 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 space-y-2">
                            <p class="font-semibold text-slate-800">Rappels utiles</p>
                            <ul class="list-disc list-inside space-y-1 leading-relaxed">
                                <li>La Tacmap regroupe carte, marqueurs, unités, messagerie, demandes CAS (9-line), flux visuels depuis le jeu, pings et suivi des appareils.</li>
                                <li>Dans le mod, l’adresse à indiquer correspond au site du portail (ex.&nbsp;<strong>https://<?= $hostHint ?></strong>), sauf consigne contraire de votre équipe technique.</li>
                                <li>Les joueurs disposent d’un <a href="<?= $baseUrl ?>/atak/setup" class="text-emerald-800 underline font-medium">assistant d’installation</a> et d’un <a href="<?= $baseUrl ?>/atak/tuto" class="text-emerald-800 underline font-medium">guide pas à pas</a> pour le mod.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-5">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Secret de signature des accès (facultatif)</label>
                        <input type="password" name="jwt_secret" autocomplete="off" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-slate-300 focus:border-slate-400" placeholder="Identique au secret configuré sur le service de liaison" value="<?= htmlspecialchars($config['jwt_secret'] ?? '') ?>" />
                        <p class="text-xs text-slate-500 mt-1.5">
                            Si vous le renseignez, les accès sécurisés Tacmap de cette communauté sont signés avec ce secret. Sinon, la valeur globale définie sur la plateforme est utilisée.
                        </p>
                    </div>
                </div>

                <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 mb-4">Serveur Arma&nbsp;3 (affichage opérateurs)</h2>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Adresse du serveur</label>
                            <input type="text" name="arma_server_host" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm" placeholder="Nom d’hôte ou adresse" value="<?= htmlspecialchars($config['arma_server_host'] ?? '') ?>" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Port</label>
                            <input type="number" name="arma_server_port" min="1" max="65535" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm" placeholder="2302" value="<?= $config['arma_server_port'] ?? '' ?>" />
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Ces informations sont montrées sur la Tacmap et dans le panneau compte pour orienter les joueurs.</p>
                </div>

                <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 mb-4">Identifiants et texte d’aide mod</h2>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bloc libre (clés, consignes techniques pour le mod)</label>
                        <textarea name="arma_mod_credentials" rows="5" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-mono" placeholder="Ex.&nbsp;: consignes d’accès, rappels de version du modpack, paramètres à recopier dans le jeu…"><?= htmlspecialchars($config['arma_mod_credentials'] ?? '') ?></textarea>
                        <p class="text-xs text-slate-500 mt-1.5">Affiché aux membres sur la Tacmap (section configuration jeu). Rédigez ce que votre équipe doit recopier ou conserver confidentiellement.</p>
                    </div>
                </div>

                <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
                    <h2 class="text-sm font-bold text-slate-800 mb-4">Instructions d’équipe</h2>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Message aux opérateurs (facultatif)</label>
                        <textarea name="instructions" rows="5" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm" placeholder="Ex.&nbsp;: ordre des canaux, règles d’usage du 9-line, lien vers briefing, horaires d’entraînement…"><?= htmlspecialchars($config['instructions'] ?? '') ?></textarea>
                        <p class="text-xs text-slate-500 mt-1.5">Texte visible sur la Tacmap pour toute l’équipe (procédures, liens utiles, rappels).</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="px-5 py-2.5 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800">Enregistrer</button>
                    <a href="<?= $baseUrl ?>/admin/atak-mod" class="px-4 py-2.5 border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50">Fichier du mod (upload)</a>
                    <a href="<?= $baseUrl ?>/atak" class="px-4 py-2.5 border border-emerald-200 text-emerald-900 text-sm font-medium rounded-lg bg-emerald-50/80 hover:bg-emerald-50">Ouvrir la Tacmap</a>
                    <a href="<?= $baseUrl ?>/admin" class="px-4 py-2.5 border border-slate-200 text-slate-600 text-sm rounded-lg hover:bg-slate-50">Retour administration</a>
                </div>
            </form>
        </div>

        <aside class="lg:col-span-4 space-y-5 lg:sticky lg:top-6">
            <div class="border border-slate-200 rounded-xl p-5 bg-slate-50/80">
                <h2 class="text-sm font-bold text-slate-900 mb-3">Ce que couvre la Tacmap aujourd’hui</h2>
                <p class="text-xs text-slate-600 mb-3 leading-relaxed">
                    Outre la carte et les marqueurs synchronisés avec le jeu, les membres ont accès aux modules suivants (selon les droits et l’activation de l’offre)&nbsp;:
                </p>
                <ul class="text-xs text-slate-700 space-y-2 list-disc list-inside leading-relaxed">
                    <li><span class="font-medium text-slate-800">Cams &amp; renseignement visuel</span> — photos CTAB et flux associés depuis Arma.</li>
                    <li><span class="font-medium text-slate-800">Messagerie tactique</span> — échanges en direct entre opérateurs connectés.</li>
                    <li><span class="font-medium text-slate-800">Pings</span> — signalements sur la carte.</li>
                    <li><span class="font-medium text-slate-800">JTAC / 9-line</span> — saisie et suivi des demandes d’appui aérien.</li>
                    <li><span class="font-medium text-slate-800">Unités et filtres</span> — présence des joueurs et contacts liés aux espaces mission.</li>
                    <li><span class="font-medium text-slate-800">Appui aérien</span> — manifeste de vol déclaré côté jeu.</li>
                    <li><span class="font-medium text-slate-800">Liaison Arma</span> — position et événements relayés via le mod COMSPEC Overwatch.</li>
                    <li><span class="font-medium text-slate-800">Diagnostic</span> — panneau d’état (connexion, base de données, charge utile mod).</li>
                </ul>
            </div>

            <div class="border border-emerald-200 rounded-xl p-5 bg-emerald-50/40">
                <h2 class="text-sm font-bold text-emerald-950 mb-2">Navigation rapide</h2>
                <ul class="text-sm space-y-2">
                    <li><a href="<?= $baseUrl ?>/overwatch" class="text-emerald-900 underline font-medium hover:no-underline">Vue C2 Overwatch</a></li>
                    <li><a href="<?= $baseUrl ?>/atak/setup" class="text-emerald-900 underline font-medium hover:no-underline">Assistant mod Arma</a></li>
                    <li><a href="<?= $baseUrl ?>/atak/tuto" class="text-emerald-900 underline font-medium hover:no-underline">Tutoriel mod détaillé</a></li>
                    <li><a href="<?= $baseUrl ?>/dashboard" class="text-emerald-900 underline font-medium hover:no-underline">Tableau de bord</a></li>
                </ul>
            </div>

            <div class="border border-slate-200 rounded-xl p-4 bg-white text-xs text-slate-600 leading-relaxed">
                <p class="font-semibold text-slate-800 mb-1">Fichier du mod</p>
                <p>Pour proposer un téléchargement direct du mod depuis le portail et le tableau de bord, déposez l’archive sur l’écran <a href="<?= $baseUrl ?>/admin/atak-mod" class="text-slate-900 underline">Mod ATAK (upload)</a>.</p>
            </div>
        </aside>
    </div>
</div>
