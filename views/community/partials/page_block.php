<?php
/**
 * Un bloc de la bibliothèque de la vitrine (bandeau, miniatures, encadré, chiffres, texte et image, appel à l’action).
 *
 * @var array<string, mixed> $pbBlock bloc normalisé par PublicPageBuilder
 * @var bool $pbEditing mode édition (attributs d’édition en place)
 * @var array<int, array<string, mixed>> $pbSheets tableaux de données lisibles (blocs « sheet » de l’accueil du back-office)
 */
use App\Services\BackOffice\Home\HomeBlocks;
use App\Services\Community\PublicPageBuilder;
use App\Support\RichTextLite;
use App\Support\VideoEmbed;

$pbEditing = !empty($pbEditing);
$b = is_array($pbBlock ?? null) ? $pbBlock : [];
$type = (string) ($b['type'] ?? '');
$p = is_array($b['props'] ?? null) ? $b['props'] : [];
$st = is_array($b['style'] ?? null) ? $b['style'] : [];
$id = (string) ($b['id'] ?? '');
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ed = static fn (string $attr): string => $pbEditing ? ' ' . $attr : '';
$txt = static function (string $key, string $tag, string $class, bool $multi = false) use ($p, $h, $ed, $pbEditing): string {
    $v = (string) ($p[$key] ?? '');
    if ($v === '' && !$pbEditing) {
        return '';
    }
    $body = $multi ? nl2br($h($v)) : $h($v);

    return '<' . $tag . ' class="' . $class . '" translate="no"' . $ed('data-cl-prop="' . $key . '" data-placeholder="' . $h(match ($key) {
        'kicker' => 'Surtitre',
        'title' => 'Titre',
        'text' => 'Texte',
        default => 'Texte',
    }) . '"') . '>' . $body . '</' . $tag . '>';
};
$btn = static function (string $labelKey, string $urlKey, string $class) use ($p, $h, $ed, $pbEditing): string {
    $label = (string) ($p[$labelKey] ?? '');
    $url = (string) ($p[$urlKey] ?? '');
    if ($label === '' && !$pbEditing) {
        return '';
    }
    if ($label === '' && $pbEditing) {
        return '<span class="cl-btn ' . $class . ' pb-btn-empty" data-cl-prop="' . $labelKey . '" data-placeholder="Bouton"></span>';
    }
    $ext = preg_match('#^https?://#i', $url) ? ' target="_blank" rel="noopener noreferrer"' : '';
    $href = $url !== '' ? $url : '#';

    return '<a class="cl-btn ' . $class . '" href="' . $h($href) . '"' . $ext . ' translate="no"' . $ed('data-cl-prop="' . $labelKey . '"') . '>' . $h($label) . '</a>';
};
$img = static function (string $src, string $alt, string $class, string $slot) use ($h, $ed, $pbEditing): string {
    $url = PublicPageBuilder::imageUrl($src);
    if ($url === '') {
        return '<div class="' . $class . ' pb-img--empty"' . $ed('data-cl-img="' . $slot . '"') . ' aria-hidden="true">'
            . ($pbEditing ? '<span class="pb-img__hint">Ajouter une image</span>' : '') . '</div>';
    }

    return '<img class="' . $class . '" src="' . $h($url) . '" alt="' . $h($alt) . '" loading="lazy"' . $ed('data-cl-img="' . $slot . '"') . '>';
};
$headBlock = static function () use ($txt): string {
    $html = $txt('kicker', 'p', 'cl-kicker pb-kicker') . $txt('title', 'h2', 'cl-h2 pb-title') . $txt('text', 'p', 'pb-lead', true);

    return $html !== '' ? '<header class="pb-head">' . $html . '</header>' : '';
};
$rich = static function (string $key) use ($p, $h, $ed, $pbEditing): string {
    $v = (string) ($p[$key] ?? '');
    if ($pbEditing) {
        return '<div class="pb-rich pb-rich--src cl-prose" translate="no"' . $ed('data-cl-prop="' . $key . '" data-placeholder="Texte : ## titre, **gras**, - liste, [lien](https://…)"') . '>' . nl2br($h($v)) . '</div>';
    }

    return $v === '' ? '' : '<div class="pb-rich cl-prose" translate="no">' . RichTextLite::render($v) . '</div>';
};
$attrs = ' id="pb-' . $h($id) . '" class="pb-block pb-custom pb-' . $h($type) . '" data-pb-id="' . $h($id) . '" data-pb-type="' . $h($type) . '"';
foreach ($st as $sk => $sv) {
    $attrs .= ' data-pb-' . $h(str_replace('_', '-', (string) $sk)) . '="' . $h($sv) . '"';
}
if (!empty($b['hidden'])) {
    $attrs .= ' data-pb-hidden="1"';
}
?>
<?php
$pbSheet = $type === 'sheet' ? (($pbSheets ?? [])[(int) ($p['sheet_id'] ?? 0)] ?? null) : null;
if ($type === 'sheet' && $pbSheet === null && !$pbEditing) {
    return;
}
?>
<section<?= $attrs ?>>
<?php if ($type === 'hero'): ?>
  <div class="pb-hero__frame">
    <div class="pb-hero__media"><?= $img((string) ($p['image'] ?? ''), (string) ($p['image_alt'] ?? ''), 'pb-hero__img', 'image') ?></div>
    <div class="pb-hero__scrim" aria-hidden="true"></div>
    <div class="pb-hero__inner">
      <?= $txt('kicker', 'p', 'pb-hero__kicker') ?>
      <?= $txt('title', 'h2', 'pb-hero__title') ?>
      <?= $txt('text', 'p', 'pb-hero__text', true) ?>
      <div class="pb-actions">
        <?= $btn('button_label', 'button_url', 'cl-btn--accent') ?>
        <?= $btn('button2_label', 'button2_url', 'cl-btn--ghost') ?>
      </div>
    </div>
  </div>
