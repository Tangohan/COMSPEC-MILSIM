<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catalogue LIVRÉ des étiquettes de style américain des fiches de renseignement (bureau SSE).
 *
 * Les sigles sont ceux de la doctrine publique du renseignement militaire (disciplines de recueil, besoins en renseignement,
 * types de comptes rendus, marquages de diffusion) : aucun sigle inventé, aucune organisation réelle citée comme émettrice.
 * Chaque communauté active, désactive, renomme ou complète ce catalogue depuis « Gestion des fiches » ; ses réglages sont
 * rangés dans sse_tags (portée « field_note »), la liaison fiche ↔ étiquette dans sse_tag_links.
 *
 * Deux familles ne se choisissent pas : la priorité de traitement suit l'urgence de la fiche (mêmes clés stockées) et
 * l'évaluation rappelle la cotation A–F / 1–6 déjà portée par la fiche. Les catégories existantes
 * (SseFieldNoteCatalog::THEMES) gardent leurs clés : elles forment la famille « category ».
 */
final class SseFieldNoteTagCatalog
{
    /** Portée des étiquettes de fiches dans la table partagée sse_tags (les rattachements utilisent « attach »). */
    public const SCOPE = 'field_note';

    public const OBJECT_NOTE = 'note';

    public const TAGS_PER_NOTE = 16;

    public const CODE_MAX = 32;

    public const SLUG_MAX = 40;

    public const LABEL_MAX = 80;

    public const DESCRIPTION_MAX = 200;

    public const QUALIFIER_MAX = 80;

    public const CUSTOM_MAX = 300;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_ARCHIVED = 'archived';

    /** @var array<string, string> */
    public const STATUSES = ['active' => 'Active', 'disabled' => 'Désactivée', 'archived' => 'Archivée'];

    /** Couleurs sobres (mêmes clés que les étiquettes des rattachements). @var array<string, array{label: string, hex: string}> */
    public const COLORS = [
        'slate' => ['label' => 'Ardoise', 'hex' => '#64748b'],
        'green' => ['label' => 'Vert', 'hex' => '#2f855a'],
        'blue' => ['label' => 'Bleu', 'hex' => '#2b6cb0'],
        'amber' => ['label' => 'Ambre', 'hex' => '#b7791f'],
        'red' => ['label' => 'Rouge', 'hex' => '#c53030'],
        'violet' => ['label' => 'Violet', 'hex' => '#6b46c1'],
    ];

    /**
     * Familles. « pick » : l'étiquette se choisit sur une fiche ; « doc » : où elle s'écrit sur le document.
     *
     * @var array<string, array{fr: string, en: string, hint_fr: string, hint_en: string, pick: bool, doc: string}>
     */
    public const FAMILIES = [
        'discipline' => [
            'fr' => 'Discipline de recueil', 'en' => 'Collection discipline',
            'hint_fr' => 'Par quel moyen le renseignement a été recueilli.', 'hint_en' => 'How the information was collected.',
            'pick' => true, 'doc' => 'block',
        ],
        'requirement' => [
            'fr' => 'Besoin en renseignement', 'en' => 'Intelligence requirement',
            'hint_fr' => 'La question du commandement à laquelle la fiche répond, avec son numéro.', 'hint_en' => 'The command question this record answers, with its number.',
            'pick' => true, 'doc' => 'block',
        ],
        'product' => [
            'fr' => 'Type de produit', 'en' => 'Product type',
            'hint_fr' => 'Le format de compte rendu que la fiche suit.', 'hint_en' => 'The report format this record follows.',
            'pick' => true, 'doc' => 'block',
        ],
        'theme' => [
            'fr' => 'Thèmes et menaces', 'en' => 'Themes and threats',
            'hint_fr' => 'De quoi parle la fiche : menace, réseau, population, infrastructure.', 'hint_en' => 'What the record is about: threat, network, population, infrastructure.',
            'pick' => true, 'doc' => 'chips',
        ],
        'framework' => [
            'fr' => 'Grille d’analyse', 'en' => 'Analytic framework',
            'hint_fr' => 'Rubriques ASCOPE et PMESII-PT de l’environnement opérationnel.', 'hint_en' => 'ASCOPE and PMESII-PT headings of the operational environment.',
            'pick' => true, 'doc' => 'chips',
        ],
        'dissemination' => [
            'fr' => 'Diffusion et contrôle', 'en' => 'Dissemination and control',
            'hint_fr' => 'Mentions écrites sur la fiche, à la suite de sa classification.', 'hint_en' => 'Markings written on the record, after its classification.',
            'pick' => true, 'doc' => 'banner',
        ],
        'validity' => [
            'fr' => 'Temporalité et validité', 'en' => 'Timeliness and validity',
            'hint_fr' => 'Jusqu’à quand le renseignement vaut, et s’il est recoupé.', 'hint_en' => 'How long the information holds, and whether it is corroborated.',
            'pick' => true, 'doc' => 'validity',
        ],
        'category' => [
            'fr' => 'Catégories', 'en' => 'Categories',
            'hint_fr' => 'Les catégories de fiche du bureau, en pastilles de couleur.', 'hint_en' => 'The office record categories, shown as coloured chips.',
            'pick' => true, 'doc' => 'chips',
        ],
        'precedence' => [
            'fr' => 'Priorité de traitement', 'en' => 'Message precedence',
            'hint_fr' => 'Suit l’urgence de la fiche : elle ne se choisit pas à part.', 'hint_en' => 'Follows the record urgency: it is not picked separately.',
            'pick' => false, 'doc' => 'cartouche',
        ],
        'evaluation' => [
            'fr' => 'Évaluation', 'en' => 'Evaluation',
            'hint_fr' => 'Rappel de la cotation de la fiche : source A à F, information 1 à 6.', 'hint_en' => 'Reminder of the record rating: source A to F, information 1 to 6.',
            'pick' => false, 'doc' => 'evaluation',
        ],
    ];

