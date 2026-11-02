<?php
/**
 * Fiche détaillée d'un élément du catalogue (qualification, brevet, formation, parcours, compétence, émetteur).
 *
 * @var array<string, mixed> $it
 */
$backTab = $it['tabs'][0] ?? 'tout';
$award = is_array($it['award'] ?? null) ? $it['award'] : null;
$cardKind = $it['kind'] === 'qualification' && in_array('brevets', $it['tabs'], true) ? 'Brevet' : ($it['kind'] === 'course' && ($it['licence'] ?? '') !== '' ? 'Formation à licence' : $oxKinds[$it['kind']]);
$facts = [];
if (($it['issuer'] ?? '') !== '' && $it['kind'] !== 'issuer') {
    $facts[] = [$it['kind'] === 'qualification' ? 'Émetteur' : 'Proposé par', '<span translate="no">' . $h($it['issuer']) . '</span>'];
}
if (($it['category'] ?? '') !== '') {
    $facts[] = [$it['kind'] === 'competency' ? 'Palier' : ($it['kind'] === 'issuer' ? 'Type' : 'Catégorie'), '<span translate="no">' . $h($it['category']) . '</span>'];
}
if ($it['level'] !== '') {
    $facts[] = ['Niveau', $oxLevel((string) $it['level'])];
}
if ($oxDuration($it['duration_min'] ?? null) !== '') {
    $facts[] = ['Durée', $h($oxDuration($it['duration_min']))];
}
if ($oxValidity($it['validity'] ?? []) !== '') {
    $facts[] = ['Validité', $oxValidity($it['validity'])];
}
if (!empty($it['grade'])) {
    $facts[] = ['Grade', '<span translate="no">' . $h($it['grade']) . '</span>'];
}
if (!empty($it['unit'])) {
    $facts[] = ['Unité', '<span translate="no">' . $h($it['unit']) . '</span>'];
}
if ($it['modes'] !== []) {
    $facts[] = ['Mode d’obtention', implode(' · ', array_map(static fn (string $m): string => '<span>' . $h($oxModes[$m] ?? $m) . '</span>', $it['modes']))];
}
$facts[] = ['Origine', '<span>' . $h($oxSources[$it['source']] ?? '') . '</span>'];
?>
<p class="acx-back"><a class="text-link" href="<?= $h($oxUrl(['onglet' => $backTab])) ?>">← <span>Retour :</span> <span><?= $h($oxTabs[$backTab]) ?></span></a></p>
<article class="acx-detail acx-card--<?= $h($it['kind']) ?> is-<?= $h($it['state']) ?>">
    <header class="acx-detail__head">
        <?= $oxBadge($it) ?>
        <div>
            <p class="eyebrow"><span><?= $h($cardKind) ?></span></p>
            <h1 translate="no"><?= $h($it['title']) ?></h1>
            <?php if (($it['summary'] ?? '') !== ''): ?><p class="acx-detail__lead" translate="no"><?= nl2br($h($it['summary'])) ?></p><?php endif ?>
        </div>
        <?= $oxStatePill($it) ?>
    </header>

    <div class="acx-detail__grid">
        <div class="acx-detail__main">
            <?php if (!in_array($it['kind'], ['pathway', 'competency', 'issuer'], true)): ?>
            <section class="acx-panel" aria-labelledby="acx-how-title">
                <h2 id="acx-how-title">Comment l’obtenir</h2>
                <?php $howFull = true; require __DIR__ . '/offer_how.php'; $howFull = false; ?>
                <?php if ($it['pct'] !== null && !in_array($it['state'], ['held', 'expiring'], true)): ?>
                    <div class="acx-progress"><span><span>Votre avancement</span> <b><?= (int) $it['pct'] ?> %</b></span><?= $oxBar((int) $it['pct'], 'Votre avancement') ?></div>
                <?php endif ?>
                <?php if ($it['state'] === 'eligible'): ?><p class="acx-note acx-note--ok">Vous remplissez les conditions : la qualification sera délivrée au prochain passage automatique, ou proposée à un responsable.</p><?php endif ?>
            </section>
            <?php endif ?>

            <?php if (($it['prereqs'] ?? []) !== [] || ($it['missing'] ?? []) !== []): ?>
            <section class="acx-panel" aria-labelledby="acx-pre-title">
                <h2 id="acx-pre-title">Prérequis</h2>
                <?php if (($it['prereqs'] ?? []) !== []): ?><ul class="acx-links"><?php foreach ($it['prereqs'] as $pre): ?><li><a href="<?= $h($oxFiche($pre['detail'])) ?>" translate="no"><?= $h($pre['label']) ?></a></li><?php endforeach ?></ul><?php endif ?>
                <?php if (($it['missing'] ?? []) !== []): ?><p class="acx-missing"><?= $oxSymbol('alert') ?><span><span>Il vous manque :</span><?php foreach ($it['missing'] as $missingLine): ?> <span class="acx-missing__item"><?= $h($missingLine) ?></span><?php endforeach ?></span></p><?php endif ?>
            </section>
            <?php endif ?>

            <?php if ($it['kind'] === 'pathway'): ?>
            <section class="acx-panel" aria-labelledby="acx-steps-title">
                <h2 id="acx-steps-title">Étapes du parcours</h2>
                <?php if ($it['pct'] !== null): ?><div class="acx-progress"><span><span>Votre avancement</span> <b><?= (int) $it['pct'] ?> %</b></span><?= $oxBar((int) $it['pct'], 'Votre avancement') ?></div><?php endif ?>
                <ol class="acx-steps">
                <?php foreach ($it['steps'] as $n => $step): ?>
                    <li class="<?= $step['done'] ? 'is-done' : '' ?>">
                        <span class="acx-steps__num" aria-hidden="true"><?= $step['done'] ? $oxSymbol('check') : $n + 1 ?></span>
                        <div>
                            <?php if ($step['detail'] !== ''): ?><a href="<?= $h($oxFiche($step['detail'])) ?>" translate="no"><?= $h($step['title']) ?></a><?php elseif ($step['url'] !== ''): ?><a href="<?= $h($step['url']) ?>" translate="no"><?= $h($step['title']) ?></a><?php else: ?><b translate="no"><?= $h($step['title']) ?></b><?php endif ?>
                            <?php if ($step['done']): ?> <span class="acx-state acx-state--held">Acquis</span><?php endif ?>
                            <?php if ($step['text'] !== ''): ?><p translate="no"><?= $h($step['text']) ?></p><?php endif ?>
                        </div>
                    </li>
                <?php endforeach ?>
                </ol>
            </section>
            <?php elseif ($it['kind'] === 'competency'): ?>
            <section class="acx-panel" aria-labelledby="acx-links-title">
                <h2 id="acx-links-title">Pour acquérir cette compétence</h2>
                <?php if (($it['links'] ?? []) === []): ?><p>Aucune formation ni qualification n’est encore liée à cette compétence. Le générateur de parcours peut proposer des étapes.</p>
                <?php else: ?><ul class="acx-links"><?php foreach ($it['links'] as $l): ?><li class="<?= $l['done'] ? 'is-done' : '' ?>"><?php if ($l['detail'] !== ''): ?><a href="<?= $h($oxFiche($l['detail'])) ?>" translate="no"><?= $h($l['title']) ?></a><?php elseif ($l['url'] !== ''): ?><a href="<?= $h($l['url']) ?>" translate="no"><?= $h($l['title']) ?></a><?php else: ?><span translate="no"><?= $h($l['title']) ?></span><?php endif ?><?php if ($l['done']): ?> <span class="acx-state acx-state--held">Acquis</span><?php endif ?></li><?php endforeach ?></ul><?php endif ?>
            </section>
            <?php elseif ($it['kind'] === 'issuer'):
                $issuerQuals = array_values(array_filter($offerItems, static fn (array $q): bool => $q['kind'] === 'qualification' && (int) $q['issuer_id'] === (int) $it['issuer_id'])); ?>
            <section class="acx-panel" aria-labelledby="acx-iq-title">
                <h2 id="acx-iq-title">Qualifications délivrées <b class="acx-count"><?= count($issuerQuals) ?></b></h2>
                <?php if ($issuerQuals === []): ?><p>Aucune qualification visible n’est rattachée à cet émetteur.</p><?php else: ?>
                <div class="acx-grid acx-grid--tight"><?php $detailIt = $it; foreach ($issuerQuals as $it) { require __DIR__ . '/offer_card.php'; } $it = $detailIt; ?></div><?php endif ?>
            </section>
            <?php endif ?>
        </div>

        <?php if ($it['kind'] !== 'issuer'): ?>
        <aside class="acx-detail__side">
            <section class="acx-panel acx-panel--me" aria-labelledby="acx-me-title">
                <h2 id="acx-me-title">Votre situation</h2>
                <p class="acx-me-state"><?= $oxStatePill($it) ?: '<span class="acx-state">Aucun suivi pour cet élément</span>' ?></p>
                <?php if ($award !== null): ?>
                <dl class="acx-meta acx-meta--stack">
                    <?php if ($oxDate($award['obtained_at'] ?? '') !== ''): ?><div><dt>Obtenue le</dt><dd><?= $h($oxDate($award['obtained_at'])) ?></dd></div><?php endif ?>
                    <div><dt>Valable jusqu’au</dt><dd><?= $oxDate($award['expires_at'] ?? '') !== '' ? $h($oxDate($award['expires_at'])) : '<span>Sans limite</span>' ?></dd></div>
                    <?php if (($it['award_issuer'] ?? '') !== ''): ?><div><dt>Délivrée par</dt><dd translate="no"><?= $h($it['award_issuer']) ?></dd></div><?php endif ?>
                    <?php if (trim((string) ($award['certificate_number'] ?? '')) !== ''): ?><div><dt>Numéro de brevet</dt><dd translate="no"><?= $h($award['certificate_number']) ?></dd></div><?php endif ?>
                </dl>
                <?php endif ?>
                <?php if ($it['next'] !== null): ?><p class="acx-next"><span>Prochaine étape :</span> <span>chapitre</span> <?= (int) $it['next']['chapter'] ?> · <span><?= $h($t($it['next']['stage'])) ?></span></p><?php endif ?>
                <?php $actionsInDetail = true; require __DIR__ . '/offer_actions.php'; $actionsInDetail = false; ?>
            </section>
            <?php if ($facts !== []): ?>
            <section class="acx-panel" aria-labelledby="acx-facts-title">
                <h2 id="acx-facts-title">En bref</h2>
                <dl class="acx-meta acx-meta--stack"><?php foreach ($facts as [$label, $value]): ?><div><dt><?= $h($label) ?></dt><dd><?= $value ?></dd></div><?php endforeach ?></dl>
            </section>
            <?php endif ?>
        </aside>
        <?php endif ?>
    </div>
</article>