<?php elseif ($type === 'tiles'): ?>
  <div class="pb-panel">
    <?= $headBlock() ?>
    <div class="pb-tiles__grid">
      <?php foreach (is_array($p['items'] ?? null) ? $p['items'] : [] as $i => $it): ?>
        <?php
        $tUrl = (string) ($it['url'] ?? '');
        $tag = $tUrl !== '' && !$pbEditing ? 'a' : 'article';
        $tExt = preg_match('#^https?://#i', $tUrl) ? ' target="_blank" rel="noopener noreferrer"' : '';
        $tHref = $tag === 'a' ? ' href="' . $h($tUrl) . '"' . $tExt : '';
        ?>
      <<?= $tag ?> class="pb-tile"<?= $tHref ?> data-cl-bitem="<?= (int) $i ?>">
        <div class="pb-tile__media">
          <?= $img((string) ($it['image'] ?? ''), (string) ($it['title'] ?? ''), 'pb-tile__img', 'items.' . (int) $i . '.image') ?>
          <?php if (($it['badge'] ?? '') !== '' || $pbEditing): ?>
          <span class="pb-tile__badge" translate="no"<?= $ed('data-cl-isub="badge" data-placeholder="Badge"') ?>><?= $h($it['badge'] ?? '') ?></span>
          <?php endif; ?>
        </div>
        <div class="pb-tile__body">
          <h3 class="pb-tile__title" translate="no"<?= $ed('data-cl-isub="title" data-placeholder="Titre"') ?>><?= $h($it['title'] ?? '') ?></h3>
          <?php if (($it['text'] ?? '') !== '' || $pbEditing): ?>
          <p class="pb-tile__text" translate="no"<?= $ed('data-cl-isub="text" data-placeholder="Texte"') ?>><?= nl2br($h($it['text'] ?? '')) ?></p>
          <?php endif; ?>
          <?php if ($tUrl !== '' && !$pbEditing): ?><span class="pb-tile__arrow" aria-hidden="true">→</span><?php endif; ?>
        </div>
      </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
  </div>
