<?php
declare(strict_types=1);
/**
 * Accueil du bureau SSE : l'état du bureau en une phrase, une bande par sujet (SseHomeBoard, filtrée par les droits et
 * l'habilitation de la session), puis chaque espace du bureau avec ses pages. Les espaces fermés à la session sont nommés
 * en bas de page, avec la raison.
 *
 * @var array{hero: array<string, mixed>, bands: list<array<string, mixed>>}|null $homeBoard
 */
use App\Support\SsePortalNav;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseNavData ??= SsePortalNav::forSession((bool) ($canManage ?? false), (bool) ($canGrant ?? false), (bool) ($isGuest ?? false), isset($canMemberPapers) ? (bool) $canMemberPapers : null);
$board = is_array($homeBoard ?? null) ? $homeBoard : ['hero' => ['parts' => [], 'link' => null, 'calm' => true], 'bands' => []];
$hero = $board['hero'];
$bands = $board['bands'];
$sseExtraStyles = ($sseExtraStyles ?? '') . '<link rel="stylesheet" href="' . $h(asset_url('assets/css/sse-accueil-bandes.css')) . '">';
$sseExtraScripts = ($sseExtraScripts ?? '') . '<script defer src="' . $h(asset_url('assets/js/sse-accueil-bandes.js')) . '"></script>';

// Un fragment chiffré : « 3 files concernées » (nombre en gras) ou un texte à trou (« Il y a 3 j »).
$bit = static function (mixed $b) use ($h): string {
    if (!is_array($b) || (string) ($b['text'] ?? '') === '') {
        return '';
    }
    $text = (string) $b['text'];
    if (str_contains($text, '%d')) {
        return '<span>' . $h(sprintf($text, (int) ($b['n'] ?? 0))) . '</span>';
    }

    return (isset($b['n']) ? '<b>' . (int) $b['n'] . '</b> ' : '') . '<span>' . $h($text) . '</span>';
};
$heroParts = array_values($hero['parts'] ?? []);
$heroLast = count($heroParts) - 1;
?>
<section class="ab-hero<?= !empty($hero['calm']) ? ' is-calm' : '' ?>" aria-labelledby="ab-hero-title">
    <div class="ab-hero__glow" aria-hidden="true"></div>
    <h1 class="ab-hero__title" id="ab-hero-title">
        <?php if ($heroParts === []): ?>
            <span>Le bureau est à jour :</span>
            <span class="ab-chip ab-chip--green"><b aria-hidden="true">✓</b><span>rien n’attend de décision</span></span>
        <?php else: ?>
            <span>Aujourd’hui :</span>
            <?php foreach ($heroParts as $k => $part): ?>
                <span class="ab-hero__part"><?php if ($k > 0 && $k === $heroLast): ?><span class="ab-hero__glue">et</span><?php endif; ?><a class="ab-chip ab-chip--<?= $h($part['accent']) ?>" href="<?= $h($part['href']) ?>"><b><?= $h($part['value']) ?></b><span><?= $h($part['label']) ?></span></a><?php if ($k < $heroLast - 1): ?><span class="ab-hero__glue">,</span><?php endif; ?></span>
            <?php endforeach; ?>
        <?php endif; ?>
    </h1>
    <div class="ab-hero__foot">
        <p class="ab-hero__lead">Ce qui attend une décision d’abord, ce qui remonte du terrain ensuite, puis ce que Cerbère a traité sans vous.</p>
        <?php if (is_array($hero['link'] ?? null)): ?>
            <a class="ab-hero__link" href="<?= $h($hero['link']['href']) ?>"><span><?= $h($hero['link']['label']) ?></span><i aria-hidden="true">→</i></a>
        <?php endif; ?>
    </div>
</section>

