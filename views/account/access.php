<?php
declare(strict_types=1);

/**
 * Self-service « Mes accès » : clarifie compte / personnage / rôle, et affiche le statut
 * des demandes d’élévation (comme demandeur et comme personne concernée), pour ne pas
 * laisser l’utilisateur dans le flou après une demande.
 *
 * @var string $accountRoleLabel
 * @var array<string,mixed>|null $accountGrade
 * @var list<array<string,mixed>> $elevationRequestedByMe
 * @var list<array<string,mixed>> $elevationRequestedAboutMe
 * @var int $elevationOpenCount
 * @var array<string,string> $elevationKindLabels
 */

$accountNavKey = 'access';
$accountTitle = 'Mes accès & rôle';
$accountLead = 'Ce que recouvrent votre compte, votre personnage et votre rôle — et le suivi de vos demandes d’élévation.';
require base_path('views/partials/account/shell_open.php');

$roleLabel = trim((string) ($accountRoleLabel ?? ''));
$grade = is_array($accountGrade ?? null) ? $accountGrade : null;
$gradeLabel = $grade
    ? trim((string) ($grade['label_short'] ?? $grade['short_name'] ?? $grade['label_long'] ?? $grade['name'] ?? ''))
    : '';
$kindLabels = is_array($elevationKindLabels ?? null) ? $elevationKindLabels : [];

$statusMeta = static function (string $status): array {
    return match ($status) {
        'approved' => ['label' => 'Acceptée', 'class' => 'account-hub__badge--ok'],
        'rejected' => ['label' => 'Refusée', 'class' => 'account-hub__badge--off'],
        'in_review' => ['label' => 'En cours d’examen', 'class' => 'account-hub__badge--warn'],
        default => ['label' => 'En attente', 'class' => 'account-hub__badge--warn'],
    };
};

$requestedByMe = is_array($elevationRequestedByMe ?? null) ? $elevationRequestedByMe : [];
$requestedAboutMe = is_array($elevationRequestedAboutMe ?? null) ? $elevationRequestedAboutMe : [];
?>

