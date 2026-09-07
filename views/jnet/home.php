<?php
/** Accueil JNET — situation d’unité */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$unitName = (string) ($unitName ?? 'Unité');
$opsStatus = (string) ($opsStatus ?? 'GREEN');
$opsStatusLabel = (string) ($opsStatusLabel ?? 'Posture verte');
$stats = is_array($stats ?? null) ? $stats : [];
$commandStaff = is_array($commandStaff ?? null) ? $commandStaff : [];
$priorityTargets = is_array($priorityTargets ?? null) ? $priorityTargets : [];
$currentOps = is_array($currentOps ?? null) ? $currentOps : [];
$intelFeed = is_array($intelFeed ?? null) ? $intelFeed : [];
$personnelPreview = is_array($personnelPreview ?? null) ? $personnelPreview : [];
$recentArticles = is_array($recentArticles ?? null) ? $recentArticles : [];
$recentDocuments = is_array($recentDocuments ?? null) ? $recentDocuments : [];
$quickLinks = is_array($quickLinks ?? null) ? $quickLinks : [];
$viewerLens = (string) ($viewerLens ?? 'operator');
$targetsTotal = (int) ($targetsTotal ?? count($priorityTargets));
$statusClass = match (strtoupper($opsStatus)) {
    'RED' => 'is-red',
    'AMBER' => 'is-amber',
    default => 'is-green',
};
$lensLabel = match ($viewerLens) {
    'command' => 'Vue commandement',
    'intel' => 'Vue renseignement',
    default => 'Vue opérateur',
};

$face = static function (array $p) use ($h): string {
    $photo = $p['photo'] ?? null;
    $initials = $h((string) ($p['initials'] ?? '?'));
    if (is_string($photo) && $photo !== '') {
        return '<img src="' . $h($photo) . '" alt="">';
    }

    return '<span>' . $initials . '</span>';
};
$prioKey = static fn (array $t): string => strtolower((string) ($t['priority_key'] ?? $t['priority'] ?? 'low'));
?>
<section class="jnet-hero-unit">
    <div class="jnet-hero-unit__row">
        <div class="jnet-hero-unit__id">
            <div class="jnet-unit-badge" aria-hidden="true"><?= $h(strtoupper(substr(preg_replace('/\s+/', '', $unitName) ?: 'U', 0, 3))) ?></div>
            <div>
                <p class="jnet-kicker"><?= $h($lensLabel) ?></p>
                <h1 class="jnet-hero-unit__name"><?= $h($unitName) ?></h1>
                <?php if (trim((string) ($unitMotto ?? '')) !== ''): ?>
                    <p class="jnet-hero-unit__motto"><?= $h((string) $unitMotto) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="jnet-statstrip">
            <div>
                <span>État opérationnel</span>
                <strong class="jnet-status <?= $statusClass ?>"><?= $h($opsStatusLabel) ?></strong>
            </div>
            <div>
                <span>Personnel en service</span>
                <strong><?= (int) ($stats['personnelPresent'] ?? 0) ?><?php if ((int) ($stats['personnelAuth'] ?? 0) > 0): ?><small>/<?= (int) $stats['personnelAuth'] ?></small><?php endif; ?></strong>
            </div>
            <div>
                <span>Opérations en cours</span>
                <strong><?= (int) ($stats['activeOps'] ?? 0) ?></strong>
            </div>
            <div>
                <span>Dossiers suivis</span>
                <strong><?= (int) ($stats['priorityTargets'] ?? 0) ?></strong>
            </div>
        </div>
    </div>
</section>

