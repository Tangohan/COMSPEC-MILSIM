<?php

declare(strict_types=1);

/**
 * Mon identité › Papiers. Variables de views/operateur/identite.php.
 *
 * Aperçu recto/verso de chaque papier, état, actions du membre (perte, vol, renouvellement, annulation de sa demande),
 * demande d'un nouveau papier, et état de la remise en jeu.
 */
require __DIR__ . '/_document_svg.php';
$docsByIdentity = [];
foreach ($idDocuments as $d) {
    $docsByIdentity[(int) $d['identity_id']][] = $d;
}
$actionLabels = [
    'lost' => ['Déclarer perdu', 'Déclarer ce papier perdu ? Les contrôles et les fichiers le signaleront. Seul un responsable peut lever ce signalement.'],
    'stolen' => ['Déclarer volé', 'Déclarer ce papier volé ? Les contrôles et les fichiers le signaleront. Seul un responsable peut lever ce signalement.'],
    'renew' => ['Demander le renouvellement', ''],
    'cancel' => ['Annuler ma demande', 'Annuler cette demande de papier ?'],
];
$typesByCountry = [];
foreach ($idCountries as $c) {
    $typesByCountry[$c['code']] = array_values($c['doc_types']);
}
?>

<?php if ($idSync !== null): ?>
    <section class="opx-sync opx-sync--<?= $h($idSync['delivery'] === 'received' ? 'ok' : ($idSync['state'] === 'blocked' ? 'bad' : 'wait')) ?>" aria-labelledby="opx-sync-title">
        <div>
            <h2 id="opx-sync-title">Remise en jeu</h2>
            <p class="opx-sync__state">
                <strong><?= $h($idSync['title'] !== '' ? $idSync['title'] : $idSync['state_label']) ?></strong><?php if ($idSync['at'] !== ''): ?> · le <?= $h($idSync['at']) ?> UTC<?php endif; ?>
            </p>
            <?php if ($idSync['why'] !== ''): ?><p><?= $h($idSync['why']) ?></p><?php endif; ?>
            <?php if (!$idSteamLinked): ?>
                <p>Votre compte n’est pas lié à Steam : le serveur de jeu ne peut pas vous remettre vos papiers. <a href="<?= $h(url('account/steam/connect')) ?>">Lier mon compte Steam</a></p>
            <?php endif; ?>
        </div>
        <span class="opx-pill opx-pill--<?= $h(['ready' => 'ok', 'incomplete' => 'warn', 'error' => 'bad', 'blocked' => 'bad'][$idSync['state']] ?? 'idle') ?>">Préparation : <?= $h($idSync['state_label']) ?></span>
    </section>
<?php endif; ?>

<?php if ($activeIdentities === []): ?>
    <div class="opx-empty">
        <h2>Pas encore d’identité</h2>
        <p>Un papier se rattache à une identité. Créez d’abord votre identité principale.</p>
        <a class="opx-btn opx-btn--primary" href="<?= $h(url('operateur/identite')) ?>">Créer mon identité</a>
    </div>
