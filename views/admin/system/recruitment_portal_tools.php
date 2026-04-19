<?php
declare(strict_types=1);
/** @var array<string, mixed> $lookup */
/** @var bool $automodMailEnabled */
/** @var string $automodMailSettingKey */
/** @var list<array{id: int|string, name: string, slug: string}> $tenantSelectRows */
/** @var list<int> $tenantIdsWithPortalBlocks */
/** @var list<array{id: int, email: string, status: string, first_name: string, last_name: string}> $enlistmentSelectRows */

$lookup = is_array($lookup ?? null) ? $lookup : [];
$tid = (int) ($lookup['tenant_id'] ?? 0);
$eid = (int) ($lookup['enlistment_id'] ?? 0);
$tenantName = $lookup['tenant_name'] ?? null;
$enlistment = is_array($lookup['enlistment'] ?? null) ? $lookup['enlistment'] : null;
$lookupErr = isset($lookup['error']) ? (string) $lookup['error'] : '';
$blocks = is_array($lookup['portal_blocks'] ?? null) ? $lookup['portal_blocks'] : [];
$tenantSelectRows = is_array($tenantSelectRows ?? null) ? $tenantSelectRows : [];
$tenantIdsWithPortalBlocks = is_array($tenantIdsWithPortalBlocks ?? null) ? $tenantIdsWithPortalBlocks : [];
$enlistmentSelectRows = is_array($enlistmentSelectRows ?? null) ? $enlistmentSelectRows : [];
$portalBlockTenantSet = array_fill_keys(array_map(static fn ($v) => (int) $v, $tenantIdsWithPortalBlocks), true);
$typeLab = static function (string $t): string {
    return $t === 'ip' ? 'Réseau' : ($t === 'email' ? 'E-mail (empreinte)' : $t);
};
$enlistStatusLabel = static function (string $s): string {
    return match ($s) {
        'submitted' => 'À traiter',
        'reviewed' => 'Acceptée',
        'rejected' => 'Refusée',
        'blocked' => 'Non admis',
        default => $s !== '' ? $s : '—',
    };
};
$enlistmentOptionLabel = static function (array $r) use ($enlistStatusLabel): string {
    $id = (int) ($r['id'] ?? 0);
    $em = trim((string) ($r['email'] ?? ''));
    $st = $enlistStatusLabel((string) ($r['status'] ?? ''));
    $fn = trim((string) ($r['first_name'] ?? ''));
    $ln = trim((string) ($r['last_name'] ?? ''));
    $name = trim($fn . ' ' . $ln);

    return '#' . $id . ' — ' . ($em !== '' ? $em : '—') . ' — ' . $st . ($name !== '' ? ' · ' . $name : '');
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
            Choisissez la <strong>communauté</strong> et le <strong>dossier</strong> dans les listes issues de la base (mêmes données que le back-office). Les dossiers affichés sont les plus récents de la communauté (limite technique) ; un numéro hors liste reste accessible si vous l’ouvrez via un lien direct. Ensuite : blocages actifs liés au portail, levée ciblée ou réouverture guidée (e-mail dossier, option réseau, option nouveau lien).
        </p>
        <?php if ($tenantIdsWithPortalBlocks !== []): ?>
            <p class="text-xs text-sky-900/90 mb-4 rounded-lg border border-sky-300/80 bg-white/80 px-3 py-2 leading-relaxed" role="status">
                <strong>Détection en base</strong> — <?= count($tenantIdsWithPortalBlocks) ?> communauté<?= count($tenantIdsWithPortalBlocks) > 1 ? 's' : '' ?> avec au moins un blocage actif dont le motif mentionne le <strong>portail recrutement</strong>. Elles sont listées en premier dans le menu déroulant (préfixe ●).
            </p>
        <?php endif; ?>
        <form method="get" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] items-end mb-6">
            <div>
                <label for="rpt-tenant_id" class="block text-xs text-slate-600 mb-1">Communauté</label>
                <select
                    id="rpt-tenant_id"
                    name="tenant_id"
                    required
                    class="w-full max-w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                    onchange="var e=document.getElementById('rpt-enlistment_id');if(e){e.value='';}this.form.submit();"
                >
                    <option value=""<?= $tid < 1 ? ' selected' : '' ?>>— Choisir une communauté —</option>
                    <?php foreach ($tenantSelectRows as $tr): ?>
                        <?php
                        $trid = (int) ($tr['id'] ?? 0);
                        if ($trid < 2) {
                            continue;
                        }
                        $tname = trim((string) ($tr['name'] ?? ''));
                        $mark = isset($portalBlockTenantSet[$trid]) ? '● ' : '';
                        ?>
                        <option value="<?= $trid ?>"<?= $tid === $trid ? ' selected' : '' ?>><?= htmlspecialchars($mark . $tname . ' (#' . $trid . ')', ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="rpt-enlistment_id" class="block text-xs text-slate-600 mb-1">Dossier de candidature</label>
                <select
                    id="rpt-enlistment_id"
                    name="enlistment_id"
                    class="w-full max-w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                    <?= $tid < 1 ? ' disabled' : '' ?>
                >
                    <option value=""<?= $eid < 1 ? ' selected' : '' ?>><?= $tid < 1 ? '— Choisir d’abord une communauté —' : '— Choisir un dossier —' ?></option>
                    <?php foreach ($enlistmentSelectRows as $er): ?>
                        <?php
                        $erid = (int) ($er['id'] ?? 0);
                        if ($erid < 1) {
                            continue;
                        }
                        ?>
                        <option value="<?= $erid ?>"<?= $eid === $erid ? ' selected' : '' ?>><?= htmlspecialchars($enlistmentOptionLabel($er), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white hover:bg-sky-800 shrink-0">Afficher</button>
        </form>
        <?php if ($tid > 0 && $eid < 1): ?>
            <p class="text-xs text-sky-900/85 mb-4">Sélectionnez un dossier puis cliquez sur <strong>Afficher</strong> pour charger les blocages et la réouverture guidée.</p>
        <?php endif; ?>

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
                    <?php
                    $portalCandEmail = trim((string) ($enlistment['email'] ?? ''));
                    $defaultPortalCandidateMail = $portalCandEmail !== '' && filter_var($portalCandEmail, FILTER_VALIDATE_EMAIL);
                    ?>
                    <form method="post" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools/reopen-enlistment'), ENT_QUOTES, 'UTF-8') ?>" class="space-y-4" onsubmit="return confirm('Confirmer la réouverture pour ce dossier ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                        <input type="hidden" name="enlistment_id" value="<?= $eid ?>">
                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <input type="checkbox" name="also_revoke_ip_candidate" value="1" class="mt-1 rounded border-slate-300">
                            <span><strong>Lever aussi</strong> les blocages <strong>réseau</strong> actifs liés au portail pour les messages <strong>candidat</strong> sur cette communauté (peut toucher plusieurs adresses IP enregistrées).</span>
                        </label>
                        <div class="rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-xs text-slate-600">
                            <strong>Courriel candidat</strong> — le message utilise le modèle « mise à jour de candidature » avec le lien de suivi. Décochez l’option ci-dessous pour ne pas envoyer de courriel (ni régénérer le jeton).
                            <?php if (!$defaultPortalCandidateMail): ?>
                                <span class="block mt-1 text-amber-800 font-semibold">Adresse du dossier absente ou invalide : l’envoi automatique ne sera pas possible.</span>
                            <?php endif; ?>
                        </div>
                        <label class="flex items-start gap-2 text-sm text-slate-800">
                            <span class="inline-flex flex-col gap-0.5">
                                <input type="hidden" name="refresh_token_and_email" value="0">
                                <input type="checkbox" name="refresh_token_and_email" value="1" class="mt-1 rounded border-slate-300"<?= $defaultPortalCandidateMail ? ' checked' : '' ?>>
                            </span>
                            <span><strong>Régénérer / prolonger</strong> le jeton de suivi et <strong>envoyer un courriel</strong> au candidat avec le lien actuel (coché par défaut si l’e-mail du dossier est valide).</span>
                        </label>
                        <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800">Appliquer la réouverture</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