<?php if ($quickLinks !== []): ?>
    <nav class="jnet-shortcuts" aria-label="Accès directs">
        <?php foreach ($quickLinks as $link): ?>
            <a class="jnet-shortcut" href="<?= $h((string) ($link['href'] ?? '#')) ?>">
                <strong><?= $h((string) ($link['label'] ?? '')) ?></strong>
                <span><?= $h((string) ($link['desc'] ?? '')) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<div class="jnet-home-grid">
    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Commandement d’unité</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/unite')) ?>">Fiche d’unité</a>
        </div>
        <div class="jnet-panel__body jnet-face-list">
            <?php if ($commandStaff === []): ?>
                <div class="jnet-empty">
                    <p>Aucun cadre identifié pour le moment.</p>
                    <p>Les postes apparaissent ici lorsque les fonctions sont renseignées dans les dossiers.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($commandStaff as $p): ?>
                <a class="jnet-face-row" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                    <div class="jnet-avatar jnet-avatar--lg"><?= $face($p) ?></div>
                    <div>
                        <strong><?= $h((string) ($p['name'] ?? '')) ?></strong>
                        <span><?= $h((string) (($p['function'] ?? '') !== '' ? $p['function'] : 'Fonction non renseignée')) ?></span>
                        <?php if (trim((string) ($p['meta_line'] ?? '')) !== ''): ?>
                            <em><?= $h((string) $p['meta_line']) ?></em>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Renseignement suivi</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/cibles')) ?>">Voir tout<?= $targetsTotal > 0 ? ' (' . $targetsTotal . ')' : '' ?></a>
        </div>
        <div class="jnet-panel__body jnet-face-list">
            <?php if ($priorityTargets === []): ?>
                <div class="jnet-empty">
                    <p>Aucun dossier de renseignement ouvert.</p>
                    <p>Les personnes et objectifs suivis par le bureau SSE apparaissent ici.</p>
                    <p><a class="jnet-btn" href="<?= $h(url('atak/sse/interet')) ?>">Ouvrir le bureau SSE</a></p>
                </div>
            <?php endif; ?>
            <?php foreach ($priorityTargets as $t): ?>
                <a class="jnet-face-row jnet-face-row--target" href="<?= $h((string) ($t['href'] ?? '#')) ?>">
                    <div class="jnet-avatar jnet-avatar--lg jnet-avatar--target"><?= $face(['initials' => substr((string) ($t['name'] ?? '?'), 0, 2), 'photo' => $t['photo'] ?? null]) ?></div>
                    <div>
                        <strong><?= $h((string) ($t['name'] ?? '')) ?><?php if (trim((string) ($t['code'] ?? '')) !== ''): ?> <small><?= $h((string) $t['code']) ?></small><?php endif; ?></strong>
                        <span class="jnet-prio jnet-prio--<?= $h($prioKey($t)) ?>"><?= $h((string) ($t['priority'] ?? '')) ?></span>
                        <em><?= $h(trim(implode(' · ', array_filter([
                            ($t['confidence_label'] ?? '') !== '' ? (string) $t['confidence_label'] : null,
                            (string) ($t['lastKnown'] ?? ''),
                        ])))) ?></em>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Opérations en cours</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/operations')) ?>">Toutes les opérations</a>
        </div>
        <div class="jnet-panel__body">
            <?php foreach ($currentOps as $op): ?>
                <a class="jnet-op-row" href="<?= $h(url('jnet/operations/' . (int) ($op['id'] ?? 0))) ?>">
                    <strong><?= $h((string) ($op['title'] ?? '')) ?></strong>
                    <span class="jnet-badge <?= ($op['state_key'] ?? '') === 'active' ? 'jnet-badge--ok' : 'jnet-badge--watch' ?>"><?= $h((string) ($op['state'] ?? '')) ?></span>
                </a>
            <?php endforeach; ?>
            <?php if ($currentOps === []): ?>
                <div class="jnet-empty">
                    <p>Aucune opération engagée.</p>
                    <p>Les missions ouvertes sur le tableau opérationnel apparaissent ici.</p>
                    <p><a class="jnet-btn" href="<?= $h(url('back-office/tableau-operationnel')) ?>">Ouvrir le tableau opérationnel</a></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Journal d’unité</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/renseignement')) ?>">Renseignement</a>
        </div>
        <div class="jnet-panel__body jnet-feed">
            <?php if ($intelFeed === []): ?>
                <div class="jnet-empty">
                    <p>Aucune entrée récente.</p>
                    <p>Les fiches terrain, dossiers suivis, opérations et articles publiés s’afficheront ici.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($intelFeed as $ev): ?>
                <a class="jnet-feed__item" href="<?= $h((string) ($ev['href'] ?? '#')) ?>">
                    <?php if (trim((string) ($ev['time'] ?? '')) !== ''): ?>
                        <time><?= $h((string) $ev['time']) ?></time>
                    <?php endif; ?>
                    <div>
                        <strong><?= $h((string) ($ev['kind'] ?? '')) ?> · <?= $h((string) ($ev['title'] ?? '')) ?></strong>
                        <?php if (trim((string) ($ev['detail'] ?? '')) !== ''): ?>
                            <span><?= $h((string) $ev['detail']) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="jnet-grid-2">
    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Articles de l’unité</h2>
            <a class="jnet-btn" href="<?= $h(url('articles')) ?>">Tous les articles</a>
        </div>
        <div class="jnet-panel__body">
            <?php if ($recentArticles === []): ?>
                <div class="jnet-empty">
                    <p>Aucun article publié.</p>
                    <p>Les notes d’unité mises en ligne apparaîtront ici.</p>
                </div>
            <?php else: ?>
                <ul class="jnet-linklist">
                    <?php foreach ($recentArticles as $article): ?>
                        <li>
                            <a href="<?= $h((string) ($article['href'] ?? '#')) ?>">
                                <strong><?= $h((string) ($article['title'] ?? '')) ?></strong>
                                <?php if (trim((string) ($article['excerpt'] ?? '')) !== ''): ?>
                                    <span><?= $h((string) $article['excerpt']) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="jnet-panel">
        <div class="jnet-panel__head">
            <h2>Documents publiés</h2>
            <a class="jnet-btn" href="<?= $h(url('jnet/bibliotheque')) ?>">Bibliothèque</a>
        </div>
        <div class="jnet-panel__body">
            <?php if ($recentDocuments === []): ?>
                <div class="jnet-empty">
                    <p>Aucun document publié.</p>
                    <p>Les consignes et doctrines mises à disposition de l’unité apparaîtront ici.</p>
                </div>
            <?php else: ?>
                <ul class="jnet-linklist">
                    <?php foreach ($recentDocuments as $doc): ?>
                        <li>
                            <a href="<?= $h((string) ($doc['href'] ?? '#')) ?>">
                                <strong><?= $h((string) ($doc['title'] ?? '')) ?></strong>
                                <?php if (trim((string) ($doc['category'] ?? '')) !== ''): ?>
                                    <span><?= $h((string) $doc['category']) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="jnet-panel jnet-panel--faces">
    <div class="jnet-panel__head">
        <h2>Personnel</h2>
        <a class="jnet-btn" href="<?= $h(url('jnet/personnel')) ?>">Annuaire complet</a>
    </div>
    <div class="jnet-panel__body">
        <?php if ($personnelPreview === []): ?>
            <div class="jnet-empty">
                <p>Aucun membre actif dans l’annuaire.</p>
            </div>
        <?php else: ?>
            <div class="jnet-gallery">
                <?php foreach ($personnelPreview as $p): ?>
                    <a class="jnet-person-card" href="<?= $h((string) ($p['href'] ?? '#')) ?>">
                        <div class="jnet-avatar jnet-avatar--xl"><?= $face($p) ?></div>
                        <strong><?= $h((string) ($p['name'] ?? '')) ?></strong>
                        <?php if (trim((string) ($p['grade'] ?? '')) !== ''): ?>
                            <span><?= $h((string) $p['grade']) ?></span>
                        <?php endif; ?>
                        <span><?= $h(trim(implode(' · ', array_filter([(string) ($p['unit'] ?? ''), (string) ($p['function'] ?? '')])))) ?></span>
                        <em class="jnet-duty"><?= $h((string) ($p['duty_label'] ?? '')) ?></em>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