    /**
     * Étiquettes livrées : code, famille, libellé FR, libellé EN, description FR, description EN, couleur, complément
     * (« » aucun, « number » numéro du besoin, « text » texte libre, « date » échéance), active par défaut.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string, 8: bool}>
     */
    public const TAGS = [
        // —— Disciplines de recueil ——
        ['HUMINT', 'discipline', 'Renseignement d’origine humaine', 'Human intelligence', 'Témoignage, entretien, contact ou source humaine.', 'Statement, interview, contact or human source.', 'blue', '', true],
        ['SIGINT', 'discipline', 'Renseignement d’origine électromagnétique', 'Signals intelligence', 'Interception de communications et de signaux.', 'Interception of communications and signals.', 'blue', '', true],
        ['GEOINT', 'discipline', 'Renseignement géospatial', 'Geospatial intelligence', 'Imagerie et données de terrain situées sur la carte.', 'Imagery and terrain data placed on the map.', 'blue', '', true],
        ['IMINT', 'discipline', 'Renseignement d’origine image', 'Imagery intelligence', 'Photographie, vidéo, drone ou imagerie aérienne.', 'Photograph, video, drone or aerial imagery.', 'blue', '', true],
        ['MASINT', 'discipline', 'Mesures et signatures', 'Measurement and signature intelligence', 'Détection acoustique, sismique, chimique ou radiologique.', 'Acoustic, seismic, chemical or radiological detection.', 'blue', '', true],
        ['OSINT', 'discipline', 'Sources ouvertes', 'Open-source intelligence', 'Presse, réseaux sociaux et documents publics.', 'Press, social media and public documents.', 'blue', '', true],
        ['TECHINT', 'discipline', 'Renseignement technique', 'Technical intelligence', 'Étude d’un matériel, d’une arme ou d’un engin saisi.', 'Study of captured equipment, weapon or device.', 'blue', '', true],
        ['CI', 'discipline', 'Contre-ingérence', 'Counterintelligence', 'Activité de renseignement adverse visant la force.', 'Hostile intelligence activity aimed at the force.', 'blue', '', true],
        ['DOMEX', 'discipline', 'Exploitation de documents et de supports', 'Document and media exploitation', 'Téléphones, ordinateurs et supports saisis sur un site.', 'Phones, computers and media seized on a site.', 'blue', '', true],
        ['DOCEX', 'discipline', 'Exploitation de documents', 'Document exploitation', 'Papiers, carnets et pièces écrites relevés sur place.', 'Papers, notebooks and written items collected on site.', 'blue', '', true],
        ['BIOMETRICS', 'discipline', 'Biométrie', 'Biometrics', 'Empreintes, iris et visage relevés sur une personne.', 'Fingerprints, iris and face collected from a person.', 'blue', '', true],
        ['FORENSICS', 'discipline', 'Criminalistique', 'Forensics', 'Traces et indices matériels analysés en laboratoire.', 'Traces and physical evidence analysed in a laboratory.', 'blue', '', true],
        ['WTI', 'discipline', 'Renseignement technique sur les armes', 'Weapons technical intelligence', 'Exploitation des engins explosifs et de leurs composants.', 'Exploitation of explosive devices and their components.', 'blue', '', true],
        // —— Besoin en renseignement servi ——
        ['CCIR', 'requirement', 'Besoin critique du commandant', 'Commander’s critical information requirement', 'Information dont le chef a besoin pour décider.', 'Information the commander needs to decide.', 'violet', 'number', true],
        ['PIR', 'requirement', 'Besoin prioritaire en renseignement', 'Priority intelligence requirement', 'Question prioritaire sur l’adversaire ou le milieu.', 'Priority question about the adversary or the environment.', 'violet', 'number', true],
        ['FFIR', 'requirement', 'Besoin d’information sur les forces amies', 'Friendly force information requirement', 'Ce que le chef doit savoir de ses propres unités.', 'What the commander must know about friendly units.', 'violet', 'number', true],
        ['EEFI', 'requirement', 'Élément ami à protéger', 'Essential element of friendly information', 'Information amie que l’adversaire ne doit pas obtenir.', 'Friendly information the adversary must not obtain.', 'violet', 'number', true],
        ['SIR', 'requirement', 'Besoin spécifique en renseignement', 'Specific information requirement', 'Question précise qui décline un besoin prioritaire.', 'Precise question that breaks down a priority requirement.', 'violet', 'number', true],
        ['EEI', 'requirement', 'Élément essentiel d’information', 'Essential element of information', 'Détail attendu pour répondre à un besoin.', 'Detail expected to answer a requirement.', 'violet', 'number', true],
        ['IR', 'requirement', 'Besoin en renseignement', 'Information requirement', 'Question générale posée au renseignement.', 'General question put to intelligence.', 'violet', 'number', true],
        ['RFI', 'requirement', 'Demande d’information', 'Request for information', 'Réponse à une demande ponctuelle d’une autre cellule.', 'Answer to a one-off request from another cell.', 'violet', 'number', true],
        // —— Type de produit ou de compte rendu ——
        ['SPOTREP', 'product', 'Compte rendu immédiat', 'Spot report', 'Fait important rapporté sans délai.', 'Significant event reported without delay.', 'slate', '', true],
        ['SALUTE', 'product', 'Compte rendu SALUTE', 'SALUTE report', 'Effectif, activité, lieu, unité, heure, équipement.', 'Size, activity, location, unit, time, equipment.', 'slate', '', true],
        ['SITREP', 'product', 'Point de situation', 'Situation report', 'État de la situation à un instant donné.', 'State of the situation at a given time.', 'slate', '', true],
        ['INTREP', 'product', 'Compte rendu de renseignement', 'Intelligence report', 'Renseignement ponctuel diffusé dès qu’il est connu.', 'Single item of intelligence issued as soon as known.', 'slate', '', true],
        ['INTSUM', 'product', 'Synthèse de renseignement', 'Intelligence summary', 'Synthèse périodique de la situation adverse.', 'Periodic summary of the adversary situation.', 'slate', '', true],
        ['IIR', 'product', 'Compte rendu d’information brute', 'Intelligence information report', 'Information brute, non évaluée, issue d’une source.', 'Raw, unevaluated information from a source.', 'slate', '', true],
        ['TACREP', 'product', 'Compte rendu tactique', 'Tactical report', 'Renseignement tactique urgent et bref.', 'Urgent, brief tactical intelligence.', 'slate', '', true],
        ['DISUM', 'product', 'Synthèse quotidienne', 'Daily intelligence summary', 'Synthèse de renseignement des dernières vingt-quatre heures.', 'Intelligence summary of the last twenty-four hours.', 'slate', '', true],
        ['BDA', 'product', 'Évaluation des dommages', 'Battle damage assessment', 'Effets constatés après une action sur un objectif.', 'Effects observed after an action on a target.', 'slate', '', true],
        ['PATROL DEBRIEF', 'product', 'Débriefing de patrouille', 'Patrol debrief', 'Ce que la patrouille a vu et appris, recueilli au retour.', 'What the patrol saw and learned, collected on return.', 'slate', '', true],
        ['SSE REPORT', 'product', 'Compte rendu d’exploitation de site', 'Sensitive site exploitation report', 'Relevé d’un site fouillé : personnes, matériels, documents.', 'Record of a searched site: persons, materiel, documents.', 'slate', '', true],
        ['TARGET PACKET', 'product', 'Dossier d’objectif', 'HVI / HVT target packet', 'Dossier d’une personne ou d’un objectif de haute valeur.', 'File on a high-value individual or target.', 'slate', '', true],
        ['LINK ANALYSIS', 'product', 'Analyse de liens', 'Link analysis', 'Schéma des relations entre personnes, lieux et objets.', 'Diagram of relations between persons, places and objects.', 'slate', '', true],
        // —— Thèmes et menaces ——
        ['IED', 'theme', 'Engin explosif improvisé', 'Improvised explosive device', 'Engin posé, découvert ou déclenché.', 'Device emplaced, found or detonated.', 'red', '', true],
        ['C-IED', 'theme', 'Lutte contre les engins explosifs', 'Counter-IED', 'Poseurs, artificiers, composants et filières.', 'Emplacers, bomb makers, components and supply lines.', 'red', '', true],
        ['WEAPONS CACHE', 'theme', 'Cache d’armes', 'Weapons cache', 'Dépôt dissimulé d’armes, de munitions ou d’explosifs.', 'Hidden store of weapons, ammunition or explosives.', 'red', '', true],
        ['FACILITATION', 'theme', 'Réseau de facilitation', 'Facilitation network', 'Passeurs, logeurs et soutiens d’un réseau.', 'Movers, safe-house keepers and supporters of a network.', 'amber', '', true],
        ['FINANCING', 'theme', 'Financement', 'Financing', 'Flux d’argent, collecteurs et circuits de transfert.', 'Money flows, collectors and transfer channels.', 'amber', '', true],
        ['RECRUITMENT', 'theme', 'Recrutement', 'Recruitment', 'Enrôlement, propagande et filières d’entraînement.', 'Enlistment, propaganda and training pipelines.', 'amber', '', true],
        ['HVI', 'theme', 'Personne de haute valeur', 'High-value individual', 'Chef, artificier ou financier dont la neutralisation pèse.', 'Leader, bomb maker or financier whose removal matters.', 'red', '', true],
        ['INSIDER THREAT', 'theme', 'Menace interne', 'Insider threat', 'Personne de confiance qui se retourne contre la force.', 'Trusted person who turns against the force.', 'red', '', true],
        ['SMUGGLING', 'theme', 'Contrebande', 'Smuggling', 'Trafic d’armes, de carburant, de personnes ou de biens.', 'Trafficking of weapons, fuel, persons or goods.', 'amber', '', true],
        ['UAS', 'theme', 'Drones', 'Unmanned aircraft systems', 'Drone observé, employé ou abattu, et ses opérateurs.', 'Drone observed, used or downed, and its operators.', 'amber', '', true],
        ['EW', 'theme', 'Guerre électronique', 'Electronic warfare', 'Brouillage, interception ou leurrage constaté.', 'Jamming, interception or spoofing observed.', 'amber', '', true],
        ['CBRN', 'theme', 'Menace CBRN', 'CBRN threat', 'Risque chimique, biologique, radiologique ou nucléaire.', 'Chemical, biological, radiological or nuclear hazard.', 'red', '', true],
        ['INFRASTRUCTURE', 'theme', 'Infrastructures', 'Infrastructure', 'Ponts, routes, réseaux d’énergie, d’eau et de communication.', 'Bridges, roads, power, water and communication networks.', 'green', '', true],
        ['POPULATION', 'theme', 'Population', 'Population', 'Attitude, besoins et griefs des habitants.', 'Attitude, needs and grievances of the inhabitants.', 'green', '', true],
        ['KEY LEADERS', 'theme', 'Responsables locaux', 'Key leaders', 'Notables, élus et chefs religieux ou coutumiers.', 'Notables, elected officials and religious or traditional leaders.', 'green', '', true],
        // —— Grille d'analyse (ASCOPE, PMESII-PT) : livrée désactivée ——
        ['AREAS', 'framework', 'ASCOPE : zones', 'ASCOPE: Areas', 'Zones qui comptent pour la population ou l’adversaire.', 'Areas that matter to the population or the adversary.', 'slate', '', false],
        ['STRUCTURES', 'framework', 'ASCOPE : structures', 'ASCOPE: Structures', 'Bâtiments et ouvrages dont l’usage pèse sur la mission.', 'Buildings and works whose use affects the mission.', 'slate', '', false],
        ['CAPABILITIES', 'framework', 'ASCOPE : capacités', 'ASCOPE: Capabilities', 'Services que les autorités locales assurent ou non.', 'Services the local authorities provide or fail to provide.', 'slate', '', false],
        ['ORGANIZATIONS', 'framework', 'ASCOPE : organisations', 'ASCOPE: Organizations', 'Groupes constitués présents dans la zone.', 'Organised groups present in the area.', 'slate', '', false],
        ['PEOPLE', 'framework', 'ASCOPE : personnes', 'ASCOPE: People', 'Personnes dont l’opinion ou l’action compte.', 'People whose opinion or action counts.', 'slate', '', false],
        ['EVENTS', 'framework', 'ASCOPE : événements', 'ASCOPE: Events', 'Fêtes, marchés, élections et rassemblements.', 'Festivals, markets, elections and gatherings.', 'slate', '', false],
        ['POLITICAL', 'framework', 'PMESII-PT : politique', 'PMESII-PT: Political', 'Pouvoirs, partis et rapports de force.', 'Authorities, parties and balance of power.', 'slate', '', false],
        ['MILITARY', 'framework', 'PMESII-PT : militaire', 'PMESII-PT: Military', 'Forces armées et groupes armés de la zone.', 'Armed forces and armed groups in the area.', 'slate', '', false],
        ['ECONOMIC', 'framework', 'PMESII-PT : économie', 'PMESII-PT: Economic', 'Ressources, emploi, commerce et trafics.', 'Resources, employment, trade and trafficking.', 'slate', '', false],
        ['SOCIAL', 'framework', 'PMESII-PT : société', 'PMESII-PT: Social', 'Communautés, langues, religions et coutumes.', 'Communities, languages, religions and customs.', 'slate', '', false],
        ['INFORMATION', 'framework', 'PMESII-PT : information', 'PMESII-PT: Information', 'Médias, rumeurs et moyens de s’informer.', 'Media, rumours and ways people get informed.', 'slate', '', false],
        ['PHYSICAL ENVIRONMENT', 'framework', 'PMESII-PT : milieu physique', 'PMESII-PT: Physical environment', 'Relief, climat et saisons qui contraignent l’action.', 'Terrain, climate and seasons that constrain action.', 'slate', '', false],
        ['TIME', 'framework', 'PMESII-PT : temps', 'PMESII-PT: Time', 'Échéances et rythmes qui pèsent sur les acteurs.', 'Deadlines and rhythms that weigh on the actors.', 'slate', '', false],
        // —— Diffusion et contrôle (mentions) ——
        ['NOFORN', 'dissemination', 'Pas de diffusion à l’étranger', 'Not releasable to foreign nationals', 'La fiche ne sort pas du cercle national.', 'The record stays within the national circle.', 'red', '', true],
        ['REL TO', 'dissemination', 'Diffusable à', 'Releasable to', 'Diffusion autorisée aux partenaires nommés.', 'Release authorised to the named partners.', 'red', 'text', true],
        ['RELIDO', 'dissemination', 'Diffusion décidée par l’officier désigné', 'Releasable by information disclosure official', 'Un officier désigné peut autoriser une diffusion plus large.', 'A designated official may authorise wider release.', 'red', '', true],
        ['DISPLAY ONLY', 'dissemination', 'Présentation sans remise', 'Display only', 'Peut être montrée au partenaire nommé, sans lui être remise.', 'May be shown to the named partner, not handed over.', 'red', 'text', true],
        ['ORCON', 'dissemination', 'Diffusion contrôlée par l’émetteur', 'Originator controlled', 'Toute rediffusion demande l’accord de l’émetteur.', 'Any further release needs the originator’s consent.', 'red', '', true],
        ['PROPIN', 'dissemination', 'Information propriétaire', 'Proprietary information', 'Contient des données confiées par une entreprise.', 'Contains data entrusted by a company.', 'red', '', true],
        ['LIMDIS', 'dissemination', 'Diffusion limitée', 'Limited distribution', 'Réservée à un cercle restreint de destinataires.', 'Restricted to a small circle of recipients.', 'red', '', true],
        ['CUI', 'dissemination', 'Information non classifiée contrôlée', 'Controlled unclassified information', 'Non classifiée mais protégée ; précisez la catégorie.', 'Unclassified but protected; state the category.', 'violet', 'text', true],
        ['FOUO', 'dissemination', 'Usage officiel seulement', 'For official use only', 'Ancienne mention des documents non classifiés protégés.', 'Legacy marking of protected unclassified documents.', 'violet', '', true],
        ['HANDLE VIA', 'dissemination', 'Acheminement réservé', 'Handle via', 'La fiche ne circule que par le canal nommé.', 'The record only travels through the named channel.', 'red', 'text', true],
        // —— Temporalité et validité ——
        ['TIME-SENSITIVE', 'validity', 'Sensible au temps', 'Time-sensitive', 'À exploiter vite : la valeur baisse d’heure en heure.', 'Exploit quickly: value drops by the hour.', 'amber', '', true],
        ['PERISHABLE', 'validity', 'Périssable', 'Perishable', 'Ne vaut plus après l’échéance indiquée.', 'No longer valid after the stated deadline.', 'amber', 'date', true],
        ['HISTORICAL', 'validity', 'Historique', 'Historical', 'Fait ancien, gardé pour le contexte.', 'Past event, kept for context.', 'slate', '', true],
        ['UNCONFIRMED', 'validity', 'Non confirmé', 'Unconfirmed', 'Aucun recoupement pour l’instant.', 'No corroboration so far.', 'amber', '', true],
        ['CORROBORATED', 'validity', 'Recoupé', 'Corroborated', 'Confirmé par une autre source indépendante.', 'Confirmed by another independent source.', 'green', '', true],
        ['SINGLE SOURCE', 'validity', 'Source unique', 'Single source', 'Repose sur une seule source : prudence.', 'Rests on a single source: caution.', 'amber', '', true],
    ];

