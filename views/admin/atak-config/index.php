<?php
$config = $config ?? null;
$atakMaps = $atakMaps ?? [];
$baseUrl = url('');
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Configuration ATAK / Arma</h1>
    <p class="text-sm text-slate-600 mb-6">Paramètres par équipe pour la liaison site ↔ jeu (mod Arma, nœud ATAK, identifiants).</p>

    <?php if ($success): ?>
        <p class="mb-4 text-sm text-green-600"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form action="<?= $baseUrl ?>/admin/atak-config" method="post" class="space-y-6">
        <?= \App\Core\Csrf::field() ?>

        <div class="border border-slate-200 rounded-lg p-4 bg-slate-50/50">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Carte par défaut</h2>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Carte de l’overlay</label>
                <select name="default_map_slug" class="w-full border border-slate-200 rounded px-3 py-2 text-sm">
                    <?php foreach ($atakMaps as $m): ?>
                        <option value="<?= htmlspecialchars($m['slug']) ?>" <?= ($config['default_map_slug'] ?? 'altis') === $m['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 mt-1">Carte affichée sur la page ATAK pour cette équipe.</p>
            </div>
        </div>

        <div class="border border-slate-200 rounded-lg p-4 bg-slate-50/50">
            <h2 class="text-sm font-bold text-slate-800 mb-3">API ATAK (site → overlay)</h2>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">URL de base API ATAK (optionnel)</label>
                <input type="url" name="node_url" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="" value="<?= htmlspecialchars($config['node_url'] ?? '') ?>" />
                <p class="text-xs text-slate-500 mt-1">L’API C2 est fournie par le site PHP (même origine). Laisser vide pour utiliser l’origine courante. Renseigner une URL uniquement pour un domaine dédié (ex. pour la DLL Arma).</p>
                <div class="mt-3 p-3 bg-white border border-slate-200 rounded text-xs text-slate-700">
                    <p class="font-semibold text-slate-800 mb-1">Configuration</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        <li>Le C2 ATAK est géré par l’<strong>API PHP</strong> du site (<code class="bg-slate-100 px-0.5">/api/atak/*</code>, polling).</li>
                        <li>Pour le mod Arma (DLL), configurez l’URL du site (ex. <code class="bg-slate-100 px-0.5">https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'votre-domaine.fr') ?></code>) dans le paramètre « URL Athena » du mod.</li>
                    </ul>
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Secret JWT (optionnel)</label>
                <input type="password" name="jwt_secret" autocomplete="off" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Secret partagé avec le nœud ATAK" value="<?= htmlspecialchars($config['jwt_secret'] ?? '') ?>" />
                <p class="text-xs text-slate-500 mt-1">Si renseigné, les tokens de cette équipe seront signés avec ce secret (sinon JWT_SECRET global).</p>
            </div>
        </div>

        <div class="border border-slate-200 rounded-lg p-4 bg-slate-50/50">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Serveur Arma 3</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Adresse du serveur</label>
                    <input type="text" name="arma_server_host" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="IP ou hostname" value="<?= htmlspecialchars($config['arma_server_host'] ?? '') ?>" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Port</label>
                    <input type="number" name="arma_server_port" min="1" max="65535" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="2302" value="<?= $config['arma_server_port'] ?? '' ?>" />
                </div>
            </div>
        </div>

        <div class="border border-slate-200 rounded-lg p-4 bg-slate-50/50">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Identifiants / liaison mod Arma</h2>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Identifiants ou config mod (texte libre)</label>
                <textarea name="arma_mod_credentials" rows="4" class="w-full border border-slate-200 rounded px-3 py-2 text-sm font-mono" placeholder="Ex: clé API, identifiant serveur, paramètres à coller dans le mod…"><?= htmlspecialchars($config['arma_mod_credentials'] ?? '') ?></textarea>
                <p class="text-xs text-slate-500 mt-1">Affiché aux opérateurs sur la page ATAK pour configurer le mod côté jeu.</p>
            </div>
        </div>

        <div class="border border-slate-200 rounded-lg p-4 bg-slate-50/50">
            <h2 class="text-sm font-bold text-slate-800 mb-3">Instructions équipe</h2>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Instructions (optionnel)</label>
                <textarea name="instructions" rows="3" class="w-full border border-slate-200 rounded px-3 py-2 text-sm" placeholder="Procédure de connexion, liens, rappels…"><?= htmlspecialchars($config['instructions'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Enregistrer</button>
            <a href="<?= $baseUrl ?>/admin/atak-mod" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Mod ATAK (upload)</a>
            <a href="<?= $baseUrl ?>/admin" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Retour admin</a>
            <a href="<?= $baseUrl ?>/atak" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Ouvrir ATAK</a>
            <a href="<?= $baseUrl ?>/atak/tuto" class="px-4 py-2 border border-slate-200 text-slate-700 text-sm rounded hover:bg-slate-50">Tuto mod Arma</a>
        </div>
    </form>
</div>
