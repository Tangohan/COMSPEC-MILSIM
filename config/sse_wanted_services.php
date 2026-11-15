<?php

declare(strict_types=1);

/**
 * Catalogue des services demandeurs des avis de recherche SSE (jeu de rôle, forces spéciales américaines).
 *
 * TOUS FICTIFS : aucun nom d'organisation, d'agence, d'unité ou de service réel, ni sigle, sceau, logo, adresse ou numéro réel.
 * Coordonnées fictives seulement : indicatifs radio inventés, lignes « 555 01xx » (plage réservée à la fiction), domaines
 * « .invalid ». Un test statique (SseWantedServicesCatalogTest) refuse tout nom de la liste de refus (SseWantedRealNames).
 * Vocabulaire de forces spéciales américaines (task force, fusion cell, theater cell), jamais de vocabulaire de police française.
 *
 * Forme d'un service (clé = code stable, ne change jamais une fois livré) :
 *   fam    famille (clé de 'families')
 *   fr/en  nom ; ac = sigle fictif ; roles = qui émet (request : demande de recherche, warrant : mandat, alert : alerte)
 *   auth_fr/auth_en  autorité émettrice (surcharge de l'autorité de la communauté)
 *   radio/tel/mail   canaux fictifs ; max = classification maximale émissible (clé de SseUsClassification::SCALE)
 *   class/thr/exp    valeurs par défaut : classification, niveau de menace, durée de validité en jours (0 : sans expiration)
 *   threats/types    niveaux de menace et types d'avis usuels (types : clés de SseWantedCatalog::TYPES)
 *   color/emblem     couleur d'en-tête (clé de 'palette') et forme vectorielle générique (clé de 'emblems')
 *   lang             langues de rédaction
 * Les champs absents reprennent les valeurs de leur famille.
 */
