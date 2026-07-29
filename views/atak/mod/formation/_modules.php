<?php
declare(strict_types=1);

/**
 * Contenu détaillé des modules — cases cochées via overwatch-mod-formation.js (localStorage).
 */
?>

<article class="ow-formation__module site-docs__section" id="module-m1" data-module="m1">
    <header class="ow-formation__module-head">
        <span class="ow-formation__module-tag">Module 1 · 25 min · Débutant</span>
        <h2>Installation &amp; première liaison</h2>
    </header>

    <h3>Objectifs pédagogiques</h3>
    <ul>
        <li>Installer le pack sans erreur de chargement</li>
        <li>Lier votre compte Athena de façon durable</li>
        <li>Obtenir le badge « En liaison » en mission</li>
    </ul>

    <h3>Cours — contexte</h3>
    <p>
        Overwatch s’appuie sur une bibliothèque native (COMSPECExtension) et plusieurs addons PBO.
        Si CBA n’est pas chargé en premier, des menus ou des scripts peuvent échouer silencieusement.
        La liaison Athena passe par HTTPS : aucune configuration « mode développeur » n’est requise côté joueur,
        seulement un compte valide et une clé communauté si votre unité en utilise une.
    </p>

    <h3>Exercice guidé</h3>
    <ol class="ow-formation__steps">
        <li class="ow-formation__step" data-step="m1-s1">
            <label>
                <input type="checkbox" data-formation-check="m1-s1" />
                <span>Télécharger le pack depuis <a href="<?= htmlspecialchars((string) ($atakModUrl ?? url('atak/mod')), ENT_QUOTES, 'UTF-8') ?>">la page ATAK mod</a> et noter la version affichée.</span>
            </label>
        </li>
        <li class="ow-formation__step" data-step="m1-s2">
            <label>
                <input type="checkbox" data-formation-check="m1-s2" />
                <span>Extraire dans le dossier mods ; vérifier la présence de la bibliothèque native à la racine du dossier @COMSPECOverwatch.</span>
            </label>
        </li>
        <li class="ow-formation__step" data-step="m1-s3">
            <label>
                <input type="checkbox" data-formation-check="m1-s3" />
                <span>Au lanceur Arma : activer <strong>CBA_A3</strong> puis <strong>@COMSPECOverwatch</strong> (Overwatch sous CBA).</span>
            </label>
        </li>
        <li class="ow-formation__step" data-step="m1-s4">
            <label>
                <input type="checkbox" data-formation-check="m1-s4" />
                <span>Sur le portail : Steam renseigné sur le profil <em>ou</em> code de liaison généré depuis ATAK.</span>
            </label>
        </li>
        <li class="ow-formation__step" data-step="m1-s5">
            <label>
                <input type="checkbox" data-formation-check="m1-s5" />
                <span>Rejoindre le serveur de votre communauté ; attendre ~30 s après spawn.</span>
            </label>
        </li>
        <li class="ow-formation__step" data-step="m1-s6">
            <label>
                <input type="checkbox" data-formation-check="m1-s6" />
                <span>Choisir un indicatif (ex. Alpha-1-1) — éviter les noms vides ou provisoires « test ».</span>
            </label>
        </li>
        <li class="ow-formation__step" data-step="m1-s7">
            <label>
                <input type="checkbox" data-formation-check="m1-s7" />
                <span>Ouvrir le hub (<kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>K</kbd>) : badge <strong>En liaison</strong> visible.</span>
            </label>
        </li>
    </ol>

    <div class="site-docs__callout site-docs__callout--warn">
        <strong>Piège fréquent.</strong> Oublier de redémarrer le lanceur après une mise à jour du pack provoque des versions incohérentes (hub affiche une version, serveur en exige une autre).
    </div>
</article>

