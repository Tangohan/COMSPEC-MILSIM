<?php
/**
 * Aperçu public d'une formation ouverte par un lien « toute personne disposant du lien » : lecture seule,
 * sans compte ni inscription. Cours et démonstrations uniquement : questions, réponses et évaluations restent dans l'Academy.
 */
require __DIR__ . '/init.php';
$customTopbar = true;
require __DIR__ . '/header.php';
require __DIR__ . '/icons.php';
$cover = in_array($course['cover'] ?? '', \App\Services\Academy\AcademyStudio::COVERS, true) ? $course['cover'] : 'night';
$mediaUrl = static fn (string $file): string => url('academy/partage/' . rawurlencode($token) . '/media/' . rawurlencode($file));
?>
<header class="ast-top">
<span class="wm">ACADEMY</span><span class="sep" aria-hidden="true"></span><span class="shp-tag"><?= $h($t('shared_preview')) ?></span>
<span class="sp"></span>
<nav class="lang" aria-label="<?= $h($t('language')) ?>"><?php foreach (['fr', 'en'] as $language): ?><a href="<?= $h(locale_switch_url($language)) ?>" lang="<?= $language ?>" aria-current="<?= $language === $lang ? 'true' : 'false' ?>"><?= strtoupper($language) ?></a><?php endforeach ?></nav>
</header>
<main id="main" class="shp">
<section class="sth-hero shp-hero" style="--img:url('<?= $h(asset_url('assets/images/academy/cover-' . $cover . '.webp')) ?>')"><div class="hin"><div>
<p class="kk"><i></i><?= $h($t('shared_preview')) ?></p><h1><?= $h($local($course['title'])) ?></h1><p><?= $h($local($course['summary'])) ?></p>
<?php if (!empty($course['license'])): ?><p class="shp-license"><?= $h($local($course['license'])) ?></p><?php endif ?>
</div></div></section>
<p class="ast-notice ast-notice--info"><?= $h($t('shared_preview_help')) ?></p>
<?php foreach ($course['chapters'] as $index => $chapter): ?>
<article class="shp-ch" aria-labelledby="shp-<?= $index ?>">
<p class="k"><?= $h($t('chapter')) ?> <?= $index + 1 ?> <?= $h($t('chapter_of')) ?> <?= count($course['chapters']) ?></p>
<h2 id="shp-<?= $index ?>"><?= $h($local($chapter['title'])) ?></h2>
<?php foreach ($chapter['resources'] ?? [] as $resource): if (!isset($resource['file'])) continue; $deckUrl = $mediaUrl((string) $resource['file']); ?>
<figure class="acr-deck" data-academy-deck data-src="<?= $h($deckUrl) ?>" data-lib="<?= $h(asset_url('assets/vendor/pdfjs/pdf.js')) ?>" data-worker="<?= $h(asset_url('assets/vendor/pdfjs/pdf.worker.min.js')) ?>" data-label-of="<?= $h($t('chapter_of')) ?>">
<div class="sv"><canvas data-deck-canvas aria-label="<?= $h($local($resource['title'])) ?>"></canvas><p class="acr-deck__status" data-deck-status><?= $h($t('slides_loading')) ?> <a href="<?= $h($deckUrl) ?>" target="_blank" rel="noopener"><?= $h($t('open_slides')) ?></a></p><button class="nav p" type="button" data-deck-prev aria-label="<?= $h($t('slide_prev')) ?>"></button><button class="nav n" type="button" data-deck-next aria-label="<?= $h($t('slide_next')) ?>"></button></div>
<div class="dc"><button class="ib" type="button" data-deck-prev aria-label="<?= $h($t('slide_prev')) ?>"><svg class="i"><use href="#ai-left"/></svg></button><span class="cnt" data-deck-count>1 / <?= (int) ($resource['pages'] ?? 1) ?></span><button class="ib" type="button" data-deck-next aria-label="<?= $h($t('slide_next')) ?>"><svg class="i"><use href="#ai-right"/></svg></button><div class="pb" data-deck-bar></div><button class="ib" type="button" data-deck-full aria-label="<?= $h($t('fullscreen')) ?>"><svg class="i"><use href="#ai-full"/></svg></button></div>
<div class="thumbs" data-deck-thumbs></div>
<figcaption><?= $h($local($resource['title'])) ?> · <?= (int) ($resource['pages'] ?? 0) ?> <?= $h($t('slides_count')) ?></figcaption>
</figure>
<?php endforeach ?>
<section><h3><?= $h($t('theory')) ?></h3><div class="lesson-text"><?= nl2br($h($local($chapter['theory']))) ?></div></section>
<section><h3><?= $h($t('demonstration')) ?></h3><div class="lesson-text"><?= nl2br($h($local($chapter['demonstration']))) ?></div></section>
</article>
<?php endforeach ?>
</main>
<?php require __DIR__ . '/disclaimer.php'; ?>
</body></html>
