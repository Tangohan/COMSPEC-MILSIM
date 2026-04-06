<?php
declare(strict_types=1);
?>
<section id="introduction" class="site-docs__section">
    <h2>Introduction</h2>
    <p>
        Le portail <strong>Athena</strong> centralise la vie de votre communauté : effectifs, briefings, formations, documents officiels,
        événements et outils tactiques. Ce guide explique notamment <strong>qui peut faire quoi</strong> (permissions et rôles),
        comment une formation passe du <strong>brouillon</strong> à la <strong>publication</strong>, et comment fonctionnent la <strong>modération</strong>
        et les <strong>fiches personnel</strong> (y compris les profils métier type « CV opérationnel »).
    </p>
    <p>
        Si une page ou un bouton n’apparaît pas pour vous, c’est en général normal : votre rôle ne couvre pas cette fonctionnalité.
        En cas de doute, demandez à un référent de votre communauté.
    </p>
    <div class="site-docs__callout site-docs__callout--tip">
        <strong>À retenir.</strong> Gardez votre adresse courriel à jour : elle sert à confirmer les actions sensibles et à recevoir les rappels importants.
    </div>
</section>

<section id="compte-et-securite" class="site-docs__section">
    <h2>Compte &amp; sécurité</h2>
    <h3>Connexion et mot de passe</h3>
    <p>
        La page de connexion vous demande vos identifiants habituels. Si vous avez oublié votre mot de passe, utilisez le lien prévu à cet effet :
        un message vous est envoyé pour choisir un nouveau mot de passe de manière sécurisée.
    </p>
    <h3>Adresse courriel et vérification</h3>
    <p>
        Lors de l’inscription ou du changement d’adresse, le site peut vous demander de <strong>confirmer</strong> votre courriel en cliquant sur un lien
        reçu dans votre boîte. Tant que ce n’est pas fait, certaines actions peuvent rester limitées.
    </p>
    <h3>Profil et préférences</h3>
    <p>
        Depuis votre espace personnel, vous pouvez compléter votre profil (informations visibles selon les règles de la communauté) et régler des
        <strong>préférences</strong> : notifications, affichage public de certaines données, etc.
    </p>
    <h3>Alertes de sécurité</h3>
    <p>
        En cas de connexion inhabituelle ou de plusieurs tentatives échouées, le site peut vous envoyer une alerte ou afficher un message.
        Ne partagez jamais vos identifiants et déconnectez-vous sur les postes partagés.
    </p>
</section>

<section id="communaute-et-contexte" class="site-docs__section">
    <h2>Communauté &amp; contexte</h2>
    <p>
        Le portail peut héberger plusieurs communautés. Votre session est associée à <strong>l’organisation active</strong> : les documents, le forum,
        les formations et les effectifs affichés correspondent à cette organisation.
    </p>
    <p>
        Si vous participez à plusieurs communautés sur la même plateforme, le changement de contexte (lorsqu’il est proposé) adapte tout le contenu
        : menus, listes et droits peuvent changer après un basculement.
    </p>
</section>

<section id="etapes-de-mise-en-service" class="site-docs__section">
    <h2>Étapes de mise en service</h2>
    <p>
        Voici un <strong>parcours type</strong> pour une unité qui déploie le portail (l’ordre peut varier selon votre organisation).
    </p>
    <ol>
        <li><strong>Création ou rattachement de la communauté</strong> — La structure (nom, identité) est configurée ; les premiers administrateurs reçoivent les habilitations de pilotage.</li>
        <li><strong>Rôles et invitations</strong> — L’état-major définit les rôles (voir les sections dédiées), envoie les invitations et valide les comptes.</li>
        <li><strong>Forum et documents</strong> — Mise en place des catégories de discussion, des règles de publication, puis des dossiers documentaires et des niveaux d’accès.</li>
        <li><strong>Formations</strong> — Création des parcours dans l’espace d’édition (Studio), construction des modules et leçons, relecture, puis passage à l’état <strong>publié</strong> pour le catalogue.</li>
        <li><strong>Effectifs</strong> — Saisie ou import des fiches personnel, affectations, ORBAT ; le cas échéant, ouverture du recrutement et du dossier opérateur.</li>
        <li><strong>Exploitation</strong> — Événements, pointage, briefings, messagerie interne : la vie courante repose sur les modules déjà configurés.</li>
    </ol>
    <div class="site-docs__callout">
        <strong>Remarque.</strong> Certaines étapes nécessitent des droits élevés (publication de formations, gestion documentaire, administration de l’organisation).
        Les membres « simples » accèdent au catalogue et aux espaces ouverts une fois la communauté prête.
    </div>
