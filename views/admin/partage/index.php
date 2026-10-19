<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Support\FileShareAccess;
use App\Support\FileShareStorage;
use App\Support\FileShareWorkspace;

/**
 * Fichiers partagés de la communauté (/partage et /back-office/partage, FileShareController).
 *
 * @var bool $fsBo
 * @var string $fsBase
 * @var bool $fsReady
 */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bo = !empty($fsBo);
$base = (string) ($fsBase ?? 'partage');
$csrf = Csrf::token();
$hidden = '<input type="hidden" name="_csrf_token" value="' . $h($csrf) . '"><input type="hidden" name="_bo" value="' . ($bo ? '1' : '0') . '">';
$link = static fn (int $folderId = 0, string $tab = ''): string => url($base . '?' . http_build_query(array_filter(['dossier' => $folderId > 0 ? $folderId : null, 'vue' => $tab !== '' ? $tab : null])));
$ico = static function (string $key): string {
    $paths = [
        'folder' => 'M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
        'file' => 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5',
        'image' => 'M4 5h16v14H4zM8 10a1.5 1.5 0 1 0 .01 0M20 16l-5-5-7 8',
        'pdf' => 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5M8 13h2.5a1.5 1.5 0 0 1 0 3H8v-3zM8 16v2',
        'archive' => 'M4 4h16v4H4zM5 8v12h14V8M10 12h4',
        'media' => 'M5 4h14v16H5zM10 9l5 3-5 3z',
        'lock' => 'M6 11h12v10H6zM8 11V7a4 4 0 0 1 8 0v4',
        'share' => 'M18 8a3 3 0 1 0-3-3M6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6M18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6M8.6 13.5l6.8 4M15.4 6.5l-6.8 4',
        'upload' => 'M12 16V4M7 9l5-5 5 5M4 20h16',
        'plus' => 'M12 5v14M5 12h14',
        'trash' => 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13',
        'log' => 'M9 3h6M4 6h16v15H4zM8 11h8M8 15h5',
        'download' => 'M12 4v12M7 11l5 5 5-5M4 20h16',
        'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 .01 0',
        'edit' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8M22 21v-2a4 4 0 0 0-3-3.9',
        'clock' => 'M12 7v5l3 2M12 3a9 9 0 1 0 .01 0',
        'back' => 'M15 18l-6-6 6-6',
    ];

    return '<svg class="fs-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($paths[$key] ?? '') . '"/></svg>';
};
$fileIcon = static function (string $name): string {
    $ext = FileShareStorage::extension($name);

    return match (true) {
        $ext === 'pdf' => 'pdf',
        in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'paa'], true) => 'image',
        in_array($ext, ['zip', '7z', 'rar', 'pbo'], true) => 'archive',
        in_array($ext, ['mp3', 'ogg', 'wav', 'mp4', 'webm'], true) => 'media',
        default => 'file',
    };
};
$date = static fn (?string $d): string => $d !== null && $d !== '' ? date('d/m/Y H:i', (int) strtotime($d)) : '';
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/back-office-partage.css')) ?>">
<div class="fs<?= $bo ? ' fs--bo' : '' ?>">
<div class="fs__frame">
<?php if (empty($fsReady)): ?>
    <header class="fs__hero">
        <p class="fs__kicker">Communauté · Fichiers partagés</p>
        <h1 class="fs__title">Fichiers partagés</h1>
        <p class="fs__notice">Le partage de fichiers n’est pas encore installé sur ce serveur. Un administrateur plateforme doit lancer <strong>run-migrations.php</strong>.</p>
    </header>
