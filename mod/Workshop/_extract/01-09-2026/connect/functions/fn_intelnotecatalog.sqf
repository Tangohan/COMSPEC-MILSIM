/*
    Référentiel des fiches de renseignement simplifiées, côté jeu.

    Copie volontaire du référentiel serveur (App\Support\SseFieldNoteCatalog) :
    l'opérateur doit lire les mêmes intitulés dans l'ATAK et dans le portail,
    même quand la liaison est coupée et qu'aucun référentiel ne peut être
    téléchargé. L'ordre des thèmes fixe l'ordre des bascules du rédacteur.

    Retour: hashmap
        "kinds"      liste de [code, libellé, aide]
        "themes"     liste de [code, libellé, couleur HTML]
        "urgencies"  liste de [code, libellé]
        "sources"    liste de [code, libellé]
        "body_max"   longueur maximale du texte
        "pieces_max" nombre maximal de pièces jointes
        "themes_max" nombre maximal de thèmes
*/

private _cached = uiNamespace getVariable ["COMSPEC_IntelNote_Catalog", createHashMap];
if ((_cached isEqualType createHashMap) && {(count _cached) > 0}) exitWith { _cached };

private _catalog = createHashMapFromArray [
    ["body_max", 1000],
    ["pieces_max", 4],
    ["themes_max", 4],
    ["kinds", [
        ["FRM", "Fiche de renseignement de mission", "Ce que vous avez constaté pendant la mission en cours."],
        ["FRO", "Fiche d’observation", "Un fait observé, sans lien direct avec la mission du jour."],
        ["FRC", "Fiche de contact", "Un échange avec une personne, un groupe ou une autorité locale."],
        ["FRA", "Fiche d’ambiance", "Le climat d’un secteur : attitude de la population, tensions."],
        ["FRT", "Fiche technique", "Matériel, véhicule, installation ou marquage relevé."]
    ]],
    ["themes", [
        ["TERROR", "Terrorisme", "#dc2626"],
        ["INSURG", "Insurrection", "#dc2626"],
        ["CBRNE", "CBRNE", "#dc2626"],
        ["ARMEMENT", "Armement / Matériel", "#ea580c"],
        ["PERSON", "Personnes / Cibles", "#ea580c"],
        ["PLANIF", "Planification", "#ea580c"],
        ["LOGIST", "Logistique", "#ca8a04"],
        ["COMMS", "Communications", "#ca8a04"],
        ["FINANCE", "Financement", "#ca8a04"],
        ["RECRUT", "Recrutement", "#ca8a04"],
        ["INFRA", "Infrastructures", "#16a34a"],
        ["ORGAN", "Organisation", "#16a34a"],
        ["MOUV", "Mouvements", "#16a34a"],
        ["SECUR", "Sécurité / Protection", "#16a34a"],
        ["CIVIL", "Environnement civil", "#2563eb"],
        ["METEO", "Météo / Terrain", "#2563eb"],
        ["GENERAL", "Général / Divers", "#2563eb"]
    ]],
    ["urgencies", [
        ["critique", "Critique"],
        ["urgent", "Urgent"],
        ["normal", "Normal"],
        ["routine", "Routine"]
    ]],
    ["sources", [
        ["HUMINT", "Renseignement humain"],
        ["IMINT", "Imagerie"],
        ["SIGINT", "Signaux"],
        ["OSINT", "Sources ouvertes"],
        ["TECHINT", "Technique"],
        ["MASINT", "Mesures et signatures"],
        ["GEOINT", "Géospatial"]
    ]]
];

uiNamespace setVariable ["COMSPEC_IntelNote_Catalog", _catalog];
_catalog