</section>

<section id="droits-permissions-et-roles" class="site-docs__section">
    <h2>Droits, permissions &amp; rôles</h2>
    <h3>Principe</h3>
    <p>
        Le site distingue ce que <strong>vous pouvez faire</strong> (permissions concrètes : voir un document, publier un message, assigner une formation…)
        de la manière dont ces permissions sont <strong>regroupées</strong> et attribuées (rôles, profils prédéfinis).
    </p>
    <h3>Deux grands périmètres</h3>
    <ul>
        <li><strong>Organisation (communauté)</strong> — La plupart des actions du quotidien (forum, personnel, formations, documents, invitations, back-office de l’unité) sont limitées à <em>votre</em> communauté active.</li>
        <li><strong>Plateforme entière</strong> — Un petit nombre de réglages transverses (supervision globale, maintenance, audit) est réservé aux administrateurs de la plateforme, hors périmètre d’une unité classique.</li>
    </ul>
    <h3>Permissions métier (exemples courants)</h3>
    <p>
        Les libellés exacts peuvent varier, mais on retrouve souvent des familles de droits : <strong>forum</strong> (lire, écrire, modérer au sein de l’organisation),
        <strong>documents</strong> (consulter, télécharger, déposer, gérer les accès), <strong>formations</strong> (suivre un parcours, être assigné, noter des rendus, gérer le contenu),
        <strong>personnel</strong> (consulter ou modifier des fiches, informations sensibles, grades, affectations), <strong>invitations et membres</strong>, etc.
    </p>
    <h3>Rôles et profils prédéfinis</h3>
    <p>
        Pour éviter de cocher des dizaines de cases à la main, l’administration peut appliquer des <strong>profils types</strong> (membre actif, instructeur, RH, modérateur organisation, gestion documentaire, commandement, etc.).
        Chaque profil active un ensemble cohérent de permissions ; des ajustements fins restent possibles pour un compte précis.
    </p>
    <div class="site-docs__callout site-docs__callout--tip">
        <strong>Publication des formations.</strong> Passer une formation en « publié » peut être réservé à certains rôles : si le menu existe mais que l’action est refusée,
        c’est en général une permission « publication » ou « gestion LMS » qui manque, pas un bug.
    </div>
</section>

