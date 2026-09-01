<?php
declare(strict_types=1);
$ge = is_array($gameExperience ?? null) ? $gameExperience : [];
$gp = is_array($gameExperiencePreview ?? null) ? $gameExperiencePreview : ['name' => 'ATHENA', 'image' => '', 'message' => ''];
$chk = static function (string $key) use ($ge): string {
    return !empty($ge[$key]) ? 'checked' : '';
};
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$img = trim((string) ($gp['image'] ?? ''));
?>
<div id="overwatch-game-experience" class="mb-8 border border-emerald-200 rounded-xl p-5 bg-emerald-50/30 shadow-sm">
    <h2 class="text-sm font-bold text-emerald-950 mb-1">Expérience en jeu (fenêtre Athena)</h2>
    <p class="text-xs text-emerald-900/80 mb-4 leading-relaxed">
        Personnalisez ce que les opérateurs voient au lancement d’Arma : image de votre communauté, méthodes de connexion, et fonctions Overwatch autorisées.
        Ces réglages sont appliqués automatiquement après connexion — personne n’a à saisir un identifiant de communauté.
    </p>
    <div class="grid lg:grid-cols-2 gap-5">
        <form action="<?= $h($baseUrl) ?>/admin/atak-config/game-experience" method="post" enctype="multipart/form-data" class="space-y-4">
            <?= \App\Core\Csrf::field() ?>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Personnalisation</h3>
                <label class="block text-sm font-medium text-slate-800 mb-1">Nom affiché</label>
                <input type="text" name="game_display_name" maxlength="80" value="<?= $h($ge['display_name'] ?? '') ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="COMSPEC">
                <label class="block text-sm font-medium text-slate-800 mt-3 mb-1">Message d’accueil</label>
                <input type="text" name="game_welcome_message" maxlength="280" value="<?= $h($ge['welcome_message'] ?? '') ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Bienvenue sur l’environnement opérationnel">
                <label class="block text-sm font-medium text-slate-800 mt-3 mb-1">Image de connexion</label>
                <input type="file" name="game_login_image" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600">
                <label class="block text-sm font-medium text-slate-800 mt-3 mb-1">Logo</label>
                <input type="file" name="game_logo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600">
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Authentification</h3>
                <label class="flex items-center gap-2 text-sm text-slate-800"><input type="hidden" name="game_auth_password" value="0"><input type="checkbox" name="game_auth_password" value="1" <?= $chk('auth_password') ?>> E-mail et mot de passe</label>
                <label class="flex items-center gap-2 text-sm text-slate-800 mt-1"><input type="hidden" name="game_auth_otp" value="0"><input type="checkbox" name="game_auth_otp" value="1" <?= $chk('auth_otp') ?>> Code temporaire par e-mail</label>
                <label class="flex items-center gap-2 text-sm text-slate-800 mt-1"><input type="hidden" name="game_auth_steam" value="0"><input type="checkbox" name="game_auth_steam" value="1" <?= $chk('auth_steam') ?>> Connexion avec Steam (poste déjà associé)</label>
                <label class="flex items-center gap-2 text-sm text-slate-800 mt-1"><input type="hidden" name="game_allow_auto_reconnect" value="0"><input type="checkbox" name="game_allow_auto_reconnect" value="1" <?= $chk('allow_auto_reconnect') ?>> Autoriser la reconnexion automatique</label>
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Synchronisation du profil</h3>
                <div class="grid grid-cols-2 gap-1">
                    <?php foreach ([
                        'sync_profile' => 'Profil Athena',
                        'sync_grade' => 'Grade',
                        'sync_unit' => 'Affectation',
                        'sync_callsign' => 'Indicatif',
                        'sync_avatar' => 'Portrait',
                        'sync_clearances' => 'Habilitations',
                        'sync_c2' => 'Configuration carte',
                    ] as $k => $lab): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-800"><input type="hidden" name="game_<?= $h($k) ?>" value="0"><input type="checkbox" name="game_<?= $h($k) ?>" value="1" <?= $chk($k) ?>><?= $h($lab) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Modules Overwatch</h3>
                <div class="grid grid-cols-2 gap-1">
                    <?php foreach ([
                        'bft_enabled' => 'Suivi des positions',
                        'chat_enabled' => 'Messagerie',
                        'intel_enabled' => 'Renseignement',
                        'photos_enabled' => 'Photographies',
                        'markers_enabled' => 'Marqueurs',
                        'jtac_enabled' => 'Appui feu',
                    ] as $k => $lab): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-800"><input type="hidden" name="game_<?= $h($k) ?>" value="0"><input type="checkbox" name="game_<?= $h($k) ?>" value="1" <?= $chk($k) ?>><?= $h($lab) ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="grid grid-cols-3 gap-2 mt-3">
                    <label class="block text-sm">Pack Overwatch minimal
                        <input type="text" name="game_min_mod_version" value="<?= $h($ge['min_mod_version'] ?? '1.5.0') ?>" class="mt-1 w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm" placeholder="1.5.0">
                        <span class="block text-xs text-slate-500 mt-1">Les opérateurs dont le pack est plus ancien voient la version actuelle et celle exigée.</span>
                    </label>
                    <label class="block text-sm">Canal
                        <select name="game_channel" class="mt-1 w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm">
                            <?php foreach (['PROD' => 'Production', 'BETA' => 'Bêta', 'DEV' => 'Atelier'] as $cv => $cl): ?>
                                <option value="<?= $h($cv) ?>" <?= (($ge['channel'] ?? 'PROD') === $cv) ? 'selected' : '' ?>><?= $h($cl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm">Fréquence de position (s)
                        <input type="number" min="2" max="60" name="game_update_interval" value="<?= $h((string) ($ge['update_interval'] ?? 5)) ?>" class="mt-1 w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm">
                    </label>
                </div>
            </div>
            <button type="submit" class="inline-flex px-4 py-2 bg-emerald-800 text-white text-sm font-semibold rounded-lg hover:bg-emerald-900">Enregistrer l’expérience en jeu</button>
        </form>
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Aperçu de la fenêtre Arma</h3>
            <div class="rounded-lg border border-slate-800 bg-[#061018] text-slate-100 shadow-lg overflow-hidden" style="font-family: ui-sans-serif, system-ui;">
                <div class="h-1 bg-emerald-400"></div>
                <div class="px-4 py-2 flex justify-between text-[11px] tracking-wide">
                    <span class="font-semibold">ATHENA</span>
                    <span class="text-emerald-300/80">COMSPEC OVERWATCH</span>
                </div>
                <div class="mx-4 h-24 rounded bg-slate-900/80 flex items-center justify-center overflow-hidden">
                    <?php if ($img !== ''): ?>
                        <img src="<?= $h($img) ?>" alt="" class="max-h-24 object-contain">
                    <?php else: ?>
                        <span class="text-xs text-slate-500">Image de votre communauté</span>
                    <?php endif; ?>
                </div>
                <p class="text-center text-sm font-bold tracking-widest mt-3">CONNEXION À ATHENA</p>
                <p class="text-center text-[11px] text-slate-400 px-4 mt-1"><?= $h($gp['name'] ?? '') ?></p>
                <?php if (trim((string) ($gp['message'] ?? '')) !== ''): ?>
                    <p class="text-center text-[11px] text-slate-500 px-4 mt-1"><?= $h($gp['message']) ?></p>
                <?php endif; ?>
                <div class="mx-8 mt-3 h-7 rounded bg-slate-800"></div>
                <div class="mx-8 mt-2 h-7 rounded bg-slate-800"></div>
                <div class="mx-8 mt-3 mb-4 h-8 rounded bg-emerald-900/80 text-[11px] flex items-center justify-center font-semibold">SE CONNECTER</div>
                <p class="text-center text-[10px] text-slate-500 pb-3">E-mail • Code temporaire • Steam</p>
            </div>
        </div>
    </div>
</div>
