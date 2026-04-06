<?php
declare(strict_types=1);

/** @var array<string, mixed> $user */
/** @var array{score: int, sections_critiques: list<string>, details: array<string, bool>, missing_labels: list<string>} $completeness */
/** @var list<array<string, mixed>> $qualifications */
/** @var list<array<string, mixed>> $certificates */
/** @var string|null $next_qualification_expiration */
/** @var bool $can_view_documents */

$score = (int) ($completeness['score'] ?? 0);
$missing = $completeness['missing_labels'] ?? [];
$critical = $completeness['sections_critiques'] ?? [];

$qualStatusLabel = static function (string $s): string {
    return match ($s) {
        'valid' => 'Valide',
        'expiring' => 'Expire bientôt',
        'expired' => 'Expiré',
        'in_progress' => 'En cours',
        default => $s,
    };
};

$certStatusLabel = static function (string $s): string {
    return match ($s) {
        'valid' => 'Valide',
        'expired' => 'Expiré',
        'revoked' => 'Révoqué',
        default => $s,
    };
};

$fmtDate = static function (?string $d): string {
    if ($d === null || $d === '') {
        return '—';
    }
    $t = strtotime($d);

    return $t ? date('d/m/Y', $t) : '—';
};
?>
<div class="relative overflow-hidden bg-slate-50">
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute left-0 top-0 h-64 w-64 rounded-full bg-sky-100/80 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-slate-200/80 blur-3xl"></div>
    </div>

    <div class="mx-auto max-w-5xl px-6 py-12 lg:py-16">
        <header class="mb-10">
            <p class="text-[11px] font-black uppercase tracking-[0.35em] text-sky-700">Dossier opérateur</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900 md:text-4xl">Accréditation</h1>
            <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-600">
                Synthèse de votre profil opérationnel : complétude du dossier, qualifications enregistrées et attestations de formation.
                Les données proviennent de votre fiche personnelle et du module formations.
            </p>
            <?php if (!empty($user['id'])): ?>
            <p class="mt-4">
                <button type="button" data-community-report data-cr-type="operator_visual" data-cr-id="<?= (int) $user['id'] ?>" data-cr-summary="Signalement concernant un élément visuel ou le contenu de votre dossier opérateur." class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-[10px] font-black uppercase tracking-wide text-rose-900 hover:bg-rose-100">Signaler un problème sur ce dossier</button>
            </p>
            <?php endif; ?>
        </header>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="space-y-8">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xs font-black uppercase tracking-[0.28em] text-slate-500">Complétude du dossier</h2>
                            <p class="mt-2 text-sm text-slate-600">
                                Indicateur global basé sur identité, affectation, clearance, qualifications et contact.
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-4">
                            <div class="relative flex h-28 w-28 items-center justify-center rounded-full border-4 border-slate-100 bg-slate-50">
                                <span class="text-3xl font-black tabular-nums text-slate-900"><?= (int) $score ?><span class="text-lg text-slate-500">%</span></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($critical !== []): ?>
                    <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        <p class="font-semibold">Points critiques signalés</p>
                        <ul class="mt-2 list-inside list-disc space-y-1 text-amber-900/90">
                            <?php foreach ($critical as $c): ?>
                            <li><?= htmlspecialchars($c) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if ($missing !== []): ?>
                    <div class="mt-6">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">À compléter</p>
                        <ul class="mt-3 flex flex-wrap gap-2">
                            <?php foreach ($missing as $m): ?>
                            <li class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700"><?= htmlspecialchars($m) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php else: ?>
                    <p class="mt-6 text-sm font-medium text-emerald-700">Dossier cohérent : aucun champ obligatoire manquant d’après les règles actuelles.</p>
                    <?php endif; ?>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="<?= htmlspecialchars(url('personnel/me/edit')) ?>" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-900">
                            Modifier ma fiche
                        </a>
                        <a href="<?= htmlspecialchars(url('account')) ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-sky-300 hover:bg-sky-50">
                            Compte &amp; préférences
                        </a>
                        <?php if ($can_view_documents): ?>
                        <a href="<?= htmlspecialchars(url('documents')) ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-sky-300 hover:bg-sky-50">
                            Documents
                        </a>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h2 class="text-xs font-black uppercase tracking-[0.28em] text-slate-500">Qualifications métier</h2>
                            <?php if ($next_qualification_expiration): ?>
                            <p class="mt-2 text-sm text-slate-600">
                                Prochaine échéance enregistrée : <strong class="text-slate-900"><?= htmlspecialchars($fmtDate($next_qualification_expiration)) ?></strong>
                            </p>
                            <?php else: ?>
                            <p class="mt-2 text-sm text-slate-600">Aucune date d’expiration future enregistrée pour vos qualifications.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($qualifications === []): ?>
                    <p class="mt-6 text-sm text-slate-600">Aucune qualification n’est encore enregistrée sur votre dossier. L’état-major peut les ajouter depuis l’administration.</p>
                    <?php else: ?>
                    <div class="mt-6 overflow-x-auto">
                        <table class="w-full min-w-[520px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-black uppercase tracking-wider text-slate-500">
                                    <th class="py-3 pr-4">Qualification</th>
                                    <th class="py-3 pr-4">Niveau</th>
                                    <th class="py-3 pr-4">Statut</th>
                                    <th class="py-3 pr-4">Obtention</th>
                                    <th class="py-3">Expiration</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($qualifications as $q): ?>
                                <tr class="text-slate-800">
                                    <td class="py-3 pr-4 font-medium"><?= htmlspecialchars((string) ($q['qualification_name'] ?? '—')) ?></td>
                                    <td class="py-3 pr-4 text-slate-600"><?= htmlspecialchars((string) ($q['level'] ?? '—')) ?></td>
                                    <td class="py-3 pr-4">
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-800"><?= htmlspecialchars($qualStatusLabel((string) ($q['status'] ?? 'valid'))) ?></span>
                                    </td>
                                    <td class="py-3 pr-4 tabular-nums text-slate-600"><?= htmlspecialchars($fmtDate(isset($q['obtained_at']) ? (string) $q['obtained_at'] : null)) ?></td>
                                    <td class="py-3 tabular-nums text-slate-600"><?= htmlspecialchars($fmtDate(isset($q['expires_at']) ? (string) $q['expires_at'] : null)) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                    <h2 class="text-xs font-black uppercase tracking-[0.28em] text-slate-500">Formations &amp; attestations</h2>
                    <p class="mt-2 text-sm text-slate-600">Certificats délivrés via le catalogue formations du tenant.</p>

                    <?php if ($certificates === []): ?>
                    <p class="mt-6 text-sm text-slate-600">Aucun certificat de formation enregistré pour le moment.</p>
                    <a href="<?= htmlspecialchars(url('formations')) ?>" class="mt-4 inline-flex text-sm font-semibold text-sky-800 hover:text-sky-950 hover:underline">Ouvrir le catalogue des formations</a>
                    <?php else: ?>
                    <ul class="mt-6 divide-y divide-slate-100">
                        <?php foreach ($certificates as $c): ?>
                        <li class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($c['course_title'] ?? 'Formation')) ?></p>
                                <p class="text-xs text-slate-500">
                                    N° <?= htmlspecialchars((string) ($c['certificate_number'] ?? '—')) ?>
                                    · Émis le <?= htmlspecialchars($fmtDate(isset($c['issued_at']) ? (string) $c['issued_at'] : null)) ?>
                                    <?php if (!empty($c['expires_at'])): ?>
                                    · Exp. <?= htmlspecialchars($fmtDate((string) $c['expires_at'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-900"><?= htmlspecialchars($certStatusLabel((string) ($c['status'] ?? 'valid'))) ?></span>
                                <?php if (!empty($c['course_slug'])): ?>
                                <a href="<?= htmlspecialchars(url('formations/' . rawurlencode((string) $c['course_slug']))) ?>" class="text-xs font-semibold text-sky-700 hover:underline">Voir la formation</a>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?= htmlspecialchars(url('formations/mes-formations')) ?>" class="mt-4 inline-flex text-sm font-semibold text-sky-800 hover:text-sky-950 hover:underline">Mes formations</a>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="space-y-6 lg:sticky lg:top-28">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400">Opérateur</p>
                    <p class="mt-3 text-lg font-black text-slate-900"><?= htmlspecialchars((string) ($user['display_name'] ?? '')) ?></p>
                    <?php if (!empty($user['callsign'])): ?>
                    <p class="mt-1 text-sm font-medium text-sky-800"><?= htmlspecialchars((string) $user['callsign']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50/90 p-6 text-sm text-slate-600">
                    <p class="font-semibold text-slate-800">À propos</p>
                    <p class="mt-2 leading-relaxed">
                        Cette page centralise l’état d’accréditation individuelle. Les mises à jour de qualifications côté état-major apparaissent ici après enregistrement.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</div>
