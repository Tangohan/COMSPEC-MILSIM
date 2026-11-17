<?php
declare(strict_types=1);

/**
 * Fiche d'un membre (JnetPortalController::personnelShow), lecture seule : identité, affectation avec son fil hiérarchique,
 * profil et qualifications, tels que le dossier les porte. Le dossier complet reste derrière son propre lien.
 *
 * @var array<string, mixed> $person
 */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$p = is_array($person ?? null) ? $person : [];
$photo = $p['photo'] ?? null;
$initials = (string) ($p['initials'] ?? '?');
$isOff = ($p['duty'] ?? '') === 'off';
$fil = is_array($p['fil'] ?? null) ? $p['fil'] : null;
$chain = $fil !== null ? implode(' › ', array_map('strval', (array) $fil['chain'])) : '';
$unit = trim((string) ($p['unit'] ?? ''));
$callsign = trim((string) ($p['callsign'] ?? ''));
$grade = trim((string) ($p['grade'] ?? ''));
$fn = trim((string) ($p['function'] ?? ''));
$profileFacts = is_array($p['profileFacts'] ?? null) ? $p['profileFacts'] : [];
$qualifications = is_array($p['qualifications'] ?? null) ? $p['qualifications'] : [];
$dossierHref = (string) ($p['dossierHref'] ?? '');
?>
<article class="pf" aria-labelledby="pf-name">
    <header class="pf-hero">
        <div class="pf-photo" aria-hidden="true">
            <?php if (is_string($photo) && $photo !== ''): ?>
                <img src="<?= $h($photo) ?>" alt="">
            <?php else: ?>
                <span><?= $h($initials) ?></span>
            <?php endif; ?>
        </div>
        <div>
            <h1 id="pf-name"><?= $h((string) ($p['name'] ?? '')) ?></h1>
            <?php if (trim((string) ($p['jnet_id'] ?? '')) !== ''): ?>
                <p class="jnet-kicker"><?= $h((string) $p['jnet_id']) ?></p>
            <?php endif; ?>
            <p class="pf-sub">
                <?php if ($grade !== ''): ?><span><?= $h($grade) ?></span><?php endif; ?>
                <?php if ($fn !== ''): ?><span><?= $h($fn) ?></span><?php endif; ?>
                <span class="pe-st pe-st--<?= $isOff ? 'off' : 'on' ?>"><?= $isOff ? 'Indisponible' : 'En service' ?></span>
            </p>
            <div class="pf-actions">
                <?php if ($dossierHref !== ''): ?>
                    <a class="ja-b1 sm" href="<?= $h($dossierHref) ?>">Dossier complet</a>
                <?php endif; ?>
                <a class="ja-b2 sm" href="<?= $h(url('jnet/personnel')) ?>">Retour à l’annuaire</a>
            </div>
        </div>
    </header>

    <div class="pf-grid">
        <section class="pf-sec" aria-labelledby="pf-aff">
            <h2 id="pf-aff">Affectation</h2>
            <dl class="pf-dl">
                <?php if ($callsign !== ''): ?><div><dt>Indicatif</dt><dd class="ja-mono"><?= $h($callsign) ?></dd></div><?php endif; ?>
                <?php if ($grade !== ''): ?><div><dt>Grade</dt><dd><?= $h($grade) ?></dd></div><?php endif; ?>
                <?php if ($fn !== ''): ?><div><dt>Fonction</dt><dd><?= $h($fn) ?></dd></div><?php endif; ?>
                <?php if (trim((string) ($p['specialties'] ?? '')) !== ''): ?><div><dt>Spécialités confirmées</dt><dd><?= $h($p['specialties']) ?><small>Le niveau indique la maîtrise du métier ; il se distingue du grade, du poste et des qualifications.</small></dd></div><?php endif; ?>
                <?php if ($unit !== ''): ?>
                    <div><dt>Unité</dt><dd>
                        <?php if ($fil !== null): ?><a href="<?= $h((string) $fil['href']) ?>"><?= $h($unit) ?></a><?php else: ?><?= $h($unit) ?><?php endif; ?>
                        <?php if ($chain !== ''): ?><small><?= $h($chain) ?></small><?php endif; ?>
                    </dd></div>
                <?php endif; ?>
                <div><dt>Situation</dt><dd><?= $h((string) ($p['duty_label'] ?? '')) ?></dd></div>
            </dl>
        </section>

        <?php if ($profileFacts !== []): ?>
            <section class="pf-sec" aria-labelledby="pf-profil">
                <h2 id="pf-profil">Profil</h2>
                <dl class="pf-dl">
                    <?php foreach ($profileFacts as $fact): ?>
                        <div><dt><?= $h((string) ($fact['label'] ?? '')) ?></dt><dd><?= $h((string) ($fact['value'] ?? '')) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endif; ?>
    </div>

    <section class="pf-sec" aria-labelledby="pf-qual">
        <h2 id="pf-qual">Qualifications</h2>
        <?php if ($qualifications === []): ?>
            <p class="pf-empty">Aucune qualification enregistrée sur ce dossier.</p>
        <?php else: ?>
            <ul class="pf-quals">
                <?php foreach ($qualifications as $q): ?>
                    <?php
                    $label = is_array($q) ? (string) ($q['name'] ?? $q['label'] ?? '') : (string) $q;
                    $status = is_array($q) ? trim((string) ($q['status'] ?? '')) : '';
                    $expires = is_array($q) ? trim((string) ($q['expires'] ?? '')) : '';
                    ?>
                    <li><span><?= $h($label) ?></span><?php if ($status !== '' || $expires !== ''): ?><small><?php if ($status !== ''): ?><?= $h($status) ?><?php endif; ?><?php if ($status !== '' && $expires !== ''): ?> · <?php endif; ?><?php if ($expires !== ''): ?><span>jusqu’au <?= $h($expires) ?></span><?php endif; ?></small><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</article>
