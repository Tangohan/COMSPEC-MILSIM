<?php
/**
 * Fiche d'une qualification du catalogue de référence (packs pays et OTAN) dans le catalogue Academy : description,
 * émetteur réel, conditions d'obtention, formations Academy qui y mènent, emplois qui la demandent, sources, import.
 * $refCompact : seulement le panneau « Catalogue de référence » (fiche d'une qualification déjà importée).
 *
 * @var array<string, mixed> $it
 */
use App\Support\QualificationCatalog\QualificationCatalogExplorer as QX;
use App\Support\QualificationCatalog\QualificationIssuerCatalog as QIC;
use App\Support\QualificationCatalog\CatalogCountry;

$refCompact = $refCompact ?? false;
$refLang = locale() === 'en' ? 'en' : 'fr';
$rd = QX::detail((string) $it['ref_id'], $refLang);
if ($rd === null) {
    return;
}
// Fiche Academy d'une autre entrée du catalogue : celle du référentiel si elle est importée.
$refFiche = static function (string $refId) use ($offerItems): string {
    foreach ($offerItems as $oi) {
        if (($oi['ref_id'] ?? '') === $refId) {
            return (string) $oi['id'];
        }
    }
    return 'r-' . $refId;
};
$realIssuer = ($rd['issuer_key'] ?? '') !== '' && ($ri = QIC::find((string) $rd['issuer_key'])) !== null ? QIC::localize($ri, $refLang) : null;
$refFacts = [
    ['Pays', $oxCountry((string) $rd['country'], (string) $rd['country_label'], true)],
    ['Langue officielle', '<span>' . $h(CatalogCountry::language((string) $rd['country'], 'fr')) . '</span>'],
    ['Catégorie', '<span>' . $h($oxNatures[$it['nature'] ?? 'qualification'] ?? '') . '</span>'],
    ['Domaine', '<span translate="no">' . $h((string) $rd['category_label']) . '</span>'],
    ['Niveau', '<span translate="no">' . $h((string) $rd['level']['label']) . '</span>' . (!empty($rd['level']['estimated']) ? ' <small class="acx-muted">estimé</small>' : '')],
    ['Code', '<span translate="no">' . $h((string) $rd['code']) . '</span>'],
];
if ($rd['short_name'] !== '') {
    $refFacts[] = ['Abrégé', '<span translate="no">' . $h((string) $rd['short_name']) . '</span>'];
}
if ($rd['validity'] !== '') {
    $refFacts[] = ['Validité', '<span translate="no">' . $h((string) $rd['validity']) . '</span>'];
}
$issuerPanel = static function () use ($h, $rd, $realIssuer, $oxCountry): void { ?>
    <section class="acx-panel acx-issuer-panel" aria-labelledby="acx-ri-title">
        <h2 id="acx-ri-title">Émetteur</h2>
        <?php if ($realIssuer !== null): ?>
        <div class="acx-issuer-panel__id">
            <span class="acx-badge acx-badge--issuer"><svg class="acx-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V10M19 21V10M9 21v-6h6v6"/><path d="m2.5 10 9.5-6 9.5 6z"/></svg></span>
            <div>
                <p class="acx-issuer-panel__name"><b translate="no"><?= $h($realIssuer['name']) ?></b><?php if ($realIssuer['short_name'] !== ''): ?> <span class="acx-count" translate="no"><?= $h($realIssuer['short_name']) ?></span><?php endif ?></p>
                <?php if ($realIssuer['transliteration'] !== ''): ?><p class="acx-muted" translate="no"><?= $h($realIssuer['transliteration']) ?></p><?php endif ?>
                <p class="acx-muted"><span><?= $h(QIC::typeLabel($realIssuer['type'], 'fr')) ?></span><?php if ($realIssuer['location'] !== ''): ?> · <span translate="no"><?= $h($realIssuer['location']) ?></span><?php endif ?> · <?= $oxCountry($realIssuer['country'], $realIssuer['country_label']) ?></p>
            </div>
        </div>
        <?php if ($realIssuer['description'] !== ''): ?><p translate="no"><?= $h($realIssuer['description']) ?></p><?php endif ?>
        <?php if ($realIssuer['website'] !== ''): ?><p><a class="text-link" href="<?= $h($realIssuer['website']) ?>" target="_blank" rel="noopener noreferrer"><span>Site officiel</span> ↗</a></p><?php endif ?>
        <?php if ($realIssuer['uncertain'] !== ''): ?><p class="acx-note"><span>À confirmer :</span> <span translate="no"><?= $h($realIssuer['uncertain']) ?></span></p><?php endif ?>
        <?php else: ?>
        <p><b translate="no"><?= $h((string) $rd['issuer']) ?></b></p>
        <?php if ($rd['body'] !== ''): ?><p class="acx-muted"><span>Organisme de référence :</span> <span translate="no"><?= $h((string) $rd['body']) ?></span></p><?php endif ?>
        <?php endif ?>
    </section>
<?php };
$jobsPanel = static function () use ($h, $rd, $oxCountry): void {
    if ($rd['jobs'] === []) {
        return;
    } ?>
    <section class="acx-panel" aria-labelledby="acx-rj-title">
        <h2 id="acx-rj-title">Emplois qui la demandent <b class="acx-count"><?= count($rd['jobs']) ?></b></h2>
        <ul class="acx-links acx-links--jobs">
        <?php foreach ($rd['jobs'] as $job): ?>
            <li><span translate="no"><?= $h($job['name']) ?></span><?php if ($job['official_name'] !== '' && $job['official_name'] !== $job['name']): ?> <small class="acx-muted" translate="no"><?= $h($job['official_name']) ?></small><?php endif ?> <?= $oxCountry((string) $job['country'], (string) $job['country_label']) ?></li>
        <?php endforeach ?>
        </ul>
    </section>
<?php };
$sourcesBlock = static function () use ($h, $rd): void {
    if ($rd['sources'] === []) {
        return;
    } ?>
    <section class="acx-panel" aria-labelledby="acx-rs-title">
        <h2 id="acx-rs-title">Sources publiques</h2>
        <ul class="acx-sources"><?php foreach ($rd['sources'] as $src): ?><li><a href="<?= $h($src['url']) ?>" target="_blank" rel="noopener noreferrer" translate="no"><?= $h($src['title'] !== '' ? $src['title'] : $src['url']) ?></a></li><?php endforeach ?></ul>
        <p class="acx-muted" translate="no"><?= $h((string) $rd['disclaimer']) ?></p>
    </section>
<?php };
?>
<?php if ($refCompact): ?>
<section class="acx-panel acx-panel--ref" aria-labelledby="acx-rc-title">
    <h2 id="acx-rc-title">Catalogue de référence</h2>
    <p><span>Qualification réelle importée du catalogue de référence :</span> <?= $oxCountry((string) $rd['country'], (string) $rd['country_label'], true) ?><?php if ($rd['official_name'] !== ''): ?> · <span class="acx-official" translate="no"><?= $h((string) $rd['official_name']) ?></span><?php endif ?></p>
