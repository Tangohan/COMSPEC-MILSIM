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
        ["securite_publique", "Sécurité publique", "#dc2626"],
        ["menace_armee", "Menace armée", "#dc2626"],
        ["engins_explosifs", "Engins explosifs", "#dc2626"],
        ["ordre_public", "Ordre public", "#d97706"],
        ["trafics", "Trafics", "#d97706"],
        ["mouvements", "Mouvements et flux", "#d97706"],
        ["population", "Population et attitude", "#2563eb"],
        ["infrastructures", "Infrastructures", "#2563eb"],
        ["communications", "Communications", "#2563eb"],
        ["logistique", "Logistique adverse", "#2563eb"],
        ["environnement", "Environnement et terrain", "#4b5563"],
        ["divers", "Divers", "#4b5563"]
    ]],
    ["urgencies", [
        ["routine", "Courant"],
        ["priorite", "Prioritaire"],
        ["immediate", "Immédiat"]
    ]]
];

uiNamespace setVariable ["COMSPEC_IntelNote_Catalog", _catalog];
_catalog
