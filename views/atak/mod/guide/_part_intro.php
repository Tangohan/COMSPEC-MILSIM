<section id="introduction" class="site-docs__section">
    <h2>Introduction</h2>
    <p class="site-docs__lead">
        COMSPEC Overwatch est le mod Arma qui relie votre terminal tactique au portail Athena de votre communauté.
        Positions, marqueurs, rapports, photos de reconnaissance et état medical remontent en temps réel vers le poste de commandement ;
        en retour, ordres, alertes et briefings peuvent vous parvenir en mission.
    </p>

    <h3>À quoi sert le mod ?</h3>
    <p>
        Le mod simule un <strong>terminal ATAK</strong> utilisable en jeu : un hub central, une messagerie, des rapports tactiques
        et une liaison avec la carte web <strong>Tacmap</strong>. Il ne remplace pas votre radio vocale ni votre doctrine interne ;
        il complète la coordination numérique lorsque votre unité l’a activée.
    </p>
    <p>
        Deux publics utilisent ce guide. Les <strong>opérateurs</strong> y trouvent les gestes du quotidien (liaison, hub, rapports).
        Les <strong>chefs de mission</strong> y trouvent aussi la configuration Eden/Zeus, le réalisme liaison et les bonnes pratiques OP.
    </p>

    <h3>Prérequis</h3>
    <ul>
        <li>Arma 3 avec le pack <strong>@COMSPECOverwatch</strong> activé (version <?= htmlspecialchars((string) ($owPackVersion ?? '1.3.0'), ENT_QUOTES, 'UTF-8') ?> ou ultérieure).</li>
        <li><strong>CBA_A3</strong> chargé avant Overwatch.</li>
        <li>Un compte Athena sur le portail de votre communauté, avec Steam renseigné ou code de liaison généré depuis ATAK.</li>
        <li>Pour le serveur : la bibliothèque native <strong>COMSPECExtension</strong> présente à la racine du mod.</li>
    </ul>

    <div class="site-docs__callout site-docs__callout--tip">
        <strong>Conseil.</strong> Si vous découvrez le mod, commencez par le
        <a href="<?= htmlspecialchars((string) ($atakFormationUrl ?? url('atak/mod/formation')), ENT_QUOTES, 'UTF-8') ?>">parcours de formation</a>
        : sept modules progressifs avec cases à cocher pour valider chaque étape.
    </div>
</section>

<section id="premiere-mission" class="site-docs__section">
    <h2>Première mission</h2>
    <p class="site-docs__lead">
        Une fois le pack installé et votre compte lié, voici le déroulé type des premières minutes en OP.
    </p>

    <h3>En cinq étapes</h3>
    <ol>
        <li><strong>Spawn</strong> — le mod tente la liaison automatiquement (~30 secondes après apparition).</li>
        <li><strong>Indicatif</strong> — choisissez ou confirmez votre indicatif ; il apparaît sur Tacmap et dans les listes du poste de commandement.</li>
        <li><strong>Hub</strong> — ouvrez le centre Overwatch (<kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>K</kbd>) et vérifiez le badge « En liaison ».</li>
        <li><strong>Carte</strong> — placez un marqueur test ; contrôlez qu’il remonte côté web si la synchronisation est activée.</li>
        <li><strong>Rapport</strong> — envoyez un SPOTREP ou CONTACT d’essai pour valider la chaîne complète.</li>
    </ol>

    <div class="site-docs__callout site-docs__callout--info">
        <strong>Terminal requis.</strong> Certaines communautés imposent un item ou une tablette pour utiliser Overwatch.
        Si le hub reste inaccessible, demandez à votre encadrement si cette règle s’applique à votre mission.
    </div>
</section>
