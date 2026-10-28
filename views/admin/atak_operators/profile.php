<?php
declare(strict_types=1);

use App\Repositories\AtakRealismRepository;
use App\Support\AtakDeviceLog;
use App\Support\UtcDateTime;

/** @var list<array{call_sign:string,label:string,status?:string}> $atakOperatorChoices */
/** @var string $atakOperatorSelected */
/** @var array<string,mixed>|null $atakOperatorLive */
/** @var array<string,mixed>|null $atakOperatorTerminal */
/** @var array<string,mixed>|null $atakOperatorCertificate */
/** @var string $atakOperatorMilitaryId */
/** @var list<array<string,mixed>> $atakOperatorRoleTimes */
/** @var array<string,mixed>|null $atakOperatorPlayStats */
/** @var array{rows:list<array<string,mixed>>,total:int} $atakOperatorJournal */
/** @var array{prev:string,next:string,index:int,count:int} $atakOperatorNeighbours */

$choices = is_array($atakOperatorChoices ?? null) ? $atakOperatorChoices : [];
$selected = trim((string) ($atakOperatorSelected ?? ''));
$live = is_array($atakOperatorLive ?? null) ? $atakOperatorLive : null;
$terminal = is_array($atakOperatorTerminal ?? null) ? $atakOperatorTerminal : null;
$certificate = is_array($atakOperatorCertificate ?? null) ? $atakOperatorCertificate : null;
$roleTimes = is_array($atakOperatorRoleTimes ?? null) ? $atakOperatorRoleTimes : [];
$play = is_array($atakOperatorPlayStats ?? null) ? $atakOperatorPlayStats : null;
$journal = is_array($atakOperatorJournal ?? null) ? $atakOperatorJournal : ['rows' => [], 'total' => 0];
$journalRows = is_array($journal['rows'] ?? null) ? $journal['rows'] : [];
$journalTotal = (int) ($journal['total'] ?? 0);
$nb = is_array($atakOperatorNeighbours ?? null) ? $atakOperatorNeighbours : ['prev' => '', 'next' => '', 'index' => 0, 'count' => 0];
$portraitUrl = trim((string) ($atakOperatorPortraitUrl ?? ''));
$userId = (int) ($atakOperatorUserId ?? 0);

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$clean = static function (mixed $v): string {
    $s = trim((string) $v);
    if ($s === '') {
        return '';
    }
    $lower = strtolower($s);
    if (in_array($lower, ['null', '<null>', '<nul>', 'nil', 'undefined'], true)
        || str_contains($lower, '<null') || str_contains($lower, '<nul>')) {
        return '';
    }

    return $s;
};
$field = static fn (?array $row, string $key): string => is_array($row) ? $clean($row[$key] ?? '') : '';

$militaryId = trim((string) ($atakOperatorMilitaryId ?? ''));
if ($militaryId === '') {
    $militaryId = $field($live, 'military_id');
}
if ($militaryId === '') {
    $militaryId = $field($terminal, 'operator_military_id');
}
$displayName = $field($live, 'linked_display_name');
if ($displayName === '') {
    $displayName = $field($terminal, 'display_name');
}
$memberUrl = $field($live, 'linked_url');
if ($memberUrl === '' && $userId > 0) {
    $memberUrl = url('personnel/' . $userId);
}
$role = $field($live, 'role_label');
$team = $field($live, 'fire_team_label');
$teamColor = $field($live, 'fire_team_color');
if ($teamColor !== '' && preg_match('/^#[0-9a-f]{3,8}$/i', $teamColor) !== 1) {
    $teamColor = '';
}
$unitGroup = $field($live, 'unit_group_label');

