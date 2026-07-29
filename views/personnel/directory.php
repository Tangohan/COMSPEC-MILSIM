<?php
declare(strict_types=1);

/**
 * Annuaire du personnel — tableau dense (grade, affectation, rôles, ancienneté, médailles…).
 *
 * @var string $query
 * @var list<array<string,mixed>> $results
 * @var array<int, list<array<string,mixed>>> $rolesByUserId
 * @var array<int, list<array{name: string, description: ?string, icon_url: ?string, granted_at: ?string}>> $badgesByUserId
 * @var bool $canEditPersonnel
 * @var int $currentUserId
 * @var string $tenantName
 * @var bool $canAccessEffectifsLms
 */

$query = trim((string) ($query ?? ''));
$results = is_array($results ?? null) ? $results : [];
$rolesByUserId = is_array($rolesByUserId ?? null) ? $rolesByUserId : [];
$badgesByUserId = is_array($badgesByUserId ?? null) ? $badgesByUserId : [];
$canEditPersonnel = !empty($canEditPersonnel);
$currentUserId = (int) ($currentUserId ?? 0);
$tenantName = trim((string) ($tenantName ?? ''));
$canAccessEffectifsLms = !empty($canAccessEffectifsLms);
$canSeeInactiveDirectory = !empty($canSeeInactiveDirectory);

$heroImageRel = 'assets/images/fog-team.jpg';
if (!is_file(base_path('public/' . $heroImageRel))) {
    $heroImageRel = 'assets/images/night-team.jpg';
}
$heroHasImage = is_file(base_path('public/' . $heroImageRel));
$heroImageUrl = $heroHasImage ? asset_url($heroImageRel) : '';
$effectifsUrl = function_exists('effectifs_workspace_url')
    ? effectifs_workspace_url()
    : url('back-office/ressources/effectifs');
$brandLine = $tenantName !== '' ? $tenantName : 'Athena';

$statusLabel = static function (string $raw): string {
    return match ($raw) {
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'pending_verification' => 'En attente de vérification de l’e-mail',
        default => $raw !== '' ? $raw : 'Statut inconnu',
    };
};
$statusClasses = static function (string $raw): string {
    return match ($raw) {
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'pending_verification' => 'bg-amber-50 text-amber-700 ring-amber-200',
        default => 'bg-slate-100 text-slate-600 ring-slate-200',
    };
};

$roleColorClasses = [
    'slate' => 'bg-slate-100 text-slate-700 ring-slate-200/90',
    'blue' => 'bg-blue-50 text-blue-800 ring-blue-200/90',
    'indigo' => 'bg-indigo-50 text-indigo-800 ring-indigo-200/90',
    'emerald' => 'bg-emerald-50 text-emerald-800 ring-emerald-200/90',
    'amber' => 'bg-amber-50 text-amber-900 ring-amber-200/90',
    'red' => 'bg-red-50 text-red-800 ring-red-200/90',
    'purple' => 'bg-purple-50 text-purple-800 ring-purple-200/90',
];

$seniorityLabel = static function (?string $dateStr): ?array {
    $dateStr = trim((string) $dateStr);
    if ($dateStr === '') {
        return null;
    }
    try {
        $start = new DateTimeImmutable($dateStr);
    } catch (\Throwable) {
        return null;
    }
    $now = new DateTimeImmutable('now');
    if ($start > $now) {
        return null;
    }
    $diff = $now->diff($start);
    if ($diff->y > 0 && $diff->m > 0) {
        $label = $diff->y . ' an' . ($diff->y > 1 ? 's' : '') . ' ' . $diff->m . ' mois';
    } elseif ($diff->y > 0) {
        $label = $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
    } elseif ($diff->m > 0) {
        $label = $diff->m . ' mois';
    } else {
        $label = 'Moins d’un mois';
    }

    return ['label' => $label, 'since' => $start->format('d/m/Y')];
};

$gradeLabelFor = static function (array $row): string {
    $override = trim((string) ($row['rank_display_override'] ?? ''));
    if ($override !== '') {
        return $override;
    }
    $rp = trim((string) ($row['rank_display'] ?? ''));
    if ($rp !== '') {
        return $rp;
    }
    $long = trim((string) ($row['grade_long'] ?? ''));
    if ($long !== '') {
        return $long;
    }

    return trim((string) ($row['grade_short'] ?? ''));
};

