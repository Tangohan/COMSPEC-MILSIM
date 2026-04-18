<?php
declare(strict_types=1);
$enlistments = $enlistments ?? [];
$statusFilter = $statusFilter ?? null;
$counts = is_array($enlistmentCounts ?? null) ? $enlistmentCounts : [];
$nSubmitted = (int) ($counts['submitted'] ?? 0);
$nReviewed = (int) ($counts['reviewed'] ?? 0);
$nRejected = (int) ($counts['rejected'] ?? 0);
$nBlocked = (int) ($counts['blocked'] ?? 0);
$nTotal = array_sum($counts);
$enlistmentSlaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedOlderThanSla = max(0, (int) ($submittedOlderThanSla ?? 0));

$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');

$initials = static function (string $first, string $last): string {
    $a = mb_strtoupper(mb_substr(trim($first), 0, 1));
    $b = mb_strtoupper(mb_substr(trim($last), 0, 1));
    if ($a === '' && $b === '') {
        return '?';
    }

    return $a . $b;
};

$statusMeta = static function (string $st): array {
    return match ($st) {
        'submitted' => [
            'class' => 'bg-amber-50 text-amber-950 ring-amber-200/80 border-amber-200',
            'bar' => 'bg-amber-500',
            'label' => 'À traiter',
        ],
        'rejected' => [
            'class' => 'bg-rose-50 text-rose-950 ring-rose-200/80 border-rose-200',
            'bar' => 'bg-rose-500',
            'label' => 'Refusée',
        ],
        'blocked' => [
            'class' => 'bg-slate-800 text-white ring-slate-600 border-slate-700',
            'bar' => 'bg-slate-600',
            'label' => 'Non admis',
        ],
        'reviewed' => [
            'class' => 'bg-emerald-50 text-emerald-950 ring-emerald-200/80 border-emerald-200',
            'bar' => 'bg-emerald-600',
            'label' => 'Acceptée',
        ],
        default => [
            'class' => 'bg-stone-100 text-stone-800 ring-stone-200 border-stone-200',
            'bar' => 'bg-stone-400',
            'label' => 'Statut à vérifier',
        ],
    };
};

$filterLink = static function (?string $key, ?string $current, string $label, int $count, string $baseUrl): string {
    $active = ($key === null && $current === null) || ($key !== null && $current === $key);
    $href = $key === null ? $baseUrl : $baseUrl . '?status=' . rawurlencode($key);
    $cls = $active
        ? 'border-[#1c2d41] bg-[#1c2d41] text-white shadow-md shadow-[#1c2d41]/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a227] focus-visible:ring-offset-2'
        : 'border-stone-200/90 bg-white text-stone-700 hover:border-[#c9a227]/60 hover:bg-[#faf8f3] focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2';

    return sprintf(
        '<a href="%s" class="inline-flex min-h-[2.5rem] items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition %s">%s%s</a>',
        htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
        $cls,
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
        $count > 0
            ? '<span class="' . ($active ? 'bg-white/15' : 'bg-stone-100') . ' min-w-[1.5rem] rounded-lg px-2 py-0.5 text-center text-xs font-black tabular-nums">' . $count . '</span>'
            : ''
    );
};

$baseList = url('back-office/recruitments');

$submittedViaLabel = static function (string $raw): string {
    $v = strtolower(trim($raw));

    return match ($v) {
        'guest' => 'Invité',
        'account' => 'Compte connecté',
        'preset' => 'Profil enregistré',
        '' => '—',
        default => 'Autre canal',
    };
};

