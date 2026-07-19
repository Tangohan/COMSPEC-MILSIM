<?php
declare(strict_types=1);

use App\Support\OrganizationRoleLabels;

/**
 * Zone 1 — Composer une invitation (formulaire).
 *
 * @var list<array<string, mixed>> $rolesOrganization
 * @var list<array<string, mixed>> $inviteUnits
 * @var list<array{id: int, label: string, name: string}> $inviteJobRoleOptions
 * @var bool $canAdd
 * @var string $organizationRoleLabelMode
 * @var array{pending: int, accepted: int, revoked: int, expired: int, total: int} $inviteStatusCounts
 */
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

$rolesByLayer = ['community' => [], 'intra' => [], 'other' => []];
foreach ($rolesOrganization as $r) {
    $ly = (string) ($r['role_layer'] ?? 'community');
    if ($ly === 'community' || $ly === 'intra') {
        $rolesByLayer[$ly][] = $r;
    } else {
        $rolesByLayer['other'][] = $r;
    }
}

$sentUrl = url('back-office/invitations/envoyees');
?>
<style>
.invite-role-card:has(input:checked) {
    border-color: #059669 !important;
    background: #ecfdf5 !important;
    box-shadow: 0 0 0 1px rgba(5, 150, 105, 0.35);
}
</style>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:py-10 space-y-6">

        <header class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 shadow-sm">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-emerald-800/90">Membres · Invitations</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900">Nouvelle invitation</h1>
                    <p class="mt-2 text-sm text-slate-600 max-w-xl leading-relaxed">
                        Envoyez un lien d’accès par e-mail. Le suivi des invitations déjà envoyées se gère dans le tableur dédié.
                    </p>
                </div>
                <div class="flex flex-col gap-2 shrink-0 sm:items-end">
                    <a href="<?= htmlspecialchars($sentUrl, ENT_QUOTES, 'UTF-8') ?>"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-900 bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-slate-800">
                        Invitations envoyées
                        <span class="tabular-nums text-slate-300"><?= (int) ($inviteStatusCounts['total'] ?? 0) ?></span>
                    </a>
                    <?php if ((int) ($inviteStatusCounts['pending'] ?? 0) > 0): ?>
                        <p class="text-xs text-amber-800 font-semibold sm:text-right">
                            <?= (int) $inviteStatusCounts['pending'] ?> en attente de réponse
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4 border-t border-slate-100 pt-5">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wide text-amber-700/80">En attente</dt>
                    <dd class="mt-0.5 text-xl font-black tabular-nums text-slate-900"><?= (int) $inviteStatusCounts['pending'] ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wide text-emerald-700/80">Rattachées</dt>
                    <dd class="mt-0.5 text-xl font-black tabular-nums text-slate-900"><?= (int) $inviteStatusCounts['accepted'] ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Annulées</dt>
                    <dd class="mt-0.5 text-xl font-black tabular-nums text-slate-800"><?= (int) $inviteStatusCounts['revoked'] ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Expirées</dt>
                    <dd class="mt-0.5 text-xl font-black tabular-nums text-slate-800"><?= (int) $inviteStatusCounts['expired'] ?></dd>
                </div>
            </dl>
        </header>

        <?php $f = \App\Core\Session::getFlash('error'); $s = \App\Core\Session::getFlash('success'); ?>
        <?php if ($f): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800" role="alert"><?= htmlspecialchars($f) ?></div>
        <?php endif; ?>
        <?php if ($s): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">
                <?= htmlspecialchars($s) ?>
                <a href="<?= htmlspecialchars($sentUrl, ENT_QUOTES, 'UTF-8') ?>" class="ml-2 underline underline-offset-2 font-bold">Voir le tableur →</a>
            </div>
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
        <section id="nouvelle-invitation" aria-labelledby="invite-new-heading" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-emerald-100 bg-gradient-to-r from-emerald-50/90 to-white px-6 py-5">
                <h2 id="invite-new-heading" class="text-lg font-black text-slate-900">Composer l’invitation</h2>
                <p class="mt-1 text-sm text-slate-600">Indiquez l’adresse e-mail de connexion et le rôle accordé dans l’unité.</p>
            </div>
            <form method="post" action="<?= url('back-office/invitations') ?>" class="p-6 sm:p-8 space-y-8">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

                <div>
                    <label for="invite-email" class="block text-sm font-semibold text-slate-800 mb-1.5">Adresse e-mail</label>
                    <input id="invite-email" type="email" name="email" required autocomplete="email"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none transition"
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
                                <div class="grid sm:grid-cols-2 gap-3">
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
                            <select id="invite-job-role" name="personnel_job_role_id" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm bg-white shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none">
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
                    <a href="<?= htmlspecialchars($sentUrl, ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Voir les invitations envoyées</a>
                    <span class="text-xs text-slate-500 w-full sm:w-auto">Le lien reste valable 7 jours.</span>
                </div>
            </form>
        </section>
        <?php endif; ?>
    </div>
</div>
