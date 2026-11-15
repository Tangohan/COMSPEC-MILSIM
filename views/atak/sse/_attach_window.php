<?php
declare(strict_types=1);

/**
 * Fenêtre « Rattacher » du bureau SSE : UNE fenêtre pour rattacher une ou plusieurs pièces à un dossier, un dossier d'intérêt,
 * une piste, une investigation, une personne, une identité, un papier, un document, un site, un objet, une campagne, un avis,
 * une fiche ou une transmission. Rendue une fois par page par \App\Services\Sse\Attach\SseAttachWindow::render().
 *
 * Tous les textes sont ici (traduits avec la page) : le script public/assets/js/sse-rattacher.js n'en contient aucun, il
 * clone les gabarits <template> et lit les libellés du bloc caché [data-ra-labels].
 *
 * @var array<string, mixed> $ra csrf, guest, ready, urls, natures, colors, templates, levels
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$ico = static function (string $d, int $size = 18): string {
    $paths = '';
    foreach (explode('|', $d) as $p) {
        $paths .= '<path d="' . $p . '"/>';
    }

    return '<svg class="ra-ico" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
};
$i = [
    'close' => 'M6 6l12 12|M18 6L6 18', 'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z|M20 20l-4-4', 'filter' => 'M4 6h16|M7 12h10|M10 18h4',
    'back' => 'M19 12H5|M11 6l-6 6 6 6', 'next' => 'M5 12h14|M13 6l6 6-6 6', 'plus' => 'M12 5v14|M5 12h14', 'check' => 'M5 12l5 5 9-10',
    'lock' => 'M6 11h12v9H6z|M8 11V8a4 4 0 0 1 8 0v3', 'warn' => 'M12 3l10 18H2z|M12 10v5|M12 18h.01', 'link' => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1|M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
    'spark' => 'M12 3v4|M12 17v4|M3 12h4|M17 12h4|M6 6l2.5 2.5|M15.5 15.5L18 18|M18 6l-2.5 2.5|M8.5 15.5L6 18', 'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z|M12 11v5|M12 8h.01',
];
$urls = (array) $ra['urls'];
?>
<link rel="stylesheet" href="<?= $h(asset_url('assets/css/sse-rattacher.css')) ?>">
<div class="ra" id="ra-window" hidden data-ra
     data-ra-context="<?= $h($urls['context']) ?>" data-ra-targets="<?= $h($urls['targets']) ?>" data-ra-target="<?= $h($urls['target']) ?>"
     data-ra-preview="<?= $h($urls['preview']) ?>" data-ra-attach="<?= $h($urls['attach']) ?>" data-ra-undo="<?= $h($urls['undo']) ?>"
     data-ra-create="<?= $h($urls['create']) ?>" data-ra-base="<?= $h($urls['base']) ?>" data-ra-csrf="<?= $h($ra['csrf']) ?>">
    <div class="ra-backdrop" data-ra-close></div>
    <section class="ra-dialog" role="dialog" aria-modal="true" aria-labelledby="ra-title" aria-describedby="ra-lead" tabindex="-1">
        <header class="ra-head">
            <div class="ra-head__main">
                <h2 id="ra-title"><span data-ra-verb>Rattacher</span> <span class="ra-head__ref" data-ra-ref translate="no"></span></h2>
                <p id="ra-lead" class="ra-lead" data-ra-lead>Choisissez une ou plusieurs cibles, dites pourquoi, puis validez.</p>
            </div>
            <span class="ra-level" data-ra-level hidden></span>
            <button type="button" class="ra-x" data-ra-close aria-label="Fermer la fenêtre"><?= $ico($i['close'], 20) ?></button>
        </header>

        <ol class="ra-steps" aria-label="Étapes du rattachement">
            <li class="ra-step is-on" data-ra-stepmark="pick"><span class="ra-step__n">1</span><span>Cibles</span></li>
            <li class="ra-step" data-ra-stepmark="settings"><span class="ra-step__n">2</span><span>Réglages</span></li>
            <li class="ra-step" data-ra-stepmark="recap"><span class="ra-step__n">3</span><span>Récapitulatif</span></li>
        </ol>

        <div class="ra-banner" data-ra-banner hidden role="status">
            <?= $ico($i['info'], 20) ?>
            <p data-ra-banner-text></p>
        </div>

        <!-- Étape 1 : choisir les cibles -->
        <div class="ra-body ra-pick" data-ra-step="pick">
            <nav class="ra-scopes" role="tablist" aria-orientation="vertical" aria-label="Périmètres" data-ra-scopes></nav>

            <div class="ra-main" role="tabpanel" aria-labelledby="ra-title">
                <div class="ra-toolbar" role="search">
                    <label class="ra-search">
                        <span class="ra-sr">Rechercher une cible</span>
                        <?= $ico($i['search']) ?>
                        <input type="search" data-ra-q placeholder="Référence, titre, alias ou étiquette" autocomplete="off" spellcheck="false" maxlength="80">
                    </label>
                    <label class="ra-select">
                        <span class="ra-sr">Trier les résultats</span>
                        <select data-ra-sort>
                            <option value="relevance">Pertinence</option>
                            <option value="recent">Plus récents</option>
                            <option value="alpha">Alphabétique</option>
                            <option value="class">Classification</option>
                            <option value="linked">Déjà liés d’abord</option>
                        </select>
                    </label>
                    <button type="button" class="ra-btn ra-btn--ghost" data-ra-filters-toggle aria-expanded="false" aria-controls="ra-filters"><?= $ico($i['filter']) ?><span>Filtres</span><span class="ra-count" data-ra-filters-count hidden></span></button>
                </div>
                <div class="ra-filters" id="ra-filters" data-ra-filters hidden>
                    <label>État<select data-ra-filter="status"><option value="">Tous</option><option value="open">Ouverts</option><option value="progress">En cours</option><option value="closed">Clos</option></select></label>
                    <label>Classification<select data-ra-filter="class"><option value="">Toutes</option></select></label>
                    <label>Étiquette<select data-ra-filter="tag"><option value="">Toutes</option></select></label>
                    <label class="ra-check"><input type="checkbox" data-ra-filter="mine"><span>Mes éléments</span></label>
                    <button type="button" class="ra-link" data-ra-filters-reset>Réinitialiser</button>
                </div>

                <div class="ra-scroll" data-ra-scroll>
                    <section class="ra-feature" data-ra-suggest hidden aria-labelledby="ra-suggest-title">
                        <div class="ra-feature__head">
                            <h3 id="ra-suggest-title"><?= $ico($i['spark']) ?> Proposés par Cerbère</h3>
                            <p>Cibles probables, d’après les liens existants, le lieu ou le numéro de la pièce.</p>
                        </div>
                        <ol class="ra-strips" data-ra-strips></ol>
                    </section>

                    <section class="ra-recents" data-ra-recents hidden aria-labelledby="ra-recents-title">
                        <h3 id="ra-recents-title">Récents</h3>
                        <ul class="ra-chips" data-ra-recents-list></ul>
                    </section>

                    <p class="ra-status" data-ra-status role="status" aria-live="polite"></p>
                    <ul class="ra-results" data-ra-results aria-label="Cibles possibles"></ul>
                    <div class="ra-empty" data-ra-empty hidden>
                        <strong data-ra-empty-title>Aucun résultat</strong>
                        <p data-ra-empty-text></p>
                        <button type="button" class="ra-btn" data-ra-create-open hidden><?= $ico($i['plus']) ?><span data-ra-create-label>Créer</span></button>
                    </div>
                    <button type="button" class="ra-btn ra-btn--ghost ra-more" data-ra-more hidden>Afficher la suite</button>
                </div>
            </div>

            <aside class="ra-detail" data-ra-detail aria-label="Détail de la cible" tabindex="-1">
                <button type="button" class="ra-link ra-detail__back" data-ra-detail-close><?= $ico($i['back']) ?><span>Retour à la liste</span></button>
                <div class="ra-detail__none" data-ra-detail-none>
                    <p>Survolez ou sélectionnez une cible pour voir sa description, son responsable et ce qui y est déjà rattaché.</p>
                    <button type="button" class="ra-btn ra-btn--ghost" data-ra-create-open hidden><?= $ico($i['plus']) ?><span data-ra-create-label>Créer</span></button>
                </div>
                <div class="ra-detail__card" data-ra-detail-card hidden>
                    <h3 data-ra-d="title" translate="no"></h3>
                    <p class="ra-detail__badges"><span data-ra-d="type_label"></span><span class="ra-mono" data-ra-d="ref" translate="no"></span><span class="ra-state" data-ra-d-state></span><span class="ra-level" data-ra-d-level hidden></span></p>
                    <p class="ra-detail__desc" data-ra-d="description" translate="no"></p>
                    <dl class="ra-facts">
                        <div data-ra-row="owner"><dt>Responsable</dt><dd data-ra-d="owner" translate="no"></dd></div>
                        <div data-ra-row="date"><dt>Créé le</dt><dd data-ra-d="date"></dd></div>
                        <div data-ra-row="updated"><dt>Dernière activité</dt><dd data-ra-d="updated"></dd></div>
                        <div data-ra-row="readers"><dt>Qui peut la voir</dt><dd data-ra-d="readers"></dd></div>
                    </dl>
                    <div class="ra-detail__attached" data-ra-row="attached">
                        <h4>Déjà rattaché (<span data-ra-d="count"></span>)</h4>
                        <ul data-ra-d-attached translate="no"></ul>
                    </div>
                    <div class="ra-detail__actions">
                        <button type="button" class="ra-btn ra-btn--primary" data-ra-detail-pick><span data-ra-detail-pick-label>Choisir cette cible</span></button>
                        <a class="ra-btn ra-btn--ghost" data-ra-detail-open target="_blank" rel="noopener">Ouvrir</a>
                    </div>
                </div>
                <form class="ra-create" data-ra-create-form hidden novalidate>
                    <h3 data-ra-create-title>Créer</h3>
                    <p class="ra-lead">Créé dans votre communauté, puis sélectionné comme cible. Vous restez dans cette fenêtre.</p>
                    <input type="hidden" name="type" value="">
                    <label class="ra-field">Titre<input type="text" name="title" maxlength="200" required autocomplete="off"></label>
                    <label class="ra-field">Description<textarea name="description" rows="3" maxlength="2000"></textarea></label>
                    <label class="ra-field">Classification<select name="classification" data-ra-create-level></select></label>
                    <p class="ra-error" data-ra-create-error role="alert" hidden></p>
                    <div class="ra-detail__actions">
                        <button type="submit" class="ra-btn ra-btn--primary">Créer et sélectionner</button>
                        <button type="button" class="ra-btn ra-btn--ghost" data-ra-create-cancel>Annuler</button>
                    </div>
                </form>
            </aside>
        </div>

        <!-- Étape 2 : régler le rattachement -->
        <form class="ra-body ra-settings" data-ra-step="settings" hidden novalidate>
            <div class="ra-settings__grid">
                <label class="ra-field">Nature du lien
                    <select name="nature" data-ra-nature aria-describedby="ra-nature-help">
                        <option value="">Selon la cible</option>
                        <?php foreach ((array) $ra['natures'] as $key => $label): ?>
                            <option value="<?= $h($key) ?>"><?= $h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small id="ra-nature-help">« Selon la cible » : classé dans un dossier, concerne une personne.</small>
                </label>
                <label class="ra-field" data-ra-nature-free hidden>Libellé de la nature
                    <input type="text" name="nature_label" maxlength="80" autocomplete="off" placeholder="Par exemple : cité par le témoin">
                </label>
                <label class="ra-field" data-ra-label-field>Nom du rattachement
                    <input type="text" name="label" maxlength="160" autocomplete="off" data-ra-label-input>
                    <small>Le nom affiché dans la cible. La pièce garde son titre.</small>
                </label>
                <div class="ra-field ra-field--wide">
                    <label for="ra-reason">Motif</label>
                    <input type="text" id="ra-reason" name="reason" maxlength="255" required minlength="5" autocomplete="off" aria-describedby="ra-reason-help" placeholder="Pourquoi rattachez-vous ?">
                    <small id="ra-reason-help">Obligatoire, 5 caractères au moins. Il est consigné avec le rattachement.</small>
                    <div class="ra-templates" role="group" aria-label="Modèles de motif">
                        <?php foreach ((array) $ra['templates'] as $tpl): ?>
                            <button type="button" class="ra-chip" data-ra-template><?= $h($tpl) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="ra-field ra-field--wide" data-ra-tags-field>
                    <label for="ra-tag-input">Étiquettes</label>
                    <div class="ra-tagbox">
                        <ul class="ra-tags" data-ra-tags-picked translate="no"></ul>
                        <input type="text" id="ra-tag-input" maxlength="40" autocomplete="off" data-ra-tag-input placeholder="Ajouter une étiquette" aria-describedby="ra-tag-help">
                        <select data-ra-tag-color aria-label="Couleur d’une nouvelle étiquette">
                            <?php foreach ((array) $ra['colors'] as $key => $label): ?>
                                <option value="<?= $h($key) ?>"><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <small id="ra-tag-help">Entrée pour ajouter. Une étiquette nouvelle entre au catalogue de la communauté.</small>
                    <div class="ra-templates" data-ra-tags-catalog role="group" aria-label="Étiquettes du catalogue" translate="no"></div>
                </div>
                <label class="ra-field ra-field--wide"><span>Note <span class="ra-opt">facultative</span></span>
                    <textarea name="note" rows="2" maxlength="500"></textarea>
                </label>
            </div>
            <fieldset class="ra-align">
                <legend>Confidentialité</legend>
                <label class="ra-check ra-check--big">
                    <input type="checkbox" name="align" value="1" data-ra-align>
                    <span><strong>Aligner la confidentialité de la pièce sur celle de la cible</strong>
                        <small data-ra-align-help>L’alignement ne peut que relever le niveau de la pièce, jamais au-dessus de votre habilitation. Il n’abaisse jamais rien.</small></span>
                </label>
                <p class="ra-align__forced" data-ra-align-forced hidden><?= $ico($i['lock']) ?> Votre communauté aligne toujours à la hausse au rattachement.</p>
                <ul class="ra-alerts" data-ra-alerts aria-live="polite"></ul>
            </fieldset>
        </form>

        <!-- Étape 3 : récapitulatif -->
        <div class="ra-body ra-recap" data-ra-step="recap" hidden>
            <p class="ra-lead" data-ra-recap-lead></p>
            <div class="ra-tablewrap">
                <table class="ra-table">
                    <thead><tr><th scope="col">Pièce</th><th scope="col">Nature</th><th scope="col">Cible</th><th scope="col">Effet</th></tr></thead>
                    <tbody data-ra-recap-rows translate="no"></tbody>
                </table>
            </div>
            <ul class="ra-alerts" data-ra-recap-effects></ul>
            <dl class="ra-facts ra-facts--inline" data-ra-recap-facts>
                <div><dt>Motif</dt><dd data-ra-recap="reason" translate="no"></dd></div>
                <div data-ra-row="recap-tags"><dt>Étiquettes</dt><dd data-ra-recap="tags" translate="no"></dd></div>
                <div data-ra-row="recap-note"><dt>Note</dt><dd data-ra-recap="note" translate="no"></dd></div>
            </dl>
        </div>

        <!-- Déplacement : motif du déplacement (mode « Déplacer ») -->
        <form class="ra-body ra-moveform" data-ra-step="move" hidden novalidate>
            <p class="ra-lead" data-ra-move-lead></p>
            <div class="ra-field ra-field--wide">
                <label for="ra-move-reason">Motif du déplacement</label>
                <input type="text" id="ra-move-reason" name="reason" maxlength="255" required minlength="5" autocomplete="off">
                <small>Obligatoire. Le libellé et les étiquettes suivent la pièce ; l’ancien rattachement reste dans l’historique.</small>
            </div>
        </form>

        <p class="ra-error ra-error--bar" data-ra-error role="alert" hidden></p>

        <footer class="ra-foot">
            <div class="ra-picked" data-ra-picked aria-live="polite">
                <span class="ra-picked__n" data-ra-picked-n>Aucune cible choisie</span>
                <ul class="ra-chips" data-ra-picked-list translate="no"></ul>
            </div>
            <div class="ra-foot__actions">
                <button type="button" class="ra-btn ra-btn--ghost" data-ra-prev hidden><?= $ico($i['back']) ?><span>Retour</span></button>
                <button type="button" class="ra-btn ra-btn--ghost" data-ra-close>Annuler</button>
                <button type="button" class="ra-btn ra-btn--primary" data-ra-next disabled><span data-ra-next-label>Continuer</span><?= $ico($i['next']) ?></button>
            </div>
        </footer>
    </section>

    <!-- Gabarits clonés par le script -->
    <template data-ra-tpl="scope">
        <button type="button" class="ra-scope" role="tab" aria-selected="false"><span class="ra-scope__l"></span><span class="ra-scope__n"></span></button>
    </template>
    <template data-ra-tpl="row">
        <li class="ra-row" tabindex="-1">
            <label class="ra-row__pick"><input type="checkbox"><span class="ra-sr">Choisir</span></label>
            <button type="button" class="ra-row__body">
                <span class="ra-row__top"><span class="ra-dot"></span><span class="ra-mono ra-row__ref"></span><span class="ra-row__title"></span></span>
                <span class="ra-row__desc"></span>
                <span class="ra-row__meta"><span class="ra-row__state"></span><span class="ra-row__owner"></span><span class="ra-row__tags"></span></span>
            </button>
            <span class="ra-row__side"><span class="ra-level"></span><span class="ra-row__count" title="Éléments déjà rattachés à cette cible"><?= $ico($i['link'], 14) ?><b></b></span><span class="ra-flag" hidden>Déjà rattaché</span></span>
        </li>
    </template>
    <template data-ra-tpl="strip">
        <li class="ra-strip">
            <span class="ra-strip__glow" aria-hidden="true"></span><span class="ra-strip__veil" aria-hidden="true"></span>
            <div class="ra-strip__body">
                <div class="ra-strip__id">
                    <span class="ra-strip__index" aria-hidden="true"></span>
                    <div class="ra-strip__who">
                        <p class="ra-strip__title"></p>
                        <p class="ra-strip__why"></p>
                    </div>
                </div>
                <div class="ra-strip__meta">
                    <span class="ra-strip__pill"></span>
                    <span class="ra-strip__actions">
                        <button type="button" class="ra-btn ra-btn--primary ra-btn--sm" data-ra-strip-pick>Choisir</button>
                        <button type="button" class="ra-btn ra-btn--ghost ra-btn--sm" data-ra-strip-detail>Détail</button>
                    </span>
                    <span class="ra-strip__figure"><b></b><small>rattachés</small></span>
                </div>
            </div>
        </li>
    </template>
    <template data-ra-tpl="chip">
        <li class="ra-chipitem"><span class="ra-chipitem__t"></span><button type="button" class="ra-chipitem__x" aria-label="Retirer"><?= $ico($i['close'], 14) ?></button></li>
    </template>
    <template data-ra-tpl="alert">
        <li class="ra-alert"><?= $ico($i['warn'], 18) ?><span></span></li>
    </template>
    <div hidden data-ra-labels>
        <?php foreach ((array) $ra['scopes'] as $key => $scope): ?>
            <span data-k="scope.<?= $h($key) ?>"><?= $h($scope['label']) ?></span>
            <span data-k="one.<?= $h($key) ?>"><?= $h($scope['one']) ?></span>
            <span data-k="empty.<?= $h($key) ?>"><?= $h($scope['empty']) ?></span>
        <?php endforeach; ?>
        <span data-k="fx.target_lower">La cible (%1) est moins protégée que la pièce (%2) : la pièce garde son niveau et s’affichera « Élément restreint » pour les lecteurs de la cible sans l’habilitation %2. Rien n’est abaissé.</span>
        <span data-k="fx.target_higher">La cible est %1, la pièce reste %2 : elle demeure lisible en dehors de la cible. Cochez l’alignement pour la relever.</span>
        <span data-k="fx.raise">La pièce passe de %2 à %1 : les lecteurs sans l’habilitation %1 ne la verront plus.</span>
        <span data-k="fx.ceiling">L’alignement dépasserait votre habilitation : la pièce garde son niveau.</span>
        <span data-k="fx.unclassifiable">Ce type de pièce ne porte pas de classification : il suit les droits de sa section, quelle que soit la cible.</span>
        <span data-k="verb.attach">Rattacher</span>
        <span data-k="verb.move">Déplacer</span>
        <span data-k="lead.one">Choisissez une ou plusieurs cibles, dites pourquoi, puis validez.</span>
        <span data-k="lead.many">%1 pièces sélectionnées : elles seront rattachées aux mêmes cibles, avec le même motif.</span>
        <span data-k="lead.move">Choisissez la nouvelle cible de cette pièce, puis donnez le motif du déplacement.</span>
        <span data-k="pieces">%1 pièces</span>
        <span data-k="level.piece">Pièce classée %1</span>
        <span data-k="level.none">Pièce non classifiée</span>
        <span data-k="level.mixed">Pièces de niveaux différents</span>
        <span data-k="loading">Chargement…</span>
        <span data-k="results.one">1 résultat</span>
        <span data-k="results.many">%1 résultats</span>
        <span data-k="results.none">Aucun résultat</span>
        <span data-k="empty.search">Aucune cible ne correspond à cette recherche. Vérifiez l’orthographe, retirez un filtre ou changez de périmètre.</span>
        <span data-k="empty.disabled">Ce périmètre sera disponible après la mise à jour de la base : seul le rattachement à un dossier fonctionne pour l’instant.</span>
        <span data-k="picked.none">Aucune cible choisie</span>
        <span data-k="picked.one">1 cible choisie</span>
        <span data-k="picked.many">%1 cibles choisies</span>
        <span data-k="next.continue">Continuer</span>
        <span data-k="next.recap">Voir le récapitulatif</span>
        <span data-k="next.attach">Rattacher</span>
        <span data-k="next.move">Déplacer</span>
        <span data-k="pick">Choisir cette cible</span>
        <span data-k="unpick">Retirer de la sélection</span>
        <span data-k="flag.all">Déjà rattaché</span>
        <span data-k="flag.some">Déjà lié à %1 pièce(s)</span>
        <span data-k="create.case">Créer un dossier</span>
        <span data-k="create.lead">Créer une piste</span>
        <span data-k="recap.lead">%1 rattachement(s) à enregistrer. Rien n’est écrit tant que vous n’avez pas validé.</span>
        <span data-k="recap.already">Déjà rattaché : ignoré</span>
        <span data-k="recap.enrich">Lien existant : il sera enrichi</span>
        <span data-k="recap.legacy">La colonne historique du dossier est aussi mise à jour</span>
        <span data-k="recap.none">Aucun effet sur la confidentialité</span>
        <span data-k="effect.raise">%1 : classification %2 → %3. Lisait avant : %4. Lira après : %5.</span>
        <span data-k="degraded">Base non mise à jour : la fenêtre fonctionne en lecture et pour le rattachement à un dossier. Libellé, étiquettes, alignement et autres périmètres arrivent avec la mise à jour.</span>
        <span data-k="readonly">Lecture seule : vous pouvez consulter les cibles, pas rattacher.</span>
        <span data-k="notfound">Cette pièce est introuvable ou hors de votre portée.</span>
        <span data-k="network">La demande n’a pas abouti. Vérifiez votre connexion puis recommencez.</span>
        <span data-k="done.title">Rattachement enregistré</span>
        <span data-k="done.one">%1 rattaché à %2.</span>
        <span data-k="done.many">%1 rattachements enregistrés.</span>
        <span data-k="done.aligned">Classification relevée : %1.</span>
        <span data-k="done.move">Pièce déplacée.</span>
        <span data-k="undo.done">Rattachement annulé.</span>
        <span data-k="tag.remove">Retirer l’étiquette %1</span>
        <span data-k="align.na">Sans objet : ce type de pièce ne porte pas de classification.</span>
        <span data-k="strip.meta">%1 · %2</span>
        <span data-k="reason.short">Le motif est obligatoire (5 caractères au moins).</span>
        <span data-k="title.short">Donnez un titre (3 caractères au moins).</span>
    </div>
</div>
<div class="ra-undo" data-ra-undo-bar hidden role="status">
    <span data-ra-undo-text>Rattachement enregistré.</span>
    <button type="button" class="ra-btn ra-btn--sm" data-ra-undo-btn><span>Annuler (<span data-ra-undo-count>15</span> s)</span></button>
    <button type="button" class="ra-btn ra-btn--ghost ra-btn--sm" data-ra-undo-reload hidden>Actualiser la page</button>
    <button type="button" class="ra-x ra-x--sm" data-ra-undo-close aria-label="Fermer ce message"><?= $ico($i['close'], 16) ?></button>
</div>
<script defer src="<?= $h(asset_url('assets/js/sse-rattacher.js')) ?>"></script>