<?php elseif ($type === 'infobox'): ?>
  <?php
  $icon = (string) ($st['icon'] ?? 'info');
  $paths = [
      'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
      'alert' => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
      'check' => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
      'star' => '<path d="M12 3.8l2.5 5.2 5.7.8-4.1 4 1 5.6L12 16.7l-5.1 2.7 1-5.6-4.1-4 5.7-.8z"/>',
      'calendar' => '<rect x="4" y="5" width="16" height="15" rx="1"/><path d="M4 10h16M9 3v4M15 3v4"/>',
      'radio' => '<path d="M5 9a10 10 0 0 1 14 0M8 12a6 6 0 0 1 8 0"/><circle cx="12" cy="16" r="1.6"/>',
      'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z"/>',
  ];
  ?>
  <div class="pb-infobox__box" role="note">
    <?php if (isset($paths[$icon])): ?>
    <span class="pb-infobox__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><?= $paths[$icon] ?></svg></span>
    <?php endif; ?>
    <div class="pb-infobox__body">
      <?= $txt('kicker', 'p', 'pb-infobox__kicker') ?>
      <?= $txt('title', 'h3', 'pb-infobox__title') ?>
      <?= $txt('text', 'p', 'pb-infobox__text', true) ?>
    </div>
    <div class="pb-infobox__action"><?= $btn('button_label', 'button_url', 'cl-btn--light') ?></div>
  </div>
<?php elseif ($type === 'stats'): ?>
  <div class="pb-panel">
    <?= $headBlock() ?>
    <div class="pb-stats__grid" style="--pb-n:<?= max(1, count(is_array($p['items'] ?? null) ? $p['items'] : [])) ?>">
      <?php foreach (is_array($p['items'] ?? null) ? $p['items'] : [] as $i => $it): ?>
      <div class="pb-stat" data-cl-bitem="<?= (int) $i ?>">
        <div class="pb-stat__v" translate="no"<?= $ed('data-cl-isub="value" data-placeholder="42"') ?>><?= $h($it['value'] ?? '') ?></div>
        <div class="pb-stat__k" translate="no"<?= $ed('data-cl-isub="label" data-placeholder="Libellé"') ?>><?= $h($it['label'] ?? '') ?></div>
        <?php if (($it['note'] ?? '') !== '' || $pbEditing): ?>
        <div class="pb-stat__n" translate="no"<?= $ed('data-cl-isub="note" data-placeholder="Précision"') ?>><?= $h($it['note'] ?? '') ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php elseif ($type === 'textmedia'): ?>
  <div class="pb-panel pb-textmedia__grid">
    <div class="pb-textmedia__copy">
      <?= $txt('kicker', 'p', 'cl-kicker pb-kicker') ?>
      <?= $txt('title', 'h2', 'cl-h2 pb-title') ?>
      <?= $txt('text', 'p', 'cl-prose pb-prose', true) ?>
      <div class="pb-actions"><?= $btn('button_label', 'button_url', 'cl-btn--accent') ?></div>
    </div>
    <figure class="pb-textmedia__media"><?= $img((string) ($p['image'] ?? ''), (string) ($p['image_alt'] ?? ''), 'pb-textmedia__img', 'image') ?></figure>
  </div>
<?php elseif ($type === 'cta'): ?>
  <div class="pb-panel pb-cta__box">
    <div class="pb-cta__copy">
      <?= $txt('kicker', 'p', 'cl-kicker pb-kicker') ?>
      <?= $txt('title', 'h2', 'cl-h2 pb-title') ?>
      <?= $txt('text', 'p', 'pb-lead', true) ?>
    </div>
    <div class="pb-actions">
      <?= $btn('button_label', 'button_url', 'cl-btn--accent') ?>
      <?= $btn('button2_label', 'button2_url', 'cl-btn--ghost') ?>
    </div>
  </div>
