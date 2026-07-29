<section id="hub-overwatch" class="site-docs__section">
    <h2>Hub Overwatch</h2>
    <p class="site-docs__lead">
        Le hub est le centre de contrôle du terminal : liaison, messagerie, ordres, rapports et état du matériel.
    </p>

    <h3>Ouvrir le hub</h3>
    <p>
        Raccourci par défaut : <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>K</kbd>.
        Un menu ACE « ATAK Tactique » peut aussi être disponible selon la configuration de votre communauté.
    </p>

    <h3>Zones du hub</h3>
    <table class="site-docs__table">
        <thead>
            <tr><th>Zone</th><th>Usage</th></tr>
        </thead>
        <tbody>
            <tr><td>En-tête liaison</td><td>État En liaison / Hors liaison, version du pack, indicatif</td></tr>
            <tr><td>Messagerie</td><td>Échanges rapides avec le poste de commandement (<kbd>Ctrl</kbd>+<kbd>K</kbd>)</td></tr>
            <tr><td>Ordres</td><td>Consignes reçues depuis Athena</td></tr>
            <tr><td>Briefing</td><td>Diapositives et documents de mission</td></tr>
            <tr><td>Rapports</td><td>CONTACT, SALUTE, SPOTREP, SITREP…</td></tr>
            <tr><td>Demandes</td><td>Appui aérien, MEDEVAC, renfort</td></tr>
            <tr><td>Profil terminal</td><td>Certificat, réalisme, état matériel</td></tr>
        </tbody>
    </table>

    <h3>Overlays réalisme (1.3.0)</h3>
    <p>
        Si votre communauté active le roleplay liaison, le hub affiche des bandeaux visuels :
        liaison perdue, zone sans couverture, pertes de transmission, écran endommagé ou terminal éteint.
        Ces messages sont volontairement explicites pour que vous sachiez <em>pourquoi</em> une action est bloquée.
    </p>

    <div class="site-docs__callout site-docs__callout--tip">
        <strong>En combat.</strong> Mémorisez le raccourci hub avant l’OP : ouvrir le terminal sous pression
        doit devenir un réflexe, comme sortir une radio.
    </div>
</section>

<section id="rapports-tactiques" class="site-docs__section">
    <h2>Rapports tactiques</h2>
    <p class="site-docs__lead">
        Les rapports transmettent une situation structurée au poste de commandement sans quitter Arma.
    </p>

    <h3>Types de rapports</h3>
    <table class="site-docs__table">
        <thead>
            <tr><th>Type</th><th>Quand l’utiliser</th></tr>
        </thead>
        <tbody>
            <tr><td>CONTACT</td><td>Contact ennemi — position, effectif estimé, activité</td></tr>
            <tr><td>SALUTE</td><td>Renseignement détaillé (taille, activité, lieu, uniforme, temps, équipement)</td></tr>
            <tr><td>SPOTREP</td><td>Observation ponctuelle (véhicule, mouvement, installation)</td></tr>
            <tr><td>SITREP</td><td>Situation globale de votre élément</td></tr>
        </tbody>
    </table>

    <h3>Bonnes pratiques</h3>
    <ul>
        <li>Rédigez en <strong>français clair</strong> — évitez les abréviations non standardisées par votre unité.</li>
        <li>Indiquez une <strong>grille ou repère</strong> lisible par le TOC (coordonnées, landmark, numéro de secteur).</li>
        <li>En réalisme liaison actif, un écran endommagé peut bloquer l’envoi complet : seule la position remonte alors.</li>
        <li>Vérifiez le badge liaison avant un rapport urgent.</li>
    </ul>
</section>

<section id="photos-renseignement" class="site-docs__section">
    <h2>Photos &amp; renseignement</h2>
    <p class="site-docs__lead">
        Certaines vues permettent d’envoyer des images de reconnaissance avec position et cap vers le portail.
    </p>

    <h3>Photo Library (cTab / BCE)</h3>
    <p>
        Si votre modpack inclut cTab ou BCE, les photos prises peuvent remonter dans le panneau
        <strong>Cams</strong> du portail. L’encadrement visualise alors ce que vous avez capturé, avec la position au moment du cliché.
    </p>

    <h3>Capture Athena</h3>
    <p>
        Des vues dédiées du terminal permettent parfois d’envoyer directement une image de reconnaissance.
        Utilisez-les pour documenter un objectif, un véhicule abandonné ou un point de passage — pas pour des clichés hors contexte OP.
    </p>

    <div class="site-docs__callout site-docs__callout--info">
        <strong>Classification.</strong> Respectez la politique de votre unité sur les images sensibles :
        visages, coordonnées exactes de bases amies, ou matériel classifié selon votre scénario.
    </div>
</section>

<section id="medical-alertes" class="site-docs__section">
    <h2>Medical &amp; alertes</h2>
    <p class="site-docs__lead">
        Avec ACE Medical (et KAT si présent), l’état de santé peut alimenter des alertes sanitaires sur le portail.
    </p>

    <h3>Ce qui remonte automatiquement</h3>
    <ul>
        <li>Blessures significatives (torse, membres)</li>
        <li>État de conscience et incapacité au combat</li>
        <li>Avec KAT : saturation, voies aériennes, complications thoraciques</li>
    </ul>
    <p>
        Vous n’avez rien à configurer : le mod lit l’état medical local et l’associe à votre indicatif.
        Le poste de commandement voit les alertes sur le tableau opérationnel ou Tacmap selon les droits.
    </p>

    <h3>Lien avec le réalisme terminal</h3>
    <p>
        Une blessure au torse ou au bras peut endommager le terminal (extinction, écran cassé).
        Une SpO2 basse peut fausser les capteurs affichés côté TOC. Consultez le chapitre
        <a href="#realisme-liaison">Réalisme liaison</a> pour le détail des effets.
    </p>
</section>
