<?php

declare(strict_types=1);

/**
 * Mon identité › Identités. Variables de views/operateur/identite.php.
 *
 * Le membre voit « principale » ou « d'emprunt », jamais la nature exacte d'une identité (vérité de jeu).
 * Une seule identité porte la pastille « Active en jeu » : celle dont les papiers partent en mission.
 */
$hasPrimary = false;
foreach ($idIdentities as $i) {
    $hasPrimary = $hasPrimary || $i['is_primary'];
}
$aliasCount = count(array_filter($idIdentities, static fn (array $i): bool => !$i['is_primary']));
$aliasMax = \App\Services\Operator\OperatorIdentityService::MAX_ALIASES;
$confirmed = is_array($idSync) && $idSync['delivery'] === 'received';

/** Champs d'une identité (création : $identity null, valeurs proposées dans $defaults). */
$identityForm = static function (?array $identity, bool $primary, array $defaults = []) use ($h, $csrfToken, $idCountries, $idHair, $idEyes): string {
    $id = (int) ($identity['id'] ?? 0);
    $p = $identity['profile'] ?? [];
    $locked = !empty($identity['civil_locked']);
    $fid = static fn (string $k): string => 'f-' . ($id > 0 ? $id : ($primary ? 'main' : 'new')) . '-' . $k;
    $val = static fn (string $k) => $h((string) ($identity[$k] ?? $defaults[$k] ?? ''));
    $sex = (string) ($identity['sex'] ?? $defaults['sex'] ?? 'X');
    $nat = (string) ($identity['nationality'] ?? $defaults['nationality'] ?? '');
    ob_start(); ?>
    <form class="opx-form" method="post" action="<?= $h(url('operateur/identite/identites' . ($id > 0 ? '/' . $id : ''))) ?>">
        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
        <?php if ($locked): ?>
            <p class="opx-note">Cette identité porte déjà un papier émis : son état civil ne se modifie plus ici (voyez un responsable). Le signalement et le métier restent modifiables.</p>
        <?php endif; ?>
        <fieldset class="opx-fieldset">
            <legend>État civil</legend>
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
        </fieldset>
        <fieldset class="opx-fieldset">
            <legend>Signalement</legend>
            <p class="opx-note">Repris sur vos papiers et sur votre fiche aux fichiers.</p>
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
        </fieldset>
        <div class="opx-dialog__foot">
            <button type="submit" class="cc-btn cc-btn-primary"><?= $id > 0 ? 'Enregistrer' : ($primary ? 'Définir mon identité principale' : 'Créer cette identité') ?></button>
        </div>
    </form>
    <?php return (string) ob_get_clean();
};
?>