<section id="roles-communaute-et-metiers" class="site-docs__section">
    <h2>Rôles communauté &amp; métiers</h2>
    <p>
        Chaque communauté dispose de <strong>rôles système</strong> aux intitulés métier (milsim / unité). Ils servent à la gouvernance et au quotidien.
        La liste ci-dessous décrit l’esprit de chaque rôle ; les <strong>permissions effectives</strong> sont celles effectivement attachées au rôle dans votre espace.
    </p>
    <h3>Gouvernance</h3>
    <ul>
        <li><strong>Fondateur</strong> — Vision et validation stratégique de la communauté ; ne confère pas à lui seul l’administration technique de toute la plateforme.</li>
        <li><strong>État-major</strong> — Direction courante : effectifs, structure, rôles, invitations, modération au niveau organisationnel et paramètres de l’unité.</li>
    </ul>
    <h3>Rôles opérationnels (exemples livrés par défaut)</h3>
    <ul>
        <li><strong>Opérateur</strong> — Membre titulaire : forum, documents standards et formations selon affectation.</li>
        <li><strong>Cadre</strong> — Encadrement, coordination d’équipe, visibilité renforcée sur les ressources utiles au commandement.</li>
        <li><strong>Modérateur forum</strong> — Modération des espaces de discussion de l’unité (épinglage, signalements, catégories) — périmètre organisation, pas « site entier ».</li>
        <li><strong>RH (S1)</strong> — Dossiers personnel, grades et suivi administratif des effectifs.</li>
        <li><strong>Recruteur</strong> — Pipeline recrutement, échanges avec les candidats, liaison avec le commandement.</li>
        <li><strong>Visiteur</strong> — Accès limité en attente d’intégration ou compte prospect (lecture ciblée).</li>
        <li><strong>Instructeur</strong> — Pôle formation : parcours, assignations, correction des rendus et suivi des qualifications.</li>
        <li><strong>OPSAN</strong> — Santé / secours : visibilité sur les informations médicales autorisées lorsque la politique de l’unité le permet.</li>
        <li><strong>Logistique</strong> — Soutien matériel, fiches équipement et documentation associée.</li>
        <li><strong>R2 (transmissions)</strong> — Diffusion d’informations officielles et coordination des annonces.</li>
        <li><strong>Période d’essai</strong> — Participation encadrée au forum en attendant la titularisation.</li>
    </ul>
    <p>
        Des <strong>profils prédéfinis de permissions</strong> (membre actif, instructeur, RH &amp; recrutement, modérateur organisation, gestion documentaire, commandement d’unité, pôle formation, etc.)
        permettent d’aligner rapidement un compte sur une fonction sans repartir de zéro.
    </p>
</section>

<section id="navigation-et-recherche" class="site-docs__section">
    <h2>Navigation &amp; recherche</h2>
    <h3>Menus et raccourcis</h3>
    <p>
        La barre de navigation regroupe les zones principales : accueil, tableau de bord, opérations, ressources, personnel, formations, administration
        (selon vos droits). Les <strong>méga-menus</strong> regroupent des liens thématiques : survolez ou cliquez pour explorer sans quitter la page courante.
    </p>
    <h3>Recherche globale</h3>
    <p>
        Une <strong>recherche portail</strong> permet de trouver rapidement des modules, des fiches personnel, des documents ou d’autres éléments indexés.
        Selon votre configuration, un raccourci clavier peut ouvrir la recherche sans utiliser la souris.
    </p>
    <div class="site-docs__callout">
        <strong>Astuce.</strong> Commencez par un mot-clé court puis affinez si trop de résultats apparaissent.
    </div>
</section>

<section id="tableau-de-bord" class="site-docs__section">
    <h2>Tableau de bord</h2>
    <p>
        Le tableau de bord résume l’essentiel : rappels, liens utiles, dossiers à suivre pour les équipes habilitées.
        Les administrateurs peuvent proposer des <strong>raccourcis</strong> vers des pages fréquentes (back-office, modération, etc.).
    </p>
    <p>
        Des widgets peuvent afficher l’état des recrutements en attente, des messages importants ou des liens vers les modules que vous utilisez le plus souvent.
    </p>
</section>

<section id="personnel-et-orbat" class="site-docs__section">
    <h2>Personnel, ORBAT &amp; profils métier</h2>
    <h3>Fiches et affectations</h3>
    <p>
        L’espace personnel permet de consulter les fiches des membres (dans la mesure autorisée), les affectations, les grades ou statuts définis par votre organisation.
        Les habilitations distinguent souvent la <strong>lecture</strong>, la <strong>mise à jour</strong> de sa propre fiche, et la <strong>gestion</strong> des fiches d’autrui (RH, cadres).
    </p>
    <h3>ORBAT</h3>
    <p>
        L’<strong>ordre de bataille</strong> présente la structure hiérarchique des unités et des postes. Il sert de référence pour la coordination et les rôles sur le terrain.
    </p>
    <h3>Complétude et « CV opérationnel »</h3>
    <p>
        La fiche peut inclure qualifications, parcours de formation interne, et champs de synthèse (fonction principale, spécialités, équipement, transmissions, etc.).
        Selon les règles de votre communauté, un <strong>indicateur de complétude</strong> peut rappeler les champs manquants pour une fiche exploitable par l’état-major ou les RH.
    </p>
    <h3>Profils métier et presets</h3>
    <p>
        Pour accélérer la saisie, des <strong>modèles de profil métier</strong> (officier opérations, chef de section, fusilier, JTAC, medic, etc.) peuvent être proposés :
        ils pré-remplissent des postes types, dotations radio, classes d’équipement ou spécialités d’armes, toujours <strong>ajustables</strong> au cas par cas.
        Ce ne sont pas des « statuts » automatiques : ils aident à aligner la fiche sur le rôle réel dans l’unité.
    </p>
