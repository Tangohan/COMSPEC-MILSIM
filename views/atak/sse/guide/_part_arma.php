<?php declare(strict_types=1); ?>
<section id="terrain-arma" class="site-docs__section">
    <h2>Terrain &amp; Arma</h2>
    <p class="site-docs__lead">
        Sur le terrain simulé, le pack Overwatch et les modules SSE permettent d’enregistrer des personnes,
        des photos et, selon la version, des supports numériques. Les fiches remontent vers Athena.
    </p>

    <h3>Préparer une mission depuis Athena</h3>
    <ol>
        <li>Importez un scénario fictif dans le bureau (si vous avez les droits de gestion).</li>
        <li>Sur le dossier : téléchargez le <strong>pack terrain Arma</strong> ou le <strong>script</strong>.</li>
        <li>Exécutez le script côté serveur / init mission, puis appliquez les identités sur les unités concernées.</li>
        <li>Activez le code d’affaire indiqué pour que les remontées terrain rejoignent le bon dossier.</li>
    </ol>

    <h3>Enregistrer une personne (Overwatch)</h3>
    <ol>
        <li>Approchez la personne (joueur, IA ou otage scénarisé).</li>
        <li>Menu ACE → <strong>COMSPEC</strong> → <strong>ATAK Tactique</strong> → <strong>Enregistrer une personne</strong>.</li>
        <li>Renseignez identité déclarée, statut et circonstances.</li>
        <li>Joignez une photo du visage si une capture récente est disponible.</li>
        <li>La biométrie proposée est une <strong>simulation de gameplay</strong> — aucune lecture réelle.</li>
    </ol>

    <h3>Côté poste de commandement</h3>
    <ul>
        <li>Carte tactique Athena — onglet Personnes pour les fiches terrain.</li>
        <li>Bureau SSE — dossiers, croisements, exploitation et rédaction.</li>
    </ul>

    <h3>Doctrine terrain</h3>
    <div class="site-docs__callout site-docs__callout--info">
        <strong>Enregistrer sans décider.</strong>
        L’absence de correspondance immédiate n’impose pas de forcer une identité.
        Créez ou enrichissez un dossier d’intérêt ; laissez le bureau qualifier.
    </div>

    <p>
        Le détail installation et hub Overwatch est dans le
        <a href="<?= htmlspecialchars(url('atak/mod/guide'), ENT_QUOTES, 'UTF-8') ?>">guide du mod Overwatch</a>
        (rubrique renseignement interpersonnel).
    </p>
</section>