<?php if ($bands !== []): ?>
<section class="ab-section" aria-labelledby="ab-bands-title">
    <header class="ab-section__head">
        <h2 id="ab-bands-title">Le bureau en un coup d’œil</h2>
        <p>Une bande par sujet, dans l’ordre où un analyste les ouvre. Chaque ligne mène à l’élément.</p>
    </header>
    <div class="ab-bands" data-ab-bands>
        <?php foreach ($bands as $i => $band): ?>
            <?php
            $items = array_values($band['items'] ?? []);
            $meta = array_map(static fn (string $m): string => '<span class="ab-band__bit">' . $m . '</span>', array_values(array_filter(array_map($bit, $band['meta'] ?? []))));
            $tag = is_array($band['tag'] ?? null) ? $band['tag'] : null;
            $bandId = 'ab-band-' . $h($band['key']);
            ?>
            <article class="ab-band ab-band--<?= $h($band['accent']) ?><?= !empty($band['mirror']) ? ' is-mirror' : '' ?><?= !empty($band['empty']) ? ' is-empty' : '' ?>" style="--ab-i: <?= (int) $i ?>" data-ab-band="<?= $h($band['key']) ?>" aria-labelledby="<?= $bandId ?>">
                <div class="ab-band__media" aria-hidden="true"></div>
                <div class="ab-band__glow" aria-hidden="true"></div>
                <div class="ab-band__veil" aria-hidden="true"></div>
                <div class="ab-band__body">
                    <div class="ab-band__identity">
                        <?php // L'index suit le dernier mot du titre sur la même ligne : à côté du titre, jamais au-dessus. ?>
                        <div class="ab-band__head">
                            <h3 class="ab-band__title" id="<?= $bandId ?>"><?= $h($band['title']) ?></h3><span class="ab-band__index" aria-hidden="true">/ <?= $h($band['index']) ?></span>
                        </div>
                        <p class="ab-band__lead"><?= $h($band['lead']) ?></p>
                        <?php if ($tag !== null): ?>
                            <p class="ab-band__tag ab-band__tag--<?= $h($tag['tone'] ?? 'calm') ?>"><i aria-hidden="true"></i><?= $bit($tag) ?><?php if (is_array($tag['when'] ?? null)): ?> <?= $bit($tag['when']) ?><?php endif; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="ab-band__list">
                        <?php if ($items !== []): ?>
                            <ul class="ab-band__items">
                                <?php foreach ($items as $item): ?>
                                    <li>
                                        <a class="ab-item<?= ($item['tone'] ?? '') === 'alert' ? ' is-alert' : '' ?>" href="<?= $h($item['href']) ?>">
                                            <span class="ab-item__main">
                                                <strong<?= !empty($item['literal']) ? ' translate="no"' : '' ?>><?= $h($item['label']) ?></strong>
                                                <?php if (!empty($item['sub'])): ?><span class="ab-item__sub"><?php foreach (array_values($item['sub']) as $k => $piece): ?><?= $k > 0 ? '<i aria-hidden="true"> · </i>' : '' ?><span><?= $h($piece) ?></span><?php endforeach; ?></span><?php endif; ?>
                                            </span>
                                            <span class="ab-item__when"><?= $bit($item['when'] ?? null) ?></span>
                                            <i class="ab-item__go" aria-hidden="true">→</i>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="ab-band__empty"><?= $h($band['empty_text']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="ab-band__rail">
                        <?php if ($meta !== []): ?>
                            <p class="ab-band__meta"><?= implode('', $meta) ?></p>
                        <?php endif; ?>
                        <p class="ab-band__figure">
                            <strong><?= $h($band['figure']['value']) ?></strong>
                            <span><?= $h($band['figure']['label']) ?></span>
                        </p>
                        <div class="ab-band__actions">
                            <a class="ab-btn ab-btn--solid" href="<?= $h($band['primary']['href']) ?>"><?= $h($band['primary']['label']) ?></a>
                            <?php if (is_array($band['secondary'] ?? null)): ?>
                                <a class="ab-btn ab-btn--quiet" href="<?= $h($band['secondary']['href']) ?>"><?= $h($band['secondary']['label']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="ab-section" aria-labelledby="ab-spaces-title">
    <header class="ab-section__head">
        <h2 id="ab-spaces-title">Tous les espaces</h2>
        <p>Les pages du bureau, rangées comme la barre latérale. <kbd>Ctrl</kbd> <kbd>K</kbd> ouvre une page par son nom.</p>
    </header>
    <div class="ab-spaces">
        <?php foreach ($sseNavData['sections'] as $section): ?>
            <section class="ab-space" id="<?= $h($section['id']) ?>" aria-labelledby="ab-space-<?= $h($section['id']) ?>">
                <div class="ab-space__head">
                    <h3 id="ab-space-<?= $h($section['id']) ?>"><?= $h($section['label']) ?></h3>
                    <p><?= $h($section['hint']) ?></p>
                </div>
                <ul class="ab-space__pages">
                    <?php foreach ($section['items'] as $item): ?>
                        <li>
                            <a href="<?= $h(url($item['path'])) ?>" title="<?= $h($item['hint'] ?? '') ?>">
                                <?= SsePortalNav::icon((string) $item['icon']) ?>
                                <span><?= $h($item['label']) ?><?php if (!empty($item['external'])): ?> <span aria-label="ouvre l’ATAK">↗</span><?php endif; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($sseNavData['restricted'] !== []): ?>
    <section class="ab-section ab-missing" aria-labelledby="ab-missing-title">
        <header class="ab-section__head">
            <h2 id="ab-missing-title">Pages que votre session ne voit pas</h2>
            <p>Elles n’apparaissent pas dans la navigation. Pour y accéder, demandez le droit indiqué à votre commandement.</p>
        </header>
        <ul>
            <?php foreach ($sseNavData['restricted'] as $missing): ?>
                <li><strong><?= $h($missing['label']) ?></strong> <span><?= $h($missing['reason']) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
