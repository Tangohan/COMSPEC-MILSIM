<?php
declare(strict_types=1);

use App\Support\EnlistmentStatusCatalog;

/**
 * Tableau plein page « Demandes de candidature » — dashboard membre/staff.
 *  - Staff RH/recrutement : toutes les candidatures de la communauté (tous statuts), avec les mêmes
 *    signaux que le bureau recrutement (étape, délai, bilan 30 jours, instructeur, affectation),
 *    filtres rapides et action « Ouvrir » toujours visible (colonne collée à droite).
 *  - Tout utilisateur : ses propres candidatures (toutes communautés), avec l’avancement de son parcours.
 *
 * Statuts, étapes et bilans viennent de App\Support\EnlistmentStatusCatalog (mêmes libellés partout).
 *
 * @var list<array<string,mixed>> $my_applications_all
 * @var list<array<string,mixed>> $staff_applications_all
 * @var bool $show_staff_enlistments
 * @var string|null $dashboard_tenant_label
 */

$myApps = $my_applications_all ?? [];
$staffApps = $staff_applications_all ?? [];
$showStaff = !empty($show_staff_enlistments);

if ($myApps === [] && ($staffApps === [] || !$showStaff)) {
    return;
}

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$toneClass = static fn (string $tone): string => match ($tone) {
    'emerald' => 'das-badge--emerald',
    'rose' => 'das-badge--rose',
    'slate' => 'das-badge--slate',
    'sky' => 'das-badge--sky',
    'orange' => 'das-badge--orange',
    'amber' => 'das-badge--amber',
    default => 'das-badge--muted',
};

$initials = static function (string $first, string $last): string {
    $a = mb_strtoupper(mb_substr(trim($first), 0, 1));
    $b = mb_strtoupper(mb_substr(trim($last), 0, 1));

    return ($a . $b) !== '' ? $a . $b : '?';
};

$fmtDate = static function (?string $raw, string $format = 'd/m/Y'): string {
    $raw = trim((string) $raw);
    $ts = $raw !== '' ? strtotime($raw) : false;

    return $ts !== false && $ts > 0 ? date($format, $ts) : '';
};

// « il y a 3 j », « il y a 5 h » : lecture rapide de la dernière action.
$ago = static function (?string $raw): string {
    $raw = trim((string) $raw);
    $ts = $raw !== '' ? strtotime($raw) : false;
    if ($ts === false || $ts <= 0) {
        return '';
    }
    $d = max(0, time() - $ts);
    if ($d < 3600) {
        return 'il y a ' . max(1, intdiv($d, 60)) . ' min';
    }
    if ($d < 86400) {
        return 'il y a ' . intdiv($d, 3600) . ' h';
    }

    return 'il y a ' . intdiv($d, 86400) . ' j';
};

$fullName = static function (array $row): string {
    $full = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));

    return $full !== '' ? $full : 'Candidat';
};

// Barre d’avancement à 4 segments (réception → instruction → intégration → intégré).
$progress = static function (array $stage) use ($e): string {
    $total = max(1, (int) ($stage['total'] ?? 4));
    $index = (int) ($stage['index'] ?? 0);
    if ($index < 1) {
        return '';
    }
    $html = '<span class="das-steps" role="img" aria-label="Étape ' . $index . ' sur ' . $total . '">';
    for ($i = 1; $i <= $total; $i++) {
        $html .= '<span class="das-steps__seg' . ($i <= $index ? ' is-on das-steps__seg--' . $e($stage['tone'] ?? 'muted') : '') . '"></span>';
    }

    return $html . '</span>';
};

// Lignes « Mes candidatures » : libellés côté candidat (pas de vocabulaire interne au staff).
$candidateStage = static function (array $row): array {
    $stage = EnlistmentStatusCatalog::stage($row);
    $label = match ($stage['key']) {
        'reception', 'instruction' => 'En cours d’examen',
        'interview' => 'Entretien planifié',
        'on_hold' => 'En attente',
        'onboarding', 'link_account' => 'Intégration en cours',
        'integrated' => 'Intégré',
        'closed' => 'Dossier clos',
        default => '—',
    };
    $hint = match ($stage['key']) {
        'interview' => $stage['hint'],
        'reception' => 'Dossier reçu',
        'instruction' => 'Un recruteur s’en occupe',
        'on_hold' => 'Décision reportée',
        'onboarding', 'link_account' => 'Accès et affectation en préparation',
        'integrated' => 'Bienvenue dans la communauté',
        default => '',
    };

    return ['label' => $label, 'hint' => $hint] + $stage;
};

