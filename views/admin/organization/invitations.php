<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

/** @var list<array<string, mixed>> $invitations */
/** @var list<array<string, mixed>> $rolesOrganization */
/** @var list<array<string, mixed>> $inviteUnits */
/** @var list<array{id: int, label: string, name: string}> $inviteJobRoleOptions */
/** @var bool $canAdd */
/** @var string $inviteFilterStatus */
/** @var string $organizationRoleLabelMode */
/** @var array{pending: int, accepted: int, revoked: int, expired: int, total: int} $inviteStatusCounts */
$inviteFilterStatus = $inviteFilterStatus ?? '';
$rolesOrganization = $rolesOrganization ?? [];
$inviteUnits = $inviteUnits ?? [];
$inviteJobRoleOptions = $inviteJobRoleOptions ?? [];
$organizationRoleLabelMode = $organizationRoleLabelMode ?? OrganizationRoleLabels::MODE_FR;
$inviteStatusCounts = $inviteStatusCounts ?? [
    'pending' => 0,
    'accepted' => 0,
    'revoked' => 0,
    'expired' => 0,
    'total' => 0,
];

$statusPresentation = static function (string $raw): array {
    return match ($raw) {
        'pending' => [
            'label' => 'En attente',
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
        return '—';
    }
    $t = strtotime($mysql);

    return $t ? date('d/m/Y H:i', $t) : '—';
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
        $parts[] = $unitsById[$uid] . ($lab !== '' ? ' — ' . $lab : '');
    }
    $jid = isset($d['personnel_job_role_id']) ? (int) $d['personnel_job_role_id'] : 0;
    if ($jid > 0 && isset($jobLabelsById[$jid])) {
        $parts[] = $jobLabelsById[$jid];
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

$filterTabs = [
    '' => ['label' => 'Toutes', 'count' => (int) ($inviteStatusCounts['total'] ?? 0)],
    'pending' => ['label' => 'En attente', 'count' => (int) ($inviteStatusCounts['pending'] ?? 0)],
    'accepted' => ['label' => 'Rattachées', 'count' => (int) ($inviteStatusCounts['accepted'] ?? 0)],
    'revoked' => ['label' => 'Annulées', 'count' => (int) ($inviteStatusCounts['revoked'] ?? 0)],
    'expired' => ['label' => 'Expirées', 'count' => (int) ($inviteStatusCounts['expired'] ?? 0)],
];
$baseInviteUrl = url('back-office/invitations');
?>
<style>
.invite-sheet { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; }
.invite-sheet thead th {
    position: sticky; top: 0; z-index: 1;
    background: #0f172a; color: #f8fafc;
    font-size: 0.65rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase;
    text-align: left; padding: 0.7rem 0.85rem; white-space: nowrap;
    border-bottom: 1px solid #1e293b;
}
.invite-sheet thead th.num { text-align: right; }
.invite-sheet tbody td {
    padding: 0.75rem 0.85rem; vertical-align: middle;
    border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9;
    background: #fff; color: #0f172a;
}
.invite-sheet tbody td:last-child { border-right: none; }
.invite-sheet tbody tr:nth-child(even) td { background: #f8fafc; }
.invite-sheet tbody tr:hover td { background: #ecfdf5; }
.invite-sheet tbody tr:last-child td { border-bottom: none; }
.invite-sheet .num { text-align: right; font-variant-numeric: tabular-nums; }
.invite-sheet .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.75rem; }
.invite-role-card:has(input:checked) {
    border-color: #059669 !important;
    background: #ecfdf5 !important;
    box-shadow: 0 0 0 1px rgba(5, 150, 105, 0.35);
}
</style>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 lg:py-12 space-y-8">

        <header class="relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-emerald-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-8 py-7 lg:py-8 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Back-office · Communauté</p>
                    <h1 class="mt-2 text-2xl lg:text-3xl font-black tracking-tight text-slate-900">Invitations</h1>
                    <p class="mt-2 text-sm text-slate-600 max-w-2xl leading-relaxed">
                        Invitez des personnes à rejoindre votre unité : elles reçoivent un message avec un lien pour créer leur accès ou rattacher un compte existant, avec le rôle que vous choisissez.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <?php if ($canAdd && !empty($rolesOrganization)): ?>
                        <a href="#nouvelle-invitation" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Nouvelle invitation</a>
                        <?php endif; ?>
                        <a href="<?= url('back-office/users') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Voir les membres</a>
                        <a href="<?= url('back-office') ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Retour back-office</a>
                    </div>
                </div>
                <div class="shrink-0 w-full lg:w-72 rounded-xl border border-slate-200/80 bg-white/90 p-4 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Aperçu</p>
                    <dl class="grid grid-cols-2 gap-3">
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-amber-700/80">En attente</dt>
                            <dd class="mt-0.5 text-2xl font-black tabular-nums text-slate-900"><?= (int) $inviteStatusCounts['pending'] ?></dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-emerald-700/80">Rattachées</dt>
                            <dd class="mt-0.5 text-2xl font-black tabular-nums text-slate-900"><?= (int) $inviteStatusCounts['accepted'] ?></dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Annulées</dt>
                            <dd class="mt-0.5 text-lg font-black tabular-nums text-slate-800"><?= (int) $inviteStatusCounts['revoked'] ?></dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Expirées</dt>
                            <dd class="mt-0.5 text-lg font-black tabular-nums text-slate-800"><?= (int) $inviteStatusCounts['expired'] ?></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </header>

        <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
        <?php if ($f): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert"><?= htmlspecialchars($f) ?></div>
        <?php endif; ?>
        <?php if ($s): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status"><?= htmlspecialchars($s) ?></div>
        <?php endif; ?>

        <?php if (!$canAdd): ?>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                Votre formule actuelle limite le nombre de membres. Passez à une offre supérieure pour envoyer de nouvelles invitations.
            </div>
        <?php endif; ?>

        <?php if ($canAdd && empty($rolesOrganization)): ?>
            <div class="rounded-xl border border-amber-200 bg-white px-5 py-4 text-sm text-amber-950 shadow-sm">
                Aucun rôle n’est encore disponible pour votre communauté. Configurez d’abord les rôles dans le back-office, ou contactez une personne administratrice si le problème persiste.
            </div>
        <?php endif; ?>

        <?php if ($canAdd && !empty($rolesOrganization)): ?>
        <section id="nouvelle-invitation" aria-labelledby="invite-new-heading" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-emerald-100 bg-gradient-to-r from-emerald-50/90 to-white px-6 py-5">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-800/80">Étape 1</p>
                <h2 id="invite-new-heading" class="mt-1 text-lg font-black text-slate-900">Nouvelle invitation</h2>
                <p class="mt-1 text-sm text-slate-600">Indiquez l’adresse e-mail de connexion et le rôle accordé dans l’unité.</p>
            </div>
            <form method="post" action="<?= url('back-office/invitations') ?>" class="p-6 sm:p-8 space-y-8">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                <div>
                    <label for="invite-email" class="block text-sm font-semibold text-slate-800 mb-1.5">Adresse e-mail</label>
                    <input id="invite-email" type="email" name="email" required autocomplete="email"
                        class="w-full max-w-xl rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition"
                        placeholder="prenom.nom@exemple.fr">
                    <p class="mt-1.5 text-xs text-slate-500">Celle que la personne utilisera pour se connecter au portail.</p>
                </div>

                <div>
                    <span class="block text-sm font-semibold text-slate-800 mb-1.5">Rôle dans l’unité</span>
                    <p class="text-xs text-slate-500 mb-4 max-w-3xl leading-relaxed">
                        Choisissez un rôle de gouvernance ou opérationnel. Les habilitations réservées à l’équipe plateforme ne sont pas proposées ici.
                    </p>
                    <div class="space-y-6" role="radiogroup" aria-label="Rôle dans l’unité">
                        <?php $firstRoleRadio = true; ?>
                        <?php foreach (['community', 'intra', 'other'] as $ly): ?>
                            <?php if (empty($rolesByLayer[$ly])) {
                                continue;
                            } ?>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500 mb-3"><?= htmlspecialchars(OrganizationRoleLabels::layerGroupLabel($ly, $organizationRoleLabelMode)) ?></p>
                                <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
                                    <?php foreach ($rolesByLayer[$ly] as $r): ?>
                                        <?php
                                        $rid = (int) ($r['id'] ?? 0);
                                        $disp = OrganizationRoleLabels::displayName($r, $organizationRoleLabelMode);
                                        $rdesc = trim((string) ($r['description'] ?? ''));
                                        ?>
                                        <label class="invite-role-card flex items-start gap-3 cursor-pointer rounded-xl border border-slate-200 bg-white p-3.5 text-sm shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50/40">
                                            <input type="radio" name="role_id" value="<?= $rid ?>" class="mt-0.5 shrink-0 border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                                <?= $firstRoleRadio ? 'required' : '' ?>>
                                            <?php $firstRoleRadio = false; ?>
                                            <span class="min-w-0">
                                                <span class="font-semibold text-slate-900 leading-snug"><?= htmlspecialchars($disp !== '' ? $disp : 'Rôle sans intitulé') ?></span>
                                                <?php if ($rdesc !== ''): ?>
                                                    <span class="block text-xs text-slate-500 mt-1 leading-relaxed"><?= htmlspecialchars($rdesc) ?></span>
                                                <?php endif; ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50/90 to-white p-5 sm:p-6 space-y-5">
                    <div>
                        <h3 class="text-sm font-black text-emerald-950">Préparer l’arrivée <span class="font-semibold text-emerald-800/70">(facultatif)</span></h3>
                        <p class="mt-1 text-xs sm:text-sm text-emerald-900/85 leading-relaxed max-w-3xl">
                            Appliqué automatiquement lorsque la personne aura accepté : affectation dans l’organigramme et fonction sur la fiche personnel.
                        </p>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label for="invite-unit" class="block text-sm font-semibold text-slate-800 mb-1.5">Unité dans l’organigramme</label>
                            <select id="invite-unit" name="unit_id" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm bg-white shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
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
                                class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                                value="Membre">
                        </div>
                    </div>
                    <?php if (!empty($inviteJobRoleOptions)): ?>
                        <div>
                            <label for="invite-job-role" class="block text-sm font-semibold text-slate-800 mb-1.5">Fonction sur la fiche personnel</label>
                            <select id="invite-job-role" name="personnel_job_role_id" class="w-full max-w-2xl rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm bg-white shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
                                <option value="0">Aucune pour l’instant</option>
                                <?php foreach ($inviteJobRoleOptions as $jo): ?>
                                    <option value="<?= (int) ($jo['id'] ?? 0) ?>"><?= htmlspecialchars($jo['label'] ?? $jo['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Aucune fonction métier n’est encore définie. Vous pourrez en ajouter depuis le menu dédié, puis les associer aux prochaines invitations.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-1 border-t border-slate-100">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-600/20 hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 transition-colors">
                        <svg class="h-4 w-4 opacity-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Envoyer l’invitation
                    </button>
                    <span class="text-xs text-slate-500">Le lien reste valable 7 jours.</span>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <section aria-labelledby="invite-list-heading" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 bg-slate-50/80 px-5 sm:px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="invite-list-heading" class="text-sm font-black uppercase tracking-[0.12em] text-slate-800">Invitations envoyées</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        <?= count($invitations) ?> affichée<?= count($invitations) > 1 ? 's' : '' ?>
                        <?php if ($inviteFilterStatus !== ''): ?> · filtre actif<?php endif; ?>
                        · les plus récentes en premier
                    </p>
                </div>
                <nav class="flex flex-wrap gap-1.5" aria-label="Filtrer par état">
                    <?php foreach ($filterTabs as $fkey => $ftab):
                        $isActive = $inviteFilterStatus === $fkey;
                        $href = $fkey === '' ? $baseInviteUrl : $baseInviteUrl . '?status=' . rawurlencode($fkey);
                        ?>
                        <a href="<?= htmlspecialchars($href) ?>"
                            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide shadow-sm transition <?= $isActive ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' ?>"
                            <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <?= htmlspecialchars($ftab['label']) ?>
                            <span class="tabular-nums opacity-80"><?= (int) $ftab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="overflow-x-auto">
                <table class="invite-sheet min-w-[56rem]">
                    <thead>
                        <tr>
                            <th style="width:2.25rem">#</th>
                            <th>Personne invitée</th>
                            <th>État</th>
                            <th>Rôle prévu</th>
                            <th>À l’arrivée</th>
                            <th>Envoyée</th>
                            <th>Valable jusqu’au</th>
                            <th>Invitée par</th>
                            <th class="num">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($invitations)): ?>
                        <tr>
                            <td colspan="9" class="!bg-white px-4 py-14 text-center">
                                <p class="text-sm font-semibold text-slate-700">Aucune invitation pour ce filtre.</p>
                                <p class="mt-1.5 text-sm text-slate-500 max-w-md mx-auto">Lorsque vous enverrez une invitation, elle apparaîtra ici avec son état et les détails prévus pour l’organigramme.</p>
                                <?php if ($canAdd && !empty($rolesOrganization)): ?>
                                <a href="#nouvelle-invitation" class="mt-4 inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-emerald-700">Créer une invitation</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invitations as $idx => $i):
                            $rawStatus = (string) ($i['status'] ?? '');
                            $sp = $statusPresentation($rawStatus);
                            $pay = $payloadSummary($i['invitation_payload'] ?? null, $unitsById, $jobLabelsById);
                            $roleLabel = OrganizationRoleLabels::displayName([
                                'name' => $i['role_name'] ?? '',
                                'label_en' => $i['role_label_en'] ?? '',
                            ], $organizationRoleLabelMode);
                            if ($roleLabel === '' || $roleLabel === '—') {
                                $roleLabel = '—';
                            }
                            $created = $formatDt(isset($i['created_at']) ? (string) $i['created_at'] : null);
                            $expires = $rawStatus === 'pending'
                                ? $formatDt(isset($i['expires_at']) ? (string) $i['expires_at'] : null)
                                : '—';
                            $inviter = trim((string) ($i['inviter_email'] ?? ''));
                            ?>
                            <tr>
                                <td class="num text-slate-400"><?= (int) ($idx + 1) ?></td>
                                <td class="font-semibold break-all"><?= htmlspecialchars((string) ($i['email'] ?? '')) ?></td>
                                <td>
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?= htmlspecialchars($sp['class']) ?>">
                                        <?= htmlspecialchars($sp['label']) ?>
                                    </span>
                                </td>
                                <td class="text-slate-700"><?= htmlspecialchars($roleLabel) ?></td>
                                <td class="text-slate-600 max-w-[14rem]">
                                    <?php if ($pay !== ''): ?>
                                        <?= htmlspecialchars($pay) ?>
                                    <?php else: ?>
                                        <span class="text-slate-400">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($created) ?></td>
                                <td class="mono text-slate-500 whitespace-nowrap"><?= htmlspecialchars($expires) ?></td>
                                <td class="text-slate-600 break-all"><?= $inviter !== '' ? htmlspecialchars($inviter) : '—' ?></td>
                                <td class="num">
                                    <?php if ($rawStatus === 'pending'): ?>
                                    <form method="post" action="<?= url('back-office/invitations/revoke') ?>"
                                        onsubmit="return confirm('Annuler cette invitation ? La personne ne pourra plus utiliser le lien reçu par e-mail.');"
                                        class="inline">
                                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) ($i['id'] ?? 0) ?>">
                                        <button type="submit" class="inline-flex rounded-md border border-rose-200 bg-white px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-rose-800 hover:bg-rose-50">
                                            Annuler
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-slate-300">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
