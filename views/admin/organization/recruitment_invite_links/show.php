<?php
declare(strict_types=1);

/**
 * Fiche d'un lien d'invitation : adresse à copier, QR code, statistiques, réglages, usages, révocation.
 *
 * @var array<string, mixed> $inviteLink
 * @var array{click: int, signup: int, application: int, join: int, request: int} $inviteLinkStats
 * @var list<array<string, mixed>> $inviteLinkUses
 * @var list<array{0: string, 1: string}> $inviteLinkSummary
 */

use App\Services\Recruitment\InviteLinkService;

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$link = is_array($inviteLink ?? null) ? $inviteLink : [];
$stats = is_array($inviteLinkStats ?? null) ? $inviteLinkStats : \App\Repositories\InviteLinkRepository::emptyStats();
$uses = is_array($inviteLinkUses ?? null) ? $inviteLinkUses : [];
$summary = is_array($inviteLinkSummary ?? null) ? $inviteLinkSummary : [];
$baseUrl = url('back-office/recruitments/liens-invitation');
$id = (int) ($link['id'] ?? 0);
$publicUrl = InviteLinkService::publicUrl((string) ($link['public_code'] ?? ''));
$status = InviteLinkService::status($link);
$isActive = $status === InviteLinkService::STATUS_ACTIVE;
$fmt = static function (?string $s, string $format = 'd/m/Y H:i'): string {
    $s = trim((string) $s);
    $t = $s !== '' ? strtotime($s) : false;

    return $t ? date($format, $t) : '—';
};
$expires = trim((string) ($link['expires_at'] ?? ''));
$maxUses = $link['max_uses'] ?? null;
$usesCount = (int) ($link['uses_count'] ?? 0);
$tone = match ($status) {
    InviteLinkService::STATUS_ACTIVE => 'ath-tag--ok',
    InviteLinkService::STATUS_REVOKED => 'ath-tag--warn',
    default => 'ath-tag--neut',
};
?>
<div class="ilx-bo-ticket ath-rise<?= $isActive ? '' : ' is-off' ?>">
    <div class="ilx-bo-ticket__head">
        <div style="min-width:0;">
            <p class="ilx-bo-ticket__k">Lien d’invitation</p>
            <p class="ilx-bo-ticket__t"><?= $h((string) ($link['label'] ?? '')) ?></p>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <span class="ath-tag <?= $tone ?>"><?= $h(InviteLinkService::statusLabel($status)) ?></span>
            <span class="ilx-bo-ticket__code" translate="no"><?= $h((string) ($link['public_code'] ?? '')) ?></span>
        </div>
    </div>
    <div class="ilx-bo-ticket__perf"></div>
    <div class="ilx-bo-ticket__body">
        <div class="ilx-bo-ticket__main">
            <label class="ath-field" style="margin:0;">
                <span class="ath-field__label">Lien à partager</span>
                <input type="text" readonly value="<?= $h($publicUrl) ?>" class="ath-field__input ath-mono" data-invite-link-url onclick="this.select()" translate="no">
            </label>
            <div class="ath-item__actions" style="margin-top:10px;">
                <button type="button" class="ath-btn ath-btn--solid" data-invite-link-copy>Copier le lien</button>
                <span hidden data-invite-link-copied>Lien copié</span>
                <a href="<?= $h($baseUrl . '/' . $id . '/qr.png?telecharger=1') ?>" class="ath-btn">Télécharger le QR code</a>
                <a href="<?= $h($publicUrl) ?>" class="ath-btn" target="_blank" rel="noopener">Voir la page</a>
                <a href="<?= $h($baseUrl . '/' . $id . '/modifier') ?>" class="ath-btn">Modifier</a>
            </div>
        </div>
        <img src="<?= $h($baseUrl . '/' . $id . '/qr.png') ?>" alt="QR code du lien d’invitation" width="140" height="140" class="ilx-bo-ticket__qr" loading="lazy">
    </div>
    <div class="ilx-bo-ticket__foot">
        <span>Créé le <?= $h($fmt((string) ($link['created_at'] ?? ''), 'd/m/Y')) ?></span>
        <?php if ($expires !== ''): ?><span>Expire le <?= $h($fmt($expires)) ?></span><?php else: ?><span>Sans date d’expiration</span><?php endif; ?>
        <?php if ($maxUses !== null): ?><span><?= $usesCount ?> utilisations sur <?= (int) $maxUses ?></span><?php else: ?><span><?= $usesCount ?> utilisations, sans limite</span><?php endif; ?>
    </div>
</div>

<div class="ath-stat-grid ath-rise" style="margin-bottom:16px;">
    <div class="ath-stat"><p class="ath-stat__value"><?= (int) $stats['click'] ?></p><p class="ath-stat__label">Clics</p></div>
    <div class="ath-stat"><p class="ath-stat__value"><?= (int) $stats['signup'] ?></p><p class="ath-stat__label">Comptes créés</p></div>
    <div class="ath-stat"><p class="ath-stat__value"><?= (int) $stats['application'] ?></p><p class="ath-stat__label">Candidatures</p></div>
    <div class="ath-stat"><p class="ath-stat__value"><?= (int) $stats['join'] ?></p><p class="ath-stat__label">Adhésions</p></div>
    <div class="ath-stat"><p class="ath-stat__value"><?= (int) $stats['request'] ?></p><p class="ath-stat__label">Demandes d’adhésion</p></div>
    <div class="ath-stat"><p class="ath-stat__value"><?= $usesCount ?><?= $maxUses !== null ? ' / ' . (int) $maxUses : '' ?></p><p class="ath-stat__label">Utilisations</p></div>