// Ligne staff déjà enrichie côté contrôleur ; repli si une clé manque (ancienne session, test).
$staffMeta = static function (array $row): array {
    $stage = is_array($row['stage'] ?? null) ? $row['stage'] : EnlistmentStatusCatalog::stage($row);
    $status = is_array($row['status_meta'] ?? null)
        ? $row['status_meta']
        : EnlistmentStatusCatalog::status((string) ($row['status'] ?? ''), !empty($row['auto_rejected']));
    $delay = is_array($row['delay'] ?? null)
        ? $row['delay']
        : EnlistmentStatusCatalog::delay($row, (int) ($row['enlistment_sla_hours'] ?? 72));
    $retro = is_array($row['retro'] ?? null)
        ? $row['retro']
        : EnlistmentStatusCatalog::retro($row, $row['staff_retro_done_at'] ?? null, true);

    return [$stage, $status, $delay, $retro];
};

// Filtres rapides du tableau staff : clé => [libellé, prédicat].
$staffFilters = [
    'all' => ['Toutes', static fn (array $r): bool => true],
    'todo' => ['À traiter', static fn (array $r): bool => (string) ($r['status'] ?? '') === 'submitted'],
    'late' => ['En retard', static fn (array $r): bool => !empty($r['submitted_sla_breached'])],
    'onboarding' => ['Intégration', static fn (array $r): bool => in_array((string) (($r['stage'] ?? [])['key'] ?? ''), ['onboarding', 'link_account'], true)],
    'retro' => ['Bilans à faire', static fn (array $r): bool => (string) ($r['staff_retro_status'] ?? '') === 'due'],
    'closed' => ['Clos', static fn (array $r): bool => EnlistmentStatusCatalog::isClosed((string) ($r['status'] ?? ''))],
];
$rowFilterKeys = static function (array $row) use ($staffFilters): string {
    $keys = [];
    foreach ($staffFilters as $k => [, $pred]) {
        if ($pred($row)) {
            $keys[] = $k;
        }
    }

    return implode(' ', $keys);
};
?>
<div class="das-stack">
    <?php if ($showStaff && $staffApps !== []): ?>
        <?php
        $filterCounts = [];
        foreach ($staffFilters as $k => [, $pred]) {
            $filterCounts[$k] = count(array_filter($staffApps, $pred));
        }
        ?>
        <section id="dashboard-applications-staff" class="das-card das-card--staff" data-das-table>
            <div class="das-card__head">
                <div class="das-card__head-main">
                    <p class="das-kicker">Recrutement</p>
                    <div class="das-card__title-row">
                        <h2 class="das-card__title">Candidatures de la communauté</h2>
                        <span class="das-card__count"><?= count($staffApps) ?></span>
                    </div>
                    <p class="das-card__lead">Les 40 dossiers les plus récemment actifs. Cliquez sur un nom pour ouvrir le dossier.</p>
                </div>
                <a href="<?= $e(url('back-office/recruitments')) ?>" class="das-card__cta">Ouvrir le bureau recrutement <span aria-hidden="true">→</span></a>
            </div>

            <div class="das-filters" role="group" aria-label="Filtrer les candidatures">
                <?php foreach ($staffFilters as $k => [$label]): ?>
                    <?php if ($k !== 'all' && $filterCounts[$k] === 0) {
                        continue;
                    } ?>
                    <button type="button" class="das-filter<?= $k === 'all' ? ' is-active' : '' ?><?= in_array($k, ['late', 'retro'], true) ? ' das-filter--alert' : '' ?>" data-das-filter="<?= $e($k) ?>" aria-pressed="<?= $k === 'all' ? 'true' : 'false' ?>">
                        <?= $e($label) ?> <span class="das-filter__n"><?= (int) $filterCounts[$k] ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <ul class="das-cards" aria-label="Candidatures de la communauté">
                <?php foreach ($staffApps as $row): ?>
                    <?php [$stage, $status, $delay, $retro] = $staffMeta($row); ?>
                    <li class="das-cards__item" data-das-row="<?= $e($rowFilterKeys($row)) ?>">
                        <div class="das-cards__top">
                            <span class="das-sheet__who-text">
                                <a class="das-sheet__name das-link" href="<?= $e($row['dossier_url'] ?? url('back-office/recruitments/' . (int) ($row['id'] ?? 0) . '?dossier=1')) ?>"><?= $e($fullName($row)) ?></a>
                                <span class="das-sheet__mail"><?= $e($fmtDate($row['created_at'] ?? '') !== '' ? 'Déposée le ' . $fmtDate($row['created_at'] ?? '') : '') ?></span>
                            </span>
                            <span class="das-badge <?= $toneClass($status['tone']) ?>"><?= $e($status['label']) ?></span>
                        </div>
                        <div class="das-stage">
                            <?= $progress($stage) ?>
                            <span class="das-stage__label"><?= $e($stage['label']) ?></span>
                        </div>
                        <div class="das-cards__meta">
                            <?php if ((string) ($row['status'] ?? '') === 'submitted'): ?>
                                <span class="das-badge <?= $toneClass($delay['tone']) ?>"><?= $e($delay['label']) ?></span>
                            <?php endif; ?>
                            <?php if (in_array($retro['key'], ['due', 'done', 'waiting'], true)): ?>
                                <span class="das-badge <?= $toneClass($retro['tone']) ?>">Bilan : <?= $e($retro['label']) ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?= $e(!empty($retro['actionable']) ? ($row['bilan_url'] ?? '') : ($row['dossier_url'] ?? '')) ?>" class="das-btn das-btn--block"><?= !empty($retro['actionable']) ? 'Faire le bilan' : 'Ouvrir le dossier' ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="das-sheet">
                <table class="das-sheet__table das-sheet__table--staff">
                    <thead>
                        <tr>
                            <th class="das-sticky-col" scope="col">Candidat</th>
                            <th scope="col">Statut et étape</th>
                            <th scope="col">Poste / affectation</th>
                            <th scope="col" class="das-hide-md">Instructeur</th>
                            <th scope="col">Délai</th>
                            <th scope="col">Bilan 30 j</th>
                            <th scope="col" class="das-hide-lg">Dernier message</th>
                            <th scope="col" class="das-hide-sm">Dates</th>
                            <th scope="col" class="das-sticky-end"><span class="das-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffApps as $row): ?>
                            <?php
                            [$stage, $status, $delay, $retro] = $staffMeta($row);
                            $full = $fullName($row);
                            $dossierUrl = (string) ($row['dossier_url'] ?? url('back-office/recruitments/' . (int) ($row['id'] ?? 0) . '?dossier=1'));
                            $email = trim((string) ($row['email'] ?? ''));
                            $callsign = trim((string) ($row['callsign'] ?? ''));
                            $opening = trim((string) ($row['assignment_opening_title'] ?? ''));
                            $unit = trim((string) ($row['assignment_unit_label'] ?? ''));
                            $role = trim((string) ($row['assignment_role_label'] ?? ''));
                            $source = (string) ($row['assignment_source'] ?? '');
                            $instructor = trim((string) ($row['instructor_label'] ?? ''));
                            $comment = trim(str_replace(['[MISE EN ATTENTE]', '[DEMANDE ENTRETIEN]'], ['Mise en attente :', 'Demande d’entretien :'], (string) ($row['reviewer_comment'] ?? '')));
                            $createdTs = strtotime((string) ($row['created_at'] ?? ''));
                            $isRecent = $createdTs !== false && $createdTs >= strtotime('-7 days');
                            ?>
                            <tr data-das-row="<?= $e($rowFilterKeys($row)) ?>">
                                <td class="das-sticky-col">
                                    <div class="das-sheet__who">
                                        <span class="das-sheet__avatar" aria-hidden="true"><?= $e($initials((string) ($row['first_name'] ?? ''), (string) ($row['last_name'] ?? ''))) ?></span>
                                        <span class="das-sheet__who-text">
                                            <a class="das-sheet__name das-link" href="<?= $e($dossierUrl) ?>" title="Ouvrir le dossier de <?= $e($full) ?>">
                                                <?= $e($full) ?><?php if ($isRecent): ?><span class="das-badge das-badge--sky das-badge--new" title="Déposée il y a moins de 7 jours">Nouveau</span><?php endif; ?>
                                            </a>
                                            <?php if ($callsign !== ''): ?><span class="das-sheet__mail">Indicatif <?= $e($callsign) ?></span><?php endif; ?>
                                            <?php if ($email !== ''): ?><span class="das-sheet__mail" title="<?= $e($email) ?>"><?= $e($email) ?></span><?php endif; ?>
                                            <span class="das-sheet__mail das-sheet__mail--soft"><?= $e(EnlistmentStatusCatalog::channel((string) ($row['submitted_via'] ?? ''))) ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="das-badge <?= $toneClass($status['tone']) ?>"><?= $e($status['label']) ?></span>
                                    <div class="das-stage">
                                        <?= $progress($stage) ?>
                                        <span class="das-stage__label"><?= $e($stage['label']) ?></span>
                                    </div>
                                    <?php if (($stage['hint'] ?? '') !== ''): ?><span class="das-sheet__meta"><?= $e($stage['hint']) ?></span><?php endif; ?>
                                </td>
                                <td class="das-sheet__wrap">
                                    <?php if ($opening !== '' || $unit !== '' || $role !== ''): ?>
                                        <span class="das-sheet__strong"><?= $e($opening !== '' ? $opening : ($unit !== '' ? $unit : $role)) ?></span>
                                        <?php
                                        $sub = array_values(array_filter([
                                            $opening !== '' ? $unit : '',
                                            ($opening !== '' || $unit !== '') ? $role : '',
                                        ]));
                                        ?>
                                        <?php if ($sub !== []): ?><span class="das-sheet__meta"><?= $e(implode(' · ', $sub)) ?></span><?php endif; ?>
                                        <?php if ($source === 'member'): ?><span class="das-sheet__meta">Unité actuelle du membre</span><?php endif; ?>
                                    <?php elseif (EnlistmentStatusCatalog::isClosed((string) ($row['status'] ?? ''))): ?>
                                        <span class="das-sheet__muted">—</span>
                                    <?php else: ?>
                                        <a class="das-link das-link--soft" href="<?= $e($row['assignment_define_url'] ?? $dossierUrl) ?>">À définir</a>
                                    <?php endif; ?>
                                </td>
                                <td class="das-hide-md">
                                    <?php if ($instructor !== ''): ?>
                                        <span class="das-sheet__cell-strong"><?= $e($instructor) ?></span>
                                    <?php elseif ((string) ($row['status'] ?? '') === 'submitted'): ?>
                                        <span class="das-badge das-badge--amber">Personne</span>
                                    <?php else: ?>
                                        <span class="das-sheet__muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="das-badge <?= $toneClass($delay['tone']) ?>"><?= $e($delay['label']) ?></span>
                                    <?php if ($delay['meta'] !== ''): ?><span class="das-sheet__meta"><?= $e($delay['meta']) ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($retro['actionable'])): ?>
                                        <a href="<?= $e($row['bilan_url'] ?? $dossierUrl) ?>" class="das-badge <?= $toneClass($retro['tone']) ?> das-badge--action" title="Renseigner le bilan d’intégration"><?= $e($retro['label']) ?> →</a>
                                    <?php else: ?>
                                        <span class="das-badge <?= $toneClass($retro['tone']) ?>"><?= $e($retro['label']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($retro['meta'] !== ''): ?><span class="das-sheet__meta"><?= $e($retro['meta']) ?></span><?php endif; ?>
                                </td>
                                <td class="das-hide-lg" title="<?= $e($comment) ?>">
                                    <?= $comment !== '' ? '<span class="das-sheet__comment">' . $e($comment) . '</span>' : '<span class="das-sheet__muted">—</span>' ?>
                                </td>
                                <td class="das-sheet__nowrap das-hide-sm">
                                    <span class="das-sheet__cell-strong"><?= $e($fmtDate($row['created_at'] ?? '') ?: '—') ?></span>
                                    <span class="das-sheet__meta" title="<?= $e($fmtDate($row['last_action_at'] ?? ($row['updated_at'] ?? ''), 'd/m/Y H:i')) ?>">Action <?= $e($ago($row['last_action_at'] ?? ($row['updated_at'] ?? ''))) ?></span>
                                </td>
                                <td class="das-sticky-end">
                                    <?php if (!empty($retro['actionable'])): ?>
                                        <a href="<?= $e($row['bilan_url'] ?? $dossierUrl) ?>" class="das-btn das-btn--accent">Bilan</a>
                                    <?php else: ?>
                                        <a href="<?= $e($dossierUrl) ?>" class="das-btn">Ouvrir</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="das-empty-filter" hidden>Aucune candidature pour ce filtre.</p>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($myApps !== []): ?>
        <section id="dashboard-applications-mine" class="das-card das-card--mine">
            <div class="das-card__head">
                <div class="das-card__head-main">
                    <p class="das-kicker">Mon parcours</p>
                    <div class="das-card__title-row">
                        <h2 class="das-card__title">Mes candidatures</h2>
                        <span class="das-card__count"><?= count($myApps) ?></span>
                    </div>
                </div>
            </div>

            <ul class="das-cards" aria-label="Mes candidatures">
                <?php foreach ($myApps as $row): ?>
                    <?php $stage = $candidateStage($row); $status = EnlistmentStatusCatalog::status((string) ($row['status'] ?? '')); ?>
                    <li class="das-cards__item">
                        <div class="das-cards__top">
                            <span class="das-sheet__who-text">
                                <span class="das-sheet__name"><?= $e(trim((string) ($row['tenant_name'] ?? '')) ?: 'Communauté') ?></span>
                                <span class="das-sheet__mail"><?= $e(trim((string) ($row['opening_title'] ?? '')) ?: ($fmtDate($row['created_at'] ?? '') !== '' ? 'Déposée le ' . $fmtDate($row['created_at'] ?? '') : '')) ?></span>
                            </span>
                            <span class="das-badge <?= $toneClass($status['tone']) ?>"><?= $e($status['label']) ?></span>
                        </div>
                        <div class="das-stage">
                            <?= $progress($stage) ?>
                            <span class="das-stage__label"><?= $e($stage['label']) ?></span>
                        </div>
                        <?php if (is_string($row['candidate_portal_href'] ?? null)): ?>
                            <a href="<?= $e($row['candidate_portal_href']) ?>" class="das-btn das-btn--block">Ouvrir mon suivi</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="das-sheet">
                <table class="das-sheet__table das-sheet__table--mine">
                    <thead>
                        <tr>
                            <th class="das-sticky-col" scope="col">Communauté</th>
                            <th scope="col">Statut et étape</th>
                            <th scope="col">Poste visé</th>
                            <th scope="col" class="das-hide-md">Suivi de l’équipe</th>
                            <th scope="col" class="das-hide-lg">Partage du profil</th>
                            <th scope="col" class="das-hide-sm">Dates</th>
                            <th scope="col" class="das-sticky-end"><span class="das-sr">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myApps as $row): ?>
                            <?php
                            $stage = $candidateStage($row);
                            $status = EnlistmentStatusCatalog::status((string) ($row['status'] ?? ''));
                            $community = trim((string) ($row['tenant_name'] ?? '')) ?: 'Communauté';
                            $opening = trim((string) ($row['opening_title'] ?? ''));
                            if ($opening === '') {
                                $opening = trim((string) ($row['specialty'] ?? ''));
                            }
                            $manual = (string) ($row['candidate_portal_status_mode'] ?? 'steps') === 'manual'
                                ? trim((string) ($row['candidate_portal_status_manual_text'] ?? ''))
                                : '';
                            $comment = trim((string) ($row['reviewer_comment'] ?? ''));
                            $portalHref = is_string($row['candidate_portal_href'] ?? null) ? (string) $row['candidate_portal_href'] : null;
                            ?>
                            <tr>
                                <td class="das-sticky-col">
                                    <span class="das-sheet__who-text">
                                        <?php if ($portalHref !== null): ?>
                                            <a class="das-sheet__name das-link" href="<?= $e($portalHref) ?>"><?= $e($community) ?></a>
                                        <?php else: ?>
                                            <span class="das-sheet__name"><?= $e($community) ?></span>
                                        <?php endif; ?>
                                        <span class="das-sheet__mail das-sheet__mail--soft"><?= $e(EnlistmentStatusCatalog::channel((string) ($row['submitted_via'] ?? ''))) ?></span>
                                    </span>
                                </td>
                                <td>
                                    <span class="das-badge <?= $toneClass($status['tone']) ?>"><?= $e($status['label']) ?></span>
                                    <div class="das-stage">
                                        <?= $progress($stage) ?>
                                        <span class="das-stage__label"><?= $e($stage['label']) ?></span>
                                    </div>
                                    <?php if ($stage['hint'] !== ''): ?><span class="das-sheet__meta"><?= $e($stage['hint']) ?></span><?php endif; ?>
                                </td>
                                <td class="das-sheet__wrap"><?= $opening !== '' ? '<span class="das-sheet__cell-strong">' . $e($opening) . '</span>' : '<span class="das-sheet__muted">—</span>' ?></td>
                                <td class="das-hide-md" title="<?= $e($manual !== '' ? $manual : $comment) ?>">
                                    <?php if ($manual !== ''): ?>
                                        <span class="das-badge <?= $toneClass((string) ($row['candidate_portal_status_manual_band'] ?? 'amber')) ?>"><?= $e($manual) ?></span>
                                    <?php elseif ($comment !== ''): ?>
                                        <span class="das-sheet__comment"><?= $e($comment) ?></span>
                                    <?php else: ?>
                                        <span class="das-sheet__muted">Pas encore de message</span>
                                    <?php endif; ?>
                                </td>
                                <td class="das-hide-lg">
                                    <?php if (trim((string) ($row['consent_sharing_at'] ?? '')) !== ''): ?>
                                        <span class="das-badge das-badge--emerald">Autorisé</span>
                                    <?php else: ?>
                                        <span class="das-badge das-badge--muted">Non renseigné</span>
                                    <?php endif; ?>
                                </td>
                                <td class="das-sheet__nowrap das-hide-sm">
                                    <span class="das-sheet__cell-strong"><?= $e($fmtDate($row['created_at'] ?? '') ?: '—') ?></span>
                                    <span class="das-sheet__meta" title="<?= $e($fmtDate($row['updated_at'] ?? '', 'd/m/Y H:i')) ?>">Mise à jour <?= $e($ago($row['updated_at'] ?? ($row['created_at'] ?? ''))) ?></span>
                                </td>
                                <td class="das-sticky-end">
                                    <?php if ($portalHref !== null): ?>
                                        <a href="<?= $e($portalHref) ?>" class="das-btn">Mon suivi</a>
                                    <?php else: ?>
                                        <span class="das-sheet__muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
