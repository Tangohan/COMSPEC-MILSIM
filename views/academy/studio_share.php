<?php
/**
 * Fenêtre « Partager » du Studio, façon Google Docs : personnes invitées (lecteur / éditeur), accès par lien
 * (désactivé, communauté, toute personne), copie et renouvellement du lien. Chaque bouton est un formulaire serveur.
 */
$sharing = $sharing ?? null;
$link = $link ?? static fn (string $path = ''): string => url('academy' . ($path !== '' ? '/' . $path : ''));
$shareLink = $sharing['link'] ?? null;
$shareUrl = $shareLink !== null && is_string($shareLink['token'] ?? null) ? url('academy/partage/' . $shareLink['token']) : null;
$members = $sharing['members'] ?? [];
$shared = $sharing['shares'] ?? [];
$sharedIds = array_column($shared, 'user_id');
$hidden = static fn () => $csrf . '<input type="hidden" name="course" value="' . $h($key) . '">';
?>
<dialog class="ast-dialog ast-share" id="ast-share" aria-labelledby="ast-share-title"<?= !empty($openShare) ? ' data-autoshow' : '' ?>>
<div class="mh"><div><p class="k"><?= $h($t('share')) ?></p><h2 id="ast-share-title"><?= $h(sprintf($t('share_heading'), $title)) ?></h2></div><button class="mx" type="button" data-close-dialog aria-label="<?= $h($t('close')) ?>">×</button></div>
<div class="mb">
<?php if (!empty($notice) && (str_starts_with($notice, 'share_') || $notice === 'studio_lifecycle_pending')): ?><p class="ast-notice ast-notice--<?= in_array($notice, ['share_invalid_member', 'share_invalid_link', 'studio_lifecycle_pending'], true) ? 'warn' : 'ok' ?>" role="status"><?= $h($t($notice)) ?></p><?php endif ?>
<?php if ($sharing === null): ?><p class="h"><?= $h($t($sharingReady ? 'share_unavailable' : 'studio_lifecycle_pending')) ?></p>
<?php else: ?>
<section class="sh-sec" aria-labelledby="sh-people"><h3 id="sh-people"><?= $h($t('share_people')) ?></h3>
<form method="post" action="<?= $h($link('action/studio_share_add')) ?>" class="sh-add"><?= $hidden() ?>
<label class="sh-find"><svg class="i"><use href="#ai-search"/></svg><input type="search" data-member-filter placeholder="<?= $h($t('share_search')) ?>" aria-label="<?= $h($t('share_search')) ?>"></label>
<div class="sh-row"><label class="grow"><span class="sr-only"><?= $h($t('share_member')) ?></span><select class="in" name="share_user" required data-member-select><option value=""><?= $h($t('share_choose_member')) ?></option><?php foreach ($members as $memberId => $memberName): if (in_array($memberId, $sharedIds, true)) continue; ?><option value="<?= (int) $memberId ?>"><?= $h($memberName) ?></option><?php endforeach ?></select></label>
<label><span class="sr-only"><?= $h($t('share_role')) ?></span><select class="in" name="share_role"><option value="viewer"><?= $h($t('role_viewer')) ?></option><option value="editor"><?= $h($t('role_editor')) ?></option></select></label>
<button class="btn dark"><?= $h($t('share_invite')) ?></button></div>
<p class="h"><?= $h($t('share_roles_help')) ?></p>
</form>
<ul class="sh-list" aria-label="<?= $h($t('share_who')) ?>">
<li><span class="av" aria-hidden="true">★</span><span class="grow"><b><?= $h($t('share_staff')) ?></b><small><?= $h($t('share_staff_help')) ?></small></span><span class="role"><?= $h($t('role_owner')) ?></span></li>
<?php foreach ($shared as $share): $name = $members[$share['user_id']] ?? '#' . $share['user_id']; ?>
<li><span class="av" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($name, 0, 1))) ?></span><span class="grow"><b><?= $h($name) ?></b><small><?= $h($t('share_since')) ?> <?= $h(date('d/m/Y', (int) strtotime($share['created_at']))) ?></small></span>
<form method="post" action="<?= $h($link('action/studio_share_add')) ?>" class="sh-inline"><?= $hidden() ?><input type="hidden" name="share_user" value="<?= (int) $share['user_id'] ?>"><label><span class="sr-only"><?= $h($t('share_role')) ?> · <?= $h($name) ?></span><select class="in sm" name="share_role" data-autosubmit><option value="viewer"<?= $share['role'] === 'viewer' ? ' selected' : '' ?>><?= $h($t('role_viewer')) ?></option><option value="editor"<?= $share['role'] === 'editor' ? ' selected' : '' ?>><?= $h($t('role_editor')) ?></option></select></label><noscript><button class="btn"><?= $h($t('update')) ?></button></noscript></form>
<form method="post" action="<?= $h($link('action/studio_share_remove')) ?>" class="sh-inline"><?= $hidden() ?><input type="hidden" name="share_user" value="<?= (int) $share['user_id'] ?>"><button class="ib dg" title="<?= $h($t('share_remove')) ?>"><svg class="i"><use href="#ai-trash"/></svg><span class="sr-only"><?= $h($t('share_remove')) ?> · <?= $h($name) ?></span></button></form></li>
<?php endforeach ?>
</ul></section>
<section class="sh-sec" aria-labelledby="sh-link"><h3 id="sh-link"><?= $h($t('share_link_title')) ?></h3>
<form method="post" action="<?= $h($link('action/studio_link_save')) ?>" class="sh-linkform" data-link-form><?= $hidden() ?>
<div class="sh-access"><span class="av link" aria-hidden="true"><svg class="i"><use href="#ai-link"/></svg></span>
<div class="grow"><label><span class="sr-only"><?= $h($t('share_link_title')) ?></span><select class="in" name="link_access" data-link-access><?php foreach (['off', 'community', 'public'] as $access): ?><option value="<?= $access ?>" data-help="<?= $h($t('link_' . $access . '_help')) ?>"<?= ($shareLink['access'] ?? 'off') === $access ? ' selected' : '' ?>><?= $h($t('link_' . $access)) ?></option><?php endforeach ?></select></label>
<small data-link-help><?= $h($t('link_' . ($shareLink['access'] ?? 'off') . '_help')) ?></small></div>
<label><span class="sr-only"><?= $h($t('share_role')) ?></span><select class="in" name="link_role" data-link-role><option value="viewer"<?= ($shareLink['role'] ?? 'viewer') === 'viewer' ? ' selected' : '' ?>><?= $h($t('role_viewer')) ?></option><option value="editor"<?= ($shareLink['role'] ?? '') === 'editor' ? ' selected' : '' ?><?= ($shareLink['access'] ?? '') === 'public' ? ' disabled' : '' ?>><?= $h($t('role_editor')) ?></option></select></label></div>
<div class="sh-row"><label class="grow"><span class="lab"><?= $h($t('link_expiry')) ?></span><select class="in" name="link_expiry"><option value="never"><?= $h($t('expiry_never')) ?></option><option value="1"><?= $h($t('expiry_1')) ?></option><option value="7"><?= $h($t('expiry_7')) ?></option><option value="30"><?= $h($t('expiry_30')) ?></option></select></label>
<button class="btn dark"><?= $h($t('link_save')) ?></button></div>
<?php if (!empty($shareLink['expires_at'])): ?><p class="h"><?= $h(sprintf($t('link_expires_on'), date('d/m/Y H:i', (int) strtotime($shareLink['expires_at'])))) ?></p><?php endif ?>
</form>
<?php if ($shareUrl !== null): ?>
<div class="sh-copy"><input class="in" readonly value="<?= $h($shareUrl) ?>" aria-label="<?= $h($t('share_link_title')) ?>" data-share-url><button class="btn" type="button" data-copy-link data-copied="<?= $h($t('link_copied')) ?>"><?= $h($t('link_copy')) ?></button></div>
<form method="post" action="<?= $h($link('action/studio_link_regenerate')) ?>" class="sh-regen"><?= $hidden() ?><input type="hidden" name="link_access" value="<?= $h($shareLink['access']) ?>"><input type="hidden" name="link_role" value="<?= $h($shareLink['role']) ?>"><input type="hidden" name="link_expiry" value="keep"><button class="ib" data-confirm="<?= $h($t('link_regenerate_confirm')) ?>"><svg class="i"><use href="#ai-refresh"/></svg><?= $h($t('link_regenerate')) ?></button><span class="h"><?= $h($t('link_regenerate_help')) ?></span></form>
<?php endif ?>
</section>
<?php endif ?>
</div>
<div class="mf"><span class="h"><?= $h($t('share_audit_note')) ?></span><button class="btn dark" type="button" data-close-dialog><?= $h($t('done')) ?></button></div>
</dialog>