require base_path('views/admin/recruitment_workspace/partials/command_shell_open.php');
?>
<style>
    /* Affichage exclusif tableau / cartes (filet si utilitaires Tailwind absents ou surchargés par le layout admin). */
    .recruitment-bureau .recruitment-bureau__view-table {
        display: none;
    }
    .recruitment-bureau .recruitment-bureau__view-cards {
        display: block;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    @media (min-width: 768px) {
        .recruitment-bureau .recruitment-bureau__view-table {
            display: block;
        }
        .recruitment-bureau .recruitment-bureau__view-cards {
            display: none;
        }
    }
</style>
<div class="recruitment-bureau min-h-[calc(100vh-3.5rem)] bg-gradient-to-b from-[#ebe6dc] via-[#f5f2eb] to-[#e8e4db]">
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-8 sm:px-6 lg:px-8 lg:py-12">

        <?php if ($flashOk): ?>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/95 px-4 py-3 text-sm font-medium text-emerald-950 shadow-sm sm:px-5" role="status"><?= htmlspecialchars((string) $flashOk) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-950 shadow-sm sm:px-5" role="alert"><?= htmlspecialchars((string) $flashErr) ?></div>
        <?php endif; ?>

        <div class="overflow-hidden rounded-2xl border border-stone-300/80 bg-white shadow-[0_25px_60px_-20px_rgba(28,45,65,0.35)] ring-1 ring-black/[0.03]">
            <!-- Bandeau type « dossier service » -->
            <div class="relative bg-[#1c2d41] px-5 py-6 sm:px-8 sm:py-8">
                <div class="absolute inset-0 bg-[linear-gradient(105deg,rgba(201,162,39,0.12)_0%,transparent_45%)] pointer-events-none" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.35em] text-[#c9a227]/90">Service recrutement</p>
                        <h1 class="mt-2 font-serif text-3xl font-bold tracking-tight text-white sm:text-4xl">Dossiers de candidature</h1>
                        <p class="mt-3 max-w-xl text-sm leading-relaxed text-slate-300/95">
                            Classement et suivi des demandes d’adhésion. Chaque ligne correspond à un dossier à ouvrir, instruire et archiver selon votre procédure interne.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="<?= htmlspecialchars(url('enlistment')) ?>" class="inline-flex min-h-[2.75rem] items-center rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white backdrop-blur-sm transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1c2d41]">
                            Formulaire public
                        </a>
                        <?php if (can('invitations.send') || can('admin.organization') || can('admin.access')): ?>
                        <a href="<?= htmlspecialchars(url('back-office/invitations')) ?>" class="inline-flex min-h-[2.75rem] items-center rounded-xl border border-[#c9a227]/50 bg-[#c9a227]/15 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-[#fde68a] transition hover:bg-[#c9a227]/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#fde68a]/50 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1c2d41]">
                            Invitations
                        </a>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/messages-prefaits')) ?>" class="inline-flex min-h-[2.75rem] items-center rounded-xl border border-white/15 bg-black/20 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-200 transition hover:bg-black/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300/50 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1c2d41]">
                            Modèles de texte
                        </a>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/settings')) ?>" class="inline-flex min-h-[2.75rem] items-center rounded-xl border border-sky-300/30 bg-sky-300/10 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-sky-100 transition hover:bg-sky-300/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1c2d41]">
                            Délais d’alerte
                        </a>
                        <a href="<?= htmlspecialchars(url('back-office')) ?>" class="inline-flex min-h-[2.75rem] items-center rounded-xl border border-white/10 bg-transparent px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-400 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 focus-visible:ring-offset-[#1c2d41]">
                            ← Tableau de bord
                        </a>
                    </div>
                </div>
            </div>

            <div class="border-b border-stone-200 bg-[#faf8f3] px-4 py-6 sm:px-8 sm:py-8">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 lg:gap-5">
                    <div class="rounded-xl border border-stone-200/90 bg-white p-4 shadow-sm sm:p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-stone-500">Total dossiers</p>
                        <p class="mt-2 font-serif text-2xl font-bold text-[#1c2d41] tabular-nums"><?= $nTotal ?></p>
                    </div>
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-4 shadow-sm sm:p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-900/70">À traiter</p>
                        <p class="mt-2 font-serif text-2xl font-bold text-amber-950 tabular-nums"><?= $nSubmitted ?></p>
                    </div>
                    <div class="rounded-xl border <?= $submittedOlderThanSla > 0 ? 'border-rose-300 bg-rose-50/60' : 'border-sky-200/80 bg-sky-50/40' ?> p-4 shadow-sm sm:p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wider leading-snug <?= $submittedOlderThanSla > 0 ? 'text-rose-900/75' : 'text-sky-900/75' ?>">Sans action depuis le délai</p>
                        <p class="mt-2 font-serif text-2xl font-bold <?= $submittedOlderThanSla > 0 ? 'text-rose-950' : 'text-sky-950' ?> tabular-nums"><?= $submittedOlderThanSla ?></p>
                    </div>
                    <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4 shadow-sm sm:p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-900/70">Acceptées</p>
                        <p class="mt-2 font-serif text-2xl font-bold text-emerald-950 tabular-nums"><?= $nReviewed ?></p>
                    </div>
                    <div class="rounded-xl border border-rose-200/80 bg-rose-50/40 p-4 shadow-sm sm:p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-900/70">Refusées</p>
                        <p class="mt-2 font-serif text-2xl font-bold text-rose-950 tabular-nums"><?= $nRejected ?></p>
                    </div>
                    <div class="col-span-2 rounded-xl border border-stone-300 bg-stone-100/80 p-4 shadow-sm sm:col-span-1 sm:p-5 lg:col-span-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-stone-600">Non admis</p>
                        <p class="mt-2 font-serif text-2xl font-bold text-stone-900 tabular-nums"><?= $nBlocked ?></p>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap gap-3 border-t border-stone-200/80 pt-8">
                    <?= $filterLink(null, $statusFilter, 'Tous les dossiers', $nTotal, $baseList) ?>
                    <?= $filterLink('submitted', $statusFilter, 'À traiter', $nSubmitted, $baseList) ?>
                    <?= $filterLink('reviewed', $statusFilter, 'Acceptées', $nReviewed, $baseList) ?>
                    <?= $filterLink('rejected', $statusFilter, 'Refusées', $nRejected, $baseList) ?>
                    <?= $filterLink('blocked', $statusFilter, 'Non admis', $nBlocked, $baseList) ?>
                </div>

                <div class="mt-8 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-6">
                    <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Raccourci — délai d’alerte</p>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-stone-600">Nombre d’heures sans traitement sur un dossier <strong>à traiter</strong> avant qu’il soit signalé comme en retard dans cette liste.</p>
                    <form method="post" action="<?= htmlspecialchars(url('back-office/recruitments/settings')) ?>" class="mt-6 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                        <?= \App\Core\Csrf::field() ?>
                        <div class="min-w-[12rem]">
                            <label for="enlistment-sla-hours" class="block text-xs font-semibold text-stone-700">Heures sans action (1 à 720)</label>
                            <input
                                type="number"
                                id="enlistment-sla-hours"
                                name="enlistment_sla_hours"
                                min="1"
                                max="720"
                                value="<?= $enlistmentSlaHours ?>"
                                class="mt-2 w-full max-w-[10rem] rounded-xl border border-stone-300 bg-white px-3 py-2.5 text-sm font-semibold text-stone-900 shadow-inner focus:border-[#1c4d6e] focus:outline-none focus:ring-2 focus:ring-[#1c4d6e]/20"
                            >
                        </div>
                        <button type="submit" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl border border-[#1c2d41] bg-[#1c2d41] px-5 py-2.5 text-xs font-bold uppercase tracking-wide text-white transition hover:bg-[#152333] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a227] focus-visible:ring-offset-2">Enregistrer</button>
                        <a href="<?= htmlspecialchars(url('back-office/recruitments/settings')) ?>" class="inline-flex min-h-[2.75rem] items-center justify-center rounded-xl border border-stone-300 bg-white px-5 py-2.5 text-xs font-bold uppercase tracking-wide text-stone-700 transition hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400 focus-visible:ring-offset-2">Page détaillée</a>
                    </form>
                </div>
            </div>

            <div class="px-4 py-8 sm:px-8 sm:py-10">
                <?php if (empty($enlistments)): ?>
                    <div class="rounded-2xl border-2 border-dashed border-stone-300 bg-[#faf8f3] px-8 py-16 text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl border border-stone-300 bg-white shadow-inner">
                            <svg class="h-8 w-8 text-stone-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <p class="mt-6 font-serif text-xl font-bold text-stone-900">Aucun dossier<?= $statusFilter ? ' pour ce filtre' : '' ?></p>
                        <p class="mt-2 max-w-md mx-auto text-sm text-stone-600">
                            Les candidatures reçues depuis la page d’enrôlement apparaîtront ici. Vérifiez un autre filtre ou partagez le lien du formulaire aux candidats.
                        </p>
                        <a href="<?= htmlspecialchars(url('enlistment')) ?>" class="mt-8 inline-flex items-center rounded-xl bg-[#1c2d41] px-6 py-3 text-xs font-bold uppercase tracking-wider text-white shadow-lg shadow-[#1c2d41]/25 transition hover:bg-[#152433]">
                            Voir le formulaire public
                        </a>
                    </div>
                <?php else: ?>
                    <div class="recruitment-bureau__view-table overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
                        <table class="w-full min-w-[52rem] text-left text-sm">
                            <thead>
                                <tr class="border-b border-stone-200 bg-[#f4f1ea] text-[10px] font-bold uppercase tracking-[0.2em] text-stone-500">
                                    <th class="px-4 py-3 pl-5">Réception</th>
                                    <th class="px-4 py-3">Candidat</th>
                                    <th class="px-4 py-3">Contact</th>
                                    <th class="px-4 py-3">Indicatif</th>
                                    <th class="px-4 py-3">Lien compte</th>
                                    <th class="px-4 py-3">État</th>
                                    <th class="px-4 py-3 pr-5 text-right">Dossier</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                <?php foreach ($enlistments as $e): ?>
                                    <?php
                                    $st = (string) ($e['status'] ?? '');
                                    $meta = $statusMeta($st);
                                    $fid = (int) ($e['id'] ?? 0);
                                    $fn = (string) ($e['first_name'] ?? '');
                                    $ln = (string) ($e['last_name'] ?? '');
                                    $full = trim($fn . ' ' . $ln) ?: '—';
                                    $slaBreached = !empty($e['submitted_sla_breached']);
                                    $ageHours = isset($e['submitted_age_hours']) ? (int) $e['submitted_age_hours'] : null;
                                    ?>
                                    <tr class="transition hover:bg-[#faf8f3]/80">
                                        <td class="whitespace-nowrap px-4 py-4 pl-5 text-stone-600 tabular-nums">
                                            <?= !empty($e['created_at']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $e['created_at']))) : '—' ?>
                                            <span class="block text-xs text-stone-400"><?= !empty($e['created_at']) ? htmlspecialchars(date('H:i', strtotime((string) $e['created_at']))) : '' ?></span>
                                            <?php if ($slaBreached && $ageHours !== null): ?>
                                            <span class="mt-1 inline-flex items-center rounded-md bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-900">Retard +<?= max(0, $ageHours - $enlistmentSlaHours) ?> h</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-[#f4f1ea] text-xs font-black text-[#1c2d41]"><?= htmlspecialchars($initials($fn, $ln)) ?></span>
                                                <span class="font-semibold text-stone-900"><?= htmlspecialchars($full) ?></span>
                                            </div>
                                        </td>
                                        <td class="max-w-[200px] truncate px-4 py-4 text-stone-700" title="<?= htmlspecialchars((string) ($e['email'] ?? '')) ?>"><?= htmlspecialchars((string) ($e['email'] ?? '—')) ?></td>
                                        <td class="px-4 py-4 text-stone-600"><?= htmlspecialchars((string) ($e['callsign'] ?? '—')) ?></td>
                                        <td class="px-4 py-4 text-sm">
                                            <?php if (!empty($e['submitter_user_id'])): ?>
                                                <a href="<?= htmlspecialchars(url('personnel/' . (int) $e['submitter_user_id'])) ?>" class="font-semibold text-[#1c4d6e] underline decoration-[#1c4d6e]/30 underline-offset-2 hover:decoration-[#1c4d6e]">Fiche membre</a>
                                                <span class="mt-0.5 block text-[10px] uppercase tracking-wide text-stone-500"><?= htmlspecialchars($submittedViaLabel((string) ($e['submitted_via'] ?? ''))) ?></span>
                                            <?php else: ?>
                                                <span class="text-stone-400">Invité</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-bold ring-1 <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                                        </td>
                                        <td class="px-4 py-4 pr-5 text-right">
                                            <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $fid)) ?>" class="inline-flex items-center gap-1 rounded-lg border border-stone-200 bg-white px-3 py-2 text-xs font-bold uppercase tracking-wider text-[#1c2d41] shadow-sm transition hover:border-[#c9a227]/50 hover:bg-[#fffef9]">
                                                Ouvrir
                                                <span aria-hidden="true">→</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <ul class="recruitment-bureau__view-cards space-y-3">
                        <?php foreach ($enlistments as $e): ?>
                            <?php
                            $st = (string) ($e['status'] ?? '');
                            $meta = $statusMeta($st);
                            $fid = (int) ($e['id'] ?? 0);
                            $fn = (string) ($e['first_name'] ?? '');
                            $ln = (string) ($e['last_name'] ?? '');
                            $full = trim($fn . ' ' . $ln) ?: '—';
                            $slaBreached = !empty($e['submitted_sla_breached']);
                            $ageHours = isset($e['submitted_age_hours']) ? (int) $e['submitted_age_hours'] : null;
                            ?>
                            <li class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                                <div class="h-1 <?= htmlspecialchars($meta['bar']) ?>" aria-hidden="true"></div>
                                <div class="flex gap-4 p-4">
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-stone-200 bg-[#f4f1ea] text-sm font-black text-[#1c2d41]"><?= htmlspecialchars($initials($fn, $ln)) ?></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p class="font-bold text-stone-900"><?= htmlspecialchars($full) ?></p>
                                                <p class="text-xs text-stone-500 tabular-nums"><?= !empty($e['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $e['created_at']))) : '—' ?></p>
                                            </div>
                                            <span class="shrink-0 rounded-lg border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1 <?= htmlspecialchars($meta['class']) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                                        </div>
                                        <?php if ($slaBreached && $ageHours !== null): ?>
                                            <p class="mt-1 inline-flex items-center rounded-md bg-rose-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-900">Délai dépassé (<?= $ageHours ?> h)</p>
                                        <?php endif; ?>
                                        <p class="mt-2 truncate text-sm text-stone-600"><?= htmlspecialchars((string) ($e['email'] ?? '—')) ?></p>
                                        <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $fid)) ?>" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-[#1c2d41] py-2.5 text-xs font-bold uppercase tracking-wider text-white">Consulter le dossier</a>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <p class="mt-10 text-center text-sm text-stone-600">
            <a href="<?= htmlspecialchars(url('back-office')) ?>" class="font-semibold text-[#1c2d41] underline decoration-stone-300 underline-offset-4 transition hover:decoration-[#1c2d41] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1c4d6e] focus-visible:ring-offset-2">Retour au tableau de bord communauté</a>
        </p>
    </div>
</div>
<?php require base_path('views/admin/recruitment_workspace/partials/command_shell_close.php'); ?>