<script>
(function () {
    document.querySelectorAll('[data-das-table]').forEach(function (card) {
        var buttons = card.querySelectorAll('[data-das-filter]');
        var rows = card.querySelectorAll('[data-das-row]');
        var empty = card.querySelector('.das-empty-filter');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-das-filter');
                var shown = 0;
                buttons.forEach(function (b) {
                    var on = b === btn;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                rows.forEach(function (row) {
                    var keys = (' ' + (row.getAttribute('data-das-row') || '') + ' ');
                    var visible = key === 'all' || keys.indexOf(' ' + key + ' ') !== -1;
                    row.hidden = !visible;
                    if (visible && row.tagName === 'TR') {
                        shown++;
                    }
                });
                if (empty) {
                    empty.hidden = shown > 0;
                }
            });
        });
    });
})();
</script>
<style>
.das-stack { display: flex; flex-direction: column; gap: 1.25rem; width: 100%; }
.das-card {
    width: 100%;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    border-radius: 0.85rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.das-card__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1.25rem;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
}
.das-card__head-main { min-width: 0; flex: 1 1 12rem; }
.das-kicker {
    margin: 0;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #059669;
}
.das-card__title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.45rem 0.65rem; margin-top: 0.2rem; }
.das-card__title { margin: 0; font-size: 1.05rem; font-weight: 900; letter-spacing: -0.025em; color: #0f172a; line-height: 1.2; }
.das-card__lead { margin: 0.25rem 0 0; font-size: 0.75rem; font-weight: 550; color: #64748b; }
.das-card__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.55rem;
    height: 1.55rem;
    padding: 0 0.4rem;
    border-radius: 999px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #047857;
    font-size: 0.75rem;
    font-weight: 900;
    font-variant-numeric: tabular-nums;
}
.das-card__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 0.9rem;
    border-radius: 0.55rem;
    border: 1px solid #059669;
    background: #059669;
    color: #fff;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-decoration: none;
    white-space: nowrap;
    box-shadow: 0 6px 14px -8px rgba(5, 150, 105, 0.7);
    transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}
