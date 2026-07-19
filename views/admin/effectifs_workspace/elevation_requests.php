<?php
declare(strict_types=1);

/**
 * @var list<array<string,mixed>> $elevationRequests
 * @var bool $elevationShowAll
 * @var array<string,string> $elevationKindLabels
 */

$requests = is_array($elevationRequests ?? null) ? $elevationRequests : [];
$showAll = (bool) ($elevationShowAll ?? false);
$kindLabels = is_array($elevationKindLabels ?? null) ? $elevationKindLabels : [];

$statusLabel = static function (string $status): string {
    return match ($status) {
        'pending' => 'En attente',
        'in_review' => 'En cours d’examen',
        'approved' => 'Acceptée',
        'rejected' => 'Refusée',
        default => $status,
    };
};
$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'approved' => 'eff-badge--community',
        'rejected' => 'eff-badge--muted',
        'in_review' => 'eff-badge--intra',
        default => 'eff-badge--intra',
    };
};
$nameOf = static function (?string $display, ?string $email): string {
    $display = trim((string) $display);
    if ($display !== '') {
        return $display;
    }
    $email = trim((string) $email);

    return $email !== '' ? $email : 'Membre';
};
?>
<section class="eff-page-head">
    <p class="eff-page-kicker">Gouvernance</p>
    <h1 class="eff-page-title">Demandes d’élévation</h1>
    <p class="eff-page-lead">
        Suivi des demandes d’évolution de grade, rôle ou droits transmises par les membres habilités.
        Traiter une demande la rend visible (statut + éventuelle note) pour le demandeur et la personne concernée sur leur page « Mes accès ».
    </p>
    <p style="margin-top:.75rem">
        <a href="<?= htmlspecialchars(effectifs_workspace_url('elevations') . ($showAll ? '' : '?all=1'), ENT_QUOTES, 'UTF-8') ?>" class="eff-card-cta">
            <?= $showAll ? 'Afficher uniquement les demandes ouvertes →' : 'Afficher tout l’historique →' ?>
        </a>
    </p>
</section>

<?php if ($requests === []): ?>
<div class="eff-panel" style="padding:1.5rem">
    <p class="eff-text-muted">Aucune demande <?= $showAll ? '' : 'ouverte ' ?>pour le moment.</p>
</div>
<?php else: ?>
<div class="eff-panel" style="overflow-x:auto">
    <table class="eff-table">
        <thead>
            <tr>
                <th>Concernant</th>
                <th>Type</th>
                <th>Demandée par</th>
                <th>Note du demandeur</th>
                <th>Date</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $r): ?>
                <?php
                $id = (int) ($r['id'] ?? 0);
                $status = (string) ($r['status'] ?? 'pending');
                $kind = (string) ($r['kind'] ?? 'general');
                $targetName = $nameOf($r['target_display_name'] ?? null, $r['target_email'] ?? null);
                $requesterName = $nameOf($r['requester_display_name'] ?? null, $r['requester_email'] ?? null);
                $note = trim((string) ($r['note'] ?? ''));
                $createdAt = (string) ($r['created_at'] ?? '');
                $createdFmt = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
                $isOpen = in_array($status, ['pending', 'in_review'], true);
                $actionUrl = url('back-office/ressources/effectifs/elevations/' . $id . '/statut');
                ?>
            <tr>
                <td><?= htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($kindLabels[$kind] ?? 'Situation RH', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($requesterName, ENT_QUOTES, 'UTF-8') ?></td>
                <td style="max-width:16rem;white-space:normal"><?= $note !== '' ? htmlspecialchars($note, ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td style="white-space:nowrap"><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="eff-badge <?= $statusBadgeClass($status) ?>"><?= htmlspecialchars($statusLabel($status), ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                    <?php if ($isOpen): ?>
                    <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="eff-elevation-form">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="text" name="resolution_note" maxlength="500" placeholder="Note (optionnel)" class="eff-elevation-note">
                        <div class="eff-elevation-actions">
                            <?php if ($status !== 'in_review'): ?>
                            <button type="submit" name="status" value="in_review" class="eff-btn-mini">En cours</button>
                            <?php endif; ?>
                            <button type="submit" name="status" value="approved" class="eff-btn-mini eff-btn-mini--ok">Approuver</button>
                            <button type="submit" name="status" value="rejected" class="eff-btn-mini eff-btn-mini--danger">Refuser</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <span class="eff-text-muted">Traitée</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<style>
.eff-elevation-form { display: flex; flex-direction: column; gap: .4rem; min-width: 12rem; }
.eff-elevation-note { font-size: .75rem; padding: .35rem .5rem; border: 1px solid #e2e8f0; border-radius: .4rem; }
.eff-elevation-actions { display: flex; flex-wrap: wrap; gap: .35rem; }
.eff-btn-mini {
    font-size: .625rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em;
    padding: .35rem .6rem; border-radius: .4rem; border: 1px solid #cbd5e1; background: #f8fafc; color: #334155; cursor: pointer;
}
.eff-btn-mini:hover { background: #f1f5f9; }
.eff-btn-mini--ok { border-color: #6ee7b7; background: #ecfdf5; color: #047857; }
.eff-btn-mini--ok:hover { background: #d1fae5; }
.eff-btn-mini--danger { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
.eff-btn-mini--danger:hover { background: #fee2e2; }
</style>