    /**
     * Priorité de traitement d'un message, reliée à l'urgence de la fiche (la clé stockée reste celle de l'urgence).
     *
     * @var array<string, array{fr: string, en: string, desc_fr: string, desc_en: string, urgency: string}>
     */
    public const PRECEDENCES = [
        'FLASH' => ['fr' => 'Flash', 'en' => 'Flash', 'desc_fr' => 'Menace imminente : passe avant tout le reste.', 'desc_en' => 'Imminent threat: handled before anything else.', 'urgency' => 'critique'],
        'IMMEDIATE' => ['fr' => 'Immédiat', 'en' => 'Immediate', 'desc_fr' => 'Situation grave : à traiter dans l’heure.', 'desc_en' => 'Grave situation: handle within the hour.', 'urgency' => 'urgent'],
        'PRIORITY' => ['fr' => 'Prioritaire', 'en' => 'Priority', 'desc_fr' => 'Utile à l’opération en cours : à traiter dans la journée.', 'desc_en' => 'Useful to the current operation: handle within the day.', 'urgency' => 'normal'],
        'ROUTINE' => ['fr' => 'Routine', 'en' => 'Routine', 'desc_fr' => 'Cours normal du travail.', 'desc_en' => 'Normal course of work.', 'urgency' => 'routine'],
    ];

