<?php
/** @var list<array<string, mixed>> $invitations */
/** @var list<array<string, mixed>> $rolesOrganization */
/** @var list<array<string, mixed>> $inviteUnits */
/** @var list<array{id: int, label: string, name: string}> $inviteJobRoleOptions */
/** @var bool $canAdd */
/** @var string $inviteFilterStatus */
$inviteFilterStatus = $inviteFilterStatus ?? '';
$rolesOrganization = $rolesOrganization ?? [];
$inviteUnits = $inviteUnits ?? [];
$inviteJobRoleOptions = $inviteJobRoleOptions ?? [];

$layerLabel = static function (string $layer): string {
    return match ($layer) {
        'community' => 'Gouvernance et habilitations',
        'intra' => 'Rôles opérationnels et métier',
        default => 'Autres rôles',
    };
};

$statusPresentation = static function (string $raw): array {
    return match ($raw) {
        'pending' => [
            'label' => 'En attente de réponse',
            'class' => 'bg-amber-50 text-amber-900 ring-amber-200',
        ],
        'accepted' => [
            'label' => 'Compte rattaché',
            'class' => 'bg-emerald-50 text-emerald-900 ring-emerald-200',
        ],
        'revoked' => [
            'label' => 'Annulée',
            'class' => 'bg-slate-100 text-slate-700 ring-slate-200',
        ],
        'expired' => [
            'label' => 'Expirée',
            'class' => 'bg-slate-100 text-slate-600 ring-slate-200',
        ],
        default => [
            'label' => 'État indéterminé',
            'class' => 'bg-slate-50 text-slate-600 ring-slate-200',
        ],
    };
};

$formatDt = static function (?string $mysql): string {
    if ($mysql === null || $mysql === '') {
        return '';
    }
    $t = strtotime($mysql);

    return $t ? date('d/m/Y à H:i', $t) : '';
};

$payloadSummary = static function (?string $raw, array $unitsById, array $jobLabelsById): string {
    if ($raw === null || $raw === '') {
        return '';
    }
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        return '';
    }
    $parts = [];
    $uid = isset($d['unit_id']) ? (int) $d['unit_id'] : 0;
    if ($uid > 0 && isset($unitsById[$uid])) {
        $lab = isset($d['assignment_label']) ? trim((string) $d['assignment_label']) : '';
        $parts[] = 'Unité : ' . $unitsById[$uid] . ($lab !== '' ? ' — ' . $lab : '');
    }
    $jid = isset($d['personnel_job_role_id']) ? (int) $d['personnel_job_role_id'] : 0;
    if ($jid > 0 && isset($jobLabelsById[$jid])) {
        $parts[] = 'Fonction sur la fiche : ' . $jobLabelsById[$jid];
    }

    return $parts !== [] ? implode(' · ', $parts) : '';
};

$unitsById = [];
foreach ($inviteUnits as $u) {
    $unitsById[(int) ($u['id'] ?? 0)] = (string) ($u['name'] ?? '');
}
$jobLabelsById = [];
foreach ($inviteJobRoleOptions as $jo) {
    $jobLabelsById[(int) ($jo['id'] ?? 0)] = (string) ($jo['label'] ?? $jo['name'] ?? '');
}

