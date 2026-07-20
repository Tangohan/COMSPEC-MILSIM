<?php
declare(strict_types=1);

$m = is_array($member ?? null) ? $member : [];
$id = (int) ($m['id'] ?? 0);
$assignments = is_array($memberAssignments ?? null) ? $memberAssignments : [];
$roleNames = is_array($memberRoleNames ?? null) ? $memberRoleNames : [];
$jobRoles = is_array($memberJobRoles ?? null) ? $memberJobRoles : [];
$units = is_array($orgUnits ?? null) ? $orgUnits : [];
$canEditProfiles = (bool) ($canEditProfiles ?? false);
$canManageStatus = (bool) ($canManageStatus ?? false);
$canManageAssignments = (bool) ($canManageAssignments ?? false);
$canRequestElevation = (bool) ($canRequestElevation ?? false);
$csrfToken = (string) ($csrfToken ?? '');
$communityName = trim((string) ($communityName ?? ($m['community_name'] ?? 'Communauté')));
$elevationCatalog = is_array($elevationCatalog ?? null) ? $elevationCatalog : [];
$elevationCooldownSeconds = (int) ($elevationCooldownSeconds ?? 0);
$elevationCooldownLabel = static function (int $seconds): string {
    $hours = max(1, (int) ceil($seconds / 3600));
    if ($hours < 24) {
        return $hours . ' heure' . ($hours > 1 ? 's' : '');
    }

    $days = max(1, (int) ceil($hours / 24));

    return $days . ' jour' . ($days > 1 ? 's' : '');
};

$elevationHistory = is_array($elevationHistory ?? null) ? $elevationHistory : [];
$elevStatusLabel = static function (string $status): string {
    return match ($status) {
        'pending' => 'En attente',
        'in_review' => 'En cours d’examen',
        'approved' => 'Acceptée',
        'rejected' => 'Refusée',
        default => $status,
    };
};

$status = (string) ($m['status'] ?? '');
$display = trim((string) ($m['display_name'] ?? ''));
$callsign = trim((string) ($m['callsign'] ?? ''));
$email = (string) ($m['email'] ?? '');
$name = $display !== '' ? $display : ($callsign !== '' ? $callsign : $email);
$grade = trim((string) ($m['grade_short'] ?? ''));
if ($grade === '') {
    $grade = trim((string) ($m['grade_long'] ?? ''));
}
$fonction = trim((string) ($m['personnel_job_role_name'] ?? ''));
if ($fonction === '') {
    $fonction = trim((string) ($m['primary_role'] ?? ''));
}
$unit = trim((string) ($m['unit_name'] ?? ''));
$unitId = (int) ($m['unit_id'] ?? 0);
$clearanceRaw = trim((string) ($m['clearance_level'] ?? ''));
$clearanceLabels = \App\Services\Documents\DocumentAccessService::getClassificationLevelLabels();
$clearanceLabel = $clearanceRaw !== '' ? ($clearanceLabels[$clearanceRaw] ?? $clearanceRaw) : '';
$clearanceReviewedAt = trim((string) ($m['clearance_reviewed_at'] ?? ''));

$statusLabel = static function (string $raw): string {
    return match ($raw) {
        'active' => 'Compte actif',
        'inactive' => 'Compte inactif',
        'pending_verification' => 'En attente de vérification de l’e-mail',
        default => $raw !== '' ? $raw : '—',
    };
};
?>
<section class="eff-page-head">
    <p class="eff-page-kicker">Fiche membre</p>
    <h1 class="eff-page-title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="eff-page-lead">
        Synthèse RH pour l’action quotidienne : statut, rôles, fonction et unité
        dans <strong class="eff-text-accent"><?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?></strong>.
        Les écrans détaillés restent disponibles dans le back-office.
    </p>
    <p style="margin-top:1rem">
        <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(effectifs_workspace_url(), ENT_QUOTES, 'UTF-8') ?>">← Retour au tableur</a>
    </p>
</section>

