<?php
declare(strict_types=1);

use App\Services\Intel\IntelPlayerFeedService;

/** @var array<string, mixed> $playerCard */
/** @var list<array<string, mixed>> $playerItems */
/** @var bool $playerTruncated */
/** @var string $playerPeriod */
/** @var string $playerCategory */
/** @var bool $playerCanSeeMembers */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$p = $playerCard;
$items = is_array($playerItems ?? null) ? $playerItems : [];
$period = (string) ($playerPeriod ?? 'tout');
$cat = (string) ($playerCategory ?? '');
$cats = IntelPlayerFeedService::CATEGORIES;
$name = (string) ($p['callsign'] !== '' ? $p['callsign'] : ($p['account'] !== '' ? $p['account'] : 'Joueur'));
$base = 'back-office/remontees/joueurs/' . rawurlencode((string) $p['key']);
$link = static function (array $over) use ($base, $period, $cat): string {
    $params = array_filter(array_merge(['periode' => $period, 'categorie' => $cat], $over), static fn ($v): bool => $v !== '' && $v !== null);

    return url($base . ($params !== [] ? '?' . http_build_query($params) : ''));
};
$when = static fn (string $ts): string => ($t = strtotime($ts)) !== false ? date('d/m/Y H:i', $t) : '';
$dayLabel = static function (string $ts): string {
    $t = strtotime($ts);
    if ($t === false) {
        return 'Date inconnue';
    }
    $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $label = $days[(int) date('w', $t)] . ' ' . (int) date('j', $t) . ' ' . $months[(int) date('n', $t)] . ' ' . date('Y', $t);

    return date('Y-m-d', $t) === date('Y-m-d') ? 'Aujourd’hui · ' . $label : $label;
};
$others = array_values(array_filter((array) ($p['callsigns_seen'] ?? []), static fn (string $c): bool => mb_strtolower($c) !== mb_strtolower((string) $p['callsign'])));
$currentDay = null;
?>
<div class="rmt">
    <div class="rmt__frame">
        <nav class="rmt__crumbs" aria-label="Fil d’Ariane"><a href="<?= $h(url('back-office/remontees')) ?>">Remontées</a> <span aria-hidden="true">/</span> <a href="<?= $h(url('back-office/remontees/joueurs')) ?>">Par joueur</a> <span aria-hidden="true">/</span> <?= $h($name) ?></nav>

        <header class="rmt__pr-head rmt__player-head">
            <span class="rmt__avatar rmt__avatar--lg rmt__avatar--<?= $h($p['kind']) ?>" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($name, 0, 2))) ?></span>
            <div>
                <h1 class="rmt__pr-title"><?= $h($name) ?></h1>
                <dl class="rmt__kv rmt__kv--inline">
                    <div><dt>Compte</dt><dd>
                        <?php if ($p['kind'] === 'user' && $p['user_id'] !== null): ?>
                            <?php if (!empty($playerCanSeeMembers)): ?><a href="<?= $h(url('back-office/users/' . (int) $p['user_id'])) ?>"><?= $h($p['account']) ?></a><?php else: ?><?= $h($p['account']) ?><?php endif; ?>
                        <?php else: ?><span class="rmt__muted">Non rattaché</span><?php endif; ?>
                    </dd></div>
                    <div><dt>Steam</dt><dd><?= $p['steam'] !== '' ? '<span class="rmt__mono">' . $h($p['steam']) . '</span>' : '<span class="rmt__muted">Inconnu</span>' ?></dd></div>
                    <div><dt>Dernière remontée</dt><dd><?= $p['last'] !== '' ? $h($when((string) $p['last'])) : '—' ?></dd></div>
                    <?php if ($others !== []): ?><div><dt>Autres indicatifs</dt><dd><?= $h(implode(', ', $others)) ?></dd></div><?php endif; ?>
                </dl>
            </div>
        </header>

        <div class="rmt__filters">
            <div class="rmt__seg" role="group" aria-label="Période">
                <?php foreach (IntelPlayerFeedService::PERIODS as $k => $label): ?>
                    <a href="<?= $h($link(['periode' => $k])) ?>" class="rmt__seg-btn<?= $period === $k ? ' is-active' : '' ?>"<?= $period === $k ? ' aria-current="true"' : '' ?>><?= $h($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <nav class="rmt__chips" aria-label="Catégories">
            <a href="<?= $h($link(['categorie' => ''])) ?>" class="rmt__cat<?= $cat === '' ? ' is-active' : '' ?>"<?= $cat === '' ? ' aria-current="true"' : '' ?>>Tout <strong><?= (int) $p['total'] ?></strong></a>
            <?php foreach ($cats as $k => $c): $n = (int) ($p['counts'][$k] ?? 0); if ($n === 0 && $cat !== $k) { continue; } ?>
                <a href="<?= $h($link(['categorie' => $k])) ?>" class="rmt__cat rmt__cat--<?= $h($k) ?><?= $cat === $k ? ' is-active' : '' ?>" title="<?= $h($c['label']) ?>"<?= $cat === $k ? ' aria-current="true"' : '' ?>><?= $h($c['short']) ?> <strong><?= $n ?></strong></a>
            <?php endforeach; ?>
        </nav>

        <section class="rmt__box" aria-label="Fil chronologique">
            <?php if ($items === []): ?>
                <div class="rmt__empty">
                    <p class="rmt__empty-title">Rien sur cette période.</p>
                    <p>Choisissez « Tout » pour remonter plus loin.</p>
                </div>
            <?php else: ?>
                <ol class="rmt__timeline">
                    <?php foreach ($items as $i):
                        $day = substr((string) $i['at'], 0, 10);
                        if ($day !== $currentDay):
                            $currentDay = $day; ?>
                            <li class="rmt__day"><?= $h($dayLabel((string) $i['at'])) ?></li>
                        <?php endif; ?>
                        <li class="rmt__tl">
                            <time class="rmt__tl-time" datetime="<?= $h(str_replace(' ', 'T', (string) $i['at'])) ?>" title="<?= $h($when((string) $i['at'])) ?>"><?= ($t = strtotime((string) $i['at'])) !== false ? $h(date('H:i', $t)) : '' ?></time>
                            <span class="rmt__tl-dot rmt__tl-dot--<?= $h($i['cat']) ?>" aria-hidden="true"></span>
                            <div class="rmt__tl-body">
                                <p class="rmt__tl-head">
                                    <span class="rmt__label rmt__label--cat rmt__label--<?= $h($i['cat']) ?>" title="<?= $h($i['label']) ?>"><?= $h($i['cat_label']) ?></span>
                                    <?php if (!empty($i['href'])): ?>
                                        <a class="rmt__link" href="<?= $h(url((string) $i['href'])) ?>"><?= $h($i['title'] !== '' ? $i['title'] : $i['label']) ?></a>
                                    <?php else: ?>
                                        <strong class="rmt__tl-title"><?= $h($i['title'] !== '' ? $i['title'] : $i['label']) ?></strong>
                                    <?php endif; ?>
                                    <?php foreach ((array) $i['tags'] as $tag): ?><span class="rmt__label rmt__label--hot"><?= $h($tag) ?></span><?php endforeach; ?>
                                </p>
                                <?php if ($i['summary'] !== ''): ?><p class="rmt__tl-text"><?= $h($i['summary']) ?></p><?php endif; ?>
                                <p class="rmt__meta rmt__meta--chips">
                                    <span class="rmt__chip" title="Source"><?= $h($i['label']) ?></span>
                                    <?php if ($i['callsign'] !== '' && mb_strtolower($i['callsign']) !== mb_strtolower((string) $p['callsign'])): ?><span class="rmt__chip" title="Indicatif utilisé">« <?= $h($i['callsign']) ?> »</span><?php endif; ?>
                                    <?php if ($i['grid'] !== ''): ?><span class="rmt__chip" title="Carroyage">⌖ <?= $h($i['grid']) ?></span><?php endif; ?>
                                    <?php if ($i['position'] !== ''): ?><span class="rmt__chip" title="Coordonnées carte"><?= $h($i['position']) ?></span><?php endif; ?>
                                    <?php if ($i['mission'] !== ''): ?><span class="rmt__chip" title="Mission"><?= $h($i['mission']) ?></span><?php endif; ?>
                                    <?php if ($i['status'] !== ''): ?><span class="rmt__chip" title="Statut"><?= $h($i['status']) ?></span><?php endif; ?>
                                    <?php if (!empty($i['href'])): ?><a class="rmt__chip rmt__chip--link" href="<?= $h(url((string) $i['href'])) ?>">Ouvrir le suivi →</a><?php endif; ?>
                                </p>
                            </div>
                            <?php if (!empty($i['image'])): ?>
                                <a class="rmt__tl-img<?= !empty($i['blurred']) ? ' is-blurred' : '' ?>" href="<?= $h($i['image']) ?>" target="_blank" rel="noopener" data-recon-viewer="rmt-player" data-title="<?= $h($i['title']) ?>" data-source="<?= $h($i['label']) ?>" data-blurred="<?= !empty($i['blurred']) ? '1' : '0' ?>"><img src="<?= $h($i['image']) ?>" alt="<?= $h($i['title'] !== '' ? $i['title'] : 'Photo') ?>" loading="lazy"></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!empty($playerTruncated)): ?>
                    <p class="rmt__hint rmt__tl-more">Fil limité aux 400 dernières remontées par source : réduisez la période pour remonter plus précisément.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
<script src="<?= $h(asset_url('assets/js/recon-viewer.js')) ?>" defer></script>
