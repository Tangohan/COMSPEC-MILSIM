<?php
declare(strict_types=1);

/**
 * Effectifs › Invitations : liens « compléter son profil » de la communauté (EffectifsWorkspaceController::invitations).
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$all = is_array($invRows ?? null) ? $invRows : [];
$filter = (string) ($invFilter ?? '');
$groups = [
    '' => ['Tous', static fn (array $r): bool => true],
    'en-cours' => ['En cours', static fn (array $r): bool => in_array($r['status'], ['active', 'opened', 'used'], true)],
    'termines' => ['Profil complété', static fn (array $r): bool => $r['status'] === 'completed'],
    'fermes' => ['Expirés ou révoqués', static fn (array $r): bool => in_array($r['status'], ['expired', 'revoked'], true)],
];
if (!isset($groups[$filter])) {
    $filter = '';
}
$invRows = array_values(array_filter($all, $groups[$filter][1]));
$invCanManage = !empty($invCanManage);
$invDestinations = is_array($invDestinations ?? null) ? $invDestinations : [];
$invReturnTo = 'invitations';
$invShowMember = true;
$invHighlight = (int) (\App\Core\Session::getFlash('profile_invite_new') ?? 0);
$flashOk = \App\Core\Session::getFlash('success');
$flashErr = \App\Core\Session::getFlash('error');
?>
<section class="eff-inv-page" data-eff-invites aria-labelledby="eff-inv-title">
    <header class="eff-inv-page__head">
        <div>
            <h2 id="eff-inv-title">Invitations</h2>
            <p>Chaque lien est personnel et expire seul. Pour en créer un, ouvrez la fiche du membre ou <a href="<?= $h(effectifs_workspace_url('nouveau')) ?>">ajoutez un membre</a>.</p>
        </div>
        <nav class="eff-inv-filter" aria-label="Filtrer les liens">
            <?php foreach ($groups as $key => [$label, $test]): ?>
                <?php $count = count(array_filter($all, $test)); ?>
                <a href="<?= $h(effectifs_workspace_url('invitations') . ($key !== '' ? '?etat=' . $key : '')) ?>" class="<?= $key === $filter ? 'is-on' : '' ?>"<?= $key === $filter ? ' aria-current="page"' : '' ?>><?= $h($label) ?> <b><?= $count ?></b></a>
            <?php endforeach; ?>
        </nav>
    </header>
    <?php if ($flashErr): ?><p class="eff-inv-flash is-bad" role="alert"><?= $h((string) $flashErr) ?></p><?php endif; ?>
    <?php if ($flashOk): ?><p class="eff-inv-flash" role="status"><?= $h((string) $flashOk) ?></p><?php endif; ?>
    <p class="eff-inv-status" data-inv-status role="status" aria-live="polite"></p>
    <?php if (empty($invReady)): ?>
        <p class="eff-inv-empty">Les liens d’invitation seront disponibles après la prochaine mise à jour de la base.</p>
    <?php elseif ($invRows === []): ?>
        <p class="eff-inv-empty"><?= $all === [] ? 'Aucun lien envoyé pour l’instant. Ajoutez un membre : son lien est prêt dès la création.' : 'Aucun lien dans cette catégorie.' ?></p>
    <?php else: ?>
        <?php require __DIR__ . '/partials/invite_rows.php'; ?>
    <?php endif; ?>
    <p class="eff-inv-foot">Chaque création, ouverture, envoi, prolongation et révocation est inscrite au journal d’audit de la communauté.</p>
</section>
<script src="<?= $h(asset_url('assets/js/eff-invites.js')) ?>" defer></script>