<div class="account-hub__stack">
    <section class="account-hub__panel" aria-labelledby="access-explain-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Comprendre vos accès</p>
            <h2 id="access-explain-heading" class="account-hub__panel-title">Trois choses distinctes</h2>
            <p class="account-hub__panel-desc">Elles sont souvent confondues — voici ce que gère chacune.</p>
        </div>
        <div class="account-hub__panel-body">
            <div class="account-hub__stat-grid">
                <div class="account-hub__stat">
                    <p class="account-hub__stat-label">Votre compte</p>
                    <p class="account-hub__stat-value">Identité civile</p>
                    <p class="account-hub__stat-meta">
                        Adresse e-mail, mot de passe, apparence du portail. Un compte appartient à une seule communauté à la fois.
                        <br><a href="<?= htmlspecialchars(url('account'), ENT_QUOTES, 'UTF-8') ?>">Vue d’ensemble →</a>
                    </p>
                </div>
                <div class="account-hub__stat">
                    <p class="account-hub__stat-label">Votre personnage</p>
                    <p class="account-hub__stat-value">Fiche opérationnelle</p>
                    <p class="account-hub__stat-meta">
                        Identité « in-universe », unité, matricule. Le suivi RH (progression, tuteur, échéances) est une autre couche, distincte des droits d’accès.
                        <br><a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>">Voir ma fiche →</a>
                    </p>
                </div>
                <div class="account-hub__stat">
                    <p class="account-hub__stat-label">Votre rôle &amp; grade</p>
                    <p class="account-hub__stat-value"><?= htmlspecialchars($roleLabel !== '' ? $roleLabel : 'Non défini', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="account-hub__stat-meta">
                        <?= $gradeLabel !== '' ? 'Grade : ' . htmlspecialchars($gradeLabel, ENT_QUOTES, 'UTF-8') : 'Grade non attribué' ?>.
                        Ce que vous pouvez faire dans la communauté (accès, modération, RH…) est déterminé par ce rôle.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="account-hub__panel" aria-labelledby="access-elevation-heading">
        <div class="account-hub__panel-head">
            <p class="account-hub__panel-kicker">Suivi</p>
            <h2 id="access-elevation-heading" class="account-hub__panel-title">
                Demandes d’élévation
                <?php if ($elevationOpenCount > 0): ?>
                <span class="account-hub__badge account-hub__badge--warn"><?= (int) $elevationOpenCount ?> en cours</span>
                <?php endif; ?>
            </h2>
            <p class="account-hub__panel-desc">
                Une « élévation » est une demande d’évolution de grade, de rôle ou de droits, transmise aux personnes habilitées de votre communauté.
            </p>
        </div>
        <div class="account-hub__panel-body">
            <?php if ($requestedAboutMe === [] && $requestedByMe === []): ?>
            <p class="account-hub__stat-meta">Aucune demande d’élévation enregistrée pour le moment.</p>
            <?php else: ?>

                <?php if ($requestedAboutMe !== []): ?>
                <p class="account-hub__nav-group-title" style="margin:0 0 .65rem">Vous concernant</p>
                <div class="account-hub__table-wrap" style="margin-bottom:1.5rem">
                    <table class="account-hub__table">
                        <thead>
                            <tr><th>Type</th><th>Demandée par</th><th>Date</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requestedAboutMe as $r): ?>
                                <?php
                                $st = $statusMeta((string) ($r['status'] ?? 'pending'));
                                $kind = (string) ($r['kind'] ?? 'general');
                                $requesterName = trim((string) ($r['requester_display_name'] ?? '')) ?: trim((string) ($r['requester_email'] ?? '')) ?: 'Membre';
                                $createdAt = (string) ($r['created_at'] ?? '');
                                $createdFmt = $createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : '—';
                                $resNote = trim((string) ($r['resolution_note'] ?? ''));
                                ?>
                            <tr>
                                <td><?= htmlspecialchars($kindLabels[$kind] ?? 'Situation RH', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($requesterName, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="account-hub__badge <?= htmlspecialchars($st['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($resNote !== ''): ?><br><small><?= htmlspecialchars($resNote, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if ($requestedByMe !== []): ?>
                <p class="account-hub__nav-group-title" style="margin:0 0 .65rem">Envoyées par vous</p>
                <div class="account-hub__table-wrap">
                    <table class="account-hub__table">
                        <thead>
                            <tr><th>Type</th><th>Concernant</th><th>Date</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requestedByMe as $r): ?>
                                <?php
                                $st = $statusMeta((string) ($r['status'] ?? 'pending'));
                                $kind = (string) ($r['kind'] ?? 'general');
                                $targetName = trim((string) ($r['target_display_name'] ?? '')) ?: trim((string) ($r['target_email'] ?? '')) ?: 'Membre';
                                $createdAt = (string) ($r['created_at'] ?? '');
                                $createdFmt = $createdAt !== '' ? date('d/m/Y', strtotime($createdAt)) : '—';
                                $resNote = trim((string) ($r['resolution_note'] ?? ''));
                                ?>
                            <tr>
                                <td><?= htmlspecialchars($kindLabels[$kind] ?? 'Situation RH', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="account-hub__badge <?= htmlspecialchars($st['class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($resNote !== ''): ?><br><small><?= htmlspecialchars($resNote, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </section>
</div>

<style>
.account-hub__table-wrap { overflow-x: auto; }
.account-hub__table { width: 100%; border-collapse: collapse; font-size: .8125rem; }
.account-hub__table th, .account-hub__table td { padding: .55rem .65rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.account-hub__table thead th { font-size: .625rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
.account-hub__table small { color: #64748b; }
</style>

<?php require base_path('views/partials/account/shell_close.php'); ?>
