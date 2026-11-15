<?php
declare(strict_types=1);
/**
 * Onglet « Historique » de la fiche identité : chronologie unique (événements propres, contrôles, consultations, photos,
 * mentions, journal d'audit), filtrable par famille, jamais effacée. Inclus par identity_show.php (mêmes variables).
 *
 * @var list<array<string,mixed>> $timeline
 * @var string $timelineFilter
 * @var string $dlang
 * @var int $iid
 */
use App\Support\SseWantedCatalog as W;

$h = $h ?? static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dlang = (string) ($dlang ?? 'fr') === 'en' ? 'en' : 'fr';
$timeline = is_array($timeline ?? null) ? $timeline : [];
$timelineFilter = (string) ($timelineFilter ?? '');
$base = url('atak/sse/papiers/identites/' . (int) $iid);
$tone = static fn (string $type): string => (string) (W::EVENTS[$type][2] ?? 'muted');
$sourceLabels = ['web' => 'Site', 'game' => 'Jeu', 'system' => 'Système'];
$fmt = static function (string $v) use ($dlang): string {
    $ts = $v !== '' ? strtotime($v . ' UTC') : false;

    return $ts === false ? $v : gmdate($dlang === 'en' ? 'Y-m-d H:i' : 'd/m/Y H:i', $ts);
};
?>
<p class="muted">Une seule chronologie par identité, que personne ne peut effacer : fiche, papiers, contrôles, consultations, photos, mentions, avis de recherche et détections. Vous ne voyez que ce que votre habilitation permet de lire.</p>
<nav class="dos-filters" aria-label="Filtrer l’historique">
    <a href="<?= $h($base . '#historique') ?>" <?= $timelineFilter === '' ? 'aria-current="true"' : '' ?>>Tout</a>
    <?php foreach (W::EVENT_FILTERS as $key => $f): ?>
        <a href="<?= $h($base . '?historique=' . $key . '#historique') ?>" <?= $timelineFilter === $key ? 'aria-current="true"' : '' ?>><?= $h($dlang === 'en' ? $f[1] : $f[0]) ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($timeline === []): ?>
    <div class="dos-card"><div class="dos-empty"><strong>Rien à afficher</strong><?= $timelineFilter !== '' ? 'Aucun événement de ce type pour cette identité.' : 'Les événements de cette identité apparaîtront ici dès la première action.' ?></div></div>
<?php else: ?>
    <ol class="dos-timeline">
        <?php foreach ($timeline as $e): ?>
            <li class="dos-event dos-event--<?= $h($tone((string) $e['type'])) ?>">
                <p class="dos-event__text" translate="no"><?= $h($e['summary']) ?></p>
                <p class="dos-event__meta"><time datetime="<?= $h(str_replace(' ', 'T', (string) $e['at']) . 'Z') ?>"><?= $h($fmt((string) $e['at'])) ?></time> · <?= $h($e['actor'] !== '' ? $e['actor'] : 'Système') ?><?= $e['source'] !== '' && isset($sourceLabels[(string) $e['source']]) ? ' · ' . $h($sourceLabels[(string) $e['source']]) : '' ?><?php if (($e['href'] ?? '') !== ''): ?> · <a class="link" href="<?= $h(url((string) $e['href'])) ?>">Ouvrir</a><?php endif; ?></p>
                <?php if (($e['details'] ?? []) !== []): ?>
                    <ul class="dos-diff">
                        <?php foreach ($e['details'] as $c): ?>
                            <li><strong><?= $h($c['label']) ?></strong> : <?php if ($c['old'] !== null): ?><del translate="no"><?= $h($c['old']) ?></del><?php else: ?><em>vide</em><?php endif; ?> → <?php if ($c['new'] !== null): ?><ins translate="no"><?= $h($c['new']) ?></ins><?php else: ?><em>vide</em><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
