/*
    Formulaires Zeus (ZEN) des organisations.
    [_mode, _objects] call comspec_sse_fnc_orgZeusForm
      "assign"   affecter les personnes sélectionnées à une organisation
      "generate" générer un réseau avec cellules et rôles à partir de la sélection
      "link"     rattacher les objets sélectionnés à une organisation
      "reveal"   révéler aux joueurs l'appartenance des personnes sélectionnées
      "remove"   retirer les personnes sélectionnées de leur organisation
*/
params [["_mode", "assign", [""]], ["_objs", [], [[]]]];
_objs = _objs select { !isNull _x };
if (isNil "zen_dialog_fnc_create") exitWith { ["Zeus Enhanced requis pour ce formulaire.", "warn"] call comspec_sse_fnc_zeusNotify; };

private _t = [] call comspec_sse_fnc_orgTables;
private _people = _objs select { _x isKindOf "CAManBase" };
private _things = _objs select { !(_x isKindOf "CAManBase") };
private _orgs = missionNamespace getVariable ["comspec_sse_orgs", createHashMap];
private _names = (values _orgs) apply { _x getOrDefault ["name", "?"] };
_names sort true;
private _hintNames = if (_names isEqualTo []) then { "Aucune pour l'instant." } else { "Existantes : " + ((_names select [0, 12]) joinString ", ") };
private _roleIds = (_t get "roles") apply { _x select 0 };
private _roleLbl = (_t get "roles") apply { _x select 1 };
private _kindIds = (_t get "kinds") apply { _x select 0 };
private _kindLbl = (_t get "kinds") apply { _x select 1 };
private _thrIds = (_t get "threats") apply { _x select 0 };
private _thrLbl = (_t get "threats") apply { _x select 1 };
private _known = [[0, 1, 2], ["Inconnu des joueurs", "Soupçonné", "Confirmé"], 0];

switch (_mode) do {
    case "assign": {
        if (_people isEqualTo []) exitWith { ["Sélectionnez des personnes.", "warn"] call comspec_sse_fnc_zeusNotify; };
        [
            format ["Organisation : affecter %1 personne%2", count _people, ["", "s"] select ((count _people) > 1)],
            [
                ["EDIT", ["Organisation", _hintNames], (_names param [0, ""]) ],
                ["EDIT", ["Cellule (facultatif)", "Une cellule est créée sous l'organisation si elle n'existe pas."], ""],
                ["COMBO", "Rôle", [_roleIds, _roleLbl, (_roleIds find "membre") max 0]],
                ["EDIT", ["Alias (facultatif)", "Nom de guerre connu des services."], ""],
                ["COMBO", "Ce que savent les joueurs", _known]
            ],
            {
                params ["_v", "_people"];
                _v params ["_org", "_cell", "_role", "_alias", "_known"];
                if ((trim _org) isEqualTo "") exitWith { ["Nom d'organisation requis.", "warn"] call comspec_sse_fnc_zeusNotify; };
                { ["member", [_x, _org, _role, _alias, _cell, _known]] call comspec_sse_fnc_orgOp; } forEach _people;
                [format ["%1 personne(s) dans %2.", count _people, _org]] call comspec_sse_fnc_zeusNotify;
            },
            {}, _people
        ] call zen_dialog_fnc_create;
    };
    case "generate": {
        if (_people isEqualTo []) exitWith { ["Sélectionnez des personnes (un groupe entier fonctionne).", "warn"] call comspec_sse_fnc_zeusNotify; };
        [
            format ["Générer un réseau (%1 personnes)", count _people],
            [
                ["EDIT", ["Nom (vide = généré)", _hintNames], ""],
                ["COMBO", "Nature", [_kindIds, _kindLbl, 0]],
                ["COMBO", "Menace", [_thrIds, _thrLbl, 1]],
                ["EDIT", ["Graine (vide = aléatoire)", "Même graine et mêmes personnes : même organisation."], ""],
                ["COMBO", "Au départ, les joueurs savent", [[0, 1, 2], ["Rien", "Le chef est soupçonné", "L'organisation est connue"], 0]]
            ],
            {
                params ["_v", "_people"];
                _v params ["_name", "_kind", "_threat", "_seed", "_known"];
                private _s = if ((trim _seed) isEqualTo "") then { -1 } else { parseNumber _seed };
                [_people, _name, _kind, _s, _known, _threat] call comspec_sse_fnc_orgGenerate;
                [format ["Réseau généré sur %1 personne(s). Tableau des liens (vue Zeus) pour le voir.", count _people]] call comspec_sse_fnc_zeusNotify;
            },
            {}, _people
        ] call zen_dialog_fnc_create;
    };
    case "link": {
        if (_things isEqualTo []) exitWith { ["Sélectionnez des objets (documents, caches, véhicules...).", "warn"] call comspec_sse_fnc_zeusNotify; };
        [
            "Rattacher à une organisation",
            [["EDIT", ["Organisation", _hintNames + " L'exploiter révèle l'organisation aux joueurs."], (_names param [0, ""])]],
            {
                params ["_v", "_things"];
                _v params ["_org"];
                { _x setVariable ["COMSPEC_SSE_OrgLinked", trim _org, true]; } forEach _things;
                ["ensure", [_org]] call comspec_sse_fnc_orgOp;
                [format ["%1 objet(s) rattaché(s) à %2.", count _things, _org]] call comspec_sse_fnc_zeusNotify;
            },
            {}, _things
        ] call zen_dialog_fnc_create;
    };
    case "reveal": {
        if (_people isEqualTo []) exitWith { ["Sélectionnez des personnes.", "warn"] call comspec_sse_fnc_zeusNotify; };
        [
            "Révéler aux joueurs",
            [
                ["COMBO", "Niveau", [[1, 2], ["Soupçonné", "Confirmé"], 1]],
                ["EDIT", "Source (vue par les joueurs)", "renseignement transmis par le PC"]
            ],
            {
                params ["_v", "_people"];
                _v params ["_lvl", "_how"];
                { ["reveal", [_x, _lvl, _how]] call comspec_sse_fnc_orgOp; } forEach _people;
                [format ["%1 appartenance(s) révélée(s).", count _people]] call comspec_sse_fnc_zeusNotify;
            },
            {}, _people
        ] call zen_dialog_fnc_create;
    };
    case "remove": {
        { ["unmember", [_x]] call comspec_sse_fnc_orgOp; } forEach _people;
        [format ["%1 personne(s) retirée(s) de leur organisation.", count _people]] call comspec_sse_fnc_zeusNotify;
    };
};
true
