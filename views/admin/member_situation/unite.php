<?php

declare(strict_types=1);

$h = static fn (mixed $v): string => htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');

$primary = is_array($primaryAssignment ?? null) ? $primaryAssignment : null;
$secondary = is_array($secondaryAssignments ?? null) ? $secondaryAssignments : [];
$unit = is_array($unit ?? null) ? $unit : null;
$commander = is_array($commander ?? null) ? $commander : null;
$teammates = is_array($teammates ?? null) ? $teammates : [];
$subUnits = is_array($subUnits ?? null) ? $subUnits : [];
$communityName = trim((string) ($communityName ?? ''));
$viewerUserId = (int) ($viewerUserId ?? 0);

$unitName = trim((string) (($unit['name'] ?? null) ?: ($primary['unit_name'] ?? '')));
$unitPath = trim((string) ($primary['assignment_path'] ?? ''));
$unitCode = trim((string) ($unit['code'] ?? ''));
$unitTypeRaw = strtolower(trim((string) ($unit['type'] ?? $primary['unit_type'] ?? '')));
$unitBlurb = trim((string) ($unit['public_blurb'] ?? ''));
$unitMotto = trim((string) ($unit['motto'] ?? ''));
$unitAccent = trim((string) ($unit['accent_color'] ?? $unit['public_accent_color'] ?? ''));
if ($unitAccent === '' || !preg_match('/^#[0-9A-Fa-f]{6}$/', $unitAccent)) {
    $unitAccent = '';
}
$role = trim((string) ($primary['role_name'] ?? $primary['role_label'] ?? $primary['assignment_role'] ?? ''));
$startedAt = trim((string) ($primary['started_at'] ?? $primary['assigned_at'] ?? ''));
$startedLabel = '';
if ($startedAt !== '') {
    $ts = strtotime($startedAt);
    $startedLabel = $ts !== false ? date('d/m/Y', $ts) : $startedAt;
}

$unitTypeLabel = match ($unitTypeRaw) {
    'company', 'compagnie' => 'Compagnie',
    'platoon', 'peloton' => 'Peloton',
    'squad', 'groupe' => 'Groupe',
    'section' => 'Section',
    'fireteam', 'equipe', 'équipe' => 'Équipe',
    'hq', 'etat-major', 'état-major', 'staff' => 'État-major',
    'battalion', 'bataillon' => 'Bataillon',
    'detachment', 'detachement', 'détachement' => 'Détachement',
    '' => '',
    default => mb_convert_case(str_replace(['_', '-'], ' ', $unitTypeRaw), MB_CASE_TITLE, 'UTF-8'),
};

$pathParts = $unitPath !== ''
    ? array_values(array_filter(array_map('trim', preg_split('/\s*\/\s*/', $unitPath) ?: [])))
    : [];

// Abrégés automatiques (ou saisis sur la fiche unité) : le nom complet reste en infobulle.
$abbrTenant = (int) \App\Core\Session::get('tenant_id');
$abbr = static fn (string $full, string $short): string => \App\Support\UnitAbbreviation::html($full, $short);
$trail = \App\Support\UnitAbbreviation::trail($pathParts, $abbrTenant);
$ancestors = $pathParts;
if ($ancestors !== [] && \App\Support\UnitAbbreviation::within(end($ancestors), [$unitName]) === '') {
    array_pop($ancestors);
}
$unitShort = \App\Support\UnitAbbreviation::forUnit($unitName, $ancestors, $abbrTenant);
$parentFull = $ancestors !== [] ? (string) end($ancestors) : '';
$parentShort = $parentFull !== '' ? \App\Support\UnitAbbreviation::forUnit($parentFull, array_slice($ancestors, 0, -1), $abbrTenant) : '';
$roleShort = $role !== '' ? \App\Support\UnitAbbreviation::role($role, $unitName, $ancestors, $abbrTenant) : '';
$roleIsUnit = $role !== '' && \App\Support\UnitAbbreviation::within($role, [$unitName]) === '';
$codeIsName = $unitCode !== '' && \App\Support\UnitAbbreviation::within($unitCode, [$unitName]) === '';

$commanderName = '';
$commanderCallsign = '';
if ($commander !== null) {
    $commanderName = trim((string) ($commander['display_name'] ?? ''));
    $commanderCallsign = trim((string) ($commander['callsign'] ?? ''));
}

