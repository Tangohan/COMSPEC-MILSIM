<?php

declare(strict_types=1);

/**
 * Mon identité › Identités. Variables de views/operateur/identite.php.
 *
 * Le membre voit « principale » ou « d'emprunt », jamais la nature exacte d'une identité (vérité de jeu).
 */
$hasPrimary = false;
foreach ($idIdentities as $i) {
    $hasPrimary = $hasPrimary || $i['is_primary'];
}
$aliasCount = count(array_filter($idIdentities, static fn (array $i): bool => !$i['is_primary']));

/** Formulaire d'une identité (création : $identity null). */
$identityForm = static function (?array $identity, bool $primary) use ($h, $csrfToken, $idCountries, $idHair, $idEyes): string {
    $id = (int) ($identity['id'] ?? 0);
    $p = $identity['profile'] ?? [];
    $locked = !empty($identity['civil_locked']);
    $fid = static fn (string $k): string => 'f-' . ($id > 0 ? $id : 'new') . '-' . $k;
    $val = static fn (string $k) => $h((string) ($identity[$k] ?? ''));
    $ro = $locked ? ' readonly aria-readonly="true"' : '';
    ob_start(); ?>
    <form class="opx-form" method="post" action="<?= $h(url('operateur/identite/identites' . ($id > 0 ? '/' . $id : ''))) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <?php if ($locked): ?>
            <p class="opx-note">Cette identité porte déjà un papier émis : son état civil ne se modifie plus ici (voyez un responsable). Le signalement et le métier restent modifiables.</p>
        <?php endif; ?>
        <fieldset class="opx-fieldset">
            <legend>État civil</legend>
            <div class="opx-fields">
                <label for="<?= $fid('last_name') ?>">Nom
                    <input id="<?= $fid('last_name') ?>" name="last_name" type="text" maxlength="40" required autocomplete="off" value="<?= $val('last_name') ?>"<?= $ro ?>>
                </label>
                <label for="<?= $fid('first_name') ?>">Prénom
                    <input id="<?= $fid('first_name') ?>" name="first_name" type="text" maxlength="40" required autocomplete="off" value="<?= $val('first_name') ?>"<?= $ro ?>>
                </label>
                <?php if (!$primary): ?>
                    <label for="<?= $fid('alias') ?>">Surnom connu <span class="opx-opt">facultatif</span>
                        <input id="<?= $fid('alias') ?>" name="alias" type="text" maxlength="120" autocomplete="off" value="<?= $val('alias') ?>">
                    </label>
                <?php endif; ?>
                <label for="<?= $fid('sex') ?>">Sexe
                    <select id="<?= $fid('sex') ?>" name="sex"<?= $locked ? ' disabled' : '' ?>>
                        <?php foreach (['X' => 'Non précisé', 'M' => 'Masculin', 'F' => 'Féminin'] as $k => $label): ?>
                            <option value="<?= $h($k) ?>"<?= ($identity['sex'] ?? 'X') === $k ? ' selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label for="<?= $fid('birth_date') ?>">Date de naissance
                    <input id="<?= $fid('birth_date') ?>" name="birth_date" type="date" value="<?= $val('birth_date') ?>"<?= $ro ?>>
                </label>
                <label for="<?= $fid('birth_place') ?>">Lieu de naissance
                    <input id="<?= $fid('birth_place') ?>" name="birth_place" type="text" maxlength="120" autocomplete="off" value="<?= $val('birth_place') ?>"<?= $ro ?>>
                </label>
                <label for="<?= $fid('nationality') ?>">Nationalité
                    <select id="<?= $fid('nationality') ?>" name="nationality"<?= $locked ? ' disabled' : '' ?>>
                        <option value="">À choisir</option>
                        <?php foreach ($idCountries as $c): ?>
                            <option value="<?= $h($c['code']) ?>"<?= ($identity['nationality'] ?? '') === $c['code'] ? ' selected' : '' ?>><?= $h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label for="<?= $fid('profession') ?>">Métier <span class="opx-opt">facultatif</span>
                    <input id="<?= $fid('profession') ?>" name="profession" type="text" maxlength="120" autocomplete="off" value="<?= $val('profession') ?>">
                </label>
            </div>
        </fieldset>
        <fieldset class="opx-fieldset">
            <legend>Signalement</legend>
            <p class="opx-note">Repris sur vos papiers et sur votre fiche aux fichiers.</p>
            <div class="opx-fields">
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
                            <option value="<?= $h($k) ?>"<?= ($p['eyes'] ?? '') === $k ? ' selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label for="<?= $fid('hair') ?>">Cheveux
                    <select id="<?= $fid('hair') ?>" name="hair">
                        <option value="">À choisir</option>
                        <?php foreach ($idHair as $k => $label): ?>
                            <option value="<?= $h($k) ?>"<?= ($p['hair'] ?? '') === $k ? ' selected' : '' ?>><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="opx-fields__wide" for="<?= $fid('marks') ?>">Signes particuliers <span class="opx-opt">facultatif</span>
                    <textarea id="<?= $fid('marks') ?>" name="marks" rows="2" maxlength="300" placeholder="Cicatrice au sourcil gauche, tatouage à l’avant-bras…"><?= $h((string) ($p['marks'] ?? '')) ?></textarea>
                </label>
            </div>
        </fieldset>
        <div class="opx-form__acts">
            <button type="submit" class="opx-btn opx-btn--primary"><?= $id > 0 ? 'Enregistrer' : ($primary ? 'Créer mon identité' : 'Créer cette identité') ?></button>
        </div>
    </form>
    <?php return (string) ob_get_clean();
};
?>