.das-card__cta:hover { background: #047857; border-color: #047857; color: #fff; transform: translateY(-1px); }

/* Filtres rapides */
.das-filters { display: flex; flex-wrap: wrap; gap: 0.4rem; padding: 0.75rem 1.15rem; border-bottom: 1px solid #e2e8f0; }
.das-filter {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-height: 2rem;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #0f172a;
    font-size: 0.6875rem;
    font-weight: 800;
    cursor: pointer;
}
.das-filter:hover { border-color: #059669; }
.das-filter.is-active { background: #0f172a; border-color: #0f172a; color: #fff; }
.das-filter--alert:not(.is-active) { border-color: #fecdd3; background: #fff1f2; color: #9f1239; }
.das-filter__n { font-variant-numeric: tabular-nums; opacity: 0.75; }
.das-filter:focus-visible, .das-btn:focus-visible, .das-link:focus-visible { outline: 2px solid #059669; outline-offset: 2px; }

/* Cartes mobiles */
.das-cards { list-style: none; margin: 0; padding: 0.75rem; display: none; flex-direction: column; gap: 0.65rem; }
.das-cards__item { display: flex; flex-direction: column; gap: 0.55rem; padding: 0.85rem 0.9rem; border: 1px solid #e2e8f0; border-radius: 0.75rem; background: #f8fafc; }
.das-cards__item[hidden] { display: none; }
.das-cards__top { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.65rem; }
.das-cards__meta { display: flex; flex-wrap: wrap; gap: 0.35rem; }

/* Tableau */
.das-sheet { overflow: auto; max-height: min(36rem, 72vh); -webkit-overflow-scrolling: touch; }
.das-sheet__table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8125rem; line-height: 1.35; }
.das-sheet__table--staff { min-width: 62rem; }
.das-sheet__table--mine { min-width: 48rem; }
.das-sheet__table th,
.das-sheet__table td { border-bottom: 1px solid #e2e8f0; padding: 0.6rem 0.7rem; vertical-align: top; text-align: left; background: #fff; }
.das-sheet__table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #0f172a;
    color: #e2e8f0;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    white-space: nowrap;
    border-bottom: 1px solid #1e293b;
}
.das-sheet__table tbody tr:nth-child(even) td { background: #f8fafc; }
.das-sheet__table tbody tr:hover td { background: #ecfdf5; }
.das-sheet__table tbody tr[hidden] { display: none; }
.das-sticky-col { position: sticky; left: 0; z-index: 1; box-shadow: 1px 0 0 #e2e8f0; min-width: 12rem; max-width: 16rem; }
.das-sticky-end { position: sticky; right: 0; z-index: 1; box-shadow: -1px 0 0 #e2e8f0; text-align: right !important; white-space: nowrap; width: 1%; }
.das-sheet__table thead .das-sticky-col,
.das-sheet__table thead .das-sticky-end { z-index: 4; background: #0f172a; box-shadow: none; }

.das-sheet__nowrap { white-space: nowrap; }
.das-sheet__wrap { min-width: 9rem; max-width: 14rem; }
.das-sheet__comment { max-width: 14rem; min-width: 9rem; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; color: #334155; font-weight: 550; }
.das-sheet__muted { color: #64748b; font-weight: 550; }
.das-sheet__cell-strong { color: #0f172a; font-weight: 650; }
.das-sheet__strong { display: block; font-weight: 700; color: #0f172a; }
.das-sheet__meta { display: block; margin-top: 0.15rem; font-size: 0.6875rem; color: #475569; font-weight: 600; font-variant-numeric: tabular-nums; }
.das-sheet__who { display: flex; align-items: flex-start; gap: 0.55rem; min-width: 0; }
.das-sheet__avatar {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: 0.45rem;
    border: 1px solid #cbd5e1;
    background: #f1f5f9;
    font-size: 0.625rem;
    font-weight: 800;
    color: #1e293b;
}
.das-sheet__who-text { display: flex; flex-direction: column; min-width: 0; gap: 0.05rem; }
.das-sheet__name { font-weight: 800; color: #0f172a; }
.das-sheet__mail { font-size: 0.7rem; color: #475569; font-weight: 550; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 13rem; }
.das-sheet__mail--soft { color: #94a3b8; }
.das-link { color: #0f172a; text-decoration: none; }
.das-link:hover { color: #047857; text-decoration: underline; text-underline-offset: 2px; }
.das-link--soft { font-size: 0.75rem; font-weight: 800; color: #047857; text-decoration: underline; text-decoration-color: rgba(4, 120, 87, 0.35); }

/* Étape */
.das-stage { display: flex; align-items: center; gap: 0.45rem; margin-top: 0.35rem; white-space: nowrap; }
.das-stage__label { font-size: 0.75rem; font-weight: 700; color: #0f172a; }
.das-steps { display: inline-flex; gap: 2px; flex-shrink: 0; }
.das-steps__seg { width: 0.7rem; height: 0.3rem; border-radius: 999px; background: #e2e8f0; }
.das-steps__seg.is-on { background: #94a3b8; }
.das-steps__seg--emerald.is-on { background: #10b981; }
.das-steps__seg--amber.is-on { background: #f59e0b; }
.das-steps__seg--sky.is-on { background: #0ea5e9; }
.das-steps__seg--orange.is-on { background: #f97316; }
.das-steps__seg--rose.is-on { background: #f43f5e; }

.das-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.2rem 0.5rem;
    border-radius: 999px;
    border: 1px solid transparent;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    white-space: nowrap;
    max-width: 13rem;
    overflow: hidden;
    text-overflow: ellipsis;
    text-decoration: none;
}
.das-badge--amber { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.das-badge--emerald { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.das-badge--rose { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
.das-badge--slate { background: #f1f5f9; border-color: #cbd5e1; color: #334155; }
.das-badge--sky { background: #f0f9ff; border-color: #bae6fd; color: #075985; }
.das-badge--orange { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
.das-badge--muted { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
.das-badge--new { margin-left: 0.35rem; padding: 0.08rem 0.35rem; font-size: 0.5625rem; vertical-align: middle; }
.das-badge--action:hover { filter: brightness(0.96); text-decoration: underline; }

.das-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.4rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid #0f172a;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.das-btn:hover { background: #059669; border-color: #059669; color: #fff; }
.das-btn--accent { background: #b45309; border-color: #b45309; }
.das-btn--block { width: 100%; margin-top: 0.15rem; }
.das-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.das-empty-filter { margin: 0; padding: 1rem 1.15rem; font-size: 0.8125rem; font-weight: 600; color: #475569; }

@media (max-width: 1399.98px) { .das-hide-lg { display: none !important; } }
@media (max-width: 1099.98px) { .das-hide-md { display: none !important; } }
@media (max-width: 899.98px) {
    .das-hide-sm { display: none !important; }
    .das-sheet__table--staff, .das-sheet__table--mine { min-width: 40rem; }
}
@media (max-width: 719.98px) {
    .das-cards { display: flex; }
    .das-sheet { display: none !important; }
    .das-card__head { padding: 0.9rem 1rem; }
    .das-card__title { font-size: 0.95rem; }
    .das-card__cta { width: 100%; justify-content: center; }
    .das-filters { padding: 0.65rem 0.75rem; }
}
</style>