<article class="ow-formation__module site-docs__section" id="module-m2" data-module="m2">
    <header class="ow-formation__module-head">
        <span class="ow-formation__module-tag">Module 2 · 30 min · Débutant</span>
        <h2>Hub, messagerie &amp; navigation</h2>
    </header>

    <h3>Objectifs pédagogiques</h3>
    <ul>
        <li>Naviguer dans le hub sans aide</li>
        <li>Utiliser la messagerie tactique</li>
        <li>Consulter ordres et briefing reçus depuis Athena</li>
    </ul>

    <h3>Cours — anatomy du hub</h3>
    <p>
        Le hub regroupe tout ce qui touche à la liaison numérique. L’en-tête indique l’état (lié / hors ligne),
        votre indicatif et la version du pack. Les onglets ou boutons mènent à la messagerie, aux ordres,
        au briefing, aux rapports et aux demandes d’appui. Prenez l’habitude d’y jeter un œil entre deux phases de déplacement,
        comme on consulte une radio numérique.
    </p>

    <h3>Exercice guidé</h3>
    <ol class="ow-formation__steps">
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m2-s1" /><span>Memoriser le raccourci hub ; l’ouvrir les yeux fermés (ou avec un binôme qui chronomètre).</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m2-s2" /><span>Repérer la zone messagerie ; ouvrir avec <kbd>Ctrl</kbd>+<kbd>K</kbd> si disponible.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m2-s3" /><span>Envoyer « TEST LIAISON [indicatif] » à votre référent ou au canal OP.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m2-s4" /><span>Recevoir une réponse depuis le portail (Athena → ordre ou message) et la lire in-game.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m2-s5" /><span>Ouvrir la section briefing ; parcourir au moins une diapositive si votre OP en fournit.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m2-s6" /><span>Consulter la section profil terminal (certificat, réalisme) — noter le statut affiché.</span></label>
        </li>
    </ol>

    <h3>Mise en situation — 5 minutes</h3>
    <p>
        Simulez un changement de consigne : votre chef OP envoie un nouvel ordre depuis Athena pendant que vous êtes en déplacement.
        Objectif : le lire dans le hub en moins de 60 secondes sans immobiliser tout l’élément (défilement rapide, lecture des points clés).
    </p>
</article>

<article class="ow-formation__module site-docs__section" id="module-m3" data-module="m3">
    <header class="ow-formation__module-head">
        <span class="ow-formation__module-tag">Module 3 · 35 min · Intermédiaire</span>
        <h2>Carte, marqueurs &amp; Tacmap</h2>
    </header>

    <h3>Objectifs pédagogiques</h3>
    <ul>
        <li>Poser des marqueurs utiles au TOC</li>
        <li>Comprendre les délais de sync (~30 s handshake, quelques secondes ensuite)</li>
        <li>Utiliser les POI ACE compatibles</li>
    </ul>

    <h3>Cours — doctrine de marquage</h3>
    <p>
        Un marqueur efficace répond à trois questions : <em>quoi</em>, <em>où</em>, <em>pour qui</em>.
        Nommez-le explicitement (« CONTACT MRAP nord pont » plutôt que « marker 3 »).
        Le TOC voit votre indicatif associé au point : un marqueur ambigu force des allers-retours radio.
    </p>

    <h3>Exercice guidé</h3>
    <ol class="ow-formation__steps">
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m3-s1" /><span>Ouvrir Tacmap sur le portail dans un second écran ou onglet (même communauté, même OP).</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m3-s2" /><span>Poser un marqueur carte Arma sur un bâtiment identifiable ; nom explicite.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m3-s3" /><span>Confirmer apparition sur Tacmap (&lt; 1 min après handshake initial).</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m3-s4" /><span>Créer un POI ACE (LZ, renfort ou objectif) ; vérifier remontée web.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m3-s5" /><span>Test négatif : marqueur volontaire en 0,0 — confirmer qu’il n’apparaît pas (comportement attendu).</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m3-s6" /><span>Supprimer ou mettre à jour un marqueur ; observer la mise à jour côté web.</span></label>
        </li>
    </ol>
</article>

