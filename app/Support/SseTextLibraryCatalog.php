<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catalogue de départ de la bibliothèque rédactionnelle SSE.
 *
 * Ce fichier n'est PAS lu par les vues : il sert uniquement de semence à la table
 * `sse_text_templates`, que chaque unité administre ensuite librement (modification,
 * désactivation, ajout de mentions propres). Une fois semé, il n'écrase plus rien.
 */
final class SseTextLibraryCatalog
{
    /** @var array<string,string> */
    public const CATEGORIES = [
        'objet' => 'Objet du dossier',
        'exploitation' => 'État de l’exploitation',
        'classification' => 'Classification et manipulation',
        'consultation' => 'Consultation',
        'reproduction' => 'Reproduction et extraction',
        'caviardage' => 'Caviardage',
        'renseignement' => 'Renseignement et appréciation',
        'sources' => 'Sources et acquisition',
        'personnes' => 'Personnes rattachées',
        'sites' => 'Sites',
        'materiels' => 'Matériels et supports',
        'communications' => 'Communications',
        'biometrie' => 'Biométrie et traces',
        'pieces' => 'Preuves et pièces',
        'chronologie' => 'Chronologie',
        'methode' => 'Méthode et limites',
        'notes' => 'Notes analytiques',
        'coordination' => 'Coordination et demandes',
        'diffusion' => 'Diffusion',
        'alertes' => 'Avertissements',
        'archivage' => 'Conservation et clôture',
        'mentions' => 'Mentions courtes',
        'fragments' => 'Fragments composables',
        'temporalite' => 'Temporalité',
        'urgence' => 'Mentions d’urgence',
        'deconfliction' => 'Déconfliction et relations',
    ];

    /** @var array<string,string> */
    public const CONTEXTS = [
        'dossier' => 'Dossier',
        'personne' => 'Personne',
        'site' => 'Site',
        'piece' => 'Pièce',
        'note' => 'Note',
        'document' => 'Document',
    ];

    /** @var array<string,string> Variables reconnues et leur description lisible. */
    public const VARIABLES = [
        'dossier.numero' => 'Référence du dossier',
        'dossier.nom' => 'Intitulé du dossier',
        'dossier.classification' => 'Classification du dossier',
        'dossier.statut' => 'État du dossier',
        'dossier.date_ouverture' => 'Date d’ouverture',
        'dossier.date_revision' => 'Date de révision',
        'unite.nom' => 'Unité',
        'bureau.nom' => 'Bureau rédacteur',
        'personne.identite' => 'Identité concernée',
        'site.designation' => 'Désignation du site',
        'piece.reference' => 'Référence de la pièce',
        'piece.classification' => 'Classification de la pièce',
        'source.reference' => 'Référence de la source',
        'redacteur.identite' => 'Rédacteur',
        'validation.identite' => 'Validateur',
        'date' => 'Date du jour',
        'heure' => 'Heure courante',
    ];

