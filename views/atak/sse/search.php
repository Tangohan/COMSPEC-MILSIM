<?php
declare(strict_types=1);
ob_start();
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** @var string $q */
/** @var list<array{type:string,ref:string,label:string,href:string,hint?:string}> $results */
$q = (string) ($q ?? '');
$results = is_array($results ?? null) ? $results : [];
$byType = [];
foreach ($results as $r) {
    $t = (string) ($r['type'] ?? 'Autre');
    $byType[$t][] = $r;
}
?>
<section class="sse-desk-hero sse-desk-hero--compact" aria-labelledby="sse-search-title">
    <div class="sse-desk-hero__main">
        <div class="sse-desk-hero__kicker">
            <span class="interest-hero__ref">Recherche</span>
            <span class="badge"><?= count($results) ?> résultat<?= count($results) === 1 ? '' : 's' ?></span>
        </div>
        <h1 id="sse-search-title"><?= $q !== '' ? 'Résultats pour « ' . $h($q) . ' »' : 'Recherche globale' ?></h1>
        <p class="sse-desk-hero__lead">
            Identités, sites, dossiers, dossiers d’intérêt, investigations et documents du bureau.
        </p>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">R.01</span> Nouvelle recherche</div>
    </div>
    <div class="panel-body">
        <form class="iw-search iw-search--page" method="get" action="<?= $h(url('atak/sse/recherche')) ?>" role="search">
            <label class="sr-only" for="sse-search-page-q">Recherche</label>
            <input id="sse-search-page-q" name="q" type="search" value="<?= $h($q) ?>"
                   placeholder="Nom, alias, référence dossier, site, document…" autocomplete="off" autofocus>
            <button type="submit" aria-label="Lancer la recherche">⌕</button>
        </form>
        <?php if ($q !== '' && mb_strlen($q) < 2): ?>
            <p class="sse-note" style="margin-top:.75rem">Saisissez au moins deux caractères.</p>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><span class="panel-index">R.02</span> Objets trouvés</div>
        <div class="panel-meta"><?= count($results) ?> résultat<?= count($results) > 1 ? 's' : '' ?></div>
    </div>
    <?php if ($q === ''): ?>
        <div class="empty-state"><div class="empty-state-inner">
            <strong>Prêt à chercher</strong>
            <p>Utilisez la barre du haut ou le champ ci-dessus (indicatif, référence DI-/SSE-, nom, alias…).</p>
        </div></div>
    <?php elseif ($results === []): ?>
        <div class="empty-state"><div class="empty-state-inner">
            <strong>Aucun résultat</strong>
            <p>Aucun élément ne correspond à « <?= $h($q) ?> ». Essayez une référence complète ou un fragment de nom.</p>
        </div></div>
    <?php else: ?>
        <div class="panel-body sse-search-groups">
            <?php foreach ($byType as $type => $rows): ?>
                <div class="sse-search-group">
                    <h2><?= $h($type) ?> <span><?= count($rows) ?></span></h2>
                    <ul>
                        <?php foreach ($rows as $r): ?>
                            <li>
                                <a href="<?= $h($r['href'] ?? '#') ?>">
                                    <strong><?= $h($r['label'] ?? '') ?></strong>
                                    <span class="record-id"><?= $h($r['ref'] ?? '') ?></span>
                                    <?php if (!empty($r['hint'])): ?>
                                        <em><?= $h($r['hint']) ?></em>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php
$sseContent = ob_get_clean();
require __DIR__ . '/_layout.php';
