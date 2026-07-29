<?php
$config = $config ?? [];
$atakMaps = $atakMaps ?? [];
$baseUrl = url('');
$success = \App\Core\Session::getFlash('success');
$error = \App\Core\Session::getFlash('error');
$hostHint = htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'votre-domaine.fr', ENT_QUOTES, 'UTF-8');
$defaultMapSlug = $config['default_map_slug'] ?? 'altis';
$tenantId = (int) ($tenantId ?? 0);
$platformKeyConfigured = !empty($platformKeyConfigured);
$accessKeyPrefix = (string) ($accessKeyPrefix ?? '');
$accessKeyGeneratedAt = $accessKeyGeneratedAt ?? null;
$hasTenantAccessKey = !empty($hasTenantAccessKey);
$newAccessKeyPlain = is_string($newAccessKeyPlain ?? null) ? $newAccessKeyPlain : null;
$authEvents = is_array($authEvents ?? null) ? $authEvents : [];
$portalBaseUrl = (string) ($portalBaseUrl ?? rtrim(url(''), '/'));
$dataSummary = is_array($dataSummary ?? null) ? $dataSummary : [];
$maintenanceSchemaReady = !empty($maintenanceSchemaReady);
$maintenanceEnabled = !empty($maintenanceEnabled);
$maintenanceMessage = (string) ($maintenanceMessage ?? '');
$purgeConfirmPhrase = (string) ($purgeConfirmPhrase ?? 'EFFACER');
$activityEventsCount = (int) ($dataSummary['activity_events'] ?? 0);
$missionRowsCount = 0;
foreach ($dataSummary as $k => $v) {
    if ($k === 'activity_events') {
        continue;
    }
    $missionRowsCount += (int) $v;
}
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

    <?php if ($maintenanceEnabled): ?>
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-5 py-4">
            <p class="text-sm font-bold text-amber-950">Mode maintenance actif</p>
            <p class="text-xs text-amber-900 mt-1 leading-relaxed">
                La carte tactique et la liaison jeu sont indisponibles pour les opérateurs. Vous pouvez toujours gérer cette page.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($newAccessKeyPlain): ?>
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-5 py-4">
            <p class="text-sm font-bold text-amber-950 mb-1">Nouvelle clé d’accès — à copier immédiatement</p>
            <p class="text-xs text-amber-900 mb-3 leading-relaxed">
                Cette valeur ne sera plus affichée en entier après avoir quitté cette page. Communiquez-la uniquement aux opérateurs autorisés (paramètres du mod Overwatch, ou via la liaison «&nbsp;Connexion en jeu&nbsp;» qui la récupère automatiquement).
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <code id="atak-new-access-key" class="flex-1 min-w-[12rem] rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm font-mono text-slate-900 break-all"><?= htmlspecialchars($newAccessKeyPlain) ?></code>
                <button type="button" class="px-3 py-2 text-sm font-semibold rounded-lg bg-amber-900 text-white hover:bg-amber-800" onclick="navigator.clipboard.writeText(document.getElementById('atak-new-access-key').textContent); this.textContent='Copié';">Copier</button>
            </div>
        </div>
    <?php endif; ?>

    <a href="<?= htmlspecialchars(url('back-office/atak/briefing-slides'), ENT_QUOTES, 'UTF-8') ?>" class="mb-8 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/40">
        <span>
            <span class="block text-sm font-bold text-slate-900">Diapositives de briefing tactique</span>
            <span class="block text-xs text-slate-500">Publier les images du briefing pour Arma (« Tableau de briefing » ou écran Eden) — ordre, aperçu et aides d’intégration.</span>
        </span>
        <span class="text-sm font-bold text-emerald-700">Ouvrir →</span>
    </a>

    <?php
    $bridgeModules = is_array($bridgeModules ?? null) ? $bridgeModules : [];
    $bridgeModulesUpdatedAt = (string) ($bridgeModulesUpdatedAt ?? '');
    $experienceCatalog = is_array($experienceCatalog ?? null) ? $experienceCatalog : [];
    $experienceGuide = (string) ($experienceGuide ?? '');
    $experienceGuideCustom = (string) ($experienceGuideCustom ?? '');
    $experienceUpdatedAt = (string) ($experienceUpdatedAt ?? '');
    $experienceSchemaReady = !empty($experienceSchemaReady);
    ?>
    <div id="bridge-modules" class="mb-8 border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
        <h2 class="text-sm font-bold text-slate-800 mb-1">Modules ATAK Enhanced / cTab</h2>
        <p class="text-xs text-slate-500 mb-4 leading-relaxed">
            Chaque fonctionnalité partagée entre le jeu et la carte tactique peut être activée ou désactivée pour votre communauté.
            Les opérateurs en liaison voient l’état des modules et un journal des données dans la tablette Athena et l’application Athena du cTab.
        </p>
        <?php if ($bridgeModulesUpdatedAt !== ''): ?>
            <p class="text-xs text-slate-400 mb-3">Dernière mise à jour : <?= htmlspecialchars($bridgeModulesUpdatedAt, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form action="<?= $baseUrl ?>/admin/atak-config/modules" method="post" class="space-y-3">
            <?= \App\Core\Csrf::field() ?>
            <div class="grid sm:grid-cols-2 gap-3">
                <?php foreach ($bridgeModules as $mod): ?>
                    <?php
                    $mid = (string) ($mod['id'] ?? '');
                    $mlabel = (string) ($mod['label'] ?? $mid);
                    $mdesc = (string) ($mod['description'] ?? '');
                    $menabled = !empty($mod['enabled']);
                    if ($mid === '') {
                        continue;
                    }
                    ?>
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-3 cursor-pointer hover:border-emerald-300">
                        <input type="hidden" name="module_<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>" value="0" />
                        <input type="checkbox" name="module_<?= htmlspecialchars($mid, ENT_QUOTES, 'UTF-8') ?>" value="1" class="mt-1 rounded border-slate-300" <?= $menabled ? 'checked' : '' ?> />
                        <span>
                            <span class="block text-sm font-medium text-slate-800"><?= htmlspecialchars($mlabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($mdesc !== ''): ?>
                                <span class="block text-xs text-slate-500 mt-0.5 leading-relaxed"><?= htmlspecialchars($mdesc, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="pt-2">
                <button type="submit" class="inline-flex px-4 py-2 bg-emerald-700 text-white text-sm font-semibold rounded-lg hover:bg-emerald-800">
                    Enregistrer les modules
                </button>
            </div>
        </form>
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50/70 p-4">
            <h3 class="text-sm font-bold text-amber-950 mb-1">Portail de renseignement classifié</h3>
            <p class="text-xs text-amber-900/90 leading-relaxed mb-3">
                Dossiers d’affaire, personnes, croisements et codes d’accès temporaires.
                Le commandement entre <strong>sans code</strong> pour délivrer les accès aux opérateurs.
            </p>
            <div class="flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars(url('back-office/renseignement/codes'), ENT_QUOTES, 'UTF-8') ?>"
                   class="inline-flex px-4 py-2 bg-amber-800 text-white text-sm font-semibold rounded-lg hover:bg-amber-900">
                    Délivrer des codes d’accès
                </a>
                <a href="<?= htmlspecialchars(url('atak/sse'), ENT_QUOTES, 'UTF-8') ?>"
                   class="inline-flex px-4 py-2 bg-white border border-amber-300 text-amber-950 text-sm font-semibold rounded-lg hover:bg-amber-50">
                    Ouvrir le portail
                </a>
            </div>
        </div>
    </div>

    <div class="mb-8 border border-violet-200 rounded-xl p-5 bg-violet-50/30 shadow-sm">
        <h2 class="text-sm font-bold text-violet-950 mb-1">Expérience en jeu (réalisme, troll, personnalisation)</h2>
        <p class="text-xs text-violet-900/80 mb-4 leading-relaxed">
            Définissez le profil d’expérience pour votre communauté. Les opérateurs en liaison reçoivent ces réglages automatiquement
            (en complément de leurs options personnelles lorsque vous laissez le choix).
            Un guide de configuration est aussi affiché en mission lors de la première liaison.
        </p>
        <?php if ($experienceUpdatedAt !== ''): ?>
            <p class="text-xs text-slate-400 mb-3">Dernière mise à jour : <?= htmlspecialchars($experienceUpdatedAt, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if (!$experienceSchemaReady): ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-sm font-semibold text-red-950">Fonction indisponible pour le moment</p>
                <p class="text-xs text-red-900 mt-1 leading-relaxed">
                    La base de données n’est pas à jour pour l’expérience Overwatch. Contactez le support plateforme pour appliquer la mise à jour, puis réessayez.
                </p>
            </div>
        <?php else: ?>
        <form action="<?= $baseUrl ?>/admin/atak-config/experience" method="post" class="space-y-4" id="atak-experience-form">
            <?= \App\Core\Csrf::field() ?>
            <div class="grid sm:grid-cols-2 gap-3">
                <?php foreach ($experienceCatalog as $exp): ?>
                    <?php
                    $eid = (string) ($exp['id'] ?? '');
                    $elabel = (string) ($exp['label'] ?? $eid);
                    $edesc = (string) ($exp['description'] ?? '');
                    $etype = (string) ($exp['type'] ?? 'bool');
                    $eval = $exp['value'] ?? false;
                    if ($eid === '') {
                        continue;
                    }
                    ?>
                    <?php if ($etype === 'tri'): ?>
                        <div class="rounded-lg border border-slate-200 bg-white px-3 py-3">
                            <label class="block text-sm font-medium text-slate-800 mb-1"><?= htmlspecialchars($elabel, ENT_QUOTES, 'UTF-8') ?></label>
                            <?php if ($edesc !== ''): ?>
                                <p class="text-xs text-slate-500 mb-2 leading-relaxed"><?= htmlspecialchars($edesc, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                            <select name="experience_<?= htmlspecialchars($eid, ENT_QUOTES, 'UTF-8') ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                                <?php foreach (($exp['choices'] ?? []) as $ch): ?>
                                    <?php $cv = (string) ($ch['value'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($cv, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $eval === $cv ? 'selected' : '' ?>><?= htmlspecialchars((string) ($ch['label'] ?? $cv), ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <?php $echecked = !empty($eval); ?>
                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3 cursor-pointer hover:border-violet-300 experience-bool-row" data-exp-id="<?= htmlspecialchars($eid, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="experience_<?= htmlspecialchars($eid, ENT_QUOTES, 'UTF-8') ?>" value="0" />
                            <input type="checkbox" name="experience_<?= htmlspecialchars($eid, ENT_QUOTES, 'UTF-8') ?>" value="1" class="mt-1 rounded border-slate-300 experience-bool-cb" <?= $echecked ? 'checked' : '' ?> />
                            <span>
                                <span class="block text-sm font-medium text-slate-800"><?= htmlspecialchars($elabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($edesc !== ''): ?>
                                    <span class="block text-xs text-slate-500 mt-0.5 leading-relaxed"><?= htmlspecialchars($edesc, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Consignes supplémentaires pour le guide en jeu (facultatif)</label>
                <textarea name="experience_guide_custom" rows="4" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm" placeholder="Ex. : canaux Teamspeak, version du modpack, rappel procédure 9-line…"><?= htmlspecialchars($experienceGuideCustom) ?></textarea>
                <p class="text-xs text-slate-500 mt-1.5">Ce texte est ajouté au guide affiché aux opérateurs en mission (journal de bord et panneau d’aide).</p>
            </div>
            <div class="pt-1 flex flex-wrap gap-2">
                <button type="submit" class="inline-flex px-4 py-2 bg-violet-800 text-white text-sm font-semibold rounded-lg hover:bg-violet-900">
                    Enregistrer l’expérience
                </button>
            </div>
        </form>
        <script>
        (function () {
            var form = document.getElementById('atak-experience-form');
            if (!form) return;
            var realism = form.querySelector('.experience-bool-cb[name="experience_realism"]');
            var troll = form.querySelector('.experience-bool-cb[name="experience_troll"]');
            if (!realism || !troll) return;
            function syncExclusive() {
                if (realism.checked) { troll.checked = false; troll.disabled = true; }
                else { troll.disabled = false; }
                if (troll.checked) { realism.checked = false; realism.disabled = true; }
                else { realism.disabled = false; }
            }
            realism.addEventListener('change', syncExclusive);
            troll.addEventListener('change', syncExclusive);
            syncExclusive();
        })();
        </script>
        <?php endif; ?>

        <?php if ($experienceGuide !== ''): ?>
        <details class="mt-5 rounded-lg border border-violet-200 bg-white">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-violet-950">Aperçu du guide en jeu (copiable)</summary>
            <div class="px-4 pb-4">
                <p class="text-xs text-slate-500 mb-2">Texte transmis aux opérateurs lors de la liaison. Vous pouvez le copier pour un briefing écrit.</p>
                <pre class="text-xs text-slate-800 bg-slate-50 border border-slate-200 rounded-lg p-3 whitespace-pre-wrap leading-relaxed max-h-80 overflow-y-auto"><?= htmlspecialchars($experienceGuide) ?></pre>
            </div>
        </details>
        <?php endif; ?>
    </div>

    <div class="grid lg:grid-cols-12 gap-8 items-start">
        <div class="lg:col-span-8 space-y-6">
            <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
                <h2 class="text-sm font-bold text-slate-800 mb-3">Mode maintenance</h2>
                <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                    Pendant la maintenance, les opérateurs ne peuvent plus ouvrir la carte tactique ni synchroniser le jeu.
                    Les administrateurs gardent l’accès à la carte et à cette page de configuration.
                </p>
                <?php if (!$maintenanceSchemaReady): ?>
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                        <p class="text-sm font-semibold text-red-950">Fonction indisponible pour le moment</p>
                        <p class="text-xs text-red-900 mt-1 leading-relaxed">
                            La mise à jour qui active le mode maintenance de la carte n’a pas encore été appliquée sur ce serveur.
                            Demandez au support plateforme de lancer la mise à jour de la base, puis réessayez.
                        </p>
                    </div>
                <?php else: ?>
                <form action="<?= $baseUrl ?>/admin/atak-config/maintenance" method="post" class="space-y-4">
                    <?= \App\Core\Csrf::field() ?>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="hidden" name="maintenance_enabled" value="0" />
                        <input type="checkbox" name="maintenance_enabled" value="1" class="mt-1 rounded border-slate-300" <?= $maintenanceEnabled ? 'checked' : '' ?> />
                        <span>
                            <span class="block text-sm font-medium text-slate-800">Mettre la carte tactique en maintenance</span>
                            <span class="block text-xs text-slate-500 mt-0.5">Les opérateurs verront un message d’indisponibilité. Vous (admin) pourrez toujours ouvrir la carte.</span>
                        </span>
                    </label>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Message affiché aux opérateurs (facultatif)</label>
                        <textarea name="maintenance_message" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm" placeholder="Ex. : Intervention prévue jusqu’à 21h00 Zulu. Reprenez contact avec l’état-major si besoin."><?= htmlspecialchars($maintenanceMessage) ?></textarea>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded-lg hover:bg-slate-800">Enregistrer le mode maintenance</button>
                </form>
                <?php endif; ?>
            </div>

            <div class="border border-red-200 rounded-xl p-5 bg-red-50/40 shadow-sm">
                <h2 class="text-sm font-bold text-red-950 mb-3">Journal et données de mission</h2>
                <p class="text-xs text-red-900/80 mb-3 leading-relaxed">
                    Vous pouvez exporter une copie complète (journal d’activité, unités, messages, ordres, pings, photos, tracés…) puis, si besoin, tout effacer pour repartir à zéro.
                    La configuration (clé d’accès, serveur, consignes) et les indicatifs liés aux comptes sont conservés.
                </p>
                <p class="text-xs text-slate-700 mb-4">
                    État actuel&nbsp;:
                    <span class="font-semibold"><?= $activityEventsCount ?></span> entrée(s) de journal,
                    <span class="font-semibold"><?= $missionRowsCount ?></span> enregistrement(s) de mission.
                </p>
                <div class="flex flex-wrap gap-2 mb-5">
                    <a href="<?= $baseUrl ?>/admin/atak-config/export" class="inline-flex px-4 py-2 bg-white border border-slate-200 text-slate-800 text-sm font-semibold rounded-lg hover:bg-slate-50">
                        Exporter tout (fichier)
                    </a>
                </div>
                <form action="<?= $baseUrl ?>/admin/atak-config/purge" method="post" class="rounded-lg border border-red-200 bg-white p-4 space-y-3" onsubmit="return confirm('Confirmer l’effacement définitif du journal et des données de mission de cette communauté&nbsp;? Cette action est irréversible.');">
                    <?= \App\Core\Csrf::field() ?>
                    <p class="text-sm font-medium text-slate-800">Effacer définitivement</p>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Pour confirmer, saisissez <strong class="font-mono tracking-wide"><?= htmlspecialchars($purgeConfirmPhrase) ?></strong> ci-dessous.
                        Nous recommandons d’exporter avant.
                    </p>
                    <input type="text" name="confirm_phrase" autocomplete="off" class="w-full border border-red-200 rounded-lg px-3 py-2.5 text-sm font-mono" placeholder="<?= htmlspecialchars($purgeConfirmPhrase) ?>" />
                    <button type="submit" class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-700">
                        Tout effacer
                    </button>
                </form>
            </div>

            <div class="border border-emerald-200 rounded-xl p-5 bg-emerald-50/40 shadow-sm">
                <h2 class="text-sm font-bold text-emerald-950 mb-4">Accès mod Overwatch (Arma&nbsp;3)</h2>
                <p class="text-xs text-emerald-900/80 mb-4 leading-relaxed">
                    Ces informations permettent au mod de se connecter à votre communauté. Les joueurs peuvent aussi obtenir automatiquement l’adresse et la clé via «&nbsp;Connexion en jeu&nbsp;» (code ou Steam) sur la Tacmap.
                </p>
                <div class="grid sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Identifiant de communauté</label>
                        <div class="flex gap-2">
                            <input type="text" readonly id="atak-tenant-id" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm bg-white font-mono" value="<?= (int) $tenantId ?>" />
                            <button type="button" class="shrink-0 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-200 bg-white hover:bg-slate-50" onclick="navigator.clipboard.writeText(document.getElementById('atak-tenant-id').value); this.textContent='OK';">Copier</button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5">À indiquer dans les options du mod si votre plateforme héberge plusieurs communautés.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Adresse du portail (mod)</label>
                        <div class="flex gap-2">
                            <input type="text" readonly id="atak-portal-url" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm bg-white font-mono" value="<?= htmlspecialchars($portalBaseUrl, ENT_QUOTES, 'UTF-8') ?>" />
                            <button type="button" class="shrink-0 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-200 bg-white hover:bg-slate-50" onclick="navigator.clipboard.writeText(document.getElementById('atak-portal-url').value); this.textContent='OK';">Copier</button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5">Sans slash final. Exemple&nbsp;: https://<?= $hostHint ?>/public</p>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 mb-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-800">Clé d’accès</p>
                            <?php if ($hasTenantAccessKey): ?>
                                <p class="text-xs text-slate-600 mt-1">
                                    Clé active&nbsp;: <span class="font-mono"><?= htmlspecialchars($accessKeyPrefix !== '' ? $accessKeyPrefix . '…' : '••••••••') ?></span>
                                    <?php if ($accessKeyGeneratedAt): ?>
                                        <span class="text-slate-400">— générée le <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $accessKeyGeneratedAt))) ?></span>
                                    <?php endif; ?>
                                </p>
                            <?php elseif ($platformKeyConfigured): ?>
                                <p class="text-xs text-slate-600 mt-1">Aucune clé spécifique à votre communauté&nbsp;: la clé plateforme est utilisée pour le moment. Générez une clé dédiée pour isoler l’accès de votre communauté.</p>
                            <?php else: ?>
                                <p class="text-xs text-amber-800 mt-1">Aucune clé configurée. Générez-en une pour activer la liaison jeu et la connexion téléphone.</p>
                            <?php endif; ?>
                        </div>
                        <form action="<?= $baseUrl ?>/admin/atak-config/access-key" method="post" onsubmit="return confirm('Générer une nouvelle clé&nbsp;? L’ancienne ne fonctionnera plus pour le mod tant que les joueurs n’auront pas relancé une liaison.');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="px-4 py-2 bg-emerald-800 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700">
                                <?= $hasTenantAccessKey ? 'Régénérer la clé' : 'Générer une clé d’accès' ?>
                            </button>
                        </form>
                    </div>
                </div>
                <ul class="text-xs text-slate-700 space-y-1.5 list-disc list-inside leading-relaxed">
                    <li>Après génération, les joueurs peuvent utiliser <strong>Connexion en jeu</strong> sur la Tacmap (code) — la clé est transmise automatiquement.</li>
                    <li>Sinon, coller manuellement l’adresse + la clé (+ identifiant de communauté) dans les options du mod.</li>
                    <li>La connexion téléphone (QR) nécessite une liaison déjà établie en jeu.</li>
                </ul>
            </div>

            <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
                <h2 class="text-sm font-bold text-slate-800 mb-3">Tentatives de connexion récentes</h2>
                <p class="text-xs text-slate-500 mb-3">Liaisons en jeu, refus de clé, et connexions téléphone. Les secrets ne sont jamais affichés.</p>
                <?php
                $shownAuth = 0;
                foreach ($authEvents as $ev) {
                    $type = (string) ($ev['type'] ?? '');
                    if (!in_array($type, ['auth', 'phone', 'client_init'], true)) {
                        continue;
                    }
                    $shownAuth++;
                }
                ?>
                <?php if ($shownAuth === 0): ?>
                    <p class="text-sm text-slate-500">Aucune tentative enregistrée pour le moment.</p>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100 text-sm">
                        <?php foreach ($authEvents as $ev): ?>
                            <?php
                            $type = (string) ($ev['type'] ?? '');
                            if (!in_array($type, ['auth', 'phone', 'client_init'], true)) {
                                continue;
                            }
                            $ok = true;
                            if ($type === 'auth') {
                                if (isset($ev['meta']['ok'])) {
                                    $ok = (bool) $ev['meta']['ok'];
                                } elseif (isset($ev['meta']['reason']) && !in_array((string) $ev['meta']['reason'], ['ok', 'key_regenerated'], true)) {
                                    $ok = false;
                                }
                            }
                            $at = (string) ($ev['at'] ?? '');
                            $atLabel = $at !== '' ? date('d/m H:i', strtotime($at)) : '—';
                            ?>
                            <li class="py-2.5 flex gap-3 items-start">
                                <span class="mt-0.5 inline-block w-2 h-2 rounded-full shrink-0 <?= $ok ? 'bg-emerald-500' : 'bg-red-500' ?>" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-slate-800"><?= htmlspecialchars((string) ($ev['label'] ?? '')) ?></p>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        <?= htmlspecialchars($atLabel) ?>
                                        <?php if (!empty($ev['actor'])): ?>
                                            — <?= htmlspecialchars((string) $ev['actor']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="text-xs text-slate-500 mt-3">
                    Le détail live est aussi visible sur la <a href="<?= $baseUrl ?>/atak" class="underline font-medium text-slate-700">Tacmap</a> (panneau Activité de liaison).
                </p>
            </div>

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
                    <h2 class="text-sm font-bold text-slate-800 mb-4">Texte d’aide pour le mod</h2>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Consignes affichées aux opérateurs</label>
                        <textarea name="arma_mod_credentials" rows="5" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm" placeholder="Ex.&nbsp;: version du modpack, canaux radio, rappels d’équipe…"><?= htmlspecialchars($config['arma_mod_credentials'] ?? '') ?></textarea>
                        <p class="text-xs text-slate-500 mt-1.5">Affiché aux membres sur la Tacmap (section configuration jeu). Ne collez pas de secrets ici — utilisez la section «&nbsp;Clé d’accès&nbsp;» ci-dessus.</p>
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
                    <a href="<?= $baseUrl ?>/admin/atak-mod-blocks" class="px-4 py-2.5 border border-rose-200 bg-rose-50/70 text-rose-950 text-sm font-medium rounded-lg hover:bg-rose-100">Restrictions d’accès au mod</a>
                    <a href="<?= $baseUrl ?>/admin/atak-beta" class="px-4 py-2.5 border border-amber-200 bg-amber-50/70 text-amber-950 text-sm font-medium rounded-lg hover:bg-amber-100">Accès anticipé</a>
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
                    <li><span class="font-medium text-slate-800">Cams &amp; renseignement visuel</span> — photos terrain, aperçus caméra casque et drone depuis Arma.</li>
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
                    <li><a href="<?= $baseUrl ?>/back-office/atak/fire-teams" class="text-emerald-900 underline font-medium hover:no-underline">Équipes de feu</a></li>
                    <li><a href="<?= $baseUrl ?>/back-office/atak/briefing-slides" class="text-emerald-900 underline font-medium hover:no-underline">Diapositives de briefing</a></li>
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
