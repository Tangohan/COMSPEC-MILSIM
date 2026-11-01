<?php
declare(strict_types=1);

/**
 * Fiche du membre › Invitations : liens « compléter son profil » de ce membre et création d'un nouveau lien.
 *
 * @var array{rows: list<array<string, mixed>>, ready: bool, can_manage: bool, destinations: list<array{id:string, label:string, preferred:bool}>, needs_link: bool} $memberInvitePanel
 * @var int $id identifiant du membre
 */
$panel = is_array($memberInvitePanel ?? null) ? $memberInvitePanel : ['rows' => [], 'ready' => false, 'can_manage' => false, 'destinations' => [], 'needs_link' => false];
$hInv = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$invRows = $panel['rows'];
$invCanManage = $panel['can_manage'];
$invDestinations = $panel['destinations'];
$invReturnTo = '';
$invShowMember = false;
$invHighlight = (int) (\App\Core\Session::getFlash('profile_invite_new') ?? 0);
$hasLive = false;
foreach ($invRows as $r) {
    $hasLive = $hasLive || !empty($r['usable']);
}
?>
<article class="eff-fm-card eff-fm-card--wide eff-inv-card" id="invitations" data-eff-invites>
    <div class="eff-fm-card__head">
        <h3>Lien pour compléter son profil</h3>
        <?php if ($invRows !== []): ?><span class="eff-fm-count"><?= count($invRows) ?></span><?php endif; ?>
    </div>
    <?php if (!$panel['ready']): ?>
        <p class="eff-fm-lead">Les liens d’invitation seront disponibles après la prochaine mise à jour de la base.</p>
    <?php else: ?>
        <p class="eff-fm-lead">
            <?= $panel['needs_link']
                ? 'Un lien personnel, valable 24 ou 48 h, pour créer son mot de passe puis relier Discord et Steam, ajouter son portrait et activer la double vérification.'
                : 'Ce membre a déjà un compte : le lien l’amène, après connexion, sur les étapes de son profil qui restent à faire.' ?>
        </p>
        <p class="eff-inv-status" data-inv-status role="status" aria-live="polite"></p>
        <?php if ($invRows !== []): ?>
            <?php require __DIR__ . '/invite_rows.php'; ?>
        <?php endif; ?>
        <?php if ($invCanManage): ?>
        <form method="post" action="<?= $hInv(effectifs_workspace_url('membres/' . (int) $id . '/invitations')) ?>" class="eff-inv-new" data-inv-form data-inv-reload>
            <input type="hidden" name="_csrf_token" value="<?= $hInv(\App\Core\Csrf::token()) ?>">
            <fieldset class="eff-inv-new__seg">
                <legend><?= $hasLive ? 'Remplacer par un nouveau lien' : 'Nouveau lien' ?></legend>
                <label><input type="radio" name="duration_hours" value="24"><span>24 h</span></label>
                <label><input type="radio" name="duration_hours" value="48" checked><span>48 h</span></label>
            </fieldset>
            <label class="eff-inv-new__check"><input type="checkbox" name="single_use" value="1"><span>Usage unique</span></label>
            <?php if ($invDestinations !== []): ?>
            <label class="eff-inv-new__check"><input type="checkbox" name="send_discord" value="1"><span>Publier sur Discord</span></label>
            <input type="hidden" name="destination" value="<?= $hInv($invDestinations[0]['id']) ?>">
            <?php endif; ?>
            <button type="submit" class="eff-inv-btn eff-inv-btn--solid"><?= $hasLive ? 'Créer un nouveau lien' : 'Créer le lien' ?></button>
            <?php if ($hasLive): ?><small>L’ancien lien cessera de fonctionner.</small><?php endif; ?>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</article>
<script src="<?= $hInv(asset_url('assets/js/eff-invites.js')) ?>" defer></script>