<?php elseif ($type === 'article'): ?>
  <?php $aImg = PublicPageBuilder::imageUrl((string) ($p['image'] ?? '')); ?>
  <article class="pb-panel pb-article">
    <?php if ($aImg !== '' || $pbEditing): ?>
    <figure class="pb-article__media"><?= $img((string) ($p['image'] ?? ''), (string) ($p['image_alt'] ?? ''), 'pb-article__img', 'image') ?></figure>
    <?php endif; ?>
    <div class="pb-article__body">
      <?= $txt('kicker', 'p', 'cl-kicker pb-kicker') ?>
      <?= $txt('title', 'h2', 'cl-h2 pb-title') ?>
      <?= $rich('text') ?>
      <div class="pb-actions"><?= $btn('button_label', 'button_url', 'cl-btn--accent') ?></div>
    </div>
  </article>
<?php elseif ($type === 'richtext'): ?>
  <div class="pb-panel pb-richtext__box">
    <?= $txt('title', 'h2', 'cl-h2 pb-title') ?>
    <?= $rich('text') ?>
  </div>
<?php elseif ($type === 'quote'): ?>
  <figure class="pb-panel pb-quote__box">
    <blockquote class="pb-quote__text" translate="no"<?= $ed('data-cl-prop="text" data-placeholder="Citation"') ?>><?= nl2br($h($p['text'] ?? '')) ?></blockquote>
    <?php if (($p['name'] ?? '') !== '' || ($p['meta'] ?? '') !== '' || $pbEditing): ?>
    <figcaption class="pb-quote__cite">
      <span class="pb-quote__name" translate="no"<?= $ed('data-cl-prop="name" data-placeholder="Auteur"') ?>><?= $h($p['name'] ?? '') ?></span>
      <span class="pb-quote__meta" translate="no"<?= $ed('data-cl-prop="meta" data-placeholder="Rôle ou contexte"') ?>><?= $h($p['meta'] ?? '') ?></span>
    </figcaption>
    <?php endif; ?>
  </figure>
<?php elseif ($type === 'links'): ?>
  <div class="pb-panel">
    <?= $txt('title', 'h2', 'cl-h2 pb-title') ?>
    <?php $lItems = is_array($p['items'] ?? null) ? $p['items'] : []; ?>
    <?php if ($lItems === [] && $pbEditing): ?><p class="pb-empty">Ajoutez des liens dans le panneau de réglages du bloc.</p><?php endif; ?>
    <ul class="pb-links__list">
      <?php foreach ($lItems as $i => $it): ?>
        <?php
        $lUrl = (string) ($it['url'] ?? '');
        $lExt = preg_match('#^https?://#i', $lUrl) ? ' target="_blank" rel="noopener noreferrer"' : '';
        ?>
      <li class="pb-links__it" data-cl-bitem="<?= (int) $i ?>">
        <a class="pb-links__a" href="<?= $h($lUrl !== '' ? $lUrl : '#') ?>"<?= $lExt ?>>
          <span class="pb-links__label" translate="no"<?= $ed('data-cl-isub="label" data-placeholder="Libellé"') ?>><?= $h($it['label'] ?? '') ?></span>
          <?php if (($it['hint'] ?? '') !== '' || $pbEditing): ?>
          <small class="pb-links__hint" translate="no"<?= $ed('data-cl-isub="hint" data-placeholder="Précision"') ?>><?= $h($it['hint'] ?? '') ?></small>
          <?php endif; ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php elseif ($type === 'gallery'): ?>
  <div class="pb-panel">
    <?= $txt('title', 'h2', 'cl-h2 pb-title') ?>
    <?php $gItems = is_array($p['items'] ?? null) ? $p['items'] : []; ?>
    <?php if ($gItems === [] && $pbEditing): ?><p class="pb-empty">Ajoutez des images dans le panneau de réglages du bloc.</p><?php endif; ?>
    <div class="pb-gallery__grid">
      <?php foreach ($gItems as $i => $it): ?>
      <figure class="pb-gallery__it" data-cl-bitem="<?= (int) $i ?>">
        <?= $img((string) ($it['image'] ?? ''), (string) ($it['alt'] ?? ''), 'pb-gallery__img', 'items.' . (int) $i . '.image') ?>
        <?php if (($it['caption'] ?? '') !== '' || $pbEditing): ?>
        <figcaption translate="no"<?= $ed('data-cl-isub="caption" data-placeholder="Légende"') ?>><?= $h($it['caption'] ?? '') ?></figcaption>
        <?php endif; ?>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