</section>

<section id="dossier-operateur" class="site-docs__section">
    <h2>Dossier opérateur</h2>
    <p>
        Le <strong>dossier opérateur</strong> regroupe les informations nécessaires à l’accréditation ou à la validation interne (parcours, validations, pièces attendues).
        Le contenu exact dépend des exigences de votre organisation.
    </p>
    <p>
        Complétez les champs demandés, vérifiez les pièces jointes autorisées et soumettez le dossier selon la procédure indiquée sur l’écran.
        Les instructeurs ou référents traitent ensuite les demandes dans les délais définis par votre unité.
    </p>
</section>

<section id="forum-et-briefings" class="site-docs__section">
    <h2>Forum &amp; briefings</h2>
    <h3>Catégories et sujets</h3>
    <p>
        Le forum est organisé en <strong>catégories</strong> (briefings, annonces, discussions, etc.). Chaque catégorie contient des <strong>sujets</strong> ;
        dans chaque sujet, les messages s’enchaînent selon le tri choisi.
    </p>
    <h3>Publication et pièces jointes</h3>
    <p>
        Pour créer un sujet ou répondre, utilisez l’éditeur prévu. Certaines communautés autorisent des <strong>fichiers joints</strong> (images, documents) avec des limites de taille et de type.
        Respectez le règlement intérieur et le droit d’auteur.
    </p>
    <p>
        La modération (signalements, épinglage, verrouillage) est détaillée dans la section <a href="#moderation-forum">Modération &amp; signalements</a>.
    </p>
</section>

<section id="moderation-forum" class="site-docs__section">
    <h2>Modération &amp; signalements</h2>
    <h3>Qui modère ?</h3>
    <p>
        Deux idées se combinent : des personnes désignées par votre communauté modèrent <strong>les contenus de l’unité</strong>, tandis qu’un niveau « plateforme »
        peut exister pour les équipes techniques globales — ce dernier ne concerne pas le quotidien d’une unité classique.
    </p>
    <h3>Modération au sein de l’organisation</h3>
    <p>
        Avec les habilitations adaptées, les modérateurs peuvent <strong>épingler</strong> des sujets importants, <strong>verrouiller</strong> un fil,
        agir sur les messages qui enfreignent le règlement et utiliser les outils d’administration des catégories prévus pour votre communauté.
    </p>
    <h3>Signalements</h3>
    <p>
        Tout membre peut en principe <strong>signaler</strong> un contenu problématique (harcèlement, spam, fuite d’information, etc.).
        Les signalements en attente apparaissent dans la console de modération ; un modérateur examine le contexte, peut intervenir sur le fil et marque le signalement comme traité lorsque la situation est réglée.
    </p>
    <h3>Bon réflexe</h3>
    <p>
        Privilégiez le signalement et les canaux officiels plutôt qu’une « riposte » publique sur le forum, qui complique la modération et l’image de l’unité.
    </p>
</section>

