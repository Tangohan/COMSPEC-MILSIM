<section id="installation" class="site-docs__section">
    <h2>Installation &amp; liaison</h2>
    <p class="site-docs__lead">
        Téléchargez le pack, installez-le côté Arma, puis appairer votre compte Athena avant la première activité.
        Le parcours guidé du portail&nbsp;:
        <a href="<?= htmlspecialchars(url('atak/premiere-liaison'), ENT_QUOTES, 'UTF-8') ?>">Première liaison</a>.
    </p>

    <h3>Téléchargement</h3>
    <p>
        Rendez-vous sur la page <strong>Télécharger le pack Overwatch</strong> depuis le menu ATAK.
        Le fichier contient les addons, la bibliothèque de liaison et les textures du terminal.
        Notez la version affichée : votre communauté peut exiger une version minimale pour rejoindre le serveur.
    </p>

    <h3>Installation côté Arma</h3>
    <ol>
        <li>Extrayez le pack dans votre dossier de mods Arma (même emplacement que vos autres @mods).</li>
        <li>Au lanceur, activez <strong>CBA_A3</strong> puis <strong>@COMSPECOverwatch</strong> (Overwatch doit être après CBA).</li>
        <li>Vérifiez que la bibliothèque native est bien présente à la racine du dossier du mod (fournie avec le pack).</li>
        <li>Quittez Arma complètement après toute mise à jour du pack, puis relancez.</li>
    </ol>

    <h3>Appairer votre compte Athena</h3>
    <p>Chemin recommandé :</p>
    <ol>
        <li>Sur le portail, ouvrez la carte ATAK → <strong>Appairer</strong> → <strong>Générer un code</strong> (ou le parcours <a href="<?= htmlspecialchars(url('atak/premiere-liaison'), ENT_QUOTES, 'UTF-8') ?>">Première liaison</a>).</li>
        <li>
            En jeu, ouvrez <strong>Connexion Athena</strong> :
            ACE → <strong>COMSPEC Athena</strong> → <strong>Connexion Athena</strong>,
            ou téléphone ATAK → Athena / Compte Athena,
            ou hub Overwatch → Connexion Athena.
        </li>
        <li>Choisissez <strong>Lier le jeu (code Appairer)</strong> si proposé, collez <strong>uniquement le code</strong> (pas l’URL du site) → Lier.</li>
        <li>Si le compte est déjà reconnu : bouton <strong>Entrer</strong> pour ouvrir le canal poste.</li>
    </ol>
    <p>Variantes utiles :</p>
    <ul>
        <li><strong>Steam</strong> déjà renseigné sur votre fiche — bouton <em>Connexion avec Steam</em>.</li>
        <li><strong>E-mail / mot de passe</strong> — même écran Connexion Athena.</li>
    </ul>
    <p>
        La <strong>clé d’accès communauté</strong> est générée par un administrateur.
        Avec Appairer, elle est transmise automatiquement : vous n’avez en général pas à la coller.
        Réglage avancé si besoin : Paramètres Athena → <strong>Liaison au poste</strong>.
        Tutoriel joueur complet (codes à ne pas confondre) :
        <a href="<?= htmlspecialchars(url('atak/tuto'), ENT_QUOTES, 'UTF-8') ?>">Connexion en jeu</a>.
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
