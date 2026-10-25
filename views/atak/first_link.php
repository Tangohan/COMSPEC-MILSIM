<?php
declare(strict_types=1);

/**
 * Première liaison : relier le téléphone de l'ATAK natif (@COMSPEC_ATAK_Native) au compte Athena.
 * Cinq étapes (installer, sortir le téléphone, se connecter, vérifier, dépanner), schéma de l'app Athena
 * sur le Samsung S7, état du compte et du mod. Servie aussi dans le back-office (/back-office/ma-situation/premiere-liaison).
 */

$fl = is_array($firstLink ?? null) ? $firstLink : [];
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$accountReady = !empty($fl['account_ready']);
$steamLinked = !empty($fl['steam_linked']);
$hasIdentity = !empty($fl['has_identity']);
$hasMod = !empty($fl['has_mod']);
$gameLinkReady = !empty($fl['game_link_ready']);
$canViewOperators = !empty($fl['can_view_operators']);

$displayName = (string) ($fl['display_name'] ?? '');
$callsign = (string) ($fl['callsign'] ?? '');
$identityLabel = $callsign !== '' ? $callsign : $displayName;

$accountUrl = (string) ($fl['account_url'] ?? url('account/preferences'));
$modPageUrl = (string) ($fl['mod_page_url'] ?? url('atak/mod'));
$modDownloadUrl = $fl['mod_download_url'] ?? null;
$tutoUrl = (string) ($fl['tuto_url'] ?? url('atak/tuto'));
$atakUrl = (string) ($fl['atak_url'] ?? url('atak'));
$operateursUrl = (string) ($fl['operateurs_url'] ?? url('back-office/atak/operateurs'));
$gameLinkUrl = (string) ($fl['game_link_url'] ?? url('atak/game-link'));
$lierUrl = (string) ($fl['lier_url'] ?? url('lier'));
$versionApiUrl = (string) ($fl['version_api_url'] ?? url('api/atak/version'));
$atakVersion = (string) ($fl['atak_version'] ?? '');
$linkVersion = (string) ($fl['link_version'] ?? '');

// Adresse du portail (APP_URL), aussi codée dans le mod : rien à saisir en jeu.
// Les mods publiés avant la migration appellent encore https://athena.ttrd.fr/public, toujours servi.
$portalAddress = 'https://' . \App\Support\PortalHost::host() . '/public';
// Ce que le téléphone affiche (fn_pageAthena : « https:// » et « /public » retirés).
$phoneAddress = \App\Support\PortalHost::label('lier');

$stepsNav = [
    ['id' => 'fl-installer', 'label' => 'Installer le mod', 'done' => false],
    ['id' => 'fl-telephone', 'label' => 'Sortir le téléphone', 'done' => false],
    ['id' => 'fl-connexion', 'label' => 'Se connecter', 'done' => false],
    ['id' => 'fl-verifier', 'label' => 'Vérifier la liaison', 'done' => false],
    ['id' => 'fl-depannage', 'label' => 'Dépannage', 'done' => false],
];

$icon = static function (string $name): string {
    $paths = [
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'phone' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M7 6v12"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9"/><path d="m17 6 3 3"/>',
        'check' => '<path d="M4 12.5 9 17.5 20 6.5"/>',
        'wrench' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
        'external' => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
        'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
        'file' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/>',
        'radar' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 12 18 6"/>',
        'map' => '<path d="m9 4-6 2v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14"/><path d="M15 6v14"/>',
        'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
    ];

    return '<svg class="fl-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
};
?>
<link href="<?= $e(asset_url('assets/css/atak-first-link.css')) ?>" rel="stylesheet">

