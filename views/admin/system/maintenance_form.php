<?php

declare(strict_types=1);

$r = $maintenanceRule;
$row = $r ?? [];
$isEdit = $r !== null;
$action = $formAction ?? url('admin/maintenance');
$dt = static function (?string $v): string {
    if ($v === null || $v === '') {
        return '';
    }

    return htmlspecialchars(str_replace(' ', 'T', substr($v, 0, 16)), ENT_QUOTES, 'UTF-8');
};

$scopeRaw = trim((string) ($row['scope'] ?? 'global'));
$scopeType = 'custom';
$scopeRoute = '';
$scopeModule = '';
$scopeCustom = '';
if ($scopeRaw === '' || $scopeRaw === 'global') {
    $scopeType = 'global';
} elseif (str_starts_with($scopeRaw, 'route:')) {
    $scopeType = 'route';
    $scopeRoute = substr($scopeRaw, strlen('route:'));
} elseif (str_starts_with($scopeRaw, 'module:')) {
    $scopeType = 'module';
    $scopeModule = substr($scopeRaw, strlen('module:'));
} else {
    $scopeCustom = $scopeRaw;
}
?>
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:py-12">
    <div class="mb-8">
        <a href="<?= url('admin/maintenance') ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-900">
            <span aria-hidden="true">←</span> Retour à la liste
        </a>
        <h1 class="mt-4 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl"><?= $isEdit ? 'Modifier la règle' : 'Nouvelle règle de maintenance' ?></h1>
        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
            Une règle définit <strong>qui voit la page de maintenance</strong> (visiteurs bloqués) et <strong>sur quelle partie du site</strong> elle s’applique.
            Les administrateurs système peuvent souvent contourner selon les options ci-dessous.
        </p>
    </div>

    <div class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50/80 p-5 text-sm text-emerald-950 shadow-sm">
        <p class="font-bold text-emerald-900">Avant de publier</p>
        <ul class="mt-2 list-inside list-disc space-y-1.5 text-emerald-900/90">
            <li><strong>Portée</strong> : « Tout le site » affecte l’ensemble du portail ; les modes chemin ou module limitent la zone.</li>
            <li><strong>Activée</strong> : tant que la case n’est pas cochée, la règle est enregistrée mais <strong>sans effet</strong> (brouillon).</li>
            <li><strong>Dates</strong> : laissées vides = pas de fenêtre automatique ; la règle dépend seulement de « Activée ».</li>
        </ul>
    </div>

    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="space-y-8" id="maintenance-form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="scope" id="maintenance-scope-hidden" value="<?= htmlspecialchars($scopeRaw === '' ? 'global' : $scopeRaw, ENT_QUOTES, 'UTF-8') ?>">

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="sec-scope">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Étape 1</p>
                    <h2 id="sec-scope" class="text-lg font-black text-slate-900">Où appliquer la maintenance ?</h2>
                    <p class="mt-1 text-sm text-slate-600">Définissez la zone concernée. La valeur technique <code class="rounded bg-slate-100 px-1 text-xs">scope</code> est générée automatiquement.</p>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <fieldset>
                    <legend class="sr-only">Type de portée</legend>
                    <div class="space-y-3">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-emerald-300 hover:bg-slate-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50">
                            <input type="radio" name="scope_mode" value="global" class="mt-1 text-emerald-600" <?= $scopeType === 'global' ? 'checked' : '' ?>>
                            <span>
                                <span class="block font-bold text-slate-900">Tout le site</span>
                                <span class="mt-0.5 block text-sm text-slate-600">Équivalent <code class="text-xs">global</code> — page de maintenance pour les visiteurs sur l’ensemble du portail.</span>
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-emerald-300 hover:bg-slate-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50">
                            <input type="radio" name="scope_mode" value="route" class="mt-1 text-emerald-600" <?= $scopeType === 'route' ? 'checked' : '' ?>>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-slate-900">Un chemin d’URL (préfixe)</span>
                                <span class="mt-0.5 block text-sm text-slate-600">Ex. tout ce qui commence par <code class="text-xs">/forum</code> — utile pour isoler une section.</span>
                                <input type="text" id="scope-route-input" autocomplete="off" placeholder="/forum" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm <?= $scopeType !== 'route' ? 'opacity-50' : '' ?>"
                                       value="<?= htmlspecialchars($scopeRoute, ENT_QUOTES, 'UTF-8') ?>"
                                       aria-label="Chemin pour la portée route">
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-emerald-300 hover:bg-slate-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50">
                            <input type="radio" name="scope_mode" value="module" class="mt-1 text-emerald-600" <?= $scopeType === 'module' ? 'checked' : '' ?>>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-slate-900">Un module applicatif</span>
                                <span class="mt-0.5 block text-sm text-slate-600">Identifiant logique (ex. <code class="text-xs">forum</code>, <code class="text-xs">atak</code>) — selon ce que l’application expose au garde maintenance.</span>
                                <input type="text" id="scope-module-input" autocomplete="off" placeholder="forum" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm <?= $scopeType !== 'module' ? 'opacity-50' : '' ?>"
                                       value="<?= htmlspecialchars($scopeModule, ENT_QUOTES, 'UTF-8') ?>"
                                       aria-label="Nom du module">
                            </span>
                        </label>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-emerald-300 hover:bg-slate-50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50">
                            <input type="radio" name="scope_mode" value="custom" class="mt-1 text-emerald-600" <?= $scopeType === 'custom' ? 'checked' : '' ?>>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-slate-900">Personnalisé (expert)</span>
                                <span class="mt-0.5 block text-sm text-slate-600">Saisie libre de la valeur <code class="text-xs">scope</code> si vous connaissez le format exact.</span>
                                <input type="text" id="scope-custom-input" autocomplete="off" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm <?= $scopeType !== 'custom' ? 'opacity-50' : '' ?>"
                                       value="<?= htmlspecialchars($scopeCustom, ENT_QUOTES, 'UTF-8') ?>"
                                       aria-label="Scope personnalisé">
                            </span>
                        </label>
                    </div>
                </fieldset>
                <p class="rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700">
                    <span class="text-slate-500">Valeur envoyée :</span>
                    <span id="scope-preview" class="break-all"><?= htmlspecialchars($scopeRaw === '' ? 'global' : $scopeRaw, ENT_QUOTES, 'UTF-8') ?></span>
                </p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="sec-visitor">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Étape 2</p>
            <h2 id="sec-visitor" class="text-lg font-black text-slate-900">Message affiché aux visiteurs</h2>
            <p class="mt-1 text-sm text-slate-600">Texte de la page d’indisponibilité (hors redirection HTTP).</p>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="is_enabled" value="1" id="is_enabled" class="h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" <?= ($r !== null && !empty($r['is_enabled'])) ? 'checked' : '' ?>>
                    <div>
                        <label for="is_enabled" class="text-sm font-bold text-slate-900">Règle activée</label>
                        <p class="text-xs text-slate-600">Si décoché, la règle reste en base mais ne bloque personne.</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <label for="maint-title" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Titre de la page</label>
                    <input type="text" name="title" id="maint-title" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                           value="<?= htmlspecialchars((string) ($row['title'] ?? 'Maintenance en cours'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="maint-message" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Message (HTML brut évité — texte simple)</label>
                    <textarea name="message" id="maint-message" rows="5" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100"><?= htmlspecialchars((string) ($row['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div>
                    <label for="maintenance_code" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Code maintenance (optionnel)</label>
                    <input type="text" name="maintenance_code" id="maintenance_code" class="mt-1 w-full max-w-md rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm"
                           value="<?= htmlspecialchars((string) ($row['maintenance_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="ex. MAINT-2026-04">
                    <p class="mt-1 text-xs text-slate-500">Référence affichée sur la page de maintenance pour le support.</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="sec-schedule">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Étape 3 — optionnel</p>
            <h2 id="sec-schedule" class="text-lg font-black text-slate-900">Fenêtre horaire</h2>
            <p class="mt-1 text-sm text-slate-600">Si vous renseignez début et/ou fin, la plateforme peut s’appuyer dessus selon la configuration du service (sinon seul le statut « activée » compte).</p>
            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="starts_at" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Début</label>
                    <input type="datetime-local" name="starts_at" id="starts_at" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" value="<?= $dt($isEdit ? ($r['starts_at'] ?? null) : null) ?>">
                </div>
                <div>
                    <label for="ends_at" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Fin</label>
                    <input type="datetime-local" name="ends_at" id="ends_at" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" value="<?= $dt($isEdit ? ($r['ends_at'] ?? null) : null) ?>">
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="sec-access">
            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Étape 4</p>
            <h2 id="sec-access" class="text-lg font-black text-slate-900">Qui peut encore accéder au site ?</h2>
            <p class="mt-1 text-sm text-slate-600">Contourner la bannière maintenance pour certaines IP ou rôles (en plus des règles admin système).</p>

            <div class="mt-6 flex flex-wrap items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                <input type="checkbox" name="allow_admin_bypass" value="1" id="allow_admin_bypass" class="mt-1 h-5 w-5 rounded border-slate-300 text-emerald-600" <?= ((int) ($row['allow_admin_bypass'] ?? 1)) === 1 ? 'checked' : '' ?>>
                <label for="allow_admin_bypass" class="text-sm leading-relaxed text-slate-800">
                    <span class="font-bold">Autoriser le contournement pour les super-admins / comptes avec accès administration système</span>
                    <span class="mt-1 block text-xs text-slate-600">Permet de continuer à travailler pendant la maintenance (selon permissions <code class="text-xs">admin.system</code> / <code class="text-xs">admin.access</code>).</span>
                </label>
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <label for="allowed_ips" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Adresses IP autorisées</label>
                    <input type="text" name="allowed_ips" id="allowed_ips" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm"
                           value="<?= htmlspecialchars((string) ($row['allowed_ips'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="127.0.0.1, 203.0.113.10">
                    <p class="mt-1 text-xs text-slate-500">Séparées par des virgules. Utile pour un poste fixe ou un bastion.</p>
                </div>
                <div>
                    <label for="allowed_roles" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Rôles de la communauté autorisés (optionnel)</label>
                    <input type="text" name="allowed_roles" id="allowed_roles" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm"
                           value="<?= htmlspecialchars((string) ($row['allowed_roles'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="formateur, moderateur">
                    <p class="mt-1 text-xs text-slate-500">Noms courts des rôles tels qu’ils figurent dans l’administration de la communauté, séparés par des virgules. Laissez vide pour ne pas filtrer par rôle.</p>
                </div>
            </div>
        </section>

        <details class="group rounded-2xl border border-slate-200 bg-slate-50/80 shadow-sm open:bg-white">
            <summary class="cursor-pointer list-none rounded-2xl px-6 py-4 text-sm font-bold text-slate-900 marker:hidden [&::-webkit-details-marker]:hidden">
                <span class="flex items-center justify-between gap-3">
                    <span>Options HTTP avancées</span>
                    <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-slate-500 group-open:border-emerald-200 group-open:bg-emerald-50 group-open:text-emerald-800">Déplier</span>
                </span>
                <span class="mt-1 block text-xs font-normal text-slate-500">Code de réponse, redirection, priorité entre plusieurs règles.</span>
            </summary>
            <div class="space-y-5 border-t border-slate-100 px-6 pb-6 pt-2">
                <div>
                    <label for="redirect_url" class="block text-xs font-bold uppercase tracking-wider text-slate-500">URL de redirection</label>
                    <input type="url" name="redirect_url" id="redirect_url" inputmode="url" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"
                           value="<?= htmlspecialchars((string) ($row['redirect_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="https://…">
                    <p class="mt-1 text-xs text-slate-500">Utilisée uniquement si le statut HTTP est une redirection (301, 302, 303, 307, 308).</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="http_status" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Code HTTP</label>
                        <select name="http_status" id="http_status" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-slate-900">
                            <?php
                            $h = (int) ($row['http_status'] ?? 503);
                            $httpChoices = [
                                503 => '503 — Service indisponible (défaut, page maintenance)',
                                502 => '502 — Bad gateway',
                                500 => '500 — Erreur serveur',
                                302 => '302 — Redirection temporaire (nécessite une URL ci-dessus)',
                                301 => '301 — Redirection permanente',
                                307 => '307 — Redirection (méthode conservée)',
                                308 => '308 — Redirection permanente (méthode conservée)',
                            ];
                            foreach ($httpChoices as $code => $label) {
                                echo '<option value="' . $code . '"' . ($h === $code ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
                            }
                            if (!isset($httpChoices[$h])) {
                                echo '<option value="' . (int) $h . '" selected>' . htmlspecialchars((string) $h) . ' (personnalisé)</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="priority" class="block text-xs font-bold uppercase tracking-wider text-slate-500">Priorité</label>
                        <input type="number" name="priority" id="priority" min="0" max="999999" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5"
                               value="<?= (int) ($row['priority'] ?? 100) ?>">
                        <p class="mt-1 text-xs text-slate-500">Plus la valeur est <strong>élevée</strong>, plus la règle est prioritaire lorsque plusieurs s’appliquent.</p>
                    </div>
                </div>
            </div>
        </details>

        <div class="flex flex-col-reverse items-stretch justify-end gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center">
            <a href="<?= url('admin/maintenance') ?>" class="inline-flex justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">Annuler</a>
            <button type="submit" class="inline-flex justify-center rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"><?= $isEdit ? 'Enregistrer les modifications' : 'Créer la règle' ?></button>
        </div>
    </form>
</div>

<script>
(function () {
    var form = document.getElementById('maintenance-form');
    if (!form) return;

    var hidden = document.getElementById('maintenance-scope-hidden');
    var preview = document.getElementById('scope-preview');
    var routeIn = document.getElementById('scope-route-input');
    var moduleIn = document.getElementById('scope-module-input');
    var customIn = document.getElementById('scope-custom-input');

    function selectedMode() {
        var el = form.querySelector('input[name="scope_mode"]:checked');
        return el ? el.value : 'global';
    }

    function normalizePath(p) {
        p = (p || '').trim();
        if (p === '') return '';
        if (p.charAt(0) !== '/') p = '/' + p;
        return p.replace(/\/+/g, '/');
    }

    function buildScope() {
        var mode = selectedMode();
        if (mode === 'global') return 'global';
        if (mode === 'route') {
            var p = normalizePath(routeIn ? routeIn.value : '');
            return p === '' ? 'global' : ('route:' + p);
        }
        if (mode === 'module') {
            var m = (moduleIn && moduleIn.value ? moduleIn.value : '').trim();
            m = m.replace(/^\/+/, '').replace(/\s+/g, '');
            return m === '' ? 'global' : ('module:' + m);
        }
        var c = (customIn && customIn.value ? customIn.value : '').trim();
        return c === '' ? 'global' : c;
    }

    function syncScope() {
        var v = buildScope();
        if (hidden) hidden.value = v;
        if (preview) preview.textContent = v;
        var mode = selectedMode();
        if (routeIn) routeIn.disabled = mode !== 'route';
        if (moduleIn) moduleIn.disabled = mode !== 'module';
        if (customIn) customIn.disabled = mode !== 'custom';
        if (routeIn) routeIn.classList.toggle('opacity-50', mode !== 'route');
        if (moduleIn) moduleIn.classList.toggle('opacity-50', mode !== 'module');
        if (customIn) customIn.classList.toggle('opacity-50', mode !== 'custom');
    }

    form.querySelectorAll('input[name="scope_mode"]').forEach(function (r) {
        r.addEventListener('change', syncScope);
    });
    [routeIn, moduleIn, customIn].forEach(function (el) {
        if (el) el.addEventListener('input', syncScope);
    });

    form.addEventListener('submit', function () {
        syncScope();
    });

    syncScope();
})();
</script>
