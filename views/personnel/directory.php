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
 */

$query = trim((string) ($query ?? ''));
$results = is_array($results ?? null) ? $results : [];
$rolesByUserId = is_array($rolesByUserId ?? null) ? $rolesByUserId : [];
$badgesByUserId = is_array($badgesByUserId ?? null) ? $badgesByUserId : [];
$canEditPersonnel = !empty($canEditPersonnel);
$currentUserId = (int) ($currentUserId ?? 0);

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
<section class="w-full px-4 py-6 sm:px-6 md:px-8 md:py-8 lg:px-10 xl:px-12">
    <header class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
        <p class="text-[11px] font-black uppercase tracking-[0.3em] text-slate-500">Athena</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Annuaire du personnel</h1>
        <p class="mt-3 max-w-3xl text-sm text-slate-600">Grade, affectation, rôles, ancienneté et médailles de chaque membre de la communauté — recherchez par nom, indicatif, identifiant système ou nom de personnage.</p>

        <form method="get" action="<?= htmlspecialchars(url('personnel'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 flex flex-col gap-3 sm:flex-row">
            <label class="sr-only" for="personnel-directory-q">Recherche annuaire</label>
            <input id="personnel-directory-q" name="q" type="search" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nom, indicatif, personnage…" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-100">
            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-700">Rechercher</button>
            <?php if ($query !== ''): ?>
            <a href="<?= htmlspecialchars(url('personnel'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Réinitialiser</a>
            <?php endif; ?>
        </form>
    </header>

    <div class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
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
                        <th class="px-4 py-3 text-left text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Statut</th>
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
                                    <img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" alt="Avatar" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                    <?php else: ?>
                                    <div class="flex h-full w-full items-center justify-center text-xs font-bold text-slate-400"><?= htmlspecialchars($initialsOf($displayName), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-black text-slate-900"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php if ($character !== ''): ?>
                                    <p class="truncate text-xs text-slate-500">RP : <?= htmlspecialchars($character, ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>
                                    <?php if ($athenaId !== ''): ?>
                                    <p class="mt-0.5 truncate text-[10px] font-medium text-slate-300" title="Identifiant système Athena (interne, réservé au staff)">ID <?= htmlspecialchars($athenaId, ENT_QUOTES, 'UTF-8') ?></p>
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
                                    <img src="<?= htmlspecialchars($iconUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($badgeName, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($badgeName, ENT_QUOTES, 'UTF-8') ?>" class="h-6 w-6 rounded-full border border-slate-200 object-cover" loading="lazy" decoding="async">
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

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset <?= htmlspecialchars($statusClasses($status), ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>

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
</section>
