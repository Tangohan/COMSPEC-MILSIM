<?php
declare(strict_types=1);

/**
 * Annuaire du personnel — vue « fiches opérateur » (cartes) + vue liste compacte.
 * Un seul balisage par membre ; la mise en page change via [data-view] sur le conteneur.
 * Styles : public/assets/css/personnel-directory.css — JS : public/assets/js/personnel-directory.js
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

$e = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

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

$statusLabel = static fn (string $raw): string => match ($raw) {
    'active' => 'Actif',
    'inactive' => 'Inactif',
    'pending_verification' => 'E-mail non vérifié',
    default => $raw !== '' ? $raw : 'Statut inconnu',
};

$roleTones = ['slate', 'blue', 'indigo', 'emerald', 'amber', 'red', 'purple'];

$seniorityOf = static function (?string $dateStr): ?array {
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
        $label = '< 1 mois';
    }

    return ['label' => $label, 'since' => $start->format('d/m/Y'), 'days' => (int) $diff->days];
};

$gradeLabelFor = static function (array $row): string {
    if (function_exists('personnel_assigned_grade_label')) {
        return personnel_assigned_grade_label($row);
    }
    $long = trim((string) ($row['grade_long'] ?? ''));

    return $long !== '' ? $long : trim((string) ($row['grade_short'] ?? ''));
};

$initialsOf = static function (string $label): string {
    $parts = preg_split('/\s+/u', trim($label)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_substr($p, 0, 1, 'UTF-8');
    }

    return $out !== '' ? mb_strtoupper($out, 'UTF-8') : '?';
};

/* ── Préparation des données (une passe) ─────────────────────────── */
$people = [];
$units = [];
$maxDays = 1;
$deployableCount = 0;
$hasDeployableData = false;
foreach ($results as $row) {
    $uid = (int) ($row['id'] ?? 0);
    if ($uid < 1) {
        continue;
    }
    $displayName = trim((string) ($row['display_name'] ?? '')) ?: 'Profil sans nom';
    $unitName = trim((string) ($row['unit_name'] ?? ''));
    $unitCode = trim((string) ($row['unit_code'] ?? ''));
    $unitTooltip = trim((string) ($row['unit_tooltip'] ?? ''));
    $enlistmentRaw = $row['enlistment_date'] ?? $row['date_of_enlistment'] ?? null;
    $seniority = $seniorityOf(is_string($enlistmentRaw) ? $enlistmentRaw : null);
    if ($seniority !== null) {
        $maxDays = max($maxDays, $seniority['days']);
    }
    $orgNumber = trim((string) ($row['tenant_member_number'] ?? ''));
    $matricule = $orgNumber !== ''
        ? $orgNumber
        : (trim((string) ($row['matricule_internal'] ?? '')) ?: trim((string) ($row['service_number'] ?? '')));
    $avatar = function_exists('personnel_operator_portrait_url')
        ? (string) (personnel_operator_portrait_url($row) ?? '')
        : (function_exists('user_media_public_url')
            ? (user_media_public_url($row['avatar_url'] ?? null) ?? '')
            : trim((string) ($row['avatar_url'] ?? '')));
    $slug = trim((string) ($row['profile_slug'] ?? ''));
    $deployable = $row['deployable'] ?? null;
    if ($deployable !== null && $deployable !== '') {
        $hasDeployableData = true;
        if ((int) $deployable === 1) {
            $deployableCount++;
        }
    }
    $unitKey = $unitName !== '' ? $unitName : '__none';
    $units[$unitKey] = ($units[$unitKey] ?? 0) + 1;

    $people[] = [
        'uid' => $uid,
        'name' => $displayName,
        'fullName' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
        'character' => \App\Support\PersonnelDirectoryHints::distinctCharacterLabel($displayName, (string) ($row['character_name'] ?? '')),
        'callsign' => trim((string) ($row['callsign'] ?? '')),
        'radio' => trim((string) ($row['radio_assigned'] ?? '')),
        'athenaId' => trim((string) ($row['athena_identifier'] ?? '')),
        'grade' => $gradeLabelFor($row),
        'gradeShort' => trim((string) ($row['grade_short'] ?? '')),
        'gradeOrder' => (int) ($row['grade_sort_order'] ?? 999),
        'matricule' => $matricule,
        'unitName' => $unitName,
        'unitCode' => $unitCode,
        'unitKey' => $unitKey,
        'unitTooltip' => $unitTooltip !== '' ? $unitTooltip : ($unitName !== '' ? 'Unité : ' . $unitName : ''),
        'function' => trim((string) ($row['primary_role'] ?? '')),
        'seniority' => $seniority,
        'avatar' => $avatar,
        'href' => url('personnel/' . rawurlencode($slug !== '' ? $slug : (string) $uid)),
        'editHref' => url('personnel/' . $uid . '/edit'),
        'canEdit' => $canEditPersonnel || ($currentUserId > 0 && $currentUserId === $uid),
        'isMe' => $currentUserId > 0 && $currentUserId === $uid,
        'roles' => $rolesByUserId[$uid] ?? [],
        'badges' => $badgesByUserId[$uid] ?? [],
        'status' => trim((string) ($row['status'] ?? '')),
        'deployable' => $deployable,
    ];
}
$total = count($people);
uksort($units, static fn ($a, $b) => $a === '__none' ? 1 : ($b === '__none' ? -1 : strnatcasecmp($a, $b)));
$unitCount = count(array_filter(array_keys($units), static fn ($k) => $k !== '__none'));
?>
<section class="pd" data-pd-root>
    <!-- ═══ En-tête ═══ -->
    <header class="pd-hero" aria-labelledby="pd-title">
        <?php if ($heroHasImage): ?>
        <img src="<?= $e($heroImageUrl) ?>" alt="" class="pd-hero__img" width="1600" height="720" decoding="async" fetchpriority="high">
        <?php endif; ?>
        <div class="pd-hero__veil" aria-hidden="true"></div>
        <div class="pd-hero__grid" aria-hidden="true"></div>

        <div class="pd-wrap pd-hero__inner">
            <div class="pd-hero__copy">
                <p class="pd-kicker"><span class="pd-kicker__dot" aria-hidden="true"></span><?= $e($brandLine) ?> · Effectifs</p>
                <h1 id="pd-title" class="pd-hero__title">Annuaire du personnel</h1>
                <p class="pd-hero__lead">Grades, affectations, indicatifs et décorations de la communauté.</p>

                <dl class="pd-stats">
                    <div class="pd-stat"><dt>Effectif</dt><dd><?= (int) $total ?></dd></div>
                    <div class="pd-stat"><dt>Unités</dt><dd><?= (int) $unitCount ?></dd></div>
                    <?php if ($hasDeployableData): ?>
                    <div class="pd-stat"><dt>Déployables</dt><dd><?= (int) $deployableCount ?><span>/<?= (int) $total ?></span></dd></div>
                    <?php endif; ?>
                </dl>
            </div>

            <div class="pd-hero__side">
                <form method="get" action="<?= $e(url('personnel')) ?>" class="pd-search" role="search">
                    <label class="pd-sr" for="pd-q">Rechercher dans l’annuaire</label>
                    <svg class="pd-search__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="pd-q" name="q" type="search" value="<?= $e($query) ?>" placeholder="Nom, indicatif, matricule, personnage…" autocomplete="off" data-pd-filter>
                    <kbd class="pd-search__kbd" aria-hidden="true">/</kbd>
                    <button type="submit" class="pd-search__btn">Rechercher</button>
                </form>
                <div class="pd-hero__links">
                    <?php if ($query !== ''): ?>
                    <a href="<?= $e(url('personnel')) ?>" class="pd-link">✕ Effacer « <?= $e($query) ?> »</a>
                    <?php endif; ?>
                    <?php if ($canAccessEffectifsLms): ?>
                    <a href="<?= $e($effectifsUrl) ?>" class="pd-btn pd-btn--accent">Bureau effectifs <span aria-hidden="true">→</span></a>
                    <?php else: ?>
                    <a href="<?= $e(url('personnel/me')) ?>" class="pd-btn pd-btn--ghost">Mon dossier</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="pd-wrap pd-body">
        <?php if ($people === []): ?>
        <div class="pd-empty pd-empty--static">
            <p class="pd-empty__title">Aucun profil<?= $query !== '' ? ' pour « ' . $e($query) . ' »' : '' ?></p>
            <p>Essayez un indicatif, un matricule ou un nom de personnage.</p>
        </div>
        <?php else: ?>

        <!-- ═══ Barre d'outils ═══ -->
        <div class="pd-toolbar">
            <div class="pd-chips" role="group" aria-label="Filtrer par unité">
                <button type="button" class="pd-chip is-active" data-pd-unit="">Toutes <span><?= (int) $total ?></span></button>
                <?php foreach ($units as $unitKey => $count): ?>
                <button type="button" class="pd-chip" data-pd-unit="<?= $e((string) $unitKey) ?>">
                    <?= $unitKey === '__none' ? 'Non affectés' : $e((string) $unitKey) ?> <span><?= (int) $count ?></span>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="pd-tools">
                <label class="pd-select">
                    <span class="pd-sr">Trier</span>
                    <select data-pd-sort>
                        <option value="grade">Trier : grade</option>
                        <option value="name">Trier : nom</option>
                        <option value="seniority">Trier : ancienneté</option>
                        <option value="unit">Trier : unité</option>
                    </select>
                </label>
                <div class="pd-toggle" role="group" aria-label="Affichage">
                    <button type="button" data-pd-view="grid" aria-pressed="true" title="Fiches">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/></svg>
                        <span class="pd-sr">Fiches</span>
                    </button>
                    <button type="button" data-pd-view="list" aria-pressed="false" title="Liste">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/></svg>
                        <span class="pd-sr">Liste</span>
                    </button>
                </div>
            </div>
        </div>
        <p class="pd-count" aria-live="polite" data-pd-count><?= (int) $total ?> profil<?= $total > 1 ? 's' : '' ?></p>

        <!-- ═══ Fiches ═══ -->
        <div class="pd-list" data-view="grid" data-pd-list>
            <div class="pd-list__head" aria-hidden="true">
                <span class="pd-list__head-member">Membre</span><span>Matricule</span><span>Affectation</span><span>Indicatif</span><span>Ancienneté</span><span>Décorations</span><span></span>
            </div>
            <?php foreach ($people as $p):
                $sen = $p['seniority'];
                $pct = $sen !== null ? max(4, (int) round($sen['days'] / $maxDays * 100)) : 0;
                $search = mb_strtolower(implode(' ', [
                    $p['name'], $p['fullName'], $p['character'], $p['callsign'], $p['matricule'],
                    $p['grade'], $p['unitName'], $p['unitCode'], $p['function'],
                ]), 'UTF-8');
                $roleNames = array_values(array_filter(array_map(static fn ($r) => trim((string) ($r['name'] ?? '')), $p['roles'])));
                $badgeNames = array_values(array_filter(array_map(static fn ($b) => trim((string) ($b['name'] ?? '')), $p['badges'])));
            ?>
            <article class="pd-card<?= $p['isMe'] ? ' is-me' : '' ?><?= $p['status'] !== '' && $p['status'] !== 'active' ? ' is-inactive' : '' ?>"
                     data-search="<?= $e($search) ?>"
                     data-unit="<?= $e($p['unitKey']) ?>"
                     data-name="<?= $e(mb_strtolower($p['name'], 'UTF-8')) ?>"
                     data-grade="<?= (int) $p['gradeOrder'] ?>"
                     data-days="<?= $sen !== null ? (int) $sen['days'] : -1 ?>">

                <div class="pd-card__strip">
                    <span class="pd-card__unitcode" title="<?= $e($p['unitTooltip']) ?>"><?= $e($p['unitCode'] !== '' && mb_strlen($p['unitCode']) <= \App\Support\UnitAbbreviation::MAX_LENGTH ? $p['unitCode'] : ($p['unitName'] !== '' ? \App\Support\UnitAbbreviation::standalone($p['unitName']) : 'Non affecté')) ?></span>
                    <span class="pd-card__mat"><?= $p['matricule'] !== '' ? $e($p['matricule']) : '—' ?></span>
                </div>

                <div class="pd-card__id">
                    <div class="pd-portrait">
                        <?php if ($p['avatar'] !== ''): ?>
                        <img src="<?= $e($p['avatar']) ?>" alt="" loading="lazy" decoding="async" data-img-fallback="portrait" data-img-initials="<?= $e($initialsOf($p['name'])) ?>" data-img-label="Portrait indisponible">
                        <?php else: ?>
                        <span class="pd-portrait__initials"><?= $e($initialsOf($p['name'])) ?></span>
                        <?php endif; ?>
                        <?php if ($p['deployable'] !== null && $p['deployable'] !== ''): ?>
                        <span class="pd-portrait__dot <?= (int) $p['deployable'] === 1 ? 'is-on' : '' ?>" title="<?= (int) $p['deployable'] === 1 ? 'Déployable' : 'Non déployable' ?>"></span>
                        <?php endif; ?>
                    </div>
                    <div class="pd-ident">
                        <p class="pd-grade"><?= $p['grade'] !== '' ? $e($p['grade']) : '<em>Grade non renseigné</em>' ?></p>
                        <h2 class="pd-name">
                            <a href="<?= $e($p['href']) ?>" class="pd-name__link"><?= $e($p['name']) ?></a>
                            <?php if ($p['isMe']): ?><span class="pd-me">Vous</span><?php endif; ?>
                        </h2>
                        <?php if ($p['fullName'] !== '' && $p['fullName'] !== $p['name']): ?>
                        <p class="pd-sub"><?= $e($p['fullName']) ?></p>
                        <?php endif; ?>
                        <?php if ($p['character'] !== ''): ?>
                        <p class="pd-sub">Personnage : <?= $e($p['character']) ?></p>
                        <?php endif; ?>
                        <?php if ($canSeeInactiveDirectory && $p['athenaId'] !== ''): ?>
                        <p class="pd-sub pd-sub--ref" title="Identifiant interne réservé à l’encadrement">Réf. <?= $e($p['athenaId']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="pd-callsign" title="<?= $p['radio'] !== '' ? 'Radio : ' . $e($p['radio']) : 'Indicatif' ?>">
                        <span class="pd-callsign__label">Indicatif</span>
                        <span class="pd-callsign__value"><?= $p['callsign'] !== '' ? $e($p['callsign']) : '—' ?></span>
                        <?php if ($p['radio'] !== ''): ?><span class="pd-callsign__radio"><?= $e($p['radio']) ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="pd-card__assign">
                    <p class="pd-unit" title="<?= $e($p['unitTooltip']) ?>"><?= $p['unitName'] !== '' ? $e(\App\Support\UnitAbbreviation::standalone($p['unitName'])) : '<span class="pd-muted">Non affecté</span>' ?></p>
                    <?php if ($p['function'] !== ''): ?><p class="pd-function"><?= $e($p['function']) ?></p><?php endif; ?>
                    <?php if ($roleNames !== []): ?>
                    <ul class="pd-roles" title="<?= $e(implode(' · ', $roleNames)) ?>">
                        <?php foreach (array_slice($p['roles'], 0, 3) as $r):
                            $style = json_decode((string) ($r['badge_style'] ?? ''), true);
                            $tone = is_array($style) ? (string) ($style['color'] ?? 'slate') : 'slate';
                            $tone = in_array($tone, $roleTones, true) ? $tone : 'slate';
                        ?>
                        <li class="pd-role pd-role--<?= $e($tone) ?>"><?= $e(trim((string) ($r['name'] ?? ''))) ?></li>
                        <?php endforeach; ?>
                        <?php if (count($roleNames) > 3): ?><li class="pd-role pd-role--more">+<?= count($roleNames) - 3 ?></li><?php endif; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <div class="pd-card__foot">
                    <div class="pd-seniority" title="<?= $sen !== null ? 'Depuis le ' . $e($sen['since']) : 'Date d’engagement non renseignée' ?>">
                        <span class="pd-seniority__label"><?= $sen !== null ? $e($sen['label']) : '—' ?></span>
                        <?php if ($sen !== null): ?>
                        <span class="pd-seniority__since">depuis le <?= $e($sen['since']) ?></span>
                        <span class="pd-seniority__bar"><span style="width:<?= (int) $pct ?>%"></span></span>
                        <?php endif; ?>
                    </div>
                    <div class="pd-medals" title="<?= $e($badgeNames !== [] ? implode(' · ', $badgeNames) : 'Aucune décoration') ?>">
                        <?php if ($p['badges'] === []): ?>
                        <span class="pd-medals__none">Aucune décoration</span>
                        <?php else: ?>
                            <?php foreach (array_slice($p['badges'], 0, 4) as $b):
                                $bName = trim((string) ($b['name'] ?? ''));
                                $icon = function_exists('user_media_public_url')
                                    ? (user_media_public_url($b['icon_url'] ?? null) ?? '')
                                    : trim((string) ($b['icon_url'] ?? ''));
                            ?>
                            <?php if ($icon !== ''): ?>
                            <img class="pd-medal" src="<?= $e($icon) ?>" alt="<?= $e($bName ?: 'Insigne') ?>" title="<?= $e($bName) ?>" loading="lazy" decoding="async" data-img-fallback="badge" data-img-initials="<?= $e($initialsOf($bName)) ?>" data-img-label="Insigne indisponible">
                            <?php else: ?>
                            <span class="pd-medal pd-medal--txt" title="<?= $e($bName) ?>"><?= $e($initialsOf($bName)) ?></span>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (count($p['badges']) > 4): ?><span class="pd-medal pd-medal--more">+<?= count($p['badges']) - 4 ?></span><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pd-card__actions">
                    <?php if ($canSeeInactiveDirectory && $p['status'] !== 'active'): ?>
                    <span class="pd-status pd-status--<?= $e($p['status'] ?: 'unknown') ?>"><?= $e($statusLabel($p['status'])) ?></span>
                    <?php endif; ?>
                    <?php if ($p['canEdit']): ?>
                    <a href="<?= $e($p['editHref']) ?>" class="pd-icon-btn" title="Modifier le dossier" aria-label="Modifier le dossier de <?= $e($p['name']) ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/></svg>
                    </a>
                    <?php endif; ?>
                    <span class="pd-open" aria-hidden="true">Profil <span>→</span></span>
                </div>
            </article>
            <?php endforeach; ?>

            <div class="pd-empty" data-pd-empty hidden>
                <p class="pd-empty__title">Aucun résultat dans cette sélection</p>
                <p>Le filtre instantané ne porte que sur les profils affichés. <button type="submit" form="pd-server-search" class="pd-link pd-link--dark">Lancer une recherche complète</button></p>
            </div>
        </div>
        <form id="pd-server-search" method="get" action="<?= $e(url('personnel')) ?>" hidden><input type="hidden" name="q" data-pd-mirror></form>
        <?php endif; ?>
    </div>
</section>
<script src="<?= $e(asset_url('assets/js/personnel-directory.js')) ?>" defer></script>