<section id="formations" class="site-docs__section">
    <h2>Formations (LMS &amp; Studio)</h2>
    <h3>Côté apprenant</h3>
    <p>
        Le <strong>catalogue</strong> liste les parcours auxquels vous avez accès : consultation libre, inscription selon règles, ou <strong>assignation</strong> par un formateur ou l’administration.
        Une fois inscrit, votre <strong>progression</strong> est enregistrée ; les parcours peuvent imposer un score minimal ou des leçons obligatoires selon la configuration de la formation.
    </p>
    <h3>Création : le Studio</h3>
    <p>
        Les équipes habilitées créent les formations dans l’espace d’édition (Studio) : <strong>fiche</strong> de la formation (titre, résumé, niveau, durée indicative, objectifs),
        puis structuration en <strong>modules</strong> et <strong>leçons</strong>. Chaque leçon a un type de contenu (texte enrichi, vidéo, fichier, audio, parcours type liste de contrôle, lien externe, parcours visuel type présentation, etc.).
    </p>
    <h3>États de visibilité d’une formation</h3>
    <p>
        La fiche formation porte un <strong>état de visibilité</strong> qui pilote qui la voit et où elle apparaît :
    </p>
    <ul>
        <li><strong>Brouillon</strong> — Travail en cours ; réservé aux auteurs et à l’équipe pédagogique dans l’outil d’édition. N’apparaît pas comme parcours public dans le catalogue pour les apprenants.</li>
        <li><strong>Privé</strong> — Contenu stabilisé mais non proposé au catalogue grand public : utile pour des parcours internes, des tests avec un groupe restreint ou des sessions sur invitation.</li>
        <li><strong>Publié</strong> — La formation est éligible au <strong>catalogue</strong> visible par les apprenants autorisés (sous réserve des règles d’accès et d’assignation).</li>
        <li><strong>Archivé</strong> — Plus proposé comme formation active ; conserve l’historique et les données de suivi pour rapport ou audit sans la présenter comme offre courante.</li>
    </ul>
    <p>
        Le passage à <strong>publié</strong> peut être soumis à une <strong>permission dédiée</strong> : seuls certains rôles valident la mise en ligne.
    </p>
    <h3>Parcours type après publication</h3>
    <ol>
        <li>Rédaction et assemblage des leçons, relecture en brouillon ou en privé.</li>
        <li>Validation interne puis bascule vers <strong>publié</strong> lorsque le contenu est prêt.</li>
        <li><strong>Assignation</strong> manuelle ou auto-inscription selon les règles du parcours.</li>
        <li>Suivi des complétions, éventuellement <strong>notation</strong> de rendus ou délivrance de certifications si la formation est configurée ainsi.</li>
    </ol>
    <h3>Niveaux et métadonnées</h3>
    <p>
        Les formations sont souvent étiquetées par <strong>niveau</strong> (initiation à expert) et catégorie pour faciliter le filtrage du catalogue.
    </p>
    <h3>Traçabilité côté administration</h3>
    <p>
        Les personnes chargées du <strong>pôle formation</strong> ou de la <strong>conformité</strong> peuvent disposer d’un <strong>journal d’audit</strong> listant les actions sensibles
        sur les parcours : création ou modification importante, changement de visibilité, publication, assignation à des membres, etc.
    </p>
    <p>
        Chaque ligne indique <strong>quand</strong> l’action a eu lieu, <strong>qui</strong> l’a effectuée, une <strong>formulation claire</strong> du type d’action, l’<strong>objet</strong> concerné
        (formation, module ou leçon selon le cas), le <strong>référent pédagogique</strong> associé au parcours lorsque c’est pertinent, et un <strong>résumé</strong> du détail utile au contrôle interne.
        Si vous ne voyez pas cet écran, votre rôle ne couvre pas la supervision pédagogique ou l’audit.
    </p>
    <div class="site-docs__callout site-docs__callout--tip">
        <strong>À retenir.</strong> Ce journal sert à l’<strong>explicabilité</strong> en interne (qui a publié quoi, quand) ; il ne remplace pas les rapports pédagogiques ou les exports statistiques s’ils existent ailleurs dans l’outil.
    </div>
</section>

