<?php
declare(strict_types=1);

/**
 * RH du membre connecté : élévation (même circuit que le bureau effectifs, pour soi)
 * et demandes d’évolution déjà prévues dans l’espace RH.
 *
 * @var array<string,mixed> $dashboard_elevation_catalog
 * @var bool $can_request_self_elevation
 * @var bool $elevation_no_recipients
 * @var int $elevation_cooldown_seconds
 * @var list<array<string,mixed>> $elevation_history_mine
 * @var list<array<string,mixed>> $rh_my_mobility
 * @var bool $rh_mobility_schema_ready
 */

use App\Repositories\PersonnelMobilityRequestRepository;
use App\Services\Effectifs\EffectifsStaffAlertService;

$catalog = is_array($dashboard_elevation_catalog ?? null) ? $dashboard_elevation_catalog : [];
$canElev = !empty($can_request_self_elevation);
$noRecipients = !empty($elevation_no_recipients);
$cooldown = (int) ($elevation_cooldown_seconds ?? 0);
$elevHistory = is_array($elevation_history_mine ?? null) ? $elevation_history_mine : [];
$mobilityReady = !empty($rh_mobility_schema_ready);
$myMobility = is_array($rh_my_mobility ?? null) ? $rh_my_mobility : [];
$mobilityTypeLabels = PersonnelMobilityRequestRepository::TYPE_LABELS;
$mobilityStatusLabels = PersonnelMobilityRequestRepository::STATUS_LABELS;
$kindLabels = EffectifsStaffAlertService::ELEVATION_KIND_LABELS;
$csrfField = \App\Core\Csrf::field();

$elevStatusLabel = static function (string $status): string {
    return match ($status) {
        'approved' => 'Acceptée',
        'rejected' => 'Refusée',
        'in_review' => 'En cours d’examen',
        default => 'En attente',
    };
};
?>
<section class="dash-hub-panel" id="dashboard-member-rh" aria-labelledby="dash-member-rh-title">
    <div class="dash-hub-panel__head">
        <div>
            <p class="dash-hub-panel__kicker">Personnel</p>
            <h2 id="dash-member-rh-title" class="dash-hub-panel__title">Mon dossier RH</h2>
            <p class="dash-hub-panel__lead">Demandez une élévation de grade ou de rôle, ou un avancement. L’encadrement reçoit la demande ; ce n’est pas le tableur de gestion des effectifs.</p>
        </div>
        <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh'), ENT_QUOTES, 'UTF-8') ?>" class="dash-hub-panel__ghost">Espace RH complet</a>
    </div>

    <div class="dash-rh-grid">
        <div class="dash-rh-col" id="elevation">
            <h3 class="dash-rh-col__title">Demande d’élévation</h3>
            <p class="dash-rh-col__hint">Grade, rôle, fonction ou affectation — transmis aux personnes habilitées de la communauté.</p>

            <?php if ($cooldown > 0): ?>
                <p class="dash-hub-panel__empty">Une demande est déjà en cours. Vous pourrez en renvoyer une ultérieurement.</p>
            <?php elseif ($noRecipients): ?>
                <p class="dash-hub-panel__empty">Aucune personne habilitée n’est joignable pour traiter une élévation dans cette communauté.</p>
            <?php elseif ($canElev): ?>
                <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/elevation'), ENT_QUOTES, 'UTF-8') ?>" class="dash-rh-form">
                    <?= $csrfField ?>
                    <input type="hidden" name="return_to" value="dashboard">
                    <?php
                    $fieldIdPrefix = 'dash-elev';
                    $elevationCatalog = $catalog;
                    $selectedKind = 'grade';
                    $includeUnit = true;
                    require base_path('views/admin/effectifs_workspace/partials/elevation_request_fields.php');
                    ?>
                    <button type="submit" class="dash-hub-panel__cta">Transmettre la demande</button>
                </form>
            <?php else: ?>
                <p class="dash-hub-panel__empty">La demande d’élévation n’est pas disponible pour le moment.</p>
            <?php endif; ?>

            <?php if ($elevHistory !== []): ?>
                <ul class="dash-rh-history">
                    <?php foreach ($elevHistory as $row): ?>
                        <?php
                        $st = $elevStatusLabel((string) ($row['status'] ?? 'pending'));
                        $kind = (string) ($row['kind'] ?? 'general');
                        $createdAt = (string) ($row['created_at'] ?? '');
                        $createdFmt = $createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : '—';
                        ?>
                        <li>
                            <strong><?= htmlspecialchars($kindLabels[$kind] ?? 'Situation RH', ENT_QUOTES, 'UTF-8') ?></strong>
                            · <?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>
                            · <?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="dash-rh-col" id="mobilite">
            <h3 class="dash-rh-col__title">Demande d’avancement</h3>
            <p class="dash-rh-col__hint">Souhait d’évolution, changement d’unité ou candidature à un poste interne.</p>

            <?php if ($mobilityReady): ?>
                <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/mobilite'), ENT_QUOTES, 'UTF-8') ?>" class="dash-rh-form">
                    <?= $csrfField ?>
                    <input type="hidden" name="return_to" value="dashboard">
                    <label for="dash-rh-request-type">Type de demande</label>
                    <select id="dash-rh-request-type" name="request_type">
                        <?php foreach ($mobilityTypeLabels as $k => $lab): ?>
                            <option value="<?= htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') ?>" <?= $k === 'career_wish' ? 'selected' : '' ?>><?= htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="dash-rh-target">Poste ou unité visée</label>
                    <input id="dash-rh-target" type="text" name="target_label" maxlength="200" placeholder="Ex. Chef d’équipe, radio…">
                    <label for="dash-rh-motivation">Motivation</label>
                    <textarea id="dash-rh-motivation" name="motivation" rows="2" maxlength="2000" placeholder="Pourquoi ce mouvement ?"></textarea>
                    <button type="submit" class="dash-hub-panel__cta">Envoyer à l’encadrement</button>
                </form>
                <?php if ($myMobility !== []): ?>
                    <ul class="dash-rh-history">
                        <?php foreach ($myMobility as $m): ?>
                            <li>
                                <?= htmlspecialchars((string) ($mobilityTypeLabels[$m['request_type'] ?? ''] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                — <?= htmlspecialchars((string) ($mobilityStatusLabels[$m['status'] ?? ''] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php $tl = trim((string) ($m['target_label'] ?? '')); if ($tl !== ''): ?>
                                    · <?= htmlspecialchars($tl, ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else: ?>
                <p class="dash-hub-panel__empty">Les demandes d’évolution ne sont pas encore proposées sur cette communauté.</p>
            <?php endif; ?>

            <p class="dash-hub-panel__hint">
                <a href="<?= htmlspecialchars(url('personnel/mon-espace-rh') . '#absences', ENT_QUOTES, 'UTF-8') ?>">Déclarer une absence</a>
                ·
                <a href="<?= htmlspecialchars(url('account/acces'), ENT_QUOTES, 'UTF-8') ?>">Suivi de mes accès</a>
            </p>
        </div>
    </div>
</section>
