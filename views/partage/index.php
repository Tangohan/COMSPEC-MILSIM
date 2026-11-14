<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Services\Jnet\JnetUnitFiles;
use App\Support\FileShareAccess;
use App\Support\FileShareStorage;
use App\Support\FileShareWorkspace;

/**
 * Fichiers partagés de la communauté (/partage et /back-office/partage, FileShareController), dans la coque JNET
 * (views/partage/_page.php). Tout est un formulaire ou un lien : la page marche sans JavaScript ; partage.js ajoute la
 * recherche, le tri, la bascule album / liste et le dépôt en un geste. Aucune fenêtre : les panneaux s'ouvrent en place
 * et les confirmations se font dans la page.
 *
 * @var bool $fsBo
 * @var string $fsBase
 * @var bool $fsReady
 */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bo = !empty($fsBo);
$base = (string) ($fsBase ?? 'partage');
$hidden = Csrf::field() . '<input type="hidden" name="_bo" value="' . ($bo ? '1' : '0') . '">';
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
        'search' => 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14M21 21l-4.3-4.3',
        'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'close' => 'M6 6l12 12M18 6 6 18',
        'gear' => 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6M12 3v3M12 18v3M3 12h3M18 12h3',
    ];

    return '<svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . ($paths[$key] ?? '') . '"/></svg>';
};
$ext = static fn (string $name): string => FileShareStorage::extension($name);
/** Famille d'un fichier : sert à l'icône et au filtre par type. */
$kindOf = static function (string $name) use ($ext): string {
    $e = $ext($name);

    return match (true) {
        JnetUnitFiles::isImage($name) => 'image',
        $e === 'pdf' => 'pdf',
        in_array($e, ['doc', 'docx', 'odt', 'rtf', 'txt', 'md', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp'], true) => 'doc',
        in_array($e, ['zip', '7z', 'rar', 'pbo', 'sqm'], true) => 'archive',
        in_array($e, ['mp3', 'ogg', 'wav', 'mp4', 'webm'], true) => 'media',
        default => 'other',
    };
};
$kindLabels = ['image' => 'Images', 'pdf' => 'PDF', 'doc' => 'Documents', 'archive' => 'Archives et missions', 'media' => 'Audio et vidéo', 'other' => 'Autres'];
$kindIcon = ['image' => 'image', 'pdf' => 'pdf', 'doc' => 'file', 'archive' => 'archive', 'media' => 'media', 'other' => 'file'];
$date = static fn (?string $d): string => $d !== null && $d !== '' ? date('d/m/Y H:i', (int) strtotime($d)) : '';
$day = static fn (?string $d): string => $d !== null && $d !== '' ? date('d/m/Y', (int) strtotime($d)) : '';
$plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n > 1 ? $many : $one);
?>
<?php if (empty($fsReady)): ?>
    <header class="fp-head">
        <h1 class="ja-disp ja-disp--md">Fichiers partagés</h1>
        <p class="ja-lead">Le partage de fichiers n’est pas encore installé sur ce serveur. Un administrateur plateforme doit lancer <strong>run-migrations.php</strong>.</p>
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
$maxLabel = FileShareAccess::humanSize((int) ($fsMaxBytes ?? FileShareStorage::MAX_BYTES));
$canRoot = !empty($fsCanCreateRoot);
$who = static fn (int $id): string => $id === $userId ? 'vous' : ($labels[$id] ?? 'un ancien membre');
$levelKey = static fn (int $lvl): string => FileShareAccess::levelKey($lvl) !== '' ? FileShareAccess::levelKey($lvl) : 'view';
$levelPill = static fn (string $key): string => '<span class="ja-pill fp-level fp-level--' . $h($key) . '">' . $h(FileShareAccess::LEVEL_LABELS[$key] ?? '') . '</span>';
$grantName = static function (array $g) use ($targets): string {
    $type = (string) $g['target_type'];
    $id = (int) $g['target_id'];

    return $type === 'all' ? 'Toute la communauté' : (string) ($targets[$type][$id] ?? ('#' . $id));
};
$isOwner = $folder !== null && (int) $folder['owner_user_id'] === $userId;
$myLevelKey = $levelKey($level);

/** Liste des partages d'un élément (avec retrait). */
$grantList = static function (array $grants, bool $canRevoke, array $inherited = []) use ($h, $hidden, $ws, $grantName, $levelPill, $ico): string {
    ob_start();
    if ($grants === [] && $inherited === []): ?>
        <p class="fp-grants__empty"><?= $ico('lock') ?>Privé : personne d’autre n’y a accès.</p>
    <?php else: ?>
        <ul class="fp-grants">
            <?php foreach ($grants as $g): ?>
                <?php $expired = $ws->isExpired($g); ?>
                <li class="fp-grant<?= $expired ? ' is-expired' : '' ?>">
                    <span class="fp-grant__who"><small><?= $h((string) ($g['target_type'] === 'all' ? '' : (FileShareAccess::TARGET_LABELS[(string) $g['target_type']] ?? ''))) ?></small><strong<?= $g['target_type'] === 'all' ? '' : ' translate="no"' ?>><?= $h($grantName($g)) ?></strong></span>
                    <?= $levelPill((string) $g['level']) ?>
                    <?php if (!empty($g['expires_at'])): ?><span class="fp-grant__exp"><?= $expired ? 'Expiré le ' : 'Jusqu’au ' ?><?= $h(date('d/m/Y', (int) strtotime((string) $g['expires_at']))) ?></span><?php endif; ?>
                    <?php if ($canRevoke): ?>
                        <form method="post" action="<?= $h(url('partage/acces/' . (int) $g['id'] . '/retirer')) ?>" class="fp-grant__revoke">
                            <?= $hidden ?>
                            <button type="submit" class="ja-b2 sm" aria-label="Retirer l’accès de <?= $h($grantName($g)) ?>">Retirer</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php foreach ($inherited as $g): ?>
                <?php if ($ws->isExpired($g)) { continue; } ?>
                <li class="fp-grant is-inherited">
                    <span class="fp-grant__who"><small><?= $h((string) ($g['target_type'] === 'all' ? '' : (FileShareAccess::TARGET_LABELS[(string) $g['target_type']] ?? ''))) ?></small><strong<?= $g['target_type'] === 'all' ? '' : ' translate="no"' ?>><?= $h($grantName($g)) ?></strong></span>
                    <?= $levelPill((string) $g['level']) ?>
                    <span class="fp-grant__exp">hérité de « <?= $h((string) $g['from_folder']) ?> »</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif;

    return (string) ob_get_clean();
};

/** Formulaire de partage (dossier ou fichier) : même POST, mêmes champs qu'avant. */
$shareForm = static function (string $itemType, int $itemId, string $idPrefix) use ($h, $hidden, $members, $roles, $units): string {
    ob_start(); ?>
    <form method="post" action="<?= $h(url('partage/acces')) ?>" class="fp-form fp-share-form" data-fp-share-form>
        <?= $hidden ?>
        <input type="hidden" name="item_type" value="<?= $h($itemType) ?>">
        <input type="hidden" name="item_id" value="<?= $itemId ?>">
        <fieldset class="fp-fieldset">
            <legend>Avec qui</legend>
            <div class="fp-seg" role="radiogroup" aria-label="Type de destinataire">
                <label><input type="radio" name="target_type" value="user" checked data-fp-target><span>Membre</span></label>
                <?php if ($roles !== []): ?><label><input type="radio" name="target_type" value="role" data-fp-target><span>Rôle</span></label><?php endif; ?>
                <?php if ($units !== []): ?><label><input type="radio" name="target_type" value="unit" data-fp-target><span>Unité</span></label><?php endif; ?>
                <label><input type="radio" name="target_type" value="all" data-fp-target><span>Toute la communauté</span></label>
            </div>
            <label class="fp-field" data-fp-pick="user">
                <span>Membre</span>
                <input list="<?= $h($idPrefix) ?>-members" data-fp-member-search placeholder="Rechercher : indicatif ou nom" autocomplete="off" hidden>
                <datalist id="<?= $h($idPrefix) ?>-members">
                    <?php foreach ($members as $m): ?><option value="<?= $h($m['label']) ?>" data-id="<?= (int) $m['id'] ?>"></option><?php endforeach; ?>
                </datalist>
                <select name="target_user" data-fp-member-select>
                    <option value="">Choisir un membre…</option>
                    <?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= $h($m['label']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php if ($roles !== []): ?>
            <label class="fp-field" data-fp-pick="role">
                <span>Rôle</span>
                <select name="target_role">
                    <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= $h($r['label']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <?php if ($units !== []): ?>
            <label class="fp-field" data-fp-pick="unit">
                <span>Unité <small>(et ses sous-unités)</small></span>
                <select name="target_unit">
                    <?php foreach ($units as $u): ?><option value="<?= (int) $u['id'] ?>"><?= $h($u['label']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </fieldset>
        <div class="fp-form__row">
            <label class="fp-field">
                <span>Niveau d’accès</span>
                <select name="level">
                    <?php foreach (FileShareAccess::LEVEL_LABELS as $key => $label): ?>
                        <?php if ($itemType === 'file' && $key === 'upload') { continue; } ?>
                        <option value="<?= $h($key) ?>"><?= $h($label) ?> · <?= $h(FileShareAccess::LEVEL_HINTS[$key]) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="fp-field fp-field--date">
                <span>Jusqu’au <small>(facultatif)</small></span>
                <input type="date" name="expires" min="<?= $h(date('Y-m-d', time() + 86400)) ?>">
            </label>
        </div>
        <button type="submit" class="ja-b1 sm">Partager</button>
    </form>
    <?php
    return (string) ob_get_clean();
};

/** Confirmation en place : le bouton rouge n'apparaît qu'après avoir ouvert le panneau, jamais de fenêtre. */
$confirmPost = static function (string $action, string $summary, string $message, string $button, string $id) use ($h, $hidden, $ico): string {
    ob_start(); ?>
    <input type="checkbox" class="fp-tg ja-sr" id="<?= $h($id) ?>" aria-label="<?= $h($summary) ?>">
    <label class="ja-b2 sm fp-danger" for="<?= $h($id) ?>"><?= $ico('trash') ?><span><?= $h($summary) ?></span></label>
    <form method="post" action="<?= $h($action) ?>" class="fp-confirm">
        <?= $hidden ?>
        <p><?= $h($message) ?></p>
        <div class="fp-confirm__acts">
            <button type="submit" class="ja-b1 sm fp-danger-fill"><?= $h($button) ?></button>
            <label class="ja-b2 sm" for="<?= $h($id) ?>">Annuler</label>
        </div>
    </form>
    <?php
    return (string) ob_get_clean();
};

$visibleFolderCount = count($entries);
$fileCount = count($files);
$kindsPresent = [];
foreach ($files as $f) {
    $kindsPresent[$kindOf((string) $f['name'])] = true;
}
$images = [];
$others = [];
foreach ($files as $f) {
    if ($kindOf((string) $f['name']) === 'image') {
        $images[] = $f;
    } else {
        $others[] = $f;
    }
}
$bytesHere = array_sum(array_map(static fn (array $f): int => (int) $f['size_bytes'], $files));
?>
    <header class="fp-head">
        <div class="fp-head__main">
            <h1 class="ja-disp ja-disp--md">Fichiers partagés</h1>
            <p class="ja-lead">Les dossiers et fichiers de la communauté. Rien n’est visible sans partage : chacun ne voit que ce qu’il a créé ou ce qu’on lui a ouvert, à un membre, un rôle, une unité ou toute la communauté.</p>
        </div>
        <div class="fp-usage" role="group" aria-label="Espace utilisé par la communauté">
            <b><?= $h(FileShareAccess::humanSize($usage)) ?></b>
            <span>utilisés sur <?= $h(FileShareAccess::humanSize($quota)) ?></span>
            <i class="fp-usage__bar"><i style="width: <?= max($usage > 0 ? 1 : 0, $usagePct) ?>%"></i></i>
        </div>
    </header>

    <nav class="ja-tabs ja-tabs--page fp-views" aria-label="Vues">
        <a class="ja-tab<?= $tab === '' ? ' is-on' : '' ?>" href="<?= $h($link()) ?>"<?= $tab === '' ? ' aria-current="page"' : '' ?>><span class="ja-mono">01</span>Dossiers</a>
        <a class="ja-tab<?= $tab === 'corbeille' ? ' is-on' : '' ?>" href="<?= $h($link(0, 'corbeille')) ?>"<?= $tab === 'corbeille' ? ' aria-current="page"' : '' ?>><span class="ja-mono">02</span>Corbeille</a>
    </nav>

<?php if ($tab === 'corbeille'): ?>
    <?php $trashFolders = $fsTrashFolders ?? []; $trashFiles = $fsTrashFiles ?? []; ?>
    <section class="fp-sec" aria-labelledby="fp-t-trash">
        <div class="ja-sh">
            <div>
                <h2 class="ja-disp ja-disp--sm" id="fp-t-trash">Corbeille</h2>
                <p class="fp-note">Éléments retirés des dossiers que vous gérez, et vos propres dépôts. Personne n’y accède tant qu’ils ne sont pas restaurés.</p>
            </div>
        </div>
        <?php if ($trashFolders === [] && $trashFiles === []): ?>
            <div class="ja-empty"><p><strong>La corbeille est vide.</strong></p><p>Un dossier ou un fichier mis à la corbeille apparaît ici, restaurable.</p></div>
        <?php else: ?>
            <ul class="fp-lines">
                <?php foreach ($trashFolders as $f): ?>
                    <?php $fid = (int) $f['id']; ?>
                    <li class="fp-line">
                        <span class="fp-line__ic"><?= $ico('folder') ?></span>
                        <span class="fp-line__main"><strong translate="no"><?= $h($f['name']) ?></strong><small>Dossier · retiré le <?= $h($date((string) $f['deleted_at'])) ?> par <?= $h($who((int) $f['deleted_by'])) ?></small></span>
                        <span class="fp-line__acts">
                            <form method="post" action="<?= $h(url('partage/corbeille/dossier/' . $fid . '/restaurer')) ?>"><?= $hidden ?><button class="ja-b2 sm">Restaurer</button></form>
                            <?= $confirmPost(url('partage/corbeille/dossier/' . $fid . '/supprimer'), 'Supprimer définitivement', 'Supprimer définitivement ce dossier et tout son contenu ? Cette action ne peut pas être annulée.', 'Supprimer définitivement', 'fp-pg-d' . $fid) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($trashFiles as $f): ?>
                    <?php $fid = (int) $f['id']; $parent = $ws->folder((int) $f['folder_id']); ?>
                    <li class="fp-line">
                        <span class="fp-line__ic"><?= $ico($kindIcon[$kindOf((string) $f['name'])]) ?></span>
                        <span class="fp-line__main"><strong translate="no"><?= $h($f['name']) ?></strong><small>Dans « <?= $h($parent['name'] ?? '') ?> » · retiré le <?= $h($date((string) $f['deleted_at'])) ?> par <?= $h($who((int) $f['deleted_by'])) ?></small></span>
                        <span class="fp-line__acts">
                            <form method="post" action="<?= $h(url('partage/corbeille/fichier/' . $fid . '/restaurer')) ?>"><?= $hidden ?><button class="ja-b2 sm">Restaurer</button></form>
                            <?php if ($ws->folderLevel((int) $f['folder_id']) >= FileShareAccess::MANAGE): ?>
                                <?= $confirmPost(url('partage/corbeille/fichier/' . $fid . '/supprimer'), 'Supprimer définitivement', 'Supprimer définitivement ce fichier ? Cette action ne peut pas être annulée.', 'Supprimer définitivement', 'fp-pg-f' . $fid) ?>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

<?php elseif ($folder === null): ?>
    <div class="ja-stats fp-stats" style="--n: 3">
        <div class="ja-stat"><b><?= $visibleFolderCount ?></b><span><?= $visibleFolderCount > 1 ? 'dossiers ouverts pour vous' : 'dossier ouvert pour vous' ?></span></div>
        <div class="ja-stat"><b><?= $fileCount ?></b><span><?= $fileCount > 1 ? 'fichiers partagés seuls' : 'fichier partagé seul' ?></span></div>
        <div class="ja-stat"><b><?= $usagePct ?><small> %</small></b><span>de l’espace de la communauté</span></div>
    </div>

    <section class="fp-sec" aria-labelledby="fp-t-root">
        <div class="ja-sh">
            <div>
                <h2 class="ja-disp ja-disp--sm" id="fp-t-root">Partagés avec moi</h2>
            </div>
            <?php if ($canRoot): ?>
                <label class="ja-b1 sm" for="fp-t-root-new"><?= $ico('plus') ?>Nouveau dossier</label>
            <?php endif; ?>
        </div>

        <?php if ($canRoot): ?>
            <input type="checkbox" class="fp-tg ja-sr" id="fp-t-root-new">
            <section class="fp-panel" aria-label="Nouveau dossier">
                <form method="post" action="<?= $h(url('partage/dossiers')) ?>" class="fp-form">
                    <?= $hidden ?>
                    <p class="fp-note">Il restera privé tant que vous ne l’avez pas partagé.</p>
                    <label class="fp-field"><span>Nom</span><input name="name" required maxlength="120" placeholder="Ex. Ordres d’opération, Cartes, Comptes rendus"></label>
                    <label class="fp-field"><span>Description <small>(facultatif)</small></span><textarea name="description" rows="2" maxlength="500"></textarea></label>
                    <div class="fp-form__acts"><button type="submit" class="ja-b1 sm">Créer</button><label class="ja-b2 sm" for="fp-t-root-new">Annuler</label></div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($entries === [] && $files === []): ?>
            <div class="ja-empty">
                <p><strong>Rien n’a encore été partagé avec vous.</strong></p>
                <p><?= $canRoot ? 'Créez un premier dossier, déposez-y vos fichiers, puis choisissez qui peut l’ouvrir.' : 'Quand un responsable vous partage un dossier ou un fichier, il apparaît ici.' ?></p>
            </div>
        <?php else: ?>
            <div class="fp-bar" data-fp-bar hidden>
                <label class="ja-fi"><?= $ico('search') ?><input type="search" data-fp-filter placeholder="Chercher un dossier ou un fichier" aria-label="Chercher un dossier ou un fichier" autocomplete="off"></label>
                <span class="fp-count" data-fp-count role="status" aria-live="polite"></span>
            </div>

            <?php if ($entries !== []): ?>
                <div class="fp-cards" data-fp-list>
                    <?php foreach ($entries as $f): ?>
                        <?php
                        $fid = (int) $f['id'];
                        $st = $stats[$fid] ?? ['files' => 0, 'bytes' => 0];
                        $lk = $levelKey($ws->folderLevel($fid));
                        $directGrants = count($ws->grantsOnFolder($fid));
                        $mine = (int) $f['owner_user_id'] === $userId;
                        ?>
                        <a class="fp-card" href="<?= $h($link($fid)) ?>" data-fp-item data-name="<?= $h(mb_strtolower((string) $f['name'] . ' ' . (string) ($f['description'] ?? ''))) ?>">
                            <span class="fp-card__ic"><?= $ico('folder') ?></span>
                            <span class="fp-card__name" translate="no"><?= $h($f['name']) ?></span>
                            <?php if (!empty($f['description'])): ?><span class="fp-card__desc" translate="no"><?= $h($f['description']) ?></span><?php endif; ?>
                            <span class="fp-card__meta"><?= $h($plural((int) $st['files'], 'fichier', 'fichiers')) ?> · <?= $h(FileShareAccess::humanSize((int) $st['bytes'])) ?> · <?= $mine ? 'à vous' : 'de ' . $h($who((int) $f['owner_user_id'])) ?></span>
                            <span class="fp-card__foot">
                                <?= $levelPill($lk) ?>
                                <?php if ($mine): ?><span class="fp-chip"><?= $ico($directGrants === 0 ? 'lock' : 'share') ?><?= $directGrants === 0 ? 'Privé' : 'Partagé · ' . $directGrants ?></span><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($files !== []): ?>
                <h3 class="fp-sub">Fichiers partagés avec vous</h3>
                <?php $albumMode = false; ?>
                <div class="fp-files" data-fp-list>
                    <?php require __DIR__ . '/_files.php'; ?>
                </div>
            <?php endif; ?>
            <div class="ja-empty" data-fp-none hidden><p><strong>Aucun résultat.</strong></p><p>Aucun dossier ni fichier ne correspond à cette recherche.</p></div>
        <?php endif; ?>
    </section>

    <?php if (!empty($fsOrphans)): ?>
        <section class="fp-sec" aria-labelledby="fp-t-orph">
            <div class="ja-sh">
                <div>
                    <h2 class="ja-disp ja-disp--sm" id="fp-t-orph">Dossiers sans propriétaire</h2>
                    <p class="fp-note">Leur créateur a quitté la communauté. Leur contenu reste fermé ; vous pouvez en reprendre la propriété (la reprise est notée dans leur journal).</p>
                </div>
            </div>
            <ul class="fp-lines">
                <?php foreach ($fsOrphans as $f): ?>
                    <li class="fp-line">
                        <span class="fp-line__ic"><?= $ico('folder') ?></span>
                        <span class="fp-line__main"><strong translate="no"><?= $h($f['name']) ?></strong><small>Créé le <?= $h($date((string) $f['created_at'])) ?></small></span>
                        <span class="fp-line__acts">
                            <?php $oid = 'fp-orph-' . (int) $f['id']; ?>
                            <input type="checkbox" class="fp-tg ja-sr" id="<?= $h($oid) ?>" aria-label="Reprendre le dossier <?= $h($f['name']) ?>">
                            <label class="ja-b2 sm" for="<?= $h($oid) ?>">Reprendre le dossier</label>
                            <form method="post" action="<?= $h(url('partage/dossiers/' . (int) $f['id'] . '/reprendre')) ?>" class="fp-confirm">
                                <?= $hidden ?>
                                <p>Reprendre la propriété de ce dossier ?</p>
                                <div class="fp-confirm__acts"><button type="submit" class="ja-b1 sm">Reprendre</button><label class="ja-b2 sm" for="<?= $h($oid) ?>">Annuler</label></div>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

<?php else: ?>
    <nav class="fp-crumbs" aria-label="Fil d’Ariane">
        <a href="<?= $h($link()) ?>">Partagés avec moi</a>
        <?php foreach ($crumbs as $c): ?>
            <span aria-hidden="true">/</span>
            <?php if ((int) $c['id'] === $folderId): ?><span aria-current="page" translate="no"><?= $h($c['name']) ?></span><?php else: ?><a href="<?= $h($link((int) $c['id'])) ?>" translate="no"><?= $h($c['name']) ?></a><?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <?php if (count($entries) > 1): ?>
        <div class="ja-chips fp-quick" role="group" aria-label="Accès rapide aux dossiers">
            <?php foreach ($entries as $e): ?>
                <?php $on = in_array((int) $e['id'], array_map(static fn (array $c): int => (int) $c['id'], $crumbs), true); ?>
                <a class="ja-chip<?= $on ? ' is-on' : '' ?>" href="<?= $h($link((int) $e['id'])) ?>" translate="no"><?= $h($e['name']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="fp-folder" aria-labelledby="fp-t-folder">
        <div class="fp-folder__head">
            <span class="fp-folder__ic"><?= $ico('folder') ?></span>
            <div class="fp-folder__text">
                <h2 class="ja-disp ja-disp--sm" id="fp-t-folder" translate="no"><?= $h($folder['name']) ?></h2>
                <?php if (!empty($folder['description'])): ?><p class="fp-folder__desc" translate="no"><?= $h($folder['description']) ?></p><?php endif; ?>
                <p class="fp-meta">Créé par <?= $h($who((int) $folder['owner_user_id'])) ?> le <?= $h($day((string) $folder['created_at'])) ?> · <?= $h($plural($fileCount, 'fichier', 'fichiers')) ?>, <?= $h(FileShareAccess::humanSize((int) $bytesHere)) ?> · votre accès <?= $levelPill($myLevelKey) ?><?= $isOwner ? ' <span class="fp-chip">propriétaire</span>' : '' ?></p>
            </div>
        </div>

        <div class="fp-tools">
            <div class="fp-acts" role="toolbar" aria-label="Actions sur le dossier">
                <?php if ($level >= FileShareAccess::UPLOAD): ?>
                    <label class="ja-b1 sm" for="fp-up"><?= $ico('upload') ?>Ajouter des fichiers</label>
                    <label class="ja-b2 sm" for="fp-t-sub"><?= $ico('plus') ?>Sous-dossier</label>
                <?php endif; ?>
                <?php if ($level >= FileShareAccess::MANAGE): ?>
                    <a class="ja-b2 sm" href="#partage"><?= $ico('share') ?>Partager</a>
                    <label class="ja-b2 sm" for="fp-t-edit"><?= $ico('edit') ?>Modifier</label>
                    <a class="ja-b2 sm<?= $tab === 'journal' ? ' is-on' : '' ?>" href="<?= $h($link($folderId, $tab === 'journal' ? '' : 'journal')) ?>"><?= $ico('log') ?>Journal</a>
                    <label class="ja-b2 sm fp-danger" for="fp-t-del"><?= $ico('trash') ?>Supprimer</label>
                <?php endif; ?>
            </div>

            <?php if ($level >= FileShareAccess::UPLOAD): ?>
                <input type="checkbox" class="fp-tg ja-sr" id="fp-t-sub">
                <section class="fp-panel" aria-label="Nouveau sous-dossier">
                    <form method="post" action="<?= $h(url('partage/dossiers')) ?>" class="fp-form">
                        <?= $hidden ?>
                        <input type="hidden" name="parent_id" value="<?= $folderId ?>">
                        <p class="fp-note">Dans « <?= $h($folder['name']) ?> » : il hérite de ses partages.</p>
                        <label class="fp-field"><span>Nom</span><input name="name" required maxlength="120"></label>
                        <label class="fp-field"><span>Description <small>(facultatif)</small></span><textarea name="description" rows="2" maxlength="500"></textarea></label>
                        <div class="fp-form__acts"><button type="submit" class="ja-b1 sm">Créer</button><label class="ja-b2 sm" for="fp-t-sub">Annuler</label></div>
                    </form>
                </section>
            <?php endif; ?>
            <?php if ($level >= FileShareAccess::MANAGE): ?>
                <input type="checkbox" class="fp-tg ja-sr" id="fp-t-edit">
                <section class="fp-panel" aria-label="Modifier ce dossier">
                    <form method="post" action="<?= $h(url('partage/dossiers/' . $folderId . '/modifier')) ?>" class="fp-form">
                        <?= $hidden ?>
                        <label class="fp-field"><span>Nom</span><input name="name" required maxlength="120" value="<?= $h($folder['name']) ?>"></label>
                        <label class="fp-field"><span>Description</span><textarea name="description" rows="3" maxlength="500"><?= $h($folder['description'] ?? '') ?></textarea></label>
                        <div class="fp-form__acts"><button type="submit" class="ja-b1 sm">Enregistrer</button><label class="ja-b2 sm" for="fp-t-edit">Annuler</label></div>
                    </form>
                </section>
                <input type="checkbox" class="fp-tg ja-sr" id="fp-t-del">
                <section class="fp-panel fp-panel--danger" aria-label="Supprimer ce dossier">
                    <form method="post" action="<?= $h(url('partage/dossiers/' . $folderId . '/supprimer')) ?>" class="fp-form">
                        <?= $hidden ?>
                        <p>Mettre ce dossier et tout son contenu à la corbeille ? Plus personne n’y aura accès tant qu’il n’est pas restauré.</p>
                        <div class="fp-form__acts"><button type="submit" class="ja-b1 sm fp-danger-fill">Mettre à la corbeille</button><label class="ja-b2 sm" for="fp-t-del">Annuler</label></div>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </section>

    <section class="fp-sec fp-sec--tight" aria-label="Contenu du dossier">
        <?php if ($level >= FileShareAccess::UPLOAD): ?>
            <form method="post" enctype="multipart/form-data" action="<?= $h(url('partage/dossiers/' . $folderId . '/fichiers')) ?>" class="fp-drop" data-fp-drop>
                <?= $hidden ?>
                <input type="file" name="files[]" multiple id="fp-up" class="ja-sr" accept="<?= $h(str_replace(' ', '', (string) $fsAccept)) ?>" data-fp-drop-input>
                <label for="fp-up" class="fp-drop__label">
                    <?= $ico('upload') ?>
                    <span class="fp-drop__text"><b>Déposez vos fichiers ici</b> ou cliquez pour les choisir<small>Jusqu’à 50 fichiers à la fois, <?= $h($maxLabel) ?> maximum chacun : documents, images, tableurs, archives, missions Arma (.sqm, .pbo)</small></span>
                </label>
                <button type="submit" class="ja-b1 sm" data-fp-send>Envoyer</button>
            </form>
        <?php endif; ?>

        <?php if ($subfolders === [] && $files === []): ?>
            <div class="ja-empty">
                <p><strong>Ce dossier est vide.</strong></p>
                <p><?= $level >= FileShareAccess::UPLOAD ? 'Glissez des fichiers dans la zone ci-dessus, ou créez un sous-dossier.' : 'Rien n’y a encore été déposé.' ?></p>
            </div>
        <?php else: ?>
            <div class="fp-bar" data-fp-bar hidden>
                <label class="ja-fi"><?= $ico('search') ?><input type="search" data-fp-filter placeholder="Chercher par nom ou auteur" aria-label="Chercher dans ce dossier" autocomplete="off"></label>
                <?php if (count($kindsPresent) > 1): ?>
                    <div class="ja-chips" role="group" aria-label="Filtrer par type" data-fp-kinds>
                        <button type="button" class="ja-chip is-on" data-fp-kind="">Tous</button>
                        <?php foreach ($kindLabels as $k => $lab): ?>
                            <?php if (isset($kindsPresent[$k])): ?><button type="button" class="ja-chip" data-fp-kind="<?= $h($k) ?>"><?= $h($lab) ?></button><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($fileCount > 1): ?>
                    <label class="fp-sort"><span>Trier</span>
                        <select data-fp-sort aria-label="Trier les fichiers">
                            <option value="name">Nom</option>
                            <option value="date">Plus récents</option>
                            <option value="size">Plus lourds</option>
                        </select>
                    </label>
                <?php endif; ?>
                <?php if ($images !== []): ?>
                    <div class="fp-toggle" role="group" aria-label="Affichage des images">
                        <button type="button" class="is-on" data-fp-view="album" aria-pressed="true" title="Album"><?= $ico('grid') ?><span>Album</span></button>
                        <button type="button" data-fp-view="list" aria-pressed="false" title="Liste"><?= $ico('list') ?><span>Liste</span></button>
                    </div>
                <?php endif; ?>
                <span class="fp-count" data-fp-count role="status" aria-live="polite"></span>
            </div>

            <?php if ($subfolders !== []): ?>
                <h3 class="fp-sub">Sous-dossiers <small class="ja-mono"><?= count($subfolders) ?></small></h3>
                <div class="fp-cards fp-cards--sub" data-fp-list>
                    <?php foreach ($subfolders as $f): ?>
                        <?php $fid = (int) $f['id']; $st = $stats[$fid] ?? ['files' => 0, 'bytes' => 0]; $directGrants = count($ws->grantsOnFolder($fid)); ?>
                        <a class="fp-card" href="<?= $h($link($fid)) ?>" data-fp-item data-name="<?= $h(mb_strtolower((string) $f['name'])) ?>">
                            <span class="fp-card__ic"><?= $ico('folder') ?></span>
                            <span class="fp-card__name" translate="no"><?= $h($f['name']) ?></span>
                            <span class="fp-card__meta"><?= $h($plural((int) $st['files'], 'fichier', 'fichiers')) ?> · <?= $h(FileShareAccess::humanSize((int) $st['bytes'])) ?> · créé par <?= $h($who((int) $f['owner_user_id'])) ?></span>
                            <?php if ($directGrants > 0): ?><span class="fp-card__foot"><span class="fp-chip"><?= $ico('share') ?>+<?= $directGrants ?> accès</span></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($images !== []): ?>
                <h3 class="fp-sub">Photos <small class="ja-mono"><?= count($images) ?></small></h3>
                <?php $files = $images; $albumMode = true; ?>
                <div class="fp-album" data-fp-list data-fp-album>
                    <?php require __DIR__ . '/_files.php'; ?>
                </div>
            <?php endif; ?>

            <?php if ($others !== []): ?>
                <h3 class="fp-sub">Fichiers <small class="ja-mono"><?= count($others) ?></small></h3>
                <?php $files = $others; $albumMode = false; ?>
                <div class="fp-files" data-fp-list>
                    <?php require __DIR__ . '/_files.php'; ?>
                </div>
            <?php endif; ?>
            <div class="ja-empty" data-fp-none hidden><p><strong>Aucun résultat.</strong></p><p>Aucun élément de ce dossier ne correspond à cette recherche.</p></div>
        <?php endif; ?>
    </section>

    <?php if ($tab === 'journal' && $level >= FileShareAccess::MANAGE): ?>
        <?php
        $actions = ['create' => 'a créé', 'upload' => 'a déposé', 'download' => 'a téléchargé', 'preview' => 'a ouvert', 'rename' => 'a renommé', 'delete' => 'a mis à la corbeille', 'restore' => 'a restauré', 'purge' => 'a supprimé définitivement', 'share' => 'a partagé avec', 'unshare' => 'a retiré l’accès à', 'takeover' => 'a repris la propriété'];
        ?>
        <section class="fp-sec" id="journal" aria-labelledby="fp-t-log">
            <div class="ja-sh">
                <div>
                    <h2 class="ja-disp ja-disp--sm" id="fp-t-log">Journal des accès</h2>
                    <p class="fp-note">Qui a ouvert, déposé, partagé ou retiré quoi, dans ce dossier et ses sous-dossiers (80 derniers événements).</p>
                </div>
            </div>
            <?php if (empty($fsEvents)): ?>
                <div class="ja-empty"><p><strong>Aucun événement.</strong></p></div>
            <?php else: ?>
                <ol class="fp-log">
                    <?php foreach ($fsEvents as $e): ?>
                        <li class="fp-log__item">
                            <time class="ja-mono" datetime="<?= $h($e['created_at']) ?>"><?= $h($date((string) $e['created_at'])) ?></time>
                            <span><strong><?= $h(ucfirst($who((int) $e['user_id']))) ?></strong> <?= $h($actions[(string) $e['action']] ?? (string) $e['action']) ?>
                            <?php $object = (string) ($e['file_name'] ?? '') !== '' ? (string) $e['file_name'] : (string) ($e['detail'] ?? ''); ?>
                            <?php if (in_array((string) $e['action'], ['share', 'unshare', 'rename', 'takeover'], true)): ?>
                                <em><?= $h((string) $e['detail']) ?></em><?php if ((string) ($e['file_name'] ?? '') !== ''): ?> <small>(<?= $h($e['file_name']) ?>)</small><?php endif; ?>
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
        <section class="fp-sec" id="partage" aria-labelledby="fp-t-share">
            <div class="ja-sh">
                <div>
                    <h2 class="ja-disp ja-disp--sm" id="fp-t-share">Qui a accès</h2>
                    <p class="fp-note">Le partage vaut pour tout le contenu de « <span translate="no"><?= $h($folder['name']) ?></span> », sous-dossiers compris. <?= $isOwner ? 'Vous en êtes le propriétaire.' : 'Propriétaire : ' . $h($who((int) $folder['owner_user_id'])) . '.' ?></p>
                </div>
            </div>
            <div class="fp-share">
                <div class="fp-share__list">
                    <?= $grantList($ws->grantsOnFolder($folderId), true, $ws->inheritedGrants($folderId)) ?>
                    <dl class="fp-levels">
                        <?php foreach (FileShareAccess::LEVEL_LABELS as $key => $label): ?>
                            <dt><?= $levelPill($key) ?></dt><dd><?= $h(FileShareAccess::LEVEL_HINTS[$key]) ?></dd>
                        <?php endforeach; ?>
                    </dl>
                    <p class="fp-note">Un partage sur un dossier vaut pour tout ce qu’il contient. Le créateur d’un dossier le gère toujours.</p>
                </div>
                <div class="fp-share__add">
                    <h3 class="fp-sub">Ajouter un accès</h3>
                    <?= $shareForm('folder', $folderId, 'fp-folder') ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>