<article class="ow-formation__module site-docs__section" id="module-m4" data-module="m4">
    <header class="ow-formation__module-head">
        <span class="ow-formation__module-tag">Module 4 · 40 min · Intermédiaire</span>
        <h2>Rapports tactiques &amp; demandes</h2>
    </header>

    <h3>Objectifs pédagogiques</h3>
    <ul>
        <li>Maîtriser SPOTREP et CONTACT</li>
        <li>Formuler une demande MEDEVAC structurée</li>
        <li>Adapter son comportement si liaison dégradée</li>
    </ul>

    <h3>Cours — structure SALUTE (rappel)</h3>
    <table class="site-docs__table">
        <thead><tr><th>Lettre</th><th>Signification</th><th>Exemple</th></tr></thead>
        <tbody>
            <tr><td>S</td><td>Size (effectif)</td><td>« 6 dismounts »</td></tr>
            <tr><td>A</td><td>Activity</td><td>« en patrouille est-ouest »</td></tr>
            <tr><td>L</td><td>Location</td><td>« grille 045 112, bordure forêt »</td></tr>
            <tr><td>U</td><td>Uniform</td><td>« woodland, gilets tan »</td></tr>
            <tr><td>T</td><td>Time</td><td>« observé 14:32Z »</td></tr>
            <tr><td>E</td><td>Equipment</td><td>« 1 MRAP sans tourelle visible »</td></tr>
        </tbody>
    </table>

    <h3>Exercice guidé</h3>
    <ol class="ow-formation__steps">
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m4-s1" /><span>Envoyer un SPOTREP sur un véhicule statique (prop OP ou véhicule entraînement).</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m4-s2" /><span>Envoyer un CONTACT avec effectif estimé et activité.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m4-s3" /><span>Vérifier réception côté portail (mur opérationnel ou module rapports).</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m4-s4" /><span>Remplir une demande MEDEVAC : position, nombre de blessés, sécurité LZ, indicatif.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m4-s5" /><span>Chronométrer : rapport complet en moins de 90 secondes (exercice répété 3 fois).</span></label>
        </li>
    </ol>

    <h3>Mise en situation — liaison dégradée</h3>
    <p>
        Si votre serveur d’entraînement active le réalisme : provoquez un écran endommagé (scenario Zeus ou blessure simulée).
        Tentez un rapport complet → observez l’échec ou la partialité. Envoyez alors au moins la position via le flux dégradé,
        puis complétez par radio vocale. C’est la procédure PACE appliquée au numérique.
    </p>
    <ol class="ow-formation__steps">
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m4-s6" /><span>Exercice liaison dégradée réalisé ; procédure de secours radio identifiée.</span></label>
        </li>
    </ol>
</article>

<article class="ow-formation__module site-docs__section" id="module-m5" data-module="m5">
    <header class="ow-formation__module-head">
        <span class="ow-formation__module-tag">Module 5 · 45 min · Avancé</span>
        <h2>Réalisme liaison (1.3.0)</h2>
    </header>

    <h3>Objectifs pédagogiques</h3>
    <ul>
        <li>Reconnaître chaque overlay et sa cause</li>
        <li>Sortir d’une zone sans couverture en connaissance de cause</li>
        <li>Rallumer / réparer le terminal via ACE</li>
        <li>Comprendre la reprise post-crash (~10 min)</li>
    </ul>

    <h3>Cours — ordre de priorité des blocages</h3>
    <p>
        Le mod teste dans l’ordre : destruction → gel → coupure réseau → zone → ATAK éteint → écran cassé → OK.
        Un overlay « Liaison perdue » n’a pas la même cause qu’un écran noir : la réaction tactique diffère
        (attendre reconnexion vs. sortir trousse de réparation).
    </p>

    <h3>Exercice guidé — tableaux de reconnaissance</h3>
    <table class="site-docs__table">
        <thead><tr><th>Overlay / état</th><th>Action opérateur</th></tr></thead>
        <tbody>
            <tr><td>Liaison perdue (compte à rebours)</td><td>Se mettre à couvert, attendre ou déplacer selon OP</td></tr>
            <tr><td>Bandeau zone</td><td>Informer le chef d’élément, noter l’entrée/sortie de zone</td></tr>
            <tr><td>Pertes % élevées</td><td>Reformuler messages courts ; éviter photos lourdes</td></tr>
            <tr><td>Écran endommagé</td><td>Position seule — radio pour le détail ; réparer si sécurisé</td></tr>
            <tr><td>ATAK éteint</td><td>ACE → Rallumer l’ATAK</td></tr>
        </tbody>
    </table>

    <ol class="ow-formation__steps">
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m5-s1" /><span>Observer une zone sans couverture (Eden ou portail) ; noter l’alerte à l’entrée.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m5-s2" /><span>Sortir de la zone ; confirmer retour badge En liaison.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m5-s3" /><span>Simuler extinction ATAK ; rallumer via ACE.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m5-s4" /><span>Simuler écran endommagé ; tenter réparation (trousse) ou signaler impossibilité.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m5-s5" /><span>Test reprise : déconnexion brutale puis reconnect &lt; 10 min — indicatif restauré.</span></label>
        </li>
    </ol>