    /** Fiabilité de la source (A–F) : libellé du portail et libellé américain. @var array<string, array{fr: string, en: string}> */
    public const RELIABILITY = [
        'A' => ['fr' => 'Source sûre', 'en' => 'Reliable'],
        'B' => ['fr' => 'Source habituellement sûre', 'en' => 'Usually reliable'],
        'C' => ['fr' => 'Source assez sûre', 'en' => 'Fairly reliable'],
        'D' => ['fr' => 'Source pas toujours sûre', 'en' => 'Not usually reliable'],
        'E' => ['fr' => 'Source peu sûre', 'en' => 'Unreliable'],
        'F' => ['fr' => 'Fiabilité inconnue', 'en' => 'Cannot be judged'],
    ];

    /** Crédibilité de l'information (1–6). @var array<int, array{fr: string, en: string}> */
    public const CREDIBILITY = [
        1 => ['fr' => 'Information confirmée', 'en' => 'Confirmed'],
        2 => ['fr' => 'Information probable', 'en' => 'Probably true'],
        3 => ['fr' => 'Information possible', 'en' => 'Possibly true'],
        4 => ['fr' => 'Information douteuse', 'en' => 'Doubtfully true'],
        5 => ['fr' => 'Information improbable', 'en' => 'Improbable'],
        6 => ['fr' => 'Véracité inconnue', 'en' => 'Cannot be judged'],
    ];

