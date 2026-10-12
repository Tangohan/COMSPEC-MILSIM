/*
    Tables des organisations : natures, rôles (rang 0 = sommet), menaces, niveaux de connaissance.
    [] call comspec_sse_fnc_orgTables → HashMap

    Utilitaires : ["roleLabel", "chef"] / ["kindLabel", "cellule"] / ["roleRank", "courrier"]
*/
params [["_what", "", [""]], ["_id", "", [""]]];

if (isNil "comspec_sse_orgTablesCache") then {
    comspec_sse_orgTablesCache = createHashMapFromArray [
        ["kinds", [
            ["reseau", "Réseau"], ["cellule", "Cellule"], ["groupe", "Groupe armé"], ["milice", "Milice"],
            ["criminel", "Réseau criminel"], ["clan", "Clan, famille"], ["entreprise", "Entreprise écran"],
            ["service", "Service de renseignement"], ["soutien", "Réseau de soutien"]
        ]],
        ["roles", [
            ["chef", "Chef", 0], ["adjoint", "Adjoint", 1], ["chefcellule", "Chef de cellule", 2],
            ["financier", "Financier", 3], ["logisticien", "Logisticien", 3], ["artificier", "Artificier", 3],
            ["recruteur", "Recruteur", 3], ["propagande", "Propagandiste", 3], ["transmissions", "Transmissions", 3],
            ["courrier", "Courrier", 4], ["guetteur", "Guetteur", 4], ["combattant", "Combattant", 4],
            ["membre", "Membre", 4], ["facilitateur", "Facilitateur", 5], ["sympathisant", "Sympathisant", 5]
        ]],
        ["threats", [["faible", "Faible"], ["moyenne", "Moyenne"], ["elevee", "Élevée"], ["critique", "Critique"]]],
        ["known", ["Non identifié", "Soupçonné", "Confirmé"]],
        ["codes", ["Faucon", "Scorpion", "Cèdre", "Vipère", "Oasis", "Croissant", "Lion", "Minaret", "Palmier",
            "Sirocco", "Djinn", "Comète", "Chacal", "Aigle", "Lune", "Sable", "Tempête", "Atlas", "Nil", "Zénith"]]
    ];
};
private _t = comspec_sse_orgTablesCache;

switch (_what) do {
    case "roleLabel": {
        private _i = (_t get "roles") findIf { (_x select 0) isEqualTo (toLower _id) };
        if (_i < 0) then { _id } else { ((_t get "roles") select _i) select 1 }
    };
    case "roleRank": {
        private _i = (_t get "roles") findIf { (_x select 0) isEqualTo (toLower _id) };
        if (_i < 0) then { 4 } else { ((_t get "roles") select _i) select 2 }
    };
    case "kindLabel": {
        private _i = (_t get "kinds") findIf { (_x select 0) isEqualTo (toLower _id) };
        if (_i < 0) then { _id } else { ((_t get "kinds") select _i) select 1 }
    };
    case "threatLabel": {
        private _i = (_t get "threats") findIf { (_x select 0) isEqualTo (toLower _id) };
        if (_i < 0) then { _id } else { ((_t get "threats") select _i) select 1 }
    };
    default { _t };
}
