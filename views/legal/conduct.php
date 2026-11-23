<?php

declare(strict_types=1);

/**
 * Code de conduite Athena. Textes : lang/{fr,en}/conduct.php (articles 1 à 7 cités par les avis de suspension).
 */
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$articles = range(1, 13);
$contactEmail = (string) (legal_public_contact_email() ?? ((array) (require base_path('config/contact_mail.php')))['address'] ?? '');
$articleLabel = static fn (int $number): string => __('conduct.article', ['number' => $number]);
$articleTitle = static fn (int $number): string => $articleLabel($number) . ' · ' . __('conduct.article_' . $number . '_title');

/** Remplace les renvois [[art:N]], [[contact]], [[appeal]]… par des liens, après échappement du texte. */
$links = static function (string $text) use ($escape, $contactEmail, $articleLabel): string {
    $html = $escape($text);
    $html = (string) preg_replace_callback('/\[\[art:(\d{1,2})\]\]/', static function (array $m) use ($articleLabel): string {
        $n = (int) $m[1];

        return '<a href="#article-' . $n . '">' . htmlspecialchars(mb_strtolower($articleLabel($n)), ENT_QUOTES, 'UTF-8') . '</a>';
    }, $html);
    $targets = [
        'appeal' => [url('contester-sanction'), 'conduct.link_appeal'],
        'rights' => [url('demande-donnees'), 'conduct.link_rights'],
        'cgu' => [url('legal/site') . '#cgu', 'conduct.link_cgu'],
        'mentions' => [url('legal/site') . '#mentions', 'conduct.link_mentions'],
        'privacy' => [url('legal/site') . '#rgpd', 'conduct.link_privacy'],
    ];
    foreach ($targets as $token => [$href, $labelKey]) {
        $html = str_replace('[[' . $token . ']]', '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(__($labelKey), ENT_QUOTES, 'UTF-8') . '</a>', $html);
    }
    $mail = $contactEmail !== ''
        ? '<a href="mailto:' . htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') . '</a>'
        : '<a href="' . htmlspecialchars(url('contact'), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(__('conduct.contact'), ENT_QUOTES, 'UTF-8') . '</a>';

    return str_replace('[[contact]]', $mail, $html);
};

/** Paragraphes séparés par une ligne vide, puces sur les lignes « - ». */
$render = static function (string $body) use ($links): string {
    $out = '';
    foreach (preg_split('/\n{2,}/', trim($body)) ?: [] as $block) {
        $lines = explode("\n", $block);
        $intro = [];
        $items = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '- ')) {
                $items[] = substr($line, 2);
            } else {
                $intro[] = $line;
            }
        }
        if ($intro !== []) {
            $out .= '<p>' . $links(implode(' ', $intro)) . '</p>';
        }
        if ($items !== []) {
            $out .= '<ul>';
            foreach ($items as $item) {
                $out .= '<li>' . $links($item) . '</li>';
            }
            $out .= '</ul>';
        }
    }

    return $out;
};
?>
<h1><?= $escape(__('conduct.title')) ?></h1>
<p><?= $escape(__('conduct.intro')) ?></p>
<p><?= $links(__('conduct.intro_scope')) ?></p>

<p class="legal-updated">
    <?= $escape(__('conduct.version_label')) ?> <?= $escape(__('conduct.version')) ?> ·
    <?= $escape(__('conduct.updated_label')) ?> : <time datetime="<?= $escape(__('conduct.updated_iso')) ?>"><?= $escape(__('conduct.updated')) ?></time>
    <button type="button" class="legal-print" onclick="window.print()"><?= $escape(__('conduct.print')) ?></button>
</p>

<div class="legal-conduct-tools">
    <label for="conduct-search"><?= $escape(__('conduct.search_label')) ?></label>
    <input type="search" id="conduct-search" autocomplete="off" placeholder="<?= $escape(__('conduct.search_placeholder')) ?>">
    <p class="legal-conduct-status" id="conduct-status" role="status" aria-live="polite"></p>
</div>

<nav class="legal-conduct-index" id="sommaire" aria-label="<?= $escape(__('conduct.index')) ?>">
    <ol>
        <?php foreach ($articles as $number): ?>
            <li data-article="<?= $number ?>"><a href="#article-<?= $number ?>"><?= $escape($articleTitle($number)) ?></a></li>
        <?php endforeach; ?>
    </ol>
</nav>

<?php foreach ($articles as $number): ?>
    <?php $example = (string) __('conduct.article_' . $number . '_example'); ?>
    <section id="article-<?= $number ?>" class="legal-article" aria-labelledby="article-<?= $number ?>-title" data-article="<?= $number ?>">
        <h2 id="article-<?= $number ?>-title">
            <?= $escape($articleTitle($number)) ?>
            <a class="legal-permalink" href="#article-<?= $number ?>" aria-label="<?= $escape(__('conduct.permalink', ['title' => $articleTitle($number)])) ?>">#</a>
        </h2>
        <p class="legal-summary"><strong><?= $escape(__('conduct.summary_label')) ?>.</strong> <?= $links(__('conduct.article_' . $number . '_summary')) ?></p>
        <?= $render(__('conduct.article_' . $number . '_body')) ?>
        <?php if ($example !== '' && $example !== 'conduct.article_' . $number . '_example'): ?>
            <aside class="legal-example"><strong><?= $escape(__('conduct.example')) ?></strong> <?= $links($example) ?></aside>
        <?php endif; ?>
        <p class="legal-backtop"><a href="#sommaire"><?= $escape(__('conduct.back_top')) ?></a></p>
    </section>
<?php endforeach; ?>
<p class="legal-conduct-empty" id="conduct-empty" hidden><?= $escape(__('conduct.search_none')) ?></p>
<p><a href="<?= $escape(url('legal/site') . '#cgu') ?>"><?= $escape(__('legal.cgu_desc')) ?></a></p>

<script>
(function () {
  var input = document.getElementById('conduct-search');
  if (!input) return;
  var items = document.querySelectorAll('[data-article]');
  var status = document.getElementById('conduct-status');
  var empty = document.getElementById('conduct-empty');
  var template = <?= json_encode(__('conduct.search_count', ['count' => '__N__']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  function norm(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  var texts = {};
  document.querySelectorAll('section[data-article]').forEach(function (s) { texts[s.dataset.article] = norm(s.textContent); });
  input.addEventListener('input', function () {
    var q = norm(input.value.trim()), shown = 0;
    Object.keys(texts).forEach(function (n) {
      var ok = q === '' || texts[n].indexOf(q) !== -1;
      if (ok) shown++;
      document.querySelectorAll('[data-article="' + n + '"]').forEach(function (el) { el.hidden = !ok; });
    });
    empty.hidden = shown !== 0;
    status.textContent = q === '' ? '' : template.replace('__N__', shown);
  });
})();
</script>
