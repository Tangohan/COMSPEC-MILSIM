<?php
declare(strict_types=1);

/**
 * Catalogue des qualifications de référence : un onglet par pays (États-Unis, France, Allemagne) et l’OTAN,
 * à importer dans le référentiel. Contenu du catalogue servi dans la langue du lecteur (translate="no" : il n’est
 * pas repris par le dictionnaire). Drapeaux et emblème OTAN dessinés en CSS (job-catalog.css, .jobcat__flag).
 */
$pack = is_array($catalogPack ?? null) ? $catalogPack : null;
$packName = (string) ($catalogPackName ?? '');
$entries = is_array($catalogEntries ?? null) ? $catalogEntries : [];
$imported = is_array($catalogImported ?? null) ? $catalogImported : [];
$countries = is_array($catalogCountries ?? null) ? $catalogCountries : [];
$current = (string) ($catalogCountry ?? '');
$lang = (string) ($catalogLang ?? 'fr');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$pick = static fn ($pair): string => \App\Support\QualificationCatalog\QualificationCatalog::pick($pair, $lang);

$byCategory = [];
foreach ($entries as $entry) {
    $byCategory[(string) $entry['category']][] = $entry;
}
$order = array_flip(array_keys(\App\Support\QualificationCatalog\QualificationCatalog::CATEGORIES));
uksort($byCategory, static fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
$available = count(array_filter($entries, static fn (array $e): bool => !isset($imported[strtoupper((string) $e['code'])])));
$disclaimer = $pack !== null ? $pick($pack['disclaimer'] ?? '') : '';
?>
<div class="rq rqc">
    <div class="rq-top">
        <a class="rq-back" href="<?= $h(url('back-office/referentiels/qualifications')) ?>">← Qualifications</a>
        <div class="rq-top__row">
            <div>
                <p class="rq-kicker">PERSONNEL · QUALIFICATIONS · CATALOGUE</p>
                <h1>Importer depuis le catalogue</h1>
                <p class="rq-lead">Brevets et qualifications de référence par pays (ceux que citent les emplois du catalogue d’emplois) et de l’OTAN, avec leur intitulé officiel, l’école ou l’organisme qui les délivre et leurs sources publiques. Cochez ceux qui servent à votre communauté : chacun devient une fiche de votre référentiel, rattachée à son émetteur (créé s’il manque), que vous pourrez ensuite modifier et régler (à la main, par une formation Academy ou automatiquement). Une qualification déjà présente n’est jamais importée deux fois.</p>
            </div>
        </div>
    </div>

    <nav class="rqc-tabs" aria-label="Pays du catalogue">
        <?php foreach ($countries as $c): $on = $c['code'] === $current; ?>
        <a class="rqc-tab<?= $on ? ' is-on' : '' ?>" href="<?= $h(url('back-office/referentiels/qualifications/catalogue') . '?pays=' . rawurlencode((string) $c['code'])) ?>"<?= $on ? ' aria-current="page"' : '' ?>><span class="jobcat__flag" data-flag="<?= $h((string) $c['code']) ?>" aria-hidden="true"><?= $h((string) $c['code']) ?></span><span translate="no"><?= $h((string) $c['label']) ?></span><span class="rqc-tab__count"><?= (int) $c['count'] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <p class="rqc-warn" role="note"><strong translate="no"><?= $h($disclaimer !== '' ? $disclaimer : \App\Support\QualificationCatalog\QualificationCatalog::disclaimer($lang)) ?></strong> Cette mention est reprise dans la description de chaque qualification importée. Les références citées sont publiques ; rien de classifié.</p>

    <?php if ($pack === null || $entries === []): ?>
        <p class="rqc-empty">Le catalogue de ce pays n’est pas disponible pour le moment.</p>
    <?php else: ?>
    <form method="post" action="<?= $h(url('back-office/referentiels/qualifications/catalogue/importer')) ?>" class="rqc-form">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="pack" value="<?= $h($packName) ?>">
        <div class="rqc-bar">
            <p class="rqc-count"><?= count($entries) ?> qualifications au catalogue · <?= $available ?> à importer</p>
            <div class="rqc-actions">
                <button type="submit" class="rq-btn rq-btn--primary">Importer la sélection</button>
                <button type="submit" name="all" value="1" class="rq-btn"<?= $available === 0 ? ' disabled' : '' ?>>Tout importer</button>
            </div>
        </div>

        <?php foreach ($byCategory as $category => $list): ?>
        <section class="rqc-cat" aria-labelledby="rqc-<?= $h($category) ?>">
            <h2 id="rqc-<?= $h($category) ?>" translate="no"><?= $h((string) $list[0]['category_label']) ?></h2>
            <ul class="rqc-list">
                <?php foreach ($list as $entry):
                    $done = isset($imported[strtoupper((string) $entry['code'])]);
                    $inputId = 'rqc-' . $h((string) $entry['id']);
                    $official = (string) $entry['official_name'];
                ?>
                <li class="rqc-item<?= $done ? ' is-done' : '' ?>">
                    <input type="checkbox" name="ids[]" value="<?= $h((string) $entry['id']) ?>" id="<?= $inputId ?>"<?= $done ? ' disabled' : '' ?>>
                    <div class="rqc-item__body">
                        <label for="<?= $inputId ?>" class="rqc-item__name"><span translate="no"><?= $h($official !== '' ? $official : (string) $entry['name']) ?></span> <span class="rqc-code rq-mono" translate="no"><?= $h((string) $entry['code']) ?></span><?php if ($done): ?> <span class="rqc-done">Déjà dans votre référentiel</span><?php endif; ?></label>
                        <?php if ($official !== '' && $official !== $entry['name']): ?><p class="rqc-item__translated" translate="no"><?= $h((string) $entry['name']) ?></p><?php endif; ?>
                        <p class="rqc-item__desc" translate="no"><?= $h((string) $entry['description']) ?></p>
                        <dl class="rqc-meta">
                            <?php if ($entry['issuer'] !== ''): ?><div><dt>Émetteur</dt><dd translate="no"><?= $h((string) $entry['issuer']) ?></dd></div><?php endif; ?>
                            <?php if ($entry['body'] !== '' && $entry['body'] !== $entry['issuer']): ?><div><dt>Organisme</dt><dd translate="no"><?= $h((string) $entry['body']) ?></dd></div><?php endif; ?>
                            <?php if ($entry['reference'] !== ''): ?><div><dt>Référence publique</dt><dd translate="no"><?= $h((string) $entry['reference']) ?></dd></div><?php endif; ?>
                            <?php if ($entry['prerequisites'] !== []): ?><div><dt>Prérequis</dt><dd translate="no"><?= $h(implode(' ; ', $entry['prerequisites'])) ?></dd></div><?php endif; ?>
                            <?php if ($entry['validity'] !== ''): ?><div><dt>Validité</dt><dd translate="no"><?= $h((string) $entry['validity']) ?></dd></div><?php endif; ?>
                            <?php if ($entry['sources'] !== []): ?><div><dt>Sources</dt><dd><?php foreach ($entry['sources'] as $i => $source): ?><?= $i > 0 ? ' · ' : '' ?><a href="<?= $h((string) $source['url']) ?>" target="_blank" rel="noopener noreferrer" translate="no"><?= $h((string) ($source['title'] !== '' ? $source['title'] : (parse_url((string) $source['url'], PHP_URL_HOST) ?: $source['url']))) ?></a><?php endforeach; ?></dd></div><?php endif; ?>
                        </dl>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endforeach; ?>

        <div class="rqc-bar rqc-bar--end">
            <p class="rqc-count" translate="no"><?= $h($pick($pack['sources_note'] ?? '')) ?></p>
            <button type="submit" class="rq-btn rq-btn--primary">Importer la sélection</button>
        </div>
    </form>
    <?php endif; ?>
</div>