$rolesByLayer = ['community' => [], 'intra' => [], 'other' => []];
foreach ($rolesOrganization as $r) {
    $ly = (string) ($r['role_layer'] ?? 'community');
    if ($ly === 'community' || $ly === 'intra') {
        $rolesByLayer[$ly][] = $r;
    } else {
        $rolesByLayer['other'][] = $r;
    }
}
?>
<div class="bg-slate-50 min-h-[calc(100vh-3.5rem)]">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">
        <header class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 mb-2">Back-office communauté</p>
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Invitations</h1>
                    <p class="mt-3 text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                        Invitez des personnes à rejoindre votre unité : elles reçoivent un lien sécurisé par e-mail pour créer leur accès ou rattacher un compte existant, avec le rôle que vous définissez.
                    </p>
                </div>
                <a href="<?= url('back-office') ?>" class="shrink-0 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-white hover:border-slate-300 transition-colors">
                    Retour au centre de pilotage
                </a>
            </div>
        </header>

        <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
        <?php if ($f): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                <?= htmlspecialchars($f) ?>
            </div>
        <?php endif; ?>
        <?php if ($s): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                <?= htmlspecialchars($s) ?>
            </div>
        <?php endif; ?>

        <?php if (!$canAdd): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Votre formule actuelle limite le nombre de membres. Passez à une offre supérieure pour envoyer de nouvelles invitations.
            </div>
        <?php endif; ?>

        <?php if ($canAdd && empty($rolesOrganization)): ?>
            <div class="rounded-xl border border-amber-200 bg-white px-5 py-4 text-sm text-amber-950 shadow-sm">
                Aucun rôle d’organisation n’est disponible pour l’instant. Configurez d’abord les rôles de votre communauté dans le back-office, ou contactez une personne administratrice de la plateforme si le problème persiste.
            </div>
        <?php endif; ?>

        <?php if ($canAdd && !empty($rolesOrganization)): ?>
        <section aria-labelledby="invite-new-heading" class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                <h2 id="invite-new-heading" class="text-lg font-bold text-slate-900">Nouvelle invitation</h2>
                <p class="mt-1 text-sm text-slate-600">L’e-mail doit être celui que la personne utilisera pour se connecter au portail.</p>
            </div>
            <form method="post" action="<?= url('back-office/invitations') ?>" class="p-6 sm:p-8 space-y-8">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="invite-email" class="block text-sm font-semibold text-slate-800 mb-1.5">Adresse e-mail</label>
                        <input id="invite-email" type="email" name="email" required autocomplete="email"
                            class="w-full max-w-xl rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition"
                            placeholder="prenom.nom@exemple.fr">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="invite-role" class="block text-sm font-semibold text-slate-800 mb-1.5">Rôle dans l’unité</label>
                        <p class="text-xs text-slate-500 mb-2 max-w-3xl leading-relaxed">
                            Choisissez un rôle de gouvernance ou opérationnel défini pour votre communauté. Les habilitations réservées à l’équipe qui gère toute la plateforme ne sont pas proposées ici.
                        </p>
                        <select id="invite-role" name="role_id" required
                            class="w-full max-w-2xl rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                            <?php foreach (['community', 'intra'] as $ly): ?>
                                <?php if (empty($rolesByLayer[$ly])) {
                                    continue;
                                } ?>
                                <optgroup label="<?= htmlspecialchars($layerLabel($ly)) ?>">
                                    <?php foreach ($rolesByLayer[$ly] as $r): ?>
                                        <?php $optLabel = trim((string) ($r['name'] ?? '')); ?>
                                        <option value="<?= (int) ($r['id'] ?? 0) ?>"><?= htmlspecialchars($optLabel !== '' ? $optLabel : 'Rôle sans intitulé') ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                            <?php if (!empty($rolesByLayer['other'])): ?>
                                <optgroup label="Autres rôles organisationnels">
                                    <?php foreach ($rolesByLayer['other'] as $r): ?>
                                        <option value="<?= (int) ($r['id'] ?? 0) ?>"><?= htmlspecialchars(trim((string) ($r['name'] ?? '')) !== '' ? (string) ($r['name'] ?? '') : 'Rôle sans intitulé') ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50/90 to-white p-5 sm:p-6 space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-emerald-950">Préparer l’arrivée (facultatif)</h3>
                        <p class="mt-1 text-xs sm:text-sm text-emerald-900/85 leading-relaxed max-w-3xl">
                            Ces choix seront appliqués automatiquement lorsque la personne aura accepté l’invitation : affectation dans l’organigramme et fonction affichée sur la fiche personnel.
                        </p>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label for="invite-unit" class="block text-sm font-semibold text-slate-800 mb-1.5">Unité dans l’organigramme</label>
                            <select id="invite-unit" name="unit_id" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                                <option value="0">Aucune pour l’instant</option>
                                <?php foreach ($inviteUnits as $u): ?>
                                    <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($u['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="invite-assignment" class="block text-sm font-semibold text-slate-800 mb-1.5">Libellé d’affectation</label>
                            <input id="invite-assignment" type="text" name="assignment_label" maxlength="120"
                                placeholder="Ex. membre d’équipe, opérateur…"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none"
                                value="Membre">
                        </div>
                    </div>
                    <?php if (!empty($inviteJobRoleOptions)): ?>
                        <div>
                            <label for="invite-job-role" class="block text-sm font-semibold text-slate-800 mb-1.5">Fonction sur la fiche personnel</label>
                            <select id="invite-job-role" name="personnel_job_role_id" class="w-full max-w-2xl rounded-lg border border-slate-300 px-3 py-2.5 text-sm bg-white shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                                <option value="0">Aucune pour l’instant</option>
                                <?php foreach ($inviteJobRoleOptions as $jo): ?>
                                    <option value="<?= (int) ($jo['id'] ?? 0) ?>"><?= htmlspecialchars($jo['label'] ?? $jo['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Aucune fonction métier n’est encore définie. Vous pourrez en ajouter depuis le menu dédié aux rôles métier et fiches personnel, puis les associer aux prochaines invitations.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 transition-colors">
                        Envoyer l’invitation par e-mail
                    </button>
                    <span class="text-xs text-slate-500">Validité du lien : 7 jours</span>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <section aria-labelledby="invite-list-heading" class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <h2 id="invite-list-heading" class="text-lg font-bold text-slate-900">Invitations envoyées</h2>
                    <p class="mt-1 text-sm text-slate-600">Historique récent (les plus récentes en premier).</p>
                </div>
                <form method="get" action="<?= url('back-office/invitations') ?>" class="flex flex-wrap items-center gap-2">
                    <label for="invite-filter-status" class="text-sm font-medium text-slate-700">Filtrer par état</label>
                    <select id="invite-filter-status" name="status" onchange="this.form.submit()"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-800 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none">
                        <option value="" <?= $inviteFilterStatus === '' ? 'selected' : '' ?>>Tous les états</option>
                        <option value="pending" <?= $inviteFilterStatus === 'pending' ? 'selected' : '' ?>>En attente de réponse</option>
                        <option value="accepted" <?= $inviteFilterStatus === 'accepted' ? 'selected' : '' ?>>Compte rattaché</option>
                        <option value="revoked" <?= $inviteFilterStatus === 'revoked' ? 'selected' : '' ?>>Annulées</option>
                        <option value="expired" <?= $inviteFilterStatus === 'expired' ? 'selected' : '' ?>>Expirées</option>
                    </select>
                    <?php if ($inviteFilterStatus !== ''): ?>
                        <a href="<?= url('back-office/invitations') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                            Afficher tout
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($invitations)): ?>
                <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-14 text-center">
                    <p class="text-sm font-medium text-slate-700">Aucune invitation pour ce filtre.</p>
                    <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">Lorsque vous enverrez une invitation, elle apparaîtra ici avec son état et les détails prévus pour l’organigramme.</p>
                </div>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($invitations as $i): ?>
                        <?php
                        $rawStatus = (string) ($i['status'] ?? '');
                        $sp = $statusPresentation($rawStatus);
                        $pay = $payloadSummary($i['invitation_payload'] ?? null, $unitsById, $jobLabelsById);
                        $created = $formatDt(isset($i['created_at']) ? (string) $i['created_at'] : null);
                        $expires = $formatDt(isset($i['expires_at']) ? (string) $i['expires_at'] : null);
                        $inviter = trim((string) ($i['inviter_email'] ?? ''));
                        ?>
                        <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                                <div class="min-w-0 space-y-2 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-slate-900 break-all"><?= htmlspecialchars((string) ($i['email'] ?? '')) ?></span>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset <?= htmlspecialchars($sp['class']) ?>">
                                            <?= htmlspecialchars($sp['label']) ?>
                                        </span>
                                    </div>
                                    <dl class="grid gap-1 text-xs sm:text-sm text-slate-600">
                                        <?php if ($created !== ''): ?>
                                            <div><span class="font-medium text-slate-700">Envoyée le</span> <?= htmlspecialchars($created) ?></div>
                                        <?php endif; ?>
                                        <?php if ($rawStatus === 'pending' && $expires !== ''): ?>
                                            <div><span class="font-medium text-slate-700">Lien valable jusqu’au</span> <?= htmlspecialchars($expires) ?></div>
                                        <?php endif; ?>
                                        <?php if ($inviter !== ''): ?>
                                            <div><span class="font-medium text-slate-700">Invitée par</span> <?= htmlspecialchars($inviter) ?></div>
                                        <?php endif; ?>
                                    </dl>
                                    <?php if ($pay !== ''): ?>
                                        <p class="text-xs sm:text-sm text-emerald-900/90 pt-1 border-t border-emerald-100/80 mt-2">
                                            <span class="font-semibold text-emerald-950">Prévu à l’arrivée : </span><?= htmlspecialchars($pay) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($rawStatus === 'pending'): ?>
                                    <form method="post" action="<?= url('back-office/invitations/revoke') ?>"
                                        onsubmit="return confirm('Annuler cette invitation ? La personne ne pourra plus utiliser le lien reçu par e-mail.');"
                                        class="shrink-0 flex items-start">
                                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) ($i['id'] ?? 0) ?>">
                                        <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-800 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 transition-colors">
                                            Annuler l’invitation
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
