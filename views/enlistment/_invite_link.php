<?php
/**
 * Candidature ouverte depuis un lien d'invitation (/i/<code>) : champ caché qui rattache le dossier au lien et
 * rappel discret pour le candidat.
 *
 * @var array{code: string, label: string, unit: string}|null $inviteLinkBanner
 */
$inviteLinkBanner = is_array($inviteLinkBanner ?? null) ? $inviteLinkBanner : null;
if ($inviteLinkBanner === null) {
    return;
}
?>
<input type="hidden" name="invite_link" value="<?= htmlspecialchars($inviteLinkBanner['code'], ENT_QUOTES, 'UTF-8') ?>">
<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950 ce-banner ce-banner--invite" role="status">
    <p class="font-bold ce-banner__kicker">Vous avez été invité à candidater</p>
    <?php if ($inviteLinkBanner['unit'] !== ''): ?>
    <p class="mt-1 text-emerald-900/90 ce-banner__text">Votre candidature sera transmise à l’équipe avec votre invitation (unité visée : <?= htmlspecialchars($inviteLinkBanner['unit'], ENT_QUOTES, 'UTF-8') ?>).</p>
    <?php else: ?>
    <p class="mt-1 text-emerald-900/90 ce-banner__text">Votre candidature sera transmise à l’équipe avec votre invitation.</p>
    <?php endif; ?>
</div>
