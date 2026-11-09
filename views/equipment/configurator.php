<?php
declare(strict_types=1);
/**
 * Configurateur d'équipement (/equipment/configurateur). Trois colonnes : palette d'objets (catalogue de la communauté :
 * catalogue de base d'Arma 3, export du jeu, objets vus en jeu), plan de travail en deux vues (① établi d'arme,
 * ② tenue et sacs, avec une tenue B pour copier), résumé (masse, accessoires, chargeurs, alertes, actions).
 * Guide en 4 étapes (fenêtre « Aide », ouverte une fois par membre), glisser-déposer souris / tactile et alternative
 * clavier, annuler / rétablir, import / export, enregistrement en tenue, modèle ou kit de départ.
 *
 * Tout le plan de travail est dessiné par public/assets/js/equipment-configurator.js à partir de $cfgBoot ; sans
 * JavaScript, un message l'explique. Les noms d'objets viennent du jeu : toujours insérés en texte (jamais en HTML) et
 * marqués translate="no". Styles : public/assets/css/equipment-configurator.css (thème clair et sombre).
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cfgBoot = is_array($cfgBoot ?? null) ? $cfgBoot : [];
$specs = is_array($cfgBoot['specs'] ?? null) ? $cfgBoot['specs'] : ['count' => 0, 'imported' => 0, 'last' => null];
$base = is_array($cfgBoot['base'] ?? null) ? $cfgBoot['base'] : ['available' => false];
$can = is_array($cfgBoot['can'] ?? null) ? $cfgBoot['can'] : [];
$tabLabels = \App\Support\EquipmentSpaces::SPACES;
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
$icon = static fn (string $d): string => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>';
?>
<link href="<?= $h(asset_url('assets/css/equipment-configurator.css')) ?>" rel="stylesheet">
<div class="eqx cfg" id="cfg" data-cfg data-cfg-theme="auto">
    <header class="eqx-hero eqx-hero--kit eqx-hero--short cfg-hero">
        <div class="eqx-hero__inner">
            <div class="eqx-hero__copy">
                <p class="eqx-hero__kicker"><i aria-hidden="true"></i><span><a href="<?= $h(url('equipment')) ?>">Communauté · Équipement</a> › Configurateur</span></p>
                <h1>Configurateur d’équipement</h1>
                <p class="eqx-hero__lead">Montez une arme, remplissez la tenue et le sac, puis enregistrez : la tenue vous attend dans l’arsenal en jeu.</p>
            </div>
        </div>
    </header>

    <nav class="eqx-tabs" aria-label="Espaces de la page Équipement">
        <ul>
            <?php foreach ($tabLabels as $key => $label): ?>
            <li><a href="<?= $h(\App\Support\EquipmentSpaces::url(['espace' => $key])) ?>"><?= $h($label) ?></a></li>
            <?php endforeach; ?>
            <li><a href="<?= $h(url('equipment/configurateur')) ?>" aria-current="page" class="is-active">Configurateur</a></li>
        </ul>
    </nav>

    <div class="cfg-body">
        <?php if ($flashOk !== ''): ?><p class="cfg-flash cfg-flash--ok" role="status"><?= $h($flashOk) ?></p><?php endif; ?>
        <?php if ($flashErr !== ''): ?><p class="cfg-flash cfg-flash--err" role="alert"><?= $h($flashErr) ?></p><?php endif; ?>
        <noscript><p class="cfg-flash cfg-flash--err">Le configurateur a besoin de JavaScript. Vos tenues restent consultables dans l’onglet « Mes tenues ».</p></noscript>

        <div class="cfg-base" data-cfg-base hidden>
            <span class="cfg-base__spin" aria-hidden="true"></span>
            <div class="cfg-base__txt">
                <strong data-cfg-base-title>Préparation du catalogue de base d’Arma 3</strong>
                <span class="cfg-base__bar" role="progressbar" aria-label="Chargement du catalogue de base" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-cfg-base-bar><i></i></span>
                <span class="cfg-muted" data-cfg-base-detail>Armes, accessoires, chargeurs, tenues et sacs du jeu de base et des DLC : une seule fois pour la communauté.</span>
            </div>
            <button type="button" class="cfg-btn cfg-btn--sm" data-cfg-base-retry hidden>Réessayer</button>
        </div>

        <?php if (empty($base['available']) && (int) ($specs['count'] ?? 0) === 0): ?>
        <div class="cfg-notice" data-cfg-specs-notice>
            <p><strong>Catalogue de configuration pas encore disponible.</strong> Les objets vus en jeu sont proposés, mais leur masse, la charge des sacs et les compatibilités des accessoires ne sont pas vérifiées tant que la mise à jour de la base n’est pas appliquée ou que le catalogue du jeu n’est pas importé.</p>
            <?php if (!empty($can['catalog'])): ?>
            <button type="button" class="cfg-btn cfg-btn--primary" data-cfg-open="catalog">Importer le catalogue</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="cfg-bar" role="toolbar" aria-label="Outils du configurateur">
            <div class="cfg-modes" role="tablist" aria-label="Vue du plan de travail">
                <button type="button" class="cfg-mode" role="tab" id="cfg-mode-bench" aria-controls="cfg-stage" aria-selected="true" data-cfg-mode="bench"><b aria-hidden="true">1</b> Établi d’arme</button>
                <button type="button" class="cfg-mode" role="tab" id="cfg-mode-outfit" aria-controls="cfg-stage" aria-selected="false" data-cfg-mode="outfit"><b aria-hidden="true">2</b> Tenue et sacs</button>
            </div>
            <span class="cfg-bar__spacer"></span>
            <div class="cfg-bar__group">
                <button type="button" class="cfg-btn cfg-btn--icon" data-cfg-act="undo" aria-label="Annuler (Ctrl+Z)" title="Annuler (Ctrl+Z)" disabled><?= $icon('M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-3') ?></button>
                <button type="button" class="cfg-btn cfg-btn--icon" data-cfg-act="redo" aria-label="Rétablir (Ctrl+Y)" title="Rétablir (Ctrl+Y)" disabled><?= $icon('m15 14 5-5-5-5M20 9H10a6 6 0 0 0 0 12h3') ?></button>
                <button type="button" class="cfg-btn" data-cfg-open="sources"><?= $icon('M4 4h16v16H4zM4 9h16M9 20V9') ?> Récupérer une tenue</button>
                <details class="cfg-more" data-cfg-more>
                    <summary class="cfg-btn" aria-label="Plus d’outils">Plus <?= $icon('m6 9 6 6 6-6') ?></summary>
                    <div class="cfg-more__list" role="menu">
                        <button type="button" role="menuitem" data-cfg-act="new">Nouvelle tenue <small>Repartir d’une tenue vide (Ctrl+Z pour revenir)</small></button>
                        <button type="button" role="menuitem" data-cfg-act="example">Essayer sur un exemple <small>Un fusil MX à équiper dans l’établi</small></button>
                        <hr>
                        <button type="button" role="menuitem" data-cfg-open="import">Importer une tenue <small>Texte copié depuis l’arsenal ACE ou le jeu</small></button>
                        <button type="button" role="menuitem" data-cfg-open="export">Exporter la tenue A <small>Texte pour ACE, une caisse de kits ou un membre</small></button>
                        <hr>
                        <button type="button" role="menuitem" data-cfg-open="catalog">Catalogue des objets <small>Contenu, import des mods de la communauté</small></button>
                        <button type="button" role="menuitem" data-cfg-act="theme">Thème : auto <small>Clair, sombre ou comme le système</small></button>
                    </div>
                </details>
                <button type="button" class="cfg-btn" data-cfg-act="help" aria-haspopup="dialog" title="Guide en 4 étapes, raccourcis clavier"><span class="cfg-help-dot" aria-hidden="true">?</span> Aide</button>
                <button type="button" class="cfg-btn cfg-btn--primary" data-cfg-open="save"><?= $icon('M5 4h11l3 3v13H5zM8 4v5h8V4M8 20v-6h8v6') ?> Enregistrer</button>
            </div>
        </div>

        <div class="cfg-work" data-cfg-work>
            <aside class="cfg-panel cfg-palette" aria-labelledby="cfg-palette-title">
                <div class="cfg-palette__head">
                    <div>
                        <h2 class="cfg-panel__title" id="cfg-palette-title">Palette</h2>
                        <p class="cfg-help" data-cfg-palette-help>Glissez un objet sur le plan, ou cliquez sur ＋. Exemple : tapez <em>MX</em>.</p>
                    </div>
                    <label class="cfg-search">
                        <span class="cfg-sr">Chercher un objet</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM20 20l-4-4"/></svg>
                        <input type="search" placeholder="Nom ou classe : MX, 6.5, carryall…" autocomplete="off" spellcheck="false" data-cfg-search>
                    </label>
                    <div class="cfg-tabs" data-cfg-families role="tablist" aria-label="Type d’objet"></div>
                    <div class="cfg-chips" data-cfg-cats role="group" aria-label="Catégories" hidden></div>
                    <label class="cfg-toggle" data-cfg-showall-wrap hidden><input type="checkbox" data-cfg-showall> <span>Montrer aussi les incompatibles (avec la raison)</span></label>
                    <p class="cfg-palette__count" data-cfg-count aria-live="polite">Chargement du catalogue…</p>
                </div>
                <div class="cfg-vlist" data-cfg-vlist role="listbox" aria-label="Objets du catalogue" tabindex="0">
                    <div class="cfg-vlist__spacer" data-cfg-vspacer></div>
                </div>
                <div class="cfg-empty" data-cfg-palette-empty hidden></div>
            </aside>

            <main class="cfg-stage" id="cfg-stage" data-cfg-stage aria-live="off"></main>

            <aside class="cfg-summary" data-cfg-summary aria-label="Résumé de la tenue A"></aside>
        </div>

        <div class="cfg-trash" data-cfg-drop="trash" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg> Lâchez ici pour retirer</div>
        <p class="cfg-sr" data-cfg-live role="status" aria-live="polite"></p>
        <div class="cfg-toast" data-cfg-toast role="status" hidden></div>
    </div>

    <dialog class="cfg-dialog cfg-tour" id="cfg-tour" aria-labelledby="cfg-tour-title">
        <div class="cfg-dialog__shell">
            <header class="cfg-dialog__head">
                <div>
                    <p class="cfg-kicker">Aide · 1 minute</p>
                    <h2 id="cfg-tour-title">Comment ça marche</h2>
                </div>
                <button type="button" class="cfg-dialog__close" data-cfg-close aria-label="Fermer l’aide">×</button>
            </header>
            <ol class="cfg-tour__steps" data-cfg-tour-steps>
                <li class="cfg-tour__step is-current" data-step="0">
                    <span class="cfg-tour__num" aria-hidden="true">1</span>
                    <svg class="cfg-tour__fig" viewBox="0 0 160 90" aria-hidden="true">
                        <rect class="f-fill" x="8" y="10" width="46" height="70" rx="5"/>
                        <rect class="f-line" x="13" y="17" width="36" height="8" rx="2"/>
                        <rect class="f-acc" x="13" y="30" width="36" height="10" rx="2"/>
                        <rect class="f-line" x="13" y="45" width="36" height="8" rx="2"/>
                        <rect class="f-line" x="13" y="58" width="36" height="8" rx="2"/>
                        <rect class="f-dash f-target" x="70" y="30" width="80" height="34" rx="6"/>
                        <g class="f-drag" style="--dx: 66px; --dy: 4px"><path class="f-ink" d="M14 36h20l3-3h7v4h-4l-2 4h-5l-1-2h-12z"/></g>
                        <text class="f-txt" x="110" y="80" text-anchor="middle">ÉTABLI</text>
                    </svg>
                    <h3>Choisissez une arme</h3>
                    <p>Dans la palette à gauche, glissez une arme sur l’établi (ou cliquez sur ＋). <b>Exemple :</b> tapez « MX » puis glissez le MX 6,5 mm.</p>
                </li>
                <li class="cfg-tour__step" data-step="1">
                    <span class="cfg-tour__num" aria-hidden="true">2</span>
                    <svg class="cfg-tour__fig" viewBox="0 0 160 90" aria-hidden="true">
                        <path class="f-ink" d="M30 48h70l8-7h22v10h-12l-5 10h-14l-3-6H62l-8 13H40l4-13h-14z"/>
                        <rect class="f-dash f-target" x="54" y="8" width="62" height="24" rx="4"/>
                        <text class="f-txt" x="85" y="23" text-anchor="middle">OPTIQUE</text>
                        <rect class="f-fill" x="120" y="62" width="34" height="20" rx="4"/>
                        <path class="f-bad" d="M126 66l22 12M148 66l-22 12"/>
                        <g class="f-drag" style="--dx: 52px; --dy: -46px"><rect class="f-acc" x="20" y="60" width="26" height="12" rx="3"/></g>
                    </svg>
                    <h3>Glissez les accessoires</h3>
                    <p>Seuls les accessoires compatibles s’accrochent ; sinon l’emplacement dit pourquoi. <b>Exemple :</b> onglet « Optiques », glissez une ACO sur « Optique ».</p>
                </li>
                <li class="cfg-tour__step" data-step="2">
                    <span class="cfg-tour__num" aria-hidden="true">3</span>
                    <svg class="cfg-tour__fig" viewBox="0 0 160 90" aria-hidden="true">
                        <rect class="f-fill" x="14" y="12" width="60" height="66" rx="6"/>
                        <rect class="f-fill" x="86" y="12" width="60" height="66" rx="6"/>
                        <text class="f-txt" x="44" y="26" text-anchor="middle">GILET</text>
                        <text class="f-txt" x="116" y="26" text-anchor="middle">SAC</text>
                        <rect class="f-line" x="22" y="64" width="44" height="6" rx="3"/>
                        <rect class="f-acc f-fillbar" x="22" y="64" width="44" height="6" rx="3"/>
                        <rect class="f-line" x="94" y="64" width="44" height="6" rx="3"/>
                        <rect class="f-acc" x="94" y="64" width="14" height="6" rx="3"/>
                        <g class="f-drag" style="--dx: -64px; --dy: 4px"><rect class="f-acc" x="104" y="36" width="10" height="18" rx="2"/></g>
                    </svg>
                    <h3>Remplissez la tenue</h3>
                    <p>Vue « Tenue et sacs » : la jauge de chaque conteneur dit ce qu’il reste. <b>Exemple :</b> glissez 6 chargeurs dans le gilet ; <kbd>Ctrl</kbd> en glissant copie au lieu de déplacer.</p>
                </li>
                <li class="cfg-tour__step" data-step="3">
                    <span class="cfg-tour__num" aria-hidden="true">4</span>
                    <svg class="cfg-tour__fig" viewBox="0 0 160 90" aria-hidden="true">
                        <rect class="f-fill" x="12" y="20" width="56" height="50" rx="6"/>
                        <path class="f-acc-line" d="M28 46l8 8 16-18"/>
                        <path class="f-line" d="M76 45h26M96 39l6 6-6 6"/>
                        <rect class="f-fill" x="108" y="20" width="42" height="50" rx="6"/>
                        <text class="f-txt" x="129" y="49" text-anchor="middle">JEU</text>
                    </svg>
                    <h3>Enregistrez</h3>
                    <p>Dans vos tenues, en modèle ou en kit de départ. <b>Exemple :</b> « Enregistrer › Mes tenues », puis en jeu : arsenal ACE › Athena · Tenues › Enfiler.</p>
                </li>
            </ol>
            <details class="cfg-tour__keys">
                <summary>Raccourcis clavier</summary>
                <dl>
                    <div><dt><kbd>Ctrl</kbd> + <kbd>Z</kbd> / <kbd>Y</kbd></dt><dd>Annuler / rétablir</dd></div>
                    <div><dt><kbd>Ctrl</kbd> en glissant</dt><dd>Copier au lieu de déplacer</dd></div>
                    <div><dt><kbd>Entrée</kbd></dt><dd>Actions de l’objet sélectionné</dd></div>
                    <div><dt><kbd>Ctrl</kbd> + <kbd>C</kbd> / <kbd>V</kbd></dt><dd>Copier, puis coller sur un emplacement</dd></div>
                    <div><dt><kbd>Suppr</kbd></dt><dd>Retirer l’objet sélectionné</dd></div>
                    <div><dt><kbd>/</kbd></dt><dd>Chercher dans la palette</dd></div>
                    <div><dt><kbd>Ctrl</kbd> + <kbd>S</kbd></dt><dd>Enregistrer</dd></div>
                    <div><dt><kbd>Échap</kbd></dt><dd>Annuler un glisser en cours</dd></div>
                </dl>
            </details>
            <footer class="cfg-dialog__foot">
                <div class="cfg-tour__dots" data-cfg-tour-dots aria-label="Étapes"></div>
                <button type="button" class="cfg-btn" data-cfg-tour-prev>Précédent</button>
                <button type="button" class="cfg-btn" data-cfg-tour-next>Suivant</button>
                <button type="button" class="cfg-btn cfg-btn--primary" data-cfg-act="example">Essayer sur un exemple</button>
            </footer>
        </div>
    </dialog>

    <dialog class="cfg-dialog" id="cfg-sources" aria-labelledby="cfg-sources-title">
        <div class="cfg-dialog__shell">
            <header class="cfg-dialog__head">
                <h2 id="cfg-sources-title">Récupérer une tenue</h2>
                <button type="button" class="cfg-dialog__close" data-cfg-close aria-label="Fermer">×</button>
            </header>
            <p class="cfg-muted">Ouvrez une tenue existante dans la tenue A pour la modifier, ou dans la tenue B pour en copier une partie. L’original n’est jamais modifié tant que vous n’enregistrez pas. Exemple : un kit « Fusilier » en B, puis glissez son gilet sur A.</p>
            <label class="cfg-search"><span class="cfg-sr">Filtrer les tenues</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14zM20 20l-4-4"/></svg><input type="search" placeholder="Filtrer : nom, membre, rôle" data-cfg-sources-q></label>
            <div class="cfg-sources" data-cfg-sources-list><p class="cfg-muted">Chargement…</p></div>
        </div>
    </dialog>

    <dialog class="cfg-dialog" id="cfg-import" aria-labelledby="cfg-import-title">
        <div class="cfg-dialog__shell">
            <header class="cfg-dialog__head">
                <h2 id="cfg-import-title">Importer une tenue</h2>
                <button type="button" class="cfg-dialog__close" data-cfg-close aria-label="Fermer">×</button>
            </header>
            <p class="cfg-muted">Collez une tenue copiée depuis le configurateur, depuis l’arsenal ACE (Exporter), ou depuis le jeu avec l’action ACE « Équipement › Copier ma tenue pour Athena ». Exemple : <code translate="no">[["arifle_MX_F","","","optic_Aco",["30Rnd_65x39_caseless_mag",30],[],""],[],[],["U_B_CombatUniform_mcam",[]],[],[],"H_HelmetB","",[],["ItemMap","","","ItemCompass","ItemWatch",""]]</code></p>
            <label class="cfg-field"><span>Texte de la tenue</span><textarea rows="8" spellcheck="false" data-cfg-import-text></textarea></label>
            <div class="cfg-report" data-cfg-import-report hidden></div>
            <footer class="cfg-dialog__foot">
                <button type="button" class="cfg-btn" data-cfg-import-analyse>Analyser</button>
                <button type="button" class="cfg-btn" data-cfg-import-strip hidden>Retirer les objets inconnus</button>
                <button type="button" class="cfg-btn cfg-btn--primary" data-cfg-import-open="a" disabled>Ouvrir dans la tenue A</button>
                <button type="button" class="cfg-btn" data-cfg-import-open="b" disabled>Ouvrir dans la tenue B</button>
            </footer>
        </div>
    </dialog>

    <dialog class="cfg-dialog" id="cfg-export" aria-labelledby="cfg-export-title">
        <div class="cfg-dialog__shell">
            <header class="cfg-dialog__head">
                <h2 id="cfg-export-title">Exporter la tenue A</h2>
                <button type="button" class="cfg-dialog__close" data-cfg-close aria-label="Fermer">×</button>
            </header>
            <p class="cfg-muted">Texte à garder, à envoyer à un membre ou à recoller ici plus tard (Importer). Le format « ACE » se colle dans l’arsenal ACE (bouton Importer) ou dans une caisse de kits du mod SSE (attribut Eden).</p>
            <div class="cfg-seg" role="radiogroup" aria-label="Format">
                <label><input type="radio" name="cfg-export-format" value="cfg" checked data-cfg-export-format> Configurateur (avec le nom)</label>
                <label><input type="radio" name="cfg-export-format" value="ace" data-cfg-export-format> ACE / getUnitLoadout</label>
            </div>
            <label class="cfg-field"><span>Texte</span><textarea rows="8" readonly spellcheck="false" data-cfg-export-text translate="no"></textarea></label>
            <footer class="cfg-dialog__foot">
                <button type="button" class="cfg-btn cfg-btn--primary" data-cfg-export-copy>Copier</button>
                <span class="cfg-muted" data-cfg-export-status role="status"></span>
            </footer>
        </div>
    </dialog>

    <dialog class="cfg-dialog" id="cfg-save" aria-labelledby="cfg-save-title">
        <form class="cfg-dialog__shell" data-cfg-save-form>
            <header class="cfg-dialog__head">
                <h2 id="cfg-save-title">Enregistrer la tenue A</h2>
                <button type="button" class="cfg-dialog__close" data-cfg-close aria-label="Fermer">×</button>
            </header>
            <fieldset class="cfg-targets">
                <legend>Où l’enregistrer</legend>
                <label class="cfg-target"><input type="radio" name="target" value="tenue" checked> <span><strong>Mes tenues</strong> Personnelle. En jeu : arsenal ACE, panneau « Athena · Tenues », Enfiler.</span></label>
                <?php if (!empty($can['kits'])): ?>
                <label class="cfg-target"><input type="radio" name="target" value="modele"> <span><strong>Modèle de la communauté</strong> Point de départ que tout le monde peut récupérer ici.</span></label>
                <label class="cfg-target"><input type="radio" name="target" value="kit"> <span><strong>Kit de départ</strong> Proposé à un rôle, une unité, une qualification ou un membre ; historique des versions ; pris en jeu à une caisse de kits.</span></label>
                <?php endif; ?>
            </fieldset>
            <label class="cfg-field"><span>Nom</span><input type="text" name="name" maxlength="90" required placeholder="Ex. Fusilier MX — désert"></label>
            <label class="cfg-field"><span>Description (facultatif)</span><textarea name="description" rows="2" maxlength="1000" placeholder="Ex. Kit standard du groupe de combat, 6 chargeurs, trousse de secours."></textarea></label>
            <?php if (!empty($can['kits'])): ?>
            <div data-cfg-kit-fields hidden>
                <label class="cfg-field"><span>À qui proposer ce kit</span><select name="audience" data-cfg-audience></select></label>
                <label class="cfg-field"><span>Note de version (facultatif)</span><input type="text" name="note" maxlength="190" placeholder="Ex. Optique remplacée par une RCO"></label>
            </div>
            <?php endif; ?>
            <label class="cfg-check" data-cfg-save-update-wrap hidden><input type="checkbox" name="update" value="1" checked data-cfg-save-update> <span data-cfg-save-update-label>Mettre à jour l’original</span></label>
            <div class="cfg-report" data-cfg-save-report hidden></div>
            <footer class="cfg-dialog__foot">
                <button type="button" class="cfg-btn" data-cfg-close>Annuler</button>
                <button type="submit" class="cfg-btn cfg-btn--primary">Enregistrer</button>
            </footer>
        </form>
    </dialog>

    <dialog class="cfg-dialog" id="cfg-versions" aria-labelledby="cfg-versions-title">
        <div class="cfg-dialog__shell">
            <header class="cfg-dialog__head">
                <h2 id="cfg-versions-title">Historique du kit</h2>
                <button type="button" class="cfg-dialog__close" data-cfg-close aria-label="Fermer">×</button>
            </header>
            <p class="cfg-muted">Chaque enregistrement du kit garde une version (50 au plus). Ouvrez une version dans la tenue B pour la comparer, ou restaurez-la : elle devient la version en cours et rien n’est effacé.</p>
            <div class="cfg-versions" data-cfg-versions-list></div>
        </div>
    </dialog>

    <dialog class="cfg-dialog" id="cfg-catalog" aria-labelledby="cfg-catalog-title">
        <form class="cfg-dialog__shell" data-cfg-catalog-form enctype="multipart/form-data">
            <header class="cfg-dialog__head">
                <h2 id="cfg-catalog-title">Catalogue des objets</h2>
                <button type="button" class="cfg-dialog__close" data-cfg-close aria-label="Fermer">×</button>
            </header>
            <p class="cfg-muted" data-cfg-catalog-summary>Le catalogue de base d’Arma 3 (jeu de base et DLC officiels) est chargé tout seul. Les objets des mods de votre communauté s’ajoutent par un export fait en jeu ; ils passent avant le catalogue de base.</p>
            <ul class="cfg-catinfo" data-cfg-catalog-families></ul>
            <?php if (!empty($can['catalog'])): ?>
            <div class="cfg-row-actions">
                <button type="button" class="cfg-btn" data-cfg-base-reload>Recharger le catalogue de base</button>
            </div>
            <h3 class="cfg-section-title">Importer les objets des mods</h3>
            <ol class="cfg-steps">
                <li>Lancez Arma 3 avec les mods de la communauté, ouvrez l’éditeur (ou une mission solo) et la console de débogage.</li>
                <li>Avec le mod SSE : tapez <code translate="no">[] call comspec_sse_fnc_loadoutCatalog</code> puis « Exécuter local ». Sans le mod : <a href="<?= $h(url('equipment/configurateur/script-export.sqf')) ?>" download>téléchargez le script d’export</a>, collez-le dans la console et exécutez-le.</li>
                <li>Le texte est copié dans le presse-papier : collez-le ci-dessous (ou enregistrez-le dans un fichier et choisissez-le).</li>
            </ol>
            <label class="cfg-field"><span>Texte de l’export</span><textarea name="text" rows="5" spellcheck="false" placeholder="[&quot;comspec-equipment-catalog&quot;,1,…]"></textarea></label>
            <label class="cfg-field"><span>Ou un fichier (.txt, .json, 48 Mo au plus)</span><input type="file" name="file" accept=".txt,.json,.sqf,text/plain,application/json"></label>
            <label class="cfg-check"><input type="checkbox" name="replace" value="1"> <span>Remplacer tout ce qui a été importé (le catalogue de base reste ; sinon les objets importés s’ajoutent, et un autre jeu de mods se cumule)</span></label>
            <?php else: ?>
            <p class="cfg-muted">Un objet de mod manque ? Demandez à un responsable de l’équipement d’importer le catalogue du jeu (bouton « Catalogue des objets »).</p>
            <?php endif; ?>
            <div class="cfg-report" data-cfg-catalog-report hidden></div>
            <footer class="cfg-dialog__foot">
                <button type="button" class="cfg-btn" data-cfg-close>Fermer</button>
                <?php if (!empty($can['catalog'])): ?><button type="submit" class="cfg-btn cfg-btn--primary">Importer</button><?php endif; ?>
            </footer>
        </form>
    </dialog>

    <div class="cfg-menu" data-cfg-menu role="menu" hidden></div>

    <script type="application/json" id="cfg-boot"><?= json_encode($cfgBoot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
</div>
<script src="<?= $h(asset_url('assets/js/equipment-base-catalog.js')) ?>" defer></script>
<script src="<?= $h(asset_url('assets/js/equipment-configurator.js')) ?>" defer></script>
