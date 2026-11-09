<?php
declare(strict_types=1);
/**
 * Configurateur d'équipement (/equipment/configurateur) : palette d'objets (catalogue de la communauté), deux tenues
 * côte à côte (A en cours, B pour récupérer et copier), établi d'arme (accessoires, chargeurs), conteneurs ouvrables
 * avec leur charge, glisser-déposer souris / tactile et alternative clavier, annuler / rétablir, import / export JSON,
 * enregistrement en tenue, modèle ou kit de départ.
 *
 * Toute l'interface est dessinée par public/assets/js/equipment-configurator.js à partir de $cfgBoot ; sans JavaScript,
 * un message l'explique. Les noms d'objets viennent du jeu : toujours insérés en texte (jamais en HTML) et marqués
 * translate="no". Styles : public/assets/css/equipment-configurator.css (thème clair et sombre).
 */
$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cfgBoot = is_array($cfgBoot ?? null) ? $cfgBoot : [];
$specs = is_array($cfgBoot['specs'] ?? null) ? $cfgBoot['specs'] : ['count' => 0, 'last' => null];
$can = is_array($cfgBoot['can'] ?? null) ? $cfgBoot['can'] : [];
$tabLabels = \App\Support\EquipmentSpaces::SPACES;
$flashOk = trim((string) ($flash_success ?? ''));
$flashErr = trim((string) ($flash_error ?? ''));
?>
<link href="<?= $h(asset_url('assets/css/equipment-configurator.css')) ?>" rel="stylesheet">
<div class="eqx cfg" id="cfg" data-cfg data-cfg-theme="auto">
    <header class="eqx-hero eqx-hero--kit eqx-hero--short cfg-hero">
        <div class="eqx-hero__inner">
            <div class="eqx-hero__copy">
                <p class="eqx-hero__kicker"><i aria-hidden="true"></i><span><a href="<?= $h(url('equipment')) ?>">Communauté · Équipement</a> › Configurateur</span></p>
                <h1>Configurateur d’équipement</h1>
                <p class="eqx-hero__lead">Montez une arme, remplissez l’uniforme, le gilet et le sac, copiez d’une tenue à l’autre, puis enregistrez : la tenue vous attend dans l’arsenal en jeu.</p>
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

        <details class="cfg-guide" data-cfg-guide>
            <summary><span class="cfg-guide__title">Comment ça marche</span> <span class="cfg-guide__hint">3 gestes, un exemple et les raccourcis</span></summary>
            <div class="cfg-guide__grid">
                <ol class="cfg-guide__steps">
                    <li><strong>Glissez</strong> un objet de la palette (à gauche) sur un emplacement de la tenue : arme, uniforme, gilet, sac, casque, radio…</li>
                    <li><strong>Configurez l’arme</strong> : bouton « Établi » sur l’arme, puis glissez une optique, une bouche, un rail ou un bipied. Seuls les accessoires compatibles s’accrochent ; sinon la raison s’affiche.</li>
                    <li><strong>Enregistrez</strong> dans vos tenues (retrouvée en jeu dans l’arsenal, panneau « Athena · Tenues »), en modèle ou en kit de départ pour un rôle, une qualification ou un membre.</li>
                </ol>
                <div class="cfg-guide__example">
                    <h2>Exemple</h2>
                    <p>Tapez <kbd>MX</kbd> dans la recherche, glissez le fusil sur « Arme principale », ouvrez l’établi et posez une lunette sur « Optique ». Glissez ensuite des chargeurs dans le gilet : la jauge montre la charge. Ouvrez une deuxième tenue avec « Récupérer une tenue », puis glissez l’arme complète de A vers son sac en tenant <kbd>Ctrl</kbd> pour la copier.</p>
                </div>
                <div class="cfg-guide__keys">
                    <h2>Raccourcis</h2>
                    <dl>
                        <div><dt><kbd>Ctrl</kbd> + <kbd>Z</kbd></dt><dd>Annuler</dd></div>
                        <div><dt><kbd>Ctrl</kbd> + <kbd>Y</kbd></dt><dd>Rétablir</dd></div>
                        <div><dt><kbd>Ctrl</kbd> en glissant</dt><dd>Copier au lieu de déplacer</dd></div>
                        <div><dt><kbd>Entrée</kbd></dt><dd>Actions de l’objet sélectionné (ajouter, déplacer, copier, retirer)</dd></div>
                        <div><dt><kbd>Ctrl</kbd> + <kbd>C</kbd> / <kbd>V</kbd></dt><dd>Copier l’objet sélectionné, le coller sur l’emplacement sélectionné</dd></div>
                        <div><dt><kbd>Suppr</kbd></dt><dd>Retirer l’objet sélectionné</dd></div>
                        <div><dt><kbd>/</kbd></dt><dd>Chercher dans la palette</dd></div>
                        <div><dt><kbd>Ctrl</kbd> + <kbd>S</kbd></dt><dd>Enregistrer</dd></div>
                    </dl>
                </div>
            </div>
        </details>

        <?php if ((int) ($specs['count'] ?? 0) === 0): ?>
        <div class="cfg-notice" data-cfg-specs-notice>
            <p><strong>Catalogue de configuration pas encore importé.</strong> Les objets vus en jeu sont proposés, mais leur masse, la charge des sacs et les compatibilités des accessoires ne sont pas vérifiées tant que la configuration du jeu n’est pas importée.</p>
            <?php if (!empty($can['catalog'])): ?>
            <button type="button" class="cfg-btn cfg-btn--primary" data-cfg-open="catalog">Importer le catalogue</button>
            <?php else: ?>
            <p class="cfg-muted">Demandez à un responsable de l’équipement de l’importer (bouton « Importer le catalogue » de cette page).</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="cfg-toolbar" role="toolbar" aria-label="Outils du configurateur">
            <div class="cfg-toolbar__group">
                <button type="button" class="cfg-btn" data-cfg-act="new">Nouvelle tenue</button>
                <button type="button" class="cfg-btn" data-cfg-open="sources">Récupérer une tenue</button>
                <button type="button" class="cfg-btn" data-cfg-open="import">Importer (JSON)</button>
                <button type="button" class="cfg-btn" data-cfg-open="export">Exporter</button>
            </div>
            <div class="cfg-toolbar__group">
                <button type="button" class="cfg-btn cfg-btn--icon" data-cfg-act="undo" aria-label="Annuler (Ctrl+Z)" title="Annuler (Ctrl+Z)" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-3"/></svg></button>
                <button type="button" class="cfg-btn cfg-btn--icon" data-cfg-act="redo" aria-label="Rétablir (Ctrl+Y)" title="Rétablir (Ctrl+Y)" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 14 5-5-5-5M20 9H10a6 6 0 0 0 0 12h3"/></svg></button>
                <?php if (!empty($can['catalog']) && (int) ($specs['count'] ?? 0) > 0): ?>
                <button type="button" class="cfg-btn cfg-btn--quiet" data-cfg-open="catalog">Catalogue (<?= (int) $specs['count'] ?>)</button>
                <?php endif; ?>
                <button type="button" class="cfg-btn cfg-btn--quiet" data-cfg-act="theme" aria-label="Changer de thème (clair, sombre, automatique)" title="Thème">Thème</button>
                <button type="button" class="cfg-btn cfg-btn--primary" data-cfg-open="save">Enregistrer</button>
            </div>
        </div>

        <div class="cfg-work">
            <aside class="cfg-palette" aria-label="Palette d’objets">
                <div class="cfg-palette__head">
                    <label class="cfg-search">
                        <span class="cfg-sr">Chercher un objet</span>
                        <input type="search" placeholder="Chercher : nom ou classe (ex. MX, 6.5, gilet)" autocomplete="off" spellcheck="false" data-cfg-search>
                    </label>
                    <div class="cfg-chips" data-cfg-families role="group" aria-label="Familles"></div>
                    <div class="cfg-chips cfg-chips--sub" data-cfg-cats role="group" aria-label="Catégories" hidden></div>
                    <p class="cfg-palette__count" data-cfg-count aria-live="polite">Chargement du catalogue…</p>
                </div>
                <div class="cfg-vlist" data-cfg-vlist role="listbox" aria-label="Objets du catalogue" tabindex="0">
                    <div class="cfg-vlist__spacer" data-cfg-vspacer></div>
                </div>
                <div class="cfg-empty" data-cfg-palette-empty hidden></div>
            </aside>

            <div class="cfg-outfits" data-cfg-outfits>
                <section class="cfg-outfit" data-cfg-outfit="a" aria-label="Tenue A"></section>
                <section class="cfg-outfit cfg-outfit--b" data-cfg-outfit="b" aria-label="Tenue B"></section>
            </div>
        </div>

        <div class="cfg-trash" data-cfg-drop="trash" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg> Lâchez ici pour retirer</div>
        <p class="cfg-sr" data-cfg-live role="status" aria-live="polite"></p>
        <div class="cfg-toast" data-cfg-toast role="status" hidden></div>
    </div>

    <dialog class="cfg-dialog cfg-dialog--bench" id="cfg-bench" aria-labelledby="cfg-bench-title">
        <div class="cfg-dialog__shell" data-cfg-bench></div>
    </dialog>

    <dialog class="cfg-dialog" id="cfg-sources" aria-labelledby="cfg-sources-title">
        <div class="cfg-dialog__shell">
            <header class="cfg-dialog__head">
                <h2 id="cfg-sources-title">Récupérer une tenue</h2>
                <button type="button" class="cfg-dialog__close" data-cfg-close aria-label="Fermer">×</button>
            </header>
            <p class="cfg-muted">Ouvrez une tenue existante (la vôtre, celle d’un membre, un kit de départ, un modèle ou ce qui a été porté en jeu) dans la tenue A pour la modifier, ou dans la tenue B pour en copier une partie. L’original n’est jamais modifié tant que vous n’enregistrez pas.</p>
            <label class="cfg-search"><span class="cfg-sr">Filtrer les tenues</span><input type="search" placeholder="Filtrer : nom, membre, rôle" data-cfg-sources-q></label>
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
                <button type="submit" class="cfg-btn cfg-btn--primary">Enregistrer</button>
                <button type="button" class="cfg-btn" data-cfg-close>Annuler</button>
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
                <h2 id="cfg-catalog-title">Importer le catalogue de configuration</h2>
                <button type="button" class="cfg-dialog__close" data-cfg-close aria-label="Fermer">×</button>
            </header>
            <p class="cfg-muted">Masse, charge des sacs, accessoires et chargeurs compatibles viennent du jeu lui-même, avec les mods de votre communauté. L’export se fait hors ligne, en solo ou dans l’éditeur, sans aucune connexion à Athena.</p>
            <ol class="cfg-steps">
                <li>Lancez Arma 3 avec les mods de la communauté, ouvrez l’éditeur (ou une mission solo) et la console de débogage.</li>
                <li>Avec le mod SSE : tapez <code translate="no">[] call comspec_sse_fnc_loadoutCatalog</code> puis « Exécuter local ». Sans le mod : <a href="<?= $h(url('equipment/configurateur/script-export.sqf')) ?>" download>téléchargez le script d’export</a>, collez-le dans la console et exécutez-le.</li>
                <li>Le texte est copié dans le presse-papier : collez-le ci-dessous (ou enregistrez-le dans un fichier et choisissez-le).</li>
            </ol>
            <label class="cfg-field"><span>Texte de l’export</span><textarea name="text" rows="5" spellcheck="false" placeholder="[&quot;comspec-equipment-catalog&quot;,1,…]"></textarea></label>
            <label class="cfg-field"><span>Ou un fichier (.txt, .json, 48 Mo au plus)</span><input type="file" name="file" accept=".txt,.json,.sqf,text/plain,application/json"></label>
            <label class="cfg-check"><input type="checkbox" name="replace" value="1"> <span>Remplacer tout le catalogue (sinon les objets importés s’ajoutent, et un autre jeu de mods se cumule)</span></label>
            <div class="cfg-report" data-cfg-catalog-report hidden></div>
            <footer class="cfg-dialog__foot">
                <button type="submit" class="cfg-btn cfg-btn--primary">Importer</button>
                <button type="button" class="cfg-btn" data-cfg-close>Fermer</button>
            </footer>
        </form>
    </dialog>

    <div class="cfg-menu" data-cfg-menu role="menu" hidden></div>

    <script type="application/json" id="cfg-boot"><?= json_encode($cfgBoot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
</div>
<script src="<?= $h(asset_url('assets/js/equipment-configurator.js')) ?>" defer></script>
