<?php
declare(strict_types=1);
/**
 * Rubriques « Photos » et « Documents ». Mise en page du design Extranet : titre et bouton « Déposer », chips d'albums
 * ou de catégories, grille de tuiles (légende au survol, visionneuse plein écran) ou liste de documents, cases vides
 * rayées tant qu'il manque de quoi remplir la grille.
 *
 * Sources réelles, jamais de données d'exemple : les fichiers que le partage de fichiers de la communauté a ouverts à
 * l'unité (chacun s'ouvre par /partage/fichiers/{id}, qui refait le contrôle d'accès), les photos terrain publiées dans
 * les échanges que le lecteur voit (JnetSpaceService::unitMedia, mêmes filtres de visibilité que les échanges ; un cliché
 * protégé n'est jamais montré), les pièces jointes des ordres et les documents publiés de la bibliothèque. Le dépôt se
 * fait dans le dossier partagé, avec le droit « Déposer » qu'on y a déjà.
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
$uploadUrl = $uploadFolders !== [] ? url('partage?dossier=' . (int) $uploadFolders[0]['id']) : url('partage');
$tab = $isPhotos ? 'photos' : 'documents';
$albumUrl = static fn (string $a): string => $base . '?vue=' . $tab . '&direct=1' . ($a !== '' ? '&album=' . rawurlencode($a) : '');
$shareTargets = is_array($usw['shareTargets'] ?? null) ? $usw['shareTargets'] : [];
$shareUrl = static fn (array $f): string => $base . '?vue=partager&kind=' . rawurlencode((string) $f['shareKind']) . '&item=' . (int) $f['id'] . '&titre=' . rawurlencode(mb_substr((string) $f['name'], 0, 120)) . '&direct=1';
$canShare = static fn (array $f): bool => $shareTargets !== [] && (string) ($f['shareKind'] ?? '') !== '';
$previewable = static fn (array $f): bool => (string) ($f['source'] ?? '') === 'file' && in_array(strtolower((string) $f['ext']), ['pdf', 'txt'], true);
$upIcon = '<svg class="ja-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M6 10l6-6 6 6M5 20h14"/></svg>';
$placeholders = $isPhotos ? max(0, 7 - count($shownItems)) : max(0, 3 - count($shownItems));
$albumName = $album !== '' ? $album : 'Album';
?>
<header class="us-page us-page--bar">
    <div>
        <h1 class="ja-disp ja-disp--md"><?= $isPhotos ? 'Le terrain. En images.' : 'Tout. Au même endroit.' ?></h1>
        <p><?= $isPhotos
            ? 'Les photos de ' . $h($label) . ' : celles du partage de fichiers de la communauté et les photos terrain publiées dans les échanges de l’unité.'
            : 'Les documents de ' . $h($label) . ' : fichiers partagés, pièces jointes des ordres et documents de la bibliothèque. Chacun s’ouvre avec les droits que vous y avez déjà.' ?></p>
    </div>
    <?php if ($isPhotos): ?><a class="ja-b1 sm" href="<?= $h($uploadUrl) ?>"><?= $upIcon ?> Déposer<?= $uploadFolders !== [] ? ' dans « ' . $h($uploadFolders[0]['name']) . ' »' : '' ?></a><?php endif; ?>
</header>

<?php if (!$isPhotos): ?>
    <a class="uw-drop uw-drop--bar" href="<?= $h($uploadUrl) ?>">
        <?= $upIcon ?>
        <span><b>Déposer un document</b><br>PDF, DOCX, XLSX, KMZ : dans le dossier partagé avec l’unité, visible de ceux qui y ont accès</span>
        <span class="uw-drop__sp"></span>
        <span class="ja-b2 sm">Ouvrir le partage</span>
    </a>
<?php endif; ?>

<div class="uw-tools">
    <?php if (count($albums) > 1): ?>
        <div class="ja-chips" role="group" aria-label="<?= $isPhotos ? 'Albums' : 'Catégories' ?>">
            <a class="ja-chip<?= $album === '' ? ' is-on' : '' ?>" href="<?= $h($albumUrl('')) ?>"<?= $album === '' ? ' aria-current="true"' : '' ?>>Tous <small class="ja-mono"><?= count($items) ?></small></a>
            <?php foreach ($albums as $name => $n): ?>
                <a class="ja-chip<?= $album === $name ? ' is-on' : '' ?>" href="<?= $h($albumUrl($name)) ?>"<?= $album === $name ? ' aria-current="true"' : '' ?>><?= $h($name) ?> <small class="ja-mono"><?= (int) $n ?></small></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <span class="uw-count-line"><?= count($shownItems) ?> <?= $isPhotos ? 'photo' . (count($shownItems) > 1 ? 's' : '') . ' · ' . count($albums) . ' album' . (count($albums) > 1 ? 's' : '') : 'document' . (count($shownItems) > 1 ? 's' : '') ?></span>
</div>

<?php if ($isPhotos): ?>
    <?php if ($shownItems !== []): ?><p class="uw-note uw-js-only" hidden>Cliquez une photo pour l’ouvrir en grand ; les flèches du clavier passent à la suivante, Échap referme.</p><?php endif; ?>
    <div class="uw-photos" data-uw-photos>
        <a class="uw-drop uw-drop--tile" href="<?= $h($uploadUrl) ?>"><?= $upIcon ?><b>Déposer des photos</b>Dans le partage de fichiers</a>
        <?php foreach ($shownItems as $i => $p): ?>
            <a class="uw-photo<?= $i === 0 && count($shownItems) > 3 ? ' is-wide' : '' ?>" href="<?= $h($p['preview']) ?>" target="_blank" rel="noopener" data-uw-photo data-title="<?= $h($p['title']) ?>" data-album="<?= $h($p['album']) ?>" data-by="<?= $h($p['by']) ?>" data-date="<?= $h($p['date']) ?>" data-open="<?= $h($p['href']) ?>"<?= $canShare($p) ? ' data-share="' . $h($shareUrl($p)) . '" data-kind="' . $h($p['shareKind']) . '" data-item="' . (int) $p['id'] . '" data-name="' . $h($p['name']) . '"' : '' ?>>
                <img src="<?= $h($p['preview']) ?>" alt="<?= $h($p['title']) ?>" loading="lazy" decoding="async">
                <span class="uw-photo__cap"><?= $h($p['title']) ?><small><?= $h($p['album']) ?><?= $p['by'] !== '' ? ' · ' . $h($p['by']) : '' ?></small></span>
            </a>
        <?php endforeach; ?>
        <?php for ($k = 0; $k < $placeholders; $k++): ?>
            <div class="uw-photo uw-stripe" aria-hidden="true"><span class="uw-stripe__t"><?= $h($albumName) ?><br>photo à déposer</span></div>
        <?php endfor; ?>
    </div>
    <?php if ($items === []): ?>
        <p class="uw-note uw-empty-note">Aucune photo pour l’instant. Partagez un dossier de « Fichiers partagés » avec l’unité, ou publiez une photo terrain dans un échange : elle apparaîtra ici.</p>
    <?php endif; ?>
<?php else: ?>
    <div class="ja-rows uw-docs">
        <?php foreach ($shownItems as $d): ?>
            <div class="uw-docrow">
                <a class="ja-row uw-doc" href="<?= $h($d['href']) ?>">
                    <span class="uw-ext"><?= $h($d['ext']) ?></span>
                    <span class="t"><b><?= $h($d['title']) ?></b><small><?= $h($d['album']) ?></small></span>
                    <span class="m uw-doc__by"><?= $h($d['by']) ?></span>
                    <span class="m uw-doc__meta"><?= $h(trim($d['date'] . ($d['size'] !== '' ? ' · ' . $d['size'] : ''), ' ·')) ?></span>
                    <span class="ar" aria-hidden="true"><svg class="ja-i" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                </a>
                <?php if ($previewable($d) || $canShare($d)): ?>
                    <span class="uw-docrow__acts">
                        <?php if ($previewable($d)): ?><a class="uw-link" href="<?= $h($d['preview']) ?>" target="_blank" rel="noopener" data-uw-preview data-title="<?= $h($d['name']) ?>" data-open="<?= $h($d['href']) ?>">Aperçu</a><?php endif; ?>
                        <?php if ($canShare($d)): ?><a class="uw-link" href="<?= $h($shareUrl($d)) ?>" data-uw-share data-kind="<?= $h($d['shareKind']) ?>" data-item="<?= (int) $d['id'] ?>" data-title="<?= $h($d['name']) ?>" data-back="<?= $h($albumUrl($album)) ?>">Partager</a><?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php for ($k = 0; $k < $placeholders; $k++): ?>
            <div class="uw-docrow uw-docrow--empty" aria-hidden="true"><span class="uw-ext">—</span><span class="uw-stripe__t">document à déposer</span></div>
        <?php endfor; ?>
    </div>
    <?php if ($items === []): ?>
        <p class="uw-note uw-empty-note">Aucun document pour l’instant. Partagez un dossier de « Fichiers partagés » avec l’unité, joignez un lien à un ordre, ou publiez un document dans la bibliothèque : il apparaîtra ici.</p>
    <?php endif; ?>
<?php endif; ?>
