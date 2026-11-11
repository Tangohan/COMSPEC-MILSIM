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
$statusBadge = ['ok' => 'cc-badge--ink', 'warn' => 'cc-badge--amber', 'wait' => 'cc-badge--amber', 'bad' => 'cc-badge--rose'];
$typesByCountry = [];
foreach ($idCountries as $c) {
    $typesByCountry[$c['code']] = array_values($c['doc_types']);
}
?>

<?php if ($idSync !== null): ?>
    <section class="dash-zone dash-zone--actions" aria-labelledby="opx-sync-title">
        <div class="dash-zone__inner dash-zone__inner--tight">
            <article class="cc-card opx-sync opx-sync--<?= $h($idSync['delivery'] === 'received' ? 'ok' : ($idSync['state'] === 'blocked' ? 'bad' : 'wait')) ?>">
                <div>
                    <h3 id="opx-sync-title">Remise en jeu</h3>
                    <p><strong><?= $h($idSync['title'] !== '' ? $idSync['title'] : $idSync['state_label']) ?></strong><?php if ($idSync['at'] !== ''): ?> · le <?= $h($dateFr($idSync['at'])) ?><?php endif; ?></p>
                    <?php if ($idSync['why'] !== ''): ?><p><?= $h($idSync['why']) ?></p><?php endif; ?>
                    <p>La photo imprimée sur vos papiers est celle de l’identité. <button type="button" class="opx-linkbtn" data-opx-open="dlg-photo-guide">Comment faire ma photo ?</button></p>
                    <?php if (!$idSteamLinked): ?>
                        <p>Votre compte n’est pas lié à Steam : le serveur de jeu ne peut pas vous remettre vos papiers. <a class="opx-link" href="<?= $h(url('account/steam/connect')) ?>">Lier mon compte Steam</a></p>
                    <?php endif; ?>
                </div>
                <span class="cc-badge <?= $h(['ready' => 'cc-badge--ink', 'incomplete' => 'cc-badge--amber', 'error' => 'cc-badge--rose', 'blocked' => 'cc-badge--rose'][$idSync['state']] ?? 'cc-badge--amber') ?>">Préparation : <?= $h($idSync['state_label']) ?></span>
            </article>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeIdentities === []): ?>
    <section class="dash-zone dash-zone--actions">
        <div class="dash-zone__inner">
            <div class="cc-empty">
                <p><strong>Pas encore d’identité.</strong> Un papier se rattache à une identité : définissez d’abord votre identité principale.</p>
                <a class="cc-btn cc-btn-primary" href="<?= $h(url('operateur/identite')) ?>">Définir mon identité</a>
            </div>
        </div>
    </section>