$initialsOf = static function (string $label): string {
    $label = trim($label);

    return $label !== '' ? mb_strtoupper(mb_substr($label, 0, 1, 'UTF-8'), 'UTF-8') : '?';
};

$totalResults = count($results);
?>
<section class="w-full">
    <header class="relative overflow-hidden border-b border-slate-700/50 bg-slate-900" aria-labelledby="personnel-directory-hero-title">
        <?php if ($heroHasImage): ?>
        <img
            src="<?= htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') ?>"
            alt=""
            class="pointer-events-none absolute inset-0 h-full w-full scale-110 object-cover object-center blur-[12px]"
            width="1600"
            height="720"
            decoding="async"
            fetchpriority="high"
        >
        <?php endif; ?>
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-slate-950/96 via-slate-900/90 to-emerald-950/60" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image:radial-gradient(circle at 18% 22%, #fff 0.55px, transparent 0.65px), radial-gradient(circle at 82% 68%, #fff 0.55px, transparent 0.65px); background-size:18px 18px" aria-hidden="true"></div>

        <div class="relative z-10 mx-auto w-full max-w-[96rem] px-4 py-10 sm:px-6 md:px-8 md:py-14 lg:px-10 xl:px-12">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[10px] font-black uppercase tracking-[0.32em] text-emerald-300/95"><?= htmlspecialchars($brandLine, ENT_QUOTES, 'UTF-8') ?> · Effectifs</p>
                    <h1 id="personnel-directory-hero-title" class="mt-3 text-3xl font-black tracking-tight text-white sm:text-4xl md:text-[2.65rem] md:leading-[1.05]">
                        Annuaire du personnel
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-300 md:text-base">
                        Consultez grades, affectations, rôles, ancienneté et médailles des membres de votre communauté.
                        Recherchez par nom, indicatif ou nom de personnage.
                    </p>
                    <?php if ($canAccessEffectifsLms): ?>
                    <div class="mt-6">
                        <a
                            href="<?= htmlspecialchars($effectifsUrl, ENT_QUOTES, 'UTF-8') ?>"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-5 py-3 text-sm font-bold text-slate-950 shadow-lg shadow-emerald-950/30 transition hover:bg-emerald-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                        >
                            Ouvrir le bureau effectifs
                            <span aria-hidden="true">→</span>
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="mt-6">
                        <a
                            href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>"
                            class="inline-flex items-center justify-center rounded-2xl border border-white/20 bg-white/10 px-5 py-3 text-sm font-bold text-white transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300/80 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                        >
                            Voir mon dossier
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <form method="get" action="<?= htmlspecialchars(url('personnel'), ENT_QUOTES, 'UTF-8') ?>" class="w-full max-w-xl shrink-0 rounded-3xl border border-white/10 bg-slate-950/55 p-4 shadow-2xl shadow-black/40 backdrop-blur-md sm:p-5">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Recherche</p>
                    <label class="sr-only" for="personnel-directory-q">Recherche annuaire</label>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                        <input id="personnel-directory-q" name="q" type="search" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom, indicatif, personnage…" class="w-full rounded-2xl border border-white/15 bg-slate-900/80 px-4 py-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-emerald-400/60 focus:ring-4 focus:ring-emerald-500/20">
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-900 transition hover:bg-emerald-300">Rechercher</button>
                    </div>
                    <?php if ($query !== ''): ?>
                    <a href="<?= htmlspecialchars(url('personnel'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3 inline-flex text-xs font-semibold text-emerald-300/90 hover:text-emerald-200">Réinitialiser la recherche</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </header>

    <div class="mx-auto w-full max-w-[96rem] px-4 py-6 sm:px-6 md:px-8 md:py-8 lg:px-10 xl:px-12">
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm font-semibold text-slate-700">
                <?= (int) $totalResults ?> profil<?= $totalResults > 1 ? 's' : '' ?> <?= $query !== '' ? 'trouvé' . ($totalResults > 1 ? 's' : '') . ' pour « ' . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . ' »' : 'dans l’annuaire' ?>
            </p>
            <p class="text-xs text-slate-500">Survolez les pastilles « +N » (rôles, médailles) pour voir le détail complet.</p>
        </div>

        <?php if ($results === []): ?>
        <p class="px-6 py-10 text-center text-sm text-slate-500">Aucun profil trouvé pour cette recherche.</p>
        <?php else: ?>
        <div class="max-h-[75vh] overflow-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="sticky top-0 z-10 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Membre</th>
                        <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Grade &amp; matricule</th>
                        <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Affectation</th>
                        <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Rôles</th>
                        <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Indicatif / radio</th>
                        <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Ancienneté</th>
                        <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Médailles</th>
                        <?php if ($canSeeInactiveDirectory): ?>
                        <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Statut</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-right text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php foreach ($results as $row):
                        $uid = (int) ($row['id'] ?? 0);
                        if ($uid < 1) {
                            continue;
                        }
                        $displayName = trim((string) ($row['display_name'] ?? 'Profil sans nom'));
                        $fullName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                        $callsign = trim((string) ($row['callsign'] ?? ''));
                        $athenaId = trim((string) ($row['athena_identifier'] ?? ''));
                        $slug = trim((string) ($row['profile_slug'] ?? ''));
                        $character = trim((string) ($row['character_name'] ?? ''));
                        $avatar = function_exists('user_media_public_url')
                            ? (user_media_public_url($row['avatar_url'] ?? null) ?? '')
                            : trim((string) ($row['avatar_url'] ?? ''));
                        $target = $slug !== '' ? $slug : (string) $uid;

                        $gradeLabel = $gradeLabelFor($row);
                        $matricule = trim((string) ($row['matricule_internal'] ?? '')) ?: trim((string) ($row['service_number'] ?? ''));

                        $unitName = trim((string) ($row['unit_name'] ?? ''));
                        $primaryRole = trim((string) ($row['primary_role'] ?? ''));

                        $radioAssigned = trim((string) ($row['radio_assigned'] ?? ''));

                        $enlistmentRaw = $row['enlistment_date'] ?? $row['date_of_enlistment'] ?? null;
                        $seniority = $seniorityLabel(is_string($enlistmentRaw) ? $enlistmentRaw : null);

                        $roles = $rolesByUserId[$uid] ?? [];
                        $badges = $badgesByUserId[$uid] ?? [];

                        $status = trim((string) ($row['status'] ?? ''));
                    ?>
                    <tr class="align-top hover:bg-slate-50/80">
                        <td class="px-4 py-3">
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                    <?php if ($avatar !== ''): ?>
                                    <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="Photo de compte de <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" class="h-full w-full object-cover" loading="lazy" decoding="async" data-img-fallback="avatar" data-img-initials="<?= htmlspecialchars($initialsOf($displayName), ENT_QUOTES, 'UTF-8') ?>" data-img-label="Photo de compte indisponible">
                                    <?php else: ?>
                                    <div class="flex h-full w-full items-center justify-center text-xs font-bold text-slate-400"><?= htmlspecialchars($initialsOf($displayName), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-900"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if ($fullName !== ''): ?>
                                    <p class="truncate text-xs text-slate-500">Prénom / nom : <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($character !== ''): ?>
                                    <p class="truncate text-xs text-slate-500">RP : <?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($canSeeInactiveDirectory && $athenaId !== ''): ?>
                                    <p class="mt-0.5 truncate text-[10px] font-medium text-slate-300" title="Identifiant interne réservé à l’encadrement">Réf. <?= htmlspecialchars($athenaId, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <?php if ($gradeLabel !== ''): ?>
                            <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($gradeLabel, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php else: ?>
                            <p class="text-sm italic text-slate-400">Grade non renseigné</p>
                            <?php endif; ?>
                            <p class="mt-0.5 font-mono text-xs text-slate-500"><?= $matricule !== '' ? htmlspecialchars($matricule, ENT_QUOTES, 'UTF-8') : '—' ?></p>
                        </td>

                        <td class="px-4 py-3">
                            <?php if ($unitName !== ''): ?>
                            <p class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($unitName, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php else: ?>
                            <span class="inline-flex items-center rounded-full border border-dashed border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-400">Non affecté</span>
                            <?php endif; ?>
                            <?php if ($primaryRole !== ''): ?>
                            <p class="mt-0.5 truncate text-xs text-slate-500"><?= htmlspecialchars($primaryRole, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3">
                            <?php if ($roles === []): ?>
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-400">Aucun rôle</span>
                            <?php else: ?>
                                <?php
                                $maxPills = 2;
                                $visibleRoles = array_slice($roles, 0, $maxPills);
                                $extraCount = count($roles) - count($visibleRoles);
                                $allRoleNames = implode(' · ', array_map(static fn (array $r): string => (string) ($r['name'] ?? ''), $roles));
                                ?>
                                <div class="flex max-w-[13rem] flex-wrap items-center gap-1" title="<?= htmlspecialchars($allRoleNames, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($visibleRoles as $roleRow):
                                        $roleName = trim((string) ($roleRow['name'] ?? ''));
                                        $badgeStyle = [];
                                        $rawStyle = $roleRow['badge_style'] ?? null;
                                        if (is_string($rawStyle) && $rawStyle !== '') {
                                            $decoded = json_decode($rawStyle, true);
                                            $badgeStyle = is_array($decoded) ? $decoded : [];
                                        }
                                        $color = trim((string) ($badgeStyle['color'] ?? 'slate'));
                                        $classes = $roleColorClasses[$color] ?? $roleColorClasses['slate'];
                                    ?>
                                    <span class="inline-flex max-w-full items-center truncate rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset <?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php endforeach; ?>
                                    <?php if ($extraCount > 0): ?>
                                    <span class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-bold text-slate-500">+<?= (int) $extraCount ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3">
                            <?php if ($callsign !== ''): ?>
                            <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($callsign, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php else: ?>
                            <p class="text-sm text-slate-400">—</p>
                            <?php endif; ?>
                            <?php if ($radioAssigned !== ''): ?>
                            <p class="mt-0.5 truncate text-xs text-slate-500">Radio : <?= htmlspecialchars($radioAssigned, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3">
                            <?php if ($seniority !== null): ?>
                            <p class="text-sm font-semibold text-slate-800" title="Depuis le <?= htmlspecialchars($seniority['since'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($seniority['label'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-[11px] text-slate-400">depuis le <?= htmlspecialchars($seniority['since'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php else: ?>
                            <p class="text-sm text-slate-400">—</p>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3">
                            <?php if ($badges === []): ?>
                            <span class="text-sm text-slate-400">—</span>
                            <?php else: ?>
                                <?php
                                $maxMedals = 3;
                                $visibleBadges = array_slice($badges, 0, $maxMedals);
                                $extraMedals = count($badges) - count($visibleBadges);
                                $allBadgeNames = implode(' · ', array_map(static fn (array $b): string => (string) ($b['name'] ?? ''), $badges));
                                ?>
                                <div class="flex items-center gap-1" title="<?= htmlspecialchars($allBadgeNames, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($visibleBadges as $badgeRow):
                                        $badgeName = trim((string) ($badgeRow['name'] ?? ''));
                                        $iconUrl = function_exists('user_media_public_url')
                                            ? (user_media_public_url($badgeRow['icon_url'] ?? null) ?? '')
                                            : trim((string) ($badgeRow['icon_url'] ?? ''));
                                    ?>
                                    <?php if ($iconUrl !== ''): ?>
                                    <img src="<?= htmlspecialchars($iconUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($badgeName !== '' ? $badgeName : 'Insigne', ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($badgeName, ENT_QUOTES, 'UTF-8') ?>" class="h-6 w-6 rounded-full border border-slate-200 object-cover" loading="lazy" decoding="async" data-img-fallback="badge" data-img-initials="<?= htmlspecialchars($initialsOf($badgeName), ENT_QUOTES, 'UTF-8') ?>" data-img-label="Insigne indisponible">
                                    <?php else: ?>
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-[10px] font-black text-amber-800 ring-1 ring-amber-200" title="<?= htmlspecialchars($badgeName, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($initialsOf($badgeName), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if ($extraMedals > 0): ?>
                                    <span class="inline-flex h-6 items-center rounded-full border border-slate-200 bg-white px-1.5 text-[11px] font-bold text-slate-500">+<?= (int) $extraMedals ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <?php if ($canSeeInactiveDirectory): ?>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset <?= htmlspecialchars($statusClasses($status), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <?php endif; ?>

                        <td class="px-4 py-3 text-right">
                            <?php
                            $canEditThisRow = $canEditPersonnel || ($currentUserId > 0 && $currentUserId === $uid);
                            ?>
                            <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                <a href="<?= htmlspecialchars(url('personnel/' . rawurlencode($target)), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-900 hover:bg-emerald-100">Ouvrir le profil</a>
                                <?php if ($canEditThisRow): ?>
                                <a href="<?= htmlspecialchars(url('personnel/' . $uid . '/edit'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-900">Modifier le dossier</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    </div>
</section>
