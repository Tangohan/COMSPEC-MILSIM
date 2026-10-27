<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $orbatPresets */
$orbatPresets = is_array($orbatPresets ?? null) ? $orbatPresets : [];
/** @var list<array<string,mixed>> $officialKits */
$officialKits = is_array($officialKits ?? null) ? $officialKits : [];
/** @var array<string, array<string,string>> $tenantTypes */
$tenantTypes = is_array($tenantTypes ?? null) ? $tenantTypes : [];
$isLoggedIn = !empty($isLoggedIn);
$createUrl = $isLoggedIn ? url('communities/create') : url('login');
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

/**
 * Explication des champs : [champ, rôle, conseil, où le modifier ensuite].
 *
 * @var array<string, array{title: string, intro: string, fields: list<array{0: string, 1: string, 2: string, 3: string}>}> $steps
 */
$steps = [
    'etape-identite' => [
        'title' => '1. Identité',
        'intro' => 'Ce que les visiteurs voient en premier : le nom, l’adresse web et, si vous le souhaitez, l’unité que vous représentez.',
        'fields' => [
            ['Nom affiché', 'Titre de la communauté sur le registre, la page publique, les e-mails et la connexion Overwatch.', 'Le nom sous lequel vos joueurs vous connaissent déjà. Seul champ obligatoire de l’assistant.', 'Paramètres de la communauté'],
            ['Adresse web personnalisée', 'Segment de l’adresse publique (/c/votre-adresse). Sans case cochée, elle est calculée depuis le nom.', 'Court, stable, sans date ni saison : il figure dans les liens et invitations déjà partagés.', 'Sur demande à l’équipe de la plateforme'],
            ['Langue par défaut', 'Langue des e-mails et des textes par défaut.', 'Chaque membre peut ensuite choisir la sienne.', 'Paramètres de la communauté'],
            ['Fuseau horaire', 'Heure affichée pour les opérations, rappels et pointages.', 'Prenez celui de la majorité de vos joueurs.', 'Paramètres de la communauté'],
            ['Unité réelle représentée', 'Unité, régiment ou commandement dont vous vous inspirez, issu du référentiel militaire. Plusieurs unités possibles, d’un seul pays.', 'La recherche accepte sigles, surnoms et anciens noms (« Green Berets », « BFST », « SAS »). Indication d’inspiration, pas d’affiliation officielle.', 'Paramètres de la communauté → Identité'],
            ['Unité fictive', 'Nom libre affiché sur la fiche registre si vous ne jouez pas une unité réelle.', 'Utile aussi si votre unité réelle n’est pas encore au référentiel.', 'Paramètres de la communauté → Identité'],
            ['Vitrine (accroche, doctrine, badges, textes, logo, bannière)', 'Contenu de la page publique de recrutement.', 'Facultatif à la création. Une bannière paysage et trois phrases claires suffisent pour commencer.', 'Page d’accueil publique'],
        ],
    ],
    'etape-type' => [
        'title' => '2. Type de communauté',
        'intro' => 'Le type fixe les modules visibles. Il se change plus tard sans perte de données : un module masqué garde son contenu.',
        'fields' => [],
    ],
    'etape-organisation' => [
        'title' => '3. Organisation',
        'intro' => 'Trois notions distinctes : les rôles (droits sur le site), les fonctions (métier affiché dans le dossier, sans droit) et les unités (l’organigramme).',
        'fields' => [
            ['Modèle de rôles', '« Démarrage rapide » ou « Modération élargie » : crée Fondateur, État-major, RH, Instructeur, Membre, Invité et Modérateur.', 'Démarrage rapide convient à la plupart des unités. La seule différence porte sur la portée du modérateur.', 'Effectifs → Niveaux d’accès'],
            ['Rôles supplémentaires', 'Jusqu’à 15 profils propres à votre unité, avec une sélection d’autorisations.', 'Créez-en seulement s’il manque un besoin réel (ex. « Cellule logistique ») ; vous les attribuerez aux membres ensuite.', 'Effectifs → Niveaux d’accès'],
            ['Modèle officiel', 'Copie un modèle du catalogue : unités, fonctions, parfois des rôles. Ce qui existe déjà n’est jamais écrasé.', 'Prenez le modèle le plus proche de votre armée et de votre taille ; les autres restent applicables plus tard avec aperçu.', 'Catalogue de l’organisation'],
            ['Vous êtes le chef de l’unité principale', 'Vous affecte comme chef de l’unité racine.', 'Décochez si un autre membre commandera ; le poste restera vacant.', 'Effectifs → Chaîne de commandement'],
            ['Structure type', 'Remplace l’arborescence par une forme prête : escouade, section, peloton US, compagnie, détachement SOF, groupement interarmes, communauté de joueurs.', 'Choisissez un cran au-dessus de votre effectif actuel : il est plus simple de supprimer que d’ajouter.', 'Structure et effectifs'],
            ['Arborescence', 'Chaque ligne : nom, adresse courte, type (groupe, section, escouade, équipe), ordre et unité parente.', 'Au moins une unité racine. Gardez des noms courts : ils s’affichent sur les cartes et l’ORBAT.', 'Structure et effectifs'],
            ['Adresses courtes par unité', 'Permet de choisir l’adresse de chaque unité au lieu de la déduire du nom.', 'Rarement utile : laissez décoché sauf besoin de liens précis.', 'Structure et effectifs'],
        ],
    ],
    'etape-grades' => [
        'title' => '4. Grades',
        'intro' => 'Le référentiel affiché sur les profils, l’ORBAT et les courriers. Les grades ne donnent aucun droit.',
        'fields' => [
            ['Référentiel', 'Grades français (Armée de terre) ou américains.', 'Celui de l’armée que vous jouez. Vérifiez la cohérence avec le modèle choisi à l’étape 3.', 'Paramètres de la communauté'],
            ['Grade du fondateur', 'Grade affiché sur votre dossier.', 'Les grades des membres s’attribuent ensuite dans Effectifs.', 'Effectifs'],
        ],
    ],
    'etape-acces' => [
        'title' => '5. Accès & offre',
        'intro' => 'Comment les candidats arrivent, ce qui est visible et la formule.',
        'fields' => [
            ['Mode d’inscription', 'Dossier MilSim complet, formulaire court ou recrutement via Discord.', 'Dossier MilSim si vous sélectionnez ; formulaire court pour une communauté ouverte ; Discord si l’entretien se fait déjà là-bas.', 'Paramètres → Inscription'],
            ['Message d’accueil', 'Texte de la page publique, surtout utile en formulaire court.', 'Deux ou trois phrases : qui vous êtes, quand vous jouez, à qui vous vous adressez.', 'Paramètres → Inscription'],
            ['Fermer le recrutement', 'La vitrine reste visible, les candidatures sont refusées.', 'Pratique tant que l’organigramme n’est pas prêt.', 'Paramètres → Inscription'],
            ['Confirmation « sans IA »', 'Le candidat atteste avoir rédigé sa candidature lui-même.', 'Recommandé en dossier MilSim.', 'Paramètres → Inscription'],
            ['Refuser les comptes d’une autre communauté', 'Bloque les comptes déjà membres ailleurs.', 'Utile pour les unités qui exigent l’exclusivité ; les visiteurs sans compte restent acceptés.', 'Paramètres → Inscription'],
            ['Atelier formulaire MilSim', 'Préambule, règles et champs du dossier, avec aperçu.', 'Commencez avec le formulaire par défaut et ajustez après les premières candidatures.', 'Paramètres → Inscription'],
            ['Créneaux de disponibilité', 'Créneaux proposés aux candidats (soirs, week-end…).', 'Reprenez vos jours de session habituels.', 'Paramètres de la communauté'],
            ['Visibilité de l’organigramme', 'Public, membres seulement ou commandement seulement.', '« Public » aide à recruter sur des postes précis ; les noms ne s’affichent qu’aux personnes autorisées.', 'Paramètres de la communauté'],
            ['Formule', 'Quartier libre (gratuit) ou abonnement, selon la configuration de la plateforme.', 'Commencez gratuitement : changer de formule ne fait perdre aucune donnée.', 'Abonnement'],
            ['Ancienneté de l’organisation', 'Date de fondation servant au calcul d’ancienneté.', 'Si l’unité existait avant Athena, indiquez sa date réelle.', 'Organisation → Ancienneté'],
        ],
    ],
];
?>
<link href="<?= $h(asset_url('assets/css/community-create.css')) ?>" rel="stylesheet">