/** Durée « il y a … » à partir d’une date MySQL UTC. */
$ago = static function (mixed $raw): string {
    $ts = UtcDateTime::timestamp($raw);
    if ($ts === null) {
        return '';
    }
    $d = max(0, time() - $ts);
    if ($d < 60) {
        return 'à l’instant';
    }
    if ($d < 3600) {
        return 'il y a ' . intdiv($d, 60) . ' min';
    }
    if ($d < 86400) {
        return 'il y a ' . intdiv($d, 3600) . ' h';
    }
    if ($d < 86400 * 60) {
        return 'il y a ' . intdiv($d, 86400) . ' j';
    }

    return 'le ' . date('d/m/Y', $ts);
};
$date = static function (mixed $raw, bool $withTime = true): string {
    $ts = UtcDateTime::timestamp($raw);

    return $ts === null ? '' : date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
};
$duration = static function (int $sec): string {
    if ($sec < 60) {
        return '0 min';
    }
    $hh = intdiv($sec, 3600);
    $mm = intdiv($sec % 3600, 60);

    return $hh > 0 ? $hh . ' h ' . str_pad((string) $mm, 2, '0', STR_PAD_LEFT) : $mm . ' min';
};

/** @return array{label:string,tone:string} */
$statusMeta = static function (?string $status): array {
    return match ((string) $status) {
        'linked' => ['label' => 'En liaison', 'tone' => 'ok'],
        'delayed' => ['label' => 'Signal faible', 'tone' => 'warn'],
        'active' => ['label' => 'Actif', 'tone' => 'ok'],
        'pending' => ['label' => 'En attente', 'tone' => 'warn'],
        'issued' => ['label' => 'Émis', 'tone' => 'info'],
        'expired' => ['label' => 'Expiré', 'tone' => 'danger'],
        'revoked' => ['label' => 'Révoqué', 'tone' => 'danger'],
        default => ['label' => 'Hors ligne', 'tone' => 'off'],
    };
};

$liveStatusKey = is_array($live) ? (string) ($live['status'] ?? 'offline') : 'offline';
$liveStatus = $statusMeta($liveStatusKey);
$termStatus = $statusMeta(is_array($terminal) ? (string) ($terminal['status'] ?? '') : '');
$isWebOnly = is_array($terminal) && AtakRealismRepository::isWebSessionTerminal($terminal);
$liaison = is_array($terminal) ? AtakRealismRepository::liaisonIdentity($terminal) : null;
$compromise = $field($terminal, 'compromise_state');
$compromise = in_array($compromise, ['captured', 'compromised'], true) ? $compromise : '';

// Certificat : statut affiché + jours restants.
$certStatusKey = is_array($certificate) ? strtolower((string) ($certificate['status'] ?? '')) : '';
$certDaysLeft = null;
$certExpiresTs = is_array($certificate) ? UtcDateTime::timestamp($certificate['expires_at'] ?? null) : null;
if ($certExpiresTs !== null) {
    $certDaysLeft = (int) floor(($certExpiresTs - time()) / 86400);
    if ($certDaysLeft < 0 && $certStatusKey !== 'revoked') {
        $certStatusKey = 'expired';
    }
}
$certStatus = $statusMeta($certStatusKey);
if (!is_array($certificate)) {
    $certStatus = ['label' => 'Aucun', 'tone' => 'off'];
}
if ($certStatus['tone'] !== 'danger' && $certDaysLeft !== null && $certDaysLeft >= 0 && $certDaysLeft <= 14) {
    $certStatus['tone'] = 'warn';
}

$initials = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $selected) ?: '?', 0, 3));
$profileUrl = url('back-office/atak/fiche-operateur');
$opUrl = static fn (string $cs): string => url('back-office/atak/fiche-operateur?indicatif=' . rawurlencode($cs));
$terminalId = is_array($terminal) ? (int) ($terminal['id'] ?? 0) : 0;
$journalUrl = $terminalId > 0 ? url('back-office/atak/realisme/terminaux/' . $terminalId . '/journal') : '';
$mapUrl = $field($live, 'map_url');