</div>

<?php
$athTableTitle = 'Réglages du lien';
$athTableCount = count($summary);
$athTableCols = ['RÉGLAGE', 'VALEUR'];
$athTableRows = $summary;
$athTableFilters = [];
$athTableMinWidth = '640px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableRowActions = null;
$athTableFoot = null;
require base_path('views/partials/ath_table.php');

$eventLabel = static fn (string $e): string => match ($e) {
    'signup' => 'Compte créé',
    'application' => 'Candidature',
    'join' => 'Adhésion',
    'request' => 'Demande d’adhésion',
    default => 'Visite',
};
$athTableTitle = 'Derniers usages';
$athTableCount = count($uses);
$athTableCols = ['DATE|m', 'USAGE', 'COMPTE'];
$athTableRows = [];
$athTableRowHrefs = [];
$athTableRowActions = [];
foreach ($uses as $use) {
    $who = trim((string) ($use['display_name'] ?? '')) ?: trim((string) ($use['email'] ?? ''));
    $athTableRows[] = [$fmt((string) ($use['created_at'] ?? '')), $eventLabel((string) ($use['event'] ?? '')), $who !== '' ? $who : 'Invité sans compte'];
    $eid = (int) ($use['enlistment_id'] ?? 0);
    $href = $eid > 0 ? url('back-office/recruitments/' . $eid) : null;
    $athTableRowHrefs[] = $href;
    $athTableRowActions[] = $href !== null ? '<a href="' . $h($href) . '" class="ath-row-action">Ouvrir le dossier</a>' : '';
}
$athTableActionsLabel = 'DOSSIER';
$athTableMinWidth = '720px';
$athTableFoot = $uses === [] ? 'Ce lien n’a encore servi à aucune inscription, candidature ou adhésion.' : null;
require base_path('views/partials/ath_table.php');
?>

<?php if ($status === InviteLinkService::STATUS_REVOKED || $status === InviteLinkService::STATUS_EXPIRED): ?>
<div class="ath-note" style="margin-top:16px;">
    <p class="ath-note__title">Réactiver le lien</p>
    <p class="ath-note__text">La même adresse fonctionnera de nouveau, pour la durée choisie.</p>
    <form method="post" action="<?= $h($baseUrl . '/' . $id . '/reactiver') ?>" style="margin-top:11px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <?= \App\Core\Csrf::field() ?>
        <select name="expiry" class="ath-field__select" aria-label="Durée de validité">
            <?php foreach (array_keys(InviteLinkService::EXPIRY_PRESETS) as $preset): ?>
                <?php if ($preset === 'date') { continue; } ?>
            <option value="<?= $h($preset) ?>"<?= $preset === '7d' ? ' selected' : '' ?>><?= $h(InviteLinkService::presetLabel($preset)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="ath-btn ath-btn--solid">Réactiver</button>
    </form>
</div>
<?php endif; ?>

<div class="ath-warn" style="margin-top:16px;">
    <p class="ath-warn__title">Supprimer le lien</p>
    <p class="ath-warn__text">Le lien et ses statistiques disparaissent ; les candidatures et adhésions déjà faites sont conservées.</p>
    <div style="margin-top:11px;">
        <button type="button" class="ath-row-action ath-row-action--danger" data-invite-link-delete="<?= $h($baseUrl . '/' . $id . '/supprimer') ?>" data-invite-link-label="<?= $h((string) ($link['label'] ?? '')) ?>">Supprimer ce lien</button>
    </div>
</div>
<?php require __DIR__ . '/_delete_dialog.php'; ?>

<?php if ($status !== InviteLinkService::STATUS_REVOKED): ?>
<div class="ath-warn" style="margin-top:16px;">
    <p class="ath-warn__title">Révoquer le lien</p>
    <p class="ath-warn__text">
        Le lien cesse aussitôt de fonctionner ; les personnes qui l’ouvrent voient qu’il est désactivé.
        Les candidatures et adhésions déjà faites ne changent pas. Vous pourrez le réactiver plus tard.
    </p>
    <form method="post" action="<?= $h($baseUrl . '/' . $id . '/revoquer') ?>" style="margin-top:11px;"
          onsubmit="return confirm('Révoquer ce lien d’invitation ? Il cessera de fonctionner tout de suite.');">
        <?= \App\Core\Csrf::field() ?>
        <button type="submit" class="ath-row-action ath-row-action--danger">Révoquer ce lien</button>
    </form>
</div>
<?php endif; ?>

<script>
(function () {
    var btn = document.querySelector('[data-invite-link-copy]');
    var input = document.querySelector('[data-invite-link-url]');
    if (!btn || !input) { return; }
    var initial = btn.textContent;
    btn.addEventListener('click', function () {
        var done = function () {
            var copied = document.querySelector('[data-invite-link-copied]');
            btn.textContent = copied ? copied.textContent : initial;
            setTimeout(function () { btn.textContent = initial; }, 2000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(done, function () { input.select(); document.execCommand('copy'); done(); });
        } else {
            input.select();
            document.execCommand('copy');
            done();
        }
    });
})();
</script>
