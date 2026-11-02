<?php
declare(strict_types=1);

use App\Support\PilotFleet;
use App\Support\PilotSyllabusCatalog as Cat;

/**
 * Cursus pilote > Appareils et familles : appareils de la communauté (nom, classes Arma, famille, filière, image),
 * réglage des appareils reconnus et relevés, familles propres. Inclus par pilot_syllabus.php (mêmes variables).
 */
$fleetAircraft = is_array($fleetAircraft ?? null) ? $fleetAircraft : [];
$fleetGroups = is_array($fleetGroups ?? null) ? $fleetGroups : PilotFleet::DEFAULT_GROUPS;
$fleetActiveGroups = is_array($fleetActiveGroups ?? null) ? $fleetActiveGroups : PilotFleet::DEFAULT_GROUPS;
$fleetCustomGroups = is_array($fleetCustomGroups ?? null) ? $fleetCustomGroups : [];
$fleetArchivedGroups = is_array($fleetArchivedGroups ?? null) ? $fleetArchivedGroups : [];
$fleetRows = is_array($fleetRows ?? null) ? $fleetRows : [];
$fleetClasses = is_array($fleetClasses ?? null) ? $fleetClasses : [];
$acTracks = [Cat::TRACK_HELI => 'Hélicoptère', Cat::TRACK_PLANE => 'Avion', PilotFleet::TRACK_OTHER => 'Autre (drone, mixte…)'];
$groupCount = [];
foreach ($fleetAircraft as $a) {
    $groupCount[$a['group']] = ($groupCount[$a['group']] ?? 0) + 1;
}
$classesOf = static function (string $key) use ($fleetClasses): string {
    return implode("\n", array_keys(array_filter($fleetClasses, static fn (string $k): bool => $k === $key)));
};