<div class="eff-member-grid">
    <div class="eff-panel">
        <p class="eff-page-kicker">Identité &amp; situation</p>
        <dl class="eff-dl" style="margin-top:1rem">
            <div>
                <dt>Nom affiché</dt>
                <dd><?= htmlspecialchars($display !== '' ? $display : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Indicatif</dt>
                <dd><?= htmlspecialchars($callsign !== '' ? $callsign : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Adresse e-mail</dt>
                <dd><?= htmlspecialchars($email !== '' ? $email : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Communauté</dt>
                <dd><span class="eff-tag eff-tag--community"><?= htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8') ?></span></dd>
            </div>
            <div>
                <dt>Grade</dt>
                <dd>
                    <?php if ($grade !== ''): ?>
                        <span class="eff-tag eff-tag--grade"><?= htmlspecialchars($grade, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Fonction</dt>
                <dd><?= htmlspecialchars($fonction !== '' ? $fonction : '—', ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Habilitation</dt>
                <dd>
                    <?php if ($clearanceLabel !== ''): ?>
                        <span class="eff-tag"><?= htmlspecialchars($clearanceLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($clearanceReviewedAt !== ''): ?>
                            <span style="font-size:11px;color:rgba(242,244,243,.5);margin-left:.35rem">revue le <?= htmlspecialchars(date('d/m/Y', strtotime($clearanceReviewedAt)), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                    <p style="margin:.35rem 0 0;font-size:11px;color:rgba(242,244,243,.5)">Se modifie via une demande d’élévation ci-contre.</p>
                </dd>
            </div>
            <div>
                <dt>Unité</dt>
                <dd>
                    <?php if ($unit !== ''): ?>
                        <span class="eff-tag eff-tag--unit"><?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <span class="eff-tag eff-tag--warn">Sans unité</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Statut</dt>
                <dd><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
                <dt>Rôles</dt>
                <dd>
                    <?php if ($roleNames === []): ?>
                        <span class="eff-tag eff-tag--warn">Sans rôle</span>
                    <?php else: ?>
                        <div class="eff-tags">
                            <?php foreach ($roleNames as $rn): ?>
                                <span class="eff-tag"><?= htmlspecialchars((string) $rn, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </dd>
            </div>
            <?php if ($jobRoles !== []): ?>
            <div>
                <dt>Emplois métier</dt>
                <dd>
                    <?php
                    $jrLabels = [];
                    foreach ($jobRoles as $jr) {
                        $jrLabels[] = (string) ($jr['role_name'] ?? '');
                    }
                    echo htmlspecialchars(implode(', ', array_filter($jrLabels)), ENT_QUOTES, 'UTF-8');
                    ?>
                </dd>
            </div>
            <?php endif; ?>
        </dl>

        <?php if ($assignments !== []): ?>
            <p class="eff-section-label" style="margin-top:1.25rem">Affectations actives</p>
            <ul style="margin:0.5rem 0 0;padding-left:1.1rem;color:rgba(242,244,243,.7);font-size:13px;line-height:1.55">
                <?php foreach ($assignments as $a): ?>
                    <li>
                        <?= htmlspecialchars((string) ($a['unit_name'] ?? 'Unité'), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($a['role_name'])): ?>
                            — <?= htmlspecialchars((string) $a['role_name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                        <?php if (!empty($a['is_primary'])): ?>
                            <span class="eff-badge eff-badge--active" style="margin-left:.35rem">Principale</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($elevationHistory !== []): ?>
            <p class="eff-section-label" style="margin-top:1.35rem">Historique des élévations</p>
            <ul style="margin:0.5rem 0 0;padding-left:1.1rem;color:rgba(242,244,243,.7);font-size:13px;line-height:1.6">
                <?php foreach ($elevationHistory as $eh): ?>
                    <?php
                    $ehStatus = (string) ($eh['status'] ?? 'pending');
                    $ehRequester = trim((string) ($eh['requester_display_name'] ?? '')) ?: trim((string) ($eh['requester_email'] ?? '')) ?: 'Membre';
                    $ehDate = (string) ($eh['created_at'] ?? '');
                    $ehDateFmt = $ehDate !== '' ? date('d/m/Y', strtotime($ehDate)) : '—';
                    ?>
                    <li>
                        <?= htmlspecialchars($ehDateFmt, ENT_QUOTES, 'UTF-8') ?> — demandée par <?= htmlspecialchars($ehRequester, ENT_QUOTES, 'UTF-8') ?>
                        <span class="eff-badge <?= $ehStatus === 'approved' ? 'eff-badge--active' : '' ?>" style="margin-left:.35rem"><?= htmlspecialchars($elevStatusLabel($ehStatus), ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($canManageAssignments): ?>
            <p class="eff-section-label" style="margin-top:1.35rem">Modifier l’unité</p>
            <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/affectation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-pop__form" style="margin-top:.65rem">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_to" value="member">
                <label for="eff-member-unit">Unité de rattachement</label>
                <select id="eff-member-unit" name="unit_id">
                    <option value="0">Retirer l’affectation</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= (int) ($u['id'] ?? 0) ?>" <?= $unitId === (int) ($u['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="eff-btn eff-btn--primary">Enregistrer l’affectation</button>
            </form>
        <?php endif; ?>
    </div>

    <aside class="eff-panel">
        <p class="eff-page-kicker">Actions rapides</p>
        <div class="eff-quick-stack" style="margin-top:1rem">
            <?php if ($canEditProfiles): ?>
                <a class="eff-btn eff-btn--primary" href="<?= htmlspecialchars(url('back-office/users/' . $id . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Modifier le compte</a>
            <?php endif; ?>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('personnel/' . $id), ENT_QUOTES, 'UTF-8') ?>">Ouvrir le dossier personnel</a>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('personnel/' . $id . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Éditer le dossier</a>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('back-office/personnel-job-roles/assignments'), ENT_QUOTES, 'UTF-8') ?>">Gérer les emplois métier</a>
            <a class="eff-btn eff-btn--ghost" href="<?= htmlspecialchars(url('back-office/roles'), ENT_QUOTES, 'UTF-8') ?>">Gérer les rôles</a>

            <?php if ($canRequestElevation && $elevationCooldownSeconds > 0): ?>
                <p class="eff-member-elevate__title" style="opacity:.6">
                    Demande d’élévation déjà envoyée — patientez <?= htmlspecialchars($elevationCooldownLabel($elevationCooldownSeconds), ENT_QUOTES, 'UTF-8') ?> avant d’en renvoyer une.
                </p>
            <?php elseif ($canRequestElevation): ?>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/elevation'), ENT_QUOTES, 'UTF-8') ?>" class="eff-pop__form eff-member-elevate">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="return_to" value="member">
                    <p class="eff-member-elevate__title">Demander une élévation</p>
                    <?php
                    $fieldIdPrefix = 'eff-member-elev';
                    $selectedKind = 'grade';
                    $includeUnit = true;
                    require base_path('views/admin/effectifs_workspace/partials/elevation_request_fields.php');
                    ?>
                    <button type="submit" class="eff-btn eff-btn--warn">Envoyer la demande</button>
                </form>
            <?php endif; ?>

            <?php if ($canManageStatus): ?>
                <form method="post" action="<?= htmlspecialchars(effectifs_workspace_url('membres/' . $id . '/statut'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="eff-quick-status" style="font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:rgba(242,244,243,.4)">Changer le statut</label>
                    <select id="eff-quick-status" name="status" style="width:100%;border:1px solid rgba(242,244,243,.12);background:#0a0d0c;color:#e8eeec;padding:.7rem;min-height:2.75rem">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Compte actif</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Compte inactif</option>
                        <option value="pending_verification" <?= $status === 'pending_verification' ? 'selected' : '' ?>>E-mail à vérifier</option>
                    </select>
                    <button type="submit" class="eff-btn eff-btn--ghost">Enregistrer le statut</button>
                </form>
            <?php endif; ?>
        </div>
    </aside>
</div>