<div class="community-create">
    <div class="cc-shell cc-guide">
        <header class="cc-hero">
            <div class="cc-hero__inner">
                <p class="cc-brand">Athena<span>.</span></p>
                <p class="cc-kicker">Guide de création</p>
                <p class="cc-lead">Tout ce qu’il faut savoir avant et pendant la création d’une communauté : les étapes, l’effet de chaque champ, les modèles disponibles et la suite dans le back-office.</p>
                <div class="cc-hero__actions">
                    <a class="cc-hero__btn" href="<?= $h($createUrl) ?>"><?= $isLoggedIn ? 'Ouvrir l’assistant' : 'Se connecter pour créer' ?></a>
                    <a class="cc-hero__link" href="#apres-creation">Après la création</a>
                </div>
            </div>
        </header>

        <div class="cc-board">
            <nav class="cc-guide__toc" aria-label="Sommaire du guide">
                <a href="#avant">Avant de commencer</a>
                <?php foreach ($steps as $anchor => $step): ?>
                <a href="#<?= $h($anchor) ?>"><?= $h($step['title']) ?></a>
                <?php endforeach; ?>
                <a href="#structures">Structures types</a>
                <a href="#modeles">Modèles officiels</a>
                <a href="#apres-creation">Après la création</a>
                <a href="#faq">Questions fréquentes</a>
            </nav>

            <div class="cc-form">
                <section class="cc-section" id="avant">
                    <h2 class="cc-section__title">Avant de commencer</h2>
                    <p class="cc-section__text">Comptez 5 à 10 minutes. Seul le nom est obligatoire ; préparez si possible :</p>
                    <ul class="cc-guide__list">
                        <li>le nom et, si vous en avez un, le logo de la communauté ;</li>
                        <li>l’armée et l’unité que vous jouez (pour le référentiel de grades et l’unité représentée) ;</li>
                        <li>l’organigramme approximatif : combien de groupes, de sections, d’équipes ;</li>
                        <li>la façon dont vous recrutez aujourd’hui (dossier détaillé, entretien Discord, accès libre).</li>
                    </ul>
                    <p class="cc-hint">Bouton <strong>Aperçu</strong> à la dernière étape : il montre la page publique sans rien créer. Rien n’est définitif : chaque choix se retrouve dans le back-office.</p>
                </section>

                <?php foreach ($steps as $anchor => $step): ?>
                <section class="cc-section" id="<?= $h($anchor) ?>">
                    <h2 class="cc-section__title"><?= $h($step['title']) ?></h2>
                    <p class="cc-section__text"><?= $h($step['intro']) ?></p>
                    <?php if ($anchor === 'etape-type'): ?>
                    <div class="cc-guide__cards">
                        <?php foreach ($tenantTypes as $type): ?>
                        <article class="cc-guide__card">
                            <h3><?= $h((string) ($type['label'] ?? '')) ?></h3>
                            <p><?= $h((string) ($type['description'] ?? '')) ?></p>
                            <?php if (!empty($type['consequences'])): ?>
                            <p class="cc-guide__muted"><?= $h((string) $type['consequences']) ?></p>
                            <?php endif; ?>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($step['fields'] !== []): ?>
                    <div class="cc-guide__table-wrap">
                        <table class="cc-guide__table">
                            <thead>
                                <tr><th scope="col">Champ</th><th scope="col">À quoi il sert</th><th scope="col">Conseil</th><th scope="col">Modifier ensuite</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($step['fields'] as [$field, $role, $tip, $where]): ?>
                                <tr>
                                    <th scope="row"><?= $h($field) ?></th>
                                    <td><?= $h($role) ?></td>
                                    <td><?= $h($tip) ?></td>
                                    <td class="cc-guide__muted"><?= $h($where) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>

                <section class="cc-section" id="structures">
                    <h2 class="cc-section__title">Structures types de l’organigramme</h2>
                    <p class="cc-section__text">Proposées à l’étape 3. Elles remplacent l’arborescence en cours, que vous pouvez ensuite renommer et compléter.</p>
                    <div class="cc-guide__cards">
                        <?php foreach ($orbatPresets as $preset): ?>
                        <article class="cc-guide__card">
                            <p class="cc-guide__eyebrow"><?= $h((string) $preset['audience']) ?> · <?= (int) ($preset['count'] ?? 0) ?> unités</p>
                            <h3><?= $h((string) $preset['label']) ?></h3>
                            <p><?= $h((string) $preset['description']) ?></p>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="cc-section" id="modeles">
                    <h2 class="cc-section__title">Modèles officiels du catalogue</h2>
                    <p class="cc-section__text">Un modèle ajoute des unités, des fonctions (emplois du dossier) et parfois des rôles d’accès. Un seul se choisit dans l’assistant ; tous restent applicables plus tard depuis le back-office, avec un aperçu de ce qui sera ajouté.</p>
                    <div class="cc-guide__cards">
                        <?php foreach ($officialKits as $kit): ?>
                        <article class="cc-guide__card">
                            <p class="cc-guide__eyebrow"><?= $h(\App\Services\OrganizationCatalog\OrganizationKitDefinitions::volumeLabel($kit)) ?></p>
                            <h3><?= $h((string) ($kit['title'] ?? '')) ?></h3>
                            <p><?= $h((string) ($kit['summary'] ?? '')) ?></p>
                            <?php $jobNames = array_slice(array_column($kit['job_roles'] ?? [], 'name'), 0, 8); ?>
                            <?php if ($jobNames !== []): ?>
                            <p class="cc-guide__muted">Exemples de fonctions : <?= $h(implode(', ', $jobNames)) ?>…</p>
                            <?php endif; ?>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="cc-section" id="apres-creation">
                    <h2 class="cc-section__title">Après la création : la première soirée</h2>
                    <ol class="cc-guide__list cc-guide__list--ordered">
                        <li><strong>Configuration initiale</strong> (back-office) : la liste de démarrage indique ce qui reste à régler.</li>
                        <li><strong>Page d’accueil publique</strong> : bannière, accroche et texte « Qui sommes-nous ».</li>
                        <li><strong>Structure et effectifs</strong> : ajustez l’organigramme, puis <strong>Affectations</strong> pour placer les membres.</li>
                        <li><strong>Niveaux d’accès</strong> : donnez les rôles État-major, RH ou Instructeur à vos cadres.</li>
                        <li><strong>Invitations</strong> : faites venir vos premiers membres par lien ou e-mail.</li>
                        <li><strong>Opérations (calendrier)</strong> : créez la prochaine session pour ouvrir les inscriptions.</li>
                    </ol>
                    <p class="cc-hint">Dans le back-office, la recherche (<strong>Ctrl + K</strong> ou <strong>⌘ + K</strong>) retrouve n’importe quelle page, membre, document ou guide : tapez par exemple « grades », « invitation » ou « orbat ».</p>
                </section>

                <section class="cc-section" id="faq">
                    <h2 class="cc-section__title">Questions fréquentes</h2>
                    <details class="cc-help" open>
                        <summary>Puis-je tout modifier après la création&nbsp;?</summary>
                        <p>Oui, sauf l’adresse web, qui se change sur demande pour ne pas casser les liens déjà partagés. Le type de communauté, les rôles, l’organigramme, les grades et l’inscription se règlent dans le back-office.</p>
                    </details>
                    <details class="cc-help">
                        <summary>Que se passe-t-il si j’applique deux modèles&nbsp;?</summary>
                        <p>Les éléments déjà présents ne sont jamais modifiés : seuls les manquants sont ajoutés. Le journal du catalogue garde la trace de chaque application.</p>
                    </details>
                    <details class="cc-help">
                        <summary>Mon unité n’est pas dans le référentiel des unités réelles.</summary>
                        <p>Déclarez-la comme unité fictive avec son nom exact, puis signalez-la à l’équipe de la plateforme pour qu’elle soit ajoutée au référentiel.</p>
                    </details>
                    <details class="cc-help">
                        <summary>Quelle différence entre un rôle et une fonction&nbsp;?</summary>
                        <p>Un rôle donne des droits sur le site (modérer, recruter, administrer). Une fonction est un libellé métier du dossier (chef de groupe, infirmier, pilote) et ne donne aucun droit.</p>
                    </details>
                    <details class="cc-help">
                        <summary>Le paiement échoue ou je change d’avis sur la formule.</summary>
                        <p>Avec une formule payante, la communauté est créée après confirmation du paiement. Vous pouvez revenir en arrière et choisir la formule gratuite : votre brouillon est conservé dans la session.</p>
                    </details>
                </section>

                <div class="cc-nav">
                    <a class="cc-btn cc-btn--ghost" href="<?= $h(url('communities')) ?>">Voir le registre des communautés</a>
                    <a class="cc-btn cc-btn--primary ml-auto" href="<?= $h($createUrl) ?>"><?= $isLoggedIn ? 'Créer ma communauté' : 'Se connecter pour créer' ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
