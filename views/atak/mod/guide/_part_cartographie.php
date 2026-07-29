<section id="carte-marqueurs" class="site-docs__section">
    <h2>Carte &amp; marqueurs</h2>
    <p class="site-docs__lead">
        Les marqueurs posés en jeu peuvent apparaître sur Tacmap et enrichir la conscience situationnelle du poste de commandement.
    </p>

    <h3>Synchronisation</h3>
    <p>
        Lorsque la synchronisation est activée pour votre communauté :
    </p>
    <ul>
        <li>Les marqueurs de la <strong>carte Arma</strong> remontent vers Tacmap (délai quelques secondes).</li>
        <li>Les points d’intérêt créés via <strong>ACE</strong> (LZ, renfort, objectif…) apparaissent aussi côté web.</li>
        <li>Attendez la fin du handshake (~30 s après spawn) avant de tester.</li>
    </ul>

    <h3>Erreurs à éviter</h3>
    <ul>
        <li>Ne placez pas de marqueurs sur l’<strong>origine carte (0,0)</strong> — ils sont ignorés par la liaison.</li>
        <li>Un indicatif vide empêche l’association correcte de vos points.</li>
        <li>En zone sans couverture (réalisme), les marqueurs ne partent pas tant que la liaison est coupée.</li>
    </ul>

    <h3>Consignes OP</h3>
    <p>
        Votre chef de mission peut imposer une doctrine de marquage (couleurs, symboles, nommage).
        Le mod transmet ce que vous posez ; la lisibilité pour le TOC reste votre responsabilité tactique.
    </p>
    <div class="site-docs__callout site-docs__callout--tip">
        <strong>Légende visuelle.</strong>
        Consultez la
        <a href="<?= htmlspecialchars(url('documentation/marqueurs'), ENT_QUOTES, 'UTF-8') ?>">bibliothèque de marqueurs</a>
        du portail pour les aperçus et libellés des symboles amis, adverses, points d’intérêt et repères tactiques.
    </div>
</section>
