<?php

declare(strict_types=1);

/**
 * Mon identité › Couvertures. Variables de views/operateur/identite.php.
 *
 * Une couverture est bâtie sur une identité du membre et emporte des papiers de cette identité
 * (OperatorIdentityService::linkedCovers) : chaque carte montre l'identité utilisée, les papiers emportés en vignettes,
 * ce qui est à traiter et les indications de cohérence. Création guidée (identité, papiers, légende, récapitulatif),
 * choix des papiers, retouche, copie, révocation, couverture active, téléchargement. Une couverture sans lien est dite
 * « non rattachée » (personne fictive, ou couverture d'avant le lien).
 */
require __DIR__ . '/_document_svg.php';
$docTypeLabels = \App\Support\SseIdentityCatalog::DOC_TYPES;
$syncLabels = [
    'never' => ['Jamais récupérée en jeu', 'cc-badge--amber'],
    'synced' => ['Récupérée en jeu', 'cc-badge--ink'],
    'update' => ['Mise à jour disponible', 'cc-badge--urgent'],
];
$statusLabels = ['valid' => null, 'expired' => ['Expirée', 'cc-badge--amber'], 'revoked' => ['Révoquée', 'cc-badge--rose'], 'invalid' => ['Fichier illisible', 'cc-badge--rose']];
$statusBadge = ['ok' => 'cc-badge--ink', 'warn' => 'cc-badge--amber', 'wait' => 'cc-badge--amber', 'bad' => 'cc-badge--rose'];
$docsByIdentity = [];
foreach ($idDocuments as $d) {
    $docsByIdentity[(int) $d['identity_id']][] = $d;
}
$countriesByCode = [];
foreach ($idCountries as $c) {
    $countriesByCode[(string) $c['code']] = $c;
}
$requestUrl = url('operateur/identite/documents');
// Identité proposée d'abord dans la création guidée : celle demandée par le lien, sinon la principale.
$wizardIdentity = 0;
foreach ($activeIdentities as $i) {
    if ((int) $i['id'] === $idCoverIdentity || ($wizardIdentity === 0 && $i['is_primary'])) {
        $wizardIdentity = (int) $i['id'];
    }
}
if ($wizardIdentity === 0 && $activeIdentities !== []) {
    $wizardIdentity = (int) $activeIdentities[0]['id'];
}
// Vignette d'un papier : aperçu recto, type, numéro, validité, état.
$paperThumb = static function (array $doc, string $prefix, string $extra = '') use ($h, $opxDocSvg, $dateFr, $statusBadge): string {
    $photo = !empty($doc['photo_path']) ? url((string) $doc['photo_path']) : null;
    // Identifiant propre à la vignette : le même papier peut être dessiné plusieurs fois dans la page.
    $svg = $opxDocSvg(['id' => (int) ($prefix . $doc['id'])] + $doc, 'recto', $photo);

    return '<a class="opx-paper__view" href="' . $h(url('operateur/identite/documents') . '#papier-' . (int) $doc['id']) . '">' . $svg . '</a>'
        . '<div class="opx-paper__text"><strong>' . $h($doc['doc_type_label']) . '</strong>'
        . '<span translate="no">' . $h($doc['doc_number'] !== '' ? $doc['doc_number'] : '—') . '</span>'
        . '<span>' . $h($doc['country_name']) . ' · expire le ' . $h($dateFr((string) $doc['expires_on'])) . '</span>'
        . '<span class="cc-badge ' . $h($statusBadge[$doc['status_tone']] ?? 'cc-badge--amber') . '">' . $h($doc['status_label']) . '</span>' . $extra . '</div>';
};
?>