<?php else: ?>
    <?php foreach ($idIdentities as $identity): ?>
        <?php $docs = $docsByIdentity[(int) $identity['id']] ?? []; ?>
        <?php if ($identity['archived'] && $docs === []) { continue; } ?>
        <section class="dash-zone dash-zone--actions" id="papiers-<?= (int) $identity['id'] ?>" aria-labelledby="papiers-titre-<?= (int) $identity['id'] ?>">
            <div class="dash-zone__inner">
                <header class="dash-zone__head">
                    <h2 id="papiers-titre-<?= (int) $identity['id'] ?>" class="dash-zone__title" translate="no"><?= $h($identity['display_name']) ?></h2>
                    <p class="dash-zone__lead"><?= $identity['is_primary'] ? 'Identité principale' : 'Identité d’emprunt' ?><?= $identity['active'] ? ' · active en jeu' : '' ?><?= $identity['archived'] ? ' · archivée' : '' ?></p>
                </header>
                <?php if ($docs === []): ?>
                    <div class="cc-empty">
                        <p>Aucun papier pour cette identité.</p>
                        <button type="button" class="cc-btn cc-btn-primary" data-opx-open="dlg-papier" data-opx-set="req-identity:<?= (int) $identity['id'] ?>">Demander un papier</button>
                    </div>
                <?php else: ?>
                    <ul class="opx-cards">
                        <?php foreach ($docs as $doc): ?>
                            <?php $photoUrl = !empty($doc['photo_path']) ? url((string) $doc['photo_path']) : null; ?>
                            <?php $twoSides = \App\Support\SseDocumentTypes::sides((string) $doc['doc_type']) === 2; ?>
                            <li>
                                <article class="cc-card opx-doc" id="papier-<?= (int) $doc['id'] ?>" data-opx-doc>
                                    <div data-opx-doc-faces>
                                        <div data-opx-face="recto"><?= $opxDocSvg($doc, 'recto', $photoUrl) ?></div>
                                        <?php if ($twoSides): ?><div data-opx-face="verso" hidden><?= $opxDocSvg($doc, 'verso', $photoUrl) ?></div><?php endif; ?>
                                    </div>
                                    <div class="opx-doc__info">
                                        <div>
                                            <h3 class="opx-card__title"><?= $h($doc['doc_type_label']) ?></h3>
                                            <p class="opx-card__sub"><?= $h($doc['country_name']) ?></p>
                                            <div class="opx-card__badges">
                                                <span class="cc-badge <?= $h($statusBadge[$doc['status_tone']] ?? 'cc-badge--amber') ?>"><?= $h($doc['status_label']) ?></span>
                                                <?php if ($doc['expires_soon']): ?><span class="cc-badge cc-badge--amber">Expire bientôt</span><?php endif; ?>
                                            </div>
                                        </div>
                                        <dl class="opx-facts opx-facts--4">
                                            <div><dt>Numéro</dt><dd translate="no"><?= $h($doc['doc_number'] !== '' ? $doc['doc_number'] : '—') ?></dd></div>
                                            <div><dt>Délivré le</dt><dd><?= $h($dateFr($doc['issued_on'])) ?></dd></div>
                                            <div><dt>Expire le</dt><dd><?= $h($dateFr($doc['expires_on'])) ?></dd></div>
                                            <?php if (!empty($doc['issued_at'])): ?><div><dt>Émis pour le jeu</dt><dd><?= $h($dateFr((string) $doc['issued_at'])) ?></dd></div><?php endif; ?>
                                        </dl>
                                        <?php $usedBy = $idCoverUsage['by_document'][(int) $doc['id']] ?? []; ?>
                                        <?php if ($usedBy !== []): ?>
                                            <p class="opx-uses"><?= count($usedBy) === 1 ? 'Emporté par la couverture' : 'Emporté par les couvertures' ?>
                                                <?php foreach ($usedBy as $n => $use): ?>
                                                    <a href="<?= $h(url('operateur/identite/couverture') . '#couverture-' . (int) $use['id']) ?>" translate="no"><?= $h($use['name']) ?></a><?= $n < count($usedBy) - 1 ? ', ' : '' ?>
                                                <?php endforeach; ?>
                                            </p>
                                        <?php elseif ($doc['status'] === 'valid'): ?>
                                            <p class="opx-uses">Dans aucune couverture.</p>
                                        <?php endif; ?>
                                        <?php if ($doc['status'] === 'requested'): ?>
                                            <p class="opx-note">Demande en attente : un responsable la valide et émet le papier. Les valeurs affichées sont une proposition.</p>
                                        <?php elseif (in_array($doc['status'], ['lost', 'stolen'], true)): ?>
                                            <p class="opx-note">Signalé aux contrôles et au passage aux fichiers. Présenté en jeu, ce papier déclenche une alerte.</p>
                                        <?php endif; ?>
                                        <div class="opx-acts">
                                            <?php if ($twoSides): ?><button type="button" class="cc-btn cc-btn-ghost" data-opx-flip aria-pressed="false">Voir le verso</button><?php endif; ?>
                                            <a class="cc-btn cc-btn-ghost" href="<?= $h(url('operateur/identite/documents/' . (int) $doc['id'] . '/specimen')) ?>" target="_blank" rel="noopener">Télécharger le spécimen</a>
                                            <?php foreach ($doc['actions'] as $action): ?>
                                                <?php [$label, $confirm] = $actionLabels[$action] ?? [$action, '']; ?>
                                                <form method="post" action="<?= $h(url('operateur/identite/documents/' . (int) $doc['id'] . '/action')) ?>"<?php if ($confirm !== ''): ?> data-opx-confirm="<?= $h($confirm) ?>"<?php endif; ?>>
                                                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                                                    <button type="submit" name="action" value="<?= $h($action) ?>" class="cc-btn <?= $action === 'renew' ? 'cc-btn-ghost' : 'opx-btn-quiet opx-btn-danger' ?>"><?= $h($label) ?></button>
                                                </form>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </article>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="dash-zone" id="demander-un-papier" aria-labelledby="opx-req-title">
        <div class="dash-zone__inner">
            <header class="dash-zone__head">
                <h2 id="opx-req-title" class="dash-zone__title">Demander un papier</h2>
                <p class="dash-zone__lead">Choisissez l’identité, le pays et le type. Un responsable valide la demande et émet le papier : il apparaît alors ici et part en jeu.</p>
            </header>
            <?php if ($idCountries === []): ?>
                <div class="cc-empty"><p>Aucun pays n’est actif dans votre communauté : les responsables doivent en activer un dans le registre des papiers.</p></div>
            <?php else: ?>
                <div class="opx-acts">
                    <button type="button" class="cc-btn cc-btn-primary" data-opx-open="dlg-papier"><?= $opxIcon('plus', '') ?> Demander un papier</button>
                </div>
                <?= $dialogOpen('dlg-papier', 'Demander un papier', 'Un responsable valide la demande et émet le papier.') ?>
                    <form class="opx-form" method="post" action="<?= $h(url('operateur/identite/documents')) ?>" data-opx-doc-request data-opx-types="<?= $h((string) json_encode($typesByCountry)) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                        <div class="opx-fields">
                            <label class="opx-fields__wide" for="req-identity">Identité
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
                                    <?php foreach (($idDocTypeGroups ?? []) as $group): ?>
                                        <optgroup label="<?= $h(function_exists('locale') && locale() === 'en' ? \App\Support\HtmlTranslator::translateText($group['label']) : $group['label']) ?>">
                                            <?php foreach ($group['types'] as $k => $label): ?>
                                                <option value="<?= $h($k) ?>"><?= $h($label) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="opx-dialog__foot">
                            <button type="submit" class="cc-btn cc-btn-primary">Envoyer la demande</button>
                        </div>
                    </form>
                <?= $dialogClose ?>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
