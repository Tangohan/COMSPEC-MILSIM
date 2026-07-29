<section id="chef-mission" class="site-docs__section">
    <h2>Chef de mission &amp; Zeus</h2>
    <p class="site-docs__lead">
        Checklist serveur, modules Eden, zones portail et briefing OP pour piloter Overwatch sur une mission.
    </p>

    <h3>Avant la mission — checklist serveur</h3>
    <ul>
        <li>Mod <strong>@COMSPECOverwatch</strong> côté serveur et clients</li>
        <li><strong>CBA_A3</strong> chargé avant Overwatch</li>
        <li>Bibliothèque native COMSPECExtension à la racine du mod</li>
        <li>Clé communauté Athena valide (paramètres serveur ou briefing)</li>
        <li>Carte / identifiant carte cohérent avec Tacmap</li>
    </ul>

    <h3>Briefing joueurs</h3>
    <p>Indiquez explicitement :</p>
    <ul>
        <li>Adresse du portail Athena de votre communauté</li>
        <li>Indicatif libre ou imposé par groupe</li>
        <li>Terminal requis ou non (tablette / item)</li>
        <li>Niveau de réalisme liaison prévu</li>
    </ul>

    <h3>Modules Eden — catégorie COMSPEC Roleplay</h3>
    <table class="site-docs__table">
        <thead>
            <tr><th>Module</th><th>Effet</th></tr>
        </thead>
        <tbody>
            <tr><td>Zone sans couverture ATAK</td><td>Liaison totalement coupée dans le rayon</td></tr>
            <tr><td>Zone d’interférence</td><td>Pertes de transmission élevées</td></tr>
            <tr><td>Couverture dégradée</td><td>Latence + pertes modérées</td></tr>
            <tr><td>Brouilleur ATAK actif</td><td>Coupures intermittentes + pertes</td></tr>
        </tbody>
    </table>
    <p>Paramètres : rayon (mètres) et intensité (%). Placez les brouilleurs sur des objectifs narratifs (poste radio, convoi ECM) plutôt que sur toute la carte.</p>

    <h3>Zones depuis le portail</h3>
    <p>
        Les administrateurs peuvent définir des zones roleplay sur la carte web.
        Si l’option est activée, le mod synchronise ces zones en plus des modules Eden.
        Testez les chevauchements sur serveur de développement avant l’OP réelle.
    </p>

    <h3>Certificats terminaux</h3>
    <p>
        Si le réalisme certificat est actif, chaque installation de jeu possède une identité terminal stable.
        L’appairage automatique peut être imposé ou désactivé par le staff.
        Les opérateurs voient leur statut (actif, en attente, expiré) dans le hub.
    </p>

    <h3>Pendant l’OP</h3>
    <ul>
        <li>Surveillez Tacmap et le mur opérationnel pour les rapports et alertes medical</li>
        <li>Utilisez les ordres Athena pour pousser des consignes sans spam radio</li>
        <li>Anticipez les zones sans couverture : prévoir relais ou procédure PACE</li>
    </ul>
</section>
