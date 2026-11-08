<?php
declare(strict_types=1);
/**
 * Assistant de création de tenue (/equipment/assistant).
 *
 * Étape 1 : choisir un emploi de la communauté (le profil en est déduit, modifiable). Étape 2 : ajuster la
 * proposition emplacement par emplacement, avec uniquement des objets que la communauté a remontés du jeu. Étape 3 :
 * aperçu et enregistrement (tenue personnelle, ou kit de départ pour un responsable). Sans objets remontés, la page
 * explique comment en obtenir ; sans la formule, elle présente l'offre. Formulaires de choix en GET (adresse
 * partageable), enregistrement en POST ; aucun JavaScript requis.
 *
 * Noms d'objets, d'emplois et d'unités viennent du jeu ou des membres : échappés par $h() et marqués translate="no".
 *
 * @var bool $assistantLocked
 * @var int $catalogCount
 * @var list<array{id: int, name: string, abbreviation: string, category: string}> $roleChoices
 * @var array<string, string> $profileOptions
 * @var int $selectedRole
 * @var string $profile
 * @var array<string, mixed>|null $proposal
 * @var list<array{title: string, items: list<array<string, mixed>>}> $preview
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$assistantLocked = !empty($assistantLocked);
$catalogCount = (int) ($catalogCount ?? 0);
$roleChoices = is_array($roleChoices ?? null) ? $roleChoices : [];
$profileOptions = is_array($profileOptions ?? null) ? $profileOptions : [];
$selectedRole = (int) ($selectedRole ?? 0);
$profile = (string) ($profile ?? '');
$proposal = is_array($proposal ?? null) ? $proposal : null;
$preview = is_array($preview ?? null) ? $preview : [];
$canManageKits = !empty($canManageKits);
$kitUnitOptions = is_array($unitOptions ?? null) ? $unitOptions : [];
$kitRoleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
$kitUnitsReady = !empty($unitsReady);
$csrfToken = (string) ($csrfToken ?? \App\Core\Csrf::token());
$upgradeUrl = (string) ($upgradeUrl ?? url('platform/upgrade'));
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$suggestedName = (string) ($suggestedName ?? '');
$wantsKit = !empty($wantsKit);
$detected = !empty($detectedFromRole);
$needLabel = static fn (string $need): string => match ($need) {
    'required' => 'Indispensable',
    'recommended' => 'Conseillé',
    default => 'Facultatif',
};
$groupLabels = \App\Services\GameEquipment\OutfitAssistant::GROUPS;
$variantCount = max(1, (int) ($variantCount ?? 1));
$variantsTruncated = !empty($variantsTruncated);
// Objets proposés par emplacement, lus par assets/js/equipment-assistant.js (fenêtre de choix). Noms d'objets : texte du jeu,
// ajoutés à la page par textContent côté script, jamais comme HTML.
$pickerData = ['max' => \App\Services\GameEquipment\OutfitAssistant::MAX_VARIANTS, 'slots' => [], 'role' => [
    'chosen' => $selectedRole > 0 ? [(string) $selectedRole] : [],
    'items' => array_map(static fn (array $rc): array => ['c' => (string) $rc['id'], 'n' => (string) $rc['name'], 'p' => 0, 'm' => (string) ($rc['category'] !== '' ? $rc['category'] : 'Autres'), 'r' => 0, 'i' => ''], $roleChoices),
]];
if ($proposal !== null) {
    foreach ($proposal['slots'] as $pickKey => $pickSlot) {
        $pickerData['slots'][(string) $pickKey] = [
            'label' => (string) $pickSlot['label'],
            'chosen' => array_values(array_map('strval', is_array($pickSlot['chosen_all'] ?? null) ? $pickSlot['chosen_all'] : ($pickSlot['chosen'] !== '' ? [$pickSlot['chosen']] : []))),
            'items' => array_map(static fn (array $c): array => [
                'c' => (string) $c['class'],
                'n' => (string) ($c['name'] !== '' ? $c['name'] : $c['class']),
                'p' => (int) $c['players'],
                'm' => (string) $c['mod'],
                'r' => !empty($c['preferred']) ? 1 : 0,
                'i' => (string) ($c['icon'] ?? ''),
            ], is_array($pickSlot['candidates'] ?? null) ? $pickSlot['candidates'] : []),
        ];
    }
}
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/job-catalog.css')) ?>">
<div class="eq-hub eqx eqx-asst" id="eq-assistant">
    <header class="eqx-hero eqx-hero--bar">
        <div class="eqx-hero__inner">
            <div class="eqx-hero__copy">
                <p class="eqx-hero__kicker"><a href="<?= $h(url('equipment')) ?>">← Équipement</a></p>
                <h1>Assistant de création de tenue</h1>
            </div>
            <p class="eqx-hero__lead">Choisissez un emploi : l’assistant propose les objets que vos joueurs ont réellement utilisés en jeu, emplacement par emplacement, et vous dit ce qui manque.</p>
        </div>
    </header>

    <div class="eq-hub__body eqx-body">
        <?php if ($flashOk !== ''): ?><p class="eq-hub__flash eq-hub__flash--ok" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="eq-hub__flash eq-hub__flash--err" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>

        <?php if ($assistantLocked): ?>
        <section class="eqx-offer" aria-labelledby="eqx-asst-offer">
            <p class="eqx-offer__tag">Formule Pro et plus</p>
            <h2 id="eqx-asst-offer">L’assistant de création est inclus à partir de la formule Pro</h2>
            <p>Il part d’un emploi (médecin, tireur d’élite, radio…), propose des objets que votre communauté a déjà utilisés en jeu pour chaque emplacement — arme, gilet, sac, radio, matériel médical — et signale ce qui manque. Le résultat s’enregistre en tenue ou en kit de départ, au format de l’arsenal ACE.</p>
            <ol class="eqx-steps">
                <li><strong>Choisir un emploi.</strong> Les emplois de votre communauté servent de point de départ.</li>
                <li><strong>Ajuster.</strong> Pour chaque emplacement, une liste d’objets déjà vus en jeu, les plus portés d’abord.</li>
                <li><strong>Enregistrer.</strong> En tenue personnelle, ou en kit proposé à une unité ou à un rôle.</li>
            </ol>
            <p class="eqx-offer__free">Reste gratuit pour tous : consulter les tenues, créer et modifier les siennes, les favoris et l’export ACE.</p>
            <div class="eqx-actions">
                <a class="eq-hub__btn" href="<?= $h($upgradeUrl) ?>">Voir les formules</a>
                <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment')) ?>">Retour à l’équipement</a>
            </div>
            <?php if (!$canManageKits): ?>
            <p class="eq-hub__hint">Pour activer cette offre, parlez-en à un responsable de votre communauté.</p>
            <?php endif; ?>
        </section>

        <?php elseif ($catalogCount === 0): ?>
        <section class="eqx-guide" aria-labelledby="eqx-asst-nodata">
            <h2 id="eqx-asst-nodata">Votre communauté n’a pas encore remonté d’objets du jeu</h2>
            <p>L’assistant ne propose que des objets que vos joueurs ont déjà portés ou envoyés depuis le jeu : il n’invente rien. Pour l’alimenter :</p>
            <ol class="eqx-steps">
                <li><strong>Lancez le jeu avec le mod Athena</strong> et connectez-vous à votre compte : l’équipement que vous portez est remonté automatiquement.</li>
                <li><strong>Envoyez vos tenues.</strong> Dans l’arsenal, ouvrez le bandeau Athena en haut de l’écran, puis choisissez « Envoyer cette » ou « Envoyer toutes » : les noms des objets de la tenue sont enregistrés.</li>
                <li><strong>Invitez quelques joueurs à faire de même.</strong> Plus il y a de joueurs, plus les listes sont fiables : les objets les plus portés arrivent en tête.</li>
                <li>Revenez ici : la liste se remplit toute seule, sans rien configurer.</li>
            </ol>
            <p class="eq-hub__hint">La synchronisation de l’équipement avec le jeu dépend de la formule de la communauté (Pro et plus). Sans elle, les objets de votre propre kit porté ne sont pas remontés.</p>
            <div class="eqx-actions">
                <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment')) ?>">Retour à l’équipement</a>
            </div>
        </section>

        <?php else: ?>
        <section class="eqx-card" aria-labelledby="eqx-asst-1">
            <header class="eqx-section__head">
                <span class="eqx-section__num" aria-hidden="true">1</span>
                <div>
                    <h2 id="eqx-asst-1">Partir d’un emploi</h2>
                    <p><?= $catalogCount ?> objets remontés du jeu par votre communauté sont disponibles.</p>
                </div>
            </header>
            <form method="get" action="<?= $h(url('equipment/assistant')) ?>" class="eqx-form eqx-form--row" data-eqp-role-form>
                <?php if ($wantsKit): ?><input type="hidden" name="cible" value="kit"><?php endif; ?>
                <div class="eqp-rolebox">
                <label>Emploi de la communauté
                    <select name="role" class="bo-select" data-eqp-role>
                        <option value="0">— Aucun emploi (je choisis un profil) —</option>
                        <?php
                        $byCategory = [];
                        foreach ($roleChoices as $rc) {
                            $byCategory[$rc['category'] !== '' ? $rc['category'] : 'Autres'][] = $rc;
                        }
                        foreach ($byCategory as $cat => $list): ?>
                        <optgroup label="<?= $h($cat) ?>">
                            <?php foreach ($list as $rc): ?>
                            <option value="<?= (int) $rc['id'] ?>"<?= (int) $rc['id'] === $selectedRole ? ' selected' : '' ?> translate="no"><?= $h($rc['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eqp-role-open aria-haspopup="dialog" hidden>Chercher un emploi…</button>
                </div>
                <label>Profil de tenue
                    <select name="profil" class="bo-select">
                        <option value="">Déduit de l’emploi</option>
                        <?php foreach ($profileOptions as $key => $label): ?>
                        <option value="<?= $h($key) ?>"<?= $key === $profile && !$detected ? ' selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="eq-hub__btn">Proposer une tenue</button>
            </form>
            <?php if ($proposal !== null): ?>
            <p class="eq-hub__hint">
                Profil retenu : <strong><?= $h((string) $proposal['profile_label']) ?></strong><?= $detected ? ' (déduit de l’emploi — changez-le ci-dessus si besoin)' : '' ?>.
                <?= $h((string) $proposal['hint']) ?>
            </p>
            <?php endif; ?>
        </section>

        <?php if ($proposal !== null): ?>
        <?php
            $missing = is_array($proposal['missing']) ? $proposal['missing'] : [];
            $warnings = is_array($proposal['warnings']) ? $proposal['warnings'] : [];
            $important = array_values(array_filter($missing, static fn (array $m): bool => $m['need'] !== 'optional'));
        ?>
        <section class="eqx-card" aria-labelledby="eqx-asst-2">
            <header class="eqx-section__head">
                <span class="eqx-section__num" aria-hidden="true">2</span>
                <div>
                    <h2 id="eqx-asst-2">Ajuster la proposition</h2>
                    <p>Pour chaque emplacement, seuls des objets déjà vus en jeu sont proposés, les plus portés d’abord. Le nombre entre parenthèses est celui des joueurs qui ont porté l’objet.</p>
                </div>
            </header>

            <?php if ($important !== [] || $warnings !== []): ?>
            <div class="eqx-missing" role="status">
                <h3>Ce qui manque</h3>
                <ul>
                    <?php foreach ($important as $m): ?>
                    <li><strong><?= $h((string) $m['label']) ?></strong> — <?= $h((string) $m['message']) ?></li>
                    <?php endforeach; ?>
                    <?php foreach ($warnings as $w): ?>
                    <li><?= $h((string) $w) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="eq-hub__hint">Pour combler un manque : faites porter ou envoyer cet objet depuis le jeu par un joueur de la communauté (arsenal, bandeau Athena, « Envoyer toutes »). Il apparaîtra ici ensuite.</p>
            </div>
            <?php endif; ?>

            <form method="get" action="<?= $h(url('equipment/assistant')) ?>#eqx-asst-2" class="eqx-form" id="eqx-asst-form" data-eqp-form>
                <?php if ($wantsKit): ?><input type="hidden" name="cible" value="kit"><?php endif; ?>
                <input type="hidden" name="role" value="<?= $selectedRole ?>">
                <input type="hidden" name="profil" value="<?= $h((string) $proposal['profile']) ?>">

                <?php foreach ($proposal['groups'] as $group => $slotKeys): ?>
                <fieldset class="eqx-group">
                    <legend><?= $h((string) ($groupLabels[$group] ?? $group)) ?></legend>
                    <div class="eqx-slots">
                        <?php foreach ($slotKeys as $slotKey): ?>
                        <?php $slot = $proposal['slots'][$slotKey]; $sid = 'eqx-slot-' . $slotKey; ?>
                        <div class="eqx-slot<?= $slot['problem'] !== '' ? ' has-problem' : '' ?>" data-eqp-slot="<?= $h($slotKey) ?>">
                            <label for="<?= $h($sid) ?>">
                                <?= $h((string) $slot['label']) ?>
                                <small class="eqx-need eqx-need--<?= $h((string) $slot['need']) ?>"><?= $h($needLabel((string) $slot['need'])) ?></small>
                            </label>
                            <select id="<?= $h($sid) ?>" name="slot[<?= $h($slotKey) ?>][]" class="bo-select" data-eqp-fallback<?= $slot['candidates'] === [] ? ' disabled' : '' ?>>
                                <option value="">— Aucun —</option>
                                <?php foreach (array_slice($slot['candidates'], 0, 60) as $cand): ?>
                                <option value="<?= $h((string) $cand['class']) ?>"<?= strcasecmp((string) $cand['class'], (string) $slot['chosen']) === 0 ? ' selected' : '' ?> translate="no"><?= $h((string) ($cand['name'] !== '' ? $cand['name'] : $cand['class'])) ?><?= (int) $cand['players'] > 0 ? ' (' . (int) $cand['players'] . ')' : '' ?><?= $cand['mod'] !== '' && strcasecmp((string) $cand['mod'], 'Arma 3') !== 0 ? ' · ' . $h((string) $cand['mod']) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($slot['candidates'] !== []): ?>
                            <div class="eqp-chooser" data-eqp-chooser hidden>
                                <ul class="eqp-chosen" data-eqp-chosen translate="no"></ul>
                                <button type="button" class="eq-hub__btn eq-hub__btn--outline eq-hub__btn--sm" data-eqp-open aria-haspopup="dialog">Choisir…</button>
                            </div>
                            <div data-eqp-inputs></div>
                            <?php endif; ?>
                            <?php if ($slot['candidates'] === []): ?>
                            <p class="eqx-slot__note">Aucun objet de ce type remonté du jeu pour le moment.</p>
                            <?php elseif ((string) $slot['observed'] !== '' && strcasecmp((string) $slot['observed'], (string) $slot['chosen']) === 0): ?>
                            <p class="eqx-slot__note">Choisie parce que vos joueurs la portent avec cette arme.</p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php endforeach; ?>

                <?php if ($proposal['accessories'] !== []): ?>
                <p class="eqx-accessories">
                    <strong>Accessoires repris</strong> de ce que vos joueurs portent avec cette arme :
                    <?php foreach ($proposal['accessories'] as $i => $acc): ?><?= $i > 0 ? ', ' : '' ?><span translate="no"><?= $h((string) $acc['name']) ?></span><?php endforeach; ?>.
                </p>
                <?php endif; ?>

                <?php if ($proposal['extras'] !== []): ?>
                <fieldset class="eqx-group">
                    <legend>Contenu à ranger</legend>
                    <ul class="eqx-extras">
                        <?php foreach ($proposal['extras'] as $ex): ?>
                        <li>
                            <label>
                                <input type="checkbox" name="extra[<?= $h((string) $ex['key']) ?>]" value="1"<?= !empty($ex['on']) ? ' checked' : '' ?><?= (string) $ex['container'] === '' ? ' disabled' : '' ?>>
                                <span><?= $h((string) $ex['label']) ?> : <strong translate="no"><?= (int) $ex['count'] ?> × <?= $h((string) $ex['name']) ?></strong>
                                    <small>dans <?= $h(match ((string) $ex['container']) {
                                        'uniform' => 'la tenue',
                                        'vest' => 'le gilet',
                                        'backpack' => 'le sac',
                                        default => 'aucun conteneur choisi',
                                    }) ?></small></span>
                            </label>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </fieldset>
                <?php endif; ?>

                <button type="submit" class="eq-hub__btn eq-hub__btn--outline">Actualiser l’aperçu</button>
            </form>
        </section>

        <section class="eqx-card" aria-labelledby="eqx-asst-3">
            <header class="eqx-section__head">
                <span class="eqx-section__num" aria-hidden="true">3</span>
                <div>
                    <h2 id="eqx-asst-3">Aperçu et enregistrement</h2>
                    <p>Le résultat est une tenue au format de l’arsenal ACE : vous pourrez la copier pour ACE ou l’enfiler depuis le bandeau Athena.</p>
                </div>
            </header>

            <?php if ($preview === []): ?>
            <p class="eq-hub__empty">Rien n’est encore choisi : sélectionnez au moins une arme, une tenue ou un gilet à l’étape 2, puis actualisez l’aperçu.</p>
            <?php else: ?>
            <div class="eqx-preview">
                <?php foreach ($preview as $sec): ?>
                <div>
                    <h3><?= $h((string) ($sec['title'] ?? '')) ?></h3>
                    <ul>
                        <?php foreach (($sec['items'] ?? []) as $it): ?>
                        <li><span translate="no"><?= $h((string) ($it['name'] ?? '')) ?></span><?php if ((int) ($it['qty'] ?? 1) > 1): ?> <em>× <?= (int) $it['qty'] ?></em><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($variantCount > 1): ?>
            <p class="eqx-variants" role="status">
                <strong><?= $variantCount ?> tenues seront créées.</strong>
                <span>Une pour chaque objet choisi à plusieurs dans un même emplacement. L’aperçu ci-dessus montre la première.</span>
                <?php if ($variantsTruncated): ?><span>Les suivantes, au-delà de la limite, sont écartées.</span><?php endif; ?>
            </p>
            <?php endif; ?>
            <?php if (is_array($proposal['mods']) && $proposal['mods'] !== []): ?>
            <p class="eq-hub__hint">Mods requis : <span translate="no"><?= $h(implode(', ', array_map('strval', $proposal['mods']))) ?></span>.</p>
            <?php else: ?>
            <p class="eq-hub__hint">Aucun mod requis en dehors d’Arma 3.</p>
            <?php endif; ?>

            <form method="post" action="<?= $h(url('equipment/assistant/enregistrer')) ?>" class="eqx-form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <input type="hidden" name="role" value="<?= $selectedRole ?>">
                <input type="hidden" name="profil" value="<?= $h((string) $proposal['profile']) ?>">
                <?php foreach ($proposal['slots'] as $slotKey => $slot): ?>
                <?php $picked = is_array($slot['chosen_all'] ?? null) && $slot['chosen_all'] !== [] ? $slot['chosen_all'] : ['']; ?>
                <?php foreach ($picked as $pickedClass): ?>
                <input type="hidden" name="slot[<?= $h($slotKey) ?>][]" value="<?= $h((string) $pickedClass) ?>">
                <?php endforeach; ?>
                <?php endforeach; ?>
                <?php foreach ($proposal['extras'] as $ex): ?>
                <?php if (!empty($ex['on'])): ?><input type="hidden" name="extra[<?= $h((string) $ex['key']) ?>]" value="1"><?php endif; ?>
                <?php endforeach; ?>

                <label>Nom de la tenue
                    <input type="text" name="nom" maxlength="90" required value="<?= $h($suggestedName) ?>" autocomplete="off">
                </label>
                <fieldset class="eqx-choice">
                    <legend>Enregistrer comme</legend>
                    <label><input type="radio" name="cible" value="tenue"<?= $wantsKit ? '' : ' checked' ?>> Ma tenue — personnelle, vous seul la voyez</label>
                    <?php if ($canManageKits): ?>
                    <label><input type="radio" name="cible" value="kit"<?= $wantsKit ? ' checked' : '' ?>> Un kit de départ — proposé à des membres</label>
                    <?php endif; ?>
                </fieldset>
                <?php if ($canManageKits): ?>
                <div class="eqx-kitopts">
                    <label>Pour un kit : à qui le proposer
                        <select name="audience" class="bo-select">
                            <?php $audience = 'all'; require __DIR__ . '/_audience_options.php'; ?>
                        </select>
                    </label>
                    <label>Pour un kit : description <small>(facultatif)</small>
                        <textarea name="description" rows="2" maxlength="1000" placeholder="Quand l’utiliser, pour qui, particularités…"></textarea>
                    </label>
                </div>
                <?php endif; ?>
                <div class="eqx-actions">
                    <button type="submit" class="eq-hub__btn">Enregistrer</button>
                    <a class="eq-hub__btn eq-hub__btn--outline" href="<?= $h(url('equipment')) ?>">Annuler</a>
                </div>
            </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!$assistantLocked && $catalogCount > 0): ?>
    <!-- Fenêtre de choix des objets d'un emplacement (composant « catalogue » du portail : .jobcat, comme les référentiels d'emplois et de grades) -->
    <dialog class="jobcat eqp" id="eqp-picker" aria-labelledby="eqp-title" aria-describedby="eqp-lead" data-eqp-dialog>
        <div class="jobcat__frame">
            <header class="jobcat__head">
                <div>
                    <p class="jobcat__kicker">Choix d’objets</p>
                    <h2 id="eqp-title" data-eqp-title>Choisir des objets</h2>
                    <p class="jobcat__lead" id="eqp-lead">Cochez un ou plusieurs objets. Si vous en cochez plusieurs, une tenue est créée pour chacun (<?= (int) \App\Services\GameEquipment\OutfitAssistant::MAX_VARIANTS ?> tenues au plus en tout). Rien n’est enregistré tant que vous n’avez pas validé.</p>
                </div>
                <div class="jobcat__head-actions">
                    <button type="button" class="jobcat__close" data-eqp-close aria-label="Fermer sans changer">×</button>
                </div>
            </header>
            <div class="jobcat__toolbar" role="search">
                <label class="jobcat__search"><span class="visually-hidden">Rechercher un objet</span><input type="search" data-eqp-search placeholder="Nom de l’objet ou du mod…" autocomplete="off" aria-controls="eqp-results"></label>
                <button type="button" class="jobcat__filters-toggle" data-eqp-filters-toggle aria-expanded="false" aria-controls="eqp-filters">Filtres et tri</button>
                <div class="jobcat__filters" id="eqp-filters">
                    <label><span>Mod</span><select data-eqp-mod><option value="">Tous</option></select></label>
                    <label><span>Trier par</span><select data-eqp-sort><option value="players">Les plus portés</option><option value="name">Nom</option></select></label>
                    <div class="jobcat__check"><input type="checkbox" id="eqp-pref" data-eqp-pref><label for="eqp-pref">Conseillés pour ce profil</label></div>
                    <div class="jobcat__check"><input type="checkbox" id="eqp-sel" data-eqp-onlysel><label for="eqp-sel">Mes choix seulement</label></div>
                </div>
            </div>
            <div class="jobcat__status"><p class="jobcat__count" data-eqp-count role="status" aria-live="polite"></p></div>
            <div class="jobcat__body eqp-body">
                <ul class="jobcat__results eqp-results" id="eqp-results" data-eqp-results aria-label="Objets proposés" translate="no"></ul>
            </div>
            <footer class="eqp-foot">
                <div class="eqp-summary">
                    <p class="eqp-summary__text" data-eqp-summary role="status" aria-live="polite"></p>
                    <ul class="eqp-summary__list" data-eqp-summary-list translate="no"></ul>
                </div>
                <div class="eqp-actions">
                    <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eqp-clear>Tout décocher</button>
                    <button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eqp-cancel>Annuler</button>
                    <button type="button" class="eq-hub__btn" data-eqp-validate>Valider</button>
                </div>
            </footer>
        </div>
    </dialog>
    <span hidden data-eqp-label="none">Aucun objet choisi</span>
    <span hidden data-eqp-label="choose">Choisir…</span>
    <span hidden data-eqp-label="change">Modifier le choix</span>
    <span hidden data-eqp-label="more">autres objets : affinez la recherche pour les voir.</span>
    <span hidden data-eqp-label="empty">Aucun objet ne correspond.</span>
    <span hidden data-eqp-label="players">joueurs</span>
    <span hidden data-eqp-label="player">joueur</span>
    <span hidden data-eqp-label="limit">Pas plus de %n objets ici : cela ferait plus de %max tenues. Décochez-en un d’abord.</span>
    <span hidden data-eqp-label="chosen">choisi(s)</span>
    <span hidden data-eqp-label="variants">Une tenue sera créée pour chaque objet : %n tenues.</span>
    <span hidden data-eqp-label="one">Une seule tenue sera créée.</span>
    <span hidden data-eqp-label="remove">Retirer</span>
    <span hidden data-eqp-label="results">résultats</span>
    <span hidden data-eqp-label="result">résultat</span>
    <span hidden data-eqp-label="all-mods">Tous</span>
    <span hidden data-eqp-label="recommended">Conseillé</span>
    <span hidden data-eqp-label="category">Catégorie</span>
    <span hidden data-eqp-label="title-slot">Choisir :</span>
    <span hidden data-eqp-label="title-role">Choisir un emploi</span>
    <span hidden data-eqp-label="lead-role">Choisissez l’emploi de départ : le profil de tenue en est déduit, vous pourrez le changer ensuite.</span>
    <span hidden data-eqp-label="search-role">Nom de l’emploi ou catégorie…</span>
    <span hidden data-eqp-label="role-choose">Chercher un emploi…</span>
    <span hidden data-eqp-label="none-role">Aucun emploi choisi : validez pour repartir d’un profil.</span>
    <script type="application/json" id="eqp-data"><?= json_encode($pickerData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
    <?php endif; ?>
</div>
<script src="<?= $h(asset_url('assets/js/equipment-assistant.js')) ?>" defer></script>