    /** Étiquettes proposées d'office selon le type de fiche (codes du catalogue). @var array<string, list<string>> */
    public const KIND_SUGGESTIONS = [
        'FRM' => ['HUMINT', 'PIR', 'SPOTREP', 'PATROL DEBRIEF', 'UNCONFIRMED'],
        'FRO' => ['IMINT', 'SALUTE', 'SIR', 'SINGLE SOURCE'],
        'FRC' => ['HUMINT', 'KEY LEADERS', 'POPULATION', 'IIR', 'SINGLE SOURCE'],
        'FRA' => ['HUMINT', 'POPULATION', 'INTSUM', 'OSINT'],
        'FRT' => ['TECHINT', 'WTI', 'DOMEX', 'SSE REPORT', 'WEAPONS CACHE'],
    ];

    /** Champs qu'une communauté peut rendre obligatoires par type de fiche. @var array<string, string> */
    public const REQUIRABLE_FIELDS = [
        'title' => 'Objet',
        'place_label' => 'Lieu',
        'grid_reference' => 'Coordonnées',
        'intel_source' => 'Mode de recueil',
    ];

    /** Couleur d'une catégorie existante d'après son ton. @var array<string, string> */
    private const TONE_COLOR = ['critical' => 'red', 'warning' => 'amber', 'caution' => 'amber', 'stable' => 'green', 'info' => 'blue', 'neutral' => 'slate'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $delivered = null;

    /** @return list<string> */
    public static function familyKeys(): array
    {
        return array_keys(self::FAMILIES);
    }

    /** Familles dont les étiquettes se choisissent sur une fiche. @return list<string> */
    public static function pickFamilies(): array
    {
        return array_keys(array_filter(self::FAMILIES, static fn (array $f): bool => $f['pick']));
    }

    public static function hasFamily(string $family): bool
    {
        return isset(self::FAMILIES[$family]);
    }

    public static function familyLabel(string $family, string $lang = 'fr'): string
    {
        $f = self::FAMILIES[$family] ?? null;

        return $f === null ? $family : ($lang === 'en' ? $f['en'] : $f['fr']);
    }

    public static function familyHint(string $family, string $lang = 'fr'): string
    {
        $f = self::FAMILIES[$family] ?? null;

        return $f === null ? '' : ($lang === 'en' ? $f['hint_en'] : $f['hint_fr']);
    }

    public static function colorHex(string $color): string
    {
        return self::COLORS[$color]['hex'] ?? self::COLORS['slate']['hex'];
    }

    public static function normalizeColor(mixed $color): string
    {
        $c = strtolower(trim((string) $color));

        return isset(self::COLORS[$c]) ? $c : 'slate';
    }

    /** Code tel qu'il s'écrit sur la fiche : majuscules, chiffres, espace, tiret, barre, point. */
    public static function normalizeCode(mixed $code): string
    {
        $c = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) $code)), 'UTF-8');

        return mb_substr($c, 0, self::CODE_MAX);
    }

    public static function isValidCode(string $code): bool
    {
        return preg_match('/^[A-Z0-9][A-Z0-9 \-\/\.]{0,' . (self::CODE_MAX - 2) . '}[A-Z0-9]$/', $code) === 1;
    }

    /** Clé stable d'une étiquette (unicité par communauté et portée) : code sans accent ni majuscule, tirets. */
    public static function slug(string $code): string
    {
        $s = strtr(mb_strtolower($code, 'UTF-8'), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'œ' => 'oe',
        ]);

        return mb_substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-'), 0, self::SLUG_MAX);
    }

    /**
     * Catalogue livré complet (étiquettes + catégories existantes), indexé par clé.
     *
     * @return array<string, array{key: string, code: string, family: string, label_fr: string, label_en: string, desc_fr: string, desc_en: string, color: string, qualifier: string, default_on: bool, sort: int}>
     */
    public static function delivered(): array
    {
        if (self::$delivered !== null) {
            return self::$delivered;
        }
        $out = [];
        $n = 0;
        foreach (self::TAGS as [$code, $family, $fr, $en, $descFr, $descEn, $color, $qualifier, $on]) {
            $out[self::slug($code)] = [
                'key' => self::slug($code), 'code' => $code, 'family' => $family, 'label_fr' => $fr, 'label_en' => $en,
                'desc_fr' => $descFr, 'desc_en' => $descEn, 'color' => $color, 'qualifier' => $qualifier, 'default_on' => $on, 'sort' => (++$n) * 10,
            ];
        }
        foreach (SseFieldNoteCatalog::THEMES as $code => $def) {
            $out[self::categoryKey($code)] = [
                'key' => self::categoryKey($code), 'code' => $code, 'family' => 'category', 'label_fr' => $def['label'], 'label_en' => self::CATEGORY_EN[$code] ?? $def['label'],
                'desc_fr' => $def['hint'], 'desc_en' => self::CATEGORY_HINT_EN[$code] ?? '', 'color' => self::TONE_COLOR[$def['tone']] ?? 'slate',
                'qualifier' => '', 'default_on' => true, 'sort' => (++$n) * 10,
            ];
        }

        return self::$delivered = $out;
    }

    /** Clé d'une catégorie existante dans le référentiel (la clé stockée sur la fiche reste le code, p. ex. « ARMEMENT »). */
    public static function categoryKey(string $themeCode): string
    {
        return 'cat-' . self::slug($themeCode);
    }

    /** Libellés anglais des catégories existantes. @var array<string, string> */
    public const CATEGORY_EN = [
        'TERROR' => 'Terrorism', 'INSURG' => 'Insurgency', 'CBRNE' => 'CBRNE', 'ARMEMENT' => 'Weapons / Materiel', 'PERSON' => 'Persons / Targets',
        'PLANIF' => 'Planning', 'LOGIST' => 'Logistics', 'COMMS' => 'Communications', 'FINANCE' => 'Financing', 'RECRUT' => 'Recruitment',
        'INFRA' => 'Infrastructure', 'ORGAN' => 'Organisation', 'MOUV' => 'Movements', 'SECUR' => 'Security / Protection', 'CIVIL' => 'Civil environment',
        'METEO' => 'Weather / Terrain', 'GENERAL' => 'General / Miscellaneous',
    ];

    /** @var array<string, string> */
    public const CATEGORY_HINT_EN = [
        'TERROR' => 'Attacks, terrorist cells, networks, financing, recruitment.',
        'INSURG' => 'Insurgent movements, rebellion, non-state armed groups.',
        'CBRNE' => 'Chemical, biological, radiological, nuclear and explosive weapons.',
        'ARMEMENT' => 'Weapons, ammunition, equipment, vehicles, weapon systems.',
        'PERSON' => 'Personalities, high-value targets, leaders, fighters, key personnel.',
        'PLANIF' => 'Intentions, plans, objectives, operation schedules.',
        'LOGIST' => 'Resupply, transport, storage, supply chains.',
        'COMMS' => 'Networks, transmissions, signals, cyber, electronic warfare.',
        'FINANCE' => 'Financial flows, laundering, resources, illicit economy.',
        'RECRUT' => 'Enlistment, radicalisation, instruction, training.',
        'INFRA' => 'Buildings, facilities, sensitive sites, nodal points.',
        'ORGAN' => 'Command structures, hierarchy, units, groups.',
        'MOUV' => 'Movements, infiltrations, exfiltrations, migrations.',
        'SECUR' => 'Security measures, force protection, counterintelligence.',
        'CIVIL' => 'Population, sentiment, community leaders, humanitarian matters.',
        'METEO' => 'Weather conditions, terrain, operational environment.',
        'GENERAL' => 'Intelligence that fits no category, background information.',
    ];

    /** Priorité de traitement par défaut d'une urgence (« critique » → « FLASH »). */
    public static function defaultPrecedence(string $urgency): string
    {
        foreach (self::PRECEDENCES as $code => $def) {
            if ($def['urgency'] === $urgency) {
                return $code;
            }
        }

        return 'ROUTINE';
    }

    public static function reliabilityUs(string $letter): string
    {
        return self::RELIABILITY[strtoupper($letter)]['en'] ?? self::RELIABILITY['F']['en'];
    }

    public static function credibilityUs(int $digit): string
    {
        return self::CREDIBILITY[$digit]['en'] ?? self::CREDIBILITY[6]['en'];
    }

    /** Nombre d'entrées livrées par famille (priorités et évaluation comprises). @return array<string, int> */
    public static function countsByFamily(): array
    {
        $out = array_fill_keys(self::familyKeys(), 0);
        foreach (self::delivered() as $tag) {
            $out[$tag['family']]++;
        }
        $out['precedence'] = count(self::PRECEDENCES);
        $out['evaluation'] = count(self::RELIABILITY) + count(self::CREDIBILITY);

        return $out;
    }
}
