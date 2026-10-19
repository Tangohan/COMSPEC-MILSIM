<?php
/**
 * Un bloc de la bibliothèque de la vitrine (bandeau, miniatures, encadré, chiffres, texte et image, appel à l’action).
 *
 * @var array<string, mixed> $pbBlock bloc normalisé par PublicPageBuilder
 * @var bool $pbEditing mode édition (attributs d’édition en place)
 */
use App\Services\Community\PublicPageBuilder;

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
$attrs = ' id="pb-' . $h($id) . '" class="pb-block pb-custom pb-' . $h($type) . '" data-pb-id="' . $h($id) . '" data-pb-type="' . $h($type) . '"';
foreach ($st as $sk => $sv) {
    $attrs .= ' data-pb-' . $h(str_replace('_', '-', (string) $sk)) . '="' . $h($sv) . '"';
}
if (!empty($b['hidden'])) {
    $attrs .= ' data-pb-hidden="1"';
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
<?php endif; ?>
</section>
