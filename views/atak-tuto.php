<?php
declare(strict_types=1);

$baseUrl = rtrim((string) url(''), '/');
$firstLinkUrl = url('atak/premiere-liaison');
$atakUrl = url('atak');
$modUrl = url('atak/mod');
$guideUrl = url('atak/mod/guide');
$accountUrl = url('account/preferences');
$adminConfigUrl = url('admin/atak-config');
$canAdmin = function_exists('can') && (can('admin.system') || can('admin.organization') || can('admin.access'));

$armaHost = trim((string) ($armaServerHost ?? ''));
$armaPort = trim((string) ($armaServerPort ?? ''));
$armaInstructions = trim((string) ($armaServerInstructions ?? ''));
$hasArmaServer = $armaHost !== '';
$armaEndpoint = $hasArmaServer
    ? ($armaPort !== '' ? $armaHost . ':' . $armaPort : $armaHost)
    : '';
?>
<link href="<?= htmlspecialchars(asset_url('assets/css/atak-connexion-tuto.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

<article class="at-cx">
    <header class="at-cx__hero">
        <p class="at-cx__eyebrow">Tutoriel joueur · Overwatch</p>
        <h1 class="at-cx__title">Connexion en jeu</h1>
        <p class="at-cx__lead">
            Relier votre compte Athena à Arma pour apparaître sur la carte partagée.
            Ce guide décrit uniquement le parcours membre — pas la configuration serveur.
        </p>
        <div class="at-cx__hero-actions">
            <a class="at-cx__btn at-cx__btn--primary" href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>">Parcours guidé Première liaison</a>
            <a class="at-cx__btn at-cx__btn--ghost" href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir la carte → Appairer</a>
        </div>
    </header>

    <div class="at-cx__body">

        <aside class="at-cx__callout at-cx__callout--ok">
            <strong>En une phrase :</strong> sur le site vous générez un code <em>Appairer</em> ;
            en jeu vous l’ouvrez via <em>Connexion Athena</em>, vous collez <strong>uniquement ce code</strong>,
            puis vous validez avec <em>Lier</em> (ou <em>Entrer</em> si le compte est déjà reconnu).
        </aside>

        <section class="at-cx__section" id="a-quoi-ca-sert">
            <h2>1. À quoi ça sert</h2>
            <p>
                Sans liaison, vous jouez « orphelin » : le poste ne vous voit pas, votre indicatif Athena
                n’apparaît pas, et le téléphone Athena ne parle pas au TOC.
            </p>
            <p>
                Avec la liaison : position sur la carte, messagerie, ordres et outils liés à votre compte.
                C’est une étape <strong>avant</strong> (ou au début) de l’activité — pas un réglage Zeus.
            </p>
        </section>

        <section class="at-cx__section" id="ne-pas-confondre">
            <h2>2. Quatre codes différents — ne les mélangez pas</h2>
            <div class="at-cx__table-wrap">
                <table class="at-cx__table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Où le prendre</th>
                            <th>Où le coller</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Code Appairer</strong> <span class="at-cx__tag">recommandé</span></td>
                            <td>Carte ATAK → <strong>Appairer</strong> → Générer un code<br><span class="at-cx__muted">(ou Première liaison)</span></td>
                            <td>En jeu → <strong>Connexion Athena</strong> → <strong>Lier le jeu (code Appairer)</strong></td>
                        </tr>
                        <tr>
                            <td><strong>Code « Associer ce terminal »</strong></td>
                            <td>Menu en jeu <em>Associer ce terminal</em></td>
                            <td>Sur le site, fenêtre Appairer → <em>Valider ce terminal</em></td>
                        </tr>
                        <tr>
                            <td><strong>Code liaison téléphone</strong></td>
                            <td>Écran <em>Liaison téléphone</em> / Connecter mon téléphone en jeu</td>
                            <td>Navigateur du téléphone réel (page Athena mobile) — <strong>pas</strong> dans Appairer</td>
                        </tr>
                        <tr>
                            <td><strong>Clé d’accès communauté</strong></td>
                            <td>Générée par un admin</td>
                            <td>Pas pour vous en général : Appairer la transmet tout seul</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="at-cx__warn-inline">
                Ne collez <strong>jamais</strong> l’adresse du site (<code><?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?></code>)
                dans le champ du code Appairer.
            </p>
        </section>

        <section class="at-cx__section" id="parcours">
            <h2>3. Le parcours recommandé (joueurs)</h2>

            <ol class="at-cx__steps">
                <li>
                    <h3>Préparer le compte Athena</h3>
                    <p>
                        Dans <a href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">Mes préférences</a> :
                        renseignez votre <strong>identifiant Steam</strong> et un <strong>nom ou indicatif</strong>.
                        Sans ça, le jeu peut vous rattacher au mauvais profil (ou à rien).
                    </p>
                </li>
                <li>
                    <h3>Installer le pack Overwatch</h3>
                    <p>
                        <a href="<?= htmlspecialchars($modUrl, ENT_QUOTES, 'UTF-8') ?>">Téléchargez le pack</a> de votre communauté.
                        Au lanceur Arma : activez <strong>CBA</strong>, puis <strong>Overwatch</strong> (après CBA).
                        Après chaque mise à jour du pack : quittez Arma <em>complètement</em>, puis relancez.
                    </p>
                    <p class="at-cx__muted">Détail installation : <a href="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>">guide du pack</a>.</p>
                </li>
                <li>
                    <h3>Générer le code sur le site</h3>
                    <p>
                        Ouvrez la <a href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">carte ATAK</a> → bouton
                        <strong>Appairer</strong> (en haut) → <strong>Générer un code</strong> → <strong>Copier</strong>.
                    </p>
                    <ul>
                        <li>Le code est <strong>usage unique</strong> et expire en environ <strong>30 minutes</strong>.</li>
                        <li>Générez-le juste avant de le coller en jeu.</li>
                        <li>Alternative : le bouton dans <a href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>">Première liaison</a>.</li>
                    </ul>
                </li>
                <li>
                    <h3>Ouvrir « Connexion Athena » en jeu</h3>
                    <p>Trois façons équivalentes (prenez celle qui s’affiche chez vous) :</p>
                    <ul>
                        <li>
                            <strong>Menu ACE</strong> (interaction sur soi) →
                            <strong>COMSPEC Athena</strong> → <strong>Connexion Athena</strong>
                        </li>
                        <li>
                            <strong>Téléphone ATAK</strong> → menu d’applications →
                            <strong>Athena</strong> / <strong>Compte Athena</strong> / <strong>Connexion Athena</strong>
                        </li>
                        <li>
                            <strong>Hub Overwatch</strong> → bouton <strong>Connexion Athena</strong>
                        </li>
                    </ul>
                    <p class="at-cx__muted">
                        Raccourcis utiles : messagerie souvent en <kbd>Ctrl</kbd>+<kbd>K</kbd> ;
                        le téléphone peut aussi s’ouvrir via ACE → <em>Ouvrir téléphone ATAK</em>.
                    </p>
                </li>
                <li>
                    <h3>Coller le code et lier</h3>
                    <ol class="at-cx__substeps">
                        <li>Si l’écran propose plusieurs boutons : choisissez <strong>Lier le jeu (code Appairer)</strong>.</li>
                        <li>Collez <strong>uniquement le code</strong> (pas l’URL du site).</li>
                        <li>Validez avec <strong>Lier</strong>.</li>
                        <li>Quand le compte est reconnu : appuyez sur <strong>Entrer</strong> pour ouvrir le canal poste.</li>
                    </ol>
                </li>
                <li>
                    <h3>Contrôler sur la carte</h3>
                    <p>
                        Revenez sur la <a href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">carte ATAK</a>.
                        Votre indicatif doit apparaître sous une minute — bougez un peu en jeu.
                        Sinon : nouveau code, quittez Arma complètement, réessayez.
                    </p>
                </li>
            </ol>

            <p class="at-cx__cta-row">
                <a class="at-cx__btn at-cx__btn--mint" href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>">Faire ça pas à pas (Première liaison)</a>
            </p>
        </section>

        <?php if ($hasArmaServer || $armaInstructions !== ''): ?>
        <section class="at-cx__section" id="serveur-arma">
            <h2>4. Rejoindre la session Arma (si votre communauté l’indique)</h2>
            <p>
                La liaison Athena (code Appairer) n’est <strong>pas</strong> l’adresse du serveur multi-joueur.
                Ce sont deux choses séparées : d’abord vous rejoignez la session Arma, ensuite vous appairerez le compte.
            </p>
            <?php if ($hasArmaServer): ?>
            <div class="at-cx__server">
                <p class="at-cx__server-label">Serveur indiqué par votre communauté</p>
                <p class="at-cx__server-endpoint" id="at-cx-server"><?= htmlspecialchars($armaEndpoint, ENT_QUOTES, 'UTF-8') ?></p>
                <button type="button" class="at-cx__btn at-cx__btn--ghost" data-at-cx-copy="at-cx-server">Copier</button>
            </div>
            <?php endif; ?>
            <?php if ($armaInstructions !== ''): ?>
            <div class="at-cx__callout"><?= nl2br(htmlspecialchars($armaInstructions, ENT_QUOTES, 'UTF-8')) ?></div>
            <?php endif; ?>
            <ul>
                <li>Lanceur Arma → Multijoueur → Distant / Favoris → coller l’hôte (et le port si fourni).</li>
                <li>Activez le même pack Overwatch (+ CBA) que pour la session.</li>
                <li>Une fois en jeu : faites l’étape Appairer ci-dessus.</li>
            </ul>
        </section>
        <?php endif; ?>

        <section class="at-cx__section" id="variantes">
            <h2><?= ($hasArmaServer || $armaInstructions !== '') ? '5' : '4' ?>. Variantes (si votre communauté les autorise)</h2>
            <ul>
                <li>
                    <strong>Steam</strong> — si votre Steam est déjà sur la fiche Athena, le bouton
                    <em>Connexion avec Steam</em> dans Connexion Athena peut suffire (sans code).
                </li>
                <li>
                    <strong>E-mail / mot de passe</strong> — même écran Connexion Athena :
                    <em>Se connecter</em>, ou code temporaire par e-mail.
                </li>
            </ul>
            <p>
                Même après Steam ou e-mail, si le canal poste n’est pas ouvert : bouton <strong>Entrer</strong>.
                En cas de doute, le code Appairer reste le chemin le plus fiable.
            </p>
        </section>

        <section class="at-cx__section" id="depannage">
            <h2><?= ($hasArmaServer || $armaInstructions !== '') ? '6' : '5' ?>. Dépannage rapide</h2>
            <div class="at-cx__table-wrap">
                <table class="at-cx__table">
                    <thead>
                        <tr><th>Symptôme</th><th>Quoi faire</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Code refusé / expiré</td>
                            <td>Générer un <strong>nouveau</strong> code Appairer ; coller tout de suite ; ne pas coller l’URL</td>
                        </tr>
                        <tr>
                            <td>Invisible sur la carte</td>
                            <td>Canal ouvert (Entrer) ; bouger un peu ; attendre ~1 min ; pack à jour</td>
                        </tr>
                        <tr>
                            <td>Mauvais nom / pseudo Steam</td>
                            <td>Vérifier Steam + indicatif dans les préférences, puis nouvel Appairer</td>
                        </tr>
                        <tr>
                            <td>Rien ne s’ouvre en jeu</td>
                            <td>ACE → COMSPEC Athena → Connexion Athena ; ou ouvrir le téléphone ATAK</td>
                        </tr>
                        <tr>
                            <td>Pack qui ne charge pas</td>
                            <td>Overwatch <em>après</em> CBA ; quitter Arma complètement après maj</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <details class="at-cx__admin">
            <summary>Pour l’équipe technique (admins) — clé communauté</summary>
            <p>
                Une clé d’accès communauté active est nécessaire pour que Appairer fonctionne pleinement.
                Les membres n’ont en général <strong>pas</strong> à la coller : le code Appairer la configure.
            </p>
            <ol>
                <li>Configuration ATAK → section Accès mod Overwatch → Générer une clé.</li>
                <li>Publier aussi le pack Overwatch pour les membres.</li>
                <li>Indiquer aux joueurs ce tutoriel ou <a href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>">Première liaison</a>.</li>
            </ol>
            <?php if ($canAdmin): ?>
            <p><a class="at-cx__btn at-cx__btn--ghost" href="<?= htmlspecialchars($adminConfigUrl, ENT_QUOTES, 'UTF-8') ?>">Ouvrir Configuration ATAK</a></p>
            <?php endif; ?>
            <p class="at-cx__muted">Régénérer la clé invalide l’ancienne : les opérateurs déjà liés devront souvent se reconnecter avec un nouveau code.</p>
        </details>

        <footer class="at-cx__footer">
            <a href="<?= htmlspecialchars($firstLinkUrl, ENT_QUOTES, 'UTF-8') ?>">Première liaison</a>
            ·
            <a href="<?= htmlspecialchars($atakUrl, ENT_QUOTES, 'UTF-8') ?>">Carte ATAK</a>
            ·
            <a href="<?= htmlspecialchars($modUrl, ENT_QUOTES, 'UTF-8') ?>">Télécharger le pack</a>
            ·
            <a href="<?= htmlspecialchars($guideUrl, ENT_QUOTES, 'UTF-8') ?>">Guide du pack</a>
            ·
            <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>">Tableau de bord</a>
        </footer>
    </div>
</article>

<script>
(function () {
  var btn = document.querySelector('[data-at-cx-copy]');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-at-cx-copy');
    var el = id ? document.getElementById(id) : null;
    var text = el ? (el.textContent || '').trim() : '';
    if (!text) return;
    var label = btn.textContent;
    var done = function () {
      btn.textContent = 'Copié';
      setTimeout(function () { btn.textContent = label; }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        done();
      });
    }
  });
})();
</script>
