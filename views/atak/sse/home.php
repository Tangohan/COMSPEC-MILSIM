<?php
declare(strict_types=1);
/**
 * Accueil du bureau SSE : chaque espace en une phrase, avec ses pages, selon les droits de la session.
 * Les espaces fermés à la session sont nommés en bas de page, avec la raison.
 */
use App\Support\SsePortalNav;

ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sseNavData = SsePortalNav::forSession((bool) ($canManage ?? false), (bool) ($canGrant ?? false), (bool) ($isGuest ?? false), isset($canMemberPapers) ? (bool) $canMemberPapers : null);
?>
<header class="sx-head">
    <div class="sx-head__copy">
        <h1>Bureau SSE</h1>
        <p>Toutes les pages du bureau, rangées par usage. La barre latérale garde le même rangement ; <kbd>Ctrl</kbd> <kbd>K</kbd> ouvre une page par son nom.</p>
    </div>
    <div class="sx-head__actions">
        <a class="iw-btn" href="<?= $h(url('atak/sse/operations')) ?>">Vue opérationnelle</a>
        <?php if (empty($isGuest)): ?>
            <a class="iw-btn iw-btn--solid" href="<?= $h(url('atak/sse/fiches/nouvelle')) ?>">Rédiger une fiche</a>
        <?php endif; ?>
    </div>
</header>

<div class="sx-home">
    <?php foreach ($sseNavData['sections'] as $section): ?>
        <section class="sx-home__space" id="<?= $h($section['id']) ?>" aria-labelledby="sx-space-<?= $h($section['id']) ?>">
            <h2 id="sx-space-<?= $h($section['id']) ?>"><?= $h($section['label']) ?></h2>
            <p class="sx-home__lead"><?= $h($section['hint']) ?></p>
            <ul class="sx-home__pages">
                <?php foreach ($section['items'] as $item): ?>
                    <li>
                        <a href="<?= $h(url($item['path'])) ?>">
                            <?= SsePortalNav::icon((string) $item['icon']) ?>
                            <span class="sx-home__page">
                                <strong><?= $h($item['label']) ?><?php if (!empty($item['external'])): ?> <span aria-label="ouvre l’ATAK">↗</span><?php endif; ?></strong>
                                <span><?= $h($item['hint']) ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
</div>

<?php if ($sseNavData['restricted'] !== []): ?>
    <section class="sx-home__missing" aria-labelledby="sx-missing-title">
        <h2 id="sx-missing-title">Pages que votre session ne voit pas</h2>
        <p>Elles n’apparaissent pas dans la navigation. Pour y accéder, demandez le droit indiqué à votre commandement.</p>
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