</article>

<article class="ow-formation__module site-docs__section" id="module-m6" data-module="m6">
    <header class="ow-formation__module-head">
        <span class="ow-formation__module-tag">Module 6 · 50 min · Encadrement</span>
        <h2>Chef de mission &amp; zones Zeus</h2>
    </header>

    <h3>Public visé</h3>
    <p>OP, Zeus, admins liaison — ce module complète le guide chef de mission du site.</p>

    <h3>Checklist pré-OP (à imprimer ou copier)</h3>
    <ol class="ow-formation__steps">
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m6-s1" /><span>Mods serveur = mods clients (Overwatch + CBA, version minimale annoncée).</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m6-s2" /><span>Bibliothèque native présente sur le serveur dédié.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m6-s3" /><span>Clé communauté et carte Tacmap alignées.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m6-s4" /><span>Briefing joueurs : portail, indicatif, terminal requis, niveau réalisme.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m6-s5" /><span>Modules Eden roleplay placés et testés (rayon, intensité).</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m6-s6" /><span>Zones portail synchronisées si l’option est activée pour la communauté.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m6-s7" /><span>Parcours dry-run : un opérateur test liaison + marqueur + rapport avant ouverture serveur public.</span></label>
        </li>
    </ol>

    <h3>Conception OP — bonnes pratiques</h3>
    <ul>
        <li>Placer les brouilleurs sur des objectifs narratifs, pas sur tout le AO.</li>
        <li>Prévoir une procédure PACE quand le numérique est coupé (radio, runner, pyro).</li>
        <li>Anticiper les certificats expirés si le réalisme certificat est actif.</li>
    </ul>
</article>

<article class="ow-formation__module site-docs__section" id="module-m7" data-module="m7">
    <header class="ow-formation__module-head">
        <span class="ow-formation__module-tag">Module 7 · 25 min · Opérateur</span>
        <h2>Renseignement interpersonnel (SSE 1.4.0)</h2>
    </header>

    <h3>Contexte</h3>
    <p>
        Le terminal de renseignement interpersonnel permet d’enregistrer une personne contrôlée sur le terrain
        (identité, photo du visage, armement) et de transmettre la fiche au poste de commandement Athena.
        Les fiches sont des identités de scénario — pas le dossier RH des membres de votre communauté.
    </p>

    <h3>Ce qui est disponible en 1.4.0</h3>
    <ul>
        <li>Terminal « Renseignement interpersonnel » (menu ACE ATAK Tactique)</li>
        <li>Photo du visage jointe à la fiche</li>
        <li>Simulation d’empreintes (gameplay)</li>
        <li>Onglet <strong>Personnes</strong> sur la carte tactique Athena</li>
        <li>Préremplissage inventaire / statut (armes, détenu ACE)</li>
        <li>Portail classifié <strong>/atak/sse</strong> : dossiers d’affaire, codes d’accès, croisements, export PDF</li>
    </ul>

    <h3>Exercice pratique</h3>
    <ol class="ow-formation__steps">
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m7-s1" /><span>Ouvrir ACE → ATAK Tactique → Enregistrer une personne sur une cible proche.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m7-s2" /><span>Remplir nom ou alias, statut et circonstances ; activer Photo du visage après une capture.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m7-s3" /><span>Vérifier sur Athena (onglet Personnes) que la fiche et la photo apparaissent.</span></label>
        </li>
        <li class="ow-formation__step">
            <label><input type="checkbox" data-formation-check="m7-s4" /><span>Ouvrir le portail classifié (lien depuis l’onglet Personnes) avec un code délivré par le commandement ; rattacher la fiche à un dossier.</span></label>
        </li>
    </ol>

    <div class="site-docs__callout site-docs__callout--info">
        <strong>Portail classifié.</strong> Le commandement délivre des codes temporaires (membres ou invités).
        Guide : section « Renseignement interpersonnel » — lien « Portail de renseignement interpersonnel ».
    </div>
</article>
