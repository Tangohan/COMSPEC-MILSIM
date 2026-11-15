<?php

declare(strict_types=1);

/**
 * Codification des avis de recherche du portail SSE (jeu de rôle, aucune donnée réelle) : motifs « M-FAM-NN », mesures à
 * prendre « A-XXX », modèles d'avis et valeurs par défaut par type d'avis. Catalogue livré avec le code ; chaque communauté
 * l'adapte dans Réglages de la notice > Codification (voir App\Support\SseWantedCodes) sans toucher à ce fichier.
 *
 * Les codes sont STABLES : un code ne change jamais de sens (le jeu et les PDF s'appuient dessus). Pour un autre sens,
 * créer un nouveau code. Vocabulaire des forces spéciales (HVT, PoI, observer et rendre compte, EEI, ISR…), jamais celui
 * d'une force de l'ordre civile.
 *
 * Forme d'un motif : famille, libellés court (fr, en), texte développé prêt à l'emploi (text_fr, text_en), menace suggérée.
 * Forme d'une mesure : mêmes champs ; « threat » est la menace à partir de laquelle la mesure est d'usage.
 *
 * @return array{
 *   reason_families: array<string, array{fr: string, en: string}>,
 *   measure_families: array<string, array{fr: string, en: string}>,
 *   reasons: array<string, array<string, string>>,
 *   measures: array<string, array<string, string>>,
 *   templates: array<string, array<string, mixed>>,
 *   type_defaults: array<string, array{reasons: list<string>, measures: list<string>}>
 * }
 */

$m = static fn (string $family, string $fr, string $en, string $threat, string $textFr, string $textEn): array => [
    'family' => $family, 'fr' => $fr, 'en' => $en, 'threat' => $threat, 'text_fr' => $textFr, 'text_en' => $textEn,
];