return [
    'palette' => [
        'navy' => '#1F3A5F',
        'slate' => '#3B4A5A',
        'teal' => '#1D5C63',
        'olive' => '#4B5A2E',
        'burgundy' => '#6B2737',
        'amber' => '#8A5A12',
        'plum' => '#4F3566',
        'graphite' => '#2E2E33',
    ],
    'emblems' => ['hex', 'circle', 'diamond', 'bars', 'chevron', 'cross', 'ring'],
    'families' => [
        'coord' => [
            'fr' => 'Coordination du renseignement', 'en' => 'Intelligence coordination',
            'max' => 'tres_restreint', 'class' => 'confidentiel', 'thr' => 'high', 'exp' => 30, 'color' => 'navy', 'emblem' => 'hex',
            'threats' => ['medium', 'high', 'critical'], 'types' => ['hvt', 'poi', 'info', 'surveillance'],
            'ins_fr' => 'Transmettre toute observation à la cellule de coordination par le canal indiqué. Aucune action sans ordre de la chaîne de commandement.',
            'ins_en' => 'Pass any sighting to the coordination cell on the channel shown. No action without orders from the chain of command.',
        ],
        'command' => [
            'fr' => 'Commandements opérationnels', 'en' => 'Operational commands',
            'max' => 'tres_restreint', 'class' => 'confidentiel', 'thr' => 'high', 'exp' => 14, 'color' => 'slate', 'emblem' => 'chevron',
            'threats' => ['high', 'critical'], 'types' => ['hvt', 'capture_alive', 'capture_or_kill', 'arrest'],
            'ins_fr' => 'Signaler au centre des opérations de la task force. Engagement soumis à l’autorisation du commandant de la task force.',
            'ins_en' => 'Report to the task force operations center. Engagement requires the task force commander’s authorization.',
        ],
        'ct' => [
            'fr' => 'Contre-terrorisme et contre-prolifération', 'en' => 'Counter-terrorism and counter-proliferation',
            'max' => 'tres_restreint', 'class' => 'confidentiel', 'thr' => 'critical', 'exp' => 14, 'color' => 'burgundy', 'emblem' => 'diamond',
            'threats' => ['high', 'critical'], 'types' => ['hvt', 'capture_alive', 'capture_or_kill', 'surveillance'],
            'ins_fr' => 'Ne pas approcher le sujet sans appui. Signaler la position et la composition du groupe à la cellule émettrice.',
            'ins_en' => 'Do not approach the subject without support. Report position and group composition to the issuing cell.',
        ],
        'fin' => [
            'fr' => 'Finances illicites', 'en' => 'Illicit finance',
            'max' => 'confidentiel', 'class' => 'encadrement', 'thr' => 'medium', 'exp' => 60, 'color' => 'amber', 'emblem' => 'bars',
            'threats' => ['low', 'medium', 'high'], 'types' => ['poi', 'info', 'surveillance', 'arrest'],
            'ins_fr' => 'Noter les mouvements de fonds, véhicules et contacts observés ; transmettre au bureau émetteur sans alerter le sujet.',
            'ins_en' => 'Note fund movements, vehicles and contacts seen; pass them to the issuing office without alerting the subject.',
        ],
        'cint' => [
            'fr' => 'Contre-espionnage', 'en' => 'Counterintelligence',
            'max' => 'tres_restreint', 'class' => 'confidentiel', 'thr' => 'high', 'exp' => 45, 'color' => 'plum', 'emblem' => 'ring',
            'threats' => ['medium', 'high', 'critical'], 'types' => ['poi', 'surveillance', 'info', 'hvt'],
            'ins_fr' => 'Ne pas interroger le sujet. Consigner les contacts et les lieux, puis les remettre au service émetteur.',
            'ins_en' => 'Do not question the subject. Log contacts and locations, then hand them to the issuing service.',
        ],
        'fp' => [
            'fr' => 'Protection de la force', 'en' => 'Force protection',
            'max' => 'confidentiel', 'class' => 'encadrement', 'thr' => 'medium', 'exp' => 14, 'color' => 'olive', 'emblem' => 'cross',
            'threats' => ['low', 'medium', 'high'], 'types' => ['poi', 'info', 'surveillance', 'arrest'],
            'ins_fr' => 'Refuser l’accès aux emprises, retenir le sujet à la porte et alerter l’officier de sécurité de la base.',
            'ins_en' => 'Deny access to the installation, hold the subject at the gate and alert the base security officer.',
        ],
        'mil' => [
            'fr' => 'Enquêtes criminelles militaires et police militaire', 'en' => 'Military criminal investigations and military police',
            'max' => 'encadrement', 'class' => 'encadrement', 'thr' => 'medium', 'exp' => 30, 'color' => 'graphite', 'emblem' => 'bars',
            'threats' => ['low', 'medium', 'high'], 'types' => ['arrest', 'escaped', 'poi', 'info'],
            'ins_fr' => 'Appréhender sur ordre du prévôt de la force et remettre le sujet à la police militaire, avec le rapport de capture.',
            'ins_en' => 'Apprehend on the provost marshal’s order and hand the subject to military police with the capture report.',
        ],
        'theatre' => [
            'fr' => 'Cellules de théâtre', 'en' => 'Theater cells',
            'max' => 'confidentiel', 'class' => 'confidentiel', 'thr' => 'high', 'exp' => 21, 'color' => 'teal', 'emblem' => 'circle',
            'threats' => ['medium', 'high', 'critical'], 'types' => ['hvt', 'capture_alive', 'poi', 'info', 'surveillance'],
            'ins_fr' => 'Signaler à la cellule de théâtre sur le réseau de la zone. Les patrouilles ne s’engagent qu’avec un ordre de mission valide.',
            'ins_en' => 'Report to the theater cell on the area net. Patrols engage only with a valid mission order.',
        ],
        'allies' => [
            'fr' => 'Bureaux alliés et coalition', 'en' => 'Allied and coalition offices',
            'max' => 'encadrement', 'class' => 'encadrement', 'thr' => 'medium', 'exp' => 30, 'color' => 'slate', 'emblem' => 'diamond',
            'threats' => ['low', 'medium', 'high'], 'types' => ['poi', 'info', 'arrest', 'surveillance'],
            'ins_fr' => 'Avis partagé au titre de la coalition : toute information passe par le bureau de liaison, jamais par contact direct.',
            'ins_en' => 'Notice shared under the coalition: all information goes through the liaison office, never by direct contact.',
        ],
        'civil' => [
            'fr' => 'Sécurité d’organisations civiles', 'en' => 'Civilian organization security',
            'max' => 'interne', 'class' => 'interne', 'thr' => 'low', 'exp' => 30, 'color' => 'amber', 'emblem' => 'ring',
            'threats' => ['low', 'medium'], 'types' => ['poi', 'info'],
            'ins_fr' => 'Avis de vigilance : informer le responsable de la sécurité de l’organisation. Aucune intervention des forces.',
            'ins_en' => 'Vigilance notice: inform the organization’s security manager. No force intervention.',
        ],
    ],
    'services' => [
        // --- Coordination du renseignement
        'SVC-JIFC' => ['fam' => 'coord', 'fr' => 'Cellule interarmées de fusion du renseignement', 'en' => 'Joint Intelligence Fusion Cell', 'ac' => 'JIFC', 'roles' => ['request', 'alert'], 'auth_fr' => 'Cellule interarmées de fusion du renseignement (fictive)', 'auth_en' => 'Joint Intelligence Fusion Cell (fictional)', 'radio' => 'ORION CONTROL · canal TAC 7', 'tel' => '+1 555 0101', 'mail' => 'veille@jifc.invalid'],
        'SVC-ICH' => ['fam' => 'coord', 'fr' => 'Centre de coordination du renseignement', 'en' => 'Intelligence Coordination Hub', 'ac' => 'ICH', 'roles' => ['request'], 'auth_fr' => 'Centre de coordination du renseignement (fictif)', 'auth_en' => 'Intelligence Coordination Hub (fictional)', 'radio' => 'LANTERN BASE · canal TAC 9', 'tel' => '+1 555 0102', 'mail' => 'bureau@icc.invalid', 'max' => 'confidentiel'],
        'SVC-CRC' => ['fam' => 'coord', 'fr' => 'Cellule de coordination des recherches', 'en' => 'Search Coordination Cell', 'ac' => 'CCR', 'roles' => ['request', 'alert'], 'auth_fr' => 'Cellule de coordination des recherches (fictive)', 'auth_en' => 'Search Coordination Cell (fictional)', 'radio' => 'SEEKER NET · canal TAC 4', 'tel' => '+1 555 0103', 'mail' => 'recherches@ccr.invalid', 'class' => 'encadrement', 'thr' => 'medium', 'exp' => 21, 'max' => 'confidentiel'],
        'SVC-AIC' => ['fam' => 'coord', 'fr' => 'Bureau d’analyse et de fusion tactique', 'en' => 'Tactical Analysis and Fusion Office', 'ac' => 'TAFO', 'roles' => ['request'], 'auth_fr' => 'Bureau d’analyse et de fusion tactique (fictif)', 'auth_en' => 'Tactical Analysis and Fusion Office (fictional)', 'radio' => 'PRISM ACTUAL · canal TAC 2', 'tel' => '+1 555 0104', 'mail' => 'analyse@tafo.invalid', 'color' => 'teal', 'emblem' => 'diamond'],
        'SVC-IPG' => ['fam' => 'coord', 'fr' => 'Groupe de planification du renseignement interarmées', 'en' => 'Joint Intelligence Planning Group', 'ac' => 'JIPG', 'roles' => ['request', 'warrant'], 'auth_fr' => 'Groupe de planification du renseignement interarmées (fictif)', 'auth_en' => 'Joint Intelligence Planning Group (fictional)', 'radio' => 'COMPASS ROSE · canal TAC 11', 'tel' => '+1 555 0105', 'mail' => 'planif@jipg.invalid', 'color' => 'slate'],
        'SVC-OSR' => ['fam' => 'coord', 'fr' => 'Section de recoupement de sources ouvertes', 'en' => 'Open-Source Correlation Section', 'ac' => 'OSCS', 'roles' => ['request'], 'auth_fr' => 'Section de recoupement de sources ouvertes (fictive)', 'auth_en' => 'Open-Source Correlation Section (fictional)', 'radio' => 'MIRROR DESK · canal TAC 5', 'tel' => '+1 555 0106', 'mail' => 'sources@oscs.invalid', 'max' => 'encadrement', 'class' => 'encadrement', 'thr' => 'medium', 'types' => ['poi', 'info'], 'color' => 'graphite', 'emblem' => 'ring'],

        // --- Commandements opérationnels
        'SVC-SOTF-ID' => ['fam' => 'command', 'fr' => 'Task force d’opérations spéciales — Direction du renseignement', 'en' => 'Special Operations Task Force — Intelligence Directorate', 'ac' => 'SOTF-ID', 'roles' => ['request', 'warrant', 'alert'], 'auth_fr' => 'Special Operations Task Force — Direction du renseignement (fictif)', 'auth_en' => 'Special Operations Task Force — Intelligence Directorate (fictional)', 'radio' => 'HAMMER ACTUAL · canal TAC 1', 'tel' => '+1 555 0111', 'mail' => 'j2@sotf.invalid'],
        'SVC-SOTF-OPS' => ['fam' => 'command', 'fr' => 'Task force d’opérations spéciales — Centre des opérations', 'en' => 'Special Operations Task Force — Operations Center', 'ac' => 'SOTF-OC', 'roles' => ['warrant', 'alert'], 'auth_fr' => 'Special Operations Task Force — Centre des opérations (fictif)', 'auth_en' => 'Special Operations Task Force — Operations Center (fictional)', 'radio' => 'HAMMER OPS · canal TAC 3', 'tel' => '+1 555 0112', 'mail' => 'j3@sotf.invalid', 'emblem' => 'bars'],
        'SVC-JTGT' => ['fam' => 'command', 'fr' => 'Task force interarmées de ciblage', 'en' => 'Joint Targeting Task Force', 'ac' => 'JTGT', 'roles' => ['warrant', 'request'], 'auth_fr' => 'Joint Targeting Task Force (fictive)', 'auth_en' => 'Joint Targeting Task Force (fictional)', 'radio' => 'ANVIL CONTROL · canal TAC 6', 'tel' => '+1 555 0113', 'mail' => 'ciblage@jtgt.invalid', 'color' => 'burgundy'],
        'SVC-TZSOC' => ['fam' => 'command', 'fr' => 'Commandement des opérations spéciales de la zone Trident (fictif)', 'en' => 'Trident Zone Special Operations Command (fictional)', 'ac' => 'TZSOC', 'roles' => ['warrant'], 'auth_fr' => 'Commandement des opérations spéciales de la zone Trident (fictif)', 'auth_en' => 'Trident Zone Special Operations Command (fictional)', 'radio' => 'TRIDENT ACTUAL · canal TAC 8', 'tel' => '+1 555 0114', 'mail' => 'etat-major@tzsoc.invalid', 'exp' => 30, 'color' => 'navy'],
        'SVC-CTF-RAVEN' => ['fam' => 'command', 'fr' => 'Task force RAVEN — Section des cibles de valeur', 'en' => 'Task Force RAVEN — High-Value Target Section', 'ac' => 'TF-RAVEN', 'roles' => ['request', 'alert'], 'auth_fr' => 'Task Force RAVEN (fictive)', 'auth_en' => 'Task Force RAVEN (fictional)', 'radio' => 'RAVEN SIX · canal TAC 12', 'tel' => '+1 555 0115', 'mail' => 'hvt@raven.invalid', 'emblem' => 'diamond', 'color' => 'graphite', 'types' => ['hvt', 'capture_alive', 'capture_or_kill']],

        // --- Contre-terrorisme, contre-prolifération, finances illicites, contre-espionnage, protection de la force
        'SVC-CTC' => ['fam' => 'ct', 'fr' => 'Cellule de contre-terrorisme interarmées', 'en' => 'Joint Counter-Terrorism Cell', 'ac' => 'JCTC', 'roles' => ['request', 'warrant', 'alert'], 'auth_fr' => 'Cellule de contre-terrorisme interarmées (fictive)', 'auth_en' => 'Joint Counter-Terrorism Cell (fictional)', 'radio' => 'FALCON NET · canal TAC 10', 'tel' => '+1 555 0121', 'mail' => 'ct@jctc.invalid'],
        'SVC-CTI' => ['fam' => 'ct', 'fr' => 'Bureau de menaces émergentes', 'en' => 'Emerging Threats Office', 'ac' => 'ETO', 'roles' => ['request', 'alert'], 'auth_fr' => 'Bureau de menaces émergentes (fictif)', 'auth_en' => 'Emerging Threats Office (fictional)', 'radio' => 'WATCHTOWER · canal TAC 13', 'tel' => '+1 555 0122', 'mail' => 'menaces@eto.invalid', 'class' => 'confidentiel', 'thr' => 'high', 'emblem' => 'ring'],
        'SVC-CPN' => ['fam' => 'ct', 'fr' => 'Cellule de contre-prolifération', 'en' => 'Counter-Proliferation Cell', 'ac' => 'CPRC', 'roles' => ['request', 'warrant'], 'auth_fr' => 'Cellule de contre-prolifération (fictive)', 'auth_en' => 'Counter-Proliferation Cell (fictional)', 'radio' => 'KEYSTONE · canal TAC 14', 'tel' => '+1 555 0123', 'mail' => 'prolif@cprc.invalid', 'color' => 'plum', 'types' => ['hvt', 'surveillance', 'poi']],
        'SVC-CEI' => ['fam' => 'ct', 'fr' => 'Équipe d’exploitation des engins explosifs improvisés', 'en' => 'IED Exploitation Team', 'ac' => 'IEDX', 'roles' => ['request', 'alert'], 'auth_fr' => 'Équipe d’exploitation des engins explosifs improvisés (fictive)', 'auth_en' => 'IED Exploitation Team (fictional)', 'radio' => 'SPARK TANGO · canal TAC 15', 'tel' => '+1 555 0124', 'mail' => 'exploitation@iedx.invalid', 'color' => 'amber', 'emblem' => 'cross', 'types' => ['capture_alive', 'poi', 'info']],
        'SVC-HNC' => ['fam' => 'ct', 'fr' => 'Cellule de neutralisation des réseaux hostiles', 'en' => 'Hostile Network Disruption Cell', 'ac' => 'HNDC', 'roles' => ['request', 'warrant'], 'auth_fr' => 'Cellule de neutralisation des réseaux hostiles (fictive)', 'auth_en' => 'Hostile Network Disruption Cell (fictional)', 'radio' => 'WEBSTER · canal TAC 16', 'tel' => '+1 555 0125', 'mail' => 'reseaux@hndc.invalid', 'color' => 'graphite', 'emblem' => 'hex'],
        'SVC-IFC' => ['fam' => 'fin', 'fr' => 'Cellule des flux financiers illicites', 'en' => 'Illicit Financial Flows Cell', 'ac' => 'IFFC', 'roles' => ['request'], 'auth_fr' => 'Cellule des flux financiers illicites (fictive)', 'auth_en' => 'Illicit Financial Flows Cell (fictional)', 'radio' => 'LEDGER ONE · canal TAC 17', 'tel' => '+1 555 0131', 'mail' => 'flux@iffc.invalid'],
        'SVC-TFS' => ['fam' => 'fin', 'fr' => 'Section de traçage des financements', 'en' => 'Funding Trace Section', 'ac' => 'FTS', 'roles' => ['request', 'alert'], 'auth_fr' => 'Section de traçage des financements (fictive)', 'auth_en' => 'Funding Trace Section (fictional)', 'radio' => 'TALLY NET · canal TAC 18', 'tel' => '+1 555 0132', 'mail' => 'tracage@fts.invalid', 'emblem' => 'ring', 'color' => 'teal', 'max' => 'encadrement', 'class' => 'encadrement'],
        'SVC-CIB' => ['fam' => 'cint', 'fr' => 'Bureau de contre-espionnage de la force', 'en' => 'Force Counterintelligence Bureau', 'ac' => 'FCIB', 'roles' => ['request', 'warrant'], 'auth_fr' => 'Bureau de contre-espionnage de la force (fictif)', 'auth_en' => 'Force Counterintelligence Bureau (fictional)', 'radio' => 'MERIDIAN · canal TAC 19', 'tel' => '+1 555 0141', 'mail' => 'cie@fcib.invalid'],
        'SVC-CIS' => ['fam' => 'cint', 'fr' => 'Section de sécurité du renseignement', 'en' => 'Intelligence Security Section', 'ac' => 'ISEC', 'roles' => ['request', 'alert'], 'auth_fr' => 'Section de sécurité du renseignement (fictive)', 'auth_en' => 'Intelligence Security Section (fictional)', 'radio' => 'VAULT KEEPER · canal TAC 20', 'tel' => '+1 555 0142', 'mail' => 'securite@isec.invalid', 'emblem' => 'cross', 'color' => 'burgundy'],
        'SVC-ITH' => ['fam' => 'cint', 'fr' => 'Cellule des menaces internes', 'en' => 'Insider Threat Cell', 'ac' => 'INTH', 'roles' => ['alert', 'request'], 'auth_fr' => 'Cellule des menaces internes (fictive)', 'auth_en' => 'Insider Threat Cell (fictional)', 'radio' => 'MIRROR WATCH · canal TAC 21', 'tel' => '+1 555 0143', 'mail' => 'internes@itc.invalid', 'max' => 'confidentiel', 'emblem' => 'diamond'],
        'SVC-FPC' => ['fam' => 'fp', 'fr' => 'Centre de protection de la force', 'en' => 'Force Protection Center', 'ac' => 'FPC', 'roles' => ['alert', 'request'], 'auth_fr' => 'Centre de protection de la force (fictif)', 'auth_en' => 'Force Protection Center (fictional)', 'radio' => 'BULWARK · canal TAC 22', 'tel' => '+1 555 0151', 'mail' => 'garde@fpc.invalid'],
        'SVC-BSO' => ['fam' => 'fp', 'fr' => 'Bureau de sécurité des emprises', 'en' => 'Installation Security Office', 'ac' => 'INSEC', 'roles' => ['alert'], 'auth_fr' => 'Bureau de sécurité des emprises (fictif)', 'auth_en' => 'Installation Security Office (fictional)', 'radio' => 'GATEKEEPER · canal TAC 23', 'tel' => '+1 555 0152', 'mail' => 'acces@insec.invalid', 'max' => 'encadrement', 'class' => 'encadrement', 'emblem' => 'bars', 'color' => 'slate'],
        'SVC-CPQ' => ['fam' => 'fp', 'fr' => 'Cellule de protection des convois', 'en' => 'Convoy Protection Cell', 'ac' => 'CPCV', 'roles' => ['alert', 'request'], 'auth_fr' => 'Cellule de protection des convois (fictive)', 'auth_en' => 'Convoy Protection Cell (fictional)', 'radio' => 'ROADRUNNER · canal TAC 24', 'tel' => '+1 555 0153', 'mail' => 'convois@cpcv.invalid', 'types' => ['poi', 'info', 'surveillance'], 'emblem' => 'chevron'],

        // --- Enquêtes criminelles militaires et police militaire
        'SVC-MCIU' => ['fam' => 'mil', 'fr' => 'Unité d’enquêtes criminelles militaires', 'en' => 'Military Criminal Investigation Unit', 'ac' => 'MCIU', 'roles' => ['request', 'warrant'], 'auth_fr' => 'Unité d’enquêtes criminelles militaires (fictive)', 'auth_en' => 'Military Criminal Investigation Unit (fictional)', 'radio' => 'DOCKET · canal TAC 25', 'tel' => '+1 555 0161', 'mail' => 'enquetes@mciu.invalid'],
        'SVC-PMO' => ['fam' => 'mil', 'fr' => 'Bureau du prévôt de la force', 'en' => 'Force Provost Marshal Office', 'ac' => 'FPMO', 'roles' => ['warrant', 'alert'], 'auth_fr' => 'Bureau du prévôt de la force (fictif)', 'auth_en' => 'Force Provost Marshal Office (fictional)', 'radio' => 'SENTINEL SIX · canal TAC 26', 'tel' => '+1 555 0162', 'mail' => 'prevot@fpmo.invalid', 'emblem' => 'cross'],
        'SVC-MPD' => ['fam' => 'mil', 'fr' => 'Détachement de police militaire de théâtre', 'en' => 'Theater Military Police Detachment', 'ac' => 'TMPD', 'roles' => ['alert', 'warrant'], 'auth_fr' => 'Détachement de police militaire de théâtre (fictif)', 'auth_en' => 'Theater Military Police Detachment (fictional)', 'radio' => 'BADGER NET · canal TAC 27', 'tel' => '+1 555 0163', 'mail' => 'pm@tmpd.invalid', 'types' => ['arrest', 'escaped'], 'emblem' => 'chevron'],
        'SVC-DCF' => ['fam' => 'mil', 'fr' => 'Centre de détention de la force — Section évasions', 'en' => 'Force Detention Center — Escape Response Section', 'ac' => 'FDC-ERS', 'roles' => ['alert', 'request'], 'auth_fr' => 'Centre de détention de la force (fictif)', 'auth_en' => 'Force Detention Center (fictional)', 'radio' => 'CELLBLOCK · canal TAC 28', 'tel' => '+1 555 0164', 'mail' => 'evasions@fdc.invalid', 'types' => ['escaped'], 'thr' => 'high', 'class' => 'encadrement', 'exp' => 7, 'color' => 'burgundy'],

        // --- Cellules de théâtre
        'SVC-TTC-EA' => ['fam' => 'theatre', 'fr' => 'Cellule de ciblage de théâtre — Afrique de l’Est', 'en' => 'Theater Targeting Cell — East Africa', 'ac' => 'TTC-EA', 'roles' => ['request', 'warrant', 'alert'], 'auth_fr' => 'Cellule de ciblage de théâtre — Afrique de l’Est (fictive)', 'auth_en' => 'Theater Targeting Cell — East Africa (fictional)', 'radio' => 'SAVANNA ACTUAL · canal TAC 31', 'tel' => '+1 555 0171', 'mail' => 'ciblage@ttc-ea.invalid'],
        'SVC-TTC-GC' => ['fam' => 'theatre', 'fr' => 'Cellule de ciblage de théâtre — Golfe et Levant', 'en' => 'Theater Targeting Cell — Gulf and Levant', 'ac' => 'TTC-GL', 'roles' => ['request', 'warrant'], 'auth_fr' => 'Cellule de ciblage de théâtre — Golfe et Levant (fictive)', 'auth_en' => 'Theater Targeting Cell — Gulf and Levant (fictional)', 'radio' => 'DUNE ACTUAL · canal TAC 32', 'tel' => '+1 555 0172', 'mail' => 'ciblage@ttc-gl.invalid', 'color' => 'amber'],
        'SVC-TTC-CA' => ['fam' => 'theatre', 'fr' => 'Cellule de ciblage de théâtre — Asie centrale', 'en' => 'Theater Targeting Cell — Central Asia', 'ac' => 'TTC-CA', 'roles' => ['request', 'alert'], 'auth_fr' => 'Cellule de ciblage de théâtre — Asie centrale (fictive)', 'auth_en' => 'Theater Targeting Cell — Central Asia (fictional)', 'radio' => 'STEPPE ACTUAL · canal TAC 33', 'tel' => '+1 555 0173', 'mail' => 'ciblage@ttc-ca.invalid', 'color' => 'olive'],
        'SVC-TTC-AM' => ['fam' => 'theatre', 'fr' => 'Cellule de ciblage de théâtre — Archipel (Pacifique)', 'en' => 'Theater Targeting Cell — Archipelago (Pacific)', 'ac' => 'TTC-AP', 'roles' => ['request'], 'auth_fr' => 'Cellule de ciblage de théâtre — Archipel (fictive)', 'auth_en' => 'Theater Targeting Cell — Archipelago (fictional)', 'radio' => 'REEF ACTUAL · canal TAC 34', 'tel' => '+1 555 0174', 'mail' => 'ciblage@ttc-ap.invalid', 'color' => 'teal'],
        'SVC-BRC' => ['fam' => 'theatre', 'fr' => 'Bureau régional de recherche de personnes', 'en' => 'Regional Personnel Recovery and Search Bureau', 'ac' => 'RPRB', 'roles' => ['request', 'alert'], 'auth_fr' => 'Bureau régional de recherche de personnes (fictif)', 'auth_en' => 'Regional Personnel Recovery and Search Bureau (fictional)', 'radio' => 'LIGHTHOUSE · canal TAC 35', 'tel' => '+1 555 0175', 'mail' => 'recherche@rprb.invalid', 'class' => 'encadrement', 'thr' => 'medium', 'max' => 'confidentiel', 'types' => ['poi', 'info', 'escaped']],

        // --- Bureaux alliés et coalition
        'SVC-COL-LNO' => ['fam' => 'allies', 'fr' => 'Bureau de liaison de la coalition', 'en' => 'Coalition Liaison Office', 'ac' => 'CLO', 'roles' => ['request', 'alert'], 'auth_fr' => 'Bureau de liaison de la coalition (fictif)', 'auth_en' => 'Coalition Liaison Office (fictional)', 'radio' => 'BRIDGE · canal COAL 1', 'tel' => '+1 555 0181', 'mail' => 'liaison@clo.invalid', 'lang' => ['en', 'fr']],
        'SVC-ALL-NB' => ['fam' => 'allies', 'fr' => 'Bureau du renseignement de l’alliance nordique (fictif)', 'en' => 'Nordic Alliance Intelligence Office (fictional)', 'ac' => 'NAIO', 'roles' => ['request'], 'auth_fr' => 'Bureau du renseignement de l’alliance nordique (fictif)', 'auth_en' => 'Nordic Alliance Intelligence Office (fictional)', 'radio' => 'AURORA · canal COAL 2', 'tel' => '+1 555 0182', 'mail' => 'bureau@naio.invalid', 'lang' => ['en'], 'color' => 'teal'],
        'SVC-ALL-SC' => ['fam' => 'allies', 'fr' => 'Cellule de partage du renseignement du Pacifique sud (fictive)', 'en' => 'South Pacific Intelligence Sharing Cell (fictional)', 'ac' => 'SPISC', 'roles' => ['request', 'alert'], 'auth_fr' => 'Cellule de partage du renseignement du Pacifique sud (fictive)', 'auth_en' => 'South Pacific Intelligence Sharing Cell (fictional)', 'radio' => 'COBALT · canal COAL 3', 'tel' => '+1 555 0183', 'mail' => 'partage@spisc.invalid', 'lang' => ['en'], 'color' => 'navy', 'emblem' => 'ring'],
        'SVC-ALL-MED' => ['fam' => 'allies', 'fr' => 'Centre de coordination méditerranéen de la coalition (fictif)', 'en' => 'Coalition Mediterranean Coordination Center (fictional)', 'ac' => 'CMCC', 'roles' => ['request', 'warrant'], 'auth_fr' => 'Centre de coordination méditerranéen de la coalition (fictif)', 'auth_en' => 'Coalition Mediterranean Coordination Center (fictional)', 'radio' => 'HARBOR · canal COAL 4', 'tel' => '+1 555 0184', 'mail' => 'coord@cmcc.invalid', 'lang' => ['fr', 'en'], 'color' => 'burgundy', 'emblem' => 'hex'],

        // --- Sécurité d'organisations civiles fictives
        'SVC-NGO-SEC' => ['fam' => 'civil', 'fr' => 'Direction de la sécurité de l’Alliance humanitaire Horizon (fictive)', 'en' => 'Horizon Humanitarian Alliance Security Directorate (fictional)', 'ac' => 'HHA-SEC', 'roles' => ['alert'], 'auth_fr' => 'Alliance humanitaire Horizon (fictive)', 'auth_en' => 'Horizon Humanitarian Alliance (fictional)', 'radio' => 'HORIZON BASE · canal ONG 1', 'tel' => '+1 555 0191', 'mail' => 'securite@horizon-alliance.invalid', 'emblem' => 'circle'],
        'SVC-CORP-SEC' => ['fam' => 'civil', 'fr' => 'Sécurité du groupe Meridian Logistics (fictif)', 'en' => 'Meridian Logistics Group Security (fictional)', 'ac' => 'MLG-SEC', 'roles' => ['alert', 'request'], 'auth_fr' => 'Meridian Logistics Group (fictif)', 'auth_en' => 'Meridian Logistics Group (fictional)', 'radio' => 'MERIDIAN OPS · canal COM 2', 'tel' => '+1 555 0192', 'mail' => 'securite@meridian-logistics.invalid', 'color' => 'graphite', 'emblem' => 'bars'],
        'SVC-PORT-SEC' => ['fam' => 'civil', 'fr' => 'Sécurité de l’autorité portuaire de Kestrel Bay (fictive)', 'en' => 'Kestrel Bay Port Authority Security (fictional)', 'ac' => 'KBPA-SEC', 'roles' => ['alert'], 'auth_fr' => 'Autorité portuaire de Kestrel Bay (fictive)', 'auth_en' => 'Kestrel Bay Port Authority (fictional)', 'radio' => 'QUAYSIDE · canal COM 3', 'tel' => '+1 555 0193', 'mail' => 'securite@kestrel-port.invalid', 'color' => 'teal', 'emblem' => 'chevron'],
        'SVC-MED-SEC' => ['fam' => 'civil', 'fr' => 'Sécurité du réseau hospitalier Sunrise (fictif)', 'en' => 'Sunrise Hospital Network Security (fictional)', 'ac' => 'SHN-SEC', 'roles' => ['alert'], 'auth_fr' => 'Réseau hospitalier Sunrise (fictif)', 'auth_en' => 'Sunrise Hospital Network (fictional)', 'radio' => 'SUNRISE CALL · canal COM 4', 'tel' => '+1 555 0194', 'mail' => 'securite@sunrise-health.invalid', 'color' => 'olive', 'emblem' => 'cross', 'types' => ['poi', 'info']],
    ],
];
