<section id="realisme-liaison" class="site-docs__section">
    <h2>Réalisme liaison (1.3.0)</h2>
    <p class="site-docs__lead">
        Votre staff peut simuler un environnement radio dégradé : couverture inégale, matériel endommagé, reprise après crash.
        Tout est configurable par communauté sur le portail.
    </p>

    <h3>Pipeline « peut-on transmettre ? »</h3>
    <p>Avant chaque envoi vers Athena, le mod évalue dans l’ordre :</p>
    <ol>
        <li>Appareil détruit → plus aucune transmission</li>
        <li>Terminal gelé (blocage temporaire) → plus de transmission</li>
        <li>Coupure réseau simulée → plus de transmission</li>
        <li>Zone sans couverture → plus de transmission</li>
        <li>ATAK éteint → plus de transmission</li>
        <li>Écran endommagé → <strong>position uniquement</strong></li>
        <li>Sinon → liaison normale</li>
    </ol>
    <p>Le poste de commandement reçoit l’état liaison avec chaque position (lié, dégradé, hors ligne).</p>

    <h3>Ce que vous observez in-game</h3>
    <table class="site-docs__table">
        <thead>
            <tr><th>Situation</th><th>Effet visible</th></tr>
        </thead>
        <tbody>
            <tr><td>Coupure réseau</td><td>Overlay « Liaison perdue », compte à rebours reconnexion</td></tr>
            <tr><td>Zone sans couverture</td><td>Bandeau zone + alerte à l’entrée</td></tr>
            <tr><td>Brouillage / interférence</td><td>Pertes affichées, coupures intermittentes</td></tr>
            <tr><td>Écran endommagé</td><td>Message plein écran — position seule encore envoyée</td></tr>
            <tr><td>ATAK éteint</td><td>Écran noir — rallumage via ACE</td></tr>
            <tr><td>Terminal bloqué</td><td>Gel temporaire (distinct d’une coupure réseau)</td></tr>
        </tbody>
    </table>

    <h3>Dommages terminal</h3>
    <table class="site-docs__table">
        <thead>
            <tr><th>Niveau réalisme</th><th>Effet opérateur</th></tr>
        </thead>
        <tbody>
            <tr><td>1</td><td>Extinction temporaire (~30 s), auto-rallumage possible</td></tr>
            <tr><td>2</td><td>Écran inutilisable — GPS / position encore transmis</td></tr>
            <tr><td>3</td><td>Appareil inutilisable — fin de liaison Athena</td></tr>
        </tbody>
    </table>
    <p>Déclencheurs courants (1.3.0+) : blessure torse, bras très blessé, choc (explosion, impact), complications thoraciques KAT, SpO2 basse.</p>

    <h3>Réparer ou rallumer</h3>
    <p>Via le menu ACE sur vous-même :</p>
    <ul>
        <li><strong>Rallumer l’ATAK</strong> — lève extinction ou gel</li>
        <li><strong>Réparer l’écran</strong> — trousse requise, animation de réparation</li>
    </ul>

    <h3>Zones roleplay</h3>
    <table class="site-docs__table">
        <thead>
            <tr><th>Type de zone</th><th>Effet</th></tr>
        </thead>
        <tbody>
            <tr><td>Sans couverture</td><td>Coupure totale</td></tr>
            <tr><td>Interférence</td><td>Pertes élevées</td></tr>
            <tr><td>Dégradé</td><td>Latence + pertes légères</td></tr>
            <tr><td>Brouilleur</td><td>Coupures aléatoires + pertes</td></tr>
        </tbody>
    </table>
    <p>Sources : modules Eden/Zeus et/ou zones définies sur le portail (synchronisation ~90 s).</p>

    <h3>Crash et reprise de session</h3>
    <ul>
        <li><strong>Quit propre</strong> — le mod signale la fin de session.</li>
        <li><strong>Crash ou coupure brutale</strong> — le portail conserve une fenêtre de reprise (~10 min) : indicatif et état terminal restaurés au reconnect (JIP).</li>
    </ul>
</section>
