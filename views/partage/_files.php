<?php
declare(strict_types=1);

use App\Support\FileShareAccess;
use App\Support\FileShareStorage;

/**
 * Fichiers d'un dossier, en lignes (liste) ou en vignettes (album d'images) : un seul balisage, la mise en page vient du
 * conteneur (.fp-files ou .fp-album). Variables de views/partage/index.php : $files, $albumMode, $ws, $who, $kindOf...
 *
 * @var list<array<string, mixed>> $files
 * @var bool $albumMode
 */
$albumMode = !empty($albumMode);
if (!$albumMode): ?>
<div class="fp-files__head" aria-hidden="true"><span></span><span>Nom</span><span>Taille</span><span>Déposé le</span><span>Auteur</span><span>Partage</span><span></span></div>
<?php endif;
foreach ($files as $f):
    $fileId = (int) $f['id'];
    $name = (string) $f['name'];
    $kind = $kindOf($name);
    $fl = $ws->fileLevel($f);
    $canManage = $fl >= FileShareAccess::MANAGE;
    $canDelete = $ws->canDeleteFile($f);
    $inline = FileShareStorage::isInline(FileShareStorage::TYPES[FileShareStorage::extension($name)] ?? '');
    $dl = url('partage/fichiers/' . $fileId);
    $open = $dl . ($inline ? '?apercu=1' : '');
    $directAll = $ws->grantsOnFile($fileId);
    $direct = array_values(array_filter($directAll, static fn (array $g): bool => !$ws->isExpired($g)));
    $author = $who((int) $f['uploaded_by']);
    $size = FileShareAccess::humanSize((int) $f['size_bytes']);
    $names = [];
    if ($canManage) {
        $all = $direct;
        if ($ws->folderLevel((int) $f['folder_id']) >= FileShareAccess::MANAGE) {
            foreach (array_merge($ws->grantsOnFolder((int) $f['folder_id']), $ws->inheritedGrants((int) $f['folder_id'])) as $g) {
                if (!$ws->isExpired($g)) {
                    $all[] = $g;
                }
            }
        }
        foreach ($all as $g) {
            $kindName = (string) $g['target_type'] === 'unit' ? 'Unité ' : ((string) $g['target_type'] === 'role' ? 'Rôle ' : '');
            $names[$kindName . $grantName($g)] = true;
        }
        $names = array_keys($names);
    }
    $mid = 'fp-m-' . $fileId;
    ?>
    <article class="fp-file fp-file--<?= $h($kind) ?>" id="fichier-<?= $fileId ?>" data-fp-item data-kind="<?= $h($kind) ?>" data-name="<?= $h(mb_strtolower($name . ' ' . $author)) ?>" data-title="<?= $h($name) ?>" data-ts="<?= (int) strtotime((string) $f['created_at']) ?>" data-size="<?= (int) $f['size_bytes'] ?>">
        <?php if ($canManage || $canDelete): ?><input type="checkbox" class="fp-tg ja-sr" id="<?= $h($mid) ?>" aria-label="Gérer <?= $h($name) ?>"><?php endif; ?>
        <a class="fp-file__thumb" href="<?= $h($open) ?>"<?= $inline ? ' target="_blank" rel="noopener"' : '' ?> tabindex="-1" aria-hidden="true">
            <?php if ($kind === 'image'): ?>
                <img src="<?= $h($dl . '?apercu=1') ?>" alt="" loading="lazy" decoding="async">
            <?php else: ?>
                <?= $ico($kindIcon[$kind]) ?>
            <?php endif; ?>
        </a>
        <div class="fp-file__main">
            <a class="fp-file__name" href="<?= $h($open) ?>"<?= $inline ? ' target="_blank" rel="noopener"' : '' ?> translate="no"><?= $h($name) ?></a>
            <span class="fp-file__sub"><b class="ja-mono"><?= $h(strtoupper($ext($name))) ?></b><?= (int) $f['download_count'] > 0 ? ' · ' . $h($plural((int) $f['download_count'], 'téléchargement', 'téléchargements')) : '' ?></span>
            <span class="fp-file__line"><?= $h($size) ?> · <?= $h($day((string) $f['created_at'])) ?> · <?= $h($author) ?></span>
        </div>
        <span class="fp-file__c fp-file__size"><?= $h($size) ?></span>
        <time class="fp-file__c fp-file__date" datetime="<?= $h((string) $f['created_at']) ?>"><?= $h($day((string) $f['created_at'])) ?></time>
        <span class="fp-file__c fp-file__by"><?= $h($author) ?></span>
        <span class="fp-file__c fp-file__share">
            <?php if ($canManage): ?>
                <?php if ($names === []): ?>
                    <span class="fp-chip"><?= $ico('lock') ?>Privé</span>
                <?php else: ?>
                    <?php foreach (array_slice($names, 0, 2) as $n): ?><span class="fp-chip" translate="no"><?= $h($n) ?></span><?php endforeach; ?>
                    <?php if (count($names) > 2): ?><span class="fp-chip">+<?= count($names) - 2 ?></span><?php endif; ?>
                <?php endif; ?>
            <?php elseif ($direct !== []): ?>
                <span class="fp-chip"><?= $ico('share') ?>+<?= count($direct) ?> accès</span>
            <?php endif; ?>
        </span>
        <span class="fp-file__acts">
            <?php if ($inline): ?><a class="fp-ib" href="<?= $h($dl . '?apercu=1') ?>" target="_blank" rel="noopener" title="Aperçu" aria-label="Aperçu de <?= $h($name) ?>"><?= $ico('eye') ?></a><?php endif; ?>
            <a class="fp-ib" href="<?= $h($dl) ?>" title="Télécharger" aria-label="Télécharger <?= $h($name) ?>"><?= $ico('download') ?></a>
            <?php if ($canManage || $canDelete): ?><label class="fp-ib fp-ib--manage" for="<?= $h($mid) ?>" title="Gérer ce fichier"><?= $ico('gear') ?><span>Gérer</span></label><?php endif; ?>
        </span>
        <?php if ($canManage || $canDelete): ?>
            <div class="fp-manage">
                <?php if ($canManage): ?>
                    <form method="post" action="<?= $h(url('partage/fichiers/' . $fileId . '/renommer')) ?>" class="fp-form fp-form--inline">
                        <?= $hidden ?>
                        <label class="fp-field"><span>Renommer</span><input name="name" value="<?= $h($name) ?>" maxlength="255" required></label>
                        <button type="submit" class="ja-b2 sm">Renommer</button>
                    </form>
                    <div class="fp-manage__share">
                        <h4 class="fp-sub">Partager ce fichier seul</h4>
                        <?= $grantList($directAll, true) ?>
                        <?= $shareForm('file', $fileId, 'fp-file-' . $fileId) ?>
                    </div>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                    <div class="fp-manage__del">
                        <?= $confirmPost(url('partage/fichiers/' . $fileId . '/supprimer'), 'Mettre à la corbeille', 'Mettre « ' . $name . ' » à la corbeille ? Vous pourrez le restaurer.', 'Mettre à la corbeille', 'fp-x-' . $fileId) ?>
                    </div>
                <?php endif; ?>
                <label class="ja-b2 sm fp-manage__close" for="<?= $h($mid) ?>"><?= $ico('close') ?>Fermer</label>
            </div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
