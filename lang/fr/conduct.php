<?php

declare(strict_types=1);

/*
 * Code de conduite Athena. Les articles 1 à 7 sont cités par les avis de suspension (PlatformBanNotice) :
 * ne jamais renuméroter, ajouter les nouveaux articles à la suite.
 * Mise en forme du corps : paragraphes séparés par une ligne vide, puces sur les lignes commençant par « - ».
 * Renvois : [[art:N]] [[contact]] [[appeal]] [[rights]] [[cgu]] [[mentions]] [[privacy]].
 */
return [
    'title' => 'Code de conduite Athena',
    'nav' => 'Code de conduite',
    'intro' => 'Ces règles s’appliquent aux échanges, aux contenus et à l’utilisation des services d’Athena. Elles complètent les conditions générales d’utilisation et les règles propres à chaque communauté.',
    'intro_scope' => 'Le mot « Admin » désigne l’administrateur de toute la plateforme Athena. Un « administrateur de communauté » gère uniquement sa propre communauté (voir l’[[art:9]]).',
    'index' => 'Sommaire des articles',
    'article' => 'Article :number',
    'version_label' => 'Version',
    'version' => '2.0',
    'updated_label' => 'Dernière mise à jour',
    'updated' => '23 novembre 2026',
    'updated_iso' => '2026-11-23',
    'print' => 'Imprimer ou enregistrer en PDF',
    'search_label' => 'Rechercher dans les articles',
    'search_placeholder' => 'Mot ou expression (exemple : contestation)',
    'search_none' => 'Aucun article ne correspond à cette recherche.',
    'search_count' => ':count article(s) affiché(s)',
    'example' => 'Exemple',
    'permalink' => 'Lien permanent vers :title',
    'back_top' => 'Retour au sommaire',
    'summary_label' => 'En bref',

    'article_1_title' => 'Respect des personnes',
    'article_1_summary' => 'Traitez chacun avec respect, même en cas de désaccord. Menaces, insultes ciblées, discriminations et harcèlement sont interdits, y compris en message privé.',
    'article_1_body' => "Les désaccords sont normaux, pas les attaques personnelles. Sont interdits :\n- les menaces et les insultes visant une personne ;\n- les propos discriminatoires, haineux ou diffamatoires ;\n- le harcèlement : messages répétés, mise à l’écart concertée, publication d’informations pour nuire.\n\nSi une personne vous demande de mettre fin à un échange, respectez cette demande. Cette règle vaut aussi sur les services liés à Athena (Discord, Steam, serveurs de jeu) quand l’incident concerne des membres de la plateforme.",
    'article_1_example' => 'Un membre écrit chaque jour en message privé à un autre membre qui lui a demandé d’arrêter. C’est du harcèlement, même si chaque message est poli.',

    'article_2_title' => 'Contenus et vie privée',
    'article_2_summary' => 'Ne partagez que ce que vous avez le droit de diffuser. Pas d’informations personnelles, d’échanges privés ou de documents de communauté sans accord. Les contenus illicites sont interdits.',
    'article_2_body' => "Vous restez responsable de ce que vous publiez (messages, images, documents, ordres, comptes rendus). Vous garantissez avoir le droit de le diffuser.\n\nNe publiez pas, sans l’accord des personnes ou des responsables concernés :\n- des informations personnelles (nom, adresse, photo, numéro de téléphone) ;\n- des échanges privés ou des captures de messages privés ;\n- des documents internes d’une communauté (effectifs, ORBAT, ordres, fiches).\n\nLes contenus illicites sont interdits : incitation à la haine ou à la violence, contenus pédopornographiques, contrefaçon, diffamation, données volées. L’éditeur peut retirer ou rendre inaccessible tout contenu manifestement illicite signalé ou détecté (voir [[mentions]]).",
    'article_2_example' => 'Publier la capture d’une conversation privée, avec le nom d’un membre, pour le ridiculiser est interdit, même si la conversation est authentique.',

    'article_3_title' => 'Comptes et sécurité',
    'article_3_summary' => 'Un compte, une personne. Protégez vos identifiants. L’usurpation, le partage de compte et le contournement d’une sanction sont interdits. Signalez tout accès suspect.',
    'article_3_body' => "Votre compte est personnel et vous êtes responsable de ce qui est fait depuis celui-ci. Il est interdit de :\n- prêter, vendre ou partager votre compte, vos clés de liaison ou vos clés de jeu ;\n- utiliser le compte d’une autre personne sans son autorisation ;\n- usurper l’identité d’une personne, d’un administrateur de communauté, d’un Admin ou de l’équipe Athena ;\n- créer ou utiliser un autre compte pour contourner un blocage, une suspension ou un bannissement.\n\nNe communiquez jamais votre mot de passe, même à quelqu’un qui se présente comme l’équipe Athena. Si vous pensez que votre compte est utilisé à votre insu, changez votre mot de passe puis [[contact]] sans attendre.",
    'article_3_example' => 'Un membre suspendu se réinscrit avec une autre adresse e-mail pour revenir : c’est un contournement de sanction, qui peut entraîner un bannissement définitif.',

    'article_4_title' => 'Usage loyal de la plateforme',
    'article_4_summary' => 'Respectez le bon fonctionnement du service : pas de spam, de fraude, d’accès non autorisé, de code malveillant ni d’extraction massive. Les API s’utilisent dans leur cadre prévu.',
    'article_4_body' => "Sont interdits :\n- le spam et les messages automatisés non sollicités ;\n- la fraude et les fausses déclarations (fausses présences, faux comptes rendus) ;\n- les tentatives d’accès non autorisé à un compte, à une communauté ou à des données ;\n- l’introduction de code malveillant et toute action qui perturbe le service ;\n- l’extraction massive de données (aspiration de pages, de profils ou d’effectifs) sans autorisation.\n\nLes interfaces (API) d’Athena sont destinées aux applications officielles (site, bot, mod). Si vous développez un outil qui s’y connecte, demandez d’abord l’accord de l’équipe ([[contact]]), respectez les limites de fréquence et n’utilisez que les accès qui vous ont été attribués.\n\nSi vous découvrez une faille, ne l’exploitez pas au-delà de ce qu’il faut pour la constater, ne diffusez pas les données qu’elle expose et signalez-la à l’équipe.",
    'article_4_example' => 'Un script qui télécharge automatiquement la liste complète des membres d’une communauté voisine est une extraction massive, même si les pages sont techniquement accessibles.',

    'article_5_title' => 'Signalements',
    'article_5_summary' => 'Signalez un incident à la modération de votre communauté, ou à l’équipe Athena pour ce qui concerne la plateforme. Donnez les faits, les dates et les liens. Ne relayez pas publiquement.',
    'article_5_body' => "À qui signaler :\n- un incident entre membres d’une même communauté : les modérateurs ou administrateurs de cette communauté ;\n- un contenu illicite, un compte piraté, un incident qui concerne la plateforme ou un administrateur de communauté : l’équipe Athena, via [[contact]].\n\nIndiquez les faits, les dates, les personnes concernées et les liens ou captures utiles. Préservez la confidentialité des personnes concernées et évitez de relayer publiquement le contenu signalé.\n\nUn signalement fait de bonne foi n’est jamais sanctionné, même s’il se révèle infondé. Un signalement faux et délibéré, fait pour nuire, relève de l’[[art:1]].",
    'article_5_example' => 'Signalement utile : « Le 12 novembre vers 21 h, dans le canal général, le membre X a publié l’adresse de Y. Lien du message : … »',

    'article_6_title' => 'Sanctions, décisions et contestation',
    'article_6_summary' => 'Les mesures sont proportionnées à la gravité des faits. L’avis indique le motif, l’article visé et la durée. Vous pouvez demander un réexamen quand la contestation est ouverte.',
    'article_6_body' => "Échelle des mesures. La modération choisit la mesure la plus légère qui suffit à faire cesser le problème :\n- avertissement : rappel de la règle, sans restriction ;\n- retrait d’un contenu ou restriction d’une fonctionnalité ;\n- suspension temporaire de l’accès au compte, avec une date de fin indiquée dans l’avis ;\n- bannissement définitif : accès au compte fermé, sans date de fin.\n\nCritères pris en compte : la gravité des faits, leur répétition, l’intention, le préjudice causé aux personnes ou à la plateforme, et l’attitude de la personne concernée. Les faits les plus graves (menaces, contenus illicites, piratage, atteinte à la sécurité du service) peuvent justifier une suspension ou un bannissement immédiat, sans avertissement préalable. Athena peut aussi appliquer une règle automatique : plusieurs suspensions ou bannissements confirmés sur une période donnée entraînent une nouvelle suspension, dont le motif précise la règle appliquée.\n\nInformation. L’avis indique le motif, l’article du présent code visé, la date de fin éventuelle et si la contestation est ouverte.\n\nContestation. Pour consulter votre décision et la contester, rendez-vous sur [[appeal]] :\n- saisissez l’adresse e-mail du compte : un lien privé, valable une heure, vous est envoyé ;\n- expliquez votre situation en 10 à 4 000 caractères, avec les éléments de contexte utiles ;\n- l’administration de la plateforme examine la demande, dans la mesure du possible par une personne différente de celle qui a pris la décision ;\n- la décision de réexamen vous est communiquée avec une réponse motivée.\n\nL’administration peut fermer la contestation, par exemple après une décision définitive : l’avis l’indique alors. Les mesures prises par la modération d’une communauté suivent les règles de cette communauté, qui doit vous permettre de les contester auprès d’elle.",
    'article_6_example' => 'Un premier message insultant donne en général lieu à un avertissement ou au retrait du message. Des menaces répétées contre un membre peuvent conduire directement à une suspension ou à un bannissement.',

    'article_7_title' => 'Inactivité et reprise du compte',
    'article_7_summary' => 'Un compte sans aucune activité pendant une longue période peut être suspendu. Ce n’est pas une sanction disciplinaire : vous pouvez demander à reprendre votre activité.',
    'article_7_body' => "Athena peut suspendre l’accès à un compte sans connexion au site ni session de jeu enregistrée pendant une période prolongée, ou sans activité enregistrée depuis son inscription. Cette mesure protège les comptes abandonnés et limite les données conservées sans utilité.\n\nConsultez votre avis pour connaître le motif et la durée de la restriction. Si vous souhaitez reprendre votre activité ou corriger une erreur (par exemple une connexion non comptabilisée), demandez un réexamen lorsque cette possibilité est indiquée, selon la procédure de l’[[art:6]].",
    'article_7_example' => 'Vous avez joué sur un serveur sans jamais vous connecter au site et votre compte est suspendu : expliquez-le dans votre demande de réexamen.',

    'article_8_title' => 'Jeu, mod et clés de jeu',
    'article_8_summary' => 'Le mod et les clés liées à votre compte sont personnels. La triche, l’ingénierie inverse et le contournement du module officiel sont interdits.',
    'article_8_body' => "Les modules d’Athena pour Arma 3 (dont ATAK) et les services associés (ORBAT, SSE, suivi des sessions) échangent des données de jeu avec la plateforme. Pour que ces données restent fiables, il est interdit de :\n- tricher ou falsifier des données de jeu (positions, présences, résultats, états d’ORBAT ou de SSE) ;\n- contourner, désactiver ou modifier le module officiel, ou faire croire qu’il est actif ;\n- décompiler ou analyser le mod ou ses échanges avec la plateforme pour en extraire des secrets ou reproduire le service (ingénierie inverse) ;\n- partager, revendre ou publier vos clés de jeu et de liaison ;\n- vous connecter à la plateforme avec un client non officiel qui imite le mod.\n\nLes données de jeu liées à votre compte peuvent être consultées par les personnes habilitées en cas de soupçon de triche (voir l’[[art:11]]).",
    'article_8_example' => 'Utiliser un programme qui envoie de fausses positions au serveur pour apparaître sur la carte tactique est de la triche.',

    'article_9_title' => 'Communautés et administrateurs de communauté',
    'article_9_summary' => 'Chaque communauté fixe ses règles internes, dans le respect de ce code. Ses administrateurs modèrent chez elle. Seuls les Admin de la plateforme agissent sur toute la plateforme.',
    'article_9_body' => "Chaque communauté peut ajouter ses propres règles (civilité, organisation, discipline de jeu). Elles ne peuvent pas contredire le présent code ni les [[cgu]].\n\nLes administrateurs de communauté peuvent modérer, retirer ou restreindre l’accès aux contenus et aux espaces de leur communauté. Ils n’ont aucun pouvoir sur les autres communautés ni sur l’accès à la plateforme : seule l’administration d’Athena (les « Admin ») peut suspendre ou bannir un compte de la plateforme.\n\nUn administrateur de communauté traite les données de ses membres avec discrétion : il ne les utilise que pour gérer sa communauté et ne les diffuse pas à l’extérieur.\n\nUn compte de communauté (unité, page, compte tenu par une équipe) doit avoir un responsable identifié et ne peut pas servir à contourner les règles applicables aux comptes personnels.",
    'article_9_example' => 'Un administrateur de communauté peut exclure un membre de son unité. Il ne peut pas suspendre son compte Athena : il signale la situation à l’équipe Athena.',

    'article_10_title' => 'Propriété intellectuelle',
    'article_10_summary' => 'Les mods, logiciels et marques d’Athena restent à leurs auteurs. Vous gardez vos droits sur vos contenus et autorisez Athena à les afficher.',
    'article_10_body' => "Les logiciels, mods, interfaces, textes, marques et bases de données d’Athena restent la propriété de l’éditeur ou de leurs auteurs. Vous pouvez utiliser les mods comme le prévoit la plateforme. Vous ne pouvez pas les copier, les republier, les modifier pour les redistribuer ou les revendre sans accord écrit.\n\nCertains éléments appartiennent à d’autres titulaires de droits (Arma 3, Steam Workshop, auteurs de mods tiers) : respectez leurs licences.\n\nSur vos propres contenus, vous gardez vos droits. En les publiant sur Athena, vous autorisez la plateforme à les stocker et à les afficher aux personnes auxquelles vous les destinez, pour faire fonctionner le service.",
    'article_10_example' => 'Republier le pack de mods Athena sur un autre site, sans accord, n’est pas permis.',

    'article_11_title' => 'Données personnelles, journaux et droits',
    'article_11_summary' => 'Athena enregistre des journaux pour la sécurité et la modération. Vous pouvez demander l’accès, la rectification, l’effacement, la portabilité, la limitation et l’opposition.',
    'article_11_body' => "Pour assurer la sécurité du service, appliquer ce code et traiter les signalements, Athena conserve des journaux techniques (connexions, actions de modération, décisions et contestations) et des données de jeu liées à votre compte. Seules les personnes habilitées y accèdent, pour ces finalités. La durée de conservation est limitée à ce qui est nécessaire ; voir [[privacy]].\n\nVous pouvez exercer vos droits d’accès, de rectification, d’effacement, de portabilité, de limitation et d’opposition, et retirer un consentement donné, depuis [[rights]] ou en écrivant à [[contact]]. Vous pouvez aussi introduire une réclamation auprès de la CNIL. Certaines données (par exemple celles qui prouvent une sanction ou répondent à une obligation légale) peuvent être conservées malgré une demande d’effacement, pendant la durée strictement nécessaire.\n\nLes prestataires techniques et les services liés sont décrits dans [[mentions]] et dans la politique de données personnelles.",
    'article_11_example' => 'Pour obtenir une copie des données de votre compte ou la suppression de celui-ci, utilisez la page « Exercer vos droits ».',

    'article_12_title' => 'Contact',
    'article_12_summary' => 'Une seule adresse pour les questions, les signalements et les demandes liées aux données : [[contact]].',
    'article_12_body' => "Pour une question sur ce code, un signalement, une demande liée à vos données ou un incident de sécurité, écrivez à [[contact]]. Indiquez l’adresse e-mail de votre compte, mais ne communiquez jamais votre mot de passe.\n\nPour contester une mesure, utilisez [[appeal]].",
    'article_12_example' => '',

    'article_13_title' => 'Évolution de ce code',
    'article_13_summary' => 'Ce code peut évoluer. La version en vigueur est celle de cette page, avec son numéro et sa date. Les numéros d’article restent stables.',
    'article_13_body' => "Ce code peut être modifié pour tenir compte de l’évolution du service, de la loi ou de la vie des communautés. La version en vigueur est publiée sur cette page, avec son numéro et sa date de dernière mise à jour. Les numéros d’article ne changent pas d’une version à l’autre : les nouveaux articles s’ajoutent à la suite, pour que les décisions déjà notifiées renvoient toujours au bon texte.\n\nLa poursuite de l’utilisation du service après la publication d’une nouvelle version vaut acceptation de celle-ci. Voir aussi [[cgu]].",
    'article_13_example' => '',

    'read_rules' => 'Consulter le code de conduite Athena',
    'contact' => 'Contacter le support',
    'appeal' => 'Consulter ou contester une décision',
    'link_appeal' => 'la page de contestation',
    'link_rights' => 'la page « Exercer vos droits »',
    'link_cgu' => 'les conditions générales d’utilisation',
    'link_mentions' => 'les mentions légales',
    'link_privacy' => 'la politique de données personnelles',
];