<div class="fl" data-first-link data-game-link-url="<?= $e($gameLinkUrl) ?>" data-me-link-url="<?= $e(url('api/atak/me-link-status')) ?>" data-version-url="<?= $e($versionApiUrl) ?>">

    <header class="fl-hero">
        <div class="fl-hero__text">
            <p class="fl-kicker">ATAK natif · mise en service</p>
            <h1 class="fl-hero__title">Relier votre téléphone ATAK à Athena</h1>
            <p class="fl-hero__lead">
                Cinq étapes, une seule fois. Ensuite, votre téléphone en jeu se connecte tout seul :
                position, marqueurs, messages et photos remontent au poste de commandement.
            </p>
            <ul class="fl-facts">
                <li>
                    <span class="fl-facts__label">Mod</span>
                    <span class="fl-facts__value" translate="no">@COMSPEC_ATAK_Native</span>
                </li>
                <li>
                    <span class="fl-facts__label">Version</span>
                    <span class="fl-facts__value" data-fl-version translate="no"><?= $e($atakVersion !== '' ? $atakVersion : '—') ?></span>
                </li>
                <li>
                    <span class="fl-facts__label">Portail</span>
                    <span class="fl-facts__value" translate="no"><?= $e(\App\Support\PortalHost::host()) ?></span>
                </li>
            </ul>
            <div class="fl-hero__actions">
                <a class="fl-btn fl-btn--primary" href="#fl-installer">Commencer <?= $icon('arrow') ?></a>
                <a class="fl-btn fl-btn--ghost" href="<?= $e($lierUrl) ?>">J’ai déjà un code <?= $icon('external') ?></a>
            </div>
        </div>

        <figure class="fl-hero__figure">
            <div class="fl-phone" role="img" aria-label="Schéma du téléphone ATAK (Samsung S7) : app Athena, onglet Compte, avec l’adresse à ouvrir et le code à saisir">
                <div class="fl-phone__case">
                    <div class="fl-phone__body">
                        <span class="fl-phone__brand" aria-hidden="true">SAMSUNG</span>
                        <div class="fl-phone__screen" aria-hidden="true">
                            <div class="fl-ui__status">
                                <span class="fl-ui__batt"><i></i></span><span>96%</span>
                                <span class="fl-ui__clock">16:06</span>
                                <span class="fl-ui__signal"><i></i><i></i><i></i><i></i></span>
                            </div>
                            <div class="fl-ui__bar"><span class="fl-ui__back">‹</span>ATHENA</div>
                            <div class="fl-ui__main">
                                <div class="fl-ui__rail"><i></i><i></i><i></i><i></i><i></i></div>
                                <div class="fl-ui__page">
                                    <div class="fl-ui__hero">
                                        <span class="fl-ui__logo"></span>
                                        <span><b>ATHENA</b> <em class="fl-ui__pill--off">● HORS LIGNE</em><br><small>Liaison avec le portail et le poste de commandement</small></span>
                                    </div>
                                    <div class="fl-ui__tabs"><span class="is-on">COMPTE</span><span>STEAM</span><span>E-MAIL</span><span>CODE PORTAIL</span></div>
                                    <p class="fl-ui__txt">Sur votre ordinateur ou votre téléphone, connecté à votre compte Athena, ouvrez :</p>
                                    <p class="fl-ui__addr"><?= $e($phoneAddress) ?></p>
                                    <p class="fl-ui__txt">puis saisissez ce code :</p>
                                    <p class="fl-ui__code">K7Q2-9XMB</p>
                                    <div class="fl-ui__btns"><span class="is-main">OUVRIR LA PAGE</span><span>NOUVEAU CODE</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <figcaption>App Athena du téléphone, onglet Compte (exemple de code).</figcaption>
        </figure>
    </header>

    <div class="fl-layout">
        <aside class="fl-aside">
            <nav class="fl-nav" aria-label="Étapes">
                <p class="fl-aside__title">Étapes</p>
                <ol>
                    <?php foreach ($stepsNav as $i => $s): ?>
                    <li><a href="#<?= $e($s['id']) ?>" data-fl-nav="<?= $e($s['id']) ?>"><span class="fl-nav__num"><?= $i + 1 ?></span><span><?= $e($s['label']) ?></span></a></li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <section class="fl-card fl-status" aria-labelledby="fl-status-title">
                <p class="fl-aside__title" id="fl-status-title">Votre situation</p>
                <ul class="fl-checks">
                    <li class="<?= $hasMod ? 'is-ok' : 'is-warn' ?>">
                        <span class="fl-checks__mark" aria-hidden="true"><?= $hasMod ? '✓' : '!' ?></span>
                        <span><?= $hasMod ? 'Mod disponible au téléchargement' : 'Mod pas encore publié pour votre communauté' ?></span>
                    </li>
                    <li class="<?= $hasIdentity ? 'is-ok' : 'is-warn' ?>">
                        <span class="fl-checks__mark" aria-hidden="true"><?= $hasIdentity ? '✓' : '!' ?></span>
                        <span><?php if ($hasIdentity): ?>Indicatif : <b translate="no"><?= $e($identityLabel) ?></b><?php else: ?>Indicatif à renseigner<?php endif; ?></span>
                    </li>
                    <li class="<?= $steamLinked ? 'is-ok' : 'is-idle' ?>">
                        <span class="fl-checks__mark" aria-hidden="true"><?= $steamLinked ? '✓' : '–' ?></span>
                        <span><?= $steamLinked ? 'Steam relié à votre fiche' : 'Steam non relié (utile pour la connexion Steam)' ?></span>
                    </li>
                </ul>
                <?php if (!$accountReady): ?>
                <a class="fl-link" href="<?= $e($accountUrl) ?>">Compléter ma fiche <?= $icon('arrow') ?></a>
                <?php endif; ?>
            </section>
        </aside>

        <div class="fl-main">

            <!-- 1. Installer -->
            <section class="fl-step" id="fl-installer" aria-labelledby="fl-installer-title">
                <header class="fl-step__head">
                    <span class="fl-step__num" aria-hidden="true">1</span>
                    <div>
                        <p class="fl-step__kicker">Étape 1 · sur votre PC</p>
                        <h2 class="fl-step__title" id="fl-installer-title">Installer le mod</h2>
                    </div>
                </header>
                <div class="fl-step__body">
                    <ol class="fl-list">
                        <li>Téléchargez le mod <b translate="no">@COMSPEC_ATAK_Native</b> et placez le dossier avec vos autres mods.</li>
                        <li>Dans le lanceur d’Arma 3, activez <b translate="no">CBA_A3</b> puis <b translate="no">@COMSPEC_ATAK_Native</b>. Rien d’autre n’est requis.</li>
                        <li>N’ajoutez pas l’ancien pack Overwatch : il est déjà inclus dans le mod.</li>
                    </ol>
                    <div class="fl-version">
                        <div>
                            <p class="fl-version__label">Dernière version</p>
                            <p class="fl-version__value" translate="no"><span data-fl-version><?= $e($atakVersion !== '' ? $atakVersion : '—') ?></span><small> · COMSPEC Link <span data-fl-link><?= $e($linkVersion !== '' ? $linkVersion : '—') ?></span></small></p>
                        </div>
                        <p class="fl-version__note">Le mod compare sa version au démarrage et vous prévient s’il est périmé. Après une mise à jour, quittez Arma complètement avant de relancer.</p>
                    </div>
                    <div class="fl-actions">
                        <?php if ($hasMod && $modDownloadUrl): ?>
                        <a class="fl-btn fl-btn--primary" href="<?= $e((string) $modDownloadUrl) ?>"><?= $icon('download') ?> Télécharger le mod</a>
                        <?php endif; ?>
                        <a class="fl-btn fl-btn--ghost" href="<?= $e($modPageUrl) ?>">Page de téléchargement</a>
                    </div>
                    <?php if (!$hasMod): ?>
                    <p class="fl-note fl-note--warn">Aucun fichier n’est encore publié pour votre communauté. Un administrateur doit le déposer ; vous pouvez déjà préparer votre fiche.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 2. Sortir le téléphone -->
            <section class="fl-step" id="fl-telephone" aria-labelledby="fl-telephone-title">
                <header class="fl-step__head">
                    <span class="fl-step__num" aria-hidden="true">2</span>
                    <div>
                        <p class="fl-step__kicker">Étape 2 · en jeu</p>
                        <h2 class="fl-step__title" id="fl-telephone-title">Sortir le téléphone</h2>
                    </div>
                </header>
                <div class="fl-step__body">
                    <div class="fl-keys">
                        <div class="fl-key">
                            <p class="fl-key__combo" translate="no"><kbd>Ctrl</kbd> + <kbd>U</kbd></p>
                            <p class="fl-key__text"><b>Porté.</b> Le téléphone s’affiche dans le coin et vous continuez à jouer.</p>
                        </div>
                        <div class="fl-key">
                            <p class="fl-key__combo"><kbd translate="no">Ctrl</kbd> + <kbd>⇧ Maj</kbd> + <kbd translate="no">U</kbd></p>
                            <p class="fl-key__text"><b>En main.</b> Souris et clavier pour toucher l’écran.</p>
                        </div>
                    </div>
                    <p class="fl-copy">Prenez-le en main, puis ouvrez <b>Athena</b> dans les applications. Au premier lancement, le téléphone vous la propose de lui-même.</p>
                </div>
            </section>

            <!-- 3. Se connecter -->
            <section class="fl-step" id="fl-connexion" aria-labelledby="fl-connexion-title">
                <header class="fl-step__head">
                    <span class="fl-step__num" aria-hidden="true">3</span>
                    <div>
                        <p class="fl-step__kicker">Étape 3 · jeu et navigateur</p>
                        <h2 class="fl-step__title" id="fl-connexion-title">Se connecter à Athena</h2>
                    </div>
                </header>
                <div class="fl-step__body">
                    <div class="fl-method fl-method--main">
                        <p class="fl-method__tag">Recommandé</p>
                        <h3 class="fl-method__title">Avec votre compte Athena</h3>
                        <ol class="fl-list">
                            <li>Dans l’app Athena, onglet <b>COMPTE</b> : le téléphone affiche une adresse et un code.</li>
                            <li>Sur votre ordinateur ou votre téléphone, connecté à Athena, ouvrez <b translate="no"><?= $e($phoneAddress) ?></b>. Le bouton <b>OUVRIR LA PAGE</b> du jeu l’ouvre avec le code déjà rempli.</li>
                            <li>Vérifiez que le code est le même, puis validez.</li>
                            <li>Revenez dans Arma : le téléphone se connecte et synchronise votre profil.</li>
                        </ol>
                        <div class="fl-actions">
                            <a class="fl-btn fl-btn--primary" href="<?= $e($lierUrl) ?>">Ouvrir la page Lier <?= $icon('external') ?></a>
                        </div>
                    </div>

                    <p class="fl-sub">Autres façons de se connecter</p>
                    <div class="fl-methods">
                        <div class="fl-method">
                            <h3 class="fl-method__title">Steam</h3>
                            <p class="fl-copy">Onglet <b>STEAM</b>, puis <b>CONNEXION STEAM</b>. Il faut que votre identifiant Steam soit sur votre fiche.</p>
                            <p class="fl-pill <?= $steamLinked ? 'is-ok' : 'is-idle' ?>"><?= $steamLinked ? 'Steam relié' : 'Steam non relié' ?></p>
                        </div>
                        <div class="fl-method">
                            <h3 class="fl-method__title">E-mail</h3>
                            <p class="fl-copy">Onglet <b>E-MAIL</b> : votre adresse et votre mot de passe Athena, ou un code reçu par e-mail.</p>
                        </div>
                        <div class="fl-method">
                            <h3 class="fl-method__title">Code du portail</h3>
                            <p class="fl-copy">Générez un code ici, puis onglet <b>CODE PORTAIL</b> et <b>LIER CE JEU À MON COMPTE</b>. Valable 30 minutes.</p>
                            <?php if ($gameLinkReady): ?>
                            <button type="button" class="fl-btn fl-btn--ghost fl-btn--sm" id="fl-game-link-btn">Générer un code</button>
                            <div class="fl-code" id="fl-game-link-result" hidden>
                                <p class="fl-code__value" id="fl-game-link-code" translate="no">————</p>
                                <p class="fl-code__meta" id="fl-game-link-meta"></p>
                                <button type="button" class="fl-btn fl-btn--ghost fl-btn--sm" id="fl-game-link-copy"><?= $icon('copy') ?> Copier</button>
                            </div>
                            <p class="fl-note fl-note--error" id="fl-game-link-error" role="alert" hidden></p>
                            <?php else: ?>
                            <p class="fl-note">Non disponible sur ce serveur pour le moment.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="fl-note fl-note--info">
                        L’adresse du portail est intégrée au mod (<span translate="no"><?= $e($portalAddress) ?></span>) :
                        aucune adresse, clé de communauté ni identifiant à saisir dans le jeu.
                    </p>
                </div>
            </section>

            <!-- 4. Vérifier -->
            <section class="fl-step" id="fl-verifier" aria-labelledby="fl-verifier-title">
                <header class="fl-step__head">
                    <span class="fl-step__num" aria-hidden="true" id="fl-verifier-badge">4</span>
                    <div>
                        <p class="fl-step__kicker">Étape 4 · contrôle</p>
                        <h2 class="fl-step__title" id="fl-verifier-title">Vérifier la liaison</h2>
                    </div>
                </header>
                <div class="fl-step__body">
                    <p class="fl-copy">Regardez l’état affiché en haut de l’app Athena.</p>
                    <dl class="fl-states">
                        <div class="fl-state">
                            <dt><span class="fl-dot fl-dot--ok"></span>CONNECTÉ</dt>
                            <dd>Tout fonctionne : le poste vous voit et vous recevez ordres et messages.</dd>
                        </div>
                        <div class="fl-state">
                            <dt><span class="fl-dot fl-dot--warn"></span>SESSION OUVERTE</dt>
                            <dd>Compte reconnu, canal fermé : appuyez sur <b>OUVRIR LE CANAL POSTE</b>.</dd>
                        </div>
                        <div class="fl-state">
                            <dt><span class="fl-dot fl-dot--off"></span>HORS LIGNE</dt>
                            <dd>Pas de liaison : passez à l’étape 5.</dd>
                        </div>
                    </dl>
                    <p class="fl-copy">Depuis ce site, vérifiez aussi que le poste reçoit votre position (bougez un peu en jeu, jusqu’à une minute).</p>
                    <div class="fl-presence" id="fl-presence-status" role="status" aria-live="polite">Pas encore vérifié.</div>
                    <div class="fl-actions">
                        <button type="button" class="fl-btn fl-btn--primary" id="fl-presence-btn"><?= $icon('radar') ?> Vérifier ma présence</button>
                        <a class="fl-btn fl-btn--ghost" href="<?= $e($atakUrl) ?>"><?= $icon('map') ?> Ouvrir la carte</a>
                        <?php if ($canViewOperators): ?>
                        <a class="fl-btn fl-btn--ghost" href="<?= $e($operateursUrl) ?>">Opérateurs en liaison</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- 5. Dépannage -->
            <section class="fl-step" id="fl-depannage" aria-labelledby="fl-depannage-title">
                <header class="fl-step__head">
                    <span class="fl-step__num" aria-hidden="true">5</span>
                    <div>
                        <p class="fl-step__kicker">Étape 5 · si ça ne marche pas</p>
                        <h2 class="fl-step__title" id="fl-depannage-title">Dépannage</h2>
                    </div>
                </header>
                <div class="fl-step__body">
                    <div class="fl-tools">
                        <div class="fl-tool">
                            <span class="fl-tool__icon"><?= $icon('wrench') ?></span>
                            <div>
                                <h3 class="fl-method__title">Dépannage liaison, dans le jeu</h3>
                                <p class="fl-copy">Touche <kbd>Échap</kbd>, puis <b>Dépannage liaison</b> dans la barre COMSPEC en haut à droite. Il contrôle le mod, les versions, le compte et la liaison, puis propose la correction : <b>Reconnecter Athena</b>, <b>Lier mon compte</b>…</p>
                                <p class="fl-copy fl-muted">Le jeu se fige ? Lancez son pas à pas : il rallume chaque fonction une par une pour trouver la fautive.</p>
                            </div>
                        </div>
                        <div class="fl-tool">
                            <span class="fl-tool__icon"><?= $icon('file') ?></span>
                            <div>
                                <h3 class="fl-method__title">Envoyer mes logs</h3>
                                <p class="fl-copy">Dans le dossier du mod, double-cliquez sur <b translate="no">Envoyer_mes_logs.bat</b>. Un fichier <span translate="no">COMSPEC_logs_&lt;date&gt;.zip</span> apparaît sur le Bureau, mots de passe et jetons masqués. Glissez-le dans le salon bugs ou help du Discord.</p>
                            </div>
                        </div>
                    </div>

                    <p class="fl-sub">Cas fréquents</p>
                    <dl class="fl-faq">
                        <div><dt>Le code a expiré</dt><dd>Onglet COMPTE, <b>NOUVEAU CODE</b>, puis validez-le sans attendre.</dd></div>
                        <div><dt>« Ce code ne peut pas être validé »</dt><dd>Vérifiez que vous êtes connecté au bon compte Athena sur le navigateur, puis recommencez avec un nouveau code.</dd></div>
                        <div><dt>« Mise à jour disponible »</dt><dd>Téléchargez la nouvelle version, remplacez le dossier du mod et relancez Arma.</dd></div>
                        <div><dt>Toujours HORS LIGNE</dt><dd>Vérifiez votre connexion internet, puis <b>Reconnecter Athena</b> dans Dépannage liaison.</dd></div>
                        <div><dt>Connecté mais invisible sur la carte</dt><dd>Ouvrez le canal poste dans l’app Athena et bougez un peu : la position remonte en moins d’une minute.</dd></div>
                    </dl>
                </div>
            </section>

            <footer class="fl-more">
                <span>Aller plus loin :</span>
                <a href="<?= $e($tutoUrl) ?>">Tutoriel joueur</a>
                <a href="<?= $e(url('atak-natif')) ?>">Découvrir l’ATAK natif</a>
                <a href="<?= $e(url('dashboard')) ?>">Tableau de bord</a>
            </footer>
        </div>
    </div>
