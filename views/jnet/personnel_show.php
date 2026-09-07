<?php
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$p = is_array($person ?? null) ? $person : [];
$photo = $p['photo'] ?? null;
$initials = (string) ($p['initials'] ?? '?');
$facts = [
    ['Indicatif', (string) ($p['callsign'] ?? '')],
    ['Unité', (string) ($p['unit'] ?? '')],
    ['Fonction', (string) ($p['function'] ?? '')],
    ['Situation', (string) ($p['duty_label'] ?? '')],
    ['Grade', (string) ($p['grade'] ?? '')],
];
$profileFacts = is_array($p['profileFacts'] ?? null) ? $p['profileFacts'] : [];
$qualifications = is_array($p['qualifications'] ?? null) ? $p['qualifications'] : [];
$dossierHref = (string) ($p['dossierHref'] ?? '');
?>
<article class="jnet-panel jnet-record">
    <div class="jnet-panel__head">
        <h2>Fiche personnel</h2>
        <div class="jnet-mail__actions">
            <?php if ($dossierHref !== ''): ?>
                <a class="jnet-btn" href="<?= $h($dossierHref) ?>">Dossier complet</a>
            <?php endif; ?>
            <a class="jnet-btn" href="<?= $h(url('jnet/personnel')) ?>">Retour à l’annuaire</a>
        </div>
    </div>
    <div class="jnet-panel__body">
        <div class="jnet-record__hero">
            <div class="jnet-avatar jnet-avatar--hero">
                <?php if (is_string($photo) && $photo !== ''): ?>
                    <img src="<?= $h($photo) ?>" alt="">
                <?php else: ?>
                    <span><?= $h($initials) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <?php if (trim((string) ($p['jnet_id'] ?? '')) !== ''): ?>
                    <p class="jnet-kicker"><?= $h((string) $p['jnet_id']) ?></p>
                <?php endif; ?>
                <h1><?= $h((string) ($p['name'] ?? '')) ?></h1>
                <div class="jnet-record__grid">
                    <?php foreach ($facts as [$label, $value]): ?>
                        <?php if (trim($value) === ''): continue; endif; ?>
                        <div><span><?= $h($label) ?></span><strong><?= $h($value) ?></strong></div>
                    <?php endforeach; ?>
                    <?php foreach ($profileFacts as $fact): ?>
                        <div><span><?= $h((string) ($fact['label'] ?? '')) ?></span><strong><?= $h((string) ($fact['value'] ?? '')) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <section class="jnet-section">
            <h3>Qualifications</h3>
            <?php if ($qualifications === []): ?>
                <p class="jnet-empty">Aucune qualification enregistrée sur ce dossier.</p>
            <?php else: ?>
                <div class="jnet-tags">
                    <?php foreach ($qualifications as $q): ?>
                        <?php
                        $label = is_array($q) ? (string) ($q['name'] ?? $q['label'] ?? '') : (string) $q;
                        $extra = is_array($q) ? trim(implode(' · ', array_filter([(string) ($q['status'] ?? ''), (string) ($q['expires'] ?? '')]))) : '';
                        ?>
                        <span><?= $h($label) ?><?= $extra !== '' ? ' — ' . $h($extra) : '' ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</article>
