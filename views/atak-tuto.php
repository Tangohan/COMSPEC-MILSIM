<?php
$baseUrl = url('');
?>
<div class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Tutoriel — Mod Arma COMSPEC Overwatch</h1>
    <p class="text-sm text-slate-600 mb-8">Installation et configuration du mod Arma 3 pour la liaison avec l’overlay ATAK / Tacmap.</p>

    <nav class="mb-8 pb-4 border-b border-slate-200">
        <a href="<?= $baseUrl ?>/atak" class="text-slate-600 hover:text-slate-900 text-sm font-medium">← Retour à l’overlay ATAK</a>
        <span class="mx-2 text-slate-400">·</span>
        <a href="<?= $baseUrl ?>/atak/setup" class="text-slate-600 hover:text-slate-900 text-sm font-medium">Assistant Mod Arma</a>
        <span class="mx-2 text-slate-400">·</span>
        <a href="<?= $baseUrl ?>/admin/atak-config" class="text-slate-600 hover:text-slate-900 text-sm font-medium">Configuration ATAK (admin)</a>
    </nav>

    <div class="prose prose-slate max-w-none space-y-10">
        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">1. Prérequis</h2>
            <ul class="list-disc pl-6 text-slate-700 space-y-1 text-sm">
                <li>Arma 3 à jour</li>
                <li><strong>CBA A3</strong> (Community Base Addons) — requis pour les paramètres en jeu</li>
                <li>Optionnel : cTab si vous utilisez des fonctionnalités compatibles (tablette, messagerie)</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">2. Téléchargement</h2>
            <p class="text-slate-700 text-sm mb-2">Récupérez le mod COMSPEC Overwatch au format .zip (release ou livrable de votre équipe).</p>
            <ul class="list-disc pl-6 text-slate-700 space-y-1 text-sm">
                <li>Lien de téléchargement : à fournir par votre administrateur (page <a href="<?= $baseUrl ?>/admin/atak-config" class="text-slate-900 underline">Configuration ATAK</a> ou annonces).</li>
                <li>Le fichier se présente comme <code class="bg-slate-100 px-1 rounded">COMSPEC_Overwatch.zip</code> ou <code class="bg-slate-100 px-1 rounded">@COMSPECOverwatch.zip</code>.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">3. Installation</h2>
            <ol class="list-decimal pl-6 text-slate-700 space-y-2 text-sm">
                <li>Extraire l’archive dans le dossier des mods Arma 3 (souvent <code class="bg-slate-100 px-1 rounded">C:\Program Files (x86)\Steam\steamapps\common\Arma 3</code> ou le répertoire de votre launcher).</li>
                <li>Vous devez obtenir un dossier nommé <code class="bg-slate-100 px-1 rounded">@COMSPECOverwatch</code> (ou le nom indiqué dans l’archive).</li>
                <li>Dans le launcher Arma 3 (ou votre gestionnaire de mods), activer <strong>COMSPEC Overwatch</strong> dans la liste des mods.</li>
                <li>Vérifier que <strong>CBA A3</strong> est bien chargé avant COMSPEC Overwatch.</li>
            </ol>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">4. Configuration</h2>
            <p class="text-slate-700 text-sm mb-2">Une fois en jeu (menu principal ou éditeur), ouvrez les paramètres CBA :</p>
            <ul class="list-disc pl-6 text-slate-700 space-y-1 text-sm">
                <li><strong>URL du serveur</strong> : saisir l’URL de base du nœud ATAK (ex. <code class="bg-slate-100 px-1 rounded">https://votre-domaine.com:3001</code> ou l’URL indiquée par votre admin). Pas de slash final.</li>
                <li><strong>Clé / code</strong> : si votre équipe utilise une clé d’accès, la saisir ici. Cette clé est affichée dans la section « Identifiants / config mod » de la <a href="<?= $baseUrl ?>/admin/atak-config" class="text-slate-900 underline">Configuration ATAK</a> (réservée aux admins).</li>
            </ul>
            <p class="text-slate-700 text-sm mt-3">Les opérateurs peuvent consulter la zone « Configuration pour le jeu » sur la <a href="<?= $baseUrl ?>/atak" class="text-slate-900 underline">page ATAK</a> pour retrouver l’adresse du serveur et les identifiants à coller dans le mod.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">5. Connexion</h2>
            <ol class="list-decimal pl-6 text-slate-700 space-y-2 text-sm">
                <li>Lancer une mission (éditeur, multijoueur, etc.).</li>
                <li>Le mod se connecte automatiquement au nœud ATAK au chargement du jeu.</li>
                <li>Sur l’overlay web (page ATAK), vérifier que le statut affiche « Réseau actif » et que les unités ou marqueurs apparaissent si le serveur en envoie.</li>
                <li>En cas d’échec : vérifier l’URL, la clé, le pare-feu et que le nœud ATAK (serveur Node) est bien démarré.</li>
            </ol>
        </section>

        <section>
            <h2 class="text-lg font-bold text-slate-900 mb-3">6. Fonctions disponibles</h2>
            <p class="text-slate-700 text-sm mb-2">Selon la version du mod et la configuration du serveur :</p>
            <ul class="list-disc pl-6 text-slate-700 space-y-1 text-sm">
                <li><strong>Position</strong> : envoi périodique de la position du joueur vers l’overlay (carte en temps réel).</li>
                <li><strong>Marqueurs</strong> : synchronisation des marqueurs carte (création, modification, suppression) entre le jeu et l’overlay.</li>
                <li><strong>Photos / intel</strong> : envoi de captures (type CTAB) vers l’overlay pour partage avec les opérateurs.</li>
            </ul>
            <p class="text-slate-700 text-sm mt-3">Pour plus de détails, consulter les instructions de votre équipe ou la section « Instructions » dans la <a href="<?= $baseUrl ?>/admin/atak-config" class="text-slate-900 underline">Configuration ATAK</a>.</p>
        </section>
    </div>

    <p class="mt-10 text-sm text-slate-500">
        <a href="<?= $baseUrl ?>/atak" class="text-slate-700 hover:underline font-medium">Ouvrir l’overlay ATAK</a>
        ·
        <a href="<?= $baseUrl ?>/dashboard" class="text-slate-700 hover:underline font-medium">Dashboard</a>
    </p>
</div>
