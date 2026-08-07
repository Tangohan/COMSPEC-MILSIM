<section id="sse-personnes" class="site-docs__section">
    <h2>Renseignement interpersonnel</h2>
    <p class="site-docs__lead">
        Enregistrez une personne contrôlée sur le terrain : identité, photo du visage, armement trouvé.
        La fiche remonte au poste de commandement Athena (onglet <strong>Personnes</strong>).
    </p>

    <h3>Ouvrir le terminal</h3>
    <ol>
        <li>Approchez la personne (joueur, IA ou otage scénarisé).</li>
        <li>Menu ACE Interact → <strong>COMSPEC</strong> → <strong>ATAK Tactique</strong> → <strong>Enregistrer une personne</strong>.</li>
        <li>Renseignez nom, prénom ou alias, le statut (civil, combattant, détenu, personne prioritaire) et les circonstances.</li>
    </ol>

    <h3>Photo du visage</h3>
    <p>
        Avant d’enregistrer, faites une capture d’écran face à la personne (ou utilisez la Photothèque),
        puis activez <strong>Photo du visage</strong> dans le terminal. La dernière capture récente est jointe à la fiche.
    </p>

    <h3>Empreintes (simulation)</h3>
    <p>
        Le bouton <strong>Empreintes (simulation)</strong> enregistre un événement de biométrie simulée pour le gameplay.
        Aucune lecture réelle d’empreinte ou d’iris n’est effectuée.
    </p>

    <h3>Côté poste de commandement</h3>
    <ul>
        <li>Ouvrez la carte tactique Athena.</li>
        <li>Onglet latéral <strong>Personnes</strong> : liste des fiches, filtre par statut, aperçu photo.</li>
        <li>Les fiches sont des <strong>identités de scénario</strong> — elles ne sont pas fusionnées avec les dossiers RH des membres.</li>
    </ul>

    <h3>Portail classifié</h3>
    <p>
        Pour les dossiers d’affaire, notes, preuves, croisements avec les listes de surveillance et l’export PDF,
        ouvrez le <a href="<?= htmlspecialchars(url('atak/sse'), ENT_QUOTES, 'UTF-8') ?>"><strong>portail de renseignement interpersonnel</strong></a>.
        L’accès exige un code temporaire délivré par le commandement (membres habilités ou invités).
        Bandeau permanent « Diffusion restreinte » — consultation tracée.
    </p>
    <p>
        Une fois dans le bureau, le <strong>manuel opérateur complet</strong> est disponible sous
        <em>Aide → Documentation</em> (ou directement après habilitation).
        Il couvre dossiers d’intérêt, dossiers validés, exploitation numérique, rédaction et diffusion.
    </p>

    <div class="site-docs__callout site-docs__callout--info">
        <strong>Module communauté.</strong> L’administrateur peut activer ou désactiver le renseignement interpersonnel
        dans la configuration ATAK (modules ATAK Enhanced / cTab).
    </div>

    <h3>Exploitation de sites</h3>
    <p>
        Les sites exploités depuis le terrain (checklist de fouille, saisies, clôture) apparaissent dans le portail
        sous <strong>Sites</strong> et peuvent être rattachés au dossier actif.
    </p>
</section>