</div>

<script>
(function () {
  var root = document.querySelector('[data-first-link]');
  if (!root) return;

  // Version du mod : relue sur /api/atak/version (même source que le mod en jeu).
  var versionUrl = root.getAttribute('data-version-url') || '';
  if (versionUrl && window.fetch) {
    fetch(versionUrl, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.ok) return;
        root.querySelectorAll('[data-fl-version]').forEach(function (el) { if (j.atak_native) el.textContent = j.atak_native; });
        root.querySelectorAll('[data-fl-link]').forEach(function (el) { if (j.link) el.textContent = j.link; });
      })
      .catch(function () {});
  }

  // Sommaire : étape visible mise en avant.
  var navLinks = root.querySelectorAll('[data-fl-nav]');
  if ('IntersectionObserver' in window && navLinks.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        navLinks.forEach(function (a) {
          a.classList.toggle('is-current', a.getAttribute('data-fl-nav') === en.target.id);
        });
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    navLinks.forEach(function (a) {
      var t = document.getElementById(a.getAttribute('data-fl-nav'));
      if (t) io.observe(t);
    });
  }

  function copyText(text, button) {
    if (!text) return;
    var label = button ? button.innerHTML : '';
    var done = function () {
      if (!button) return;
      button.textContent = 'Copié';
      setTimeout(function () { button.innerHTML = label; }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {});
      return;
    }
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      done();
    } catch (e) {}
  }

  // Code du portail (onglet CODE PORTAIL du téléphone).
  var gameLinkUrl = root.getAttribute('data-game-link-url') || '';
  var btn = document.getElementById('fl-game-link-btn');
  var resultEl = document.getElementById('fl-game-link-result');
  var codeEl = document.getElementById('fl-game-link-code');
  var metaEl = document.getElementById('fl-game-link-meta');
  var errEl = document.getElementById('fl-game-link-error');
  var copyBtn = document.getElementById('fl-game-link-copy');
  var busy = false;

  function showError(msg) {
    if (!errEl) return;
    errEl.textContent = msg || '';
    errEl.hidden = !msg;
  }

  if (copyBtn && codeEl) {
    copyBtn.addEventListener('click', function () { copyText((codeEl.textContent || '').trim(), copyBtn); });
  }

  if (btn && gameLinkUrl) {
    btn.addEventListener('click', function () {
      if (busy) return;
      busy = true;
      btn.disabled = true;
      btn.textContent = 'Génération…';
      showError('');
      fetch(gameLinkUrl, { method: 'POST', credentials: 'include', headers: { Accept: 'application/json' } })
        .then(function (r) {
          return r.text().then(function (raw) {
            var j = null;
            try { j = raw ? JSON.parse(raw) : null; } catch (e) { j = null; }
            return { ok: r.ok, body: j };
          });
        })
        .then(function (res) {
          busy = false;
          btn.disabled = false;
          if (!res.ok || !res.body || !res.body.code) {
            btn.textContent = 'Générer un code';
            showError((res.body && res.body.message) ? res.body.message : 'Impossible de générer le code pour le moment.');
            return;
          }
          btn.textContent = 'Nouveau code';
          if (codeEl) codeEl.textContent = res.body.code;
          if (metaEl) metaEl.textContent = 'À saisir dans l’onglet CODE PORTAIL. Valable 30 minutes.';
          if (resultEl) resultEl.hidden = false;
        })
        .catch(function () {
          busy = false;
          btn.disabled = false;
          btn.textContent = 'Générer un code';
          showError('Réseau indisponible. Réessayez dans un instant.');
        });
    });
  }

  // Présence vue par le poste.
  var meLinkUrl = root.getAttribute('data-me-link-url') || '';
  var presenceBtn = document.getElementById('fl-presence-btn');
  var presenceEl = document.getElementById('fl-presence-status');
  var presenceBadge = document.getElementById('fl-verifier-badge');
  var presenceBusy = false;
  var presenceLabel = presenceBtn ? presenceBtn.innerHTML : '';

  function setPresence(visible, message) {
    if (!presenceEl) return;
    presenceEl.textContent = message || '';
    presenceEl.classList.remove('is-ok', 'is-warn');
    presenceEl.classList.add(visible ? 'is-ok' : 'is-warn');
    if (presenceBadge) presenceBadge.textContent = visible ? '✓' : '4';
    var step = document.getElementById('fl-verifier');
    if (step) step.classList.toggle('is-done', !!visible);
  }

  if (presenceBtn && meLinkUrl) {
    presenceBtn.addEventListener('click', function () {
      if (presenceBusy) return;
      presenceBusy = true;
      presenceBtn.disabled = true;
      presenceBtn.textContent = 'Vérification…';
      var finish = function () {
        presenceBusy = false;
        presenceBtn.disabled = false;
        presenceBtn.innerHTML = presenceLabel;
      };
      fetch(meLinkUrl, { credentials: 'include', cache: 'no-store', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json().catch(function () { return null; }); })
        .then(function (j) {
          finish();
          if (!j) { setPresence(false, 'Impossible de vérifier pour le moment.'); return; }
          setPresence(!!j.visible, j.message || (j.visible ? 'Visible.' : 'Pas encore visible.'));
        })
        .catch(function () {
          finish();
          setPresence(false, 'Réseau indisponible. Réessayez dans un instant.');
        });
    });
  }
})();
</script>
