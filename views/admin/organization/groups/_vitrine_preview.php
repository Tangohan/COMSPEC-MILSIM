<?php
declare(strict_types=1);

/**
 * Aperçu de la carte publique d’un groupe (fiche + formulaire). Les attributs data-grp-pv-* sont mis à jour en direct par le formulaire.
 *
 * @var array<string, mixed> $vitrineGroup
 */

$vg = is_array($vitrineGroup ?? null) ? $vitrineGroup : [];
$vh = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$vAccent = trim((string) ($vg['public_accent_color'] ?? ''));
$vAccent = preg_match('/^#[0-9a-f]{6}$/i', $vAccent) ? $vAccent : '#1e4f80';
$vBlurb = trim((string) ($vg['public_blurb'] ?? ''));
$vTags = $vg['public_tags'] ?? [];
if (is_string($vTags)) {
    $dec = json_decode($vTags, true);
    $vTags = is_array($dec) ? $dec : preg_split('/\R/u', $vTags);
}
$vTags = array_values(array_filter(array_map(static fn ($t): string => trim((string) $t), (array) $vTags), static fn (string $t): bool => $t !== ''));
$vSlots = $vg['public_open_slots'] ?? null;
$vSlotsLabel = $vSlots === null || $vSlots === '' ? '' : ((int) $vSlots === -1 || $vSlots === 'ouvert' ? 'Recrutement ouvert' : (int) $vSlots . ' place' . ((int) $vSlots > 1 ? 's' : '') . ' ouverte' . ((int) $vSlots > 1 ? 's' : ''));
$vCapacity = (int) ($vg['public_capacity'] ?? 0);
$vVisible = !array_key_exists('show_on_public_page', $vg) || (int) ($vg['show_on_public_page'] ?? 0) === 1;
?>
<div class="bo-grp-vitrine" style="--grp-accent: <?= $vh($vAccent) ?>" data-grp-pv>
    <div class="bo-grp-vitrine__band" aria-hidden="true"></div>
    <div class="bo-grp-vitrine__body">
        <div>
            <h3 class="bo-grp-vitrine__title" data-grp-pv-name><?= $vh((string) (($vg['name'] ?? '') !== '' ? $vg['name'] : 'Nom du groupe')) ?></h3>
            <span class="bo-grp-vitrine__code" data-grp-pv-code><?= $vh((string) ($vg['code'] ?? '')) ?></span>
        </div>
        <p class="bo-grp-vitrine__blurb<?= $vBlurb === '' ? ' bo-grp-vitrine__blurb--empty' : '' ?>" data-grp-pv-blurb><?= $vBlurb !== '' ? $vh($vBlurb) : 'Pas encore de présentation : écrivez deux phrases sur le rôle du groupe.' ?></p>
        <div class="bo-grp-vitrine__tags" data-grp-pv-tags>
            <?php foreach ($vTags as $t): ?><span class="bo-grp-vitrine__tag"><?= $vh($t) ?></span><?php endforeach; ?>
        </div>
        <div class="bo-grp-vitrine__facts">
            <span data-grp-pv-capacity><?= $vCapacity > 0 ? 'Effectif max <b>' . $vCapacity . '</b>' : '' ?></span>
            <span data-grp-pv-slots><?= $vSlotsLabel !== '' ? '<b>' . $vh($vSlotsLabel) . '</b>' : '' ?></span>
        </div>
    </div>
    <div class="bo-grp-vitrine__hidden" data-grp-pv-hidden<?= $vVisible ? ' hidden' : '' ?>>Masqué : cette carte n’apparaît pas sur la page publique.</div>
</div>
