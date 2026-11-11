<?php

declare(strict_types=1);

/**
 * Mes identités › Identités. Variables de views/operateur/identite.php.
 *
 * Un seul objet : l'identité. Chaque carte porte sa pastille de type (« Personnage » ou « D'emprunt », jamais la nature
 * exacte : vérité de jeu) et ses sections Identité · Papiers · Histoire · Jeu. Une seule identité porte « Active en
 * jeu » : ses papiers partent en mission et le téléphone du jeu la présente comme l'identité jouée.
 * Une seule création guidée : type → état civil → signalement → papiers → histoire → récapitulatif.
 */
require __DIR__ . '/_document_svg.php';
$hasPrimary = false;
$primaryName = '';
foreach ($idIdentities as $i) {
    if ($i['is_primary']) {
        $hasPrimary = true;
        $primaryName = (string) $i['display_name'];
    }
}
$aliasCount = count(array_filter($idIdentities, static fn (array $i): bool => !$i['is_primary']));
$aliasMax = \App\Services\Operator\OperatorIdentityService::MAX_ALIASES;
$storyMax = \App\Services\Operator\OperatorIdentityService::STORY_MAX;
$confirmed = is_array($idSync) && $idSync['delivery'] === 'received';
$statusBadge = ['ok' => 'cc-badge--ink', 'warn' => 'cc-badge--amber', 'wait' => 'cc-badge--amber', 'bad' => 'cc-badge--rose'];
$docsByIdentity = [];
foreach ($idDocuments as $d) {
    $docsByIdentity[(int) $d['identity_id']][] = $d;
}
$typesByCountry = [];
foreach ($idCountries as $c) {
    $typesByCountry[$c['code']] = array_values($c['doc_types']);
}
$typicalPapers = [];
foreach (\App\Services\Operator\OperatorIdentityService::TYPICAL_PAPERS as $type) {
    $typicalPapers[$type] = (string) ($idDocTypes[$type] ?? $type);
}
$counts = ['active' => 0, 'personnage' => 0, 'emprunt' => 0, 'archivee' => 0];
foreach ($idIdentities as $i) {
    $counts['archivee'] += $i['archived'] ? 1 : 0;
    $counts['active'] += $i['active'] ? 1 : 0;
    $counts[$i['type'] === 'personnage' ? 'personnage' : 'emprunt'] += $i['archived'] ? 0 : 1;
}

