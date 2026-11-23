<section id="installation" class="site-docs__section">
    <h2>Installation &amp; liaison</h2>
    <p class="site-docs__lead">
        Téléchargez le pack, installez-le côté Arma, puis appairer votre compte Athena avant la première activité.
        Le parcours guidé du portail&nbsp;:
        <a href="<?= htmlspecialchars(url('atak/premiere-liaison'), ENT_QUOTES, 'UTF-8') ?>">Première liaison</a>.
    </p>

    <h3>Télécharger le pack</h3>
    <p>
        Le pack COMSPEC se télécharge sur la page publique
        <a href="<?= htmlspecialchars(url('modpacks'), ENT_QUOTES, 'UTF-8') ?>"><strong>des modpacks</strong></a>
        (empreinte SHA-256 et explication « signalé par l’antivirus » comprises). La page
        <a href="<?= htmlspecialchars(url('atak/mod'), ENT_QUOTES, 'UTF-8') ?>">Installer le mod Athena</a>
        résume les étapes. Steam ne met pas à jour un pack ajouté à la main : revenez sur la page à chaque nouvelle version.
        Votre communauté peut exiger une version minimale pour rejoindre le serveur.
    </p>

    <h3>Installation côté Arma</h3>
    <ol>
        <li>Décompressez l’archive .RAR (7-Zip ou WinRAR) dans un dossier dédié.</li>
        <li>Au lanceur d’Arma 3, onglet Mods, ajoutez chaque dossier extrait avec « Mod local », puis activez-les ainsi que <strong>CBA_A3</strong> (à abonner sur le Steam Workshop).</li>
        <li>La bibliothèque de liaison (COMSPEC Link) est fournie avec le mod : rien à copier à la main.</li>
        <li>Quittez Arma complètement après toute mise à jour du pack, puis relancez.</li>
    </ol>
    <h3>Appairer votre compte Athena</h3>
    <p>Chemin recommandé :</p>
    <ol>
        <li>Sur le portail, ouvrez la carte ATAK → <strong>Appairer</strong> → <strong>Générer un code</strong> (ou le parcours <a href="<?= htmlspecialchars(url('atak/premiere-liaison'), ENT_QUOTES, 'UTF-8') ?>">Première liaison</a>).</li>
        <li>En jeu : téléphone → application <strong>Athena</strong> → coller <strong>uniquement le code</strong> (pas l’adresse du site) → Lier.</li>
        <li>Si le compte est déjà reconnu : bouton <strong>Entrer</strong> pour rouvrir le canal poste.</li>
    </ol>
    <p>Variantes utiles :</p>
    <ul>
        <li><strong>Steam</strong> déjà renseigné sur votre fiche — bouton Steam dans Athena.</li>
        <li><strong>E-mail / mot de passe</strong> — même panneau Athena.</li>
    </ul>
    <p>
        La <strong>clé d’accès communauté</strong> est générée par un administrateur.
        Avec Appairer, elle est transmise automatiquement : vous n’avez en général pas à la coller.
        Réglage avancé si besoin : Paramètres Athena → <strong>Liaison au poste</strong>.
        Détails : <a href="<?= htmlspecialchars(url('atak/tuto'), ENT_QUOTES, 'UTF-8') ?>">guide connexion</a>.
    </p>

    <h3>Vérifier que tout fonctionne</h3>
    <table class="site-docs__table">
        <thead>
            <tr><th>Indicateur</th><th>Attendu</th></tr>
        </thead>
        <tbody>
            <tr><td>Athena / canal poste</td><td>Compte prêt, puis présence sur la carte</td></tr>
            <tr><td>Indicatif</td><td>Visible sur le téléphone et sur la carte du poste</td></tr>
            <tr><td>Version pack</td><td>Correspond à celle publiée sur le portail</td></tr>
            <tr><td>Position carte</td><td>Point mis à jour sous une minute en mission (bougez un peu)</td></tr>
        </tbody>
    </table>

    <div class="site-docs__callout site-docs__callout--warn">
        <strong>Attention.</strong> Si la liaison reste impossible : nouveau code Appairer, quitter Arma complètement,
        vérifier Steam sur le compte, et que votre poste autorise les connexions sortantes vers le portail (HTTPS).
    </div>
</section>