<?php else: ?>
<?php
/** @var FileShareWorkspace $ws */
$ws = $fsWs;
$userId = (int) $fsUserId;
$folderId = (int) $fsFolderId;
$folder = is_array($fsFolder ?? null) ? $fsFolder : null;
$level = (int) $fsLevel;
$tab = (string) $fsTab;
$stats = is_array($fsStats ?? null) ? $fsStats : [];
$entries = is_array($fsEntries ?? null) ? $fsEntries : [];
$subfolders = is_array($fsSubfolders ?? null) ? $fsSubfolders : [];
$files = is_array($fsFiles ?? null) ? $fsFiles : [];
$crumbs = is_array($fsBreadcrumb ?? null) ? $fsBreadcrumb : [];
$labels = is_array($fsUserLabels ?? null) ? $fsUserLabels : [];
$members = is_array($fsMembers ?? null) ? $fsMembers : [];
$roles = is_array($fsRoles ?? null) ? $fsRoles : [];
$units = is_array($fsUnits ?? null) ? $fsUnits : [];
$targets = is_array($fsTargetLabels ?? null) ? $fsTargetLabels : ['user' => [], 'role' => [], 'unit' => []];
$usage = (int) $fsUsage;
$quota = max(1, (int) $fsQuota);
$usagePct = min(100, (int) round(100 * $usage / $quota));
$canRoot = !empty($fsCanCreateRoot);
$who = static fn (int $id): string => $id === $userId ? 'vous' : ($labels[$id] ?? 'un ancien membre');
$grantTarget = static function (array $g) use ($targets, $h): string {
    $type = (string) $g['target_type'];
    $id = (int) $g['target_id'];
    $name = $type === 'all' ? 'Toute la communauté' : ($targets[$type][$id] ?? ('#' . $id));
    $kind = $type === 'all' ? '' : '<span class="fs-grant__kind">' . $h(FileShareAccess::TARGET_LABELS[$type] ?? '') . '</span>';

    return $kind . '<strong' . ($type === 'all' ? '' : ' translate="no"') . '>' . $h($name) . '</strong>';
};
$levelBadge = static fn (string $key): string => '<span class="fs-level fs-level--' . $key . '">' . FileShareAccess::LEVEL_LABELS[$key] . '</span>';
$folderShareState = static function (int $id) use ($ws): array {
    $direct = count($ws->grantsOnFolder($id));
    $inherited = count($ws->inheritedGrants($id));

    return ['direct' => $direct, 'inherited' => $inherited];
};
$myLevelKey = FileShareAccess::levelKey($level);
$isOwner = $folder !== null && (int) $folder['owner_user_id'] === $userId;

