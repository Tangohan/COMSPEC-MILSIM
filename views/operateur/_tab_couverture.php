<?php

declare(strict_types=1);

/**
 * Mon identité › Couvertures. Variables de views/operateur/identite.php.
 *
 * Couvertures générées sur Athena (sse_cover_exports) : légende, état, synchronisation avec le téléphone du jeu,
 * création guidée, retouche, copie, révocation, couverture active, téléchargement.
 */
$hasPrimary = false;
foreach ($idIdentities as $i) {
    $hasPrimary = $hasPrimary || ($i['is_primary'] && !$i['archived']);
}
$docTypeLabels = \App\Support\SseIdentityCatalog::DOC_TYPES;
$syncLabels = [
    'never' => ['Jamais récupérée en jeu', 'cc-badge--amber'],
    'synced' => ['Récupérée en jeu', 'cc-badge--ink'],
    'update' => ['Mise à jour disponible', 'cc-badge--urgent'],
];
$statusLabels = ['valid' => null, 'expired' => ['Expirée', 'cc-badge--amber'], 'revoked' => ['Révoquée', 'cc-badge--rose'], 'invalid' => ['Fichier illisible', 'cc-badge--rose']];
?>

<section class="dash-zone dash-zone--actions" aria-labelledby="opx-covers-title">
    <div class="dash-zone__inner">
        <header class="dash-zone__head">
            <h2 id="opx-covers-title" class="dash-zone__title">Mes couvertures</h2>
            <p class="dash-zone__lead">Une légende complète à jouer : nom, métier, adresse, famille, histoire et papier. En jeu : téléphone › Ma couverture › Synchroniser avec Athena.</p>
        </header>
        <div class="opx-acts">
            <?php if ($idCountries === []): ?>
                <p class="opx-note">Aucun pays n’est actif dans votre communauté : les responsables doivent en activer un dans le registre des papiers.</p>
            <?php else: ?>
                <button type="button" class="cc-btn cc-btn-primary" data-opx-open="dlg-couverture"<?php if ($idCoverQuota < 1): ?> disabled<?php endif; ?>><?= $opxIcon('plus', '') ?> Créer une couverture</button>
                <p class="opx-note"><?php if ($idCoverQuota === 1): ?>1 création restante sur 24 heures.<?php elseif ($idCoverQuota < 1): ?>Limite de créations atteinte : réessayez dans 24 heures.<?php else: ?><?= (int) $idCoverQuota ?> créations restantes sur 24 heures.<?php endif; ?></p>
            <?php endif; ?>
        </div>

        <?php if ($idCovers === []): ?>
            <div class="cc-empty">
                <p><strong>Aucune couverture.</strong> Vous n’en avez pas besoin pour jouer. Créez-en une quand une mission demande de se faire passer pour quelqu’un d’autre.</p>
            </div>
        <?php else: ?>
            <ul class="opx-cards">
                <?php foreach ($idCovers as $cover): ?>
                    <?php
                    $cid = (int) $cover['id'];
                    [$syncLabel, $syncClass] = $syncLabels[$cover['sync']] ?? $syncLabels['never'];
                    $status = $statusLabels[$cover['status']] ?? null;
                    $actionUrl = url('operateur/identite/couverture/' . $cid);
                    ?>
                    <li>
                        <article class="cc-card opx-card<?= $cover['status'] !== 'valid' ? ' is-off' : '' ?><?= $cover['active'] ? ' is-live' : '' ?>" id="couverture-<?= $cid ?>">
                            <div class="opx-card__body">
                                <div class="opx-card__top">
                                    <span class="opx-photo">
                                        <?php if ($cover['photo_b64'] !== null): ?>
                                            <img src="data:image/jpeg;base64,<?= $h($cover['photo_b64']) ?>" alt="Portrait fictif de <?= $h($cover['name']) ?>">
                                        <?php else: ?>
                                            <?= $opxIcon('mask', '') ?>
                                        <?php endif; ?>
                                    </span>
                                    <div>
                                        <h3 class="opx-card__title" translate="no"><?= $h($cover['name']) ?></h3>
                                        <p class="opx-card__sub"><?= $h(implode(' · ', array_filter([$cover['profession'], $cover['city'], $cover['country']], static fn (string $v): bool => $v !== ''))) ?></p>
                                        <div class="opx-card__badges">
                                            <?php if ($cover['active']): ?><span class="cc-badge cc-badge--live-solid"><span class="cc-badge__dot" aria-hidden="true"></span> Active</span><?php endif; ?>
                                            <?php if ($status !== null): ?><span class="cc-badge <?= $h($status[1]) ?>"><?= $h($status[0]) ?></span><?php endif; ?>
                                            <?php if ($cover['status'] === 'valid'): ?>
                                                <span class="cc-badge <?= $h($syncClass) ?>"><?= $h($syncLabel) ?><?php if ($cover['sync'] === 'synced' && $cover['downloaded_at'] !== null): ?> le <?= $h($dateFr($cover['downloaded_at'])) ?><?php endif; ?></span>
                                            <?php endif; ?>
                                            <?php if ($cover['by_manager']): ?><span class="cc-badge cc-badge--ink">Préparée par un responsable</span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <dl class="opx-facts opx-facts--4">
                                    <div><dt>Référence</dt><dd translate="no"><?= $h($cover['ref']) ?></dd></div>
                                    <div><dt>Créée le</dt><dd><?= $h($dateFr($cover['created_at'])) ?></dd></div>
                                    <div><dt>Valable jusqu’au</dt><dd><?= $h($dateFr($cover['expires_at'])) ?></dd></div>
                                    <div><dt>Visible par</dt><dd>Vous et les responsables habilités</dd></div>
                                </dl>
                            </div>
                            <div class="opx-card__foot opx-acts">
                                <button type="button" class="cc-btn cc-btn-primary" data-opx-open="dlg-legende-<?= $cid ?>">Lire la légende</button>
                                <?php if ($cover['editable']): ?>
                                    <button type="button" class="cc-btn cc-btn-ghost" data-opx-open="dlg-ajuster-<?= $cid ?>">Ajuster la légende</button>
                                <?php endif; ?>
                                <?php if ($cover['status'] !== 'revoked' && $cover['status'] !== 'invalid'): ?>
                                    <a class="cc-btn cc-btn-ghost" href="<?= $h($actionUrl . '/telecharger') ?>"><?= $opxIcon('download', '') ?> Télécharger</a>
                                <?php endif; ?>
                                <?php if ($cover['status'] === 'valid' && !$cover['active']): ?>
                                    <form method="post" action="<?= $h($actionUrl) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <button type="submit" name="action" value="activate" class="cc-btn cc-btn-ghost">Définir active</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($cover['status'] !== 'invalid'): ?>
                                    <form method="post" action="<?= $h($actionUrl) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <button type="submit" name="action" value="duplicate" class="cc-btn opx-btn-quiet"<?php if ($idCoverQuota < 1): ?> disabled title="Limite de créations atteinte sur 24 heures"<?php endif; ?>>Dupliquer</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($cover['status'] !== 'revoked'): ?>
                                    <form class="opx-acts__end" method="post" action="<?= $h($actionUrl) ?>" data-opx-confirm="Révoquer cette couverture ? Elle ne sera plus téléchargeable ni proposée au téléphone. C’est définitif.">
                                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                        <button type="submit" name="action" value="revoke" class="cc-btn opx-btn-quiet opx-btn-danger">Révoquer</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>

                        <?= $dialogOpen('dlg-legende-' . $cid, $cover['name'], 'Légende à tenir en jeu. Simulation fictive.') ?>
                            <dl class="opx-legend">
                                <div><dt>Né(e) le</dt><dd><?= $h($dateFr($cover['dob'])) ?><?= $cover['pob'] !== '' ? ' à ' . $h($cover['pob']) : '' ?></dd></div>
                                <div><dt>Adresse</dt><dd><?= $h($cover['address'] !== '' ? $cover['address'] : '—') ?></dd></div>
                                <div><dt>Métier</dt><dd><?= $h($cover['profession'] !== '' ? $cover['profession'] : '—') ?></dd></div>
                                <div><dt>Employeur</dt><dd><?= $h($cover['employer'] !== '' ? $cover['employer'] : '—') ?></dd></div>
                                <div><dt>Téléphone</dt><dd translate="no"><?= $h($cover['phone'] !== '' ? $cover['phone'] : '—') ?></dd></div>
                                <div><dt>Motif du déplacement</dt><dd><?= $h($cover['travel'] !== '' ? $cover['travel'] : '—') ?></dd></div>
                                <div class="opx-legend__wide"><dt>Famille</dt><dd><?= $h($cover['family'] !== '' ? $cover['family'] : '—') ?></dd></div>
                                <?php if ($cover['history'] !== []): ?>
                                    <div class="opx-legend__wide"><dt>Histoire</dt><dd><?= nl2br($h(implode("\n", $cover['history']))) ?></dd></div>
                                <?php endif; ?>
                                <?php if ($cover['docs'] !== []): ?>
                                    <div class="opx-legend__wide"><dt>Papiers de la légende</dt><dd>
                                        <?php foreach ($cover['docs'] as $d): ?>
                                            <span class="opx-legend__line"><?= $h($docTypeLabels[$d['type']] ?? $d['type']) ?> <span translate="no"><?= $h($d['number']) ?></span> · <?= $h($d['country']) ?> · expire le <?= $h($dateFr($d['expires'])) ?></span>
                                        <?php endforeach; ?>
                                    </dd></div>
                                <?php endif; ?>
                                <?php if ($cover['contacts'] !== []): ?>
                                    <div class="opx-legend__wide"><dt>Personnes qui peuvent confirmer</dt><dd>
                                        <?php foreach ($cover['contacts'] as $ct): ?>
                                            <span class="opx-legend__line"><span translate="no"><?= $h($ct[0] ?? '') ?></span> · <?= $h($ct[1] ?? '') ?> · <span translate="no"><?= $h($ct[2] ?? '') ?></span></span>
                                        <?php endforeach; ?>
                                    </dd></div>
                                <?php endif; ?>
                            </dl>
                        <?= $dialogClose ?>

                        <?php if ($cover['editable']): ?>
                            <?= $dialogOpen('dlg-ajuster-' . $cid, 'Ajuster la légende', 'Le nom, la date de naissance et les papiers ne changent pas : ils sont réservés dans le registre. Après modification, resynchronisez le téléphone.', $idEditId === $cid) ?>
                                <form class="opx-form" method="post" action="<?= $h($actionUrl) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <div class="opx-fields">
                                        <label for="cv-<?= $cid ?>-profession">Métier
                                            <input id="cv-<?= $cid ?>-profession" name="profession" type="text" maxlength="120" value="<?= $h($cover['profession']) ?>" placeholder="Que fait-il de ses journées ?">
                                        </label>
                                        <label for="cv-<?= $cid ?>-employer">Employeur
                                            <input id="cv-<?= $cid ?>-employer" name="employer" type="text" maxlength="190" value="<?= $h($cover['employer']) ?>" placeholder="Pour qui travaille-t-il ?">
                                        </label>
                                        <label for="cv-<?= $cid ?>-city">Ville
                                            <input id="cv-<?= $cid ?>-city" name="city" type="text" maxlength="120" value="<?= $h($cover['city']) ?>">
                                        </label>
                                        <label for="cv-<?= $cid ?>-address">Adresse
                                            <input id="cv-<?= $cid ?>-address" name="address" type="text" maxlength="255" value="<?= $h($cover['address']) ?>">
                                        </label>
                                        <label class="opx-fields__wide" for="cv-<?= $cid ?>-travel">Motif du déplacement
                                            <input id="cv-<?= $cid ?>-travel" name="travel" type="text" maxlength="255" value="<?= $h($cover['travel']) ?>" placeholder="Pourquoi est-il ici, aujourd’hui ?">
                                        </label>
                                        <label class="opx-fields__wide" for="cv-<?= $cid ?>-family">Famille
                                            <input id="cv-<?= $cid ?>-family" name="family" type="text" maxlength="190" value="<?= $h($cover['family']) ?>" placeholder="Qui l’attend à la maison ?">
                                        </label>
                                        <label class="opx-fields__wide" for="cv-<?= $cid ?>-history">Histoire <span class="opx-opt">une ligne par élément, 8 au plus</span>
                                            <textarea id="cv-<?= $cid ?>-history" name="history" rows="6" placeholder="Où a-t-il grandi ? Qu’a-t-il étudié ? Qu’est-ce qui l’a amené ici ?"><?= $h(implode("\n", $cover['history'])) ?></textarea>
                                        </label>
                                    </div>
                                    <div class="opx-dialog__foot">
                                        <button type="submit" name="action" value="edit" class="cc-btn cc-btn-primary">Enregistrer la légende</button>
                                    </div>
                                </form>
                            <?= $dialogClose ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="opx-note">Une couverture n’est visible que de vous et des responsables habilités de votre communauté. Elle n’apparaît dans aucun registre consulté par les autres membres.</p>
        <?php endif; ?>

        <?php if ($idCountries !== []): ?>
            <?= $dialogOpen('dlg-couverture', 'Créer une couverture', 'Tout est tiré des listes du pays choisi, jamais d’une personne réelle. Vous ajustez la légende ensuite.') ?>
                <form class="opx-form" method="post" enctype="multipart/form-data" action="<?= $h(url('operateur/identite/couverture')) ?>" data-opx-cover-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <ol class="opx-steps">
                        <li>
                            <h3>D’où part la légende</h3>
                            <div class="opx-choices">
                                <label class="opx-choice">
                                    <input type="radio" name="source" value="random" checked data-opx-cover-source>
                                    <span><strong>Une personne fictive</strong><em>Nom, métier et histoire plausibles pour le pays choisi.</em></span>
                                </label>
                                <label class="opx-choice<?= $hasPrimary ? '' : ' is-disabled' ?>">
                                    <input type="radio" name="source" value="athena" data-opx-cover-source<?php if (!$hasPrimary): ?> disabled<?php endif; ?>>
                                    <span><strong>Mon identité Athena</strong><em><?= $hasPrimary ? 'Votre identité principale et ses papiers émis, pour jouer sans attendre le serveur.' : 'Définissez d’abord votre identité principale.' ?></em></span>
                                </label>
                            </div>
                        </li>
                        <li data-opx-cover-random>
                            <h3>Le personnage</h3>
                            <div class="opx-fields">
                                <label for="cov-country">Pays
                                    <select id="cov-country" name="country">
                                        <option value="">Au hasard</option>
                                        <?php foreach ($idCountries as $c): ?>
                                            <option value="<?= $h($c['code']) ?>"><?= $h($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label for="cov-sex">Sexe
                                    <select id="cov-sex" name="sex">
                                        <option value="">Au hasard</option>
                                        <option value="M">Masculin</option>
                                        <option value="F">Féminin</option>
                                    </select>
                                </label>
                                <label class="opx-fields__wide" for="cov-photo">Portrait fictif <span class="opx-opt">facultatif</span>
                                    <input id="cov-photo" type="file" name="photo" accept="image/png,image/jpeg,image/webp">
                                </label>
                            </div>
                        </li>
                        <li>
                            <h3>La langue des textes</h3>
                            <div class="opx-fields">
                                <label for="cov-lang">Langue
                                    <select id="cov-lang" name="lang">
                                        <option value="fr">Français</option>
                                        <option value="en">English</option>
                                    </select>
                                </label>
                            </div>
                        </li>
                    </ol>
                    <div class="opx-dialog__foot">
                        <button type="submit" class="cc-btn cc-btn-primary"<?php if ($idCoverQuota < 1): ?> disabled<?php endif; ?>>Créer la couverture</button>
                    </div>
                </form>
            <?= $dialogClose ?>
        <?php endif; ?>
    </div>
</section>
