<?php

declare(strict_types=1);

/**
 * Lexique livré de Cerbère-Lecture : ce que le bureau SSE détecte dans le texte des fiches et des comptes rendus pour
 * proposer un type de fiche, des thèmes et une urgence. Lu par App\Services\Sse\Detection\SseDetectionLexicon ; chaque
 * communauté peut ensuite, depuis « Détections Cerbère », désactiver une règle, changer son poids, ses termes, ses exclusions
 * et son contexte requis (table sse_detection_rule_settings). « Rétablir le lexique livré » revient à ce fichier.
 *
 * Écriture d'un terme (comparé MOT ENTIER, sans accents ni casse, jamais à l'intérieur d'un autre mot) :
 *   « otage »             le mot, et son pluriel simple en -s ou -x (otages) ;
 *   « prise d’otage »     une expression : les mots se suivent (l’apostrophe sépare comme dans le texte : « d », « otage ») ;
 *   « blessé(e|es) »      suffixes déclarés : blessé, blessée, blessées (et blessés par le pluriel simple) ;
 *   « surveill* »         racine déclarée : tout mot qui COMMENCE ainsi (surveille, surveillance…) ; les faux amis et
 *                         exclusions de la règle sont alors vérifiés.
 * Un terme vaut le poids de sa règle, sauf s'il porte son propre poids (« terme » => 80).
 *
 * Champs d'une règle :
 *   target      theme (thème de fiche), urgency (urgence) ou kind (type de fiche) ;
 *   value       code visé : SseFieldNoteCatalog::THEMES, « critique »/« urgent », SseFieldNoteCatalog::KINDS ;
 *   label       nom court ; explain : pourquoi la règle existe, en une phrase ;
 *   weight      0 à 100 : confiance de départ quand un terme est trouvé ;
 *   terms       termes ; exclusions : expressions qui annulent un terme quand il en fait partie (« barrage routier »
 *               n'est pas une infrastructure) ; requires : au moins un de ces mots doit aussi être dans le texte ;
 *   kinds       types de fiche où la règle est attendue (petit bonus) ;
 *   level       urgence seulement : critique ou urgent ; immediacy : la règle dit que la menace est immédiate (une urgence
 *               « Critique » n'est proposée qu'avec un signal immédiat ET un deuxième signal d'urgence distinct).
 */
