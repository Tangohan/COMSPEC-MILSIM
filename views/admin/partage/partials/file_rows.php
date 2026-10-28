<?php
declare(strict_types=1);

use App\Support\FileShareAccess;
use App\Support\FileShareStorage;

/**
 * Lignes de fichiers (dossier ouvert, ou fichiers partagés seuls). Variables de views/admin/partage/index.php.
 */
?>
<ul class="fs-rows fs-rows--files">
    <?php foreach ($files as $f): ?>
        <?php
        $fileId = (int) $f['id'];
        $fl = $ws->fileLevel($f);
        $canManage = $fl >= FileShareAccess::MANAGE;
        $canDelete = $ws->canDeleteFile($f);
        $inline = FileShareStorage::isInline(FileShareStorage::TYPES[FileShareStorage::extension((string) $f['name'])] ?? '');
        $dl = url('partage/fichiers/' . $fileId);
        $direct = $ws->grantsOnFile($fileId);
        ?>
        <li class="fs-row fs-row--file" id="fichier-<?= $fileId ?>">
            <span class="fs-row__icon fs-row__icon--<?= $h($fileIcon((string) $f['name'])) ?>"><?= $ico($fileIcon((string) $f['name'])) ?></span>
            <a class="fs-row__main" href="<?= $h($dl . ($inline ? '?apercu=1' : '')) ?>"<?= $inline ? ' target="_blank" rel="noopener"' : '' ?>>
                <strong translate="no"><?= $h($f['name']) ?></strong>
                <span class="fs__muted"><?= $h(FileShareAccess::humanSize((int) $f['size_bytes'])) ?> · déposé par <?= $h($who((int) $f['uploaded_by'])) ?> le <?= $h($date((string) $f['created_at'])) ?><?= (int) $f['download_count'] > 0 ? ' · ' . (int) $f['download_count'] . ' téléchargement(s)' : '' ?></span>
            </a>
            <span class="fs-row__meta">
                <?php if ($direct !== []): ?><span class="fs-chip"><?= $ico('share') ?>+<?= count($direct) ?> accès</span><?php endif; ?>
            </span>
            <span class="fs-row__actions">
                <?php if ($inline): ?><a class="fs-btn fs-btn--icon" href="<?= $h($dl . '?apercu=1') ?>" target="_blank" rel="noopener" title="Aperçu" aria-label="Aperçu de <?= $h($f['name']) ?>"><?= $ico('eye') ?></a><?php endif; ?>
                <a class="fs-btn fs-btn--icon" href="<?= $h($dl) ?>" title="Télécharger" aria-label="Télécharger <?= $h($f['name']) ?>"><?= $ico('download') ?></a>
                <?php if ($canManage || $canDelete): ?>
                    <details class="fs-more">
                        <summary class="fs-btn fs-btn--icon" title="Plus d’actions" aria-label="Plus d’actions pour <?= $h($f['name']) ?>">⋯</summary>
                        <div class="fs-more__menu">
                            <?php if ($canManage): ?>
                                <form method="post" action="<?= $h(url('partage/fichiers/' . $fileId . '/renommer')) ?>" class="fs-inline-form">
                                    <?= $hidden ?>
                                    <label class="fs-field"><span>Renommer</span><input name="name" value="<?= $h($f['name']) ?>" maxlength="255" required></label>
                                    <button type="submit" class="fs-btn fs-btn--sm">Renommer</button>
                                </form>
                                <div class="fs-more__share">
                                    <p class="fs-more__title"><?= $ico('share') ?>Partager ce fichier seul</p>
                                    <?= $grantList($direct, true) ?>
                                    <?= $shareForm('file', $fileId, 'fs-file-' . $fileId) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                                <form method="post" action="<?= $h(url('partage/fichiers/' . $fileId . '/supprimer')) ?>" data-confirm="Mettre « <?= $h($f['name']) ?> » à la corbeille ?">
                                    <?= $hidden ?>
                                    <button type="submit" class="fs-btn fs-btn--sm fs-btn--danger-ghost"><?= $ico('trash') ?>Mettre à la corbeille</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
