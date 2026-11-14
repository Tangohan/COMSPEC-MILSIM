<?php
declare(strict_types=1);
/**
 * Rubriques « Photos » et « Documents » : les fichiers que le Partage de fichiers de la communauté a partagés avec
 * cette unité. Rien n'est stocké ni autorisé ici : chaque fichier s'ouvre par /partage/fichiers/{id}, qui refait le
 * contrôle d'accès ; le dépôt se fait dans le dossier partagé, avec le droit « Déposer » qu'on y a déjà.
 *
 * @var array<string, mixed> $usw
 * @var string $usKind 'photos' ou 'documents'
 * @var string $label
 * @var string $base
 */
use App\Services\Jnet\JnetUnitFiles;

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$files = is_array($usw['files'] ?? null) ? $usw['files'] : ['ready' => false, 'photos' => [], 'documents' => [], 'folders' => []];
$isPhotos = ($usKind ?? 'documents') === 'photos';
$items = $isPhotos ? (array) $files['photos'] : (array) $files['documents'];
$albums = JnetUnitFiles::albums($items);
$album = (string) ($usw['album'] ?? '');
if ($album !== '' && !isset($albums[$album])) {
    $album = '';
}
$shownItems = $album === '' ? $items : array_values(array_filter($items, static fn (array $i): bool => $i['album'] === $album));
$uploadFolders = array_values(array_filter((array) $files['folders'], static fn (array $f): bool => !empty($f['canUpload'])));
$tab = $isPhotos ? 'photos' : 'documents';
$albumUrl = static fn (string $a): string => $base . '?vue=' . $tab . '&direct=1' . ($a !== '' ? '&album=' . rawurlencode($a) : '');
?>
<header class="us-page">
    <h1 class="ja-disp ja-disp--md"><?= $isPhotos ? 'Le terrain. En images.' : 'Tout. Au même endroit.' ?></h1>
    <p><?= $isPhotos
        ? 'Les photos que le partage de fichiers de la communauté a ouvertes à ' . $h($label) . '.'
        : 'Les documents que le partage de fichiers de la communauté a ouverts à ' . $h($label) . '. Chacun s’ouvre avec les droits que vous y avez déjà.' ?></p>
    <?php foreach (array_slice($uploadFolders, 0, 1) as $f): ?>
        <a class="ja-b1 sm" href="<?= $h(url('partage?dossier=' . (int) $f['id'])) ?>">Déposer dans « <?= $h($f['name']) ?> »</a>
    <?php endforeach; ?>
</header>
<?php if (empty($files['ready'])): ?>
    <div class="jn-empty"><p>Le partage de fichiers n’est pas encore installé sur ce serveur.</p></div>
<?php elseif ($items === []): ?>
    <div class="jn-empty">
        <p><?= $isPhotos ? 'Aucune photo n’est partagée avec cette unité.' : 'Aucun document n’est partagé avec cette unité.' ?></p>
        <p>Partagez un dossier de « Fichiers partagés » avec l’unité pour qu’il apparaisse ici.</p>
        <a class="us-btn" href="<?= $h(url('partage')) ?>">Ouvrir les fichiers partagés</a>
    </div>
<?php else: ?>
    <div class="uw-tools">
        <?php if (count($albums) > 1): ?>
            <div class="ja-chips" role="group" aria-label="<?= $isPhotos ? 'Albums' : 'Catégories' ?>">
                <a class="ja-chip<?= $album === '' ? ' is-on' : '' ?>" href="<?= $h($albumUrl('')) ?>">Tous <small class="ja-mono"><?= count($items) ?></small></a>
                <?php foreach ($albums as $name => $n): ?>
                    <a class="ja-chip<?= $album === $name ? ' is-on' : '' ?>" href="<?= $h($albumUrl($name)) ?>"><?= $h($name) ?> <small class="ja-mono"><?= (int) $n ?></small></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <span class="uw-count-line"><?= count($shownItems) ?> <?= $isPhotos ? 'photo' . (count($shownItems) > 1 ? 's' : '') . ' · ' . count($albums) . ' album' . (count($albums) > 1 ? 's' : '') : 'document' . (count($shownItems) > 1 ? 's' : '') ?></span>
    </div>
    <?php if ($isPhotos): ?>
        <div class="uw-photos">
            <?php foreach ($shownItems as $i => $p): ?>
                <a class="uw-photo<?= $i === 0 && count($shownItems) > 3 ? ' is-wide' : '' ?>" href="<?= $h($p['preview']) ?>" target="_blank" rel="noopener">
                    <img src="<?= $h($p['preview']) ?>" alt="<?= $h($p['title']) ?>" loading="lazy" decoding="async">
                    <span class="uw-photo__cap"><?= $h($p['title']) ?><small><?= $h($p['album']) ?><?= $p['by'] !== '' ? ' · ' . $h($p['by']) : '' ?></small></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="ja-rows uw-docs">
            <?php foreach ($shownItems as $d): ?>
                <a class="ja-row uw-doc" href="<?= $h($d['href']) ?>">
                    <span class="uw-ext"><?= $h($d['ext']) ?></span>
                    <span class="t"><b><?= $h($d['title']) ?></b><small><?= $h($d['album']) ?><?= $d['by'] !== '' ? ' · ' . $h($d['by']) : '' ?></small></span>
                    <span class="m uw-doc__meta"><?= $h($d['date']) ?> · <?= $h($d['size']) ?></span>
                    <span class="ar" aria-hidden="true"><svg class="ja-i" viewBox="0 0 24 24"><path d="M12 4v12M6 12l6 6 6-6M5 20h14"/></svg></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