<section class="dash-zone dash-zone--actions" aria-labelledby="opx-covers-title">
    <div class="dash-zone__inner">
        <header class="dash-zone__head">
            <h2 id="opx-covers-title" class="dash-zone__title">Mes couvertures</h2>
            <p class="dash-zone__lead">Une couverture se bâtit sur une de vos identités et emporte ses papiers : le téléphone du jeu reçoit la légende et exactement ces papiers. En jeu : téléphone › Ma couverture › Synchroniser avec Athena.</p>
        </header>
        <div class="opx-acts">
            <?php if ($idCountries === []): ?>
                <p class="opx-note">Aucun pays n’est actif dans votre communauté : les responsables doivent en activer un dans le registre des papiers.</p>
            <?php else: ?>
                <button type="button" class="cc-btn cc-btn-primary" data-opx-open="dlg-couverture"<?php if ($idCoverQuota < 1): ?> disabled<?php endif; ?>><?= $opxIcon('plus', '') ?> Créer une couverture</button>
                <button type="button" class="cc-btn opx-btn-quiet" data-opx-open="dlg-couverture-fictive"<?php if ($idCoverQuota < 1): ?> disabled<?php endif; ?>>Personne fictive, sans identité ni papiers Athena</button>
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
                    $on = is_array($cover['identity']) ? $cover['identity'] : null;
                    $onName = $on !== null ? ($on['display_name'] !== '' ? (string) $on['display_name'] : 'Identité sans nom') : '';
                    $onUrl = $on !== null ? url('operateur/identite') . '#identite-' . (int) $on['id'] : '';
                    $selectable = array_merge(array_values(array_filter($cover['papers'], static fn (array $p): bool => $p['status'] === 'valid')), $cover['addable']);
                    $canPick = $on !== null && $cover['editable'];
                    ?>
                    <li>
                        <article class="cc-card opx-card<?= $cover['status'] !== 'valid' ? ' is-off' : '' ?><?= $cover['active'] ? ' is-live' : '' ?>" id="couverture-<?= $cid ?>">
                            <div class="opx-card__body">
                                <div class="opx-card__top">
                                    <span class="opx-photo">
                                        <?php if ($on !== null && !empty($on['photo_path'])): ?>
                                            <img src="<?= $h(url((string) $on['photo_path'])) ?>" alt="Photo d’identité de <?= $h($onName) ?>" loading="lazy">
                                        <?php elseif ($cover['photo_b64'] !== null): ?>
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
                                            <?php if ($on === null): ?><span class="cc-badge cc-badge--amber">Non rattachée</span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($on !== null): ?>
                                    <div class="opx-built">
                                        <div class="opx-built__col">
                                            <h4 class="opx-built__title">Identité utilisée</h4>
                                            <a class="opx-built__id" href="<?= $h($onUrl) ?>">
                                                <span class="opx-photo opx-photo--mini">
                                                    <?php if (!empty($on['photo_path'])): ?>
                                                        <img src="<?= $h(url((string) $on['photo_path'])) ?>" alt="" loading="lazy">
                                                    <?php else: ?>
                                                        <?= $opxIcon('user', '') ?>
                                                    <?php endif; ?>
                                                </span>
                                                <span>
                                                    <strong translate="no"><?= $h(trim($on['first_name'] . ' ' . mb_strtoupper((string) $on['last_name'])) !== '' ? trim($on['first_name'] . ' ' . mb_strtoupper((string) $on['last_name'])) : $onName) ?></strong>
                                                    <em><?= $on['is_primary'] ? 'Identité principale' : 'Identité d’emprunt' ?><?= $on['archived'] ? ' · archivée' : '' ?></em>
                                                    <em>Nationalité : <?= $h($countryName($on['nationality'])) ?></em>
                                                </span>
                                            </a>
                                        </div>
                                        <div class="opx-built__col">
                                            <h4 class="opx-built__title"><?php if (count($cover['papers']) === 0): ?>Papiers emportés<?php elseif (count($cover['papers']) === 1): ?>1 papier emporté<?php else: ?><?= count($cover['papers']) ?> papiers emportés<?php endif; ?></h4>
                                            <?php if ($cover['papers'] !== [] || $cover['pending'] !== []): ?>
                                                <ul class="opx-papers">
                                                    <?php foreach ($cover['papers'] as $paper): ?>
                                                        <li class="opx-paper"><?= $paperThumb($paper, '7' . $cid . '0') ?></li>
                                                    <?php endforeach; ?>
                                                    <?php foreach ($cover['pending'] as $paper): ?>
                                                        <li class="opx-paper is-waiting"><?= $paperThumb($paper, '8' . $cid . '0', '<span class="opx-paper__note">Pas dans la couverture</span>') ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($cover['warnings'] !== [] || $cover['hints'] !== []): ?>
                                        <ul class="opx-checks">
                                            <?php foreach ($cover['warnings'] as $w): ?>
                                                <?php $wd = is_array($w['doc'] ?? null) ? $w['doc'] : ['doc_type_label' => '', 'doc_number' => '']; ?>
                                                <li class="opx-check opx-check--warn"><?= $opxIcon('alert', '') ?>
                                                    <span>
                                                        <?php if ($w['code'] === 'no_paper'): ?>
                                                            <strong>Aucun papier pour cette couverture.</strong> En jeu, ce personnage n’aura rien à présenter à un contrôle.
                                                        <?php elseif ($w['code'] === 'paper_waiting'): ?>
                                                            <strong>Papier demandé, pas encore émis :</strong> <?= $h($wd['doc_type_label']) ?>. Ajoutez-le à la couverture quand un responsable l’aura émis.
                                                        <?php elseif ($w['code'] === 'paper_expired'): ?>
                                                            <strong>Papier expiré :</strong> <?= $h($wd['doc_type_label']) ?> <?= $h($wd['doc_number']) ?>. Demandez son renouvellement dans l’onglet Papiers.
                                                        <?php elseif ($w['code'] === 'paper_lost'): ?>
                                                            <strong>Papier déclaré perdu :</strong> <?= $h($wd['doc_type_label']) ?> <?= $h($wd['doc_number']) ?>. Le présenter en jeu déclenche une alerte.
                                                        <?php elseif ($w['code'] === 'paper_stolen'): ?>
                                                            <strong>Papier déclaré volé :</strong> <?= $h($wd['doc_type_label']) ?> <?= $h($wd['doc_number']) ?>. Le présenter en jeu déclenche une alerte.
                                                        <?php else: ?>
                                                            <strong>Papier qui n’est plus valable :</strong> <?= $h($wd['doc_type_label']) ?> <?= $h($wd['doc_number']) ?>. Retirez-le de la couverture.
                                                        <?php endif; ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                            <?php foreach ($cover['hints'] as $w): ?>
                                                <?php $wd = is_array($w['doc'] ?? null) ? $w['doc'] : ['doc_type_label' => '', 'doc_number' => '', 'country_name' => '', 'issued_on' => null, 'expires_on' => null]; ?>
                                                <li class="opx-check"><?= $opxIcon('search', '') ?>
                                                    <span>
                                                        <?php if ($w['code'] === 'paper_country'): ?>
                                                            À vérifier : <?= $h($wd['doc_type_label']) ?> délivré par <?= $h($wd['country_name']) ?>, alors que l’identité est de nationalité <?= $h($countryName($on['nationality'])) ?>.
                                                        <?php elseif ($w['code'] === 'paper_before_birth'): ?>
                                                            À vérifier : <?= $h($wd['doc_type_label']) ?> délivré le <?= $h($dateFr((string) $wd['issued_on'])) ?>, avant la date de naissance de l’identité.
                                                        <?php elseif ($w['code'] === 'paper_expires_first'): ?>
                                                            À vérifier : <?= $h($wd['doc_type_label']) ?> expire le <?= $h($dateFr((string) $wd['expires_on'])) ?>, avant la fin de validité de la couverture.
                                                        <?php elseif ($w['code'] === 'name'): ?>
                                                            À vérifier : le nom de la légende (<?= $h($cover['name']) ?>) ne s’écrit pas comme celui de l’identité (<?= $h($onName) ?>).
                                                        <?php elseif ($w['code'] === 'dob'): ?>
                                                            À vérifier : la légende donne une naissance le <?= $h($dateFr($cover['dob'])) ?>, l’identité le <?= $h($dateFr((string) $on['birth_date'])) ?>.
                                                        <?php else: ?>
                                                            À vérifier : le fichier envoyé au jeu ne porte pas exactement ces papiers. Enregistrez de nouveau le choix des papiers pour le réécrire.
                                                        <?php endif; ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <?php if ($canPick || $cover['missing'] !== []): ?>
                                        <div class="opx-acts">
                                            <?php if ($canPick && $cover['addable'] !== []): ?>
                                                <button type="button" class="cc-btn cc-btn-ghost" data-opx-open="dlg-papiers-<?= $cid ?>"><?= $opxIcon('plus', '') ?> Ajouter un papier à cette couverture</button>
                                            <?php elseif ($canPick && $selectable !== []): ?>
                                                <button type="button" class="cc-btn cc-btn-ghost" data-opx-open="dlg-papiers-<?= $cid ?>">Choisir les papiers emportés</button>
                                            <?php endif; ?>
                                            <?php foreach ($cover['missing'] as $m): ?>
                                                <span class="opx-ask"><?= $h($m['label']) ?> · <?= $h($m['country_name']) ?>
                                                    <button type="submit" form="opx-cover-request" name="demande" value="<?= $h((int) $on['id'] . ':' . $m['type'] . ':' . $m['country_code']) ?>" class="cc-btn cc-btn-ghost">Demander ce papier</button>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="opx-checks">
                                        <p class="opx-check opx-check--warn"><?= $opxIcon('alert', '') ?>
                                            <span>
                                                <strong>Couverture non rattachée.</strong>
                                                <?php if ($cover['docs'] === []): ?>
                                                    Elle ne repose sur aucune de vos identités et n’emporte aucun papier.
                                                <?php else: ?>
                                                    Elle ne repose sur aucune de vos identités : ses papiers sont inventés et n’existent pas dans l’onglet Papiers.
                                                <?php endif; ?>
                                                <?php if ($cover['candidate_id'] === null): ?>
                                                    Pour une couverture liée à vos papiers, créez-en une sur une de vos identités.
                                                <?php endif; ?>
                                            </span>
                                        </p>
                                        <?php if ($cover['candidate_id'] !== null): ?>
                                            <form class="opx-acts" method="post" action="<?= $h($actionUrl) ?>">
                                                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                                <button type="submit" name="action" value="attach" class="cc-btn cc-btn-ghost"><?= $opxIcon('link', '') ?> Rattacher à une identité</button>
                                                <p class="opx-note">Une de vos identités porte ce nom et cette date de naissance.</p>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

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
                                    <div class="opx-legend__wide"><dt>Papiers écrits dans le fichier du jeu</dt><dd>
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
                            <?= $dialogOpen('dlg-ajuster-' . $cid, 'Ajuster la légende', 'Le nom et la date de naissance viennent de l’identité et ne changent pas ici ; les papiers se choisissent sur la carte de la couverture. Après modification, resynchronisez le téléphone.', $idEditId === $cid && $idCoverWindow === '') ?>
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

                        <?php if ($canPick && $selectable !== []): ?>
                            <?= $dialogOpen('dlg-papiers-' . $cid, 'Papiers de la couverture', 'Seuls les papiers émis et valables de l’identité de cette couverture peuvent être emportés. Le fichier du jeu est réécrit avec exactement ce choix : resynchronisez ensuite le téléphone.', $idEditId === $cid && $idCoverWindow === 'papiers') ?>
                                <form class="opx-form" method="post" action="<?= $h($actionUrl) ?>">
                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                    <ul class="opx-papers opx-papers--pick">
                                        <?php foreach ($selectable as $paper): ?>
                                            <li>
                                                <label class="opx-paper opx-paper--pick">
                                                    <input type="checkbox" name="documents[]" value="<?= (int) $paper['id'] ?>"<?php if (in_array((int) $paper['id'], $cover['document_ids'], true)): ?> checked<?php endif; ?>>
                                                    <?= $paperThumb($paper, '9' . $cid . '0') ?>
                                                </label>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="opx-dialog__foot">
                                        <button type="submit" name="action" value="papers" class="cc-btn cc-btn-primary">Enregistrer les papiers</button>
                                    </div>
                                </form>
                            <?= $dialogClose ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="opx-note">Une couverture n’est visible que de vous et des responsables habilités de votre communauté. Elle n’apparaît dans aucun registre consulté par les autres membres.</p>
        <?php endif; ?>

        <form id="opx-cover-request" method="post" action="<?= $h($requestUrl) ?>" hidden>
            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
            <input type="hidden" name="retour" value="couverture">
        </form>

        <?php if ($idCountries !== []): ?>
            <?= $dialogOpen('dlg-couverture', 'Créer une couverture', 'Quatre étapes : l’identité, ses papiers, la légende, puis la vérification. Tout reste une simulation fictive.', $idCoverIdentity > 0) ?>
                <?php if ($activeIdentities === []): ?>
                    <div class="cc-empty">
                        <p><strong>Pas encore d’identité.</strong> Une couverture se bâtit sur une identité et sur ses papiers : définissez d’abord la vôtre.</p>
                        <a class="cc-btn cc-btn-primary" href="<?= $h(url('operateur/identite')) ?>">Définir mon identité</a>
                    </div>
                <?php else: ?>
                    <form class="opx-form" method="post" action="<?= $h(url('operateur/identite/couverture')) ?>" data-opx-cover-form>
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <input type="hidden" name="source" value="athena">
                        <ol class="opx-steps">
                            <li>
                                <h3>L’identité</h3>
                                <div class="opx-choices">
                                    <?php foreach ($activeIdentities as $identity): ?>
                                        <?php
                                        $iid = (int) $identity['id'];
                                        $iname = $identity['display_name'] !== '' ? (string) $identity['display_name'] : 'Identité sans nom';
                                        $validCount = count(array_filter($docsByIdentity[$iid] ?? [], static fn (array $d): bool => $d['status'] === 'valid'));
                                        ?>
                                        <label class="opx-choice">
                                            <input type="radio" name="identity_id" value="<?= $iid ?>" data-opx-cover-identity data-opx-label="<?= $h($iname) ?>"<?php if ($iid === $wizardIdentity): ?> checked<?php endif; ?> required>
                                            <span><strong translate="no"><?= $h($iname) ?></strong><em><?= $identity['is_primary'] ? 'Identité principale' : 'Identité d’emprunt' ?> · <?= $h($countryName($identity['nationality'])) ?> · <?php if ($validCount === 0): ?>aucun papier valable<?php elseif ($validCount === 1): ?>1 papier valable<?php else: ?><?= $validCount ?> papiers valables<?php endif; ?></em></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="opx-note">Il vous faut un autre nom ? <a href="<?= $h(url('operateur/identite') . '#nouvelle-identite') ?>">Créez d’abord une identité d’emprunt.</a></p>
                            </li>
                            <li>
                                <h3>Ses papiers</h3>
                                <?php foreach ($activeIdentities as $identity): ?>
                                    <?php
                                    $iid = (int) $identity['id'];
                                    $iname = $identity['display_name'] !== '' ? (string) $identity['display_name'] : 'Identité sans nom';
                                    $idDocs = $docsByIdentity[$iid] ?? [];
                                    $valid = array_values(array_filter($idDocs, static fn (array $d): bool => $d['status'] === 'valid'));
                                    $waiting = array_values(array_filter($idDocs, static fn (array $d): bool => in_array($d['status'], ['requested', 'pending'], true)));
                                    $missing = \App\Services\Operator\OperatorIdentityService::missingPapers($identity, $idDocs, $countriesByCode);
                                    ?>
                                    <div class="opx-pick" data-opx-cover-papers="<?= $iid ?>">
                                        <p class="opx-pick__who">Papiers de <?= $h($iname) ?></p>
                                        <?php if ($valid === []): ?>
                                            <p class="opx-check opx-check--warn"><?= $opxIcon('alert', '') ?><span><strong>Aucun papier émis et valable pour cette identité.</strong> La couverture peut être créée sans papier, puis complétée quand un papier sera émis.</span></p>
                                        <?php else: ?>
                                            <ul class="opx-papers opx-papers--pick">
                                                <?php foreach ($valid as $paper): ?>
                                                    <li>
                                                        <label class="opx-paper opx-paper--pick">
                                                            <input type="checkbox" name="documents[<?= $iid ?>][]" value="<?= (int) $paper['id'] ?>" checked data-opx-label="<?= $h($paper['doc_type_label'] . ' ' . $paper['doc_number']) ?>">
                                                            <?= $paperThumb($paper, '6' . $iid . '0') ?>
                                                        </label>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <?php foreach ($waiting as $paper): ?>
                                            <p class="opx-note"><?= $h($paper['doc_type_label']) ?> : <?= $h(mb_strtolower((string) $paper['status_label'])) ?>. Vous pourrez l’ajouter à la couverture une fois émis.</p>
                                        <?php endforeach; ?>
                                        <?php if ($missing !== []): ?>
                                            <div class="opx-acts">
                                                <?php foreach ($missing as $m): ?>
                                                    <span class="opx-ask"><?= $h($m['label']) ?> · <?= $h($m['country_name']) ?>
                                                        <button type="submit" form="opx-cover-request" name="demande" value="<?= $h($iid . ':' . $m['type'] . ':' . $m['country_code']) ?>" class="cc-btn cc-btn-ghost" formnovalidate>Demander ce papier</button>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </li>
                            <li>
                                <h3>La légende</h3>
                                <p class="opx-note">Le nom, la date et le lieu de naissance viennent de l’identité. Ce que vous laissez vide est proposé par Athena, plausible pour le pays de l’identité ; tout se retouche ensuite.</p>
                                <div class="opx-fields">
                                    <label for="cov-profession">Métier <span class="opx-opt">facultatif</span>
                                        <input id="cov-profession" name="profession" type="text" maxlength="120" placeholder="Que fait-il de ses journées ?">
                                    </label>
                                    <label for="cov-employer">Employeur <span class="opx-opt">facultatif</span>
                                        <input id="cov-employer" name="employer" type="text" maxlength="190" placeholder="Pour qui travaille-t-il ?">
                                    </label>
                                    <label for="cov-city">Ville <span class="opx-opt">facultatif</span>
                                        <input id="cov-city" name="city" type="text" maxlength="120">
                                    </label>
                                    <label for="cov-address">Adresse <span class="opx-opt">facultatif</span>
                                        <input id="cov-address" name="address" type="text" maxlength="255">
                                    </label>
                                    <label class="opx-fields__wide" for="cov-travel">Motif du déplacement <span class="opx-opt">facultatif</span>
                                        <input id="cov-travel" name="travel" type="text" maxlength="255" placeholder="Pourquoi est-il ici, aujourd’hui ?">
                                    </label>
                                    <label class="opx-fields__wide" for="cov-family">Famille <span class="opx-opt">facultatif</span>
                                        <input id="cov-family" name="family" type="text" maxlength="190" placeholder="Qui l’attend à la maison ?">
                                    </label>
                                    <label class="opx-fields__wide" for="cov-history">Histoire <span class="opx-opt">facultatif, une ligne par élément, 8 au plus</span>
                                        <textarea id="cov-history" name="history" rows="4" placeholder="Où a-t-il grandi ? Qu’a-t-il étudié ? Qu’est-ce qui l’a amené ici ?"></textarea>
                                    </label>
                                    <label for="cov-lang">Langue des textes proposés
                                        <select id="cov-lang" name="lang">
                                            <option value="fr">Français</option>
                                            <option value="en">English</option>
                                        </select>
                                    </label>
                                </div>
                            </li>
                            <li>
                                <h3>Vérification</h3>
                                <dl class="opx-legend" data-opx-cover-recap hidden>
                                    <div><dt>Identité</dt><dd translate="no" data-opx-recap-identity></dd></div>
                                    <div><dt>Papiers emportés</dt><dd>
                                        <span data-opx-recap-papers translate="no"></span>
                                        <span data-opx-recap-none hidden>Aucun papier : la couverture sera créée sans papier.</span>
                                    </dd></div>
                                </dl>
                                <p class="opx-note">Le téléphone du jeu recevra cette identité, la légende et exactement les papiers cochés. Une couverture compte dans la limite de créations sur 24 heures.</p>
                            </li>
                        </ol>
                        <div class="opx-dialog__foot">
                            <button type="submit" class="cc-btn cc-btn-primary"<?php if ($idCoverQuota < 1): ?> disabled<?php endif; ?>>Créer la couverture</button>
                        </div>
                    </form>
                <?php endif; ?>
            <?= $dialogClose ?>

            <?= $dialogOpen('dlg-couverture-fictive', 'Personne fictive', 'Sans identité ni papiers Athena : nom, métier, histoire et papier sont tirés des listes du pays, jamais d’une personne réelle. Cette couverture reste non rattachée : ses papiers n’existent pas dans l’onglet Papiers.') ?>
                <form class="opx-form" method="post" enctype="multipart/form-data" action="<?= $h(url('operateur/identite/couverture')) ?>">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="source" value="random">
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
                        <label for="cov-lang-fictive">Langue des textes
                            <select id="cov-lang-fictive" name="lang">
                                <option value="fr">Français</option>
                                <option value="en">English</option>
                            </select>
                        </label>
                    </div>
                    <div class="opx-dialog__foot">
                        <button type="submit" class="cc-btn cc-btn-primary"<?php if ($idCoverQuota < 1): ?> disabled<?php endif; ?>>Créer cette personne fictive</button>
                    </div>
                </form>
            <?= $dialogClose ?>
        <?php endif; ?>
    </div>
</section>