$mateCount = count($teammates);
$matePortraits = \App\Support\OperatorPortraits::forUsers(
    (int) \App\Core\Session::get('tenant_id'),
    array_map(static fn ($m): int => is_array($m) ? (int) ($m['user_id'] ?? 0) : 0, $teammates)
);
$subCount = count($subUnits);
$secondaryCount = count($secondary);

$mateLabel = static function (array $mate): string {
    $callsign = trim((string) ($mate['callsign'] ?? ''));
    $name = trim((string) ($mate['display_name'] ?? ''));
    if ($callsign !== '') {
        return $callsign;
    }

    return $name !== '' ? $name : 'Opérateur';
};
?>
<div class="bo-member-situation bo-member-situation--dossier bo-member-situation--unite">
    <?php if ($primary === null || $unitName === ''): ?>
        <header class="bo-dossier-hero">
            <div>
                <p class="bo-dossier-hero__kicker">Affectation</p>
                <h2 class="bo-dossier-hero__title">Pas encore d’unité</h2>
                <p class="bo-dossier-hero__lead">
                    Vous n’êtes pas encore rattaché à une unité de la communauté<?= $communityName !== '' ? ' « ' . $h($communityName) . ' »' : '' ?>.
                    Un responsable peut vous placer dans l’organigramme depuis les effectifs.
                </p>
            </div>
        </header>
        <section class="bo-doc-empty">
            <div class="bo-unit-empty-mark" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
            <div>
                <h3>En attente d’affectation</h3>
                <p>Dès qu’une unité vous est attribuée, cette page affiche votre fonction, le chemin dans l’organigramme et vos camarades.</p>
                <div class="bo-member-situation__actions" style="margin-top:0.85rem">
                    <a class="ath-btn ath-btn--solid" href="<?= $h(url('back-office/ma-situation/ma-fiche') . '?onglet=unite') ?>">Ouvrir ma fiche</a>
                    <a class="ath-btn" href="<?= $h(url('orbat')) ?>">Voir l’organigramme</a>
                </div>
            </div>
        </section>
    <?php else: ?>
        <header class="bo-unit-hero"<?= $unitAccent !== '' ? ' style="--bo-unit-accent:' . $h($unitAccent) . '"' : '' ?>>
            <div class="bo-unit-hero__main">
                <p class="bo-unit-hero__kicker">
                    <?= $h($communityName !== '' ? $communityName : 'Communauté') ?>
                    · Affectation principale
                </p>
                <?php if ($pathParts !== []): ?>
                    <nav class="bo-unit-trail" aria-label="Chemin dans l’organigramme">
                        <?php foreach ($trail as $i => $part): ?>
                            <?php if ($i > 0): ?><span class="bo-unit-trail__sep" aria-hidden="true">/</span><?php endif; ?>
                            <span class="bo-unit-trail__part<?= $i === count($trail) - 1 ? ' is-current' : '' ?>"><?= $abbr($part['full'], $part['short']) ?></span>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
                <h2 class="bo-unit-hero__title"><?= $abbr($unitName, $unitShort) ?></h2>
                <?php if ($unitShort !== $unitName): ?>
                    <p class="bo-unit-hero__fullname"><?= $h($unitName) ?></p>
                <?php endif; ?>
                <?php if ($unitMotto !== ''): ?>
                    <p class="bo-unit-hero__motto">« <?= $h($unitMotto) ?> »</p>
                <?php endif; ?>
                <div class="bo-unit-hero__tags">
                    <?php if ($unitTypeLabel !== ''): ?>
                        <span class="bo-unit-tag"><?= $h($unitTypeLabel) ?></span>
                    <?php endif; ?>
                    <?php if ($unitCode !== '' && !$codeIsName): ?>
                        <span class="bo-unit-tag bo-unit-tag--code"><?= $h($unitCode) ?></span>
                    <?php endif; ?>
                    <?php if ($role !== '' && !$roleIsUnit): ?>
                        <span class="bo-unit-tag bo-unit-tag--role"><?= $abbr($role, $roleShort) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($unitBlurb !== ''): ?>
                    <p class="bo-unit-hero__blurb"><?= $h($unitBlurb) ?></p>
                <?php elseif ($role !== ''): ?>
                    <p class="bo-unit-hero__blurb">Vous tenez la fonction <strong><?= $abbr($role, $roleShort) ?></strong> au sein de cette unité.</p>
                <?php else: ?>
                    <p class="bo-unit-hero__blurb">Voici votre place dans l’organigramme et les opérateurs rattachés à la même unité.</p>
                <?php endif; ?>
            </div>
            <div class="bo-unit-hero__aside" aria-label="Synthèse">
                <div class="bo-unit-stat">
                    <strong><?= $mateCount ?></strong>
                    <span>opérateur<?= $mateCount > 1 ? 's' : '' ?></span>
                </div>
                <div class="bo-unit-stat">
                    <strong><?= $subCount ?></strong>
                    <span>sous-unité<?= $subCount > 1 ? 's' : '' ?></span>
                </div>
                <div class="bo-unit-stat">
                    <strong><?= max(1, $secondaryCount + 1) ?></strong>
                    <span>affectation<?= ($secondaryCount + 1) > 1 ? 's' : '' ?></span>
                </div>
            </div>
        </header>

        <div class="bo-member-situation__actions">
            <a class="ath-btn ath-btn--solid" href="<?= $h(url('orbat')) ?>">Ouvrir l’organigramme</a>
            <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/ma-fiche') . '?onglet=unite') ?>">Voir dans ma fiche</a>
            <a class="ath-btn" href="<?= $h(url('back-office/ma-situation/qualifications')) ?>">Mes qualifications</a>
        </div>

        <div class="bo-unit-layout">
            <section class="bo-unit-panel">
                <header class="bo-unit-panel__head">
                    <p class="bo-member-situation__kicker">Votre place</p>
                    <h3>Poste tenu</h3>
                </header>
                <dl class="bo-unit-facts">
                    <div>
                        <dt>Fonction</dt>
                        <dd><?= $role !== '' ? $abbr($role, $roleShort) : 'Membre' ?></dd>
                    </div>
                    <div>
                        <dt>Unité</dt>
                        <dd>
                            <?= $abbr($unitName, $unitShort) ?>
                            <?php if ($parentShort !== ''): ?>
                                <span class="bo-unit-muted"> · <?= $abbr($parentFull, $parentShort) ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <?php if ($startedLabel !== ''): ?>
                        <div>
                            <dt>Depuis</dt>
                            <dd><?= $h($startedLabel) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($commanderName !== '' || $commanderCallsign !== ''): ?>
                        <div>
                            <dt>Chef d’unité</dt>
                            <dd>
                                <?php if ($commanderCallsign !== ''): ?>
                                    <span class="bo-unit-callsign"><?= $h($commanderCallsign) ?></span>
                                    <?php if ($commanderName !== '' && strcasecmp($commanderName, $commanderCallsign) !== 0): ?>
                                        <span class="bo-unit-muted"> · <?= $h($commanderName) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= $h($commanderName) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>

            <section class="bo-unit-panel bo-unit-panel--roster">
                <header class="bo-unit-panel__head">
                    <div>
                        <p class="bo-member-situation__kicker">Effectif de l’unité</p>
                        <h3>Camarades</h3>
                    </div>
                    <span class="bo-member-situation__badge"><?= $mateCount ?> présent<?= $mateCount > 1 ? 's' : '' ?></span>
                </header>
                <?php if ($teammates === []): ?>
                    <p class="bo-unit-empty-line">Aucun autre opérateur n’est encore listé sur cette unité.</p>
                <?php else: ?>
                    <ul class="bo-unit-roster">
                        <?php foreach ($teammates as $mate): ?>
                            <?php
                            if (!is_array($mate)) {
                                continue;
                            }
                            $mateId = (int) ($mate['user_id'] ?? 0);
                            $isYou = $mateId > 0 && $mateId === $viewerUserId;
                            $mateRole = trim((string) ($mate['role_name'] ?? ''));
                            $label = $mateLabel($mate);
                            $fullName = trim((string) ($mate['display_name'] ?? ''));
                            $initials = function_exists('user_display_initials')
                                ? user_display_initials($fullName !== '' ? $fullName : $label, 2)
                                : mb_strtoupper(mb_substr($label, 0, 2));
                            ?>
                            <li class="bo-unit-roster__item<?= $isYou ? ' is-you' : '' ?>">
                                <?php $matePhoto = $matePortraits[$mateId] ?? null; ?>
                                <span class="bo-unit-roster__avatar<?= $matePhoto !== null ? ' bo-unit-roster__avatar--photo' : '' ?>" aria-hidden="true">
                                    <?php if ($matePhoto !== null): ?>
                                        <img src="<?= $h($matePhoto) ?>" alt="" width="34" height="34" loading="lazy" decoding="async" data-img-fallback="avatar" data-img-initials="<?= $h($initials) ?>">
                                    <?php else: ?>
                                        <?= $h($initials) ?>
                                    <?php endif; ?>
                                </span>
                                <span class="bo-unit-roster__body">
                                    <strong>
                                        <?= $h($label) ?>
                                        <?php if ($isYou): ?><em>Vous</em><?php endif; ?>
                                    </strong>
                                    <?php if ($mateRole !== ''): ?>
                                        <span><?= $abbr($mateRole, \App\Support\UnitAbbreviation::role($mateRole, $unitName, $ancestors, $abbrTenant)) ?></span>
                                    <?php elseif ($fullName !== '' && strcasecmp($fullName, $label) !== 0): ?>
                                        <span><?= $h($fullName) ?></span>
                                    <?php else: ?>
                                        <span>Opérateur</span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <?php if ($subUnits !== []): ?>
            <section class="bo-unit-panel">
                <header class="bo-unit-panel__head">
                    <p class="bo-member-situation__kicker">Structure</p>
                    <h3>Sous-unités rattachées</h3>
                </header>
                <ul class="bo-unit-subs">
                    <?php foreach ($subUnits as $child): ?>
                        <?php
                        if (!is_array($child)) {
                            continue;
                        }
                        $childName = trim((string) ($child['name'] ?? ''));
                        if ($childName === '') {
                            continue;
                        }
                        $childCode = trim((string) ($child['code'] ?? ''));
                        $childType = trim((string) ($child['type'] ?? ''));
                        ?>
                        <li>
                            <strong><?= $abbr($childName, \App\Support\UnitAbbreviation::forUnit($childName, [...$ancestors, $unitName], $abbrTenant)) ?></strong>
                            <?php if ($childCode !== '' && \App\Support\UnitAbbreviation::within($childCode, [$childName]) === '') {
                                $childCode = '';
                            } ?>
                            <?php if ($childCode !== '' || $childType !== ''): ?>
                                <span><?= $h(trim($childCode . ($childCode !== '' && $childType !== '' ? ' · ' : '') . $childType)) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($secondary !== []): ?>
            <section class="bo-unit-panel">
                <header class="bo-unit-panel__head">
                    <p class="bo-member-situation__kicker">Autres rattachements</p>
                    <h3>Affectations secondaires</h3>
                </header>
                <div class="bo-unit-secondary">
                    <?php foreach ($secondary as $row): ?>
                        <?php
                        if (!is_array($row)) {
                            continue;
                        }
                        $secName = trim((string) ($row['unit_name'] ?? $row['name'] ?? ''));
                        $secPath = trim((string) ($row['assignment_path'] ?? ''));
                        $secRole = trim((string) ($row['role_name'] ?? $row['role_label'] ?? ''));
                        if ($secName === '' && $secPath === '') {
                            continue;
                        }
                        ?>
                        <article class="bo-unit-secondary__card">
                            <?php
                            $secTrail = \App\Support\UnitAbbreviation::trail($secPath !== '' ? $secPath : $secName, $abbrTenant);
                            $secAnc = array_column($secTrail, 'full');
                            if ($secAnc !== [] && $secName !== '' && \App\Support\UnitAbbreviation::within(end($secAnc), [$secName]) === '') {
                                array_pop($secAnc);
                            }
                            $secTitle = $secName !== '' ? $secName : $secPath;
                            ?>
                            <h4><?= $abbr($secTitle, $secName !== '' ? \App\Support\UnitAbbreviation::forUnit($secName, $secAnc, $abbrTenant) : $secTitle) ?></h4>
                            <?php if ($secPath !== '' && $secPath !== $secName): ?>
                                <p><?php foreach ($secTrail as $i => $part): ?><?= $i > 0 ? ' / ' : '' ?><?= $abbr($part['full'], $part['short']) ?><?php endforeach; ?></p>
                            <?php endif; ?>
                            <?php if ($secRole !== ''): ?>
                                <p><strong><?= $abbr($secRole, \App\Support\UnitAbbreviation::role($secRole, $secName, $secAnc, $abbrTenant)) ?></strong></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
