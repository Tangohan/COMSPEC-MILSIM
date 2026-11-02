<?php
declare(strict_types=1);

use App\Support\PilotFleet;
use App\Support\PilotSyllabusCatalog as Cat;

/**
 * Cursus pilote > Installer un cursus : appareils réellement pilotés dans la communauté (relevés par le mod),
 * regroupés par famille, avec recherche et filtres, appareils jamais pilotés et ajout à la main.
 * Inclus par pilot_syllabus.php (mêmes variables).
 */
$installedKeys = [];
foreach ($rules as $r) {
    if (str_starts_with((string) $r['scope'], 'family:')) {
        $installedKeys[(string) $r['track']][substr((string) $r['scope'], 7)] = true;
    }
}
$fleetAircraft = is_array($fleetAircraft ?? null) ? $fleetAircraft : [];
$fleetActiveGroups = is_array($fleetActiveGroups ?? null) ? $fleetActiveGroups : PilotFleet::DEFAULT_GROUPS;
$fleetGroups = is_array($fleetGroups ?? null) ? $fleetGroups : PilotFleet::DEFAULT_GROUPS;
$flownCount = count(array_filter($fleetAircraft, static fn (array $a): bool => $a['flown']));
$day = static fn (string $at): string => $at !== '' ? date('d/m/Y', strtotime($at) ?: time()) : '';
?>
    <section class="pcs-install ath-card" id="installer" aria-labelledby="pcs-install-title">
        <header class="pcs-sec__head">
            <h2 id="pcs-install-title"><?= $rules === [] ? 'Installer un cursus' : 'Ajouter des paliers ou des appareils' ?></h2>
            <p>Cochez les appareils de votre cursus : ceux que vos pilotes ont réellement pilotés dans Arma 3 sont relevés par le mod et proposés en premier, avec leurs heures. Les qualifications sont créées dans votre référentiel (catégorie « Pilotage »), avec leurs prérequis ; celles déjà installées sont reprises sans doublon. Les seuils restent modifiables ensuite.</p>
        </header>
        <?php if ($flownCount === 0): ?>
        <p class="pcs-empty">Aucun vol relevé pour l’instant : les appareils apparaîtront ici dès qu’un membre aura piloté en jeu avec le mod ATAK. Vous pouvez déjà installer un cursus avec les appareils connus ou ajouter les vôtres à la main.</p>
        <?php endif; ?>
        <?php foreach ($tracks as $trackKey => $trackInfo):
            $here = array_values(array_filter($fleetAircraft, static fn (array $a): bool => !$a['archived'] && ($a['track'] === $trackKey || $a['track'] === PilotFleet::TRACK_OTHER)));
            $flownHere = count(array_filter($here, static fn (array $a): bool => $a['flown']));
            $byGroup = [];
            foreach ($here as $a) {
                $byGroup[$a['group']][] = $a;
            }
            $orderedGroups = array_values(array_unique(array_merge(array_keys($fleetGroups), array_keys($byGroup))));
            ?>
        <details class="pcs-install__track"<?= $trackKey === Cat::TRACK_HELI ? ' open' : '' ?>>
            <summary>Filière <?= $h(mb_strtolower($trackInfo['label'])) ?><?php if ($flownHere > 0): ?> · <span class="pcs-install__sum"><?= $flownHere ?> appareil(s) piloté(s) dans la communauté</span><?php endif; ?></summary>
            <form method="post" action="<?= $h(url($base . '/installer')) ?>" class="pcs-install__form">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="track" value="<?= $h($trackKey) ?>">
                <fieldset class="pcs-fleet" data-pcs-fleet>
                    <legend>Appareils (qualification de type, pilote opérationnel, commandant de bord, instructeur)</legend>
                    <div class="pcs-fleet__bar" role="group" aria-label="Chercher un appareil">
                        <label class="pcs-fleet__search">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input type="search" data-pcs-q placeholder="Chercher un nom ou une classe Arma" aria-label="Chercher un nom ou une classe Arma">
                        </label>
                        <select data-pcs-group aria-label="Famille">
                            <option value="">Toutes les familles</option>
                            <?php foreach ($fleetActiveGroups as $gk => $gl): if (empty($byGroup[$gk])) { continue; } ?>
                            <option value="<?= $h($gk) ?>"><?= $h($gl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="pcs-check pcs-fleet__flown"><input type="checkbox" data-pcs-flown<?= $flownHere > 0 ? '' : ' disabled' ?>> Pilotés seulement</label>
                    </div>
                    <?php foreach ($orderedGroups as $gk):
                        if (empty($byGroup[$gk])) {
                            continue;
                        }
                        $list = $byGroup[$gk];
                        ?>
                    <div class="pcs-fleet__grp" data-pcs-grp="<?= $h((string) $gk) ?>">
                        <div class="pcs-fleet__grph">
                            <h3><?= $h($fleetGroups[$gk] ?? (PilotFleet::DEFAULT_GROUPS[$gk] ?? (string) $gk)) ?> <span class="rql__count"><?= count($list) ?></span></h3>
                            <span class="pcs-fleet__grpacts">
                                <button type="button" class="pcs-link" data-pcs-check="all">Tout cocher</button>
                                <button type="button" class="pcs-link" data-pcs-check="none">Tout décocher</button>
                            </span>
                        </div>
                        <div class="pcs-fleet__grid">
                            <?php foreach ($list as $a):
                                $installed = isset($installedKeys[$trackKey][$a['key']]);
                                $search = mb_strtolower($a['label'] . ' ' . $a['key'] . ' ' . implode(' ', array_map(static fn (array $c): string => $c['class'] . ' ' . $c['label'], $a['classes'])));
                                ?>
                            <label class="pcs-ac<?= $a['flown'] ? ' is-flown' : '' ?>" data-pcs-ac data-search="<?= $h($search) ?>" data-group="<?= $h($a['group']) ?>" data-flown="<?= $a['flown'] ? '1' : '0' ?>">
                                <input type="checkbox" name="families[]" value="<?= $h($a['key']) ?>"<?= $a['flown'] && !$installed ? ' checked' : '' ?>>
                                <span class="pcs-ac__img" aria-hidden="true">
                                    <?php if ($a['image'] !== ''): ?><img src="<?= $h($a['image']) ?>" alt="" loading="lazy"><?php else: ?>
                                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?php if ($a['track'] === Cat::TRACK_PLANE): ?><path d="M12 3v7l8 4v2l-8-2v4l3 2v1l-3-1-3 1v-1l3-2v-4l-8 2v-2l8-4V3"/><?php else: ?><path d="M3 6h18M12 6v3"/><rect x="6" y="9" width="10" height="6" rx="3"/><path d="M16 12h5M8 15l-1 3h9l-1-3"/><?php endif; ?></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="pcs-ac__body">
                                    <span class="pcs-ac__name"><?= $h($a['label']) ?><?php if ($a['custom']): ?> <em class="pcs-tag">Communauté</em><?php endif; ?><?php if ($installed): ?> <em class="pcs-tag is-ok">Déjà au cursus</em><?php endif; ?></span>
                                    <?php if ($a['flown']): ?>
                                    <span class="pcs-ac__meta"><b><?= $h($hours((float) $a['hours'])) ?></b> · <?= (int) $a['pilots'] ?> pilote<?= $a['pilots'] > 1 ? 's' : '' ?> · dernier vol le <?= $h($day($a['last_flown_at'])) ?></span>
                                    <?php else: ?>
                                    <span class="pcs-ac__meta">Jamais piloté dans la communauté</span>
                                    <?php endif; ?>
                                    <?php if ($a['classes'] !== []): ?>
                                    <span class="pcs-ac__cls" translate="no"><?= $h(implode(', ', array_slice(array_column($a['classes'], 'class'), 0, 4))) ?><?= count($a['classes']) > 4 ? ' +' . (count($a['classes']) - 4) : '' ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if (str_starts_with($a['key'], 'class:')): ?><input type="hidden" name="family_labels[<?= $h($a['key']) ?>]" value="<?= $h($a['label']) ?>"><?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <p class="pcs-empty" data-pcs-none hidden>Aucun appareil ne correspond à cette recherche.</p>
                    <?php if (!empty($fleetReady)): ?>
                    <div class="pcs-fleet__add">
                        <p class="pcs-fleet__addh"><b>Appareil absent de la liste ?</b> Ajoutez-le avec sa classe Arma (visible dans l’éditeur ou la config du mod) : ses heures compteront dès le premier vol.</p>
                        <label>Nom affiché <input type="text" name="new_name" maxlength="120" placeholder="Ex. : Tigre HAD"></label>
                        <label>Classe Arma <input type="text" name="new_class" maxlength="80" placeholder="Ex. : UK3CB_BAF_Tigre" translate="no" pattern="[A-Za-z0-9_]{1,80}"></label>
                        <label>Famille
                            <select name="new_group">
                                <option value="">Déduite de la classe</option>
                                <?php foreach ($fleetActiveGroups as $gk => $gl): ?><option value="<?= $h($gk) ?>"><?= $h($gl) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <?php else: ?>
                    <p class="pcs-install__foot">L’ajout d’appareils à la main arrive avec la prochaine mise à jour du site (migration « pilot_fleet_and_steps »).</p>
                    <?php endif; ?>
                </fieldset>
                <fieldset>
                    <legend>Paliers du cursus modèle</legend>
                    <div class="pcs-tiers">
                        <?php foreach ($tiers as $tKey => $t): ?>
                        <label class="pcs-tier">
                            <input type="checkbox" name="tiers[]" value="<?= $h($tKey) ?>" checked>
                            <span class="pcs-tier__body">
                                <span class="pcs-tier__name"><b><?= $h($t['short']) ?></b> <?= $h($t['label']) ?> <em><?= $t['mode'] === Cat::MODE_DIRECT ? 'automatique' : 'instructeur' ?></em></span>
                                <span class="pcs-tier__game">Jeu : <?= $h($hours((float) $t['min_scope_hours'])) ?> <?= $t['per'] === Cat::PER_FAMILY ? 'sur le type' : 'en ' . $h($trackInfo['noun']) ?><?= (float) $t['min_track_hours'] > 0 ? ', ' . $h($hours((float) $t['min_track_hours'])) . ' en ' . $h($trackInfo['noun']) : '' ?><?= $t['cond'] !== null ? ', dont ' . $h($hours((float) $t['min_cond_hours'])) . ' ' . $h(Cat::conditions()[$t['cond']] ?? '') . ' au carnet' : '' ?><?= $t['currency_days'] ? ' · à jour ' . (int) $t['currency_days'] . ' jours après le dernier vol' : '' ?></span>
                                <span class="pcs-tier__real">Vraie vie : <?= $h((string) ($t['real'][$trackKey] ?? '')) ?></span>
                                <?php if ($t['data_note'] !== ''): ?><span class="pcs-tier__note"><?= $h($t['data_note']) ?></span><?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="pcs-install__foot">Vous voulez d’autres paliers que ce modèle ? Créez les vôtres dans « Paliers de la communauté » ci-dessus.</p>
                </fieldset>
                <button type="submit" class="ath-btn ath-btn--solid"<?= $allowed && $schemaReady ? '' : ' disabled' ?>>Installer la filière <?= $h(mb_strtolower($trackInfo['label'])) ?></button>
            </form>
        </details>
        <?php endforeach; ?>
        <p class="pcs-install__foot">Les seuils du jeu sont ramenés à l’échelle d’une communauté qui vole quelques heures par semaine : les minimums réels se comptent en dizaines ou centaines d’heures. Les références (EASA Part-FCL, FAA 14 CFR Part 61, US Army TC 3-04.11) sont des repères publics, pas une certification.</p>
    </section>
