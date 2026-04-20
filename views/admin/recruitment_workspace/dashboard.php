<?php
declare(strict_types=1);
$counts = is_array($enlistmentCounts ?? null) ? $enlistmentCounts : [];
$nSubmitted = (int) ($counts['submitted'] ?? 0);
$nTotal = array_sum($counts);
$enlistmentSlaHours = max(1, (int) ($enlistmentSlaHours ?? 72));
$submittedOlderThanSla = max(0, (int) ($submittedOlderThanSla ?? 0));
$via = is_array($submittedViaCounts ?? null) ? $submittedViaCounts : [];
$weekly = is_array($weeklyCreated ?? null) ? $weeklyCreated : [];
$topOpenings = is_array($topOpenings ?? null) ? $topOpenings : [];
$automodDossiers = is_array($automodDossiers ?? null) ? $automodDossiers : [];
$canOpenSystemRecruitmentTools = !empty($canOpenSystemRecruitmentTools);
$nPortalBlocks = is_array($portalRecruitmentBlocks ?? null) ? count($portalRecruitmentBlocks) : 0;
$maskEmail = static function (string $e): string {
    $e = trim($e);
    if ($e === '' || !str_contains($e, '@')) {
        return '—';
    }
    [$u, $d] = explode('@', $e, 2);
    $prefix = mb_substr($u, 0, 2);

    return $prefix . '•••@' . $d;
};
$viaLabel = static function (string $k): string {
    return match ($k) {
        'guest' => 'Sans compte (invité)',
        'account', 'user' => 'Compte membre',
        default => $k !== '' ? $k : 'Autre',
    };
};
$fmtDateTime = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }
    $t = strtotime($raw);

    return $t !== false ? date('d/m/Y à H:i', $t) : '—';
};
$enlistmentStatusLabel = static function (string $st): string {
    return match ($st) {
        'submitted' => 'À traiter',
        'reviewed' => 'Acceptée',
        'rejected' => 'Refusée',
        'blocked' => 'Non admis',
        default => $st !== '' ? 'Statut à vérifier' : '—',
    };
};
?>
<div class="max-w-6xl w-full space-y-8">
        <div class="lms-infobanner" role="note">
            <span class="lms-infobanner__icon" aria-hidden="true">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <p><strong>Vue d’ensemble.</strong> Les actions de traitement des dossiers se font depuis la <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="text-sky-700 font-semibold hover:underline">file des candidatures</a>.</p>
        </div>

                <header class="lms-panel rounded-[2rem] p-6 md:p-8 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-sky-500/80 via-sky-500/20 to-transparent" aria-hidden="true"></div>
                    <p class="text-[9px] font-black tracking-[0.45em] text-sky-600 uppercase mb-3">Pilotage recrutement</p>
                    <h1 class="text-3xl md:text-4xl font-black tracking-tight uppercase text-slate-900 leading-tight">Candidatures &amp; dossiers</h1>
                    <p class="text-sm text-stone-600 max-w-2xl leading-relaxed">
                        Point d’entrée unique pour suivre la file, les délais internes (SLA) et les volumes. Les actions de traitement se font depuis la file ou la fiche dossier.
                    </p>
                    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mt-8">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total dossiers</p>
                            <p class="mt-2 text-2xl font-black text-slate-900 tabular-nums"><?= $nTotal ?></p>
                        </div>
                        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/50 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-900/70">À traiter</p>
                            <p class="mt-2 text-2xl font-black text-amber-950 tabular-nums"><?= $nSubmitted ?></p>
                        </div>
                        <div class="rounded-2xl border <?= $submittedOlderThanSla > 0 ? 'border-rose-200 bg-rose-50/70' : 'border-sky-200 bg-sky-50/40' ?> p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider <?= $submittedOlderThanSla > 0 ? 'text-rose-800' : 'text-sky-800' ?>">Dépassement SLA</p>
                            <p class="mt-2 text-2xl font-black <?= $submittedOlderThanSla > 0 ? 'text-rose-900' : 'text-sky-950' ?> tabular-nums"><?= $submittedOlderThanSla ?></p>
                        </div>
                        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/40 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-900/70">SLA (heures)</p>
                            <p class="mt-2 text-2xl font-black text-emerald-900 tabular-nums"><?= $enlistmentSlaHours ?></p>
                        </div>
                    </div>
                </header>

                <section class="lms-panel rounded-[2rem] p-6 md:p-8 border border-amber-200/90 bg-amber-50/30 space-y-4" aria-labelledby="automod-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-[9px] font-black tracking-[0.35em] text-amber-800 uppercase">Modération automatique</p>
                            <h2 id="automod-heading" class="mt-2 text-lg font-black text-slate-900 tracking-tight">Dossiers concernés par le filtre du portail</h2>
                            <p class="mt-2 text-sm text-stone-700 max-w-3xl leading-relaxed">
                                Lorsqu’un texte est refusé sur le portail candidat ou par un membre de l’équipe, un blocage sur l’adresse concernée peut s’appliquer et un courriel d’alerte est envoyé aux contacts de la communauté.
                                Cette liste ne montre que les dossiers pour lesquels un <strong>blocage e-mail est encore actif</strong> (l’adresse du candidat ou celle du membre visé). Vous pouvez <strong>rétablir l’accès</strong> sur votre communauté ou <strong>solliciter l’équipe du site</strong> pour un arbitrage plus large.
                            </p>
                        </div>
                        <?php if ($canOpenSystemRecruitmentTools): ?>
                        <a href="<?= htmlspecialchars(url('admin/system/recruitment-portal-tools'), ENT_QUOTES, 'UTF-8') ?>" class="shrink-0 inline-flex items-center justify-center rounded-xl border border-sky-300 bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wide text-sky-900 hover:bg-sky-50 transition">Outil assistance site</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($nPortalBlocks > 0): ?>
                    <p class="text-xs text-amber-950/90 rounded-xl border border-amber-200 bg-white/80 px-3 py-2">
                        <span class="font-bold"><?= (int) $nPortalBlocks ?></span> blocage(s) encore actif(s) lié(s) au portail recrutement sur cette communauté (tous dossiers confondus).
                    </p>
                    <?php endif; ?>
                    <?php if ($automodDossiers === []): ?>
                    <p class="text-sm text-stone-600">Aucun blocage e-mail actif sur une adresse liée à un dossier ayant déclenché le filtre du portail. Les dossiers déjà débloqués n’apparaissent plus ici.</p>
                    <?php else: ?>
                    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
                        <table class="min-w-full text-sm text-left">
                            <thead>
                                <tr class="text-[10px] font-black uppercase tracking-wider text-stone-500 border-b border-stone-200 bg-stone-50/80">
                                    <th class="py-3 px-4">Dernière alerte</th>
                                    <th class="py-3 px-4">Création du dossier</th>
                                    <th class="py-3 px-4">Dossier</th>
                                    <th class="py-3 px-4">Contact</th>
                                    <th class="py-3 px-4">Origine</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($automodDossiers as $d): ?>
                                <?php $eid = (int) ($d['enlistment_id'] ?? 0); ?>
                                <tr class="border-b border-stone-100 last:border-0 align-top">
                                    <td class="py-3 px-4 text-stone-800 whitespace-nowrap"><?= htmlspecialchars($fmtDateTime(isset($d['mod_at']) ? (string) $d['mod_at'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 px-4 text-stone-800 whitespace-nowrap"><?= htmlspecialchars($fmtDateTime(isset($d['enlistment_created_at']) ? (string) $d['enlistment_created_at'] : null), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 px-4">
                                        <a href="<?= htmlspecialchars(url('back-office/recruitments/' . $eid . '?dossier=1'), ENT_QUOTES, 'UTF-8') ?>" class="font-bold text-sky-800 hover:underline">Dossier n°<?= $eid ?></a>
                                        <p class="text-[11px] text-stone-500 mt-0.5">Statut : <?= htmlspecialchars($enlistmentStatusLabel((string) ($d['enlistment_status'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                                    </td>
                                    <td class="py-3 px-4 text-stone-700"><?= htmlspecialchars($maskEmail((string) ($d['display_contact_email'] ?? $d['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 px-4 text-stone-700"><?= htmlspecialchars((string) ($d['moderation_side_fr'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="py-3 px-4 text-right space-y-2">
                                        <form method="post" action="<?= htmlspecialchars(url('back-office/ressources/recrutement/automod/restore-access'), ENT_QUOTES, 'UTF-8') ?>" class="inline-block text-left space-y-1" onsubmit="return confirm('Rétablir l’accès pour ce dossier sur votre communauté ?');">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="hidden" name="enlistment_id" value="<?= $eid ?>">
                                            <label class="flex items-center gap-2 text-[11px] text-stone-600 cursor-pointer">
                                                <input type="checkbox" name="also_revoke_ip" value="1" class="rounded border-stone-300">
                                                Lever aussi les blocages réseau « portail candidat »
                                            </label>
                                            <button type="submit" class="mt-1 w-full sm:w-auto inline-flex justify-center rounded-lg bg-emerald-700 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-white hover:bg-emerald-800 transition">Rétablir l’accès</button>
                                        </form>
                                        <form method="post" action="<?= htmlspecialchars(url('back-office/ressources/recrutement/automod/escalate'), ENT_QUOTES, 'UTF-8') ?>" class="block text-left space-y-1 border-t border-stone-100 pt-2 mt-2">
                                            <?= \App\Core\Csrf::field() ?>
                                            <input type="hidden" name="enlistment_id" value="<?= $eid ?>">
                                            <label class="block text-[11px] font-semibold text-stone-600">Message pour l’équipe du site (optionnel)</label>
                                            <textarea name="staff_note" rows="2" maxlength="2000" class="w-full min-w-[12rem] rounded-lg border border-stone-300 px-2 py-1.5 text-xs" placeholder="Contexte, urgence, pièces jointes à vérifier…"></textarea>
                                            <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-lg border border-sky-400 bg-sky-50 px-3 py-2 text-[10px] font-black uppercase tracking-wide text-sky-950 hover:bg-sky-100 transition">Solliciter l’équipe du site</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-stone-600">La sollicitation envoie un courriel aux personnes chargées de l’administration technique du site. Si aucun destinataire n’est trouvé, demandez à l’équipe qui gère l’hébergement d’ajouter des adresses dédiées aux escalades recrutement.</p>
                    <?php endif; ?>
                </section>

                <section class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <a href="<?= htmlspecialchars(url('back-office/recruitments'), ENT_QUOTES, 'UTF-8') ?>" class="lms-panel rounded-2xl p-5 border border-slate-200/80 block transition hover:border-sky-300/60 hover:shadow-md">
                        <p class="text-[10px] font-black uppercase tracking-wider text-sky-600">File</p>
                        <h2 class="mt-2 text-sm font-black uppercase tracking-wide text-slate-900">Ouvrir la file des dossiers</h2>
                        <p class="mt-2 text-xs text-slate-600 leading-relaxed">Filtrer par statut, consulter chaque dossier et enregistrer une décision.</p>
                    </a>
                    <a href="<?= htmlspecialchars(recruitment_workspace_url('analyses'), ENT_QUOTES, 'UTF-8') ?>" class="lms-panel rounded-2xl p-5 border border-slate-200/80 block transition hover:border-sky-300/60 hover:shadow-md">
                        <p class="text-[10px] font-black uppercase tracking-wider text-sky-600">Indicateurs</p>
                        <h2 class="mt-2 text-sm font-black uppercase tracking-wide text-slate-900">Analyses détaillées</h2>
                        <p class="mt-2 text-xs text-slate-600 leading-relaxed">Volumes par semaine, canaux de dépôt et offres les plus sollicitées.</p>
                    </a>
                    <a href="<?= htmlspecialchars(url('back-office/recruitments/settings'), ENT_QUOTES, 'UTF-8') ?>" class="lms-panel rounded-2xl p-5 border border-slate-200/80 block transition hover:border-sky-300/60 hover:shadow-md sm:col-span-2 xl:col-span-1">
                        <p class="text-[10px] font-black uppercase tracking-wider text-sky-600">Paramètres</p>
                        <h2 class="mt-2 text-sm font-black uppercase tracking-wide text-slate-900">SLA &amp; messages</h2>
                        <p class="mt-2 text-xs text-slate-600 leading-relaxed">Ajuster le délai d’alerte et préparer les modèles de texte pour le traitement.</p>
                    </a>
                </section>

                <?php if ($via !== []): ?>
                <section class="lms-panel rounded-[2rem] p-6 md:p-8 border border-slate-200/80">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-800 mb-3">Canal de transmission</h2>
                    <p class="text-xs text-stone-600 mb-4">Répartition des dossiers selon le mode de dépôt enregistré.</p>
                    <ul class="space-y-2">
                        <?php foreach ($via as $k => $n): ?>
                            <li class="flex justify-between gap-3 text-sm border-b border-stone-100 pb-2 last:border-0">
                                <span class="text-stone-700"><?= htmlspecialchars($viaLabel((string) $k), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="font-mono font-bold text-sky-800"><?= (int) $n ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <?php if ($weekly !== []): ?>
                <section class="lms-panel rounded-[2rem] p-6 md:p-8 border border-slate-200/80">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-800 mb-3">Arrivées par semaine (12 dernières)</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-left">
                            <thead>
                                <tr class="text-[10px] font-black uppercase tracking-wider text-stone-500 border-b border-stone-200">
                                    <th class="py-2 pr-4">Semaine (lundi)</th>
                                    <th class="py-2 text-right">Candidatures</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($weekly as $row): ?>
                                    <tr class="border-b border-stone-100">
                                        <td class="py-2 pr-4 text-stone-800"><?= htmlspecialchars((string) ($row['week_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="py-2 text-right font-semibold tabular-nums"><?= (int) ($row['c'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($topOpenings !== []): ?>
                <section class="lms-panel rounded-[2rem] p-6 md:p-8 border border-slate-200/80">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-800 mb-3">Offres les plus citées</h2>
                    <ul class="space-y-2">
                        <?php foreach ($topOpenings as $ro): ?>
                            <li class="flex flex-wrap justify-between gap-2 text-sm border-b border-stone-100 pb-2 last:border-0">
                                <span class="text-stone-800 font-medium"><?= htmlspecialchars((string) ($ro['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="font-mono text-stone-600"><?= (int) ($ro['c'] ?? 0) ?> dossier(s)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

</div>