<?php elseif ($type === 'video'): ?>
  <?php $vEmbed = VideoEmbed::embedUrl($p['video_url'] ?? ''); ?>
  <div class="pb-panel">
    <?= $txt('title', 'h2', 'cl-h2 pb-title') ?>
    <div class="pb-video__frame">
      <?php if ($vEmbed !== ''): ?>
      <iframe src="<?= $h($vEmbed) ?>" title="<?= $h(($p['title'] ?? '') !== '' ? $p['title'] : 'Vidéo') ?>" loading="lazy" allowfullscreen allow="fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"<?= $pbEditing ? ' tabindex="-1"' : '' ?>></iframe>
      <?php else: ?>
      <div class="pb-video__empty"><span>Collez l’adresse d’une vidéo YouTube ou Vimeo dans le panneau de réglages.</span></div>
      <?php endif; ?>
    </div>
    <?= $txt('text', 'p', 'pb-lead', true) ?>
  </div>
<?php elseif ($type === 'divider'): ?>
  <div class="pb-divider__box" role="separator">
    <span class="pb-divider__line" aria-hidden="true"></span>
    <?php if (($p['title'] ?? '') !== '' || $pbEditing): ?>
    <span class="pb-divider__label" translate="no"<?= $ed('data-cl-prop="title" data-placeholder="Titre (facultatif)"') ?>><?= $h($p['title'] ?? '') ?></span>
    <span class="pb-divider__line" aria-hidden="true"></span>
    <?php endif; ?>
  </div>
<?php elseif ($type === 'table'): ?>
  <?php $tRows = HomeBlocks::tableRows((string) ($p['text'] ?? '')); $tHead = $tRows[0] ?? []; $tBody = array_slice($tRows, 1); ?>
  <div class="pb-panel">
    <?= $txt('title', 'h2', 'cl-h2 pb-title') ?>
    <?php if ($tHead === []): ?>
      <?php if ($pbEditing): ?><p class="pb-empty">Saisissez le tableau dans le panneau de réglages du bloc.</p><?php endif; ?>
    <?php else: ?>
    <div class="pb-table__wrap" tabindex="0" role="region" aria-label="<?= $h(($p['title'] ?? '') !== '' ? $p['title'] : 'Tableau') ?>">
      <table class="pb-table" translate="no">
        <thead><tr><?php foreach ($tHead as $c): ?><th scope="col"><?= $h($c) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($tBody as $row): ?>
          <tr><?php foreach ($tHead as $ci => $unused): ?><td><?= $h($row[$ci] ?? '') ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
<?php elseif ($type === 'sheet'): ?>
  <div class="pb-panel">
    <?= $txt('title', 'h2', 'cl-h2 pb-title') ?>
    <?php if ($pbSheet === null): ?>
      <p class="pb-empty">Choisissez un de vos tableaux de données dans le panneau de réglages du bloc.</p>
    <?php else: ?>
    <div class="pb-table__wrap" tabindex="0" role="region" aria-label="<?= $h($pbSheet['title']) ?>">
      <table class="pb-table" translate="no">
        <thead><tr><?php foreach ($pbSheet['cols'] as $c): ?><th scope="col"><?= $h($c) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($pbSheet['rows'] as $row): ?>
          <tr><?php foreach ($row as $cell): ?><td><?= $h($cell) ?></td><?php endforeach; ?></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="pb-table__more">
      <a class="pb-link" href="<?= $h($pbSheet['url']) ?>"><?= $pbSheet['total'] > count($pbSheet['rows']) ? 'Voir les ' . (int) $pbSheet['total'] . ' lignes' : 'Ouvrir le tableau' ?></a>
    </p>
    <?php endif; ?>
  </div>
<?php endif; ?>
</section>