<?php else: ?>
    <?php foreach ($idIdentities as $identity): ?>
        <?php $docs = $docsByIdentity[(int) $identity['id']] ?? []; ?>
        <?php if ($identity['archived'] && $docs === []) { continue; } ?>
        <section class="opx-section" id="papiers-<?= (int) $identity['id'] ?>" aria-labelledby="papiers-titre-<?= (int) $identity['id'] ?>">
            <div class="opx-section__head">
                <h2 id="papiers-titre-<?= (int) $identity['id'] ?>" translate="no"><?= $h($identity['display_name']) ?></h2>
                <p><?= $identity['is_primary'] ? 'Identité principale' : 'Identité d’emprunt' ?><?= $identity['active'] ? ' · envoyée en jeu' : '' ?><?= $identity['archived'] ? ' · archivée' : '' ?></p>
            </div>
            <?php if ($docs === []): ?>
                <p class="opx-hint">Aucun papier pour cette identité. Demandez-en un ci-dessous.</p>
            <?php else: ?>
                <ul class="opx-docs">
                    <?php foreach ($docs as $doc): ?>
                        <?php $photoUrl = !empty($doc['photo_path']) ? url((string) $doc['photo_path']) : null; ?>
                        <li class="opx-doc" id="papier-<?= (int) $doc['id'] ?>" data-opx-doc>
                            <div class="opx-doc__preview" data-opx-doc-faces>
                                <div class="opx-doc__face" data-opx-face="recto"><?= $opxDocSvg($doc, 'recto', $photoUrl) ?></div>
                                <div class="opx-doc__face" data-opx-face="verso" hidden><?= $opxDocSvg($doc, 'verso', $photoUrl) ?></div>
                            </div>
                            <div class="opx-doc__info">
                                <div class="opx-doc__head">
                                    <h3><?= $h($doc['doc_type_label']) ?> <span class="opx-doc__country"><?= $h($doc['country_name']) ?></span></h3>
                                    <div class="opx-pills">
                                        <span class="opx-pill opx-pill--<?= $h($doc['status_tone']) ?>"><?= $h($doc['status_label']) ?></span>
                                        <?php if ($doc['expires_soon']): ?><span class="opx-pill opx-pill--warn">Expire bientôt</span><?php endif; ?>
                                    </div>
                                </div>
                                <dl class="opx-facts opx-facts--tight">
                                    <div><dt>Numéro</dt><dd translate="no"><?= $h($doc['doc_number'] !== '' ? $doc['doc_number'] : '—') ?></dd></div>
                                    <div><dt>Délivré le</dt><dd><?= $h($dateFr($doc['issued_on'])) ?></dd></div>
                                    <div><dt>Expire le</dt><dd><?= $h($dateFr($doc['expires_on'])) ?></dd></div>
                                    <?php if (!empty($doc['issued_at'])): ?><div><dt>Émis pour le jeu</dt><dd><?= $h($dateFr((string) $doc['issued_at'])) ?></dd></div><?php endif; ?>
                                </dl>
                                <?php if ($doc['status'] === 'requested'): ?>
                                    <p class="opx-note">Demande en attente : un responsable la valide et émet le papier. Les valeurs affichées sont une proposition.</p>
                                <?php elseif (in_array($doc['status'], ['lost', 'stolen'], true)): ?>
                                    <p class="opx-note">Signalé aux contrôles et au passage aux fichiers. Présenté en jeu, ce papier déclenche une alerte.</p>
                                <?php endif; ?>
                                <div class="opx-doc__acts">
                                    <button type="button" class="opx-btn opx-btn--line" data-opx-flip aria-pressed="false">Voir le verso</button>
                                    <a class="opx-btn opx-btn--line" href="<?= $h(url('operateur/identite/documents/' . (int) $doc['id'] . '/specimen')) ?>" target="_blank" rel="noopener">Télécharger le spécimen</a>
                                    <?php foreach ($doc['actions'] as $action): ?>
                                        <?php [$label, $confirm] = $actionLabels[$action] ?? [$action, '']; ?>
                                        <form method="post" action="<?= $h(url('operateur/identite/documents/' . (int) $doc['id'] . '/action')) ?>"<?= $confirm !== '' ? ' data-opx-confirm="' . $h($confirm) . '"' : '' ?>>
                                            <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                            <button type="submit" name="action" value="<?= $h($action) ?>" class="opx-btn <?= $action === 'renew' ? 'opx-btn--line' : 'opx-btn--quiet' ?>"><?= $h($label) ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <section class="opx-panel" id="demander-un-papier">
        <h2>Demander un papier</h2>
        <p class="opx-panel__lead">Choisissez l’identité, le pays et le type. Un responsable valide la demande et émet le papier : il apparaît alors ici et part en jeu.</p>
        <?php if ($idCountries === []): ?>
            <p class="opx-note">Aucun pays n’est actif dans votre communauté : les responsables doivent en activer un dans le registre des papiers.</p>
        <?php else: ?>
            <form class="opx-form" method="post" action="<?= $h(url('operateur/identite/documents')) ?>" data-opx-doc-request data-opx-types="<?= $h((string) json_encode($typesByCountry)) ?>">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="opx-fields">
                    <label for="req-identity">Identité
                        <select id="req-identity" name="identity_id" required>
                            <?php foreach ($activeIdentities as $identity): ?>
                                <option value="<?= (int) $identity['id'] ?>"><?= $h($identity['display_name']) ?><?= $identity['is_primary'] ? ' (principale)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label for="req-country">Pays émetteur
                        <select id="req-country" name="country_code" required data-opx-req-country>
                            <?php foreach ($idCountries as $c): ?>
                                <option value="<?= $h($c['code']) ?>"><?= $h($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label for="req-type">Type de papier
                        <select id="req-type" name="doc_type" required data-opx-req-type>
                            <?php foreach ($idDocTypes as $k => $label): ?>
                                <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="opx-form__acts">
                    <button type="submit" class="opx-btn opx-btn--primary">Envoyer la demande</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
