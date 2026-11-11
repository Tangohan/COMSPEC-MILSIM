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
    'never' => ['Jamais récupérée en jeu', 'idle'],
    'synced' => ['Récupérée en jeu', 'ok'],
    'update' => ['Mise à jour disponible', 'warn'],
];
$statusLabels = ['valid' => null, 'expired' => ['Expirée', 'warn'], 'revoked' => ['Révoquée', 'bad'], 'invalid' => ['Fichier illisible', 'bad']];
?>

<section class="opx-panel" id="nouvelle-couverture">
    <h2>Créer une couverture</h2>
    <p class="opx-panel__lead">Une légende complète à jouer : nom, métier, adresse, famille, histoire et papier. Tout est tiré des listes du pays choisi, jamais d’une personne réelle. Vous l’ajustez ensuite.</p>
    <?php if ($idCountries === []): ?>
        <p class="opx-note">Aucun pays n’est actif dans votre communauté : les responsables doivent en activer un dans le registre des papiers.</p>
    <?php else: ?>
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
                            <input type="radio" name="source" value="athena" data-opx-cover-source<?= $hasPrimary ? '' : ' disabled' ?>>
                            <span><strong>Mon identité Athena</strong><em><?= $hasPrimary ? 'Votre identité principale et ses papiers émis, pour jouer sans attendre le serveur.' : 'Créez d’abord votre identité principale.' ?></em></span>
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
            <div class="opx-form__acts">
                <button type="submit" class="opx-btn opx-btn--primary"<?= $idCoverQuota < 1 ? ' disabled' : '' ?>>Créer la couverture</button>
                <span class="opx-quota"><?= (int) $idCoverQuota ?> création(s) restante(s) sur 24 heures.</span>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if ($idCovers === []): ?>
    <div class="opx-empty">
        <h2>Aucune couverture</h2>
        <p>Vous n’en avez pas besoin pour jouer. Créez-en une quand une mission demande de se faire passer pour quelqu’un d’autre.</p>
    </div>