// Points à vérifier, du plus grave au moins grave.
$issues = [];
if ($compromise !== '') {
    $issues[] = ['danger', $compromise === 'captured' ? 'Appareil déclaré capturé' : 'Appareil déclaré compromis', 'Les échanges de cet appareil ne sont plus fiables.', $journalUrl, 'Voir le journal'];
}
if (is_array($certificate) && $certStatusKey === 'revoked') {
    $issues[] = ['danger', 'Certificat révoqué', 'L’appareil ne peut plus prouver son identité.', url('back-office/atak/certificats'), 'Émettre un certificat'];
} elseif (is_array($certificate) && $certStatusKey === 'expired') {
    $issues[] = ['danger', 'Certificat expiré', 'À renouveler avant la prochaine opération.', url('back-office/atak/certificats'), 'Renouveler'];
} elseif ($certDaysLeft !== null && $certDaysLeft <= 14) {
    $issues[] = ['warn', 'Certificat bientôt expiré', $certDaysLeft <= 0 ? 'Expire aujourd’hui.' : 'Expire dans ' . $certDaysLeft . ' j.', url('back-office/atak/certificats'), 'Renouveler'];
}
if (!is_array($terminal)) {
    $issues[] = ['warn', 'Aucun appareil rattaché', 'L’indicatif n’a encore été vu sur aucun appareil.', url('back-office/atak/realisme'), 'Parc des appareils'];
} elseif ($isWebOnly) {
    $issues[] = ['info', 'Session navigateur seulement', 'Aucun appareil de jeu n’est connu pour cet indicatif.', url('back-office/atak/realisme'), 'Parc des appareils'];
}
if (is_array($terminal) && !is_array($certificate)) {
    $issues[] = ['warn', 'Aucun certificat', 'L’appareil échange sans chaîne de confiance.', url('back-office/atak/certificats'), 'Émettre un certificat'];
}
if ($userId < 1) {
    $issues[] = ['warn', 'Aucun compte lié', 'L’indicatif n’est rattaché à aucun membre.', url('back-office/atak/operateurs?statut=tous'), 'Sessions'];
}
if ($liveStatusKey === 'delayed') {
    $issues[] = ['warn', 'Signal faible', 'Les positions arrivent avec du retard.', '', ''];
}
if ($militaryId === '') {
    $issues[] = ['info', 'Pas d’ID militaire', 'Il sera attribué à la prochaine liaison.', '', ''];
}

$hasRealData = is_array($live) || is_array($terminal) || is_array($certificate);
$maxRole = 0;
foreach ($roleTimes as $rt) {
    $maxRole = max($maxRole, (int) ($rt['seconds'] ?? 0));
}
$weeks = is_array($play['weeks'] ?? null) ? $play['weeks'] : [];
$maxWeek = 0;
foreach ($weeks as $w) {
    $maxWeek = max($maxWeek, (int) ($w['seconds'] ?? 0));
}

