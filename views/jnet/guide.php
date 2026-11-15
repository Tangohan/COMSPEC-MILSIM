<?php
declare(strict_types=1);

/**
 * Guide de l'extranet JNET pour les membres (JnetPortalController::guide), dans la coque views/jnet/_page.php : sommaire
 * cliquable à gauche, recherche dans la page, neuf chapitres à ancres. Rédigé d'après ce que le code propose réellement ;
 * les rubriques personnelles du hub (Mon profil, Mon ATAK, Mes données, Mon service) sont décrites sans adresse précise.
 * Le script public/assets/js/jnet_guide.js filtre le sommaire et les chapitres.
 */

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$u = static fn (string $path): string => htmlspecialchars(url($path), ENT_QUOTES, 'UTF-8');

/** Chapitres du sommaire : ancre => titre. Chaque ancre est reprise par un <section id="…"> plus bas. */
$toc = [
    'cest-quoi' => '1. C’est quoi JNET ?',
    'premiers-pas' => '2. Premiers pas',
    'espace-unite' => '3. L’espace d’une unité',
    'personnaliser' => '4. Personnaliser son espace',
    'liaisons' => '5. Liaisons',
    'outils' => '6. Recherche, messagerie et annuaire',
    'securite' => '7. Sécurité et confidentialité',
    'faq' => '8. Questions fréquentes',
    'glossaire' => '9. Glossaire',
];
/** Sous-rubriques de l'espace d'unité (chapitre 3) : ancre => titre. */
$tabs = [
    'onglet-accueil' => 'Accueil',
    'onglet-echanges' => 'Ordres et échanges',
    'onglet-effectif' => 'Effectif et suivi',
    'onglet-taches' => 'Tâches',
    'onglet-pages' => 'Pages',
    'onglet-photos' => 'Photos',
    'onglet-documents' => 'Documents',
    'onglet-operations' => 'Opérations',
];
?>
<div class="jg" data-jg>
    <p class="jg-intro">
        JNET est l’extranet de votre unité. Ce guide explique, pas à pas, ce que vous y trouvez, ce que vous pouvez y faire selon votre droit,
        et comment retrouver votre chemin quand quelque chose semble manquer.
    </p>

    <div class="jg-tools">
        <button type="button" class="jg-toc-toggle" id="jg-toc-toggle" aria-expanded="false" aria-controls="jg-toc" hidden>Sommaire</button>
        <form class="jg-search" role="search" onsubmit="return false">
            <label for="jg-filter" class="jg-sr">Chercher dans le guide</label>
            <input id="jg-filter" type="search" autocomplete="off" placeholder="Chercher dans le guide…" data-jg-filter>
            <p class="jg-count" role="status" aria-live="polite" data-jg-count></p>
            <span hidden data-jg-one>rubrique correspond</span>
            <span hidden data-jg-many>rubriques correspondent</span>
        </form>
    </div>

    <div class="jg-layout">
        <aside class="jg-side" id="jg-toc" aria-label="Sommaire du guide">
            <p class="jg-side__title">Sommaire</p>
            <ol class="jg-toc">
                <?php foreach ($toc as $id => $title): ?>
                    <li>
                        <a href="#<?= $h($id) ?>" data-jg-toc="<?= $h($id) ?>"><?= $h($title) ?></a>
                        <?php if ($id === 'espace-unite'): ?>
                            <ul>
                                <?php foreach ($tabs as $tabId => $tabTitle): ?>
                                    <li><a href="#<?= $h($tabId) ?>" data-jg-toc="<?= $h($tabId) ?>"><?= $h($tabTitle) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="jg-side__links">
                <a href="<?= $u('jnet/espaces') ?>">Ouvrir Mon extranet</a>
                <a href="<?= $u('documentation') ?>">Guide du portail Athena</a>
            </p>
        </aside>

        <div class="jg-main">
            <p class="jg-empty" data-jg-empty hidden>Aucun chapitre ne correspond. Essayez un autre mot, ou videz la recherche.</p>

            <!-- 1. C'est quoi JNET -->
            <section class="jg-sec" id="cest-quoi" aria-labelledby="cest-quoi-t" data-jg-sec>
                <h2 id="cest-quoi-t">1. C’est quoi JNET ?</h2>
                <p>
                    JNET est l’extranet de votre organisation sur Athena : un espace de travail pour chaque unité de l’organigramme (l’ORBAT),
                    réunis au même endroit. Sa devise tient en quatre mots : « Une unité. Un espace. »
                </p>
                <p>
                    Concrètement, les ordres descendent la chaîne de commandement avec un accusé de lecture. L’unité garde ses tâches, ses pages,
                    ses photos et ses documents sous la main. Et chaque membre retrouve son profil, son ATAK et ses données au même endroit.
                </p>

                <article class="jg-sub" id="qui-entre" data-jg-sub>
                    <h3>Qui y entre, et pourquoi</h3>
                    <ul>
                        <li>Tout membre rattaché à une organisation : pour suivre ce que son unité traite, lire les ordres et y répondre.</li>
                        <li>Les chefs d’unité et l’encadrement : pour diffuser, relayer, faire remonter, et vérifier que les consignes sont lues.</li>
                        <li>La gestion de l’organisation : pour la vue d’ensemble. Elle retrouve JNET dans l’onglet « Mon extranet », à côté de la gestion.</li>
                    </ul>
                    <p class="jg-note">
                        JNET n’est pas proposé à un compte sans organisation, ni aux profils de communauté qui l’ont retiré de leurs menus
                        (par exemple Effectifs ou Carte ATAK). Si vous ne le voyez pas, lisez la question « Je ne vois pas JNET » dans les questions fréquentes.
                    </p>
                </article>

                <article class="jg-sub" id="vue-ensemble" data-jg-sub>
                    <h3>Ce que JNET contient</h3>
                    <figure class="jg-fig">
                        <svg viewBox="0 0 560 250" role="img" aria-labelledby="jg-fig1-t">
                            <title id="jg-fig1-t">Schéma : l’organisation, ses unités et leurs sous-unités, avec une liaison entre deux unités.</title>
                            <rect class="jg-box jg-box--top" x="190" y="10" width="180" height="44" rx="6"/>
                            <text x="280" y="37" text-anchor="middle">Espace commun</text>
                            <path class="jg-line" d="M280 54v22M130 76h300M130 76v22M430 76v22"/>
                            <rect class="jg-box" x="40" y="98" width="180" height="44" rx="6"/>
                            <text x="130" y="125" text-anchor="middle">Unité A</text>
                            <rect class="jg-box" x="340" y="98" width="180" height="44" rx="6"/>
                            <text x="430" y="125" text-anchor="middle">Unité B</text>
                            <path class="jg-line" d="M85 142v22M175 142v22M430 142v22"/>
                            <rect class="jg-box jg-box--sm" x="20" y="164" width="130" height="38" rx="6"/>
                            <text x="85" y="188" text-anchor="middle">Sous-unité A1</text>
                            <rect class="jg-box jg-box--sm" x="150" y="164" width="130" height="38" rx="6"/>
                            <text x="215" y="188" text-anchor="middle">Sous-unité A2</text>
                            <rect class="jg-box jg-box--sm" x="365" y="164" width="130" height="38" rx="6"/>
                            <text x="430" y="188" text-anchor="middle">Sous-unité B1</text>
                            <path class="jg-dash" d="M220 120h120"/>
                            <text class="jg-cap" x="280" y="112" text-anchor="middle">Liaison</text>
                            <text class="jg-cap" x="280" y="236" text-anchor="middle">Chaque case a son propre espace.</text>
                        </svg>
                        <figcaption>L’espace commun réunit toute l’organisation ; chaque unité et chaque sous-unité a le sien. Une liaison relie deux unités qui ne sont pas dans la même filiation.</figcaption>
                    </figure>
                    <dl class="jg-dl">
                        <dt>Mon extranet</dt>
                        <dd>Le point d’arrivée : vos unités, puis vos rubriques personnelles.</dd>
                        <dt>Espace commun</dt>
                        <dd>L’espace de toute l’organisation : ses échanges, ses unités et ses opérations.</dd>
                        <dt>Espace d’unité</dt>
                        <dd>Un espace par unité, avec ses onglets : accueil, ordres et échanges, effectif, tâches, pages, photos, documents, opérations.</dd>
                        <dt>Pages communes</dt>
                        <dd>La messagerie, l’annuaire du personnel, les opérations et la bibliothèque, ouvertes à toute l’organisation.</dd>
                    </dl>
                </article>
            </section>

            <!-- 2. Premiers pas -->
            <section class="jg-sec" id="premiers-pas" aria-labelledby="premiers-pas-t" data-jg-sec>
                <h2 id="premiers-pas-t">2. Premiers pas</h2>

                <article class="jg-sub" id="mon-extranet" data-jg-sub>
                    <h3>Arriver sur Mon extranet</h3>
                    <p>
                        Quand vous ouvrez JNET, vous arrivez sur « Mon extranet ». Le registre montre d’abord vos unités (celles où vous êtes affecté),
                        puis les autres unités que vous pouvez voir. Chaque ligne indique le type de l’unité, son chef, son effectif, votre droit
                        et le nombre d’éléments à lire.
                    </p>
                    <p>Sous le registre, des onglets rassemblent vos rubriques personnelles :</p>
                    <dl class="jg-dl">
                        <dt>Mon unité</dt>
                        <dd>La fiche de votre unité, la messagerie, les opérations, l’annuaire du personnel, la bibliothèque et les événements.</dd>
                        <dt>Mon profil</dt>
                        <dd>Votre fiche, votre compte, vos qualifications, votre avancement, votre dossier de carrière, vos décorations, votre dotation et vos accès.</dd>
                        <dt>Mon ATAK</dt>
                        <dd>La liaison de votre téléphone avec Athena, vos appareils et la musique écoutée en jeu.</dd>
                        <dt>Mes données</dt>
                        <dd>Vos pièces et papiers, et la copie ou la suppression de ce que le site garde sur vous.</dd>
                        <dt>Mon service</dt>
                        <dd>Ce qui vous attend : tâches, avis demandés et démarches.</dd>
                        <dt>Autres unités</dt>
                        <dd>Les unités que vous pouvez consulter sans en être membre.</dd>
                    </dl>
                    <p class="jg-note">
                        Les rubriques proposées dépendent du profil de votre communauté : une rubrique absente n’est pas une erreur. Ces pages personnelles
                        sont en cours de déplacement vers JNET : passez toujours par « Mon extranet » plutôt que par un favori enregistré.
                    </p>
                    <p><a class="jg-btn" href="<?= $u('jnet/espaces') ?>">Ouvrir Mon extranet</a></p>
                </article>

                <article class="jg-sub" id="espace-commun" data-jg-sub>
                    <h3>L’espace commun</h3>
                    <p>
                        L’espace commun est celui de toute l’organisation. On l’ouvre depuis l’onglet « Espace commun », ou depuis la ligne de l’organisation
                        dans Mon extranet. Il montre les échanges de l’organisation, ses unités sous deux formes (cartes ou organigramme) et ses opérations.
                    </p>
                    <p><a class="jg-btn" href="<?= $u('jnet?commun=1') ?>">Ouvrir l’espace commun</a></p>
                </article>

                <article class="jg-sub" id="entrer-unite" data-jg-sub>
                    <h3>Entrer dans une unité</h3>
                    <p>
                        Cliquez sur la ligne d’une unité, ou sur « Entrer ». À la première visite de la session, l’unité peut d’abord montrer un écran d’entrée
                        (son insigne, sa devise, son mot d’accueil) : c’est le chef de l’unité qui l’active. « Entrer dans l’espace » ouvre ensuite l’accueil.
                    </p>
                    <p>
                        Si le chef a demandé la prise en compte obligatoire, une fenêtre « Information importante » peut s’afficher à l’entrée : elle liste les ordres
                        que vous n’avez pas encore accusés, et reste là jusqu’à ce que vous cliquiez sur « J’ai pris connaissance ».
                    </p>
                </article>

                <article class="jg-sub" id="droits" data-jg-sub>
                    <h3>Votre droit sur une unité</h3>
                    <p>Chaque ligne du registre affiche votre droit sur l’unité. Il y en a quatre :</p>
                    <dl class="jg-dl">
                        <dt>Administration</dt>
                        <dd>Réservé à la gestion de l’organisation : tout est ouvert, sur toutes les unités.</dd>
                        <dt>Chef de l’unité</dt>
                        <dd>Vous commandez l’unité, ou une unité au-dessus : vous publiez, relayez, liez, et vous personnalisez l’espace.</dd>
                        <dt>Lecture et écriture</dt>
                        <dd>Vous êtes membre de l’unité, ou d’une unité au-dessus : vous publiez, créez des tâches et des pages, répondez aux rendez-vous.</dd>
                        <dt>Lecture seule</dt>
                        <dd>Vous voyez l’espace sans y publier. Sur ces lignes, « Demander l’affectation » ouvre la demande d’affectation de votre avancement.</dd>
                    </dl>
                </article>
            </section>

            <!-- 3. L'espace d'une unité -->
            <section class="jg-sec" id="espace-unite" aria-labelledby="espace-unite-t" data-jg-sec>
                <h2 id="espace-unite-t">3. L’espace d’une unité</h2>
                <p>
                    Dans un espace d’unité, une rangée d’onglets en haut de la page donne accès à chaque rubrique. En haut à gauche, le nom de l’unité
                    ramène toujours à l’accueil ; « Changer d’espace » passe d’une unité à une autre ; la loupe (ou la touche /) lance la recherche.
                </p>

                <article class="jg-sub" id="onglet-accueil" data-jg-sub>
                    <h3>Accueil</h3>
                    <p>
                        Le tableau de bord de l’unité : son nom, sa devise, son chef et son adjoint, la chaîne des échelons, et quatre chiffres
                        (disponibles, à lire, opérations, sous-unités). En dessous, par ordre d’importance :
                    </p>
                    <ul>
                        <li>le mot d’accueil du chef, s’il en a écrit un ;</li>
                        <li>les informations à prendre en compte, avec le bouton « J’ai pris connaissance » ;</li>
                        <li>les ordres reçus du commandement, et le fil de l’unité où l’on publie en un clic ;</li>
                        <li>le prochain rendez-vous, avec votre réponse de présence (présent, incertain, absent) ;</li>
                        <li>les opérations, les unités liées, vos tâches (avec un champ pour en ajouter une), les pages, l’effectif et les sous-unités ;</li>
                        <li>les dernières photos et les derniers documents.</li>
                    </ul>
                </article>

                <article class="jg-sub" id="onglet-echanges" data-jg-sub>
                    <h3>Ordres et échanges</h3>
                    <p>
                        C’est le cœur de JNET. Un échange part d’un espace et vise l’unité elle-même, son échelon supérieur ou ses subordonnés.
                        Il se range dans une des trois colonnes :
                    </p>
                    <figure class="jg-fig">
                        <svg viewBox="0 0 560 190" role="img" aria-labelledby="jg-fig2-t">
                            <title id="jg-fig2-t">Schéma : les ordres descendent de l’échelon supérieur vers l’unité ; les comptes rendus remontent ; le fil interne reste dans l’unité.</title>
                            <rect class="jg-box jg-box--top" x="190" y="8" width="180" height="40" rx="6"/>
                            <text x="280" y="33" text-anchor="middle">Échelon supérieur</text>
                            <rect class="jg-box" x="190" y="142" width="180" height="40" rx="6"/>
                            <text x="280" y="167" text-anchor="middle">Votre unité</text>
                            <path class="jg-arrow" d="M240 50v90"/>
                            <path class="jg-arrow" d="M320 140V52"/>
                            <path class="jg-line" d="M370 162h40a22 22 0 1 0 0-.1"/>
                            <text class="jg-cap" x="228" y="98" text-anchor="end">Reçu</text>
                            <text class="jg-cap" x="332" y="98">Remonté</text>
                            <text class="jg-cap" x="440" y="166">Dans l’unité</text>
                        </svg>
                        <figcaption>Reçu descend du commandement, Remonté part vers l’échelon supérieur, Dans l’unité reste entre les membres.</figcaption>
                    </figure>
                    <dl class="jg-dl">
                        <dt>Reçu du commandement</dt>
                        <dd>Les ordres et consignes descendus de l’échelon supérieur et des unités au-dessus.</dd>
                        <dt>Dans l’unité</dt>
                        <dd>Le fil interne de l’unité et de ses sous-unités, comme une discussion.</dd>
                        <dt>Remonté</dt>
                        <dd>Les comptes rendus et le renseignement transmis vers l’échelon supérieur.</dd>
                    </dl>
                    <h4>Les types d’échange</h4>
                    <p>
                        À la publication, on choisit un type : Ordre, Compte rendu, Renseignement, Document ou Information. On donne un titre,
                        un détail facultatif et, si on veut, l’adresse d’une page Athena à joindre (un document, une fiche, une carte). La case « Diffuser à »
                        choisit les destinataires : l’espace lui-même, l’échelon supérieur ou une sous-unité.
                    </p>
                    <h4>L’accusé de lecture</h4>
                    <p>
                        Un ordre demande un accusé de lecture à chaque destinataire. Sous l’ordre, le compteur indique « Lu par 3 / 8 — 5 en attente »,
                        et le bouton « Accuser réception » enregistre votre lecture. Elle est horodatée et visible du commandement. Sur l’accueil,
                        « J’ai pris connaissance » fait la même chose.
                    </p>
                    <h4>Relayer, faire remonter, partager</h4>
                    <dl class="jg-dl">
                        <dt>Relayer aux sous-unités</dt>
                        <dd>Sous un ordre reçu, « Relayer aux sous-unités » le transmet tel quel aux sous-unités cochées, avec un mot du chef en tête si vous en écrivez un.</dd>
                        <dt>Faire remonter</dt>
                        <dd>Dans la colonne « Remonté », « Faire remonter vers… » publie un compte rendu vers l’échelon supérieur.</dd>
                        <dt>Partager avec une autre unité</dt>
                        <dd>Sous un échange, ce lien l’envoie dans le fil d’une autre unité, comme une information, jamais comme un ordre. Seules les unités où vous pouvez publier, et les unités liées à la vôtre, sont proposées.</dd>
                    </dl>
                    <p class="jg-note">
                        Publier demande d’être membre de l’unité ou d’une unité au-dessus. Sans cela, vous consultez l’espace sans pouvoir y écrire.
                        Le chef peut aussi réserver la publication à ses chefs d’équipe, ou à lui seul (voir « Personnaliser son espace »).
                    </p>
                </article>

                <article class="jg-sub" id="onglet-effectif" data-jg-sub>
                    <h3>Effectif et suivi</h3>
                    <p>
                        Pour les membres qui peuvent publier, l’onglet « Effectif » devient un tableau de suivi : chaque membre direct de l’unité avec son statut
                        (disponible, indisponible, en absence), sa réponse au prochain rendez-vous, les consignes qu’il a lues et ce qui reste à traiter.
                        Les filtres du haut (Tous, À traiter, Disponibles, En absence, Indisponibles) trient instantanément.
                    </p>
                    <p>
                        « Relancer les sans-réponse » envoie un message, par la messagerie JNET, aux membres qui n’ont pas répondu au rendez-vous.
                        Un même rappel ne repart pas avant six heures. Les autres lecteurs voient simplement la liste des membres et leur disponibilité.
                    </p>
                    <p>
                        L’effectif compte les sous-unités. Les membres d’une unité rattachée temporairement ne sont pas comptés dans celui de l’unité qui les accueille.
                        Le lien « Annuaire de l’organisation » mène à l’annuaire du personnel.
                    </p>
                </article>

                <article class="jg-sub" id="onglet-taches" data-jg-sub>
                    <h3>Tâches</h3>
                    <p>
                        Un tableau à trois colonnes : À faire, En cours, Fait. Tapez le titre d’une tâche puis Entrée pour l’ajouter. Glissez une carte d’une colonne à l’autre,
                        ou utilisez ses boutons « Démarrer », « Terminer » et « Rouvrir ». Une liste « Responsable » permet de confier la tâche à un membre ;
                        elle apparaît alors dans « Mes tâches » sur son accueil.
                    </p>
                    <p>
                        Chaque membre qui peut écrire ajoute des tâches et les fait avancer. Retirer une tâche revient à son auteur, à son responsable, au chef de l’espace et aux chefs de module.
                        Une tâche peut être liée à un ordre, une opération, une page… (voir « Liaisons »).
                    </p>
                </article>

                <article class="jg-sub" id="onglet-pages" data-jg-sub>
                    <h3>Pages</h3>
                    <p>
                        Des pages écrites ensemble, comme un document partagé : consignes, procédures, comptes rendus, notes de préparation.
                        « Nouvelle page » en crée une ; « Modifier » ouvre l’éditeur ; « Enregistrer » (ou Ctrl + S) la garde. La liste affiche qui l’a modifiée en dernier,
                        et on peut réordonner les pages par glisser-déposer ou avec les flèches.
                    </p>
                    <p>Une page est faite de blocs. On en insère un avec le bouton « + », ou en tapant « / » dans une ligne vide pour ouvrir la liste :</p>
                    <dl class="jg-dl">
                        <dt>Texte et Titre</dt>
                        <dd>Un paragraphe simple, et un intertitre de section.</dd>
                        <dt>Tâche</dt>
                        <dd>Une case à cocher que chacun peut cocher en lecture.</dd>
                        <dt>Encadré</dt>
                        <dd>Un point d’attention mis en avant.</dd>
                        <dt>Sondage</dt>
                        <dd>Une question avec deux à huit choix. Un vote par membre, modifiable.</dd>
                        <dt>Compte à rebours</dt>
                        <dd>Les jours restants avant le prochain rendez-vous.</dd>
                        <dt>Séparateur</dt>
                        <dd>Un filet horizontal.</dd>
                    </dl>
                    <p>
                        Dans le texte, « @ » (ou « [[ ») ouvre une recherche pour citer une unité, un membre, une opération ou une page : la mention devient un lien,
                        affiché « Lien masqué » pour un lecteur qui n’a pas le droit de voir l’objet cité. Une page se lit avec trois réactions (Vu, Reçu, Question)
                        et des commentaires. Seuls l’auteur d’une page et le chef de l’espace peuvent la supprimer.
                    </p>
                </article>

                <article class="jg-sub" id="onglet-photos" data-jg-sub>
                    <h3>Photos</h3>
                    <p>
                        Les photos de l’unité, en grille, avec une visionneuse plein écran. Elles viennent du partage de fichiers de la communauté (les dossiers ouverts à l’unité)
                        et des photos de terrain publiées dans les échanges que vous voyez. Les puces du haut filtrent par album.
                        Un cliché protégé n’est jamais montré à qui n’y a pas droit.
                    </p>
                    <p>« Déposer » mène au dossier partagé de l’unité dans le partage de fichiers : on y dépose les images, elles apparaissent ensuite ici.</p>
                </article>

                <article class="jg-sub" id="onglet-documents" data-jg-sub>
                    <h3>Documents</h3>
                    <p>
                        Les fichiers partagés avec l’unité, les pièces jointes des ordres et les documents publiés de la bibliothèque. Chaque document s’ouvre avec les droits
                        que vous y avez déjà : JNET ne donne aucun droit de plus. « Déposer un document » mène au partage de fichiers (PDF, DOCX, XLSX, KMZ…).
                        « Partager avec une autre unité » envoie un document dans le fil d’une autre unité.
                    </p>
                </article>

                <article class="jg-sub" id="onglet-operations" data-jg-sub>
                    <h3>Opérations</h3>
                    <p>
                        Les opérations où l’unité ou une de ses sous-unités est engagée, avec leur état (en cours, planifiée…). Chaque ligne ouvre la fiche de l’opération ;
                        « Toutes les opérations » liste celles de l’organisation.
                    </p>
                    <p>
                        Deux autres onglets apparaissent selon les cas : « Sous-unités » (cartes ou organigramme des sous-unités, chacune avec son espace)
                        et « Unités liées » (voir « Liaisons »).
                    </p>
                    <p>
                        <a class="jg-btn" href="<?= $u('jnet/operations') ?>">Voir les opérations</a>
                    </p>
                </article>
            </section>

            <!-- 4. Personnaliser -->
            <section class="jg-sec" id="personnaliser" aria-labelledby="personnaliser-t" data-jg-sec>
                <h2 id="personnaliser-t">4. Personnaliser son espace</h2>
                <p>
                    Le bouton « Personnaliser », en haut de l’espace, est réservé au chef de l’unité (et à l’administration). Un aperçu montre le résultat avant l’enregistrement.
                    Les membres voient ces choix à l’entrée de l’espace et en haut de sa page.
                </p>
                <article class="jg-sub" id="apparence" data-jg-sub>
                    <h3>Apparence</h3>
                    <dl class="jg-dl">
                        <dt>Thème et couleur</dt>
                        <dd>Sept thèmes (Athena noir, Opérations, Nuit, Désert, Marine, Acier, Garde), et une couleur d’unité au choix. Sans couleur choisie, c’est celle de l’ORBAT ou du thème.</dd>
                        <dt>Devise de l’unité</dt>
                        <dd>Une phrase courte sous le nom de l’unité. Vide, c’est la devise de l’ORBAT, s’il y en a une.</dd>
                        <dt>Mot d’accueil</dt>
                        <dd>Quelques lignes du chef, affichées à l’entrée et en haut de l’espace (600 caractères au plus).</dd>
                        <dt>Mention d’accès</dt>
                        <dd>Le bandeau de l’écran d’entrée, par exemple « Diffusion restreinte ».</dd>
                        <dt>Insigne et bannière</dt>
                        <dd>L’insigne de préférence en PNG transparent ; la bannière, une photo en paysage de 8 Mo au plus, sert de fond à l’entrée et au haut de l’espace.</dd>
                        <dt>Écran d’entrée</dt>
                        <dd>Le montrer ou non à la première visite de la session.</dd>
                    </dl>
                </article>
                <article class="jg-sub" id="rubriques" data-jg-sub>
                    <h3>Rubriques affichées</h3>
                    <p>
                        Tâches, Pages, Photos et Documents peuvent être masqués. Une rubrique masquée disparaît des onglets, mais son contenu est conservé :
                        la réafficher le retrouve intact.
                    </p>
                </article>
                <article class="jg-sub" id="regles" data-jg-sub>
                    <h3>Ordres et relais</h3>
                    <dl class="jg-dl">
                        <dt>Prise en compte obligatoire</dt>
                        <dd>Un ordre que le membre n’a pas accusé s’affiche dans une fenêtre bloquante à l’entrée de l’espace, jusqu’à « J’ai pris connaissance ».</dd>
                        <dt>Relais automatique aux sous-unités</dt>
                        <dd>Un ordre adressé à l’unité repart tout seul, une fois, vers ses sous-unités directes, tel quel et avec la mention de son origine.</dd>
                        <dt>Publication réservée à</dt>
                        <dd>Tous les membres, les chefs d’équipe et au-dessus, ou les chefs d’unité seulement. Ce réglage resserre la règle de l’ORBAT : il ne l’élargit jamais.</dd>
                    </dl>
                </article>
                <article class="jg-sub" id="acces" data-jg-sub>
                    <h3>Accès et gestionnaires</h3>
                    <dl class="jg-dl">
                        <dt>Accès</dt>
                        <dd>
                            Tant que la liste est vide, tous ceux qui voient l’unité dans l’ORBAT ouvrent l’espace. Dès qu’une unité y figure, l’espace n’est ouvert
                            qu’à l’unité elle-même, à son encadrement et aux unités listées, avec un droit par ligne : Lecture seule, Lecture et écriture, ou Chef de module.
                        </dd>
                        <dt>Gestionnaires</dt>
                        <dd>
                            Les chefs de module modèrent les tâches et les pages de l’espace (retirer ce qu’un autre a écrit) et y écrivent. On les choisit parmi
                            les membres de l’unité, de ses sous-unités et de son encadrement, par leur indicatif.
                        </dd>
                    </dl>
                </article>
            </section>

            <!-- 5. Liaisons -->
            <section class="jg-sec" id="liaisons" aria-labelledby="liaisons-t" data-jg-sec>
                <h2 id="liaisons-t">5. Liaisons</h2>
                <p>
                    JNET connaît deux sortes de liaisons : celles qui relient deux unités, et celles qui relient deux contenus.
                    Aucune ne donne de droit de plus que le modèle de JNET.
                </p>
                <article class="jg-sub" id="unites-liees" data-jg-sub>
                    <h3>Unités liées</h3>
                    <p>
                        Une liaison relie deux unités qui ne sont pas dans la même filiation de l’ORBAT. Son type dit pourquoi :
                    </p>
                    <dl class="jg-dl">
                        <dt>Appui</dt>
                        <dd>Une unité en appuie une autre. Le sens se précise à la création.</dd>
                        <dt>Partenaire</dt>
                        <dd>Deux unités qui travaillent ensemble. La liaison est réciproque.</dd>
                        <dt>Rattachement temporaire</dt>
                        <dd>Une unité est rattachée à une autre pour un temps, de préférence avec une date de fin.</dd>
                    </dl>
                    <p>
                        Le chef de l’unité et l’encadrement créent la liaison depuis l’onglet « Unités liées » : unité à lier, type, sens, dates facultatives, précision,
                        et une case pour donner à l’autre unité la lecture de l’espace (lecture seule, sans écriture ni publication). Elle cesse à sa date de fin,
                        ou dès que le chef y met fin ; elle reste visible dans « Échues et terminées ».
                    </p>
                    <ul>
                        <li>Elle ne donne jamais le droit de publier un ordre : on partage une information, pas un ordre.</li>
                        <li>La colonne « Avec les unités liées » de l’onglet « Ordres et échanges » réunit ce que les deux unités se sont partagé.</li>
                        <li>Mon extranet marque d’une étiquette les unités qui ont une liaison active.</li>
                        <li>Une unité liée que vous ne voyez pas dans votre ORBAT s’affiche « Unité liée masquée ».</li>
                    </ul>
                </article>
                <article class="jg-sub" id="liens-contenus" data-jg-sub>
                    <h3>Liens entre contenus</h3>
                    <p>
                        Sous un ordre, une tâche, une page, une opération ou une cible, un bloc « Liés à » rassemble les contenus qui vont ensemble. Le bouton « Lier à… »
                        ouvre une recherche (dès deux lettres) parmi ce que vous pouvez voir : un ordre, une page, une tâche, une opération, une cible, un document,
                        une unité ou un membre. La croix d’un lien le retire.
                    </p>
                    <p>
                        Chaque lien est contrôlé avec vos droits : un objet que vous n’avez pas le droit de voir apparaît sans titre, en « Lien masqué ».
                        Les mentions avec « @ » dans une page fonctionnent de la même façon.
                    </p>
                    <p><a class="jg-btn" href="<?= $u('jnet/liens/nouveau') ?>">Chercher un contenu à lier</a></p>
                </article>
            </section>

            <!-- 6. Outils -->
            <section class="jg-sec" id="outils" aria-labelledby="outils-t" data-jg-sec>
                <h2 id="outils-t">6. Recherche, messagerie et annuaire</h2>
                <article class="jg-sub" id="recherche" data-jg-sub>
                    <h3>Recherche</h3>
                    <p>
                        Dans un espace d’unité, la touche « / » ou Ctrl + K (⌘ + K sur Mac) ouvre la recherche ; « Échap » la referme. Tapez au moins deux lettres :
                        elle parcourt les ordres et échanges, les membres, les tâches, les pages et les documents de l’espace, et ne montre que ce que vous avez le droit de voir.
                        La recherche d’un lien (« Lier à… ») est une autre recherche, qui porte sur toute l’organisation.
                    </p>
                </article>
                <article class="jg-sub" id="messagerie" data-jg-sub>
                    <h3>Messagerie</h3>
                    <p>
                        La messagerie de JNET reçoit les messages d’unité, les rappels et les réponses. L’onglet « Messagerie » affiche le nombre de messages non lus.
                    </p>
                    <p>
                        « Rédiger un message » demande un objet, un niveau d’urgence (Routine, Prioritaire, Immédiat, Flash), les destinataires et le corps du message.
                        Les destinataires se choisissent par groupes (toute l’unité, la chaîne de commandement, une unité de l’ORBAT, une liste enregistrée)
                        et par noms. Une diffusion ne peut pas dépasser 250 destinataires. Les consultations sont journalisées.
                    </p>
                    <p><a class="jg-btn" href="<?= $u('jnet/courrier') ?>">Ouvrir la messagerie</a></p>
                </article>
                <article class="jg-sub" id="annuaire" data-jg-sub>
                    <h3>Annuaire du personnel</h3>
                    <p>
                        Retrouver un camarade, sa fonction, sa section. La page offre un filtre par unité (et un filtre « Indisponibles ») et ouvre la fiche de chaque personne.
                    </p>
                    <p><a class="jg-btn" href="<?= $u('jnet/personnel') ?>">Ouvrir l’annuaire</a></p>
                </article>
                <article class="jg-sub" id="fichiers" data-jg-sub>
                    <h3>Partage de fichiers, opérations et bibliothèque</h3>
                    <p>
                        Les fichiers déposés par l’unité vivent dans le partage de fichiers de la communauté : JNET ne les copie pas, il les présente dans « Photos » et « Documents ».
                        Les opérations ouvertes et les consignes publiées ont leurs pages dans l’extranet.
                    </p>
                    <p>
                        <a class="jg-btn" href="<?= $u('partage') ?>">Partage de fichiers</a>
                        <a class="jg-btn" href="<?= $u('jnet/operations') ?>">Opérations</a>
                        <a class="jg-btn" href="<?= $u('jnet/bibliotheque') ?>">Bibliothèque</a>
                    </p>
                </article>
            </section>

            <!-- 7. Sécurité -->
            <section class="jg-sec" id="securite" aria-labelledby="securite-t" data-jg-sec>
                <h2 id="securite-t">7. Sécurité et confidentialité</h2>
                <article class="jg-sub" id="qui-voit" data-jg-sub>
                    <h3>Ce que voit chaque rôle</h3>
                    <ul>
                        <li>Les membres d’une unité voient ses échanges.</li>
                        <li>L’encadrement au-dessus voit aussi ce qui se passe en dessous.</li>
                        <li>Les unités voisines ne voient rien, sauf liaison active et lecture accordée par le chef.</li>
                        <li>Le chef peut restreindre l’accès à une liste d’unités : l’espace n’est alors ouvert qu’à elles, à l’unité et à son encadrement.</li>
                        <li>Un membre ne voit dans l’organigramme que les unités que sa confidentialité lui laisse voir.</li>
                    </ul>
                    <p>
                        Chaque lien de JNET mène à une page qui refait sa propre vérification côté serveur. Voir un lien ne garantit donc pas de pouvoir l’ouvrir,
                        et un lien masqué ne révèle rien de ce qu’il cache.
                    </p>
                </article>
                <article class="jg-sub" id="renseignement" data-jg-sub>
                    <h3>Le renseignement SSE et ses portes</h3>
                    <p>
                        Le journal de renseignement, les dossiers en suivi (cibles) et l’exploitation s’appuient sur le portail SSE. Pour les lire dans JNET, il faut passer les deux
                        mêmes portes que dans le portail SSE : avoir le droit d’y entrer (membre habilité ou encadrement), puis accepter l’engagement de confidentialité pendant la connexion.
                    </p>
                    <p>
                        Sans ces deux portes, JNET n’en montre rien : il n’ouvre pas ce que le portail protège. Les clichés de terrain protégés restent masqués de la même façon.
                    </p>
                </article>
                <article class="jg-sub" id="journalisation" data-jg-sub>
                    <h3>Journalisation et bonnes pratiques</h3>
                    <ul>
                        <li>L’accès à JNET est réservé aux membres affectés et journalisé ; les consultations de la messagerie le sont aussi.</li>
                        <li>Votre accusé de lecture est horodaté et visible du commandement.</li>
                        <li>Ne publiez dans un espace que ce que tous ses membres et son encadrement peuvent lire.</li>
                        <li>Pour un destinataire précis, préférez la messagerie ; pour une autre unité, « Partager avec une autre unité ».</li>
                        <li>Ne partagez jamais votre session, et déconnectez-vous sur un appareil qui n’est pas le vôtre.</li>
                    </ul>
                </article>
            </section>

            <!-- 8. FAQ -->
            <section class="jg-sec" id="faq" aria-labelledby="faq-t" data-jg-sec>
                <h2 id="faq-t">8. Questions fréquentes</h2>
                <article class="jg-sub" data-jg-sub>
                    <details class="jg-q">
                        <summary>Je ne vois pas JNET</summary>
                        <div>
                            <p>
                                JNET demande un compte rattaché à une organisation réelle. Un compte simple, sans organisation, n’y accède pas : rejoignez ou créez une communauté depuis le tableau de bord.
                                Certains profils de communauté (Effectifs, Carte ATAK) retirent aussi JNET de leurs menus. Si votre communauté le propose et que vous ne le trouvez pas,
                                demandez à votre chef d’unité de vérifier votre affectation.
                            </p>
                        </div>
                    </details>
                    <details class="jg-q">
                        <summary>Je n’ai pas accès à mon unité</summary>
                        <div>
                            <p>
                                Si votre ligne affiche « Lecture seule » au lieu de « Lecture et écriture », vous n’êtes pas affecté à cette unité. Cliquez sur « Demander l’affectation » :
                                la demande passe par la page de votre avancement. Si l’espace a une liste d’accès, vous n’y entrez que si votre unité y figure ; le chef de l’unité peut vous l’ouvrir.
                            </p>
                        </div>
                    </details>
                    <details class="jg-q">
                        <summary>Je ne vois pas un ordre</summary>
                        <div>
                            <p>
                                Un ordre n’apparaît que dans les espaces auxquels il est adressé. Vérifiez que vous êtes dans la bonne unité et dans la colonne « Reçu du commandement »,
                                et que l’ordre ne vise pas une autre sous-unité. Les unités voisines ne voient pas les ordres les unes des autres. Si tout est correct, demandez à l’auteur
                                de vérifier la case « Diffuser à ».
                            </p>
                        </div>
                    </details>
                    <details class="jg-q">
                        <summary>Comment accuser réception d’un ordre ?</summary>
                        <div>
                            <p>
                                Ouvrez « Ordres et échanges » et cliquez sur « Accuser réception » sous l’ordre, ou sur « J’ai pris connaissance » dans l’encadré de l’accueil
                                ou dans la fenêtre « Information importante ». Le compteur « Lu par… » se met à jour pour le commandement.
                            </p>
                        </div>
                    </details>
                    <details class="jg-q">
                        <summary>Comment retrouver l’accueil ?</summary>
                        <div>
                            <p>
                                Dans un espace d’unité, le nom de l’unité en haut à gauche et l’onglet « Accueil » ramènent à l’accueil de l’unité. Le bouton en haut à droite revient à l’espace commun,
                                et le logo Athena (ou « Mon extranet ») à votre registre. « Accueil Athena » quitte JNET pour le tableau de bord.
                            </p>
                        </div>
                    </details>
                    <details class="jg-q">
                        <summary>Je ne peux pas publier</summary>
                        <div>
                            <p>
                                Soit vous consultez l’espace sans en être membre (« Lecture seule »), soit le chef a réservé la publication à ses chefs d’équipe ou à lui seul.
                                Dans les deux cas, adressez-vous au chef de l’unité.
                            </p>
                        </div>
                    </details>
                    <details class="jg-q">
                        <summary>Un onglet a disparu (Tâches, Pages, Photos, Documents)</summary>
                        <div>
                            <p>
                                Le chef peut masquer ces rubriques depuis « Personnaliser ». Leur contenu est conservé : il revient dès qu’elles sont réaffichées.
                                Les onglets « Sous-unités » et « Unités liées » n’apparaissent que s’il y a quelque chose à montrer.
                            </p>
                        </div>
                    </details>
                    <details class="jg-q">
                        <summary>La fenêtre « Information importante » ne se ferme pas</summary>
                        <div>
                            <p>
                                C’est voulu : le chef a demandé la prise en compte obligatoire. Lisez chaque ordre et cliquez sur « J’ai pris connaissance » ;
                                la touche Échap ne la ferme pas.
                            </p>
                        </div>
                    </details>
                    <details class="jg-q">
                        <summary>La recherche ne trouve rien</summary>
                        <div>
                            <p>
                                Tapez au moins deux lettres. La recherche porte sur l’espace où vous êtes, et seulement sur ce que vous avez le droit de voir.
                                Pour retrouver un membre de toute l’organisation, utilisez l’annuaire du personnel.
                            </p>
                        </div>
                    </details>
                    <details class="jg-q">
                        <summary>Où est passé « Personnaliser » ?</summary>
                        <div>
                            <p>Le bouton n’est visible que du chef de l’unité et de l’administration.</p>
                        </div>
                    </details>
                </article>
            </section>

            <!-- 9. Glossaire -->
            <section class="jg-sec" id="glossaire" aria-labelledby="glossaire-t" data-jg-sec>
                <h2 id="glossaire-t">9. Glossaire</h2>
                <article class="jg-sub" data-jg-sub>
                    <dl class="jg-dl jg-dl--gloss">
                        <dt>Unité</dt>
                        <dd>Un groupe de l’organigramme : compagnie, section, équipe… Chaque unité a son espace.</dd>
                        <dt>ORBAT</dt>
                        <dd>L’ordre de bataille : l’organigramme de l’organisation, qui dit qui commande qui.</dd>
                        <dt>Espace commun</dt>
                        <dd>L’espace de toute l’organisation, au-dessus des espaces d’unité.</dd>
                        <dt>Échelon supérieur, subordonné</dt>
                        <dd>L’unité au-dessus de la vôtre dans l’ORBAT, et celles qui en dépendent.</dd>
                        <dt>Ordre</dt>
                        <dd>Un échange qui demande un accusé de lecture à chaque destinataire.</dd>
                        <dt>Accusé de lecture</dt>
                        <dd>La confirmation, horodatée, qu’un destinataire a lu un ordre.</dd>
                        <dt>Prise en compte obligatoire</dt>
                        <dd>Un réglage du chef : la fenêtre qui bloque l’espace tant qu’un ordre n’est pas accusé.</dd>
                        <dt>Relais</dt>
                        <dd>La transmission d’un ordre reçu aux sous-unités, à la main ou automatiquement.</dd>
                        <dt>Remonter</dt>
                        <dd>Envoyer un compte rendu ou un renseignement vers l’échelon supérieur.</dd>
                        <dt>Liaison</dt>
                        <dd>Un lien entre deux unités hors filiation (appui, partenaire, rattachement temporaire), ou entre deux contenus (« Lier à… »).</dd>
                        <dt>Chef de module</dt>
                        <dd>Un membre désigné par le chef pour modérer les tâches et les pages de l’espace.</dd>
                        <dt>Droit</dt>
                        <dd>Ce que vous pouvez faire dans un espace : Administration, Chef de l’unité, Lecture et écriture, Lecture seule.</dd>
                        <dt>SSE</dt>
                        <dd>Le portail de renseignement et de suivi d’Athena, protégé par un droit d’entrée et un engagement de confidentialité.</dd>
                    </dl>
                </article>
            </section>
        </div>
    </div>
</div>