<?php else: ?>
    <section class="opx-section" aria-labelledby="opx-covers-title">
        <div class="opx-section__head">
            <h2 id="opx-covers-title">Mes couvertures</h2>
            <p>En jeu : téléphone › Ma couverture › Synchroniser avec Athena. Le fichier reste téléchargeable pour jouer sans connexion.</p>
        </div>
        <ul class="opx-covers">
            <?php foreach ($idCovers as $cover): ?>
                <?php
                $cid = (int) $cover['id'];
                $open = $idEditId === $cid;
                [$syncLabel, $syncTone] = $syncLabels[$cover['sync']] ?? $syncLabels['never'];
                $status = $statusLabels[$cover['status']] ?? null;
                $actionUrl = url('operateur/identite/couverture/' . $cid);
                ?>
                <li class="opx-cover<?= $cover['status'] !== 'valid' ? ' is-off' : '' ?><?= $cover['active'] ? ' is-active' : '' ?>" id="couverture-<?= $cid ?>">
                    <div class="opx-cover__photo">
                        <?php if ($cover['photo_b64'] !== null): ?>
                            <img src="data:image/jpeg;base64,<?= $h($cover['photo_b64']) ?>" alt="Portrait fictif de <?= $h($cover['name']) ?>">
                        <?php else: ?>
                            <span class="opx-identity__nophoto" aria-hidden="true"><?= $opxIcon('mask', 'opx-ico opx-ico--xl') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="opx-cover__main">
                        <div class="opx-identity__head">
                            <h3 translate="no"><?= $h($cover['name']) ?></h3>
                            <div class="opx-pills">
                                <?php if ($cover['active']): ?><span class="opx-pill opx-pill--ok">Active</span><?php endif; ?>
                                <?php if ($status !== null): ?><span class="opx-pill opx-pill--<?= $h($status[1]) ?>"><?= $h($status[0]) ?></span><?php endif; ?>
                                <?php if ($cover['status'] === 'valid'): ?>
                                    <span class="opx-pill opx-pill--<?= $h($syncTone) ?>"><?= $h($syncLabel) ?><?= $cover['sync'] === 'synced' && $cover['downloaded_at'] !== null ? ' le ' . $h($dateFr($cover['downloaded_at'])) : '' ?></span>
                                <?php endif; ?>
                                <?php if ($cover['by_manager']): ?><span class="opx-pill opx-pill--info">Préparée par un responsable</span><?php endif; ?>
                            </div>
                        </div>
                        <p class="opx-cover__summary"><?= $h(implode(' · ', array_filter([$cover['profession'], $cover['city'], $cover['country']], static fn (string $v): bool => $v !== ''))) ?></p>
                        <dl class="opx-facts opx-facts--tight">
                            <div><dt>Référence</dt><dd translate="no"><?= $h($cover['ref']) ?></dd></div>
                            <div><dt>Créée le</dt><dd><?= $h($dateFr($cover['created_at'])) ?></dd></div>
                            <div><dt>Valable jusqu’au</dt><dd><?= $h($dateFr($cover['expires_at'])) ?></dd></div>
                            <div><dt>Visible par</dt><dd>Vous et les responsables habilités</dd></div>
                        </dl>

                        <details class="opx-edit">
                            <summary>Lire la légende</summary>
                            <dl class="opx-legend">
                                <div><dt>Né(e) le</dt><dd><?= $h($dateFr($cover['dob'])) ?><?= $cover['pob'] !== '' ? ' à ' . $h($cover['pob']) : '' ?></dd></div>
                                <div><dt>Adresse</dt><dd><?= $h($cover['address'] !== '' ? $cover['address'] : '—') ?></dd></div>
                                <div><dt>Employeur</dt><dd><?= $h($cover['employer'] !== '' ? $cover['employer'] : '—') ?></dd></div>
                                <div><dt>Téléphone</dt><dd translate="no"><?= $h($cover['phone'] !== '' ? $cover['phone'] : '—') ?></dd></div>
                                <div><dt>Motif du déplacement</dt><dd><?= $h($cover['travel'] !== '' ? $cover['travel'] : '—') ?></dd></div>
                                <div><dt>Famille</dt><dd><?= $h($cover['family'] !== '' ? $cover['family'] : '—') ?></dd></div>
                                <?php if ($cover['history'] !== []): ?>
                                    <div class="opx-legend__wide"><dt>Histoire</dt><dd><?= nl2br($h(implode("\n", $cover['history']))) ?></dd></div>
                                <?php endif; ?>
                                <?php if ($cover['docs'] !== []): ?>
                                    <div class="opx-legend__wide"><dt>Papiers de la légende</dt><dd>
                                        <?php foreach ($cover['docs'] as $d): ?>
                                            <span class="opx-legend__doc"><?= $h($docTypeLabels[$d['type']] ?? $d['type']) ?> <span translate="no"><?= $h($d['number']) ?></span> · <?= $h($d['country']) ?> · expire le <?= $h($dateFr($d['expires'])) ?></span>
                                        <?php endforeach; ?>
                                    </dd></div>
                                <?php endif; ?>
                                <?php if ($cover['contacts'] !== []): ?>
                                    <div class="opx-legend__wide"><dt>Personnes qui peuvent confirmer</dt><dd>
                                        <?php foreach ($cover['contacts'] as $ct): ?>
                                            <span class="opx-legend__doc"><span translate="no"><?= $h($ct[0] ?? '') ?></span> · <?= $h($ct[1] ?? '') ?> · <span translate="no"><?= $h($ct[2] ?? '') ?></span></span>
                                        <?php endforeach; ?>
                                    </dd></div>
                                <?php endif; ?>
                            </dl>
                        </details>

                        <?php if ($cover['editable']): ?>
                            <details class="opx-edit"<?= $open ? ' open' : '' ?>>
                                <summary>Ajuster la légende</summary>
                                <form class="opx-form" method="post" action="<?= $h($actionUrl) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <p class="opx-note">Le nom, la date de naissance et les papiers ne changent pas : ils sont réservés dans le registre. Après modification, resynchronisez le téléphone.</p>
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
                                        <label class="opx-fields__wide" for="cv-<?= $cid ?>-address">Adresse
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
                                    <div class="opx-form__acts">
                                        <button type="submit" name="action" value="edit" class="opx-btn opx-btn--primary">Enregistrer la légende</button>
                                    </div>
                                </form>
                            </details>
                        <?php endif; ?>

                        <div class="opx-identity__acts">
                            <?php if ($cover['status'] !== 'revoked' && $cover['status'] !== 'invalid'): ?>
                                <a class="opx-btn opx-btn--line" href="<?= $h($actionUrl . '/telecharger') ?>"><?= $opxIcon('download', 'opx-ico opx-ico--sm') ?> Télécharger</a>
                            <?php endif; ?>
                            <?php if ($cover['status'] === 'valid' && !$cover['active']): ?>
                                <form method="post" action="<?= $h($actionUrl) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <button type="submit" name="action" value="activate" class="opx-btn opx-btn--line">Définir active</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($cover['status'] !== 'invalid'): ?>
                                <form method="post" action="<?= $h($actionUrl) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <button type="submit" name="action" value="duplicate" class="opx-btn opx-btn--quiet"<?php if ($idCoverQuota < 1): ?> disabled title="Limite de créations atteinte sur 24 heures"<?php endif; ?>>Dupliquer</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($cover['status'] !== 'revoked'): ?>
                                <form method="post" action="<?= $h($actionUrl) ?>" data-opx-confirm="Révoquer cette couverture ? Elle ne sera plus téléchargeable ni proposée au téléphone. C’est définitif.">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <button type="submit" name="action" value="revoke" class="opx-btn opx-btn--quiet opx-btn--danger">Révoquer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <p class="opx-foot">Une couverture n’est visible que de vous et des responsables habilités de votre communauté. Elle n’apparaît dans aucun registre consulté par les autres membres.</p>
<?php endif; ?>