<section id="documents" class="site-docs__section">
    <h2>Documents</h2>
    <p>
        L’espace documentaire centralise les fichiers officiels : ordres, modèles, procédures, etc. L’accès dépend des <strong>permissions</strong> :
        certains dossiers sont réservés aux cadres ou à des unités précises (y compris documents sensibles ou grand public selon les réglages).
    </p>
    <h3>Consultation et dépôt</h3>
    <p>
        La plupart des membres <strong>consultent</strong> et téléchargent les documents autorisés. Le <strong>dépôt</strong>, les versions, les métadonnées et les droits d’accès
        sont en général réservés aux rôles « gestion documentaire » ou équivalent.
    </p>
</section>

<section id="courrier-officiel" class="site-docs__section">
    <h2>Courrier officiel</h2>
    <p>
        Le <strong>bureau courrier</strong> permet de suivre les courriers entrants et sortants, les statuts de traitement et les éventuelles actions attendues.
        Si vous n’avez pas accès à ce module, c’est que votre fonction n’y est pas associée.
    </p>
    <p>
        Utilisez les filtres et les étiquettes proposées pour prioriser les dossiers urgents ou en attente de réponse.
    </p>
</section>

<section id="evenements-messages-pointage" class="site-docs__section">
    <h2>Événements, messages &amp; pointage</h2>
    <h3>Événements communautaires</h3>
    <p>
        Le calendrier ou la liste d’<strong>événements</strong> annonce les activités collectives : entraînements, briefings, opérations simulées, etc.
        Vous pouvez souvent confirmer votre présence ; des rappels par courriel peuvent être envoyés automatiquement.
    </p>
    <h3>Messages internes</h3>
    <p>
        Les <strong>messages</strong> liés à la communauté permettent d’échanger des informations courtes sans passer par le forum public.
    </p>
    <h3>Pointage</h3>
    <p>
        Le <strong>pointage</strong> enregistre les présences ou les passages selon les règles définies par votre organisation.
    </p>
</section>

<section id="equipement-et-modpacks" class="site-docs__section">
    <h2>Équipement &amp; modpacks</h2>
    <p>
        Les pages <strong>équipement</strong> et <strong>modpacks</strong> décrivent le matériel virtuel ou les packs de mods validés pour les sessions.
        Elles servent de référence unique pour aligner les joueurs sur les mêmes versions.
    </p>
    <p>
        Avant une mission, vérifiez la liste des mods obligatoires et les mises à jour publiées par les équipes techniques.
    </p>
</section>

<section id="outils-cartes-et-tactique" class="site-docs__section">
    <h2>Outils, cartes &amp; tactique</h2>
    <p>
        Le <strong>hub</strong> opérationnel regroupe les accès rapides vers les modules tactiques : briefings, cartes, outils de coordination.
        Selon votre communauté, vous trouverez des liens vers des cartes interactives ou des applications externes.
    </p>
    <h3>Coordination</h3>
    <p>
        Utilisez ces outils pour préparer les déplacements et suivre une vue d’ensemble. Les droits d’accès peuvent limiter certaines informations.
    </p>
</section>

