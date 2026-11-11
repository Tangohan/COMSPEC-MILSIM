<?php declare(strict_types=1); ?>
<section id="bureau" class="site-docs__section">
    <h2>Le bureau SSE</h2>
    <p class="site-docs__lead">
        Interface plein écran « Bureau SSE » : barre supérieure, bandeau d’habilitation, navigation latérale rangée par usage, fil d’Ariane et zone de travail.
    </p>

    <h3>Barre supérieure</h3>
    <ul>
        <li><strong>Recherche globale</strong> — identités, sites, matériels, véhicules, documents, événements.</li>
        <li><strong>Aller à une page</strong> (<kbd>Ctrl</kbd> <kbd>K</kbd>) — ouvre n’importe quelle page du bureau par son nom.</li>
        <li><strong>Heure Zulu et durée de session</strong> — temps restant avant expiration de l’habilitation.</li>
        <li><strong>Actions</strong> — rédiger une fiche, créer un objet ou un dossier, quitter le bureau.</li>
    </ul>

    <h3>Navigation</h3>
    <p>
        L’<strong>accueil du bureau</strong> présente chaque espace en une phrase, avec ses pages. La barre latérale
        (le bouton <strong>Menu</strong> sur téléphone) suit le même rangement. Une page que votre session ne peut pas
        ouvrir n’y figure pas ; l’accueil dit lesquelles et pourquoi.
    </p>
    <table>
        <thead>
            <tr>
                <th>Espace</th>
                <th>Pages</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (\App\Support\SsePortalNav::sections(true, true, false, true, true) as $guideSection): ?>
                <tr>
                    <td><?= htmlspecialchars($guideSection['label'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= implode(', ', array_map(static fn (array $i): string => '<span>' . htmlspecialchars($i['label'], ENT_QUOTES, 'UTF-8') . '</span>', $guideSection['items'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Vue opérationnelle</h3>
    <p>
        Point d’entrée du centre : indicateurs, file opérateur, alertes et activité récente.
        Servez-vous-en pour prioriser la journée, pas pour tout traiter d’un coup.
    </p>

    <h3>Transmissions terrain</h3>
    <p>
        Journal de tout ce qui a été envoyé depuis Arma 3 (terminal SSE, ACE, Zeus, etc.).
        Filtrez par nature ou origine, puis ouvrez une fiche pour rejoindre l’identité, le site
        ou le dossier lié. Ce journal n’est pas une chronologie d’analyse : il sert à suivre
        la collecte brute.
    </p>

    <h3>Fiches de renseignement</h3>
    <p>
        La marche la plus basse du renseignement : un texte libre daté et situé, un ou plusieurs
        thèmes, et jusqu’à quatre pièces jointes. Le rédacteur s’ouvre en plein écran, avec la
        même disposition que celui de l’ATAK — un opérateur qui passe du jeu au bureau ne
        réapprend rien.
    </p>
    <p>
        Une fiche n’identifie personne et ne vaut pas preuve : elle consigne un constat.
        À la lecture, renseignez le suivi (prise en compte, exploitation, classement sans suite)
        et rattachez-la à un dossier si elle y trouve sa place.
    </p>

    <h3>Contexte mission et diffusion</h3>
    <p>
        Quand votre communauté rattache le travail à une mission théâtre, le contexte de mission
        et le niveau de classification de session guident ce que vous consultez et ce que vous pouvez diffuser.
        Changez-les depuis les commandes prévues dans le bureau lorsque vous êtes habilité.
    </p>

    <p>
        Ce manuel est accessible depuis la navigation (<strong>Documentation</strong>)
        ou directement à l’adresse du guide pendant votre session SSE.
    </p>
</section>
