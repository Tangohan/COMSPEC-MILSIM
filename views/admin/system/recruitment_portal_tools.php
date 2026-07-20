<?php
declare(strict_types=1);
/** @var array<string, mixed> $lookup */
/** @var bool $automodMailEnabled */
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
$automodMailEnabled = (bool) ($automodMailEnabled ?? false);

$typeLab = static function (string $t): string {
    return match ($t) {
        'ip' => 'Adresse réseau',
        'email' => 'Adresse e-mail',
        default => $t !== '' ? $t : 'Autre',
    };
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
    $parts = ['Dossier nº ' . $id];
    if ($em !== '') {
        $parts[] = $em;
    }
    $parts[] = $st;
    if ($name !== '') {
        $parts[] = $name;
    }

    return implode(' — ', $parts);
};

$linkChip = 'inline-flex items-center justify-center rounded-lg border px-3 py-2 text-xs font-semibold transition';
?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10 space-y-10">
    <header>
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Administration plateforme</p>
        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Suivi candidatures — filtre automatique et accès</h1>
        <p class="mt-3 text-sm text-slate-600 leading-relaxed max-w-3xl">
            Page réservée aux <strong class="font-semibold text-slate-800">administrateurs site</strong> :
            rétablir l’accès d’un candidat après un message refusé par le filtre, activer ou couper les courriels d’alerte,
            et rejoindre rapidement les autres outils de restriction.
        </p>
        <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-3xl">
            Au quotidien, chaque communauté gère ses propres restrictions depuis
            <a href="<?= htmlspecialchars(url('back-office/security-indicators'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 hover:decoration-emerald-600">Blocages portail et sécurité</a>
            dans son espace.
        </p>
        <a href="<?= url('admin') ?>" class="inline-block mt-5 text-sm text-slate-600 hover:underline">Retour au centre opérateur site</a>
    </header>

    <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
    <?php if ($f): ?>
        <p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($s): ?>
        <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm" aria-labelledby="rpt-shortcuts-heading">
        <h2 id="rpt-shortcuts-heading" class="text-sm font-bold text-slate-900">Autres outils liés</h2>
        <p class="mt-2 text-sm text-slate-600">Raccourcis vers les écrans voisins (restrictions globales, sanctions, historique).</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="<?= htmlspecialchars(url('admin/system/blocklist'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $linkChip ?> border-rose-200 bg-rose-50/80 text-rose-950 hover:bg-rose-100">Liste e-mail et réseau (toute la plateforme)</a>
            <a href="<?= htmlspecialchars(url('admin/system/member-sanctions'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $linkChip ?> border-slate-200 bg-slate-50 text-slate-800 hover:bg-slate-100">Sanctions membres (site)</a>
            <a href="<?= htmlspecialchars(url('admin/audit'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $linkChip ?> border-slate-200 bg-slate-50 text-slate-800 hover:bg-slate-100">Journal d’audit</a>
        </div>
    </section>

    <section class="rounded-2xl border border-amber-200/90 bg-amber-50/40 p-6 sm:p-8 shadow-sm" aria-labelledby="rpt-filter-heading">
        <h2 id="rpt-filter-heading" class="text-sm font-bold text-amber-950">Comment fonctionne le filtre sur le portail</h2>
        <p class="mt-3 text-sm text-slate-700 leading-relaxed">
            Lorsqu’un candidat envoie un message sur le suivi en ligne, un filtre lit le texte et peut
            <strong class="font-semibold text-amber-950">bloquer l’accès</strong> s’il détecte des formulations graves
            (par exemple harcèlement ou mise en danger). Le candidat voit alors que le suivi est indisponible ;
            les responsables de la communauté et l’équipe site peuvent être prévenus par courriel.
        </p>
        <details class="mt-6 rounded-xl border border-amber-200/80 bg-white/80 px-4 py-3 text-sm text-slate-700">
            <summary class="cursor-pointer font-semibold text-amber-950 outline-none focus-visible:ring-2 focus-visible:ring-amber-300 rounded">Que faire ensuite ?</summary>
            <ul class="mt-3 space-y-2 list-disc pl-5 text-slate-600">
                <li>La communauté peut souvent rétablir l’accès depuis son espace recrutement, sans passer par cette page.</li>
                <li>Utilisez l’assistance ci-dessous lorsqu’il faut lever un blocage, renvoyer un lien de suivi, ou traiter un cas depuis le centre site.</li>
                <li>Les listes de formulations sont mises à jour avec l’application : aucun réglage manuel ici.</li>
            </ul>
        </details>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm" aria-labelledby="rpt-mail-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h2 id="rpt-mail-heading" class="text-sm font-bold text-slate-900">Courriels d’alerte</h2>
                <p class="mt-2 text-sm text-slate-600 leading-relaxed max-w-2xl">
                    Quand le filtre refuse un message, la plateforme peut prévenir les personnes concernées par courriel.
                    Si vous désactivez cette option, <strong class="font-semibold text-slate-800">les blocages continuent de s’appliquer</strong> :
                    seul l’envoi des alertes s’arrête, sur toute la plateforme.
                </p>
            </div>
            <span class="inline-flex shrink-0 items-center rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide <?= $automodMailEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-slate-50 text-slate-600' ?>">
                <?= $automodMailEnabled ? 'Alertes actives' : 'Alertes coupées' ?>
            </span>
        </div>
        <form method="post" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools/save-mail'), ENT_QUOTES, 'UTF-8') ?>" class="mt-8 flex flex-col gap-5 sm:flex-row sm:flex-wrap sm:items-center">
            <?= \App\Core\Csrf::field() ?>
            <label class="inline-flex items-start gap-3 text-sm font-medium text-slate-800 cursor-pointer">
                <input type="checkbox" name="automod_alerts_enabled" value="1" class="mt-0.5 rounded border-slate-300" <?= $automodMailEnabled ? 'checked' : '' ?>>
                <span>Envoyer les courriels d’alerte lorsque le filtre bloque un message sur le suivi candidature</span>
            </label>
            <button type="submit" class="inline-flex w-fit items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">Enregistrer</button>
        </form>
    </section>

    <section class="rounded-2xl border border-sky-200 bg-sky-50/40 p-6 sm:p-8 shadow-sm space-y-8" aria-labelledby="rpt-assist-heading">
        <div>
            <h2 id="rpt-assist-heading" class="text-sm font-bold text-sky-950">Assistance — rétablir le suivi d’un dossier</h2>
            <p class="mt-3 text-sm text-sky-950/90 leading-relaxed max-w-3xl">
                Choisissez la communauté puis le dossier. Vous verrez les restrictions encore actives liées au portail recrutement,
                pourrez en lever une précisément, ou appliquer une réouverture guidée (déblocage de l’e-mail du dossier,
                option réseau, option nouveau lien de suivi par courriel).
            </p>
            <?php if ($tenantIdsWithPortalBlocks !== []): ?>
                <p class="mt-4 text-sm text-sky-950 rounded-xl border border-sky-300/80 bg-white/90 px-4 py-3 leading-relaxed" role="status">
                    <strong class="font-semibold"><?= count($tenantIdsWithPortalBlocks) ?> communauté<?= count($tenantIdsWithPortalBlocks) > 1 ? 's' : '' ?></strong>
                    <?= count($tenantIdsWithPortalBlocks) > 1 ? 'ont' : 'a' ?> au moins une restriction active liée au portail recrutement.
                    Elles apparaissent en premier dans la liste (marqueur ●).
                </p>
            <?php endif; ?>
        </div>

        <form method="get" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools'), ENT_QUOTES, 'UTF-8') ?>" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end rounded-xl border border-sky-200/80 bg-white p-4 sm:p-5">
            <div>
                <label for="rpt-tenant_id" class="block text-xs font-medium text-slate-600 mb-1.5">Communauté</label>
                <select
                    id="rpt-tenant_id"
                    name="tenant_id"
                    required
                    class="w-full max-w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm bg-white"
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
                        <option value="<?= $trid ?>"<?= $tid === $trid ? ' selected' : '' ?>><?= htmlspecialchars($mark . ($tname !== '' ? $tname : ('Communauté nº ' . $trid)), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="rpt-enlistment_id" class="block text-xs font-medium text-slate-600 mb-1.5">Dossier de candidature</label>
                <select
                    id="rpt-enlistment_id"
                    name="enlistment_id"
                    class="w-full max-w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm bg-white"
                    <?= $tid < 1 ? ' disabled' : '' ?>
                >
                    <option value=""<?= $eid < 1 ? ' selected' : '' ?>><?= $tid < 1 ? '— Choisissez d’abord une communauté —' : '— Choisir un dossier —' ?></option>
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
                <?php if ($tid > 0): ?>
                    <p class="mt-2 text-xs text-slate-500 leading-relaxed">Liste des dossiers récents de cette communauté. Un dossier absent de la liste reste accessible via un lien direct vers cette page.</p>
                <?php endif; ?>
            </div>
            <button type="submit" class="rounded-lg bg-sky-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-sky-900 shrink-0">Afficher</button>
        </form>

        <?php if ($tid > 0 && $eid < 1): ?>
            <p class="text-sm text-sky-950/85 rounded-xl border border-dashed border-sky-300/70 bg-white/60 px-4 py-3">
                Sélectionnez un dossier puis cliquez sur <strong class="font-semibold">Afficher</strong> pour charger les restrictions et la réouverture guidée.
            </p>
        <?php endif; ?>

        <?php if ($tid > 0 && $eid > 0): ?>
            <?php if ($lookupErr !== ''): ?>
                <p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><?= htmlspecialchars($lookupErr, ENT_QUOTES, 'UTF-8') ?></p>
            <?php else: ?>
                <div class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 space-y-8">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Dossier sélectionné</p>
                        <p class="mt-2 text-sm text-slate-900">
                            <strong class="font-bold"><?= htmlspecialchars((string) $tenantName, ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="text-slate-400"> · </span>
                            Dossier nº<?= (int) ($enlistment['id'] ?? 0) ?>
                            <?php $em = trim((string) ($enlistment['email'] ?? '')); if ($em !== ''): ?>
                                <span class="text-slate-400"> · </span><span class="text-slate-700"><?= htmlspecialchars($em, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Restrictions actives (portail recrutement)</h3>
                        <?php if ($blocks === []): ?>
                            <p class="mt-3 text-sm text-slate-600">Aucune restriction active liée au portail recrutement pour cette communauté.</p>
                        <?php else: ?>
                            <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 overflow-hidden text-sm">
                                <?php foreach ($blocks as $b): ?>
                                    <?php
                                    $bid = (int) ($b['id'] ?? 0);
                                    $rs = trim((string) ($b['reason'] ?? ''));
                                    ?>
                                    <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between bg-white">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <span class="font-semibold text-slate-900"><?= htmlspecialchars($typeLab((string) ($b['indicator_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="text-xs text-slate-500">Réf. nº<?= $bid ?></span>
                                            </div>
                                            <p class="mt-1.5 text-xs text-slate-600 leading-relaxed"><?= $rs !== '' ? htmlspecialchars($rs, ENT_QUOTES, 'UTF-8') : 'Motif non renseigné' ?></p>
                                        </div>
                                        <form method="post" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools/revoke-indicator'), ENT_QUOTES, 'UTF-8') ?>" class="shrink-0" onsubmit="return confirm('Lever cette restriction pour cette communauté ?');">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="hidden" name="indicator_id" value="<?= $bid ?>">
                                            <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                                            <input type="hidden" name="return_enlistment_id" value="<?= $eid ?>">
                                            <button type="submit" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-900 hover:bg-emerald-100">Lever</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-5 sm:p-6">
                        <h3 class="text-sm font-bold text-slate-900">Réouverture guidée</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                            Cette action lève le blocage lié à l’adresse e-mail du dossier (s’il existe encore).
                            Cochez les options ci-dessous selon le besoin.
                        </p>
                        <?php
                        $portalCandEmail = trim((string) ($enlistment['email'] ?? ''));
                        $defaultPortalCandidateMail = $portalCandEmail !== '' && filter_var($portalCandEmail, FILTER_VALIDATE_EMAIL);
                        ?>
                        <form method="post" action="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools/reopen-enlistment'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-5" onsubmit="return confirm('Confirmer la réouverture pour ce dossier ?');">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="tenant_id" value="<?= $tid ?>">
                            <input type="hidden" name="enlistment_id" value="<?= $eid ?>">
                            <label class="flex items-start gap-3 text-sm text-slate-800 cursor-pointer">
                                <input type="checkbox" name="also_revoke_ip_candidate" value="1" class="mt-1 rounded border-slate-300">
                                <span>
                                    <strong class="font-semibold">Lever aussi</strong> les restrictions <strong class="font-semibold">réseau</strong> actives liées aux messages candidats sur cette communauté.
                                    <span class="block mt-1 text-xs text-slate-500">Attention : cela peut concerner plusieurs adresses réseau enregistrées pour la communauté.</span>
                                </span>
                            </label>
                            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 leading-relaxed">
                                <p>
                                    <strong class="font-semibold text-slate-800">Courriel au candidat</strong> —
                                    le message reprend le modèle « mise à jour de candidature » avec le lien de suivi.
                                    Décochez l’option ci-dessous pour ne pas envoyer de courriel ni renouveler le lien.
                                </p>
                                <?php if (!$defaultPortalCandidateMail): ?>
                                    <p class="mt-2 text-amber-900 font-semibold text-xs">Adresse e-mail du dossier absente ou invalide : l’envoi automatique ne sera pas possible.</p>
                                <?php endif; ?>
                            </div>
                            <label class="flex items-start gap-3 text-sm text-slate-800 cursor-pointer">
                                <span class="inline-flex flex-col gap-0.5">
                                    <input type="hidden" name="refresh_token_and_email" value="0">
                                    <input type="checkbox" name="refresh_token_and_email" value="1" class="mt-1 rounded border-slate-300"<?= $defaultPortalCandidateMail ? ' checked' : '' ?>>
                                </span>
                                <span>
                                    <strong class="font-semibold">Renouveler le lien de suivi</strong> et <strong class="font-semibold">envoyer un courriel</strong> au candidat.
                                    <span class="block mt-1 text-xs text-slate-500">Coché par défaut lorsque l’adresse e-mail du dossier est valide.</span>
                                </span>
                            </label>
                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Appliquer la réouverture</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