/** @param array<string, mixed>|null $a */
$acForm = static function (?array $a) use ($h, $base, $acTracks, $fleetActiveGroups, $fleetRows, $classesOf, $allowed): void {
    $key = (string) ($a['key'] ?? '');
    $row = $fleetRows[$key] ?? null;
    $custom = $a === null || !empty($a['custom']);
    $isClass = str_starts_with($key, 'class:');
    ?>
    <form method="post" action="<?= $h(url($base . '/appareils')) ?>" enctype="multipart/form-data" class="pcs-sform pcs-acform">
        <?= \App\Core\Csrf::field() ?>
        <input type="hidden" name="aircraft_key" value="<?= $h($key) ?>">
        <div class="pcs-sform__row">
            <label class="pcs-sform__wide">Nom affiché
                <input type="text" name="name" maxlength="120" required value="<?= $h((string) ($a['label'] ?? '')) ?>" placeholder="Ex. : NH90 Caïman">
            </label>
            <label>Filière
                <select name="track">
                    <?php if (!$custom): ?><option value="">Déduite (<?= $h($acTracks[(string) ($a['track'] ?? '')] ?? '') ?>)</option><?php endif; ?>
                    <?php foreach ($acTracks as $tk => $tl): ?><option value="<?= $h($tk) ?>"<?= (string) ($row['track'] ?? ($custom ? ($a['track'] ?? '') : '')) === $tk ? ' selected' : '' ?>><?= $h($tl) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Famille
                <select name="group_key">
                    <?php if (!$custom): ?><option value="">Déduite (<?= $h($fleetActiveGroups[(string) ($a['group'] ?? '')] ?? (PilotFleet::DEFAULT_GROUPS[(string) ($a['group'] ?? '')] ?? '')) ?>)</option><?php endif; ?>
                    <?php foreach ($fleetActiveGroups as $gk => $gl): ?><option value="<?= $h($gk) ?>"<?= (string) ($row['group_key'] ?? ($custom ? ($a['group'] ?? '') : '')) === $gk ? ' selected' : '' ?>><?= $h($gl) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <?php if (!$isClass): ?>
        <label class="pcs-sform__line">Classes Arma rattachées <small>(une par ligne : variantes, versions de mods…)</small>
            <textarea name="classes" rows="3" translate="no" placeholder="RHS_UH60M&#10;RHS_UH60M_ESSS"><?= $h($classesOf($key)) ?></textarea>
            <small><?= $custom ? 'Leurs heures comptent pour cet appareil, en plus de l’appareil reconnu par le site : les cursus déjà installés gardent leurs heures.' : 'Ajoutez ici des classes que le site ne range pas encore avec cet appareil.' ?></small>
        </label>
        <?php endif; ?>
        <div class="pcs-sform__row">
            <label>Image <small>(PNG ou WebP, 2 Mo au plus)</small>
                <input type="file" name="image" accept="image/png,image/webp">
            </label>
            <?php if (trim((string) ($row['image_path'] ?? '')) !== ''): ?>
            <label class="pcs-check"><input type="checkbox" name="remove_image" value="1"> Retirer l’image</label>
            <?php endif; ?>
            <?php if ($custom && $a !== null): ?>
            <label class="pcs-check"><input type="checkbox" name="archived" value="1"<?= !empty($a['archived']) ? ' checked' : '' ?>> Archivé (n’est plus proposé)</label>
            <?php endif; ?>
        </div>
        <div class="pcs-sform__foot">
            <button type="submit" class="ath-btn ath-btn--solid"<?= $allowed ? '' : ' disabled' ?>><?= $a === null ? 'Créer l’appareil' : 'Enregistrer' ?></button>
        </div>
    </form>
    <?php
};
?>
    <section class="pcs-fleetadm ath-card" id="flotte" aria-labelledby="pcs-fleet-title">
        <header class="pcs-sec__head">
            <h2 id="pcs-fleet-title">Appareils et familles</h2>
            <p>Les appareils pilotés sont reconnus d’après leur classe Arma (UH-60, AH-64, C-130…) et rangés dans une famille. Renommez-les, rangez-les ailleurs, rattachez-leur d’autres classes, ou créez vos propres appareils et familles.</p>
        </header>
        <?php if (empty($fleetReady)): ?>
        <p class="pcs-notice pcs-notice--warn" role="status">Les appareils et familles de la communauté arrivent avec la prochaine mise à jour du site : l’administrateur doit lancer la migration « pilot_fleet_and_steps ». Les appareils pilotés restent proposés dans « Installer un cursus ».</p>
        <?php else: ?>
        <div class="pcs-fleetadm__cols">
            <div class="pcs-fleetadm__list">
                <h3 class="pcs-ladder__title">Appareils <span class="rql__count"><?= count($fleetAircraft) ?></span></h3>
                <?php if ($fleetAircraft === []): ?>
                <p class="pcs-empty">Aucun appareil pour l’instant.</p>
                <?php endif; ?>
                <ul class="pcs-aclist">
                    <?php foreach ($fleetAircraft as $a): ?>
                    <li class="pcs-aclist__row<?= $a['archived'] ? ' is-off' : '' ?>" id="appareil-<?= $h(str_replace(':', '-', $a['key'])) ?>">
                        <details>
                            <summary>
                                <span class="pcs-aclist__img" aria-hidden="true"><?php if ($a['image'] !== ''): ?><img src="<?= $h($a['image']) ?>" alt="" loading="lazy"><?php else: ?><?= $a['track'] === Cat::TRACK_PLANE ? '✈' : '◉' ?><?php endif; ?></span>
                                <span class="pcs-aclist__main">
                                    <b><?= $h($a['label']) ?></b>
                                    <small><?= $h($fleetGroups[$a['group']] ?? $a['group']) ?> · <?= $h($acTracks[$a['track']] ?? '') ?><?php if ($a['flown']): ?> · <?= $h($hours((float) $a['hours'])) ?><?php endif; ?></small>
                                </span>
                                <span class="pcs-aclist__tags">
                                    <?php if ($a['custom']): ?><em class="pcs-tag">Communauté</em><?php elseif ($a['configured']): ?><em class="pcs-tag">Réglé</em><?php endif; ?>
                                    <?php if ($a['archived']): ?><em class="pcs-tag">Archivé</em><?php endif; ?>
                                </span>
                            </summary>
                            <?php if ($a['classes'] !== []): ?>
                            <p class="pcs-aclist__cls">Classes vues en jeu : <span translate="no"><?= $h(implode(', ', array_column($a['classes'], 'class'))) ?></span></p>
                            <?php endif; ?>
                            <?php $acForm($a); ?>
                        </details>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="pcs-fleetadm__side">
                <details class="pcs-install__track" open>
                    <summary>Créer un appareil</summary>
                    <div class="pcs-install__form"><?php $acForm(null); ?></div>
                </details>
                <details class="pcs-install__track" open>
                    <summary>Familles d’appareils</summary>
                    <div class="pcs-install__form">
                        <ul class="pcs-grplist">
                            <?php $gi = 0; foreach ($fleetGroups as $gk => $gl): $gi++; $gArchived = in_array($gk, $fleetArchivedGroups, true); ?>
                            <li class="<?= $gArchived ? 'is-off' : '' ?>">
                                <form method="post" action="<?= $h(url($base . '/familles')) ?>" class="pcs-grplist__form">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="group_key" value="<?= $h((string) $gk) ?>">
                                    <input type="text" name="name" maxlength="80" required value="<?= $h($gl) ?>" aria-label="Nom de la famille">
                                    <input type="number" name="sort_order" min="-999" max="9999" step="1" value="<?= $gi * 10 ?>" aria-label="Ordre" title="Ordre">
                                    <label class="pcs-check" title="Archivée"><input type="checkbox" name="archived" value="1"<?= $gArchived ? ' checked' : '' ?>> Archivée</label>
                                    <span class="pcs-grplist__n"><?= (int) ($groupCount[$gk] ?? 0) ?></span>
                                    <button type="submit" class="ath-btn ath-btn--sm"<?= $allowed ? '' : ' disabled' ?>>OK</button>
                                </form>
                                <?php if (in_array($gk, $fleetCustomGroups, true)): ?><em class="pcs-tag">Communauté</em><?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <form method="post" action="<?= $h(url($base . '/familles')) ?>" class="pcs-grplist__form pcs-grplist__new">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="text" name="name" maxlength="80" required placeholder="Nouvelle famille (ex. : EVASAN)" aria-label="Nouvelle famille">
                            <button type="submit" class="ath-btn ath-btn--solid ath-btn--sm"<?= $allowed ? '' : ' disabled' ?>>Ajouter</button>
                        </form>
                        <small class="pcs-sform__hint">Une famille archivée n’est plus proposée ; ses appareils retombent dans la famille déduite de leur classe.</small>
                    </div>
                </details>
            </div>
        </div>
        <?php endif; ?>
    </section>