<section id="recrutement-et-enrolement" class="site-docs__section">
    <h2>Recrutement &amp; enrôlement</h2>
    <h3>Côté candidat</h3>
    <p>
        Le parcours d’<strong>enrôlement</strong> permet de soumettre un dossier via le formulaire prévu par votre communauté, puis de suivre l’état de la demande depuis votre espace lorsque c’est activé.
        Après envoi, un message de confirmation vous indique que la candidature est bien enregistrée.
    </p>
    <p>
        Soyez précis dans vos réponses : les validateurs s’appuient sur ces informations pour vous orienter vers la bonne unité.
    </p>
    <h3>Côté staff : la liste des dossiers</h3>
    <p>
        Les membres habilités ouvrent la <strong>liste des dossiers de candidature</strong> depuis le back-office : chaque ligne correspond à une demande reçue.
        Les filtres ou tris éventuels permettent de prioriser les dossiers à traiter.
    </p>
    <h3>Ouvrir un dossier</h3>
    <p>
        En ouvrant un dossier, vous arrivez sur une <strong>fiche structurée</strong> : un fil d’orientation en tête de page vous indique que vous consultez un dossier individuel et permet de <strong>revenir à la liste</strong>.
        Un bandeau visuel rappelle le <strong>statut</strong> affiché pour ce dossier, par exemple : <em>à traiter</em>, <em>acceptée</em>, <em>refusée</em> ou <em>non admis</em> — les libellés à l’écran font foi.
    </p>
    <p>
        Le contenu est regroupé en <strong>rubriques</strong> (identité et réception, éléments du formulaire, pièces ou champs spécifiques selon le modèle utilisé).
        Lorsque le candidat s’est identifié avec un <strong>compte portail</strong>, un lien permet d’ouvrir directement la <strong>fiche membre</strong> associée pour croiser les informations sans quitter le flux de recrutement.
        Pour une candidature déposée sans compte au moment du dépôt, l’écran l’indique clairement.
    </p>
    <h3>Instruction et échanges</h3>
    <p>
        Lorsqu’une décision ou un commentaire a été enregistré, la zone <strong>instruction du dossier</strong> peut afficher la date, une note laissée par le traitant et l’historique utile à la relecture.
        Des <strong>messages prédéfinis</strong> peuvent être proposés pour accélérer les réponses standard aux candidats, selon la configuration de votre unité.
    </p>
</section>

<section id="alertes-et-annonces" class="site-docs__section">
    <h2>Alertes &amp; annonces</h2>
    <p>
        Le site peut afficher des <strong>bandeaux</strong> ou des messages temporaires (maintenance, consigne urgente).
        Les <strong>messages flash</strong> confirment une action réussie ou signalent une erreur juste après une opération.
    </p>
</section>

<section id="pilotage-organisation" class="site-docs__section">
    <h2>Pilotage d’organisation</h2>
    <p>
        Les personnes chargées de l’<strong>administration de communauté</strong> accèdent au back-office pour gérer les membres, les rôles, les invitations,
        les événements, la modération, la configuration du forum, des documents et des formations (selon les habilitations).
    </p>
    <h3>Rôles et permissions</h3>
    <p>
        La création ou l’édition de rôles relie des <strong>permissions</strong> précises à des intitulés de poste. Les <strong>presets</strong> accélèrent l’attribution d’un jeu cohérent de droits ;
        évitez d’attribuer « tout » par défaut : appliquez le principe du moindre privilège.
    </p>
    <h3>Plateforme globale</h3>
    <p>
        Un périmètre distinct existe pour les <strong>administrateurs de la plateforme entière</strong> (paramètres transverses, maintenance, audit).
        Ce niveau ne remplace pas la gouvernance interne de votre unité (état-major, fondateur).
    </p>
</section>

<section id="bonnes-pratiques" class="site-docs__section">
    <h2>Bonnes pratiques</h2>
    <ul>
        <li><strong>Actualisez votre profil</strong> lorsque votre affectation ou vos moyens de contact changent.</li>
        <li><strong>Vérifiez la communauté active</strong> avant de publier un message ou un document sensible.</li>
        <li><strong>Utilisez le canal adapté</strong> : forum pour l’information durable, messages pour l’échange rapide.</li>
        <li><strong>Protégez les données</strong> : ne copiez pas d’informations nominatives hors des espaces autorisés.</li>
        <li><strong>Signalez les anomalies</strong> à un référent plutôt que de contourner les règles.</li>
        <li><strong>Formations</strong> : testez en brouillon ou en privé avant de publier ; vérifiez les assignations après mise en ligne.</li>
    </ul>
    <div class="site-docs__callout site-docs__callout--tip">
        <strong>Évolutions.</strong> Les libellés à l’écran et les workflows peuvent être ajustés par votre organisation : en cas d’écart avec ce guide,
        les consignes internes et l’interface font foi.
    </div>
</section>
