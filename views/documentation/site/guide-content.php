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

<section id="cooperations-inter-unites" class="site-docs__section">
    <h2>Coopérations inter-unités</h2>
    <p>
        Ce module sert à <strong>organiser une collaboration officielle entre deux communautés ou plus</strong> sur la même plateforme Athena,
        lorsqu’elles doivent se coordonner sur un même sujet opérationnel (brief commun, partage d’informations encadré, réunions, suivi d’engagement).
        Il ne remplace ni le forum général de votre unité ni les canaux externes : il fournit un <strong>dossier dédié</strong>, une <strong>chronologie</strong> et un
        <strong>espace de discussion lié au brief partagé</strong>, avec des règles d’accès explicites pour limiter les malentendus et tracer les décisions importantes.
    </p>
    <div class="site-docs__callout site-docs__callout--tip">
        <strong>Important.</strong> Si vous ne voyez aucun menu « Coopérations inter-unités », votre compte n’a tout simplement pas l’habilitation correspondante dans la communauté active.
        Demandez à un responsable de l’unité de vous attribuer le rôle ou la permission adaptée, ou de confirmer que la fonction est ouverte aux membres comme vous.
    </div>

    <h3>Où ouvrir le module dans le portail</h3>
    <p>
        L’accès principal se fait depuis le <strong>back-office de votre communauté</strong>, dans la zone regroupant les outils métier (souvent intitulée « Ressources » ou « Ressources &amp; outils » selon l’écran).
        Repérez l’entrée <strong>Coopérations inter-unités</strong> : elle mène à la <strong>liste des dossiers</strong> auxquels vous participez ou que vous pilotez.
    </p>
    <p>
        Selon votre profil, un <strong>raccourci</strong> peut aussi apparaître depuis le <strong>tableau de bord</strong> du portail, dans le menu latéral des ressources rapides, ou depuis les raccourcis d’administration de la communauté lorsque vous gérez plusieurs modules.
        Si vous aviez conservé d’anciens favoris pointant vers une ancienne adresse du module, le site peut vous <strong>rediriger automatiquement</strong> vers la zone actuelle : le contenu reste le même, seul le chemin affiché dans la barre d’adresse peut différer.
    </p>

    <h3>Idées clés à comprendre avant de commencer</h3>
    <ul>
        <li><strong>Communauté à l’initiative (souvent dite « unité support »)</strong> — C’est la communauté qui propose la coopération, en rédige la proposition initiale et, en général, héberge l’espace commun (fil de discussion partagé) une fois la coopération ouverte.</li>
        <li><strong>Communautés partenaires</strong> — Ce sont les autres unités invitées à rejoindre le dossier. Elles peuvent accepter, refuser, négocier, puis participer aux échanges une fois les conditions réunies.</li>
        <li><strong>Dossier de coopération</strong> — L’ensemble structuré : proposition, invitations, journal, onglets (réunion, structures, clôture…). Pensez-y comme à un <em>dossier vivant</em> qui avance par étapes.</li>
        <li><strong>Phase ou état du dossier</strong> — Indique où vous en êtes (proposition en discussion, coopération en cours, clôturée, etc.). Les intitulés à l’écran sont volontairement métier ; l’interface grise ou masque les actions impossibles à l’étape courante.</li>
        <li><strong>Espace commun (fil lié au brief)</strong> — Une discussion dédiée au dossier, distincte des fils généraux du forum. Elle sert aux échanges opérationnels autorisés dans le cadre fixé par les unités.</li>
        <li><strong>Journal (chronologie)</strong> — Liste horodatée des événements marquants : invitations, validations, ouvertures, messages notables liés au processus, etc. Elle aide tout le monde à se synchroniser sans relire tout l’historique du fil.</li>
        <li><strong>Autorisation de partage</strong> — Étape où chaque participant <strong>confirme explicitement</strong>, pour son compte, ce qu’il accepte de partager dans ce cadre. Un <strong>code de confirmation</strong> est en général envoyé sur l’adresse courriel du compte pour renforcer la sécurité.</li>
    </ul>

    <h3>Parcours complet, de la proposition à l’après-clôture</h3>
    <p>
        Le détail des boutons peut varier légèrement selon votre rôle, mais la <strong>logique générale</strong> reste la suivante.
    </p>

    <h4>1. Lancer une nouvelle coopération</h4>
    <p>
        Depuis la liste, utilisez l’action de <strong>création</strong>. Vous saisissez un <strong>titre</strong> clair (visible par les partenaires), choisissez une <strong>typologie</strong> (entraînement conjoint, appui, échange d’information, etc.) et un <strong>niveau de priorité</strong> lorsque ces champs sont proposés.
        La <strong>date limite pour répondre à la proposition</strong>, si vous la renseignez, sert de repère pour tout le monde : au-delà, l’état-major sait qu’une relance ou une décision s’impose.
    </p>
    <p>
        Certaines organisations utilisent aussi des <strong>conditions suspensives</strong> : il s’agit de points qui doivent être levés (validation interne, disponibilité d’un moyen, accord d’un tiers) avant de considérer que la coopération peut passer à l’étape suivante.
        Rédigez-les comme des phrases compréhensibles par un lecteur extérieur à votre unité ; évitez le jargon interne non expliqué.
    </p>

    <h4>2. Inviter d’autres communautés</h4>
    <p>
        Le pilote du dossier désigne les <strong>communautés partenaires</strong> à associer. Chaque invitation est notée dans le <strong>journal</strong>.
        Les partenaires voient alors la demande dans leur propre liste de coopérations et reçoivent les indications nécessaires pour comprendre l’objet et l’échéance.
    </p>

    <h4>3. Accepter ou refuser</h4>
    <p>
        Côté partenaire, l’acceptation signifie « nous entrons dans le cadre de discussion prévu ». Le refus clôt la participation de cette communauté à ce dossier précis, sans préjuger d’autres collaborations futures.
        Tant que toutes les parties requises n’ont pas accepté, la coopération ne peut pas avancer vers l’ouverture de l’espace commun.
    </p>

    <h4>4. Négociation et contre-propositions</h4>
    <p>
        Si le cadre initial ne convient pas entièrement, une communauté partenaire peut formuler une <strong>contre-proposition</strong> structurée (calendrier, périmètre, modalités de coordination, etc.).
        L’unité à l’initiative peut <strong>accepter</strong>, <strong>refuser</strong> ou ajuster la proposition principale. Chaque va-et-vient est consigné pour que personne ne doute de la version « officielle » en vigueur à un instant donné.
    </p>

    <h4>5. Autorisation de partage (consentement personnel)</h4>
    <p>
        Avant ou pendant l’activation selon le workflow de votre plateforme, chaque participant concerné doit passer par l’écran d’<strong>autorisation de partage</strong>.
        Vous cochez des <strong>familles d’informations</strong> (brief, organigramme, qualifications, documents de séance, etc. — les libellés exacts dépendent de la configuration).
        Pour les partages jugés sensibles, l’interface peut demander une <strong>courte justification</strong> : elle aide les référents à comprendre le contexte sans remplacer une instruction officielle sur le secret opérationnel.
    </p>
    <p>
        Un <strong>code à usage limité dans le temps</strong> est envoyé sur votre courriel : saisissez-le sur la page indiquée. En cas d’échec répété, attendez le délai affiché avant de redemander un code, pour éviter le blocage automatique du compte sur cette étape.
        Si vous ne recevez rien, vérifiez les courriers indésirables et la bonne adresse associée à votre compte.
    </p>

    <h4>6. Activation et « instantané » de contexte</h4>
    <p>
        L’activation marque le passage à la <strong>coopération en cours</strong>. À ce moment, le système peut enregistrer un <strong>instantané de contexte</strong> : qui participait, quelles grandes lignes de la proposition étaient actives, éléments de structure ou de coordination déjà saisis.
        Cet instantané sert de <strong>référence</strong> si, plus tard, un différend apparaît sur « ce qui avait été acté au départ ». Ce n’est pas une garantie juridique externe au site, mais un outil de clarté interne entre unités.
    </p>
    <p>
        Le <strong>fil commun</strong> est alors disponible (sous réserve des droits individuels). Un <strong>message d’accueil</strong> peut être généré automatiquement pour rappeler l’objet, les unités engagées, les liens utiles vers la réunion ou les structures, et les règles de bon usage.
    </p>

    <h4>7. Pendant la coopération en cours</h4>
    <p>
        Les onglets du dossier couvrent les besoins courants :
    </p>
    <ul>
        <li><strong>Synthèse</strong> — Vue d’ensemble : état, partenaires, raccourcis vers les autres onglets, actions possibles pour votre rôle (inviter, promouvoir un co-pilote, assigner des rôles de dossier à des membres de votre communauté, etc.).</li>
        <li><strong>Proposition</strong> — Ajustement des paramètres initiaux tant que le cadre le permet (typologie, priorité, délais, conditions suspensives).</li>
        <li><strong>Négociation</strong> — Suivi des contre-propositions en cours ou passées.</li>
        <li><strong>Espace commun</strong> — Accès au fil de discussion partagé et, le cas échéant, à la visioconférence temporaire proposée par la plateforme.</li>
        <li><strong>Autorisation de partage</strong> — Retour sur l’écran de consentement si vous devez mettre à jour ou renouveler votre accord (selon politique de votre organisation).</li>
        <li><strong>Chronologie</strong> — Journal détaillé avec filtrage par type d’événement ; les pilotes peuvent parfois exporter une copie pour archivage interne hors site.</li>
        <li><strong>Réunion</strong> — Planification d’une réunion (titre, ordre du jour, date prévue, participants attendus), enregistrement d’une réunion « notée dans le journal », lien vers un compte rendu ou une rediffusion si votre unité en fournit une.</li>
        <li><strong>Structures &amp; liaisons</strong> — Points de contact, fréquences, textes libres pour la coordination cartographique présentés sous forme de libellés opérationnels (sans exposer de détails techniques de réseau), procédure de bascule si un canal principal devient indisponible, état de synchronisation décrit en langage courant, et éventuellement besoins de compétences déclarés pour la mission (chef de mission, liaison radio, soutien santé, etc.) à titre indicatif.</li>
        <li><strong>Clôture</strong> — Lorsque la coopération doit s’arrêter : motif, bilan synthétique, niveau de conservation des éléments selon les choix proposés.</li>
        <li><strong>Retour d’expérience (REX)</strong> — Après clôture, chaque communauté peut remplir un formulaire structuré (réussites, difficultés, recommandations, notation de plusieurs critères). Certaines personnes habilitées peuvent consulter une <strong>vue consolidée</strong> regroupant les contributions de toutes les unités.</li>
    </ul>
    <p>
        Un <strong>verrouillage de l’échange</strong> peut être appliqué par les pilotes : par exemple lecture seule du fil commun, ou blocage des nouvelles réponses, selon la politique interne ou la phase du dossier (y compris après clôture).
        Si vous voyez la mention de <strong>consultation seule</strong> sur le fil partagé, respectez-la : les échanges informels doivent alors passer par les canaux que votre état-major a prévus.
    </p>

    <h4>8. Messages dans l’espace commun : nature et bon usage</h4>
    <p>
        Lorsque l’interface le permet, vous choisissez une <strong>nature de message</strong> : message standard, information officielle, décision validée, etc.
        Les décisions marquées comme telles peuvent être mises en avant dans le <strong>journal</strong> pour retrouver rapidement les engagements publics pris dans le cadre du dossier.
        Les <strong>brouillons</strong> permettent de préparer un texte avant publication lorsque cette option existe.
    </p>
    <p>
        Même lorsque le message est publié depuis <strong>votre compte personnel</strong>, le contexte « coopération inter-unités » peut indiquer clairement <strong>quelle communauté vous représentez</strong> dans cet échange.
        Restez aligné sur les instructions de votre chaîne de commandement : le fil partagé n’est pas un espace anonyme.
    </p>

    <h4>9. Rôles au sein du dossier (personnes, pas seulement unités)</h4>
    <p>
        Outre le fait d’appartenir à une communauté <strong>support</strong> ou <strong>partenaire</strong>, des rôles <strong>au sein du dossier</strong> peuvent être attribués à des membres nommément (référent, lecture seule, rédacteur, observateur, officier de liaison, etc. — les intitulés exacts dépendent du référentiel de votre plateforme).
        Ces rôles <strong>ne remplacent pas</strong> votre rôle général dans la communauté : ils précisent ce que vous êtes autorisé à faire <em>dans ce dossier précis</em> (par exemple préparer les messages officiels ou seulement consulter).
    </p>

    <h3>Liste des coopérations : indicateurs et « actions attendues »</h3>
    <p>
        La page liste peut afficher des <strong>indicateurs de pilotage</strong> (nombre de dossiers actifs, invitations en attente, délais proches, etc.) et une zone <strong>actions attendues</strong> : il s’agit de rappels explicites du type « une réponse est attendue de votre unité sur tel dossier » ou « une autorisation de partage n’est pas complète ».
        Utilisez cette zone comme une <strong>file de priorités</strong> avant d’ouvrir chaque dossier en détail.
    </p>

    <h3>Cohérence avec le forum général et la modération</h3>
    <p>
        Le fil d’une coopération inter-unités suit les <strong>mêmes principes de civilité</strong> que le reste du forum. Les signalements et interventions des modérateurs restent possibles selon les règles de votre communauté.
        Pour le détail des outils de modération, voir la section <a href="#moderation-forum">Modération &amp; signalements</a>.
    </p>

    <h3>Référence des types et messages automatiques</h3>
    <p>
        Le portail propose une <strong>liste de types de coopération</strong> (libellés et textes d’aide) que votre communauté peut compléter si les personnes habilitées l’ont prévu.
        Des <strong>messages types</strong> peuvent aussi accompagner les grandes étapes d’un dossier (invitation, mise à jour, clôture, etc.) via le courriel, les notifications du portail ou des publications forum, selon la configuration retenue et vos préférences personnelles.
        En cas de doute sur ce qui est modifiable chez vous, interrogez l’encadrement ou l’administration de la communauté.
    </p>

    <h3>Modèles, duplication et capitalisation</h3>
    <p>
        Selon l’évolution du portail, votre organisation peut proposer de <strong>dupliquer</strong> un dossier passé pour démarrer une nouvelle coopération sur un schéma proche, ou d’utiliser des <strong>modèles</strong> internes.
        La duplication évite de recopier à la main les grandes rubriques tout en exigeant une relecture : les partenaires, dates et engagements précédents ne sont jamais repris tels quels sans validation humaine.
    </p>

    <h3>Notifications et suivi dans le temps</h3>
    <p>
        Le portail peut consigner des <strong>événements destinés à des notifications</strong> (courriel, messages dans l’interface, récapitulatifs) selon les préférences de votre compte et les réglages de la plateforme.
        L’objectif est d’éviter le spam : les rappels sont souvent <strong>regroupés par dossier</strong> lorsque plusieurs actions se produisent en peu de temps.
        Pensez à garder vos <strong>préférences de notification</strong> à jour dans votre espace personnel si vous trouvez les rappels trop rares ou trop fréquents.
    </p>

    <h3>Bonnes pratiques opérationnelles et de sécurité</h3>
    <ul>
        <li><strong>Centralisez les décisions importantes</strong> dans le dossier (proposition validée, messages marqués comme décisions, journal) plutôt que sur des canaux parallèles non tracés.</li>
        <li><strong>Ne reproduisez pas</strong> dans le fil partagé des informations dont la classification dépasse ce que votre autorisation de partage couvre.</li>
        <li><strong>Vérifiez la communauté active</strong> en haut de l’écran avant d’inviter ou d’accepter : une erreur de contexte peut envoyer une invitation depuis la mauvaise unité.</li>
        <li><strong>Préparez les réunions</strong> avec un ordre du jour dans l’outil : les autres unités savent à quoi s’attendre et le journal garde une trace.</li>
        <li><strong>Clôturez proprement</strong> : un dossier resté « en cours » alors que l’activité est finie crée de la confusion pour les tableaux de bord et les statistiques internes.</li>
        <li><strong>Remplissez le REX</strong> après clôture : c’est le moment où la mémoire institutionnelle s’améliore pour la prochaine coopération.</li>
    </ul>

    <h3>Dépannage : situations fréquentes</h3>
    <dl class="site-docs__dl">
        <dt>Message « accès refusé » en ouvrant un dossier</dt>
        <dd>Vous n’êtes pas listé comme participant actif, ou votre rôle ne couvre pas cette action à cette étape. Vérifiez aussi que la bonne communauté est sélectionnée.</dd>
        <dt>Je ne peux pas répondre sur le fil commun</dt>
        <dd>La coopération n’est peut-être pas encore activée, un verrouillage est en place, ou votre permission d’écriture dans l’espace commun n’a pas été accordée. Demandez au pilote du dossier.</dd>
        <dt>Code de confirmation jamais reçu</dt>
        <dd>Contrôlez le courrier indésirable, l’adresse du compte, et attendez quelques minutes. Évitez de demander trop de codes d’affilée.</dd>
        <dt>Partenaire qui « ne voit rien »</dt>
        <dd>Souvent : mauvaise communauté active de son côté, invitation encore en attente, ou habilitation manquante dans son unité.</dd>
    </dl>

    <div class="site-docs__callout">
        <strong>Rappel.</strong> Athena est un outil au service de votre organisation : les règles de classification de l’information, les ordres internes et le jugement du commandement restent prioritaires sur tout affichage du portail.
        En cas de doute sur ce qui peut être partagé avec une autre unité, <strong>ne publiez pas</strong> et sollicitez votre référent avant d’utiliser l’espace commun.
    </div>
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
    <p>
        Selon la configuration de la plateforme, le catalogue peut mêler des parcours <strong>publiés par votre communauté</strong> et des parcours <strong>proposés sur l’ensemble du site</strong> (repérés par une pastille).
        Des filtres permettent d’afficher <strong>tous</strong> les parcours, <strong>uniquement ceux de la communauté</strong>, ou <strong>uniquement ceux proposés sur toute la plateforme</strong>, en complément du filtre par thème et de la recherche.
    </p>
    <h3>Création : le Studio</h3>
    <p>
        Les équipes habilitées créent les formations dans l’espace d’édition (Studio) : <strong>fiche</strong> de la formation (titre, résumé, niveau, durée indicative, objectifs),
        puis structuration en <strong>modules</strong> et <strong>leçons</strong>. Chaque leçon a un type de contenu (texte enrichi, vidéo, fichier, audio, parcours type liste de contrôle, lien externe, parcours visuel type présentation, etc.).
    </p>
    <p>
        Les <strong>administrateurs de la plateforme</strong> peuvent indiquer si un parcours est destiné <strong>à la communauté courante</strong> ou <strong>proposé sur toute la plateforme</strong> (choix à la création et sur la fiche).
        Les autres rôles voient la portée actuelle sans pouvoir la modifier. Les adresses courtes des parcours proposés sur toute la plateforme doivent rester <strong>uniques</strong> à l’échelle du site.
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
        <li><strong>Coopérations inter-unités</strong> : tenez le journal à jour, clôturez les dossiers terminés, remplissez le retour d’expérience ; ne dépassez jamais le périmètre de votre autorisation de partage.</li>
    </ul>
    <div class="site-docs__callout site-docs__callout--tip">
        <strong>Évolutions.</strong> Les libellés à l’écran et les workflows peuvent être ajustés par votre organisation : en cas d’écart avec ce guide,
        les consignes internes et l’interface font foi.
    </div>
</section>