</section>
<?php $issuerPanel(); $jobsPanel(); $sourcesBlock(); return; endif ?>

<p class="acx-back"><a class="text-link" href="<?= $h($oxUrl(['onglet' => 'qualifications', 'origine' => 'catalogue'])) ?>">← <span>Retour :</span> <span>Catalogue de référence</span></a></p>
<article class="acx-detail acx-card--reference">
    <header class="acx-detail__head">
        <?= $oxBadge($it) ?>
        <div>
            <p class="eyebrow"><span><?= $h($oxNatures[$it['nature']] ?? 'Qualification') ?></span> · <?= $oxCountry((string) $rd['country'], (string) $rd['country_label'], true) ?></p>
            <h1 translate="no"><?= $h((string) $rd['name']) ?></h1>
            <?php if ($rd['official_name'] !== '' && $rd['official_name'] !== $rd['name']): ?><p class="acx-official acx-official--lg" translate="no" lang="<?= $h((string) $rd['official_lang']) ?>"><?= $h((string) $rd['official_name']) ?></p><?php endif ?>
            <?php if ($rd['description'] !== ''): ?><p class="acx-detail__lead" translate="no"><?= nl2br($h((string) $rd['description'])) ?></p><?php endif ?>
        </div>
        <?= $oxStatePill($it) ?>
    </header>
    <div class="acx-detail__grid">
        <div class="acx-detail__main">
            <section class="acx-panel" aria-labelledby="acx-rq-title">
                <h2 id="acx-rq-title">Conditions d’obtention</h2>
                <?php if ($rd['prerequisite_links'] === [] && $rd['validity'] === '' && $rd['reference'] === ''): ?><p>Aucun prérequis public n’est précisé pour cette qualification.</p><?php endif ?>
                <?php if ($rd['prerequisite_links'] !== []): ?>
                <h3>Prérequis</h3>
                <ul class="acx-links">
                <?php foreach ($rd['prerequisite_links'] as $pre): ?>
                    <li><span translate="no"><?= $h($pre['text']) ?></span><?php foreach ($pre['links'] as $pl): ?> → <a href="<?= $h($oxFiche($refFiche((string) $pl['id']))) ?>" translate="no"><?= $h($pl['name']) ?></a><?php endforeach ?></li>
                <?php endforeach ?>
                </ul>
                <?php endif ?>
                <?php if ($rd['validity'] !== ''): ?><p><b>Validité :</b> <span translate="no"><?= $h((string) $rd['validity']) ?></span></p><?php endif ?>
                <?php if ($rd['reference'] !== ''): ?><p><b>Référence publique :</b> <span translate="no"><?= $h((string) $rd['reference']) ?></span></p><?php endif ?>
                <?php if ($rd['body'] !== ''): ?><p><b>Organisme de référence :</b> <span translate="no"><?= $h((string) $rd['body']) ?></span></p><?php endif ?>
                <?php if ($rd['unlocks'] !== []): ?>
                <h3>Ouvre l’accès à</h3>
                <ul class="acx-links"><?php foreach ($rd['unlocks'] as $un): ?><li><a href="<?= $h($oxFiche($refFiche((string) $un['id']))) ?>" translate="no"><?= $h($un['name']) ?></a></li><?php endforeach ?></ul>
                <?php endif ?>
            </section>
            <?php $issuerPanel(); ?>
            <section class="acx-panel" aria-labelledby="acx-ra-title">
                <h2 id="acx-ra-title">Formations Academy qui y mènent</h2>
                <?php if (($rd['academy'] ?? null) !== null): ?>
                <ul class="acx-links"><li><a href="<?= $h($oxFiche('a-' . $rd['academy']['key'])) ?>" translate="no"><?= $h((string) $rd['academy']['title']) ?></a> <small class="acx-muted">Formation de jeu à la même procédure</small></li></ul>
                <?php else: ?>
                <p>Aucune formation Academy n’y mène encore. Une fois importée, la qualification peut être rattachée à un cours depuis le Studio.</p>
                <?php endif ?>
            </section>
            <?php $jobsPanel(); $sourcesBlock(); ?>
        </div>
        <aside class="acx-detail__side">
            <section class="acx-panel acx-panel--me" aria-labelledby="acx-rme-title">
                <h2 id="acx-rme-title">Dans mon référentiel</h2>
                <p>Pas encore dans le référentiel de votre communauté. Une fois importée, avec son émetteur réel, elle se délivre comme toute autre qualification.</p>
                <?php $actionsInDetail = true; require __DIR__ . '/offer_actions.php'; $actionsInDetail = false; ?>
            </section>
            <section class="acx-panel" aria-labelledby="acx-rf-title">
                <h2 id="acx-rf-title">En bref</h2>
                <dl class="acx-meta acx-meta--stack"><?php foreach ($refFacts as [$label, $value]): ?><div><dt><?= $h($label) ?></dt><dd><?= $value ?></dd></div><?php endforeach ?></dl>
            </section>
        </aside>
    </div>
</article>