return [
    'reason_families' => [
        'TER' => ['fr' => 'Terrorisme et extrémisme violent', 'en' => 'Terrorism and violent extremism'],
        'INS' => ['fr' => 'Insurrection et combat hostile', 'en' => 'Insurgency and hostile combat'],
        'TRF' => ['fr' => 'Trafics et logistique', 'en' => 'Trafficking and logistics'],
        'FIN' => ['fr' => 'Financement et facilitation', 'en' => 'Financing and facilitation'],
        'ESP' => ['fr' => 'Espionnage et renseignement hostile', 'en' => 'Espionage and hostile intelligence'],
        'VIO' => ['fr' => 'Violences contre des personnes', 'en' => 'Violence against persons'],
        'CYB' => ['fr' => 'Cyber et communications', 'en' => 'Cyber and communications'],
        'EVA' => ['fr' => 'Évasion et fraude', 'en' => 'Escape and fraud'],
        'INT' => ['fr' => 'Intérêt pour le renseignement', 'en' => 'Intelligence interest'],
    ],

    'measure_families' => [
        'OBS' => ['fr' => 'Observation et compte rendu', 'en' => 'Observation and reporting'],
        'CTL' => ['fr' => 'Contrôle et collecte', 'en' => 'Screening and collection'],
        'INT' => ['fr' => 'Intervention', 'en' => 'Action'],
        'SAF' => ['fr' => 'Sécurité des intervenants', 'en' => 'Force protection'],
        'BRD' => ['fr' => 'Mouvements et frontières', 'en' => 'Movement and borders'],
        'COM' => ['fr' => 'Coordination', 'en' => 'Coordination'],
    ],

    'reasons' => [
        // Terrorisme et extrémisme violent
        'M-TER-01' => $m('TER', 'Planification d’attaque', 'Attack planning', 'critical',
            'Planifie une attaque contre des forces de la coalition ou des civils : reconnaissance d’objectifs, répartition des rôles et choix du moment.',
            'Plans an attack against coalition forces or civilians: target reconnaissance, assignment of roles and timing.'),
        'M-TER-02' => $m('TER', 'Appartenance à une organisation hostile désignée', 'Member of a designated hostile organization', 'high',
            'Membre actif d’une organisation hostile désignée par la coalition.',
            'Active member of a hostile organization designated by the coalition.'),
        'M-TER-03' => $m('TER', 'Fabrication ou pose d’EEI', 'IED manufacture or emplacement', 'critical',
            'Fabrique ou pose des engins explosifs improvisés (EEI) sur les axes empruntés par la coalition.',
            'Builds or emplaces improvised explosive devices (IEDs) on routes used by the coalition.'),
        'M-TER-04' => $m('TER', 'Recrutement et endoctrinement', 'Recruitment and indoctrination', 'high',
            'Recrute et endoctrine de nouveaux combattants pour le compte d’une organisation hostile.',
            'Recruits and indoctrinates new fighters on behalf of a hostile organization.'),
        'M-TER-05' => $m('TER', 'Attentat suicide ou à la voiture piégée', 'Suicide or VBIED attack', 'critical',
            'Impliqué dans la préparation d’attaques suicides ou de véhicules piégés (VBIED).',
            'Involved in preparing suicide or vehicle-borne IED (VBIED) attacks.'),
        'M-TER-06' => $m('TER', 'Propagande et incitation', 'Propaganda and incitement', 'medium',
            'Diffuse de la propagande et incite à la violence contre la coalition et les populations locales.',
            'Spreads propaganda and incites violence against the coalition and local populations.'),

        // Insurrection et combat hostile
        'M-INS-01' => $m('INS', 'Commandement de cellule insurgée', 'Insurgent cell leadership', 'high',
            'Commande une cellule insurgée et donne les ordres d’opération à ses éléments.',
            'Leads an insurgent cell and issues operational orders to its members.'),
        'M-INS-02' => $m('INS', 'Embuscade contre la coalition', 'Ambush against the coalition', 'high',
            'Auteur ou organisateur d’embuscades contre des convois et patrouilles de la coalition.',
            'Perpetrator or organizer of ambushes against coalition convoys and patrols.'),
        'M-INS-03' => $m('INS', 'Tirs indirects', 'Indirect fire', 'high',
            'Conduit des tirs indirects (mortiers, roquettes) sur des positions de la coalition.',
            'Conducts indirect fire (mortars, rockets) on coalition positions.'),
        'M-INS-04' => $m('INS', 'Combattant ennemi non identifiable', 'Unlawful enemy combatant', 'medium',
            'Combattant ennemi sans signe distinctif, qui se mêle à la population civile.',
            'Enemy combatant without distinctive insignia, blending into the civilian population.'),
        'M-INS-05' => $m('INS', 'Camp d’entraînement', 'Training camp', 'high',
            'Organise ou tient un camp d’entraînement au combat pour une force hostile.',
            'Organizes or runs a combat training camp for a hostile force.'),

        // Trafics et logistique
        'M-TRF-01' => $m('TRF', 'Trafic d’armes', 'Arms trafficking', 'high',
            'Achemine ou revend des armes et des munitions à des groupes hostiles.',
            'Moves or resells weapons and ammunition to hostile groups.'),
        'M-TRF-02' => $m('TRF', 'Passeur', 'Smuggler', 'medium',
            'Organise le franchissement clandestin de la frontière pour des combattants ou des marchandises.',
            'Organizes clandestine border crossings for fighters or goods.'),
        'M-TRF-03' => $m('TRF', 'Trafic de stupéfiants', 'Narcotics trafficking', 'medium',
            'Trafic de stupéfiants dont les revenus alimentent des groupes hostiles.',
            'Narcotics trafficking whose proceeds fund hostile groups.'),
        'M-TRF-04' => $m('TRF', 'Traite de personnes', 'Human trafficking', 'high',
            'Participe à un réseau de traite et d’exploitation de personnes.',
            'Takes part in a human trafficking and exploitation network.'),
        'M-TRF-05' => $m('TRF', 'Matières explosives ou dangereuses', 'Explosive or hazardous materials', 'high',
            'Détient ou transporte des explosifs, des détonateurs ou des matières dangereuses.',
            'Holds or transports explosives, detonators or hazardous materials.'),
        'M-TRF-06' => $m('TRF', 'Logistique d’approvisionnement', 'Supply logistics', 'medium',
            'Assure le ravitaillement en vivres, véhicules et matériel d’une cellule hostile.',
            'Supplies food, vehicles and equipment to a hostile cell.'),

        // Financement et facilitation
        'M-FIN-01' => $m('FIN', 'Financeur de cellule', 'Cell financier', 'high',
            'Finance les opérations d’une cellule hostile et paie ses combattants.',
            'Funds the operations of a hostile cell and pays its fighters.'),
        'M-FIN-02' => $m('FIN', 'Circuit de fonds informel', 'Informal funds transfer', 'medium',
            'Fait transiter des fonds par des circuits informels au profit d’un groupe hostile.',
            'Moves funds through informal channels for the benefit of a hostile group.'),
        'M-FIN-03' => $m('FIN', 'Blanchiment de fonds', 'Money laundering', 'medium',
            'Blanchit l’argent d’un réseau hostile par des sociétés écrans.',
            'Launders a hostile network’s money through front companies.'),
        'M-FIN-04' => $m('FIN', 'Hébergement et refuge', 'Safe house and shelter', 'medium',
            'Fournit des refuges et des caches à des combattants recherchés.',
            'Provides safe houses and caches to wanted fighters.'),

        // Espionnage et renseignement hostile
        'M-ESP-01' => $m('ESP', 'Collecte de renseignement hostile', 'Hostile intelligence collection', 'high',
            'Collecte du renseignement sur les positions, les mouvements et les effectifs de la coalition.',
            'Collects intelligence on coalition positions, movements and strength.'),
        'M-ESP-02' => $m('ESP', 'Infiltration', 'Infiltration', 'high',
            'Cherche à s’infiltrer dans les rangs ou les installations de la coalition.',
            'Attempts to infiltrate coalition ranks or installations.'),
        'M-ESP-03' => $m('ESP', 'Source compromise', 'Compromised source', 'medium',
            'Ancienne source dont la fiabilité est compromise : elle transmet à l’adversaire.',
            'Former source whose reliability is compromised: passes information to the adversary.'),
        'M-ESP-04' => $m('ESP', 'Contre-surveillance', 'Counter-surveillance', 'medium',
            'Repère et suit les patrouilles de la coalition pour le compte d’une cellule hostile.',
            'Spots and tracks coalition patrols on behalf of a hostile cell.'),
        'M-ESP-05' => $m('ESP', 'Agent d’influence', 'Influence agent', 'medium',
            'Agent d’influence qui manipule des responsables locaux contre la coalition.',
            'Influence agent who manipulates local leaders against the coalition.'),

        // Violences contre des personnes
        'M-VIO-01' => $m('VIO', 'Assassinat ciblé', 'Targeted killing', 'critical',
            'Auteur ou commanditaire d’assassinats ciblés de responsables locaux ou de militaires.',
            'Perpetrator or sponsor of targeted killings of local leaders or service members.'),
        'M-VIO-02' => $m('VIO', 'Enlèvement et prise d’otages', 'Kidnapping and hostage taking', 'critical',
            'Organise des enlèvements et des prises d’otages à des fins d’extorsion ou de propagande.',
            'Organizes kidnappings and hostage taking for extortion or propaganda.'),
        'M-VIO-03' => $m('VIO', 'Violences contre des civils', 'Violence against civilians', 'high',
            'Responsable de violences répétées contre la population civile.',
            'Responsible for repeated violence against the civilian population.'),
        'M-VIO-04' => $m('VIO', 'Mauvais traitements de détenus', 'Detainee abuse', 'high',
            'Soumet des détenus à des mauvais traitements.',
            'Subjects detainees to abuse.'),
        'M-VIO-05' => $m('VIO', 'Exécutions sommaires', 'Summary executions', 'critical',
            'Ordonne ou exécute des mises à mort sommaires.',
            'Orders or carries out summary executions.'),

        // Cyber et communications
        'M-CYB-01' => $m('CYB', 'Intrusion dans les systèmes de la coalition', 'Intrusion into coalition systems', 'high',
            'Mène des intrusions dans les réseaux et systèmes d’information de la coalition.',
            'Conducts intrusions into coalition networks and information systems.'),
        'M-CYB-02' => $m('CYB', 'Brouillage et interception radio', 'Radio jamming and interception', 'medium',
            'Brouille ou intercepte les communications radio de la coalition.',
            'Jams or intercepts coalition radio communications.'),
        'M-CYB-03' => $m('CYB', 'Communications chiffrées', 'Encrypted communications', 'medium',
            'Anime un réseau de communications chiffrées utilisé par une cellule hostile.',
            'Runs an encrypted communications network used by a hostile cell.'),

        // Évasion et fraude
        'M-EVA-01' => $m('EVA', 'Évasion de détention', 'Escape from detention', 'high',
            'S’est évadé d’un site de détention de la coalition.',
            'Escaped from a coalition detention facility.'),
        'M-EVA-02' => $m('EVA', 'Faux papiers', 'Forged documents', 'medium',
            'Utilise ou fabrique de faux papiers d’identité pour circuler.',
            'Uses or produces forged identity documents to move around.'),
        'M-EVA-03' => $m('EVA', 'Franchissement d’une zone interdite', 'Restricted area violation', 'medium',
            'A franchi une zone interdite ou un périmètre de sécurité de la coalition.',
            'Crossed a restricted area or a coalition security perimeter.'),
        'M-EVA-04' => $m('EVA', 'Refus de s’arrêter à un point de contrôle', 'Checkpoint evasion', 'medium',
            'A forcé ou contourné un point de contrôle de la coalition.',
            'Ran or bypassed a coalition checkpoint.'),

        // Intérêt pour le renseignement
        'M-INT-01' => $m('INT', 'Contact connu d’une cible de haute valeur', 'Known associate of an HVT', 'low',
            'Contact régulier d’une cible de haute valeur (HVT) : peut mener à elle.',
            'Regular associate of a high-value target (HVT): may lead to them.'),
        'M-INT-02' => $m('INT', 'Témoin clé', 'Key witness', 'low',
            'Témoin d’un événement important : à retrouver pour l’entendre, sans suspicion à ce stade.',
            'Witness to a significant event: to be located for questioning, no suspicion at this stage.'),
        'M-INT-03' => $m('INT', 'Source potentielle', 'Potential source', 'low',
            'Source potentielle : à approcher avec prudence par le service demandeur.',
            'Potential source: to be approached carefully by the requesting service.'),
        'M-INT-04' => $m('INT', 'Comportement inhabituel signalé', 'Reported unusual behavior', 'low',
            'Comportement inhabituel signalé près d’installations de la coalition : à surveiller.',
            'Unusual behavior reported near coalition installations: to be monitored.'),
    ],

    'measures' => [
        // Observation et compte rendu
        'A-OBS' => $m('OBS', 'Observer et rendre compte', 'Observe and report', 'low',
            'Observer sans se faire repérer et rendre compte au service demandeur.',
            'Observe without being detected and report to the requesting service.'),
        'A-REP' => $m('OBS', 'Rendre compte immédiatement', 'Report immediately', 'medium',
            'Rendre compte immédiatement au service demandeur de tout contact avec la personne.',
            'Report immediately to the requesting service any contact with the subject.'),
        'A-NOI' => $m('OBS', 'Ne pas intervenir seul', 'Do not engage alone', 'medium',
            'Ne pas intervenir seul : attendre des renforts et l’ordre de la chaîne de commandement.',
            'Do not engage alone: wait for reinforcements and orders from the chain of command.'),
        'A-TRK' => $m('OBS', 'Suivre discrètement', 'Track discreetly', 'medium',
            'Suivre discrètement la personne et noter ses contacts et ses déplacements.',
            'Track the subject discreetly and log contacts and movements.'),
        'A-PAT' => $m('OBS', 'Établir un schéma de vie', 'Establish pattern of life', 'medium',
            'Établir le schéma de vie de la personne : horaires, lieux, véhicules et fréquentations.',
            'Establish the subject’s pattern of life: schedule, locations, vehicles and associates.'),

        // Contrôle et collecte
        'A-FRK' => $m('CTL', 'Fouille et contrôle renforcés', 'Enhanced search and screening', 'medium',
            'Procéder à une fouille et à un contrôle renforcés de la personne, de ses affaires et de son véhicule.',
            'Conduct an enhanced search and screening of the subject, belongings and vehicle.'),
        'A-IDV' => $m('CTL', 'Vérifier l’identité complète', 'Verify full identity', 'low',
            'Vérifier l’identité complète sur les papiers et la confronter au fichier.',
            'Verify full identity against documents and cross-check against the file.'),
        'A-BIO' => $m('CTL', 'Relever la biométrie (SEEK II)', 'Collect biometrics (SEEK II)', 'medium',
            'Relever la biométrie avec le terminal SEEK II : visage, empreintes, iris.',
            'Collect biometrics with the SEEK II terminal: face, fingerprints, iris.'),
        'A-SEZ' => $m('CTL', 'Saisir documents et appareils', 'Seize documents and devices', 'medium',
            'Saisir les documents, téléphones et supports numériques pour exploitation.',
            'Seize documents, phones and digital media for exploitation.'),
        'A-PHO' => $m('CTL', 'Photographier la personne et le véhicule', 'Photograph subject and vehicle', 'low',
            'Photographier la personne, son véhicule et son environnement immédiat.',
            'Photograph the subject, vehicle and immediate surroundings.'),

        // Intervention
        'A-ARR' => $m('INT', 'Appréhender sur ordre', 'Apprehend on order', 'high',
            'Appréhender la personne uniquement sur ordre de la chaîne de commandement.',
            'Apprehend the subject only on orders from the chain of command.'),
        'A-LIV' => $m('INT', 'Capturer vivant', 'Capture alive', 'high',
            'Capturer vivante : la personne est recherchée pour son renseignement.',
            'Capture alive: the subject is sought for intelligence value.'),
        'A-NEU' => $m('INT', 'Neutraliser sur autorisation', 'Neutralize on authorization', 'critical',
            'Neutraliser uniquement avec l’autorisation expresse de l’autorité d’engagement.',
            'Neutralize only with the express authorization of the engagement authority.'),
        'A-DET' => $m('INT', 'Détenir et remettre au service demandeur', 'Detain and hand over', 'high',
            'Détenir la personne et la remettre au service demandeur sans l’interroger.',
            'Detain the subject and hand over to the requesting service without questioning.'),
        'A-CRD' => $m('INT', 'Isoler la zone', 'Cordon the area', 'high',
            'Isoler la zone du contact et interdire tout accès jusqu’à l’arrivée de renforts.',
            'Cordon the area of contact and deny access until reinforcements arrive.'),
        'A-QRF' => $m('INT', 'Alerter la force de réaction rapide', 'Alert the quick reaction force', 'high',
            'Alerter la force de réaction rapide (QRF) dès le contact.',
            'Alert the quick reaction force (QRF) upon contact.'),

        // Sécurité des intervenants
        'A-DNG' => $m('SAF', 'Considérer armé et dangereux', 'Consider armed and dangerous', 'high',
            'Considérer la personne comme armée et dangereuse : approche prudente, mains visibles exigées.',
            'Consider the subject armed and dangerous: approach with caution, hands must be visible.'),
        'A-IED' => $m('SAF', 'Risque d’EEI', 'IED threat', 'high',
            'Risque d’engins explosifs improvisés : sécuriser les abords et attendre le détachement de déminage (EOD).',
            'IED threat: secure the surroundings and wait for the EOD team.'),
        'A-CIV' => $m('SAF', 'Protéger les civils alentour', 'Protect nearby civilians', 'medium',
            'Prendre en compte la présence de civils : éviter toute intervention en zone peuplée.',
            'Account for civilians in the area: avoid any action in populated areas.'),
        'A-BOD' => $m('SAF', 'Prévoir protection et soutien médical', 'Plan protection and medical support', 'high',
            'Équipement de protection individuelle exigé et soutien médical prévu avant tout contact.',
            'Personal protective equipment required and medical support planned before any contact.'),

        // Mouvements et frontières
        'A-EXF' => $m('BRD', 'Signaler tout mouvement transfrontalier', 'Report any cross-border movement', 'medium',
            'Signaler tout mouvement transfrontalier de la personne ou de ses véhicules.',
            'Report any cross-border movement of the subject or their vehicles.'),
        'A-CHK' => $m('BRD', 'Alerter les points de contrôle', 'Alert checkpoints', 'medium',
            'Diffuser le signalement aux points de contrôle et aux patrouilles de la zone.',
            'Pass the description to checkpoints and patrols in the area.'),
        'A-NAV' => $m('BRD', 'Surveiller aéroports et ports', 'Monitor airfields and ports', 'high',
            'Surveiller les aérodromes, les ports et les gares de la zone.',
            'Monitor airfields, ports and stations in the area.'),

        // Coordination
        'A-ISR' => $m('COM', 'Demander un appui renseignement (ISR)', 'Request ISR support', 'high',
            'Demander un appui de renseignement, surveillance et reconnaissance (ISR) pour localiser la personne.',
            'Request intelligence, surveillance and reconnaissance (ISR) support to locate the subject.'),
        'A-CHN' => $m('COM', 'Référer à la chaîne de commandement', 'Refer to the chain of command', 'low',
            'Référer toute décision à la chaîne de commandement.',
            'Refer any decision to the chain of command.'),
        'A-SJA' => $m('COM', 'Consulter le conseiller juridique avant toute action', 'Consult the legal adviser first', 'medium',
            'Consulter le conseiller juridique de la force avant toute action.',
            'Consult the force legal adviser before any action.'),
    ],

    // Modèles d'avis : combinaisons de codes prêtes. « type » et « armed » sont des suggestions, jamais imposées.
    'templates' => [
        'cell_financier' => ['fr' => 'Financeur de cellule', 'en' => 'Cell financier', 'type' => 'arrest', 'threat' => 'high', 'armed' => 'no',
            'reasons' => ['M-FIN-01', 'M-FIN-02', 'M-FIN-03'], 'measures' => ['A-OBS', 'A-NOI', 'A-REP', 'A-SEZ']],
        'smuggler' => ['fr' => 'Passeur', 'en' => 'Smuggler', 'type' => 'surveillance', 'threat' => 'medium', 'armed' => 'unknown',
            'reasons' => ['M-TRF-02', 'M-EVA-02'], 'measures' => ['A-EXF', 'A-CHK', 'A-FRK', 'A-REP']],
        'cell_leader' => ['fr' => 'Chef de cellule', 'en' => 'Cell leader', 'type' => 'hvt', 'threat' => 'high', 'armed' => 'yes',
            'reasons' => ['M-INS-01', 'M-INS-02', 'M-TER-02'], 'measures' => ['A-DNG', 'A-NOI', 'A-QRF', 'A-LIV', 'A-REP']],
        'bomb_maker' => ['fr' => 'Artificier', 'en' => 'Bomb maker', 'type' => 'capture_alive', 'threat' => 'critical', 'armed' => 'yes',
            'reasons' => ['M-TER-03', 'M-TRF-05'], 'measures' => ['A-DNG', 'A-IED', 'A-NOI', 'A-ISR', 'A-LIV']],
        'recruiter' => ['fr' => 'Recruteur', 'en' => 'Recruiter', 'type' => 'surveillance', 'threat' => 'medium', 'armed' => 'no',
            'reasons' => ['M-TER-04', 'M-TER-06'], 'measures' => ['A-OBS', 'A-PAT', 'A-TRK', 'A-REP']],
        'arms_dealer' => ['fr' => 'Trafiquant d’armes', 'en' => 'Arms dealer', 'type' => 'arrest', 'threat' => 'high', 'armed' => 'yes',
            'reasons' => ['M-TRF-01', 'M-TRF-06'], 'measures' => ['A-DNG', 'A-FRK', 'A-SEZ', 'A-QRF']],
        'hostile_agent' => ['fr' => 'Agent de renseignement hostile', 'en' => 'Hostile intelligence agent', 'type' => 'surveillance', 'threat' => 'medium', 'armed' => 'unknown',
            'reasons' => ['M-ESP-01', 'M-ESP-04'], 'measures' => ['A-OBS', 'A-PAT', 'A-NOI', 'A-CHN']],
        'escaped' => ['fr' => 'Évadé de détention', 'en' => 'Detention escapee', 'type' => 'escaped', 'threat' => 'high', 'armed' => 'unknown',
            'reasons' => ['M-EVA-01'], 'measures' => ['A-DNG', 'A-CHK', 'A-ARR', 'A-DET', 'A-REP']],
        'key_witness' => ['fr' => 'Témoin clé', 'en' => 'Key witness', 'type' => 'info', 'threat' => 'low', 'armed' => 'no',
            'reasons' => ['M-INT-02'], 'measures' => ['A-IDV', 'A-REP', 'A-CHN']],
        'hvt_associate' => ['fr' => 'Contact d’une cible de haute valeur', 'en' => 'HVT associate', 'type' => 'poi', 'threat' => 'medium', 'armed' => 'unknown',
            'reasons' => ['M-INT-01'], 'measures' => ['A-OBS', 'A-REP', 'A-BIO']],
    ],

    // Codes préremplis par type d'avis (type fourni ou propre à la communauté : sans entrée, rien n'est prérempli).
    'type_defaults' => [
        'arrest' => ['reasons' => [], 'measures' => ['A-NOI', 'A-ARR', 'A-REP']],
        'hvt' => ['reasons' => [], 'measures' => ['A-DNG', 'A-NOI', 'A-QRF', 'A-REP']],
        'capture_alive' => ['reasons' => [], 'measures' => ['A-DNG', 'A-LIV', 'A-NOI', 'A-REP']],
        'capture_or_kill' => ['reasons' => [], 'measures' => ['A-DNG', 'A-NEU', 'A-NOI', 'A-QRF']],
        'poi' => ['reasons' => ['M-INT-01'], 'measures' => ['A-OBS', 'A-REP']],
        'info' => ['reasons' => ['M-INT-02'], 'measures' => ['A-IDV', 'A-REP']],
        'surveillance' => ['reasons' => [], 'measures' => ['A-OBS', 'A-PAT', 'A-NOI']],
        'escaped' => ['reasons' => ['M-EVA-01'], 'measures' => ['A-DNG', 'A-CHK', 'A-REP']],
    ],
];