/** Formulaire de partage (dossier ou fichier). */
$shareForm = static function (string $itemType, int $itemId, string $idPrefix) use ($h, $hidden, $members, $roles, $units): string {
    ob_start(); ?>
    <form method="post" action="<?= $h(url('partage/acces')) ?>" class="fs-share-form" data-fs-share-form>
        <?= $hidden ?>
        <input type="hidden" name="item_type" value="<?= $h($itemType) ?>">
        <input type="hidden" name="item_id" value="<?= $itemId ?>">
        <fieldset class="fs-share-form__who">
            <legend>Avec qui</legend>
            <div class="fs-seg" role="radiogroup">
                <label><input type="radio" name="target_type" value="user" checked data-fs-target> Membre</label>
                <?php if ($roles !== []): ?><label><input type="radio" name="target_type" value="role" data-fs-target> Rôle</label><?php endif; ?>
                <?php if ($units !== []): ?><label><input type="radio" name="target_type" value="unit" data-fs-target> Unité</label><?php endif; ?>
                <label><input type="radio" name="target_type" value="all" data-fs-target> Toute la communauté</label>
            </div>
            <label class="fs-field" data-fs-pick="user">
                <span>Membre</span>
                <input list="<?= $h($idPrefix) ?>-members" data-fs-member-search placeholder="Rechercher : indicatif ou nom" autocomplete="off" hidden>
                <datalist id="<?= $h($idPrefix) ?>-members">
                    <?php foreach ($members as $m): ?><option value="<?= $h($m['label']) ?>" data-id="<?= (int) $m['id'] ?>"></option><?php endforeach; ?>
                </datalist>
                <select name="target_user" data-fs-member-select>
                    <option value="">Choisir un membre…</option>
                    <?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= $h($m['label']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php if ($roles !== []): ?>
            <label class="fs-field" data-fs-pick="role" hidden>
                <span>Rôle</span>
                <select name="target_role">
                    <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= $h($r['label']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <?php if ($units !== []): ?>
            <label class="fs-field" data-fs-pick="unit" hidden>
                <span>Unité <small>(et ses sous-unités)</small></span>
                <select name="target_unit">
                    <?php foreach ($units as $u): ?><option value="<?= (int) $u['id'] ?>"><?= $h($u['label']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </fieldset>
        <div class="fs-share-form__row">
            <label class="fs-field">
                <span>Niveau d’accès</span>
                <select name="level">
                    <?php foreach (FileShareAccess::LEVEL_LABELS as $key => $label): ?>
                        <?php if ($itemType === 'file' && $key === 'upload') { continue; } ?>
                        <option value="<?= $h($key) ?>"><?= $h($label) ?> · <?= $h(FileShareAccess::LEVEL_HINTS[$key]) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="fs-field fs-field--date">
                <span>Jusqu’au <small>(facultatif)</small></span>
                <input type="date" name="expires" min="<?= $h(date('Y-m-d', time() + 86400)) ?>">
            </label>
        </div>
        <button type="submit" class="fs-btn fs-btn--primary">Partager</button>
    </form>
    <?php
    return (string) ob_get_clean();
};

/** Liste des partages d'un élément (avec retrait). */
$lockIco = $ico('lock');
$grantList = static function (array $grants, bool $canRevoke, array $inherited = []) use ($h, $hidden, $ws, $grantTarget, $levelBadge, $lockIco): string {
    ob_start();
    if ($grants === [] && $inherited === []): ?>
        <p class="fs-grants__empty"><?= $lockIco ?>Privé : personne d’autre n’y a accès.</p>
    <?php else: ?>
        <ul class="fs-grants">
            <?php foreach ($grants as $g): ?>
                <?php $expired = $ws->isExpired($g); ?>
                <li class="fs-grant<?= $expired ? ' is-expired' : '' ?>">
                    <span class="fs-grant__who"><?= $grantTarget($g) ?></span>
                    <?= $levelBadge((string) $g['level']) ?>
                    <?php if (!empty($g['expires_at'])): ?><span class="fs-grant__exp"><?= $expired ? 'Expiré le ' : 'Jusqu’au ' ?><?= $h(date('d/m/Y', (int) strtotime((string) $g['expires_at']))) ?></span><?php endif; ?>
                    <?php if ($canRevoke): ?>
                        <form method="post" action="<?= $h(url('partage/acces/' . (int) $g['id'] . '/retirer')) ?>" class="fs-grant__revoke">
                            <?= $hidden ?>
                            <button type="submit" class="fs-btn fs-btn--ghost fs-btn--sm" aria-label="Retirer l’accès">Retirer</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php foreach ($inherited as $g): ?>
                <?php if ($ws->isExpired($g)) { continue; } ?>
                <li class="fs-grant is-inherited">
                    <span class="fs-grant__who"><?= $grantTarget($g) ?></span>
                    <?= $levelBadge((string) $g['level']) ?>
                    <span class="fs-grant__exp">hérité de « <?= $h((string) $g['from_folder']) ?> »</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif;

    return (string) ob_get_clean();
};

/** Arborescence : points d'entrée, dossiers du fil d'Ariane dépliés. */
$openIds = array_map(static fn (array $f): int => (int) $f['id'], $crumbs);
$tree = static function (array $nodes, int $depth) use (&$tree, $ws, $openIds, $folderId, $link, $h, $ico, $folderShareState): string {
    if ($nodes === []) {
        return '';
    }
    $html = '<ul class="fs-tree__list"' . ($depth > 0 ? ' role="group"' : '') . '>';
    foreach ($nodes as $n) {
        $id = (int) $n['id'];
        $open = in_array($id, $openIds, true);
        $state = $folderShareState($id);
        $shared = $state['direct'] + $state['inherited'] > 0;
        $html .= '<li><a class="fs-tree__item' . ($id === $folderId ? ' is-current' : '') . '" href="' . $h($link($id)) . '"' . ($id === $folderId ? ' aria-current="page"' : '') . ' style="--depth:' . $depth . '">'
            . $ico('folder') . '<span class="fs-tree__name" translate="no">' . $h($n['name']) . '</span>'
            . ($shared ? '<span class="fs-tree__flag" title="Partagé">' . $ico('share') . '</span>' : '<span class="fs-tree__flag is-private" title="Privé">' . $ico('lock') . '</span>')
            . '</a>';
        if ($open) {
            $html .= $tree($ws->childFolders($id), $depth + 1);
        }
        $html .= '</li>';
    }

    return $html . '</ul>';
};
$visibleFolderCount = count($entries);
?>
    <header class="fs__hero">
        <div class="fs__hero-main">
            <p class="fs__kicker"><?= $bo ? 'Communauté · Gestion' : 'Communauté' ?> · Fichiers partagés</p>
            <h1 class="fs__title">Fichiers partagés</h1>
            <p class="fs__lead">Dossiers et fichiers de la communauté, rangés dans un espace privé. <strong>Rien n’est visible sans partage</strong> : chacun ne voit que ce qu’il a créé ou ce qu’on lui a partagé, à un membre, un rôle, une unité ou toute la communauté.</p>
        </div>
        <div class="fs__usage" aria-label="Espace utilisé par la communauté">
            <span class="fs__usage-label">Espace de la communauté</span>
            <strong><?= $h(FileShareAccess::humanSize($usage)) ?></strong> <span class="fs__muted">sur <?= $h(FileShareAccess::humanSize($quota)) ?></span>
            <span class="fs__usage-bar"><span style="width: <?= max($usage > 0 ? 1 : 0, $usagePct) ?>%"></span></span>
        </div>
    </header>

    <div class="fs__layout">
        <aside class="fs__side" aria-label="Arborescence des dossiers">
            <nav class="fs-tree">
                <a class="fs-tree__home<?= $folderId === 0 && $tab === '' ? ' is-current' : '' ?>" href="<?= $h($link()) ?>"><?= $ico('users') ?>Partagés avec moi</a>
                <?= $tree($entries, 0) ?>
                <?php if ($entries === []): ?><p class="fs__muted fs-tree__empty">Aucun dossier pour l’instant.</p><?php endif; ?>
                <a class="fs-tree__home fs-tree__trash<?= $tab === 'corbeille' ? ' is-current' : '' ?>" href="<?= $h($link(0, 'corbeille')) ?>"><?= $ico('trash') ?>Corbeille</a>
            </nav>
            <div class="fs-rules">
                <p class="fs-rules__title">Niveaux d’accès</p>
                <dl>
                    <?php foreach (FileShareAccess::LEVEL_LABELS as $key => $label): ?>
                        <dt><?= $levelBadge($key) ?></dt><dd><?= $h(FileShareAccess::LEVEL_HINTS[$key]) ?></dd>
                    <?php endforeach; ?>
                </dl>
                <p class="fs__muted">Un partage sur un dossier vaut pour tout ce qu’il contient. Le créateur d’un dossier le gère toujours.</p>
            </div>
        </aside>

        <main class="fs__main">
<?php if ($tab === 'corbeille'): ?>
            <?php $trashFolders = $fsTrashFolders ?? []; $trashFiles = $fsTrashFiles ?? []; ?>
            <section class="fs-panel">
                <div class="fs-panel__head">
                    <h2 class="fs-panel__title"><?= $ico('trash') ?>Corbeille</h2>
                    <p class="fs__muted">Éléments retirés des dossiers que vous gérez (et vos propres dépôts). Personne n’y accède tant qu’ils ne sont pas restaurés.</p>
                </div>
                <?php if ($trashFolders === [] && $trashFiles === []): ?>
                    <div class="fs-empty"><p class="fs-empty__title">La corbeille est vide.</p></div>
                <?php else: ?>
                    <ul class="fs-rows">
                        <?php foreach ($trashFolders as $f): ?>
                            <li class="fs-row">
                                <span class="fs-row__icon fs-row__icon--folder"><?= $ico('folder') ?></span>
                                <span class="fs-row__main"><strong translate="no"><?= $h($f['name']) ?></strong><span class="fs__muted">Dossier · retiré le <?= $h($date((string) $f['deleted_at'])) ?> par <?= $h($who((int) $f['deleted_by'])) ?></span></span>
                                <span class="fs-row__actions">
                                    <form method="post" action="<?= $h(url('partage/corbeille/dossier/' . (int) $f['id'] . '/restaurer')) ?>"><?= $hidden ?><button class="fs-btn fs-btn--sm">Restaurer</button></form>
                                    <form method="post" action="<?= $h(url('partage/corbeille/dossier/' . (int) $f['id'] . '/supprimer')) ?>" data-confirm="Supprimer définitivement ce dossier et tout son contenu ? Cette action ne peut pas être annulée."><?= $hidden ?><button class="fs-btn fs-btn--sm fs-btn--danger">Supprimer définitivement</button></form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                        <?php foreach ($trashFiles as $f): ?>
                            <?php $parent = $ws->folder((int) $f['folder_id']); ?>
                            <li class="fs-row">
                                <span class="fs-row__icon"><?= $ico($fileIcon((string) $f['name'])) ?></span>
                                <span class="fs-row__main"><strong translate="no"><?= $h($f['name']) ?></strong><span class="fs__muted">Dans « <?= $h($parent['name'] ?? '') ?> » · retiré le <?= $h($date((string) $f['deleted_at'])) ?> par <?= $h($who((int) $f['deleted_by'])) ?></span></span>
                                <span class="fs-row__actions">
                                    <form method="post" action="<?= $h(url('partage/corbeille/fichier/' . (int) $f['id'] . '/restaurer')) ?>"><?= $hidden ?><button class="fs-btn fs-btn--sm">Restaurer</button></form>
                                    <?php if ($ws->folderLevel((int) $f['folder_id']) >= FileShareAccess::MANAGE): ?>
                                        <form method="post" action="<?= $h(url('partage/corbeille/fichier/' . (int) $f['id'] . '/supprimer')) ?>" data-confirm="Supprimer définitivement ce fichier ? Cette action ne peut pas être annulée."><?= $hidden ?><button class="fs-btn fs-btn--sm fs-btn--danger">Supprimer définitivement</button></form>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
<?php elseif ($folder === null): ?>
            <section class="fs-panel">
                <div class="fs-panel__head fs-panel__head--split">
                    <div>
                        <h2 class="fs-panel__title"><?= $ico('users') ?>Partagés avec moi</h2>
                        <p class="fs__muted"><?= $visibleFolderCount ?> dossier(s) ouvert(s) pour vous<?= $files !== [] ? ', ' . count($files) . ' fichier(s) partagé(s) seul(s)' : '' ?>.</p>
                    </div>
                    <?php if ($canRoot): ?>
                        <button type="button" class="fs-btn fs-btn--primary" data-fs-open="fs-dlg-root"><?= $ico('plus') ?>Nouveau dossier</button>
                    <?php endif; ?>
                </div>
                <?php if ($entries === [] && $files === []): ?>
                    <div class="fs-empty">
                        <span class="fs-empty__icon"><?= $ico('lock') ?></span>
                        <p class="fs-empty__title">Rien n’a encore été partagé avec vous.</p>
                        <p><?= $canRoot ? 'Créez un premier dossier, déposez-y vos fichiers, puis choisissez qui peut l’ouvrir.' : 'Quand un responsable vous partage un dossier ou un fichier, il apparaît ici.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="fs-cards">
                        <?php foreach ($entries as $f): ?>
                            <?php
                            $fid = (int) $f['id'];
                            $st = $stats[$fid] ?? ['files' => 0, 'bytes' => 0];
                            $lk = FileShareAccess::levelKey($ws->folderLevel($fid));
                            $share = $folderShareState($fid);
                            $mine = (int) $f['owner_user_id'] === $userId;
                            ?>
                            <a class="fs-card" href="<?= $h($link($fid)) ?>">
                                <span class="fs-card__icon"><?= $ico('folder') ?></span>
                                <span class="fs-card__name" translate="no"><?= $h($f['name']) ?></span>
                                <?php if (!empty($f['description'])): ?><span class="fs-card__desc" translate="no"><?= $h($f['description']) ?></span><?php endif; ?>
                                <span class="fs-card__meta"><?= (int) $st['files'] ?> fichier(s) · <?= $h(FileShareAccess::humanSize((int) $st['bytes'])) ?></span>
                                <span class="fs-card__foot">
                                    <?= $mine ? '<span class="fs-chip">À vous</span>' : '<span class="fs-chip">De ' . $h($who((int) $f['owner_user_id'])) . '</span>' ?>
                                    <?= $levelBadge($lk !== '' ? $lk : 'view') ?>
                                    <?php if ($mine): ?><span class="fs-chip<?= $share['direct'] === 0 ? ' fs-chip--private' : '' ?>"><?= $share['direct'] === 0 ? 'Privé' : 'Partagé · ' . $share['direct'] ?></span><?php endif; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($files !== []): ?>
                        <h3 class="fs-panel__sub">Fichiers partagés avec vous</h3>
                        <?php require __DIR__ . '/partials/file_rows.php'; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <?php if (!empty($fsOrphans)): ?>
                <section class="fs-panel fs-panel--warn">
                    <div class="fs-panel__head">
                        <h2 class="fs-panel__title"><?= $ico('users') ?>Dossiers sans propriétaire</h2>
                        <p class="fs__muted">Leur créateur a quitté la communauté. Leur contenu reste fermé ; vous pouvez en reprendre la propriété (la reprise est notée dans leur journal).</p>
                    </div>
                    <ul class="fs-rows">
                        <?php foreach ($fsOrphans as $f): ?>
                            <li class="fs-row">
                                <span class="fs-row__icon fs-row__icon--folder"><?= $ico('folder') ?></span>
                                <span class="fs-row__main"><strong translate="no"><?= $h($f['name']) ?></strong><span class="fs__muted">Créé le <?= $h($date((string) $f['created_at'])) ?></span></span>
                                <span class="fs-row__actions">
                                    <form method="post" action="<?= $h(url('partage/dossiers/' . (int) $f['id'] . '/reprendre')) ?>" data-confirm="Reprendre la propriété de ce dossier ?"><?= $hidden ?><button class="fs-btn fs-btn--sm">Reprendre le dossier</button></form>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
<?php else: ?>
            <nav class="fs-crumbs" aria-label="Fil d’Ariane">
                <a href="<?= $h($link()) ?>">Partagés avec moi</a>
                <?php foreach ($crumbs as $c): ?>
                    <span aria-hidden="true">/</span>
                    <?php if ((int) $c['id'] === $folderId): ?><span aria-current="page" translate="no"><?= $h($c['name']) ?></span><?php else: ?><a href="<?= $h($link((int) $c['id'])) ?>" translate="no"><?= $h($c['name']) ?></a><?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <section class="fs-panel">
                <div class="fs-folder-head">
                    <span class="fs-folder-head__icon"><?= $ico('folder') ?></span>
                    <div class="fs-folder-head__text">
                        <h2 class="fs-folder-head__title" translate="no"><?= $h($folder['name']) ?></h2>
                        <?php if (!empty($folder['description'])): ?><p class="fs-folder-head__desc" translate="no"><?= $h($folder['description']) ?></p><?php endif; ?>
                        <p class="fs__muted">Créé par <?= $h($who((int) $folder['owner_user_id'])) ?> le <?= $h($date((string) $folder['created_at'])) ?> · votre accès : <?= $levelBadge($myLevelKey) ?><?= $isOwner ? ' <span class="fs-chip">propriétaire</span>' : '' ?></p>
                    </div>
                </div>
                <div class="fs-toolbar" role="toolbar" aria-label="Actions sur le dossier">
                    <?php if ($level >= FileShareAccess::UPLOAD): ?>
                        <button type="button" class="fs-btn fs-btn--primary" data-fs-open="fs-dlg-upload"><?= $ico('upload') ?>Ajouter des fichiers</button>
                        <button type="button" class="fs-btn" data-fs-open="fs-dlg-sub"><?= $ico('plus') ?>Sous-dossier</button>
                    <?php endif; ?>
                    <?php if ($level >= FileShareAccess::MANAGE): ?>
                        <a class="fs-btn" href="#partage"><?= $ico('share') ?>Partager</a>
                        <button type="button" class="fs-btn" data-fs-open="fs-dlg-edit"><?= $ico('edit') ?>Modifier</button>
                        <a class="fs-btn<?= $tab === 'journal' ? ' is-active' : '' ?>" href="<?= $h($link($folderId, $tab === 'journal' ? '' : 'journal')) ?>"><?= $ico('log') ?>Journal</a>
                        <form method="post" action="<?= $h(url('partage/dossiers/' . $folderId . '/supprimer')) ?>" data-confirm="Mettre ce dossier et tout son contenu à la corbeille ? Plus personne n’y aura accès tant qu’il n’est pas restauré."><?= $hidden ?><button type="submit" class="fs-btn fs-btn--danger-ghost"><?= $ico('trash') ?>Supprimer</button></form>
                    <?php endif; ?>
                </div>

                <?php if ($level >= FileShareAccess::UPLOAD): ?>
                    <form method="post" enctype="multipart/form-data" action="<?= $h(url('partage/dossiers/' . $folderId . '/fichiers')) ?>" class="fs-drop" data-fs-drop>
                        <?= $hidden ?>
                        <input type="file" name="files[]" multiple id="fs-drop-input" class="fs-drop__input" accept="<?= $h(str_replace(' ', '', (string) $fsAccept)) ?>" data-fs-drop-input>
                        <label for="fs-drop-input" class="fs-drop__label"><?= $ico('upload') ?><span><strong>Déposez vos fichiers ici</strong> ou cliquez pour les choisir</span><small>100 Mo maximum par fichier · documents, images, tableurs, archives, missions Arma (.sqm, .pbo)</small></label>
                        <noscript><button type="submit" class="fs-btn fs-btn--primary">Envoyer</button></noscript>
                    </form>
                <?php endif; ?>

                <?php if ($subfolders === [] && $files === []): ?>
                    <div class="fs-empty">
                        <p class="fs-empty__title">Ce dossier est vide.</p>
                        <p><?= $level >= FileShareAccess::UPLOAD ? 'Glissez des fichiers dans la zone ci-dessus.' : 'Rien n’y a encore été déposé.' ?></p>
                    </div>
                <?php else: ?>
                    <?php if ($subfolders !== []): ?>
                        <ul class="fs-rows">
                            <?php foreach ($subfolders as $f): ?>
                                <?php $fid = (int) $f['id']; $st = $stats[$fid] ?? ['files' => 0, 'bytes' => 0]; $share = $folderShareState($fid); ?>
                                <li class="fs-row">
                                    <span class="fs-row__icon fs-row__icon--folder"><?= $ico('folder') ?></span>
                                    <a class="fs-row__main" href="<?= $h($link($fid)) ?>"><strong translate="no"><?= $h($f['name']) ?></strong><span class="fs__muted"><?= (int) $st['files'] ?> fichier(s) · <?= $h(FileShareAccess::humanSize((int) $st['bytes'])) ?> · créé par <?= $h($who((int) $f['owner_user_id'])) ?></span></a>
                                    <span class="fs-row__meta"><?php if ($share['direct'] > 0): ?><span class="fs-chip"><?= $ico('share') ?>+<?= $share['direct'] ?> accès</span><?php endif; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($files !== []): ?>
                        <?php require __DIR__ . '/partials/file_rows.php'; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <?php if ($tab === 'journal' && $level >= FileShareAccess::MANAGE): ?>
                <?php
                $actions = ['create' => 'a créé', 'upload' => 'a déposé', 'download' => 'a téléchargé', 'preview' => 'a ouvert', 'rename' => 'a renommé', 'delete' => 'a mis à la corbeille', 'restore' => 'a restauré', 'purge' => 'a supprimé définitivement', 'share' => 'a partagé avec', 'unshare' => 'a retiré l’accès à', 'takeover' => 'a repris la propriété'];
                ?>
                <section class="fs-panel" id="journal">
                    <div class="fs-panel__head">
                        <h2 class="fs-panel__title"><?= $ico('log') ?>Journal des accès</h2>
                        <p class="fs__muted">Qui a ouvert, déposé, partagé ou retiré quoi, dans ce dossier et ses sous-dossiers (80 derniers événements).</p>
                    </div>
                    <?php if (empty($fsEvents)): ?>
                        <div class="fs-empty"><p class="fs-empty__title">Aucun événement.</p></div>
                    <?php else: ?>
                        <ol class="fs-log">
                            <?php foreach ($fsEvents as $e): ?>
                                <li class="fs-log__item fs-log__item--<?= $h($e['action']) ?>">
                                    <time datetime="<?= $h($e['created_at']) ?>"><?= $h($date((string) $e['created_at'])) ?></time>
                                    <span><strong><?= $h(ucfirst($who((int) $e['user_id']))) ?></strong> <?= $h($actions[(string) $e['action']] ?? (string) $e['action']) ?>
                                    <?php $object = (string) ($e['file_name'] ?? '') !== '' ? (string) $e['file_name'] : (string) ($e['detail'] ?? ''); ?>
                                    <?php if (in_array((string) $e['action'], ['share', 'unshare', 'rename', 'takeover'], true)): ?>
                                        <em><?= $h((string) $e['detail']) ?></em><?php if ((string) ($e['file_name'] ?? '') !== ''): ?> <span class="fs__muted">(<?= $h($e['file_name']) ?>)</span><?php endif; ?>
                                    <?php else: ?>
                                        <em><?= $h($object !== '' ? $object : (string) ($e['folder_name'] ?? '')) ?></em>
                                    <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($level >= FileShareAccess::MANAGE): ?>
                <section class="fs-panel" id="partage">
                    <div class="fs-panel__head">
                        <h2 class="fs-panel__title"><?= $ico('share') ?>Qui a accès à « <?= $h($folder['name']) ?> »</h2>
                        <p class="fs__muted">Le partage vaut pour tout le contenu du dossier, sous-dossiers compris. <?= $isOwner ? 'Vous en êtes le propriétaire.' : 'Propriétaire : ' . $h($who((int) $folder['owner_user_id'])) . '.' ?></p>
                    </div>
                    <div class="fs-share">
                        <div class="fs-share__list"><?= $grantList($ws->grantsOnFolder($folderId), true, $ws->inheritedGrants($folderId)) ?></div>
                        <div class="fs-share__add">
                            <h3 class="fs-panel__sub">Ajouter un accès</h3>
                            <?= $shareForm('folder', $folderId, 'fs-folder') ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
<?php endif; ?>
        </main>
    </div>

    <?php if ($canRoot && $folder === null && $tab === ''): ?>
        <dialog class="fs-dlg" id="fs-dlg-root" aria-labelledby="fs-dlg-root-t">
            <form method="post" action="<?= $h(url('partage/dossiers')) ?>" class="fs-dlg__body">
                <?= $hidden ?>
                <h2 id="fs-dlg-root-t" class="fs-dlg__title">Nouveau dossier</h2>
                <p class="fs__muted">Il restera privé tant que vous ne l’avez pas partagé.</p>
                <label class="fs-field"><span>Nom</span><input name="name" required maxlength="120" placeholder="Ex. Ordres d’opération, Cartes, Comptes rendus"></label>
                <label class="fs-field"><span>Description <small>(facultatif)</small></span><textarea name="description" rows="2" maxlength="500"></textarea></label>
                <div class="fs-dlg__actions"><button type="button" class="fs-btn" data-fs-close>Annuler</button><button type="submit" class="fs-btn fs-btn--primary">Créer</button></div>
            </form>
        </dialog>
    <?php endif; ?>
    <?php if ($folder !== null && $level >= FileShareAccess::UPLOAD): ?>
        <dialog class="fs-dlg" id="fs-dlg-sub" aria-labelledby="fs-dlg-sub-t">
            <form method="post" action="<?= $h(url('partage/dossiers')) ?>" class="fs-dlg__body">
                <?= $hidden ?>
                <input type="hidden" name="parent_id" value="<?= $folderId ?>">
                <h2 id="fs-dlg-sub-t" class="fs-dlg__title">Nouveau sous-dossier</h2>
                <p class="fs__muted">Dans « <?= $h($folder['name']) ?> » : il hérite de ses partages.</p>
                <label class="fs-field"><span>Nom</span><input name="name" required maxlength="120"></label>
                <label class="fs-field"><span>Description <small>(facultatif)</small></span><textarea name="description" rows="2" maxlength="500"></textarea></label>
                <div class="fs-dlg__actions"><button type="button" class="fs-btn" data-fs-close>Annuler</button><button type="submit" class="fs-btn fs-btn--primary">Créer</button></div>
            </form>
        </dialog>
        <dialog class="fs-dlg" id="fs-dlg-upload" aria-labelledby="fs-dlg-upload-t">
            <form method="post" enctype="multipart/form-data" action="<?= $h(url('partage/dossiers/' . $folderId . '/fichiers')) ?>" class="fs-dlg__body">
                <?= $hidden ?>
                <h2 id="fs-dlg-upload-t" class="fs-dlg__title">Ajouter des fichiers</h2>
                <p class="fs__muted">Jusqu’à 50 fichiers à la fois, 100 Mo maximum chacun.</p>
                <label class="fs-field"><span>Fichiers</span><input type="file" name="files[]" multiple required accept="<?= $h(str_replace(' ', '', (string) $fsAccept)) ?>"></label>
                <div class="fs-dlg__actions"><button type="button" class="fs-btn" data-fs-close>Annuler</button><button type="submit" class="fs-btn fs-btn--primary">Envoyer</button></div>
            </form>
        </dialog>
    <?php endif; ?>
    <?php if ($folder !== null && $level >= FileShareAccess::MANAGE): ?>
        <dialog class="fs-dlg" id="fs-dlg-edit" aria-labelledby="fs-dlg-edit-t">
            <form method="post" action="<?= $h(url('partage/dossiers/' . $folderId . '/modifier')) ?>" class="fs-dlg__body">
                <?= $hidden ?>
                <h2 id="fs-dlg-edit-t" class="fs-dlg__title">Modifier ce dossier</h2>
                <label class="fs-field"><span>Nom</span><input name="name" required maxlength="120" value="<?= $h($folder['name']) ?>"></label>
                <label class="fs-field"><span>Description</span><textarea name="description" rows="3" maxlength="500"><?= $h($folder['description'] ?? '') ?></textarea></label>
                <div class="fs-dlg__actions"><button type="button" class="fs-btn" data-fs-close>Annuler</button><button type="submit" class="fs-btn fs-btn--primary">Enregistrer</button></div>
            </form>
        </dialog>
    <?php endif; ?>
<?php endif; ?>
</div>
</div>
<script defer src="<?= $h(asset_url('assets/js/back-office-partage.js')) ?>"></script>