return [
    'version' => '2026-11-16.1',

    /** Seuils de confiance (%) : au-dessus de « visible », la proposition est montrée ; entre les deux, repliée « à vérifier ». */
    'thresholds' => ['visible' => 55, 'review' => 30],

    /** Indices de contexte, cherchés dans la même phrase juste avant le terme (négation) ou autour (hypothèse, prudence). */
    'contexts' => [
        'negation' => ['factor' => 0.4, 'before' => 4, 'after' => 0, 'cues' => ['pas de', 'pas d', 'aucun', 'aucune', 'sans', 'ni', 'jamais de', 'absence de', 'absence d', 'pas un', 'pas une', 'no', 'not', 'without', 'none']],
        'hypothesis' => ['factor' => 0.45, 'before' => 5, 'after' => 3, 'cues' => ['exercice', 'simulation', 'simule(e|es)', 'entrainement', 'scenario', 'hypothese', 'hypothetique', 'drill', 'exercise', 'training', 'test']],
        'hedge' => ['factor' => 0.8, 'before' => 6, 'after' => 3, 'cues' => ['peut etre', 'possible', 'possiblement', 'pourrait', 'pourraient', 'pouvant', 'probable', 'probablement', 'eventuel(le|s|les)', 'suppose(e|es)', 'rumeur', 'maybe', 'possibly', 'might', 'could']],
    ],

    /**
     * Faux amis : mots qui CONTIENNENT un terme sans en être un. La correspondance par mots entiers les écarte déjà ; la liste
     * sert aux racines déclarées (« arme* » ne doit pas prendre « armée ») et s'affiche dans « Pourquoi ? ».
     */
    'false_friends' => [
        'sabotage' => 'contient « otage »', 'bagage' => 'finit comme « otage »', 'etage' => 'finit comme « otage »',
        'menage' => 'finit comme « otage »', 'potager' => 'contient « otage »', 'pylone' => 'sans rapport avec « pont » ni « piste »',
        'armee' => 'l’armée n’est pas une arme', 'armoire' => 'commence comme « arme »', 'armature' => 'commence comme « arme »',
        'gazon' => 'commence comme « gaz »', 'gazette' => 'commence comme « gaz »', 'pontage' => 'commence comme « pont »',
        'radiologue' => 'commence comme « radio »', 'radiateur' => 'commence comme « radi »', 'chefferie' => 'commence comme « chef »',
        'stockholm' => 'commence comme « stock »', 'cachette' => 'commence comme « cache »', 'surveillant' => 'métier, pas une surveillance',
        'attentatoire' => 'commence comme « attentat »', 'tirage' => 'commence comme « tir »', 'tiroir' => 'commence comme « tir »',
    ],

    'rules' => [
        // —————————————————————————————————————————————— Thèmes
        'R-01' => ['target' => 'theme', 'value' => 'TERROR', 'label' => 'Attentat, terrorisme', 'weight' => 75,
            'explain' => 'Un attentat, un acte ou une cellule terroriste est nommé.',
            'terms' => ['attentat', 'attentat suicide', 'kamikaze', 'terroriste', 'terrorisme', 'cellule terroriste', 'terrorist', 'terrorism', 'suicide bomber']],
        'R-02' => ['target' => 'theme', 'value' => 'TERROR', 'label' => 'Engin explosif improvisé', 'weight' => 65,
            'explain' => 'Un engin explosif improvisé ou un piège explosif est signalé.',
            'terms' => ['engin explosif improvise', 'engins explosifs improvises', 'ied', 'eei', 'vbied', 'voiture piegee', 'vehicule piege', 'colis piege', 'piege explosif', 'booby trap']],
        'R-03' => ['target' => 'theme', 'value' => 'TERROR', 'label' => 'Prise d’otages', 'weight' => 65,
            'explain' => 'Une prise d’otages ou des otages sont mentionnés (le mot entier : jamais « sabotage »).',
            'terms' => ['prise d’otage' => 85, 'prises d’otages' => 85, 'preneur d’otage' => 85, 'preneurs d’otages' => 85, 'otage', 'hostage', 'hostage taking' => 85],
            'exclusions' => ['otage de la situation', 'otages de la situation']],
        'R-04' => ['target' => 'theme', 'value' => 'INSURG', 'label' => 'Groupes armés, insurrection', 'weight' => 60,
            'explain' => 'Un groupe armé non étatique, une milice ou une rébellion est nommé.',
            'terms' => ['insurge(e|es)', 'insurrection', 'rebelle', 'rebellion', 'milice', 'milicien(ne|nes)', 'groupe arme', 'groupes armes', 'guerilla', 'insurgent', 'militia', 'rebel']],
        'R-05' => ['target' => 'theme', 'value' => 'CBRNE', 'label' => 'Chimique, radiologique, nucléaire', 'weight' => 70,
            'explain' => 'Un agent ou une arme chimique, biologique, radiologique ou nucléaire est évoqué.',
            'terms' => ['arme chimique', 'armes chimiques', 'agent chimique', 'chimique' => 55, 'radiologique', 'nucleaire', 'biologique' => 55, 'nrbc', 'nrbce', 'cbrn', 'cbrne', 'neurotoxique', 'sarin', 'ypérite', 'bombe sale', 'chemical weapon', 'radiological', 'nerve agent', 'dirty bomb'],
            'exclusions' => ['agriculture biologique', 'produit biologique', 'produits biologiques', 'centrale nucleaire']],
        'R-06' => ['target' => 'theme', 'value' => 'CBRNE', 'label' => 'Gaz (avec contexte)', 'weight' => 55,
            'explain' => 'Le mot « gaz » ne compte que s’il est accompagné d’un signe de danger (toxique, masque, irritant…).',
            'terms' => ['gaz', 'gas'],
            'exclusions' => ['bouteille de gaz', 'bouteilles de gaz', 'gaz d’echappement', 'station de gaz', 'gaz naturel', 'gaz de ville'],
            'requires' => ['toxique', 'masque', 'irritant', 'lacrymogene', 'asphyxiant', 'suffocant', 'intoxication', 'symptome', 'nausee', 'toxic', 'mask']],
        'R-07' => ['target' => 'theme', 'value' => 'ARMEMENT', 'label' => 'Armes et munitions', 'weight' => 55,
            'explain' => 'Des armes, munitions ou explosifs sont décrits (« armée » n’est pas une arme).',
            'terms' => ['arme', 'armement', 'munition', 'fusil', 'mitrailleuse', 'mortier', 'grenade', 'lance roquette', 'roquette', 'rpg', 'kalachnikov', 'ak 47', 'ak47', 'explosif', 'obus', 'missile', 'weapon', 'ammunition', 'rifle', 'machine gun', 'rcws', 'tourelle teleoperee'],
            'exclusions' => ['homme arme', 'hommes armes', 'groupe arme', 'groupes armes', 'force armee', 'forces armees'],
            'kinds' => ['FRT']],
        'R-08' => ['target' => 'theme', 'value' => 'ARMEMENT', 'label' => 'Matériel et véhicules militaires', 'weight' => 50,
            'explain' => 'Un matériel militaire, un drone ou un véhicule blindé est décrit.',
            'terms' => ['drone', 'ugv', 'uav', 'vehicule blinde', 'vehicules blindes', 'blinde(e|es)', 'systeme d’armes', 'systemes d’armes', 'tourelle', 'radar', 'materiel militaire', 'equipement militaire', 'equipements militaires', 'armored vehicle'],
            'exclusions' => ['porte blindee', 'portes blindees'],
            'kinds' => ['FRT']],
        'R-09' => ['target' => 'theme', 'value' => 'PERSON', 'label' => 'Personnes et cibles', 'weight' => 45,
            'explain' => 'Un individu suspect, une cible ou un chef adverse est décrit.',
            'terms' => ['individu', 'suspect(e|es)', 'cible', 'homme arme', 'hommes armes', 'chef', 'leader', 'haute valeur', 'hvt', 'personnalite', 'target', 'suspect'],
            'exclusions' => ['chef de village', 'chefs de village', 'chef de section', 'chef de groupe', 'chef d’equipe', 'chef de bord', 'colis suspect', 'colis suspects', 'engin suspect', 'vehicule suspect']],
        'R-10' => ['target' => 'theme', 'value' => 'LOGIST', 'label' => 'Stocks et ravitaillement', 'weight' => 50,
            'explain' => 'Un dépôt, un stock, du carburant ou un ravitaillement est décrit.',
            'terms' => ['depot', 'caisse', 'carburant', 'ravitaillement', 'stock', 'entrepot', 'entrepose(e|es)', 'hangar', 'convoi', 'cache d’armes', 'caches d’armes', 'supply', 'fuel', 'warehouse'],
            'exclusions' => ['depot de plainte']],
        'R-11' => ['target' => 'theme', 'value' => 'COMMS', 'label' => 'Transmissions', 'weight' => 45,
            'explain' => 'Une radio, une antenne, une fréquence ou un brouillage est décrit.',
            'terms' => ['radio', 'antenne', 'frequence', 'telephone', 'relais radio', 'brouillage', 'brouilleur', 'guerre electronique', 'satellite', 'jamming', 'frequency'],
            'exclusions' => ['radio locale', 'antenne medicale']],
        'R-12' => ['target' => 'theme', 'value' => 'INFRA', 'label' => 'Infrastructures', 'weight' => 45,
            'explain' => 'Un pont, un bâtiment, une centrale ou un barrage est décrit.',
            'terms' => ['pont', 'batiment', 'centrale', 'barrage', 'aeroport', 'piste d’atterrissage', 'chateau d’eau', 'pylone electrique', 'bridge', 'power plant'],
            'exclusions' => ['barrage routier', 'barrages routiers', 'pont radio']],
        'R-13' => ['target' => 'theme', 'value' => 'MOUV', 'label' => 'Mouvements', 'weight' => 45,
            'explain' => 'Un déplacement, un convoi, une colonne ou une infiltration est décrit.',
            'terms' => ['deplacement', 'convoi', 'colonne', 'se dirige', 'se dirigent', 'patrouille', 'franchi(t|ssent|ssement)', 'infiltration', 'exfiltration', 'en mouvement', 'heading', 'moving']],
        'R-14' => ['target' => 'theme', 'value' => 'SECUR', 'label' => 'Sabotage et atteintes à la sécurité', 'weight' => 60,
            'explain' => 'Un sabotage, une effraction ou des traces de manipulation menacent la protection des forces.',
            'terms' => ['sabotage', 'sabote(e|es)', 'saboteur', 'trace de manipulation', 'traces de manipulation', 'effraction', 'intrusion', 'contre ingerence', 'tampering']],
        'R-15' => ['target' => 'theme', 'value' => 'SECUR', 'label' => 'Surveillance et contrôles', 'weight' => 45,
            'explain' => 'Un poste de garde, une surveillance ou un point de contrôle est décrit.',
            'terms' => ['garde', 'sentinelle', 'surveill*', 'checkpoint', 'barrage routier', 'poste de controle', 'guard'],
            'exclusions' => ['garde a vue', 'mise en garde', 'prendre garde']],
        'R-16' => ['target' => 'theme', 'value' => 'CIVIL', 'label' => 'Population civile', 'weight' => 45,
            'explain' => 'La population, des habitants, un village ou un marché sont décrits.',
            'terms' => ['population', 'habitant', 'civil(e|es)', 'village', 'marche', 'refugie(e|es)', 'chef de village', 'humanitaire', 'civilian', 'villagers'],
            'exclusions' => ['en marche', 'marche arriere', 'marche a pied', 'mise en marche', 'se met en marche', 'tenue civile', 'en civil']],
        'R-17' => ['target' => 'theme', 'value' => 'FINANCE', 'label' => 'Trafics et financement', 'weight' => 50,
            'explain' => 'Un trafic, de la contrebande, une rançon ou du blanchiment est décrit.',
            'terms' => ['trafic', 'trafiquant', 'contrebande', 'blanchiment', 'rancon', 'racket', 'smuggling', 'ransom'],
            'exclusions' => ['trafic routier', 'trafic radio']],
        'R-18' => ['target' => 'theme', 'value' => 'RECRUT', 'label' => 'Recrutement', 'weight' => 50,
            'explain' => 'Un recrutement, un enrôlement ou une radicalisation est décrit.',
            'terms' => ['recrutement', 'recrue', 'enrolement', 'enrole(e|es)', 'radicalisation', 'radicalise(e|es)', 'recruitment']],
        'R-19' => ['target' => 'theme', 'value' => 'PLANIF', 'label' => 'Intentions et projets', 'weight' => 45,
            'explain' => 'Un projet d’attaque, un plan ou une intention adverse est rapporté.',
            'terms' => ['plan d’attaque', 'projet d’attaque', 'preparent une attaque', 'prepare une attaque', 'intention', 'planifie(nt)', 'attack plan']],
        'R-20' => ['target' => 'theme', 'value' => 'METEO', 'label' => 'Météo et terrain', 'weight' => 40,
            'explain' => 'Les conditions météo ou de terrain gênent l’action.',
            'terms' => ['meteo', 'pluie', 'brouillard', 'tempete', 'crue', 'inondation', 'visibilite reduite', 'fog', 'flood']],

        // —————————————————————————————————————————————— Urgence (signaux propres, jamais un thème seul)
        'U-01' => ['target' => 'urgency', 'value' => 'critique', 'level' => 'critique', 'immediacy' => true, 'label' => 'Menace immédiate', 'weight' => 70,
            'explain' => 'Le texte dit que l’action a lieu maintenant ou va avoir lieu (imminent, en cours, sous le feu).',
            'terms' => ['imminent(e|s|es)', 'attaque en cours', 'combat en cours', 'tirs en cours', 'prise d’otage en cours', 'prise d’otages en cours', 'tir nourri', 'tirs nourris', 'sous le feu', 'troupes au contact', 'contact en cours', 'au contact de l’ennemi', 'embuscade', 'immediatement', 'under fire', 'troops in contact', 'ongoing attack']],
        'U-02' => ['target' => 'urgency', 'value' => 'critique', 'level' => 'critique', 'label' => 'Otages', 'weight' => 60,
            'explain' => 'Des personnes sont retenues contre leur gré.',
            'terms' => ['prise d’otage' => 70, 'prises d’otages' => 70, 'otage', 'hostage', 'enlevement', 'kidnapping', 'sequestration'],
            'exclusions' => ['otage de la situation', 'otages de la situation']],
        'U-03' => ['target' => 'urgency', 'value' => 'critique', 'level' => 'critique', 'label' => 'Attentat', 'weight' => 65,
            'explain' => 'Un attentat est commis ou annoncé.',
            'terms' => ['attentat', 'attentat suicide', 'kamikaze', 'bombing', 'suicide bomber']],
        'U-04' => ['target' => 'urgency', 'value' => 'urgent', 'level' => 'urgent', 'label' => 'Victimes', 'weight' => 55,
            'explain' => 'Des blessés, des morts ou des victimes sont signalés.',
            'terms' => ['blesse(e|es)', 'mort(e|es)', 'tue(e|es)', 'victime', 'deces', 'medevac', 'evacuation sanitaire', 'casualty', 'casualties', 'wounded', 'killed', 'kia', 'wia'],
            'exclusions' => ['mort de rire', 'point mort', 'temps mort', 'angle mort']],
        'U-05' => ['target' => 'urgency', 'value' => 'urgent', 'level' => 'urgent', 'label' => 'Armes en action', 'weight' => 55,
            'explain' => 'Des tirs, une explosion ou des hommes armés sont signalés.',
            'terms' => ['tir', 'coups de feu', 'coup de feu', 'fusillade', 'explosion', 'detonation', 'homme arme', 'hommes armes', 'gunfire', 'shots fired'],
            'exclusions' => ['tir d’entrainement', 'tirs d’entrainement', 'champ de tir', 'stand de tir', 'pas de tir']],
        'U-06' => ['target' => 'urgency', 'value' => 'urgent', 'level' => 'urgent', 'label' => 'Engin explosif, colis suspect', 'weight' => 55,
            'explain' => 'Un engin explosif ou un colis suspect est signalé.',
            'terms' => ['engin explosif', 'engins explosifs', 'ied', 'eei', 'colis suspect', 'colis piege', 'voiture piegee', 'engin suspect', 'relie a des fils', 'suspicious package']],
        'U-07' => ['target' => 'urgency', 'value' => 'urgent', 'level' => 'urgent', 'label' => 'Délai court', 'weight' => 45,
            'explain' => 'Un délai court est donné (dans l’heure, cette nuit, au plus vite).',
            'terms' => ['dans l’heure', 'dans les prochaines minutes', 'dans les prochaines heures', 'd’ici ce soir', 'cette nuit', 'sous peu', 'au plus vite', 'sans delai', 'urgent', 'urgence', 'within the hour', 'asap']],

        // —————————————————————————————————————————————— Type de fiche (au moins deux termes distincts)
        'K-01' => ['target' => 'kind', 'value' => 'FRT', 'label' => 'Matériel décrit', 'weight' => 40,
            'explain' => 'Le texte décrit du matériel, un véhicule, un marquage ou une installation.',
            'terms' => ['vehicule', 'pick up', 'pickup', 'camion', 'arme', 'munition', 'lance roquette', 'materiel', 'immatricul*', 'plaque', 'marquage', 'antenne', 'engin', 'caisse', 'drone', 'colis', 'ugv', 'rcws', 'panne', 'tourelle'],
            'exclusions' => ['homme arme', 'hommes armes', 'groupe arme', 'groupes armes']],
        'K-02' => ['target' => 'kind', 'value' => 'FRC', 'label' => 'Échange avec une personne', 'weight' => 40,
            'explain' => 'Le texte rapporte un échange avec une personne.',
            'terms' => ['entretien', 'discute', 'discussion', 'rencontre', 'chef de village', 'informateur', 'interlocuteur', 'nous a dit', 'a declare', 'temoin', 'temoignage']],
        'K-03' => ['target' => 'kind', 'value' => 'FRA', 'label' => 'Climat d’un secteur', 'weight' => 40,
            'explain' => 'Le texte décrit l’attitude de la population ou l’ambiance d’un secteur.',
            'terms' => ['population', 'ambiance', 'tension', 'hostile', 'accueil', 'habitant', 'rumeur', 'climat', 'manifestation']],
    ],
];
