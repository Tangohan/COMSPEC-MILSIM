<?php
declare(strict_types=1);
/**
 * Fenêtres de la page Équipement (aperçu et modification d’une tenue, collections, fiches matériel, dotation, aide).
 * Incluse par hub.php : reprend ses variables ($h, $lower, $csrfToken, $coverHint, $mineWardrobes, ...).
 * Les attributs data-eq-* sont lus par public/assets/js/equipment-catalog.js.
 */
?>
    <!-- Quick-view modal -->
    <dialog class="eq-hub__dialog" id="eq-quickview" aria-labelledby="eq-qv-title">
        <div class="eq-hub__dialog-shell" data-eq-qv-shell>
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <div class="eq-hub__qv" data-eq-qv-view>
                <div class="eq-hub__qv-media">
                    <div class="eq-hub__qv-stage" data-eq-qv-stage>
                        <span class="eq-hub__ph eq-hub__ph--lg" aria-hidden="true">
                            <svg viewBox="0 0 80 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M40 18c6 0 10 4 10 10v6c8 3 14 10 14 20v28H16V54c0-10 6-17 14-20v-6c0-6 4-10 10-10z" stroke="currentColor" stroke-width="2.2"/>
                            </svg>
                        </span>
                    </div>
                    <div class="eq-hub__qv-thumbs" data-eq-qv-thumbs hidden></div>
                </div>
                <div class="eq-hub__qv-panel">
                    <h2 id="eq-qv-title" data-eq-qv-title>Tenue</h2>
                    <button type="button" class="eq-hub__badge" data-eq-qv-collection hidden></button>
                    <p class="eq-hub__qv-desc" data-eq-qv-desc hidden></p>
                    <p class="eq-hub__qv-hint" data-eq-qv-hint></p>
                    <div class="eq-hub__qv-items" data-eq-qv-items></div>
                    <p class="eq-hub__qv-ace-status" data-eq-qv-ace-status role="status" hidden></p>
                    <div class="eq-hub__qv-actions">
                        <button type="button" class="eq-hub__btn" data-eq-qv-ace-copy hidden>Copier pour ACE</button>
                        <a class="eq-hub__btn eq-hub__btn--outline" data-eq-qv-ace-download hidden download>Télécharger (.txt)</a>
                        <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-qv-edit hidden>Modifier</button>
                    </div>
                </div>
            </div>
            <div class="eq-hub__qv-edit" data-eq-qv-edit-panel hidden>
                <h2>Modifier la tenue</h2>
                <form method="post" enctype="multipart/form-data" class="eq-hub__form" data-eq-qv-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="_return" value="hub">
                    <label>Nom de la tenue
                        <input type="text" name="name" required maxlength="120" data-eq-qv-name>
                        <span class="eq-hub__hint">Si vous la renommez, renommez-la aussi dans l’arsenal ACE : le jeu reconnaît vos tenues par leur nom.</span>
                    </label>
                    <label>Qui peut voir cette tenue
                        <select name="visibility" class="bo-select" data-eq-qv-visibility>
                            <option value="tenant">Toute la communauté</option>
                            <option value="personal">Moi seul</option>
                        </select>
                    </label>
                    <label>Description (visible par tous)
                        <textarea name="description" rows="3" maxlength="1000" data-eq-qv-description placeholder="Quand porter ce kit, contexte d’emploi, particularités…"></textarea>
                    </label>
                    <label>Photo de présentation (principale)
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                    </label>
                    <label>Photos supplémentaires (galerie, max 5)
                        <input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple>
                    </label>
                    <div class="eq-hub__gallery-edit" data-eq-qv-gallery-edit hidden></div>
                    <label>Collection
                        <select name="collection_id" class="bo-select" data-eq-qv-collection-select>
                            <option value="0">Sans collection</option>
                        </select>
                    </label>
                    <label>Note interne (vous seul)
                        <textarea name="notes" rows="2" maxlength="255" data-eq-qv-notes></textarea>
                    </label>
                    <div class="eq-hub__form-actions">
                        <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-qv-back>Retour à l’aperçu</button>
                        <button type="submit" class="eq-hub__btn">Enregistrer</button>
                    </div>
                </form>
                <form method="post" class="eq-hub__danger" data-eq-qv-delete data-ui-confirm="1" data-ui-confirm-title="Supprimer la tenue" data-ui-confirm-body="Supprimer cette tenue d’Athena ? Elle reste dans votre arsenal ACE si elle y est enregistrée.">
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <button type="submit" class="eq-hub__btn eq-hub__btn--ghost">Supprimer la tenue</button>
                </form>
            </div>
        </div>
    </dialog>

    <!-- Nouvelle collection -->
    <dialog class="eq-hub__dialog" id="eq-collection-new" aria-labelledby="eq-col-new-title"<?= (string) ($_GET['creer'] ?? '') === 'collection' ? ' open' : '' ?>>
        <div class="eq-hub__dialog-shell eq-hub__dialog-shell--wide">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-col-new-title">Nouvelle collection</h2>
            <form method="post" action="<?= $h(url('equipment/collections')) ?>" enctype="multipart/form-data" class="eq-hub__form eq-hub__form--collection" data-eq-collection-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="eq-hub__form-grid">
                    <label>Nom
                        <input type="text" name="name" required maxlength="120" placeholder="Assaut nocturne">
                    </label>
                    <label>Qui peut s’en servir
                        <select name="visibility" class="bo-select">
                            <option value="personal">Moi seulement</option>
                            <option value="unit">Mon unité</option>
                            <option value="tenant">Toute la communauté</option>
                        </select>
                    </label>
                </div>
                <label>Présentation
                    <textarea name="description" rows="2" maxlength="500" placeholder="Quand porter ce kit, pour qui, contraintes."></textarea>
                </label>
                <label>Photo de présentation
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h($coverHint) ?> Sans photo, une mosaïque des tenues sélectionnées sera utilisée.</span>
                </label>

                <fieldset class="eq-hub__picker">
                    <legend>
                        Tenues à inclure
                        <span class="eq-hub__picker-count" data-eq-pick-count>0 sélectionnée</span>
                    </legend>
                    <div class="eq-hub__picker-toolbar">
                        <input type="search" placeholder="Rechercher une tenue…" data-eq-pick-search autocomplete="off">
                        <select data-eq-pick-kind class="bo-select">
                            <option value="">Tous les types</option>
                            <?php foreach ($equipmentKindLabels as $kind => $label): ?>
                            <option value="<?= $h($kind) ?>"><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($mineWardrobes === []): ?>
                    <p class="eq-hub__hint">En jeu, ouvrez l’arsenal, puis le bandeau Athena en haut de l’écran, et envoyez vos tenues. Elles apparaîtront ici pour les ranger dans une collection.</p>
                    <?php else: ?>
                    <div class="eq-hub__picker-grid" data-eq-pick-grid>
                        <?php foreach ($mineWardrobes as $w): ?>
                        <?php
                            $display = (string) ($w['display_name'] ?? \App\Support\ArsenalLoadoutItems::formatWardrobeTitle((string) ($w['name'] ?? '')));
                            $kinds = is_array($w['kinds'] ?? null) ? $w['kinds'] : [];
                        ?>
                        <label
                            class="eq-hub__pick"
                            data-eq-pick
                            data-name="<?= $h($lower($display . ' ' . (string) ($w['name'] ?? ''))) ?>"
                            data-kinds="<?= $h(implode(',', $kinds)) ?>"
                        >
                            <input type="checkbox" name="wardrobe_ids[]" value="<?= (int) $w['id'] ?>" hidden>
                            <span class="eq-hub__media eq-hub__media--pick">
                                <?php if (!empty($w['cover_url'])): ?>
                                <img class="eq-hub__img" src="<?= $h($w['cover_url']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                <span class="eq-hub__ph" aria-hidden="true">
                                    <svg viewBox="0 0 80 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M40 18c6 0 10 4 10 10v6c8 3 14 10 14 20v28H16V54c0-10 6-17 14-20v-6c0-6 4-10 10-10z" stroke="currentColor" stroke-width="2.2"/>
                                    </svg>
                                </span>
                                <?php endif; ?>
                                <span class="eq-hub__pick-mark" aria-hidden="true">✓</span>
                            </span>
                            <span class="eq-hub__pick-name"><?= $h($display) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </fieldset>
                <button type="submit" class="eq-hub__btn">Créer la collection</button>
            </form>
        </div>
    </dialog>

    <!-- Éditer collection -->
    <dialog class="eq-hub__dialog" id="eq-collection-edit" aria-labelledby="eq-col-edit-title">
        <div class="eq-hub__dialog-shell eq-hub__dialog-shell--wide">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-col-edit-title">Modifier la collection</h2>
            <form method="post" enctype="multipart/form-data" class="eq-hub__form" data-eq-collection-edit-form>
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="eq-hub__form-grid">
                    <label>Nom
                        <input type="text" name="name" required maxlength="120" data-eq-col-edit-name>
                    </label>
                    <label>Qui peut s’en servir
                        <select name="visibility" class="bo-select" data-eq-col-edit-visibility>
                            <option value="personal">Moi seulement</option>
                            <option value="unit">Mon unité</option>
                            <option value="tenant">Toute la communauté</option>
                        </select>
                    </label>
                </div>
                <label>Présentation
                    <textarea name="description" rows="2" maxlength="500" data-eq-col-edit-description></textarea>
                </label>
                <label>Photo de présentation
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                </label>
                <fieldset class="eq-hub__picker">
                    <legend>Tenues à inclure <span class="eq-hub__picker-count" data-eq-col-edit-count>0 sélectionnée</span></legend>
                    <div class="eq-hub__picker-toolbar">
                        <input type="search" placeholder="Rechercher…" data-eq-col-edit-search autocomplete="off">
                    </div>
                    <div class="eq-hub__picker-grid" data-eq-col-edit-grid></div>
                </fieldset>
                <div class="eq-hub__form-actions">
                    <button type="submit" class="eq-hub__btn">Enregistrer</button>
                </div>
            </form>
            <form method="post" class="eq-hub__danger" data-eq-col-edit-delete data-ui-confirm="1" data-ui-confirm-title="Retirer la collection" data-ui-confirm-body="Retirer cette collection ? Les tenues ne sont pas supprimées.">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <button type="submit" class="eq-hub__btn eq-hub__btn--ghost">Retirer la collection</button>
            </form>
        </div>
    </dialog>

    <!-- Fiche matériel modal -->
    <dialog class="eq-hub__dialog" id="eq-fiche-view" aria-labelledby="eq-fiche-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <div data-eq-fiche-view-panel>
                <div class="eq-hub__qv" style="grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr)">
                    <div class="eq-hub__qv-stage" data-eq-fiche-stage></div>
                    <div>
                        <p class="eq-hub__badge" data-eq-fiche-cat hidden></p>
                        <h2 id="eq-fiche-title" data-eq-fiche-title>Fiche</h2>
                        <p class="eq-hub__qv-desc" data-eq-fiche-desc></p>
                        <div class="eq-hub__qv-actions">
                            <a class="eq-hub__btn eq-hub__btn--ghost-light" data-eq-fiche-page href="#">Page complète</a>
                            <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-fiche-edit hidden>Modifier</button>
                        </div>
                    </div>
                </div>
            </div>
            <div data-eq-fiche-edit-panel hidden>
                <h2>Modifier la fiche</h2>
                <form method="post" enctype="multipart/form-data" class="eq-hub__form" data-eq-fiche-edit-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <label>Nom <input type="text" name="name" required maxlength="255" data-eq-fiche-edit-name></label>
                    <label>Catégorie <input type="text" name="category" maxlength="100" data-eq-fiche-edit-category placeholder="Radio, protection…"></label>
                    <label>Description
                        <textarea name="description" rows="4" data-eq-fiche-edit-description placeholder="Usage, contraintes, doctrine associée…"></textarea>
                    </label>
                    <label>Photo
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                    </label>
                    <div class="eq-hub__form-actions">
                        <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-fiche-back>Retour</button>
                        <button type="submit" class="eq-hub__btn">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>

    <?php if ($canManageCatalog): ?>
    <dialog class="eq-hub__dialog" id="eq-fiche-new" aria-labelledby="eq-fiche-new-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-fiche-new-title">Nouvelle fiche matériel</h2>
            <form method="post" action="<?= $h(url('equipment/fiches')) ?>" enctype="multipart/form-data" class="eq-hub__form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <label>Nom <input type="text" name="name" required maxlength="255" placeholder="Gilet porte-plaques"></label>
                <label>Catégorie <input type="text" name="category" maxlength="100" placeholder="Protection"></label>
                <label>Description
                    <textarea name="description" rows="4" placeholder="Décrivez l’usage, le contexte et les contraintes."></textarea>
                </label>
                <label>Photo
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                </label>
                <button type="submit" class="eq-hub__btn">Créer la fiche</button>
            </form>
        </div>
    </dialog>
    <?php endif; ?>

    <!-- Dotation modal -->
    <dialog class="eq-hub__dialog" id="eq-dotation-view" aria-labelledby="eq-dotation-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <div data-eq-dotation-view-panel>
                <div class="eq-hub__qv" style="grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr)">
                    <div class="eq-hub__qv-stage" data-eq-dotation-stage></div>
                    <div>
                        <p class="eq-hub__badge" data-eq-dotation-code hidden></p>
                        <h2 id="eq-dotation-title" data-eq-dotation-title>Article</h2>
                        <p class="eq-hub__qv-desc" data-eq-dotation-desc></p>
                        <div class="eq-hub__qv-actions">
                            <a class="eq-hub__btn eq-hub__btn--ghost-light" data-eq-dotation-admin href="#" hidden>Carnet de dotation</a>
                            <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-dotation-edit hidden>Modifier</button>
                        </div>
                    </div>
                </div>
            </div>
            <div data-eq-dotation-edit-panel hidden>
                <h2>Modifier l’article</h2>
                <form method="post" enctype="multipart/form-data" class="eq-hub__form" data-eq-dotation-edit-form>
                    <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                    <div class="eq-hub__form-grid">
                        <label>Code <input type="text" name="code" required maxlength="40" data-eq-dotation-edit-code></label>
                        <label>Nom <input type="text" name="name" required maxlength="180" data-eq-dotation-edit-name></label>
                    </div>
                    <label>Catégorie <input type="text" name="category" maxlength="80" data-eq-dotation-edit-category></label>
                    <label>Description
                        <textarea name="description" rows="4" data-eq-dotation-edit-description placeholder="Description de l’article, consignes d’emploi…"></textarea>
                    </label>
                    <label>Photo
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                    </label>
                    <div class="eq-hub__form-actions">
                        <button type="button" class="eq-hub__btn eq-hub__btn--ghost" data-eq-dotation-back>Retour</button>
                        <button type="submit" class="eq-hub__btn">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>

    <?php if ($canManageDotation): ?>
    <dialog class="eq-hub__dialog" id="eq-dotation-new" aria-labelledby="eq-dotation-new-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-dotation-new-title">Nouvel article de dotation</h2>
            <form method="post" action="<?= $h(url('equipment/dotation')) ?>" enctype="multipart/form-data" class="eq-hub__form">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="eq-hub__form-grid">
                    <label>Code <input type="text" name="code" required maxlength="40" placeholder="RAD-001"></label>
                    <label>Nom <input type="text" name="name" required maxlength="180" placeholder="Radio AN/PRC"></label>
                </div>
                <label>Catégorie <input type="text" name="category" maxlength="80" placeholder="Radio"></label>
                <label>Description
                    <textarea name="description" rows="4" placeholder="Décrivez l’article et son emploi."></textarea>
                </label>
                <label>Photo
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                    <span class="eq-hub__hint"><?= $h($coverHint) ?></span>
                </label>
                <button type="submit" class="eq-hub__btn">Créer l’article</button>
            </form>
        </div>
    </dialog>
    <?php endif; ?>

    <!-- Aide nouvelle tenue -->
    <dialog class="eq-hub__dialog" id="eq-tenue-help" aria-labelledby="eq-tenue-help-title">
        <div class="eq-hub__dialog-shell">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-tenue-help-title">Nouvelle tenue</h2>
            <p class="eq-hub__lead-plain">Trois façons d’ajouter une tenue à vos tenues :</p>
            <ol class="eqx-steps">
                <li><strong>Depuis l’arsenal en jeu.</strong> Ouvrez l’équipement, bandeau Athena en haut, puis <strong>Envoyer cette</strong> ou <strong>Envoyer toutes</strong>.</li>
                <li><strong>Avec votre équipement porté.</strong> Le jeu remonte ce que vous portez : enregistrez-le d’un clic avec « Enregistrer mon kit en jeu ».</li>
                <li><strong>Avec l’assistant de création</strong> (formule Pro et plus) : choisissez un emploi, l’assistant propose des objets que votre communauté a déjà utilisés en jeu.</li>
            </ol>
            <div class="eqx-actions">
                <a class="eq-hub__btn" href="<?= $h(url('equipment/assistant')) ?>">Ouvrir l’assistant</a>
                <button type="button" class="eq-hub__btn eq-hub__btn--outline" data-eq-close>Compris</button>
            </div>
        </div>
    </dialog>

    <?php if (!empty($canManageKits) && !empty($kitsAllowed)): ?>
    <!-- Création d'un kit de départ -->
    <dialog class="eq-hub__dialog" id="eq-kit-new" aria-labelledby="eq-kit-new-title">
        <div class="eq-hub__dialog-shell eq-hub__dialog-shell--wide">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-kit-new-title">Créer un kit de départ</h2>
            <?php require __DIR__ . '/_kit_create.php'; ?>
        </div>
    </dialog>
    <?php endif; ?>

    <!-- Fenêtre commune : contenu d'un kit, gestion d'un kit, mon kit en jeu (repris des blocs repliables de la page) -->
    <dialog class="eq-hub__dialog" id="eq-generic" aria-labelledby="eq-generic-title">
        <div class="eq-hub__dialog-shell eq-hub__dialog-shell--wide">
            <button type="button" class="eq-hub__dialog-close" data-eq-close aria-label="Fermer">×</button>
            <h2 id="eq-generic-title" data-eq-generic-title></h2>
            <div data-eq-generic-body></div>
        </div>
    </dialog>
