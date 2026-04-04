<?php
$user = $user ?? [];
$profile = $profile ?? null;
$errors = $errors ?? [];
$success = $success ?? null;
$error = $error ?? null;
$baseUrl = url('');
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-2">Préférences</h1>
    <p class="text-slate-600 mb-6">Nom d'affichage, indicatif, fuseau horaire et langue.</p>
    <?php if ($success): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= url('account/preferences') ?>" class="space-y-4 bg-white border border-slate-200 rounded-lg p-6">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="display_name" class="block text-sm font-medium text-slate-700 mb-1">Nom d'affichage</label>
            <input type="text" name="display_name" id="display_name" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900 focus:border-slate-900" maxlength="100">
            <?php if (!empty($errors['display_name'])): foreach ($errors['display_name'] as $e): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; endif; ?>
        </div>
        <div>
            <label for="callsign" class="block text-sm font-medium text-slate-700 mb-1">Indicatif</label>
            <input type="text" name="callsign" id="callsign" value="<?= htmlspecialchars($user['callsign'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900 focus:border-slate-900" maxlength="50">
            <?php if (!empty($errors['callsign'])): foreach ($errors['callsign'] as $e): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; endif; ?>
        </div>
        <div>
            <label for="profile_slug" class="block text-sm font-medium text-slate-700 mb-1">Identifiant URL fiche personnel (optionnel)</label>
            <input type="text" name="profile_slug" id="profile_slug" value="<?= htmlspecialchars($user['profile_slug'] ?? '') ?>" pattern="[a-z0-9]([a-z0-9-]{0,38}[a-z0-9])?" class="w-full px-3 py-2 border border-slate-300 rounded font-mono lowercase focus:ring-2 focus:ring-slate-900 focus:border-slate-900" maxlength="40" placeholder="ex. jean-dupont">
            <p class="mt-1 text-xs text-slate-500">Lien : <?= htmlspecialchars(rtrim($baseUrl, '/')) ?>/personnel/<em>identifiant</em>. Vide = uniquement l’URL par numéro.</p>
            <?php if (!empty($errors['profile_slug'])): foreach ($errors['profile_slug'] as $e): ?>
            <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; endif; ?>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="steam_id" class="block text-sm font-medium text-slate-700 mb-1">Steam ID (liaison ATAK)</label>
                <input type="text" name="steam_id" id="steam_id" value="<?= htmlspecialchars($user['steam_id'] ?? '') ?>" placeholder="76561198…" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="20">
                <?php if (!empty($errors['steam_id'])): foreach ($errors['steam_id'] as $e): ?>
                <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                <?php endforeach; endif; ?>
            </div>
            <div>
                <label for="arma_callsign" class="block text-sm font-medium text-slate-700 mb-1">Indicatif Arma (liaison ATAK)</label>
                <input type="text" name="arma_callsign" id="arma_callsign" value="<?= htmlspecialchars($profile['arma_callsign'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="100">
                <?php if (!empty($errors['arma_callsign'])): foreach ($errors['arma_callsign'] as $e): ?>
                <p class="mt-1 text-sm text-red-600"><?= htmlspecialchars($e) ?></p>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="first_name" class="block text-sm font-medium text-slate-700 mb-1">Prénom</label>
                <input type="text" name="first_name" id="first_name" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="100">
            </div>
            <div>
                <label for="last_name" class="block text-sm font-medium text-slate-700 mb-1">Nom</label>
                <input type="text" name="last_name" id="last_name" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="100">
            </div>
        </div>
        <div>
            <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Téléphone</label>
            <input type="text" name="phone" id="phone" value="<?= htmlspecialchars($profile['phone'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="50">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="timezone" class="block text-sm font-medium text-slate-700 mb-1">Fuseau horaire</label>
                <input type="text" name="timezone" id="timezone" value="<?= htmlspecialchars($profile['timezone'] ?? 'Europe/Paris') ?>" placeholder="Europe/Paris" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900" maxlength="50">
            </div>
            <div>
                <label for="language" class="block text-sm font-medium text-slate-700 mb-1">Langue</label>
                <select name="language" id="language" class="w-full px-3 py-2 border border-slate-300 rounded focus:ring-2 focus:ring-slate-900">
                    <option value="fr" <?= ($profile['language'] ?? '') === 'fr' ? 'selected' : '' ?>>Français</option>
                    <option value="en" <?= ($profile['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>
        </div>
        <button type="submit" class="py-2.5 px-4 bg-slate-900 text-white font-semibold rounded hover:bg-slate-800">Enregistrer</button>
    </form>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('account') ?>" class="underline">Retour à Paramètres</a></p>
</div>