    /**
     * Mentions livrées d'origine.
     *
     * @return list<array{code:string,category:string,title:string,content:string,context:string,classification_min:?string,is_default:bool,doctrine:string,fragment_kind:string}>
     */
    public static function entries(): array
    {
        $out = [];
        foreach (self::raw() as $row) {
            $out[] = [
                'code' => $row[0],
                'category' => $row[1],
                'title' => $row[2],
                'content' => $row[3],
                'context' => $row[4],
                'classification_min' => $row[5] ?? null,
                'is_default' => (bool) ($row[6] ?? false),
                'doctrine' => (string) ($row[7] ?? 'neutre'),
                'fragment_kind' => (string) ($row[8] ?? 'bloc'),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function variablesUsedIn(string $content): array
    {
        if (!preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', $content, $m)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            $m[1],
            static fn (string $name): bool => isset(self::VARIABLES[$name])
        )));
    }

    /**
     * [code, catégorie, titre, contenu, contexte, classif. min, défaut, doctrine, fragment]
     *
     * @return list<array{0:string,1:string,2:string,3:string,4:string,5?:?string,6?:bool,7?:string,8?:string}>
     */
    private static function raw(): array
    {
        return [
            // ───────────────────────────── Objet du dossier
            ['OBJ-01', 'objet', 'Dossier vierge', "Aucune synthèse n’a encore été portée à la chemise. Le premier rédacteur y consigne l’origine de l’affaire, le périmètre retenu et les limites d’exploitation admises.", 'dossier'],
            ['OBJ-02', 'objet', 'Ouverture sur renseignement initial', "Le dossier {{dossier.numero}} est ouvert à la suite de la réception d’éléments faisant apparaître un intérêt opérationnel pour [PERSONNE / SITE / RÉSEAU / MATÉRIEL]. Les informations disponibles à ce stade demeurent partielles et appellent exploitation, recoupement et consolidation.", 'dossier'],
            ['OBJ-03', 'objet', 'Réseau', "Le présent dossier centralise les renseignements relatifs au réseau désigné « [NOM] », à ses membres supposés, à ses moyens matériels, à ses implantations connues ou présumées ainsi qu’aux relations susceptibles d’être établies entre ces différents éléments.", 'dossier'],
            ['OBJ-04', 'objet', 'Périmètre strict', "L’exploitation est limitée aux éléments présentant un lien direct avec l’objet de la présente chemise. Tout renseignement périphérique est versé séparément ou fait l’objet d’un renvoi vers le dossier correspondant.", 'dossier'],
            ['OBJ-05', 'objet', 'Reprise après clôture', "Le dossier {{dossier.numero}}, précédemment clos, est rouvert à la suite de l’apparition d’éléments nouveaux. Les conclusions antérieures sont conservées en l’état et demeurent opposables tant qu’elles n’ont pas été expressément révisées.", 'dossier'],
            ['OBJ-06', 'objet', 'Dossier thématique', "La présente chemise n’est pas rattachée à une personne ou à un site déterminé. Elle rassemble des éléments relatifs à un procédé, une pratique ou une zone, aux seules fins de comparaison et d’orientation de l’exploitation.", 'dossier'],
            ['OBJ-07', 'objet', 'Dossier issu d’un dossier d’intérêt', "Le présent dossier est constitué à partir du dossier d’intérêt dont il reprend les éléments d’ouverture. Le dossier d’origine demeure consultable pour la traçabilité du signalement initial.", 'dossier'],
            ['OBJ-08', 'objet', 'Limites d’exploitation admises', "L’exploitation admise porte sur [ÉNUMÉRER]. Toute extension du périmètre suppose une décision expresse portée au dossier et signalée aux personnels concernés.", 'dossier'],

            // ───────────────────────────── État de l'exploitation
            ['EXP-01', 'exploitation', 'Exploitation en cours', "Dossier en cours d’exploitation. Les rapprochements, qualifications et rattachements qui y figurent sont susceptibles d’évoluer à mesure de l’intégration de nouveaux éléments.", 'dossier', null, true],
            ['EXP-02', 'exploitation', 'Données non consolidées', "Les éléments présentés dans cette partie n’ont pas tous atteint le même degré de consolidation. Leur présence au dossier ne vaut ni confirmation définitive ni validation analytique.", 'dossier'],
            ['EXP-03', 'exploitation', 'Recoupement requis', "Renseignement exploitable sous réserve de recoupement. Aucun rattachement définitif ne doit être déduit de ce seul élément.", 'note'],
            ['EXP-04', 'exploitation', 'Contradiction non résolue', "Les informations disponibles présentent une contradiction non résolue. Les différentes hypothèses sont conservées au dossier jusqu’à obtention d’un élément discriminant.", 'note'],
            ['EXP-05', 'exploitation', 'Exploitation suspendue', "L’exploitation de cette branche est suspendue. Les éléments sont conservés en l’état afin de préserver l’historique analytique et permettre une reprise ultérieure.", 'dossier'],
            ['EXP-06', 'exploitation', 'Attente de moyens', "L’exploitation est momentanément arrêtée faute de moyens d’acquisition disponibles. Les besoins exprimés sont maintenus jusqu’à satisfaction ou abandon formel.", 'dossier'],
            ['EXP-07', 'exploitation', 'Priorité d’exploitation', "Dossier traité en priorité. Toute pièce nouvelle est exploitée sans délai et portée à la connaissance du bureau {{bureau.nom}}.", 'dossier'],
            ['EXP-08', 'exploitation', 'Exploitation partielle', "Seule une partie des éléments disponibles a pu être exploitée à ce jour. Les éléments non traités sont conservés et signalés comme tels afin de ne pas être tenus pour inexistants.", 'dossier'],

            // ───────────────────────────── Classification et manipulation
            ['CLASS-01', 'classification', 'Niveau de la chemise', "Le dossier {{dossier.numero}} regroupe des pièces de niveaux de protection différents. Le niveau applicable à la chemise est celui de la pièce la plus protégée qui y est versée, soit {{dossier.classification}}.", 'dossier', null, true],
            ['CLASS-02', 'classification', 'Héritage du niveau', "Toute extraction, transcription, note de travail ou reproduction autorisée issue du présent dossier hérite de son niveau de protection jusqu’à reclassement explicite.", 'dossier'],
            ['CLASS-03', 'classification', 'Besoin d’en connaître', "La détention de l’habilitation requise ne vaut pas autorisation générale de consultation. L’accès demeure subordonné au besoin d’en connaître attaché à la fonction exercée et à l’exploitation considérée.", 'dossier'],
            ['CLASS-04', 'classification', 'Pièce isolée', "Aucune pièce extraite de la chemise ne peut circuler sans sa référence, son niveau de protection et son rattachement au dossier d’origine.", 'piece'],
            ['CLASS-05', 'classification', 'Surclassement par agrégation', "Prises isolément, les informations réunies ici relèvent d’un niveau inférieur. Leur rapprochement fait apparaître un ensemble plus sensible que chacune des pièces qui le composent ; le niveau retenu est celui de l’ensemble.", 'dossier'],
            ['CLASS-06', 'classification', 'Déclassement', "Le niveau de protection est abaissé à compter du {{date}}. Les exemplaires antérieurs conservent leur marquage d’origine jusqu’à remplacement ou destruction enregistrée.", 'dossier'],
            ['CLASS-07', 'classification', 'Marquage à apposer', "Chaque page reçoit le marquage du niveau retenu en en-tête et en pied. Une page sans marquage est réputée non contrôlée et ne doit pas circuler.", 'document'],

            // ───────────────────────────── Consultation
            ['CONS-01', 'consultation', 'Consultation sur poste habilité', "Consultation autorisée exclusivement depuis un poste et un environnement habilités. La chemise est consultée complète et demeure sous la responsabilité du détenteur pendant toute la durée de son exploitation.", 'dossier'],
            ['CONS-02', 'consultation', 'Inscription au registre', "Toute consultation est portée au registre avec identification du consultant, date, heure, motif et, le cas échéant, référence des pièces examinées.", 'dossier'],
            ['CONS-03', 'consultation', 'Remise en main propre', "Remise en main propre exclusivement. Le transfert de responsabilité est matérialisé par l’inscription correspondante au registre.", 'dossier'],
            ['CONS-04', 'consultation', 'Restitution et recollement', "La chemise et l’ensemble des pièces communiquées sont restitués à l’issue de la consultation. Toute anomalie constatée lors du recollement est immédiatement signalée.", 'dossier'],
            ['CONS-05', 'consultation', 'Consultation partielle', "Seules les pièces expressément désignées sont communiquées. L’absence des autres éléments ne préjuge en rien de leur contenu et ne doit donner lieu à aucune déduction.", 'dossier'],
            ['CONS-06', 'consultation', 'Consultation accompagnée', "La consultation se tient en présence d’un personnel du bureau {{bureau.nom}}, chargé de la remise, du recollement et de l’inscription au registre.", 'dossier'],

            // ───────────────────────────── Reproduction et extraction
            ['REPRO-01', 'reproduction', 'Bandeau court', "NE PAS REPRODUIRE — PORTER AU REGISTRE — USAGE INTERNE", 'dossier'],
            ['REPRO-02', 'reproduction', 'Reproduction soumise à autorisation', "La reproduction totale ou partielle est interdite hors autorisation expresse. Chaque exemplaire autorisé reçoit un numéro de contrôle propre.", 'dossier'],
            ['REPRO-03', 'reproduction', 'Captation interdite', "La photographie, numérisation, transcription intégrale ou extraction vers un support extérieur au système autorisé est interdite.", 'dossier'],
            ['REPRO-04', 'reproduction', 'Extraction hors contexte', "Une information issue du dossier ne peut être reprise hors de son contexte analytique lorsque cette extraction est susceptible d’en modifier le sens ou le degré de certitude.", 'dossier'],
            ['REPRO-05', 'reproduction', 'Impression contrôlée', "L’impression est limitée au nombre d’exemplaires strictement nécessaire. Chaque exemplaire est numéroté, suivi au registre et détruit ou restitué en fin d’exploitation.", 'dossier'],
            ['REPRO-06', 'reproduction', 'Prise de notes', "Les notes manuscrites prises à partir du dossier reprennent son niveau de protection et sont traitées comme des pièces à part entière.", 'dossier'],

            // ───────────────────────────── Caviardage
            ['CAV-01', 'caviardage', 'Objet du masquage', "Les passages masqués protègent l’origine du renseignement, l’identité d’une source, une capacité d’acquisition ou un élément dont la diffusion excède le besoin d’en connaître du destinataire.", 'document'],
            ['CAV-02', 'caviardage', 'Reconstitution interdite', "Un passage caviardé ne doit faire l’objet d’aucune tentative de reconstitution par rapprochement avec d’autres versions ou pièces accessibles.", 'document'],
            ['CAV-03', 'caviardage', 'Levée de caviardage', "La levée d’un caviardage donne lieu à la production d’une version distincte, identifiée, tracée et rattachée à la version dont elle dérive.", 'document'],
            ['CAV-04', 'caviardage', 'Masquage par agrégation', "Certains passages sont masqués non pour leur contenu propre, mais parce que leur rapprochement avec d’autres éléments accessibles permettrait d’identifier une source ou une capacité.", 'document'],
            ['CAV-05', 'caviardage', 'Motif conservé au dossier', "Le motif de chaque masquage est enregistré dans la version de travail. Le destinataire de la version expurgée n’a pas à en connaître le détail.", 'document'],
            ['CAV-06', 'caviardage', 'Étendue du masquage', "Le masquage porte sur le strict nécessaire. Un passage entier n’est masqué que lorsque la suppression des seuls éléments sensibles rendrait le texte trompeur.", 'document'],

            // ───────────────────────────── Renseignement et appréciation
            ['RENS-01', 'renseignement', 'Non confirmé', "APPRÉCIATION : NON CONFIRMÉ\n\nÉlément enregistré pour exploitation. Aucun recoupement indépendant disponible à cette heure.", 'note'],
            ['RENS-02', 'renseignement', 'Partiellement recoupé', "APPRÉCIATION : PARTIELLEMENT RECOUPÉ\n\nUne partie des informations a pu être rapprochée d’éléments indépendants. Les points restant incertains sont maintenus sous réserve.", 'note'],
            ['RENS-03', 'renseignement', 'Recoupé', "APPRÉCIATION : RECOUPÉ\n\nLes éléments essentiels ont fait l’objet de rapprochements concordants provenant de sources ou d’acquisitions distinctes.", 'note'],
            ['RENS-04', 'renseignement', 'Hypothèse analytique', "HYPOTHÈSE DE TRAVAIL\n\nLe rapprochement présenté constitue une construction analytique destinée à orienter l’exploitation. Il ne doit pas être présenté comme un fait établi.", 'note'],
            ['RENS-05', 'renseignement', 'Forte convergence', "Plusieurs éléments indépendants convergent vers la même appréciation. Cette convergence renforce l’hypothèse sans supprimer les réserves expressément mentionnées.", 'note'],
            ['RENS-06', 'renseignement', 'Infirmé', "APPRÉCIATION : INFIRMÉ\n\nL’élément est contredit par des informations plus solides. Il demeure au dossier afin de conserver la trace du raisonnement et d’éviter sa réintroduction ultérieure.", 'note'],
            ['RENS-07', 'renseignement', 'Origine indéterminée', "L’origine exacte de ce renseignement n’a pu être établie. À défaut de pouvoir en apprécier la fiabilité, il n’est retenu qu’à titre d’orientation.", 'note'],
            ['RENS-08', 'renseignement', 'Cotation de l’information', "COTATION : [FIABILITÉ A-F] / [CRÉDIBILITÉ 1-6]\n\nLa première lettre apprécie la source, le chiffre apprécie l’information elle-même. Les deux appréciations sont indépendantes et ne se compensent pas.", 'note'],
            ['RENS-09', 'renseignement', 'Information périmée', "L’information conserve sa valeur historique mais ne rend plus compte de la situation actuelle. Elle ne doit pas fonder une décision de conduite sans vérification.", 'note'],
            ['RENS-10', 'renseignement', 'Renseignement d’ambiance', "Élément d’ambiance recueilli sans démarche dirigée. Il éclaire le contexte général sans permettre de conclusion sur une personne ou un fait déterminé.", 'note'],

            // ───────────────────────────── Sources et acquisition
            ['SRC-01', 'sources', 'Protection de la source', "L’origine de ce renseignement est protégée. Aucune mention permettant d’identifier la source, son mode de recueil ou son environnement ne figure dans les versions destinées à diffusion.", 'note'],
            ['SRC-02', 'sources', 'Source unique', "Élément issu d’une source unique. Sa reprise doit préserver cette réserve : la répétition d’une même information par plusieurs destinataires ne vaut pas recoupement.", 'note'],
            ['SRC-03', 'sources', 'Source nouvelle', "Source nouvellement ouverte, dont la fiabilité n’a pas encore pu être appréciée sur la durée. Les éléments transmis sont exploités avec la réserve correspondante.", 'note'],
            ['SRC-04', 'sources', 'Source éprouvée', "Source ayant fourni par le passé des éléments régulièrement vérifiés. Cette antériorité ne dispense pas de l’appréciation propre à chaque information transmise.", 'note'],
            ['SRC-05', 'sources', 'Recueil technique', "Élément obtenu par un moyen technique dont la description ne figure pas au dossier. Seul le résultat est versé ; la capacité mise en œuvre demeure protégée.", 'note'],
            ['SRC-06', 'sources', 'Source ouverte', "Élément recueilli en source ouverte. Sa disponibilité publique ne préjuge ni de son exactitude, ni de l’absence d’intention de la part de celui qui l’a diffusé.", 'note'],
            ['SRC-07', 'sources', 'Interruption du recueil', "Le recueil auprès de cette source est interrompu à compter du {{date}}. Les éléments antérieurs demeurent exploitables sous les réserves déjà portées au dossier.", 'note'],

            // ───────────────────────────── Personnes rattachées
            ['PERS-01', 'personnes', 'Identification à confirmer', "Identité susceptible de correspondre à la personne observée. Rattachement provisoire dans l’attente d’éléments complémentaires.", 'personne', null, true],
            ['PERS-02', 'personnes', 'Rattachement indirect', "La personne n’est pas directement rattachée à l’objet principal du dossier. Son apparition résulte d’une relation observée avec un individu, un site, un moyen ou un événement déjà exploité.", 'personne'],
            ['PERS-03', 'personnes', 'Risque d’homonymie', "Risque d’homonymie identifié. Aucun rapprochement automatique ne doit être opéré sans contrôle d’éléments discriminants.", 'personne'],
            ['PERS-04', 'personnes', 'Relation supposée', "Nature exacte de la relation non déterminée. La représentation graphique du lien traduit une hypothèse d’exploitation et non une relation définitivement établie.", 'personne'],
            ['PERS-05', 'personnes', 'Personne non impliquée', "L’apparition de {{personne.identite}} au dossier résulte du contexte de l’observation. Aucun élément ne lui est imputé à ce stade et sa mention ne vaut pas mise en cause.", 'personne'],
            ['PERS-06', 'personnes', 'Alias et identités multiples', "Plusieurs désignations sont attachées à cette personne. Tant qu’un élément discriminant n’a pas été obtenu, chaque désignation est conservée séparément afin d’éviter une fusion prématurée.", 'personne'],
            ['PERS-07', 'personnes', 'Rattachement écarté', "Le rattachement envisagé est écarté. La personne demeure enregistrée avec la mention du motif d’écartement, afin d’éviter que le rapprochement ne soit proposé à nouveau sans élément nouveau.", 'personne'],
            ['PERS-08', 'personnes', 'Personne protégée', "Cette personne relève d’un statut appelant des précautions particulières de mention et de diffusion. Sa désignation est réduite au strict nécessaire dans les versions de diffusion.", 'personne'],
            ['PERS-09', 'personnes', 'Rôle présumé', "Le rôle attribué à cette personne au sein de l’ensemble observé constitue une appréciation d’analyse. Il repose sur des indices de comportement et non sur une déclaration ou un document.", 'personne'],

            // ───────────────────────────── Sites
            ['SITE-01', 'sites', 'Site identifié', "Site identifié et rattaché au dossier. Les éléments ayant conduit à ce rattachement sont référencés dans les pièces associées.", 'site'],
            ['SITE-02', 'sites', 'Site supposé', "Implantation présumée. La localisation est suffisamment cohérente pour justifier son suivi, sans permettre à ce stade une qualification définitive de sa fonction.", 'site'],
            ['SITE-03', 'sites', 'Fonction inconnue', "La présence d’un lien avec le dossier est établie ou fortement présumée ; la fonction exacte du site demeure indéterminée.", 'site'],
            ['SITE-04', 'sites', 'Site exploité', "L’exploitation de {{site.designation}} a produit des éléments versés à la présente chemise. Chaque donnée conserve la référence de l’opération ou de l’acquisition dont elle est issue.", 'site'],
            ['SITE-05', 'sites', 'Site abandonné', "Le site paraît inoccupé à la date de la dernière observation. L’absence d’occupation ne vaut pas abandon définitif et ne justifie pas la levée du suivi.", 'site'],
            ['SITE-06', 'sites', 'Accès non réalisé', "Aucun accès physique n’a pu être réalisé. Les éléments disponibles reposent sur l’observation extérieure et doivent être appréciés avec la réserve correspondante.", 'site'],
            ['SITE-07', 'sites', 'Environnement sensible', "Le site présente un environnement appelant des précautions particulières (population, lieu de culte, structure de soins, établissement scolaire). Toute exploitation en tient compte.", 'site'],
            ['SITE-08', 'sites', 'Localisation approximative', "La localisation retenue est approximative. Elle est suffisante pour l’orientation de la recherche mais ne peut fonder à elle seule une action sur le terrain.", 'site'],

            // ───────────────────────────── Matériels et supports
            ['MAT-01', 'materiels', 'Matériel d’intérêt', "Matériel enregistré comme élément d’intérêt. Son rattachement à une personne, un site ou une activité fait l’objet d’une appréciation distincte.", 'piece'],
            ['MAT-02', 'materiels', 'Support numérique', "Support numérique versé pour exploitation SSE. Les données extraites sont conservées comme éléments dérivés et demeurent rattachées à leur support d’origine.", 'piece'],
            ['MAT-03', 'materiels', 'Attribution incertaine', "L’utilisation du matériel est documentée ; son attribution nominative demeure incertaine.", 'piece'],
            ['MAT-04', 'materiels', 'Corrélation technique', "Le rapprochement technique observé constitue un indice de relation entre les éléments concernés. Il ne suffit pas, pris isolément, à établir l’identité de leur utilisateur.", 'piece'],
            ['MAT-05', 'materiels', 'Armement et munitions', "Élément relevant de l’armement ou des munitions. Marquages, numéros et conditionnement sont relevés en l’état ; toute conclusion sur la provenance suppose une comparaison distincte.", 'piece'],
            ['MAT-06', 'materiels', 'Véhicule', "Véhicule enregistré comme élément d’intérêt. L’observation d’un véhicule en un lieu ne vaut pas présence de son détenteur habituel.", 'piece'],
            ['MAT-07', 'materiels', 'Matériel neutralisé', "Le matériel a été neutralisé ou rendu inopérant avant versement. Cette opération est enregistrée avec sa date, son motif et le personnel l’ayant conduite.", 'piece'],

            // ───────────────────────────── Communications
            ['COM-01', 'communications', 'Identifiant de communication', "Identifiant relevé et enregistré comme élément d’intérêt. Un identifiant peut changer de porteur : son rattachement à une personne est réapprécié à chaque exploitation.", 'piece'],
            ['COM-02', 'communications', 'Analyse de trafic', "L’analyse porte sur l’existence et la fréquence des liaisons, non sur leur contenu. Elle établit une relation technique et non une intention.", 'note'],
            ['COM-03', 'communications', 'Silence observé', "Une interruption des communications est constatée. Elle peut traduire une mesure de sécurité, un changement de moyen ou une simple absence ; aucune de ces lectures n’est privilégiée à ce stade.", 'note'],
            ['COM-04', 'communications', 'Contenu non exploité', "Le contenu des échanges n’a pas été exploité. Seules les données d’enveloppe sont versées au dossier.", 'note'],
            ['COM-05', 'communications', 'Traduction et transcription', "Le texte porté au dossier est une transcription de travail. En cas de divergence, l’enregistrement d’origine fait foi et la transcription est rectifiée en conséquence.", 'piece'],
            ['COM-06', 'communications', 'Moyen partagé', "Le moyen de communication paraît utilisé par plusieurs personnes. Aucune imputation individuelle ne peut être tirée du seul usage de cet identifiant.", 'note'],

            // ───────────────────────────── Biométrie et traces
            ['BIO-01', 'biometrie', 'Prélèvement effectué', "Prélèvement réalisé sur place et enregistré avec sa date, son lieu et l’identité du préleveur. Le résultat de comparaison, s’il existe, fait l’objet d’une pièce distincte.", 'piece'],
            ['BIO-02', 'biometrie', 'Correspondance partielle', "La comparaison fait apparaître une correspondance partielle. Elle constitue un indice de rapprochement et ne vaut pas identification.", 'piece'],
            ['BIO-03', 'biometrie', 'Qualité insuffisante', "La qualité du relevé est insuffisante pour permettre une comparaison exploitable. L’élément est conservé pour reprise éventuelle avec de meilleurs moyens.", 'piece'],
            ['BIO-04', 'biometrie', 'Trace sans attribution', "La trace relevée est enregistrée sans attribution. Sa présence en un lieu atteste d’un passage, non d’une participation à une activité déterminée.", 'piece'],
            ['BIO-05', 'biometrie', 'Correspondance confirmée', "La comparaison établit une correspondance. L’identification qui en résulte porte sur la trace elle-même ; le rôle de la personne concernée relève d’une appréciation distincte.", 'piece'],

            // ───────────────────────────── Preuves et pièces
            ['PIECE-01', 'pieces', 'Versement au dossier', "Pièce versée au dossier sous la référence {{piece.reference}}. Son origine, sa date d’intégration et son empreinte d’intégrité sont enregistrées dans la fiche associée.", 'piece', null, true],
            ['PIECE-02', 'pieces', 'Original conservé séparément', "La présente chemise contient une représentation de travail. L’original est conservé séparément sous la référence indiquée.", 'piece'],
            ['PIECE-03', 'pieces', 'Élément dérivé', "Cette pièce résulte de l’exploitation d’un élément source. Elle ne se substitue pas à celui-ci et doit être interprétée conjointement avec sa référence d’origine.", 'piece'],
            ['PIECE-04', 'pieces', 'Intégrité', "Empreinte relevée lors du versement. Toute divergence ultérieure impose la suspension de l’exploitation de la pièce jusqu’à contrôle.", 'piece'],
            ['PIECE-05', 'pieces', 'Chaîne de responsabilité', "La succession des détenteurs de la pièce est enregistrée depuis son recueil. Une rupture dans cette chaîne est signalée et affecte la valeur qui peut lui être reconnue.", 'piece'],
            ['PIECE-06', 'pieces', 'Pièce annoncée non reçue', "La pièce annoncée n’a pas été reçue à ce jour. Son absence est signalée afin qu’elle ne soit pas tenue pour versée au vu des seules mentions qui la citent.", 'piece'],
            ['PIECE-07', 'pieces', 'Photographie de situation', "Prise de vue réalisée en l’état, sans déplacement ni reconstitution. Toute modification ultérieure du cadrage ou du contraste est signalée.", 'piece'],
            ['PIECE-08', 'pieces', 'Pièce retirée', "La pièce est retirée de la chemise. Le motif du retrait, sa date et son nouveau lieu de conservation sont enregistrés ; la référence demeure au dossier.", 'piece'],

            // ───────────────────────────── Chronologie
            ['CHRON-01', 'chronologie', 'Datation incertaine', "La date retenue est approximative. Elle résulte d’un recoupement d’indices et non d’un horodatage propre à l’élément.", 'note'],
            ['CHRON-02', 'chronologie', 'Séquence reconstituée', "L’ordre des faits est reconstitué à partir d’éléments d’origines différentes. Il traduit l’état de l’analyse et peut être révisé.", 'note'],
            ['CHRON-03', 'chronologie', 'Référence horaire', "Les heures portées au dossier sont exprimées en Zulu. Toute reprise dans un autre référentiel doit le mentionner expressément.", 'dossier'],
            ['CHRON-04', 'chronologie', 'Lacune assumée', "La période comprise entre les deux repères indiqués n’est couverte par aucun élément. Cette lacune est signalée et ne doit pas être comblée par déduction.", 'note'],
            ['CHRON-05', 'chronologie', 'Antériorité déterminante', "L’ordre des faits emporte ici des conséquences d’analyse. Il est expressément vérifié avant toute conclusion sur un enchaînement de causes.", 'note'],

            // ───────────────────────────── Méthode et limites
            ['METH-01', 'methode', 'Limites de l’exploitation', "Les conclusions portées reflètent les seuls éléments disponibles à la date de rédaction et les moyens effectivement mis en œuvre. Elles ne prétendent pas à l’exhaustivité.", 'note'],
            ['METH-02', 'methode', 'Hypothèses concurrentes examinées', "Plusieurs lectures des mêmes éléments ont été envisagées. Celles qui ont été écartées sont mentionnées avec leur motif, afin de rendre le raisonnement contrôlable.", 'note'],
            ['METH-03', 'methode', 'Risque de biais', "L’exploitation présente un risque de confirmation : les éléments recherchés l’ont été à partir d’une hypothèse initiale. Ce point est signalé pour permettre une relecture critique.", 'note'],
            ['METH-04', 'methode', 'Angle mort', "Un pan de la situation demeure hors de portée des moyens engagés. Son absence au dossier ne vaut pas absence sur le terrain.", 'note'],
            ['METH-05', 'methode', 'Degré de confiance', "DEGRÉ DE CONFIANCE : [FAIBLE / MODÉRÉ / ÉLEVÉ]\n\nLe degré indiqué porte sur la conclusion et non sur chacun des éléments qui la soutiennent.", 'note'],
            ['METH-06', 'methode', 'Rédaction à plusieurs mains', "Le présent produit résulte de contributions successives. Chaque partie demeure attribuable à son rédacteur dans l’historique du dossier.", 'document'],

            // ───────────────────────────── Notes analytiques
            ['NOTE-01', 'notes', 'Bandeau note analytique', "NOTE ANALYTIQUE — NE PAS CONFONDRE AVEC UN FAIT ÉTABLI", 'note'],
            ['NOTE-02', 'notes', 'État de l’analyse', "Cette note expose l’état de l’analyse à la date de sa rédaction. Elle peut être complétée, rectifiée ou invalidée par des renseignements ultérieurs.", 'note', null, true],
            ['NOTE-03', 'notes', 'Conclusions intermédiaires conservées', "Les conclusions intermédiaires sont conservées afin d’assurer la traçabilité du raisonnement analytique, y compris lorsqu’elles sont ultérieurement abandonnées.", 'note'],
            ['NOTE-04', 'notes', 'Absence de renseignement', "Absence de renseignement ne vaut pas constat d’absence. Les zones non documentées demeurent signalées comme telles.", 'note'],
            ['NOTE-05', 'notes', 'Note contradictoire', "La présente note s’écarte d’une appréciation antérieure versée au dossier. Les deux lectures sont conservées jusqu’à arbitrage explicite.", 'note'],
            ['NOTE-06', 'notes', 'Note d’orientation', "Note destinée à orienter la recherche. Elle formule des besoins et des priorités ; elle ne constitue pas un compte rendu de faits.", 'note'],

            // ───────────────────────────── Coordination et demandes
            ['COORD-01', 'coordination', 'Transmission pour exploitation', "Le présent élément est transmis au bureau {{bureau.nom}} pour exploitation dans son domaine. Le dossier {{dossier.numero}} demeure ouvert et conserve la conduite d’ensemble.", 'dossier'],
            ['COORD-02', 'coordination', 'Demande de recherche', "Une demande de recherche est adressée en vue d’obtenir les éléments manquants énumérés ci-après. Le délai de retour attendu est porté au dossier.", 'dossier'],
            ['COORD-03', 'coordination', 'Retour attendu', "L’exploitation demeure suspendue à la réception des éléments demandés. À défaut de retour à l’échéance fixée, la demande est réitérée ou abandonnée par décision expresse.", 'dossier'],
            ['COORD-04', 'coordination', 'Élément reçu d’un tiers', "Élément reçu d’un organisme extérieur. Il est exploité selon les restrictions posées par son émetteur, qui demeurent applicables à toute reprise.", 'note'],
            ['COORD-05', 'coordination', 'Restitution au demandeur', "Les conclusions sont restituées au demandeur sous la forme convenue. Les éléments d’origine et les capacités mises en œuvre ne sont pas communiqués.", 'document'],

            // ───────────────────────────── Diffusion
            ['DIFF-01', 'diffusion', 'Usage interne', "USAGE INTERNE — DIFFUSION LIMITÉE AUX PERSONNELS DÉSIGNÉS", 'document'],
            ['DIFF-02', 'diffusion', 'Version de diffusion', "Version préparée pour diffusion. Certains éléments de la chemise source ont été retirés, synthétisés ou caviardés conformément au niveau d’accès du destinataire.", 'document'],
            ['DIFF-03', 'diffusion', 'Reconstitution de la source interdite', "La présente version ne doit pas être utilisée pour reconstituer le contenu de la chemise source.", 'document'],
            ['DIFF-04', 'diffusion', 'Transmission enregistrée', "Transmission enregistrée sous le numéro de contrôle [NUMÉRO]. Toute retransmission est soumise aux mêmes restrictions que la remise initiale.", 'document'],
            ['DIFF-05', 'diffusion', 'Diffusion externe interdite', "Diffusion strictement interne à {{unite.nom}}. Toute communication à un organisme extérieur suppose une décision expresse portée au dossier.", 'document'],
            ['DIFF-06', 'diffusion', 'Destinataires nommément désignés', "La diffusion est limitée aux destinataires nommément désignés en tête du présent document. Aucune diffusion par liste ou par fonction n’est admise.", 'document'],
            ['DIFF-07', 'diffusion', 'Accusé de réception attendu', "Le destinataire accuse réception du présent envoi. À défaut, la transmission est réputée non aboutie et l’exemplaire est considéré comme non contrôlé.", 'document'],

            // ───────────────────────────── Avertissements
            ['ALERT-01', 'alertes', 'Identité non consolidée', "ATTENTION — IDENTITÉ NON CONSOLIDÉE", 'personne'],
            ['ALERT-02', 'alertes', 'Source unique', "ATTENTION — SOURCE UNIQUE", 'note'],
            ['ALERT-03', 'alertes', 'Renseignement non recoupé', "ATTENTION — RENSEIGNEMENT NON RECOUPÉ", 'note'],
            ['ALERT-04', 'alertes', 'Contradiction non résolue', "ATTENTION — CONTRADICTION NON RÉSOLUE", 'note'],
            ['ALERT-05', 'alertes', 'Pièce plus protégée que le dossier', "ATTENTION — PIÈCE PLUS PROTÉGÉE QUE LE DOSSIER", 'piece'],
            ['ALERT-06', 'alertes', 'Version de travail', "ATTENTION — VERSION DE TRAVAIL", 'document'],
            ['ALERT-07', 'alertes', 'Périmètre SSE', "DIFFUSION INTERDITE HORS PÉRIMÈTRE SSE", 'document'],
            ['ALERT-08', 'alertes', 'Personne non impliquée citée', "ATTENTION — PERSONNE CITÉE SANS MISE EN CAUSE", 'personne'],
            ['ALERT-09', 'alertes', 'Révision échue', "ATTENTION — ÉCHÉANCE DE RÉVISION DÉPASSÉE", 'dossier'],
            ['ALERT-10', 'alertes', 'Information périmée', "ATTENTION — INFORMATION POTENTIELLEMENT PÉRIMÉE", 'note'],
            ['ALERT-11', 'alertes', 'Intégrité non vérifiée', "ATTENTION — INTÉGRITÉ DE LA PIÈCE NON VÉRIFIÉE", 'piece'],

            // ───────────────────────────── Conservation et clôture
            ['ARCH-01', 'archivage', 'Durée de conservation', "La durée de conservation court à compter de la clôture administrative du dossier. Toute réouverture suspend le calcul de l’échéance précédemment portée.", 'dossier'],
            ['ARCH-02', 'archivage', 'Révision de classification', "La classification fait l’objet d’une révision à l’échéance portée sur la chemise ou lors de tout changement substantiel affectant la sensibilité des informations conservées.", 'dossier'],
            ['ARCH-03', 'archivage', 'Clôture', "L’exploitation active est clôturée le {{date}}. Les éléments demeurent consultables selon les règles de conservation et d’habilitation applicables au dossier.", 'dossier'],
            ['ARCH-04', 'archivage', 'Destruction enregistrée', "À l’issue de la période de conservation, la destruction est enregistrée avec identification du dossier, date, motif et personnels ayant constaté l’opération.", 'dossier'],
            ['ARCH-05', 'archivage', 'Versement aux archives', "Le dossier {{dossier.numero}} est versé aux archives de {{unite.nom}}. Le bordereau de versement porte la liste des pièces et leur niveau de protection.", 'dossier'],
            ['ARCH-06', 'archivage', 'Clôture sans suite', "L’exploitation est close sans suite. Cette clôture ne vaut pas infirmation des éléments recueillis, qui demeurent consultables en cas d’élément nouveau.", 'dossier'],

            // ───────────────────────────── Mentions courtes
            ['MENT-01', 'mentions', 'À confirmer', 'À CONFIRMER', 'dossier'],
            ['MENT-02', 'mentions', 'Non recoupé', 'NON RECOUPÉ', 'note'],
            ['MENT-03', 'mentions', 'Recoupé', 'RECOUPÉ', 'note'],
            ['MENT-04', 'mentions', 'Source unique', 'SOURCE UNIQUE', 'note'],
            ['MENT-05', 'mentions', 'Identité incertaine', 'IDENTITÉ INCERTAINE', 'personne'],
            ['MENT-06', 'mentions', 'Attribution probable', 'ATTRIBUTION PROBABLE', 'piece'],
            ['MENT-07', 'mentions', 'Lien supposé', 'LIEN SUPPOSÉ', 'note'],
            ['MENT-08', 'mentions', 'Lien confirmé', 'LIEN CONFIRMÉ', 'note'],
            ['MENT-09', 'mentions', 'Hypothèse analytique', 'HYPOTHÈSE ANALYTIQUE', 'note'],
            ['MENT-10', 'mentions', 'Exploitation en cours', 'EXPLOITATION EN COURS', 'dossier'],
            ['MENT-11', 'mentions', 'Exploitation suspendue', 'EXPLOITATION SUSPENDUE', 'dossier'],
            ['MENT-12', 'mentions', 'En attente de validation', 'EN ATTENTE DE VALIDATION', 'document'],
            ['MENT-13', 'mentions', 'Validé par analyste', 'VALIDÉ PAR ANALYSTE', 'document'],
            ['MENT-14', 'mentions', 'Caviardé', 'CAVIARDÉ', 'document'],
            ['MENT-15', 'mentions', 'Diffusion restreinte', 'DIFFUSION RESTREINTE', 'document'],
            ['MENT-16', 'mentions', 'Pièce source', 'PIÈCE SOURCE', 'piece'],
            ['MENT-17', 'mentions', 'Élément dérivé', 'ÉLÉMENT DÉRIVÉ', 'piece'],
            ['MENT-18', 'mentions', 'Intégrité vérifiée', 'INTÉGRITÉ VÉRIFIÉE', 'piece'],
            ['MENT-19', 'mentions', 'Version de travail', 'VERSION DE TRAVAIL', 'document'],
            ['MENT-20', 'mentions', 'Version de diffusion', 'VERSION DE DIFFUSION', 'document'],
            ['MENT-21', 'mentions', 'Archivé', 'ARCHIVÉ', 'dossier'],
            ['MENT-22', 'mentions', 'Infirmé', 'INFIRMÉ', 'note'],
            ['MENT-23', 'mentions', 'Sous réserve', 'SOUS RÉSERVE', 'note'],
            ['MENT-24', 'mentions', 'Origine protégée', 'ORIGINE PROTÉGÉE', 'note'],
            ['MENT-25', 'mentions', 'Sans mise en cause', 'SANS MISE EN CAUSE', 'personne'],
            ['MENT-26', 'mentions', 'Localisation approximative', 'LOCALISATION APPROXIMATIVE', 'site'],
            ['MENT-27', 'mentions', 'Information périmée', 'INFORMATION PÉRIMÉE', 'note'],
            ['MENT-28', 'mentions', 'Ne pas reproduire', 'NE PAS REPRODUIRE', 'document'],

            // ───────────────────────────── Fragments composables
            ['FRAG-01', 'fragments', 'À ce stade', 'À ce stade, ', 'note', null, false, 'analytique', 'phrase'],
            ['FRAG-02', 'fragments', 'Sous réserve de', 'sous réserve de ', 'note', null, false, 'analytique', 'phrase'],
            ['FRAG-03', 'fragments', 'Sous réserve', 'sous réserve des éléments disponibles à la date de rédaction', 'note', null, false, 'analytique', 'phrase'],
            ['FRAG-04', 'fragments', 'Plusieurs éléments concordants', 'Plusieurs éléments concordants établissent que ', 'note', null, false, 'analytique', 'phrase'],
            ['FRAG-05', 'fragments', 'Aucun élément ne permet', 'Aucun élément disponible ne permet cependant d’établir ', 'note', null, false, 'analytique', 'phrase'],
            ['FRAG-06', 'fragments', 'Hypothèse privilégiée', 'demeure l’hypothèse privilégiée', 'note', null, false, 'analytique', 'phrase'],
            ['FRAG-07', 'fragments', 'Sans préjuger', 'sans préjuger de ', 'note', null, false, 'neutre', 'phrase'],
            ['FRAG-08', 'fragments', 'Dans l’état actuel', 'Dans l’état actuel du dossier, ', 'note', null, false, 'synthese_commandement', 'phrase'],
            ['FRAG-09', 'fragments', 'Observation terrain', 'Observation réalisée sur le terrain : ', 'note', null, false, 'compte_rendu_terrain', 'phrase'],
            ['FRAG-10', 'fragments', 'Pour le commandement', 'À l’attention du commandement : ', 'document', null, false, 'synthese_commandement', 'phrase'],

            // ───────────────────────────── Temporalité
            ['TEMP-01', 'temporalite', 'Valable à la date du', 'Renseignement valable à la date du {{date}}. Toute exploitation ultérieure suppose vérification.', 'note', null, false, 'neutre', 'bloc'],
            ['TEMP-02', 'temporalite', 'Renseignement ancien', 'Renseignement ancien. Il conserve sa valeur historique mais ne rend plus compte, à lui seul, de la situation actuelle.', 'note', null, false, 'analytique', 'bloc'],
            ['TEMP-03', 'temporalite', 'Dernière confirmation', 'Dernière confirmation obtenue le {{date}}. Aucun élément plus récent n’est disponible à ce jour.', 'note', null, false, 'analytique', 'bloc'],
            ['TEMP-04', 'temporalite', 'Situation susceptible d’évoluer', 'Situation susceptible d’avoir évolué depuis la date de recueil. Une réappréciation est nécessaire avant toute décision de conduite.', 'note', null, false, 'synthese_commandement', 'bloc'],

            // ───────────────────────────── Urgence
            ['URG-01', 'urgence', 'Exploitation prioritaire', 'EXPLOITATION PRIORITAIRE — Traiter sans délai et porter au bureau {{bureau.nom}}.', 'dossier', null, false, 'synthese_commandement', 'phrase'],
            ['URG-02', 'urgence', 'Attention commandement', 'ATTENTION COMMANDEMENT — Élément susceptible d’orienter une décision immédiate.', 'dossier', null, false, 'synthese_commandement', 'phrase'],
            ['URG-03', 'urgence', 'Validité courte', 'INFORMATION À DURÉE DE VALIDITÉ COURTE — Réévaluer avant toute reprise hors du délai indiqué.', 'note', null, false, 'neutre', 'phrase'],

            // ───────────────────────────── Déconfliction
            ['DECON-01', 'deconfliction', 'Déjà suivi ailleurs', 'DÉCONFLICTION — L’individu ou le site fait déjà l’objet d’un autre dossier. Toute exploitation croisée est signalée et les références croisées sont portées au registre.', 'dossier', null, false, 'analytique', 'bloc'],
            ['DECON-02', 'deconfliction', 'Doublon potentiel', 'DOUBLON POTENTIEL — Un rapprochement avec un autre dossier est envisageable. Aucune fusion n’est opérée tant qu’un arbitrage explicite n’a pas été consigné.', 'dossier', null, false, 'analytique', 'bloc'],
            ['DECON-03', 'deconfliction', 'Fusion avec conservation', 'FUSION — Les dossiers fusionnés conservent leurs anciennes références dans le registre. Les conclusions antérieures demeurent consultables.', 'dossier', null, false, 'analytique', 'bloc'],
            ['DECON-04', 'deconfliction', 'Dissociation', 'DISSOCIATION — Les éléments sont séparés tout en conservant le lien historique et les références d’origine.', 'dossier', null, false, 'analytique', 'bloc'],

            // ───────────────────────────── Origines / cotation enrichies
            ['ORIG-01', 'sources', 'Observation directe', 'Origine : observation directe. La fiabilité de la source et la crédibilité de l’information sont cotées séparément.', 'note', null, false, 'compte_rendu_terrain', 'bloc'],
            ['ORIG-02', 'sources', 'Compte rendu terrain', 'Origine : compte rendu terrain. Les conditions d’observation et les limites de visibilité sont signalées lorsque connues.', 'note', null, false, 'compte_rendu_terrain', 'bloc'],
            ['ORIG-03', 'sources', 'Exploitation numérique', 'Origine : exploitation numérique. Les conclusions portent sur les données extraites, non sur l’intention de leur auteur.', 'note', null, false, 'analytique', 'bloc'],
            ['ORIG-04', 'sources', 'Renseignement partenaire', 'Origine : renseignement partenaire. Les restrictions posées par l’émetteur demeurent applicables à toute reprise.', 'note', null, false, 'neutre', 'bloc'],
            ['ORIG-05', 'sources', 'Source humaine scénario', 'Origine : source humaine (scénario). La cotation de fiabilité est réévaluée à chaque transmission.', 'note', null, false, 'analytique', 'bloc'],
            ['COTE-01', 'renseignement', 'Grille fiabilité / crédibilité', 'COTATION SÉPARÉE — Fiabilité de la source : [A–F]. Crédibilité de l’information : [1–6]. Les deux appréciations sont indépendantes et ne se compensent pas.', 'note', null, false, 'analytique', 'modele'],
            ['APPR-01', 'renseignement', 'Modèle appréciation', "APPRÉCIATION ANALYTIQUE — CONFIANCE [FAIBLE / MODÉRÉE / ÉLEVÉE]\n\n[FAIT]. [RECOUPEMENT]. [APPRÉCIATION]. L’hypothèse [H1/H2/H3] demeure privilégiée sous réserve de {{si.confiance.faible:confirmation complémentaire}}.", 'note', null, false, 'analytique', 'modele'],
            ['VISA-01', 'methode', 'Visa analytique', 'Visa analytique — Rédacteur : {{redacteur.identite}} · Relecteur : [NOM] · Validateur : {{validation.identite}} · Date : {{date}} · Version : [N].', 'document', null, false, 'neutre', 'bloc'],
        ];
    }
}