<?php if (!$hasPrimary): ?>
    <section class="opx-panel" id="nouvelle-identite">
        <h2>Créez votre identité principale</h2>
        <p class="opx-panel__lead">C’est l’identité de votre personnage : celle de vos papiers et de votre fiche aux fichiers. Vous pourrez la compléter plus tard.</p>
        <?= $identityForm(null, true) ?>
    </section>
<?php endif; ?>

<?php foreach ($idIdentities as $identity): ?>
    <?php
    $id = (int) $identity['id'];
    $c = $identity['completeness'];
    $open = $idEditId === $id;
    $missingCount = count($c['missing']);
    ?>
    <article class="opx-identity<?= $identity['archived'] ? ' is-archived' : '' ?>" id="identite-<?= $id ?>">
        <div class="opx-identity__photo">
            <?php if (!empty($identity['photo_path'])): ?>
                <img src="<?= $h(url((string) $identity['photo_path'])) ?>" alt="Photo d’identité de <?= $h($identity['display_name']) ?>" loading="lazy">
            <?php else: ?>
                <span class="opx-identity__nophoto" aria-hidden="true"><?= $opxIcon('user', 'opx-ico opx-ico--xl') ?></span>
            <?php endif; ?>
        </div>
        <div class="opx-identity__main">
            <div class="opx-identity__head">
                <h2 translate="no"><?= $h($identity['display_name'] !== '' ? $identity['display_name'] : 'Identité sans nom') ?></h2>
                <div class="opx-pills">
                    <span class="opx-pill opx-pill--<?= $identity['is_primary'] ? 'info' : 'idle' ?>"><?= $identity['is_primary'] ? 'Principale' : 'D’emprunt' ?></span>
                    <?php if ($identity['active']): ?><span class="opx-pill opx-pill--ok">Envoyée en jeu</span><?php endif; ?>
                    <?php if ($identity['archived']): ?><span class="opx-pill opx-pill--idle">Archivée</span><?php endif; ?>
                </div>
            </div>
            <dl class="opx-facts">
                <div><dt>Né(e) le</dt><dd><?= $h($dateFr($identity['birth_date'])) ?></dd></div>
                <div><dt>Lieu de naissance</dt><dd><?= $h($identity['birth_place'] ?: '—') ?></dd></div>
                <div><dt>Nationalité</dt><dd><?= $h($countryName($identity['nationality'])) ?></dd></div>
                <div><dt>Signalement</dt><dd><?= $h(implode(' · ', array_filter([
                    $identity['profile']['height_cm'] !== null ? $identity['profile']['height_cm'] . ' cm' : null,
                    $identity['profile']['eyes'] !== null ? 'yeux ' . mb_strtolower($idEyes[$identity['profile']['eyes']] ?? '') : null,
                    $identity['profile']['hair'] !== null ? 'cheveux ' . mb_strtolower($idHair[$identity['profile']['hair']] ?? '') : null,
                ])) ?: '—') ?></dd></div>
                <div><dt>Papiers</dt><dd><a href="<?= $h(url('operateur/identite/documents') . '#papiers-' . $id) ?>"><?= (int) $identity['documents_count'] ?> papier(s)</a></dd></div>
            </dl>

            <?php if (!$identity['archived']): ?>
                <div class="opx-complete">
                    <div class="opx-meter" role="img" aria-label="Identité complète à <?= (int) $c['percent'] ?> %"><span style="width: <?= (int) $c['percent'] ?>%"></span></div>
                    <?php if ($missingCount === 0): ?>
                        <p class="opx-complete__text opx-complete__text--ok"><?= $opxIcon('check', 'opx-ico opx-ico--sm') ?> Identité complète</p>
                    <?php else: ?>
                        <p class="opx-complete__text"><strong><?= $missingCount ?> champ(s) à compléter :</strong>
                            <?php foreach ($c['missing'] as $n => $m): ?>
                                <a href="<?= $h(url('operateur/identite') . '?modifier=' . $id . '#' . ($m['key'] === 'photo' ? 'photo-' . $id : 'f-' . $id . '-' . $m['key'])) ?>"><?= $h($m['label']) ?></a><?= $n < $missingCount - 1 ? ', ' : '' ?>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <details class="opx-edit"<?= $open ? ' open' : '' ?>>
                    <summary>Modifier cette identité</summary>
                    <?= $identityForm($identity, $identity['is_primary']) ?>
                    <form class="opx-form opx-form--photo" id="photo-<?= $id ?>" method="post" enctype="multipart/form-data" action="<?= $h(url('operateur/identite/identites/' . $id . '/photo')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <fieldset class="opx-fieldset">
                            <legend>Photo d’identité</legend>
                            <p class="opx-note">Portrait de face, fond neutre. PNG, JPG ou WebP. L’image est recadrée au format 35 × 45 et réenregistrée sans ses données cachées.</p>
                            <div class="opx-photo-pick">
                                <span class="opx-photo-pick__preview" data-opx-photo-preview aria-hidden="true"></span>
                                <label class="opx-btn opx-btn--line" for="photo-input-<?= $id ?>">Choisir une photo</label>
                                <input class="opx-sr" id="photo-input-<?= $id ?>" type="file" name="photo" accept="image/png,image/jpeg,image/webp" data-opx-photo-input>
                                <button type="submit" class="opx-btn opx-btn--primary">Envoyer la photo</button>
                                <?php if (!empty($identity['photo_path'])): ?>
                                    <button type="submit" name="photo_remove" value="1" class="opx-btn opx-btn--quiet">Retirer la photo</button>
                                <?php endif; ?>
                            </div>
                        </fieldset>
                    </form>
                </details>
            <?php endif; ?>

            <div class="opx-identity__acts">
                <?php if (!$identity['archived'] && !$identity['active']): ?>
                    <form method="post" action="<?= $h(url('operateur/identite/identites/' . $id . '/activer')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <button type="submit" class="opx-btn opx-btn--line" title="Vos papiers émis de cette identité sont remis en jeu au prochain chargement">Utiliser en jeu</button>
                    </form>
                <?php endif; ?>
                <?php if (!$identity['is_primary']): ?>
                    <form method="post" action="<?= $h(url('operateur/identite/identites/' . $id . '/archiver')) ?>"<?php if (!$identity['archived']): ?> data-opx-confirm="Archiver cette identité ? Ses papiers restent enregistrés, vous pourrez la restaurer."<?php endif; ?>>
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <?php if ($identity['archived']): ?><input type="hidden" name="restaurer" value="1"><?php endif; ?>
                        <button type="submit" class="opx-btn opx-btn--quiet"><?= $identity['archived'] ? 'Restaurer' : 'Archiver' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </article>
<?php endforeach; ?>

<?php if ($hasPrimary): ?>
    <section class="opx-panel" id="nouvelle-identite">
        <h2>Identité d’emprunt</h2>
        <p class="opx-panel__lead">Un autre nom que votre personnage peut porter en mission, avec ses propres papiers. <?= (int) $aliasCount ?> sur <?= (int) \App\Services\Operator\OperatorIdentityService::MAX_ALIASES ?> utilisées.</p>
        <?php if ($aliasCount >= \App\Services\Operator\OperatorIdentityService::MAX_ALIASES): ?>
            <p class="opx-note">Limite atteinte. Archiver une identité ne libère pas de place : voyez un responsable pour en retirer une.</p>
        <?php else: ?>
            <details class="opx-edit">
                <summary>Créer une identité d’emprunt</summary>
                <?= $identityForm(null, false) ?>
            </details>
        <?php endif; ?>
    </section>
    <p class="opx-foot">Vos identités d’emprunt ne sont visibles que de vous et des responsables habilités de votre communauté. Aucun autre membre ne les voit.</p>
<?php endif; ?>