/** Champs d'état civil, communs à la création guidée et à la modification. */
$civilFields = static function (?array $identity, string $key, bool $primary, array $defaults = []) use ($h, $idCountries): string {
    $locked = !empty($identity['civil_locked']);
    $fid = static fn (string $k): string => 'f-' . $key . '-' . $k;
    $val = static fn (string $k) => $h((string) ($identity[$k] ?? $defaults[$k] ?? ''));
    $sex = (string) ($identity['sex'] ?? $defaults['sex'] ?? 'X');
    $nat = (string) ($identity['nationality'] ?? $defaults['nationality'] ?? '');
    ob_start(); ?>
    <div class="opx-fields opx-fields--3">
        <label for="<?= $fid('last_name') ?>">Nom
            <input id="<?= $fid('last_name') ?>" name="last_name" type="text" maxlength="40" required autocomplete="off" value="<?= $val('last_name') ?>"<?php if ($locked): ?> readonly<?php endif; ?>>
        </label>
        <label for="<?= $fid('first_name') ?>">Prénom
            <input id="<?= $fid('first_name') ?>" name="first_name" type="text" maxlength="40" required autocomplete="off" value="<?= $val('first_name') ?>"<?php if ($locked): ?> readonly<?php endif; ?>>
        </label>
        <?php if (!$primary): ?>
            <label for="<?= $fid('alias') ?>">Surnom connu <span class="opx-opt">facultatif</span>
                <input id="<?= $fid('alias') ?>" name="alias" type="text" maxlength="120" autocomplete="off" value="<?= $val('alias') ?>">
            </label>
        <?php endif; ?>
        <label for="<?= $fid('sex') ?>">Sexe
            <select id="<?= $fid('sex') ?>" name="sex"<?php if ($locked): ?> disabled<?php endif; ?>>
                <?php foreach (['X' => 'Non précisé', 'M' => 'Masculin', 'F' => 'Féminin'] as $k => $label): ?>
                    <option value="<?= $h($k) ?>"<?php if ($sex === $k): ?> selected<?php endif; ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="<?= $fid('birth_date') ?>">Date de naissance
            <input id="<?= $fid('birth_date') ?>" name="birth_date" type="date" value="<?= $val('birth_date') ?>"<?php if ($locked): ?> readonly<?php endif; ?>>
        </label>
        <label for="<?= $fid('birth_place') ?>">Lieu de naissance
            <input id="<?= $fid('birth_place') ?>" name="birth_place" type="text" maxlength="120" autocomplete="off" value="<?= $val('birth_place') ?>"<?php if ($locked): ?> readonly<?php endif; ?>>
        </label>
        <label for="<?= $fid('nationality') ?>">Nationalité
            <select id="<?= $fid('nationality') ?>" name="nationality"<?php if ($locked): ?> disabled<?php endif; ?>>
                <option value="">À choisir</option>
                <?php foreach ($idCountries as $c): ?>
                    <option value="<?= $h($c['code']) ?>"<?php if ($nat === $c['code']): ?> selected<?php endif; ?>><?= $h($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="<?= $fid('profession') ?>">Métier <span class="opx-opt">facultatif</span>
            <input id="<?= $fid('profession') ?>" name="profession" type="text" maxlength="120" autocomplete="off" value="<?= $val('profession') ?>">
        </label>
    </div>
    <?php return (string) ob_get_clean();
};
/** Champs du signalement. */
$profileFields = static function (?array $identity, string $key) use ($h, $idHair, $idEyes): string {
    $p = $identity['profile'] ?? [];
    $fid = static fn (string $k): string => 'f-' . $key . '-' . $k;
    ob_start(); ?>
    <div class="opx-fields opx-fields--3">
        <label for="<?= $fid('height_cm') ?>">Taille (cm)
            <input id="<?= $fid('height_cm') ?>" name="height_cm" type="number" inputmode="numeric" min="130" max="230" value="<?= $h((string) ($p['height_cm'] ?? '')) ?>">
        </label>
        <label for="<?= $fid('weight_kg') ?>">Poids (kg) <span class="opx-opt">facultatif</span>
            <input id="<?= $fid('weight_kg') ?>" name="weight_kg" type="number" inputmode="numeric" min="35" max="200" value="<?= $h((string) ($p['weight_kg'] ?? '')) ?>">
        </label>
        <label for="<?= $fid('eyes') ?>">Yeux
            <select id="<?= $fid('eyes') ?>" name="eyes">
                <option value="">À choisir</option>
                <?php foreach ($idEyes as $k => $label): ?>
                    <option value="<?= $h($k) ?>"<?php if (($p['eyes'] ?? '') === $k): ?> selected<?php endif; ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="<?= $fid('hair') ?>">Cheveux
            <select id="<?= $fid('hair') ?>" name="hair">
                <option value="">À choisir</option>
                <?php foreach ($idHair as $k => $label): ?>
                    <option value="<?= $h($k) ?>"<?php if (($p['hair'] ?? '') === $k): ?> selected<?php endif; ?>><?= $h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="opx-fields__wide" for="<?= $fid('marks') ?>">Signes particuliers <span class="opx-opt">facultatif</span>
            <textarea id="<?= $fid('marks') ?>" name="marks" rows="2" maxlength="300" placeholder="Cicatrice au sourcil gauche, tatouage à l’avant-bras…"><?= $h((string) ($p['marks'] ?? '')) ?></textarea>
        </label>
    </div>
    <?php return (string) ob_get_clean();
};
$storyField = static function (?array $identity, string $key) use ($h, $storyMax): string {
    ob_start(); ?>
    <div class="opx-fields">
        <label class="opx-fields__wide" for="f-<?= $h($key) ?>-story">Histoire et notes du personnage <span class="opx-opt">facultatif</span>
            <textarea id="f-<?= $h($key) ?>-story" name="story" rows="5" maxlength="<?= (int) $storyMax ?>" placeholder="Où a-t-il grandi ? Que fait-il de ses journées ? Pourquoi est-il ici ?"><?= $h((string) ($identity['story'] ?? '')) ?></textarea>
        </label>
    </div>
    <?php return (string) ob_get_clean();
};

/** Modification d'une identité : mêmes champs que la création, sans les étapes. */
$identityForm = static function (array $identity) use ($h, $csrfToken, $civilFields, $profileFields, $storyField, $idStoryAvailable): string {
    $id = (int) $identity['id'];
    ob_start(); ?>
    <form class="opx-form" method="post" action="<?= $h(url('operateur/identite/identites/' . $id)) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <?php if (!empty($identity['civil_locked'])): ?>
            <p class="opx-note">Cette identité porte déjà un papier émis : son état civil ne se modifie plus ici (voyez un responsable). Le signalement, le métier et l’histoire restent modifiables.</p>
        <?php endif; ?>
        <fieldset class="opx-fieldset">
            <legend>État civil</legend>
            <?= $civilFields($identity, (string) $id, (bool) $identity['is_primary']) ?>
        </fieldset>
        <fieldset class="opx-fieldset">
            <legend>Signalement</legend>
            <p class="opx-note">Repris sur vos papiers et sur votre fiche aux fichiers.</p>
            <?= $profileFields($identity, (string) $id) ?>
        </fieldset>
        <?php if ($idStoryAvailable): ?>
            <fieldset class="opx-fieldset">
                <legend>Histoire</legend>
                <p class="opx-note">Ce que vous racontez quand on vous interroge. Les huit premières lignes sont envoyées au téléphone du jeu avec l’identité.</p>
                <?= $storyField($identity, (string) $id) ?>
            </fieldset>
        <?php endif; ?>
        <div class="opx-dialog__foot">
            <button type="submit" class="cc-btn cc-btn-primary">Enregistrer</button>
        </div>
    </form>
    <?php return (string) ob_get_clean();
};

/** Création guidée « Nouvelle identité » : une seule, pour le personnage comme pour une identité d'emprunt. */
$wizard = static function (bool $primary, array $defaults = []) use ($h, $csrfToken, $civilFields, $profileFields, $storyField, $idStoryAvailable, $typicalPapers, $typesByCountry, $primaryName, $idCountries): string {
    $key = $primary ? 'main' : 'new';
    ob_start(); ?>
    <form class="opx-form" method="post" action="<?= $h(url('operateur/identite/identites')) ?>" data-opx-wizard data-opx-types="<?= $h((string) json_encode($typesByCountry)) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <ol class="opx-steps">
            <li>
                <h3>Le type</h3>
                <div class="opx-choices">
                    <label class="opx-choice<?= $primary ? '' : ' is-disabled' ?>">
                        <input type="radio" name="type" value="personnage" data-opx-label="Personnage"<?php if ($primary): ?> checked<?php else: ?> disabled<?php endif; ?>>
                        <span><strong>Personnage</strong><em><?php if ($primary): ?>L’identité de votre personnage : celle de vos papiers et de votre fiche aux fichiers.<?php else: ?>Déjà défini : <span translate="no"><?= $h($primaryName) ?></span>. Pour en changer, utilisez « Définir comme personnage » sur une autre identité.<?php endif; ?></em></span>
                    </label>
                    <label class="opx-choice<?= $primary ? ' is-disabled' : '' ?>">
                        <input type="radio" name="type" value="emprunt" data-opx-label="D’emprunt"<?php if (!$primary): ?> checked<?php else: ?> disabled<?php endif; ?>>
                        <span><strong>D’emprunt</strong><em><?php if ($primary): ?>Un autre nom à porter en mission. Disponible une fois votre personnage créé.<?php else: ?>Un autre nom que votre personnage porte en mission, avec ses propres papiers. Visible de vous et des responsables habilités seulement.<?php endif; ?></em></span>
                    </label>
                </div>
            </li>
            <li>
                <h3>L’état civil</h3>
                <?php if ($primary): ?><p class="opx-note">Les champs sont repris de votre profil quand il les connaît ; corrigez-les, le reste se complète plus tard.</p><?php endif; ?>
                <?= $civilFields(null, $key, $primary, $defaults) ?>
            </li>
            <li>
                <h3>Le signalement</h3>
                <p class="opx-note">Repris sur les papiers et sur la fiche aux fichiers. La photo s’ajoute sur la carte de l’identité, une fois créée.</p>
                <?= $profileFields(null, $key) ?>
            </li>
            <li>
                <h3>Les papiers</h3>
                <?php if ($idCountries === []): ?>
                    <p class="opx-note">Aucun pays n’est actif dans votre communauté : les papiers se demanderont plus tard.</p>
                <?php else: ?>
                    <p class="opx-note">Cochez ceux à demander pour cette identité, au pays de sa nationalité. Un responsable valide chaque demande et émet le papier.</p>
                    <div class="opx-choices">
                        <?php foreach ($typicalPapers as $type => $label): ?>
                            <label class="opx-choice">
                                <input type="checkbox" name="request_papers[]" value="<?= $h($type) ?>" data-opx-wizard-paper data-opx-label="<?= $h($label) ?>">
                                <span><strong><?= $h($label) ?></strong><em>Demandé à la création.</em></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </li>
            <?php if ($idStoryAvailable): ?>
                <li>
                    <h3>L’histoire</h3>
                    <p class="opx-note">Ce que vous racontez quand on vous interroge. Les huit premières lignes sont envoyées au téléphone du jeu avec l’identité.</p>
                    <?= $storyField(null, $key) ?>
                </li>
            <?php endif; ?>
            <li>
                <h3>Le récapitulatif</h3>
                <dl class="opx-legend" data-opx-wizard-recap>
                    <div><dt>Type</dt><dd><?= $primary ? 'Personnage' : 'D’emprunt' ?></dd></div>
                    <div><dt>Nom</dt><dd translate="no" data-opx-recap="name">—</dd></div>
                    <div><dt>Naissance</dt><dd data-opx-recap="birth">—</dd></div>
                    <div><dt>Nationalité</dt><dd data-opx-recap="nationality">—</dd></div>
                    <div class="opx-legend__wide"><dt>Papiers demandés</dt><dd data-opx-recap="papers">Aucun</dd></div>
                </dl>
                <p class="opx-note">Tout se modifie ensuite sur la carte de l’identité, sauf l’état civil une fois un papier émis.</p>
            </li>
        </ol>
        <div class="opx-dialog__foot">
            <button type="submit" class="cc-btn cc-btn-primary"><?= $primary ? 'Créer mon personnage' : 'Créer cette identité' ?></button>
        </div>
    </form>
    <?php return (string) ob_get_clean();
};

// Vignette d'un papier : aperçu recto, type, numéro, validité, état.
$paperThumb = static function (array $doc) use ($h, $opxDocSvg, $dateFr, $statusBadge): string {
    $photo = !empty($doc['photo_path']) ? url((string) $doc['photo_path']) : null;

    return '<a class="opx-paper__view" href="' . $h(url('operateur/identite/documents') . '#papier-' . (int) $doc['id']) . '">' . $opxDocSvg($doc, 'recto', $photo) . '</a>'
        . '<div class="opx-paper__text"><strong>' . $h($doc['doc_type_label']) . '</strong>'
        . '<span translate="no">' . $h($doc['doc_number'] !== '' ? $doc['doc_number'] : '—') . '</span>'
        . '<span>' . $h($doc['country_name']) . ' · expire le ' . $h($dateFr((string) $doc['expires_on'])) . '</span>'
        . '<span class="cc-badge ' . $h($statusBadge[$doc['status_tone']] ?? 'cc-badge--amber') . '">' . $h($doc['status_label']) . '</span></div>';
};
?>

<?php if (!$hasPrimary): ?>
    <section class="dash-zone dash-zone--actions" id="nouvelle-identite" aria-labelledby="opx-main-title">
        <div class="dash-zone__inner">
            <header class="dash-zone__head">
                <h2 id="opx-main-title" class="dash-zone__title">Créer mon personnage</h2>
                <p class="dash-zone__lead">Votre première identité est celle de votre personnage. Vous pourrez ensuite lui ajouter des identités d’emprunt, chacune avec ses papiers.</p>
            </header>
            <article class="cc-card">
                <div class="opx-card__body">
                    <?= $wizard(true, $idProposal) ?>
                </div>
            </article>
        </div>
    </section>
<?php endif; ?>

<?php if ($idIdentities !== []): ?>
    <section class="dash-zone dash-zone--actions" aria-labelledby="opx-ids-title">
        <div class="dash-zone__inner">
            <header class="dash-zone__head">
                <h2 id="opx-ids-title" class="dash-zone__title">Mes identités</h2>
                <p class="dash-zone__lead">Votre personnage et vos identités d’emprunt, chacune avec ses papiers et son histoire. Une seule est active en jeu : ses papiers vous sont remis en mission et le téléphone du jeu la présente comme l’identité jouée.</p>
            </header>
            <div class="opx-acts"<?php if ($hasPrimary): ?> id="nouvelle-identite"<?php endif; ?>>
                <?php if ($hasPrimary && $aliasCount < $aliasMax): ?>
                    <button type="button" class="cc-btn cc-btn-primary" data-opx-open="dlg-nouvelle"><?= $opxIcon('plus', '') ?> Nouvelle identité</button>
                <?php endif; ?>
                <button type="button" class="cc-btn cc-btn-ghost" data-opx-open="dlg-photo-guide"><?= $opxIcon('camera', '') ?> Comment faire ma photo ?</button>
                <?php if ($hasPrimary): ?>
                    <p class="opx-note">
                        <?php if ($aliasCount >= $aliasMax): ?>
                            <?= (int) $aliasCount ?> identités d’emprunt sur <?= (int) $aliasMax ?> : limite atteinte. Archiver une identité ne libère pas de place, voyez un responsable pour en retirer une.
                        <?php elseif ($aliasCount <= 1): ?>
                            <?= (int) $aliasCount ?> identité d’emprunt sur <?= (int) $aliasMax ?>
                        <?php else: ?>
                            <?= (int) $aliasCount ?> identités d’emprunt sur <?= (int) $aliasMax ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php if (count($idIdentities) > 1): ?>
                <div class="opx-filters" role="group" aria-label="Filtrer mes identités" data-opx-filters hidden>
                    <button type="button" class="opx-filter is-on" data-opx-filter="" aria-pressed="true">Toutes <span><?= count($idIdentities) ?></span></button>
                    <button type="button" class="opx-filter" data-opx-filter="active" aria-pressed="false">Active en jeu <span><?= (int) $counts['active'] ?></span></button>
                    <button type="button" class="opx-filter" data-opx-filter="personnage" aria-pressed="false">Personnage <span><?= (int) $counts['personnage'] ?></span></button>
                    <button type="button" class="opx-filter" data-opx-filter="emprunt" aria-pressed="false">D’emprunt <span><?= (int) $counts['emprunt'] ?></span></button>
                    <?php if ($counts['archivee'] > 0): ?>
                        <button type="button" class="opx-filter" data-opx-filter="archivee" aria-pressed="false">Archivées <span><?= (int) $counts['archivee'] ?></span></button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <form id="opx-paper-request" method="post" action="<?= $h(url('operateur/identite/documents')) ?>" hidden>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <input type="hidden" name="retour" value="identites">
            </form>
            <ul class="opx-cards">
                <?php foreach ($idIdentities as $identity): ?>
                    <?php
                    $id = (int) $identity['id'];
                    $c = $identity['completeness'];
                    $missingCount = count($c['missing']);
                    $name = $identity['display_name'] !== '' ? $identity['display_name'] : 'Identité sans nom';
                    $signalement = implode(' · ', array_filter([
                        $identity['profile']['height_cm'] !== null ? $identity['profile']['height_cm'] . ' cm' : null,
                        $identity['profile']['eyes'] !== null ? 'yeux ' . mb_strtolower($idEyes[$identity['profile']['eyes']] ?? '') : null,
                        $identity['profile']['hair'] !== null ? 'cheveux ' . mb_strtolower($idHair[$identity['profile']['hair']] ?? '') : null,
                    ]));
                    $docs = $docsByIdentity[$id] ?? [];
                    $missingPapers = $idMissingPapers[$id] ?? [];
                    $tags = trim(($identity['archived'] ? 'archivee' : $identity['type']) . ($identity['active'] ? ' active' : ''));
                    ?>
                    <li data-opx-identity-card="<?= $h($tags) ?>">
                        <article class="cc-card opx-card<?= $identity['archived'] ? ' is-off' : '' ?><?= $identity['active'] ? ' is-live' : '' ?>" id="identite-<?= $id ?>">
                            <div class="opx-card__body">
                                <div class="opx-card__top">
                                    <?php if (!empty($identity['photo_path'])): ?>
                                        <span class="opx-photo"><img src="<?= $h(url((string) $identity['photo_path'])) ?>" alt="Photo d’identité de <?= $h($name) ?>" loading="lazy"></span>
                                    <?php elseif (!$identity['archived']): ?>
                                        <button type="button" class="opx-photo" data-opx-open="dlg-photo-<?= $id ?>"><?= $opxIcon('camera', '') ?> Ajouter une photo</button>
                                    <?php else: ?>
                                        <span class="opx-photo" aria-hidden="true"><?= $opxIcon('user', '') ?></span>
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="opx-card__title" translate="no"><?= $h($name) ?></h3>
                                        <?php if (!empty($identity['alias'])): ?><p class="opx-card__sub">Connu comme <span translate="no"><?= $h($identity['alias']) ?></span></p><?php endif; ?>
                                        <div class="opx-card__badges">
                                            <span class="cc-badge <?= $identity['type'] === 'personnage' ? 'cc-badge--ink' : 'cc-badge--sky-solid' ?>"><?= $h($identity['type_label']) ?></span>
                                            <?php if ($identity['active']): ?>
                                                <span class="cc-badge cc-badge--live-solid"><span class="cc-badge__dot" aria-hidden="true"></span> Active en jeu</span>
                                            <?php endif; ?>
                                            <?php if ($identity['archived']): ?><span class="cc-badge cc-badge--amber">Archivée</span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="opx-sections">
                                    <section class="opx-section" aria-labelledby="sec-id-<?= $id ?>">
                                        <h4 class="opx-built__title" id="sec-id-<?= $id ?>">Identité</h4>
                                        <dl class="opx-facts">
                                            <div><dt>Né(e) le</dt><dd><?= $h($dateFr($identity['birth_date'])) ?></dd></div>
                                            <div><dt>Lieu de naissance</dt><dd><?= $h($identity['birth_place'] ?: '—') ?></dd></div>
                                            <div><dt>Nationalité</dt><dd><?= $h($countryName($identity['nationality'])) ?></dd></div>
                                            <div><dt>Métier</dt><dd><?= $h($identity['profession'] ?: '—') ?></dd></div>
                                            <div><dt>Signalement</dt><dd><?= $h($signalement !== '' ? $signalement : 'À renseigner') ?></dd></div>
                                        </dl>
                                        <?php if (!$identity['archived']): ?>
                                            <div class="opx-complete">
                                                <div class="opx-complete__row">
                                                    <div class="cc-progress" role="img" aria-label="Identité complète à <?= (int) $c['percent'] ?> %"><span style="width: <?= (int) $c['percent'] ?>%"></span></div>
                                                    <span class="opx-complete__pct"><?= (int) $c['percent'] ?> %</span>
                                                </div>
                                                <?php if ($missingCount === 0): ?>
                                                    <p class="opx-complete__text">Identité complète.</p>
                                                <?php else: ?>
                                                    <p class="opx-complete__text"><?php if ($missingCount === 1): ?>Il manque 1 champ :<?php else: ?>Il manque <?= $missingCount ?> champs :<?php endif; ?>
                                                        <?php foreach ($c['missing'] as $n => $m): ?>
                                                            <a href="#<?= $h($m['key'] === 'photo' ? 'photo-input-' . $id : 'f-' . $id . '-' . $m['key']) ?>" data-opx-open="<?= $h($m['key'] === 'photo' ? 'dlg-photo-' . $id : 'dlg-identite-' . $id) ?>"><?= $h($m['label']) ?></a><?= $n < $missingCount - 1 ? ', ' : '' ?>
                                                        <?php endforeach; ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </section>

                                    <section class="opx-section" aria-labelledby="sec-pap-<?= $id ?>">
                                        <h4 class="opx-built__title" id="sec-pap-<?= $id ?>"><?php if ($docs === []): ?>Papiers<?php elseif (count($docs) === 1): ?>1 papier<?php else: ?><?= count($docs) ?> papiers<?php endif; ?></h4>
                                        <?php if ($docs === []): ?>
                                            <p class="opx-uses">Aucun papier pour cette identité.</p>
                                        <?php else: ?>
                                            <ul class="opx-papers">
                                                <?php foreach ($docs as $doc): ?>
                                                    <li class="opx-paper<?= in_array($doc['status'], ['requested', 'pending'], true) ? ' is-waiting' : '' ?>"><?= $paperThumb($doc) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php if (!$identity['archived']): ?>
                                            <div class="opx-acts">
                                                <?php foreach ($missingPapers as $m): ?>
                                                    <span class="opx-ask"><?= $h($m['label']) ?> · <?= $h($m['country_name']) ?>
                                                        <button type="submit" form="opx-paper-request" name="demande" value="<?= $h($id . ':' . $m['type'] . ':' . $m['country_code']) ?>" class="cc-btn cc-btn-ghost">Demander ce papier</button>
                                                    </span>
                                                <?php endforeach; ?>
                                                <a class="opx-link" href="<?= $h(url('operateur/identite/documents') . '#papiers-' . $id) ?>"><?= $docs === [] ? 'Demander un autre papier' : 'Voir et gérer ses papiers' ?></a>
                                            </div>
                                        <?php endif; ?>
                                    </section>

                                    <?php if ($idStoryAvailable): ?>
                                        <section class="opx-section" aria-labelledby="sec-his-<?= $id ?>">
                                            <h4 class="opx-built__title" id="sec-his-<?= $id ?>">Histoire</h4>
                                            <?php if (trim((string) $identity['story']) !== ''): ?>
                                                <p class="opx-story"><?= nl2br($h((string) $identity['story'])) ?></p>
                                            <?php elseif (!$identity['archived']): ?>
                                                <p class="opx-uses">Aucune histoire écrite. <button type="button" class="opx-linkbtn" data-opx-open="dlg-identite-<?= $id ?>">Écrire son histoire</button></p>
                                            <?php else: ?>
                                                <p class="opx-uses">Aucune histoire écrite.</p>
                                            <?php endif; ?>
                                        </section>
                                    <?php endif; ?>

                                    <section class="opx-section" aria-labelledby="sec-jeu-<?= $id ?>">
                                        <h4 class="opx-built__title" id="sec-jeu-<?= $id ?>">Jeu</h4>
                                        <?php if ($identity['archived']): ?>
                                            <p class="opx-uses">Archivée : elle n’est plus envoyée au jeu.</p>
                                        <?php elseif ($identity['active']): ?>
                                            <p class="opx-uses"><strong>Active en jeu.</strong>
                                                <?php if ($confirmed): ?>
                                                    Papiers confirmés par le jeu<?php if ($idSync['at'] !== ''): ?> le <?= $h($dateFr($idSync['at'])) ?><?php endif; ?>.
                                                <?php else: ?>
                                                    Ses papiers émis partent en mission. Le jeu n’a pas encore confirmé les avoir reçus.
                                                <?php endif; ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="opx-uses">Pas active : ses papiers ne partent pas en mission.</p>
                                        <?php endif; ?>
                                        <?php if (!$identity['archived']): ?>
                                            <p class="opx-uses">
                                                <?php if ($identity['game_synced_at'] !== null): ?>
                                                    Envoyée au téléphone du jeu le <?= $h($dateFr((string) $identity['game_synced_at'])) ?>.
                                                <?php else: ?>
                                                    Sur le téléphone du jeu, « Synchroniser avec Athena » la récupère.
                                                <?php endif; ?>
                                            </p>
                                            <?php if (!$identity['active']): ?>
                                                <form method="post" action="<?= $h(url('operateur/identite/identites/' . $id . '/activer')) ?>">
                                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                                    <button type="submit" class="cc-btn cc-btn-ghost" title="Ses papiers émis vous seront remis en mission à la place de ceux de l’identité active">Utiliser en jeu</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </section>
                                </div>
                            </div>
                            <div class="opx-card__foot opx-acts">
                                <?php if (!$identity['archived']): ?>
                                    <button type="button" class="cc-btn cc-btn-primary" data-opx-open="dlg-identite-<?= $id ?>">Modifier</button>
                                    <?php if (!empty($identity['photo_path'])): ?>
                                        <button type="button" class="cc-btn cc-btn-ghost" data-opx-open="dlg-photo-<?= $id ?>">Changer la photo</button>
                                    <?php endif; ?>
                                    <?php if (!empty($identity['promotable'])): ?>
                                        <form method="post" action="<?= $h(url('operateur/identite/identites/' . $id . '/principale')) ?>" data-opx-confirm="Faire de cette identité celle de votre personnage ? L’identité actuelle de votre personnage devient une identité d’emprunt. Rien n’est supprimé.">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                            <button type="submit" class="cc-btn cc-btn-ghost">Définir comme personnage</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!$identity['is_primary']): ?>
                                    <form class="opx-acts__end" method="post" action="<?= $h(url('operateur/identite/identites/' . $id . '/archiver')) ?>"<?php if (!$identity['archived']): ?> data-opx-confirm="Archiver cette identité ? Ses papiers restent enregistrés, vous pourrez la restaurer."<?php endif; ?>>
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <?php if ($identity['archived']): ?><input type="hidden" name="restaurer" value="1"><?php endif; ?>
                                        <button type="submit" class="cc-btn opx-btn-quiet<?= $identity['archived'] ? '' : ' opx-btn-danger' ?>"><?= $identity['archived'] ? 'Restaurer' : 'Archiver' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>

                        <?php if (!$identity['archived']): ?>
                            <?= $dialogOpen('dlg-identite-' . $id, 'Modifier ' . $name, '', $idEditId === $id) ?>
                                <?= $identityForm($identity) ?>
                            <?= $dialogClose ?>
                            <?= $dialogOpen('dlg-photo-' . $id, 'Photo d’identité', 'Portrait de face, fond neutre. PNG, JPG ou WebP. L’image est recadrée au format 35 × 45 et réenregistrée sans ses données cachées.') ?>
                                <form class="opx-form" method="post" enctype="multipart/form-data" action="<?= $h(url('operateur/identite/identites/' . $id . '/photo')) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <div class="opx-fields">
                                        <label class="opx-fields__wide" for="photo-input-<?= $id ?>">Fichier de la photo
                                            <input id="photo-input-<?= $id ?>" type="file" name="photo" accept="image/png,image/jpeg,image/webp">
                                        </label>
                                    </div>
                                    <div class="opx-dialog__foot">
                                        <button type="button" class="cc-btn cc-btn-ghost opx-acts__start" data-opx-open="dlg-photo-guide">Comment faire ma photo ?</button>
                                        <?php if (!empty($identity['photo_path'])): ?>
                                            <button type="submit" name="photo_remove" value="1" class="cc-btn opx-btn-quiet opx-btn-danger">Retirer la photo</button>
                                        <?php endif; ?>
                                        <button type="submit" class="cc-btn cc-btn-primary">Envoyer la photo</button>
                                    </div>
                                </form>
                            <?= $dialogClose ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="opx-note" data-opx-filter-empty hidden>Aucune identité ne correspond à ce filtre.</p>
        </div>
    </section>
<?php endif; ?>

<?php if ($hasPrimary && $aliasCount < $aliasMax): ?>
    <?= $dialogOpen('dlg-nouvelle', 'Nouvelle identité', 'Type, état civil, signalement, papiers à demander, histoire : tout se saisit une seule fois.') ?>
        <?= $wizard(false) ?>
    <?= $dialogClose ?>
<?php endif; ?>
