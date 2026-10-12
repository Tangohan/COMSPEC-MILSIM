/*
    Tables des organisations : natures, rôles (rang 0 = sommet), menaces, niveaux de connaissance.
    [] call comspec_sse_fnc_orgTables → HashMap

    Utilitaires : ["roleLabel", "chef"] / ["kindLabel", "cellule"] / ["roleRank", "courrier"]
*/
params [["_what", "", [""]], ["_id", "", [""]]];

// Personnalisation (réglages CBA) : la table est refaite quand un réglage change.
private _sig = str [
    missionNamespace getVariable ["comspec_sse_ana_extraRoles", ""],
    missionNamespace getVariable ["comspec_sse_ana_extraKinds", ""],
    missionNamespace getVariable ["comspec_sse_ana_codenames", ""]
];
if ((missionNamespace getVariable ["comspec_sse_orgTablesSig", ""]) isNotEqualTo _sig) then {
    comspec_sse_orgTablesCache = nil;
    comspec_sse_orgTablesSig = _sig;
};
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
    private _split = {
        params ["_txt"];
        if (!(_txt isEqualType "")) exitWith { [] };
        ((_txt splitString ",") apply { (_x splitString ":") apply { trim _x } }) select { (count _x) >= 2 && {(_x select 0) isNotEqualTo ""} }
    };
    {
        private _id = toLower (_x select 0);
        private _rank = ((parseNumber (_x param [2, "4"])) max 0) min 5;
        private _roles = comspec_sse_orgTablesCache get "roles";
        private _i = _roles findIf { (_x select 0) isEqualTo _id };
        if (_i >= 0) then { _roles set [_i, [_id, _x select 1, _rank]]; } else { _roles pushBack [_id, _x select 1, _rank]; };
    } forEach ([missionNamespace getVariable ["comspec_sse_ana_extraRoles", ""]] call _split);
    {
        private _id = toLower (_x select 0);
        private _kinds = comspec_sse_orgTablesCache get "kinds";
        private _i = _kinds findIf { (_x select 0) isEqualTo _id };
        if (_i >= 0) then { _kinds set [_i, [_id, _x select 1]]; } else { _kinds pushBack [_id, _x select 1]; };
    } forEach ([missionNamespace getVariable ["comspec_sse_ana_extraKinds", ""]] call _split);
    private _codes = ((missionNamespace getVariable ["comspec_sse_ana_codenames", ""]) splitString ",") apply { trim _x };
    _codes = _codes select { _x isNotEqualTo "" };
    if (_codes isNotEqualTo []) then { comspec_sse_orgTablesCache set ["codes", _codes]; };
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
