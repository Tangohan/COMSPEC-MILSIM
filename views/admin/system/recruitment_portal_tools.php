<?php
declare(strict_types=1);
/** @var array<string, mixed> $lookup */
/** @var bool $automodMailEnabled */
/** @var string $automodMailSettingKey */

$lookup = is_array($lookup ?? null) ? $lookup : [];
$tid = (int) ($lookup['tenant_id'] ?? 0);
$eid = (int) ($lookup['enlistment_id'] ?? 0);
$tenantName = $lookup['tenant_name'] ?? null;
$enlistment = is_array($lookup['enlistment'] ?? null) ? $lookup['enlistment'] : null;
$lookupErr = isset($lookup['error']) ? (string) $lookup['error'] : '';
$blocks = is_array($lookup['portal_blocks'] ?? null) ? $lookup['portal_blocks'] : [];
$typeLab = static function (string $t): string {
    return $t === 'ip' ? 'Réseau' : ($t === 'email' ? 'E-mail (empreinte)' : $t);
};
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">
    <header class="mb-8">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Administration plateforme</p>
        <h1 class="text-2xl font-black text-slate-900">Portail recrutement — modération automatique &amp; accès</h1>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed">
            Outils réservés aux <strong>administrateurs site</strong> : assistance après blocage automatique (messages inacceptables), courriels d’alerte, et liens vers les listes globales.
            La gestion courante des blocages par communauté reste dans le <a href="<?= htmlspecialchars(url('back-office/security-indicators'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline">back-office — Blocages portail &amp; sécurité</a>.
        </p>
        <a href="<?= url('admin') ?>" class="inline-block mt-4 text-sm text-slate-600 hover:underline">Retour au centre opérateur site</a>
    </header>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-8">
        <h2 class="text-sm font-bold text-slate-900 mb-2">Raccourcis</h2>
        <div class="flex flex-wrap gap-3 text-sm font-semibold">
            <a href="<?= htmlspecialchars(url('admin/system/blocklist'), ENT_QUOTES, 'UTF-8') ?>" class="text-rose-800 hover:underline">Liste globale e-mail / réseau</a>
            <a href="<?= htmlspecialchars(url('admin/system/member-sanctions'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-700 hover:underline">Sanctions membres (site)</a>
            <a href="<?= htmlspecialchars(url('admin/audit'), ENT_QUOTES, 'UTF-8') ?>" class="text-slate-700 hover:underline">Journal d’audit</a>
        </div>
    </section>

    <section class="rounded-2xl border border-amber-200/90 bg-amber-50/40 p-6 shadow-sm mb-8">
        <h2 class="text-sm font-bold text-amber-950 mb-2">Modération automatique (texte sur le portail)</h2>
        <p class="text-sm text-slate-700 leading-relaxed">
            Les règles sont aujourd’hui définies dans le code (<code class="rounded bg-white px-1 text-xs">EnlistmentPortalTextModerationScanner</code>) : détection locale de formulations graves (ex. harcèlement, mise en danger).
            Toute évolution des listes passe par une mise à jour applicative ou une future externalisation en base.
        </p>
        <p class="mt-3 text-xs text-slate-600">Codes internes courants : <span class="font-mono">self_harm</span>, <span class="font-mono">harassment</span>.</p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-8">
        <h2 class="text-sm font-bold text-slate-900 mb-2">Courriels d’alerte (modération automatique portail)</h2>
        <p class="text-sm text-slate-600 mb-4">Clé technique : <span class="font-mono text-xs"><?= htmlspecialchars($automodMailSettingKey, ENT_QUOTES, 'UTF-8') ?></span> (table <span class="font-mono text-xs">platform_settings</span>). Désactivé : plus aucun mail d’alerte automod n’est envoyé ; les <strong>blocages</strong> restent appliqués.</p>
        <form method="post" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools/save-mail'), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-center gap-4">
            <?= \App\Core\Csrf::field() ?>
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-800">
                <input type="checkbox" name="automod_alerts_enabled" value="1" class="rounded border-slate-300" <?= $automodMailEnabled ? 'checked' : '' ?>>
                Envoyer les courriels d’alerte modération automatique (portail recrutement)
            </label>
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800">Enregistrer</button>
        </form>
    </section>

    <section class="rounded-2xl border border-sky-200 bg-sky-50/50 p-6 shadow-sm mb-8">
        <h2 class="text-sm font-bold text-sky-950 mb-2">Assistance — réouvrir le suivi après un message bloqué</h2>
        <p class="text-sm text-sky-950/90 mb-4">
            Saisissez l’identifiant de la <strong>communauté</strong> (tenant) et du <strong>dossier</strong> (enlistment), visibles sur la fiche recrutement back-office. Cela liste les blocages actifs liés au portail ; vous pouvez en lever un précis, ou lancer la réouverture guidée (e-mail dossier, option réseau, option nouveau lien).
        </p>
        <form method="get" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-3 sm:grid-cols-3 items-end mb-6">
            <div>
                <label class="block text-xs text-slate-600 mb-1">ID communauté (tenant_id)</label>
                <input type="number" name="tenant_id" value="<?= $tid > 0 ? $tid : '' ?>" min="1" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" required>
            </div>
            <div>
                <label class="block text-xs text-slate-600 mb-1">ID dossier (enlistment_id)</label>
                <input type="number" name="enlistment_id" value="<?= $eid > 0 ? $eid : '' ?>" min="1" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" required>
            </div>
            <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white hover:bg-sky-800">Afficher</button>
        </form>

        <?php if ($tid > 0 && $eid > 0): ?>
            <?php if ($lookupErr !== ''): ?>
                <p class="text-sm text-red-700"><?= htmlspecialchars($lookupErr, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <p class="text-sm text-slate-800 mb-4">
                    <strong><?= htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') ?></strong>
                    — dossier n°<?= (int) ($enlistment['id'] ?? 0) ?>
                    <?php $em = trim((string) ($enlistment['email'] ?? '')); if ($em !== ''): ?>
                        <span class="text-slate-500"> · </span><span class="font-mono text-xs"><?= htmlspecialchars($em, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </p>

                <?php if ($blocks === []): ?>
                    <p class="text-sm text-slate-600">Aucun blocage actif avec motif « Portail recrutement » pour cette communauté.</p>
                <?php else: ?>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Blocages actifs (motif portail recrutement)</h3>
                    <ul class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white text-sm">
                        <?php foreach ($blocks as $b): ?>
                            <?php
                            $bid = (int) ($b['id'] ?? 0);
                            $rs = trim((string) ($b['reason'] ?? ''));
                            $vh = (string) ($b['value_hash'] ?? '');
                            $fp = $vh !== '' ? '…' . htmlspecialchars(substr($vh, -10), ENT_QUOTES, 'UTF-8') : '—';
                            ?>
                            <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <span class="font-semibold text-slate-900"><?= htmlspecialchars($typeLab((string) ($b['indicator_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="ml-2 font-mono text-xs text-slate-500"><?= $fp ?></span>
                                    <p class="mt-1 text-xs text-slate-600"><?= $rs !== '' ? htmlspecialchars($rs, ENT_QUOTES, 'UTF-8') : '—' ?></p>
                                </div>
                                <form method="post" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools/revoke-indicator'), ENT_QUOTES, 'UTF-8') ?>" class="shrink-0" onsubmit="return confirm('Lever ce blocage pour cette communauté ?');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="indicator_id" value="<?= $bid ?>">
                                    <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                    <input type="hidden" name="return_enlistment_id" value="<?= $eid ?>">
                                    <button type="submit" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-900 hover:bg-emerald-100">Lever</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="mt-8 rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-600 mb-3">Réouverture guidée (dossier)</h3>
                    <form method="post" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools/reopen-enlistment'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4" onsubmit="return confirm('Confirmer la réouverture pour ce dossier ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                        <input type="hidden" name="enlistment_id" value="<?= $eid ?>">
                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <input type="checkbox" name="also_revoke_ip_candidate" value="1" class="mt-1 rounded border-slate-300">
                            <span><strong>Lever aussi</strong> les blocages <strong>réseau</strong> actifs liés au portail pour les messages <strong>candidat</strong> sur cette communauté (peut toucher plusieurs adresses IP enregistrées).</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <input type="checkbox" name="refresh_token_and_email" value="1" class="mt-1 rounded border-slate-300">
                            <span><strong>Régénérer / prolonger</strong> le jeton de suivi et <strong>envoyer un courriel</strong> au candidat avec le lien actuel (recommandé si le lien a expiré).</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Appliquer la réouverture</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