$levelMeta = static function (string $lvl): array {
    return match (AtakDeviceLog::normalizeLevel($lvl)) {
        AtakDeviceLog::LEVEL_ERROR => ['Erreur', 'danger'],
        AtakDeviceLog::LEVEL_WARN => ['Alerte', 'warn'],
        AtakDeviceLog::LEVEL_DEBUG => ['Détail', 'off'],
        default => ['Info', 'info'],
    };
};
$pill = static fn (string $tone, string $label): string => '<span class="aop-pill aop-pill--' . htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
$row = static function (string $label, string $value, bool $mono = false, bool $copy = false) use ($h): string {
    $val = $value !== '' ? $h($value) : '<span class="aop-none">—</span>';
    $btn = $copy && $value !== '' ? ' <button type="button" class="aop-copy" data-aop-copy="' . $h($value) . '" title="Copier">Copier</button>' : '';

    return '<div class="aop-dl__row"><dt>' . $h($label) . '</dt><dd' . ($mono ? ' class="aop-mono"' : '') . '>' . $val . $btn . '</dd></div>';
};
?>
<div class="aop">
    <nav class="aop-bar" aria-label="Opérateurs">
        <a class="aop-bar__back" href="<?= $h(url('back-office/atak/operateurs')) ?>">← Sessions et connexions</a>
        <form method="get" action="<?= $h($profileUrl) ?>" class="aop-switch" data-aop-autosubmit>
            <label for="aop-indicatif" class="aop-switch__label">Opérateur</label>
            <select id="aop-indicatif" name="indicatif" class="aop-switch__select">
                <?php if ($choices === []): ?>
                    <option value="">Aucun opérateur disponible</option>
                <?php endif; ?>
                <?php foreach ($choices as $choice): ?>
                    <?php $dot = in_array((string) ($choice['status'] ?? ''), ['linked', 'delayed'], true) ? '● ' : '○ '; ?>
                    <option value="<?= $h($choice['call_sign']) ?>" <?= strtoupper((string) $choice['call_sign']) === strtoupper($selected) ? 'selected' : '' ?>><?= $h($dot . $choice['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="ath-btn aop-switch__go">Afficher</button>
        </form>
        <?php if ((int) $nb['count'] > 1): ?>
            <div class="aop-step" aria-label="Opérateur précédent ou suivant">
                <?php if ($nb['prev'] !== ''): ?>
                    <a class="aop-step__btn" href="<?= $h($opUrl($nb['prev'])) ?>" title="Précédent : <?= $h($nb['prev']) ?>">‹</a>
                <?php else: ?>
                    <span class="aop-step__btn is-off" aria-hidden="true">‹</span>
                <?php endif; ?>
                <span class="aop-step__pos"><?= (int) $nb['index'] ?> / <?= (int) $nb['count'] ?></span>
                <?php if ($nb['next'] !== ''): ?>
                    <a class="aop-step__btn" href="<?= $h($opUrl($nb['next'])) ?>" title="Suivant : <?= $h($nb['next']) ?>">›</a>
                <?php else: ?>
                    <span class="aop-step__btn is-off" aria-hidden="true">›</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </nav>

    <?php if ($selected === '' || $choices === []): ?>
        <section class="aop-empty">
            <p class="aop-empty__title">Aucun opérateur à afficher</p>
            <p>Un opérateur apparaît ici dès sa première liaison ATAK, ou quand un appareil est déclaré dans le parc.</p>
            <a class="ath-btn" href="<?= $h(url('back-office/atak/realisme')) ?>">Parc des appareils</a>
        </section>
    <?php else: ?>

    <header class="aop-hero">
        <div class="aop-hero__portrait">
            <span class="aop-hero__avatar aop-tone--<?= $h($liveStatus['tone']) ?>">
                <?php if ($portraitUrl !== ''): ?>
                    <img src="<?= $h($portraitUrl) ?>" alt="" loading="lazy" decoding="async" data-img-fallback="portrait" data-img-initials="<?= $h($initials) ?>" data-img-label="Portrait opérateur indisponible">
                <?php else: ?>
                    <span class="aop-hero__initials"><?= $h($initials) ?></span>
                <?php endif; ?>
                <i class="aop-hero__dot" title="<?= $h($liveStatus['label']) ?>"></i>
            </span>
        </div>
        <div class="aop-hero__id">
            <p class="aop-kicker">Fiche opérateur ATAK<?php if ($role !== ''): ?> · <?= $h($role) ?><?php endif; ?></p>
            <h1 class="aop-hero__call"><?= $h($selected) ?></h1>
            <p class="aop-hero__meta">
                <?php if ($displayName !== ''): ?>
                    <?php if ($memberUrl !== ''): ?>
                        <a href="<?= $h($memberUrl) ?>"><?= $h($displayName) ?></a>
                    <?php else: ?>
                        <span><?= $h($displayName) ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="aop-none">Aucun compte lié</span>
                <?php endif; ?>
                <?php if ($militaryId !== ''): ?>
                    <span class="aop-mono">ID <?= $h($militaryId) ?></span>
                <?php endif; ?>
                <?php if ($team !== ''): ?>
                    <span class="aop-team"><i style="<?= $teamColor !== '' ? 'background:' . $h($teamColor) : '' ?>"></i><?= $h($team) ?><?php if ($unitGroup !== ''): ?> · <?= $h($unitGroup) ?><?php endif; ?></span>
                <?php elseif ($unitGroup !== ''): ?>
                    <span><?= $h($unitGroup) ?></span>
                <?php endif; ?>
            </p>
            <div class="aop-hero__chips">
                <?= $pill($liveStatus['tone'], $liveStatus['label']) ?>
                <?php if (is_array($terminal)): ?>
                    <?= $pill($termStatus['tone'], 'Appareil · ' . $termStatus['label']) ?>
                <?php endif; ?>
                <?php if ($compromise !== ''): ?>
                    <?= $pill('danger', $compromise === 'captured' ? 'Capturé' : 'Compromis') ?>
                <?php endif; ?>
                <?php if ($liaison !== null && $liaison['versions'] !== '—'): ?>
                    <span class="aop-pill aop-pill--plain"><?= $h($liaison['versions']) ?></span>
                <?php endif; ?>
            </div>
            <div class="aop-hero__actions">
                <?php if ($mapUrl !== ''): ?>
                    <a class="ath-btn ath-btn--solid" href="<?= $h($mapUrl) ?>">Voir sur la carte</a>
                <?php endif; ?>
                <?php if ($memberUrl !== ''): ?>
                    <a class="ath-btn" href="<?= $h($memberUrl) ?>">Fiche membre</a>
                <?php endif; ?>
                <?php if ($journalUrl !== ''): ?>
                    <a class="ath-btn" href="<?= $h($journalUrl) ?>">Journal de l’appareil</a>
                <?php endif; ?>
                <a class="ath-btn" href="<?= $h(url('back-office/atak/certificats')) ?>">Certificats</a>
            </div>
        </div>
        <aside class="aop-check<?= $issues === [] ? ' is-ok' : '' ?>" aria-label="Points à vérifier">
            <p class="aop-check__k">Points à vérifier</p>
            <?php if ($issues === []): ?>
                <p class="aop-check__v">Rien à signaler</p>
                <p class="aop-check__lead">Liaison, appareil et certificat sont en ordre.</p>
            <?php else: ?>
                <p class="aop-check__v"><?= count($issues) ?></p>
                <ul class="aop-check__list">
                    <?php foreach ($issues as [$tone, $title, $detail, $href, $cta]): ?>
                        <li class="aop-issue aop-issue--<?= $h($tone) ?>">
                            <strong><?= $h($title) ?></strong>
                            <span><?= $h($detail) ?></span>
                            <?php if ($href !== ''): ?><a href="<?= $h($href) ?>"><?= $h($cta) ?> →</a><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </header>

    <section class="aop-tiles" aria-label="En bref">
        <div class="aop-tile aop-tile--<?= $h($liveStatus['tone']) ?>">
            <p class="aop-tile__k">Liaison</p>
            <p class="aop-tile__v"><?= $h($liveStatus['label']) ?></p>
            <p class="aop-tile__s"><?= $h(is_array($live) && $field($live, 'updated_at') !== '' ? 'Position reçue ' . ($ago($live['updated_at']) ?: $field($live, 'updated_at_label')) : 'Aucune position reçue') ?></p>
        </div>
        <div class="aop-tile">
            <p class="aop-tile__k">Position</p>
            <p class="aop-tile__v aop-mono"><?= $h($field($live, 'grid_ref') !== '' ? $field($live, 'grid_ref') : '—') ?></p>
            <p class="aop-tile__s"><?= $h($field($live, 'heading_label') !== '' ? 'Cap ' . $field($live, 'heading_label') : 'Cap inconnu') ?><?php if ($field($live, 'map_label') !== ''): ?> · <?= $h($field($live, 'map_label')) ?><?php endif; ?></p>
        </div>
        <div class="aop-tile">
            <p class="aop-tile__k">Temps de jeu · 30 j</p>
            <p class="aop-tile__v"><?= $h($play !== null ? $duration((int) ($play['play_30d'] ?? 0)) : '—') ?></p>
            <p class="aop-tile__s"><?= $h($play !== null ? (int) ($play['sessions_30d'] ?? 0) . ' session(s) · ' . $duration((int) ($play['total_seconds'] ?? 0)) . ' au total' : 'Pas encore de temps enregistré') ?></p>
        </div>
        <div class="aop-tile aop-tile--<?= $h($certStatus['tone']) ?>">
            <p class="aop-tile__k">Certificat</p>
            <p class="aop-tile__v"><?= $h($certStatus['label']) ?></p>
            <p class="aop-tile__s"><?php
                if ($certDaysLeft === null) {
                    echo $h(is_array($certificate) ? 'Sans échéance' : 'Aucun certificat émis');
                } elseif ($certDaysLeft < 0) {
                    echo $h('Expiré le ' . $date($certificate['expires_at'] ?? null, false));
                } else {
                    echo $h('Expire dans ' . $certDaysLeft . ' j');
                }
            ?></p>
        </div>
    </section>

    <nav class="aop-jump" aria-label="Rubriques">
        <a href="#aop-position">Position</a>
        <a href="#aop-appareil">Appareil</a>
        <a href="#aop-certificat">Certificat</a>
        <a href="#aop-activite">Activité</a>
        <a href="#aop-journal">Journal<?php if ($journalTotal > 0): ?> <span><?= (int) $journalTotal ?></span><?php endif; ?></a>
    </nav>

    <div class="aop-sections">
        <section class="aop-section" id="aop-position">
            <div class="aop-section__head">
                <h2>Position et liaison</h2>
                <p>Dernier état reçu de l’ATAK en jeu.</p>
            </div>
            <div class="aop-grid">
                <article class="aop-card">
                    <h3 class="aop-card__title">Situation</h3>
                    <dl class="aop-dl">
                        <?= $row('Statut', $liveStatus['label']) ?>
                        <?= $row('Grille', $field($live, 'grid_ref'), true, true) ?>
                        <?= $row('Cap', $field($live, 'heading_label')) ?>
                        <?= $row('Carte', $field($live, 'map_label')) ?>
                        <?= $row('Dernière mise à jour', is_array($live) ? ($date($live['updated_at'] ?? null) ?: $field($live, 'updated_at_label')) : '') ?>
                    </dl>
                </article>
                <article class="aop-card">
                    <h3 class="aop-card__title">Identité réseau</h3>
                    <dl class="aop-dl">
                        <?= $row('Indicatif', $selected, true, true) ?>
                        <?= $row('ID militaire', $militaryId, true, true) ?>
                        <?= $row('Rôle', $role) ?>
                        <?= $row('Équipe', $team) ?>
                        <?= $row('Unité', $unitGroup) ?>
                        <?= $row('Compte lié', $displayName) ?>
                    </dl>
                </article>
            </div>
        </section>

        <section class="aop-section" id="aop-appareil">
            <div class="aop-section__head">
                <h2>Appareil</h2>
                <p>Le terminal qui porte cet indicatif dans le parc de la communauté.</p>
            </div>
            <?php if (!is_array($terminal)): ?>
                <div class="aop-card aop-card--empty">
                    <p>Aucun appareil rattaché à cet indicatif. Il apparaîtra à sa première liaison depuis le jeu.</p>
                    <a class="ath-btn" href="<?= $h(url('back-office/atak/realisme')) ?>">Parc des appareils</a>
                </div>
            <?php else: ?>
                <div class="aop-grid">
                    <article class="aop-card">
                        <h3 class="aop-card__title"><?= $h($field($terminal, 'terminal_label') !== '' ? $field($terminal, 'terminal_label') : 'Terminal ATAK') ?></h3>
                        <dl class="aop-dl">
                            <?= $row('Identifiant', $field($terminal, 'terminal_uid'), true, true) ?>
                            <?= $row('Type', $isWebOnly ? 'Session navigateur' : ($field($terminal, 'platform_label') !== '' ? $field($terminal, 'platform_label') : $field($terminal, 'terminal_type'))) ?>
                            <?= $row('Statut', $termStatus['label']) ?>
                            <?= $row('Première liaison', $date($terminal['first_seen_at'] ?? ($terminal['created_at'] ?? null))) ?>
                            <?= $row('Dernier passage', $date($terminal['last_seen_at'] ?? null)) ?>
                        </dl>
                    </article>
                    <article class="aop-card">
                        <h3 class="aop-card__title">Version et réseau</h3>
                        <dl class="aop-dl">
                            <?= $row('Versions', $liaison !== null && $liaison['versions'] !== '—' ? $liaison['versions'] : '') ?>
                            <?= $row('Adresse IP', $liaison !== null && $liaison['ip'] !== '—' ? $liaison['ip'] : '', true) ?>
                            <?= $row('Serveur', $liaison['host'] ?? '', true) ?>
                            <?= $row('Signature serveur', $liaison !== null && $liaison['signature'] !== '—' ? $liaison['signature'] : '', true) ?>
                            <?= $row('Compromission', $compromise === 'captured' ? 'Capturé' : ($compromise === 'compromised' ? 'Compromis' : 'Aucune')) ?>
                        </dl>
                    </article>
                </div>
            <?php endif; ?>
        </section>

        <section class="aop-section" id="aop-certificat">
            <div class="aop-section__head">
                <h2>Certificat</h2>
                <p>La preuve d’identité de l’appareil sur le réseau ATAK.</p>
            </div>
            <?php if (!is_array($certificate)): ?>
                <div class="aop-card aop-card--empty">
                    <p>Aucun certificat associé pour le moment.</p>
                    <a class="ath-btn" href="<?= $h(url('back-office/atak/certificats')) ?>">Émettre un certificat</a>
                </div>
            <?php else: ?>
                <div class="aop-grid">
                    <article class="aop-card">
                        <h3 class="aop-card__title"><?= $h($field($certificate, 'certificate_ref') !== '' ? $field($certificate, 'certificate_ref') : 'Certificat') ?></h3>
                        <dl class="aop-dl">
                            <?= $row('Statut', $certStatus['label']) ?>
                            <?= $row('Autorité', $field($certificate, 'authority_label')) ?>
                            <?= $row('Domaine', $field($certificate, 'crypto_domain_label')) ?>
                            <?= $row('Émis le', $date($certificate['issued_at'] ?? ($certificate['created_at'] ?? null), false)) ?>
                            <?= $row('Échéance', $date($certificate['expires_at'] ?? null, false)) ?>
                        </dl>
                        <?php if ($certDaysLeft !== null && $certDaysLeft >= 0 && $certExpiresTs !== null):
                            $issuedTs = UtcDateTime::timestamp($certificate['issued_at'] ?? ($certificate['valid_from'] ?? ($certificate['created_at'] ?? null)));
                            $span = $issuedTs !== null ? max(1, $certExpiresTs - $issuedTs) : 0;
                            $pct = $span > 0 ? (int) round(max(0, min(1, ($certExpiresTs - time()) / $span)) * 100) : 0;
                            ?>
                            <?php if ($span > 0): ?>
                                <div class="aop-meter<?= $certDaysLeft <= 14 ? ' is-low' : '' ?>" title="Validité restante">
                                    <span style="width:<?= $pct ?>%"></span>
                                </div>
                                <p class="aop-meter__lead"><?= $h('Validité restante : ' . $certDaysLeft . ' j') ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </article>
                    <article class="aop-card">
                        <h3 class="aop-card__title">Détails techniques</h3>
                        <dl class="aop-dl">
                            <?= $row('Type', ['device' => 'Appareil', 'operator' => 'Opérateur', 'gateway' => 'Passerelle', 'test' => 'Test'][$field($certificate, 'certificate_type')] ?? $field($certificate, 'certificate_type')) ?>
                            <?= $row('Nom commun', $field($certificate, 'common_name'), true) ?>
                            <?= $row('N° de série', $field($certificate, 'serial_number'), true, true) ?>
                            <?= $row('Empreinte SHA-256', $field($certificate, 'fingerprint_sha256'), true, true) ?>
                        </dl>
                    </article>
                </div>
            <?php endif; ?>
        </section>

        <section class="aop-section" id="aop-activite">
            <div class="aop-section__head">
                <h2>Activité en jeu</h2>
                <p>Temps passé avec l’ATAK, relevé par le mod.</p>
            </div>
            <?php if ($play === null && $roleTimes === []): ?>
                <div class="aop-card aop-card--empty">
                    <p><?= $userId > 0 ? 'Pas encore de temps de jeu enregistré pour ce membre.' : 'Le temps de jeu est suivi par membre : liez d’abord cet indicatif à un compte.' ?></p>
                </div>
            <?php else: ?>
                <div class="aop-grid">
                    <article class="aop-card">
                        <h3 class="aop-card__title">8 dernières semaines</h3>
                        <?php if ($weeks !== [] && $maxWeek > 0): ?>
                            <div class="aop-weeks" role="img" aria-label="Temps de jeu par semaine">
                                <?php foreach ($weeks as $w): $sec = (int) ($w['seconds'] ?? 0); $pct = $maxWeek > 0 ? max($sec > 0 ? 4 : 0, (int) round($sec / $maxWeek * 100)) : 0; ?>
                                    <div class="aop-weeks__col" title="<?= $h('Semaine du ' . date('d/m', strtotime((string) $w['start']) ?: time()) . ' : ' . $duration($sec)) ?>">
                                        <span class="aop-weeks__bar" style="height:<?= $pct ?>%"></span>
                                        <span class="aop-weeks__lbl"><?= $h(date('d/m', strtotime((string) $w['start']) ?: time())) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="aop-muted">Aucune session ces 8 dernières semaines.</p>
                        <?php endif; ?>
                        <dl class="aop-dl aop-dl--inline">
                            <?= $row('7 jours', $duration((int) ($play['play_7d'] ?? 0))) ?>
                            <?= $row('30 jours', $duration((int) ($play['play_30d'] ?? 0))) ?>
                            <?= $row('Sessions', (string) (int) ($play['sessions_total'] ?? 0)) ?>
                            <?= $row('Dernière session', $date(($play['last_day'] ?? '') !== '' ? $play['last_day'] . ' 12:00:00' : null, false)) ?>
                        </dl>
                    </article>
                    <article class="aop-card">
                        <h3 class="aop-card__title">Temps par rôle</h3>
                        <?php if ($roleTimes === []): ?>
                            <p class="aop-muted">Aucun rôle relevé.</p>
                        <?php else: ?>
                            <ul class="aop-bars">
                                <?php foreach (array_slice($roleTimes, 0, 8) as $rt): $sec = (int) ($rt['seconds'] ?? 0); ?>
                                    <li>
                                        <span class="aop-bars__label"><?= $h((string) ($rt['label'] ?? '')) ?></span>
                                        <span class="aop-bars__value"><?= $h($duration($sec)) ?></span>
                                        <span class="aop-bars__track"><span style="width:<?= $maxRole > 0 ? max(3, (int) round($sec / $maxRole * 100)) : 0 ?>%"></span></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                </div>
            <?php endif; ?>
        </section>

        <section class="aop-section" id="aop-journal">
            <div class="aop-section__head">
                <h2>Journal récent</h2>
                <p>Les derniers événements remontés par l’appareil.</p>
                <?php if ($journalUrl !== '' && $journalTotal > 0): ?>
                    <a class="aop-section__more" href="<?= $h($journalUrl) ?>">Tout le journal (<?= (int) $journalTotal ?>) →</a>
                <?php endif; ?>
            </div>
            <?php if ($journalRows === []): ?>
                <div class="aop-card aop-card--empty">
                    <p><?= is_array($terminal) ? 'Aucun événement enregistré pour cet appareil.' : 'Pas de journal sans appareil rattaché.' ?></p>
                </div>
            <?php else: ?>
                <ol class="aop-log">
                    <?php foreach ($journalRows as $log): [$lvlLabel, $lvlTone] = $levelMeta((string) ($log['level'] ?? '')); ?>
                        <li class="aop-log__item">
                            <span class="aop-log__time" title="<?= $h($date($log['created_at'] ?? null)) ?>"><?= $h($ago($log['created_at'] ?? null) ?: '—') ?></span>
                            <?= $pill($lvlTone, $lvlLabel) ?>
                            <span class="aop-log__msg"><?= $h($clean($log['message'] ?? '') !== '' ? $clean($log['message'] ?? '') : '—') ?></span>
                            <?php if ($clean($log['channel'] ?? '') !== ''): ?><span class="aop-log__chan aop-mono"><?= $h($clean($log['channel'] ?? '')) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
    <?php if (!$hasRealData): ?>
        <p class="aop-muted aop-foot">Cet indicatif n’a encore laissé aucune trace : ni position, ni appareil, ni certificat.</p>
    <?php endif; ?>
    <?php endif; ?>
</div>
<script>
(function () {
    var form = document.querySelector('form[data-aop-autosubmit]');
    if (form) {
        var sel = form.querySelector('select');
        if (sel) { sel.addEventListener('change', function () { form.submit(); }); }
    }
    document.querySelectorAll('[data-aop-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var v = btn.getAttribute('data-aop-copy') || '';
            if (!navigator.clipboard) { return; }
            navigator.clipboard.writeText(v).then(function () {
                var old = btn.textContent;
                btn.textContent = 'Copié';
                btn.classList.add('is-done');
                setTimeout(function () { btn.textContent = old; btn.classList.remove('is-done'); }, 1400);
            });
        });
    });
})();
</script>