<?php if (!$hasPrimary): ?>
    <section class="dash-zone dash-zone--actions" id="nouvelle-identite" aria-labelledby="opx-main-title">
        <div class="dash-zone__inner">
            <header class="dash-zone__head">
                <h2 id="opx-main-title" class="dash-zone__title">Définir mon identité principale</h2>
                <p class="dash-zone__lead">C’est l’identité de votre personnage : celle de vos papiers et de votre fiche aux fichiers. Les champs sont repris de votre profil quand il les connaît ; corrigez-les, le reste se complète plus tard.</p>
            </header>
            <article class="cc-card">
                <div class="opx-card__body">
                    <?= $identityForm(null, true, $idProposal) ?>
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
                <p class="dash-zone__lead">L’identité principale est celle de votre personnage. Une seule identité est active en jeu : ses papiers émis vous sont remis en mission.</p>
            </header>
            <div class="opx-acts">
                <button type="button" class="cc-btn cc-btn-ghost" data-opx-open="dlg-photo-guide"><?= $opxIcon('camera', '') ?> Comment faire ma photo ?</button>
                <p class="opx-note">La photo d’identité de votre personnage, à faire avec un générateur d’image : étapes et prompt à copier.</p>
            </div>
            <ul class="opx-cards">
                <?php foreach ($idIdentities as $identity): ?>
                    <?php
                    $id = (int) $identity['id'];
                    $c = $identity['completeness'];
                    $missingCount = count($c['missing']);
                    $missingPhoto = in_array('photo', array_column($c['missing'], 'key'), true);
                    $name = $identity['display_name'] !== '' ? $identity['display_name'] : 'Identité sans nom';
                    $signalement = implode(' · ', array_filter([
                        $identity['profile']['height_cm'] !== null ? $identity['profile']['height_cm'] . ' cm' : null,
                        $identity['profile']['eyes'] !== null ? 'yeux ' . mb_strtolower($idEyes[$identity['profile']['eyes']] ?? '') : null,
                        $identity['profile']['hair'] !== null ? 'cheveux ' . mb_strtolower($idHair[$identity['profile']['hair']] ?? '') : null,
                    ]));
                    $papers = (int) $identity['documents_count'];
                    ?>
                    <li>
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
                                            <span class="cc-badge cc-badge--ink"><?= $identity['is_primary'] ? 'Identité principale' : 'Identité d’emprunt' ?></span>
                                            <?php if ($identity['active']): ?>
                                                <span class="cc-badge cc-badge--live-solid"><span class="cc-badge__dot" aria-hidden="true"></span> Active en jeu</span>
                                            <?php endif; ?>
                                            <?php if ($identity['archived']): ?><span class="cc-badge cc-badge--amber">Archivée</span><?php endif; ?>
                                        </div>
                                        <?php if ($identity['active']): ?>
                                            <p class="opx-card__sub">
                                                <?php if ($confirmed): ?>
                                                    Papiers confirmés par le jeu<?php if ($idSync['at'] !== ''): ?> le <?= $h($dateFr($idSync['at'])) ?><?php endif; ?>.
                                                <?php else: ?>
                                                    Ses papiers émis partent en mission. Le jeu n’a pas encore confirmé les avoir reçus.
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <dl class="opx-facts opx-facts--6">
                                    <div><dt>Né(e) le</dt><dd><?= $h($dateFr($identity['birth_date'])) ?></dd></div>
                                    <div><dt>Lieu de naissance</dt><dd><?= $h($identity['birth_place'] ?: '—') ?></dd></div>
                                    <div><dt>Nationalité</dt><dd><?= $h($countryName($identity['nationality'])) ?></dd></div>
                                    <div><dt>Signalement</dt><dd><?= $h($signalement !== '' ? $signalement : 'À renseigner') ?></dd></div>
                                    <div><dt>Papiers</dt><dd><a href="<?= $h(url('operateur/identite/documents') . '#papiers-' . $id) ?>"><?php if ($papers === 0): ?>Aucun papier<?php elseif ($papers === 1): ?>1 papier<?php else: ?><?= $papers ?> papiers<?php endif; ?></a></dd></div>
                                    <?php $builtOn = $idCoverUsage['by_identity'][$id] ?? []; ?>
                                    <div><dt>Couvertures</dt><dd>
                                        <?php if ($builtOn !== []): ?>
                                            <a href="<?= $h(url('operateur/identite/couverture') . '#couverture-' . (int) $builtOn[0]['id']) ?>"><?php if (count($builtOn) === 1): ?>1 couverture<?php else: ?><?= count($builtOn) ?> couvertures<?php endif; ?></a>
                                        <?php elseif (!$identity['archived']): ?>
                                            <a href="<?= $h(url('operateur/identite/couverture') . '?identite=' . $id) ?>">Aucune, en créer une</a>
                                        <?php else: ?>
                                            Aucune
                                        <?php endif; ?>
                                    </dd></div>
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
                            </div>
                            <div class="opx-card__foot opx-acts">
                                <?php if (!$identity['archived']): ?>
                                    <button type="button" class="cc-btn cc-btn-primary" data-opx-open="dlg-identite-<?= $id ?>">Modifier</button>
                                    <?php if (!empty($identity['photo_path'])): ?>
                                        <button type="button" class="cc-btn cc-btn-ghost" data-opx-open="dlg-photo-<?= $id ?>">Changer la photo</button>
                                    <?php endif; ?>
                                    <?php if (!$identity['active']): ?>
                                        <form method="post" action="<?= $h(url('operateur/identite/identites/' . $id . '/activer')) ?>">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                            <button type="submit" class="cc-btn cc-btn-ghost" title="Ses papiers émis vous seront remis en mission à la place de ceux de l’identité active">Utiliser en jeu</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!empty($identity['promotable'])): ?>
                                        <form method="post" action="<?= $h(url('operateur/identite/identites/' . $id . '/principale')) ?>" data-opx-confirm="Faire de cette identité votre identité principale ? L’identité principale actuelle devient une identité d’emprunt. Rien n’est supprimé.">
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                            <button type="submit" class="cc-btn cc-btn-ghost">Définir comme principale</button>
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
                                <?= $identityForm($identity, $identity['is_primary']) ?>
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
        </div>
    </section>
<?php endif; ?>

<?php if ($hasPrimary): ?>
    <section class="dash-zone" id="nouvelle-identite" aria-labelledby="opx-alias-title">
        <div class="dash-zone__inner">
            <header class="dash-zone__head">
                <h2 id="opx-alias-title" class="dash-zone__title">Identité d’emprunt</h2>
                <p class="dash-zone__lead">Un autre nom que votre personnage peut porter en mission, avec ses propres papiers. Visible de vous et des responsables habilités seulement.</p>
            </header>
            <div class="opx-acts">
                <?php if ($aliasCount >= $aliasMax): ?>
                    <p class="opx-note"><?= (int) $aliasCount ?> identités d’emprunt sur <?= (int) $aliasMax ?> : limite atteinte. Archiver une identité ne libère pas de place, voyez un responsable pour en retirer une.</p>
                <?php else: ?>
                    <button type="button" class="cc-btn cc-btn-primary" data-opx-open="dlg-alias"><?= $opxIcon('plus', '') ?> Créer une identité d’emprunt</button>
                    <p class="opx-note"><?php if ($aliasCount <= 1): ?><?= (int) $aliasCount ?> identité d’emprunt sur <?= (int) $aliasMax ?><?php else: ?><?= (int) $aliasCount ?> identités d’emprunt sur <?= (int) $aliasMax ?><?php endif; ?></p>
                <?php endif; ?>
            </div>
            <?php if ($aliasCount < $aliasMax): ?>
                <?= $dialogOpen('dlg-alias', 'Nouvelle identité d’emprunt', 'Elle reçoit ensuite ses propres papiers, à demander dans l’onglet Papiers.') ?>
                    <?= $identityForm(null, false) ?>
                <?= $dialogClose ?>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
