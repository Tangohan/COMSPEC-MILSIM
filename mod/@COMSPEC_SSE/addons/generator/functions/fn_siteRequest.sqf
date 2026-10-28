/*
    Point d'entrée serveur des modules « sites et kits » (Zeus, Eden, ZEN, script).
    [_action, _args] remoteExecCall ["comspec_sse_fnc_siteRequest", 2]   (déclaré dans CfgRemoteExec)
    [_action, _args] call comspec_sse_fnc_siteRequest                     (sur le serveur : module Eden)

      "SITE"     [_type, _posATL, _dir, _options]        site préfabriqué (comspec_sse_fnc_spawnComposition)
      "BUILDING" [_type, _buildingPosATL, _options]      le bâtiment à cette position devient le site
      "SEED"     [_posATL, _radius, _count, _options]     semis de preuves (comspec_sse_fnc_seedEvidence)
      "NETWORK"  [_posATL, _count, _radius, _options]     réseau multi-sites (comspec_sse_fnc_spawnSiteNetwork)
      "KIT"      [_kit, _posATL, _dir]                    caisse de kit (comspec_sse_fnc_spawnKitBox)
    _options : paires [clé, valeur].

    Appel distant accepté d'un Zeus (curateur assigné) ou d'un administrateur seulement.
    Le compte rendu revient au demandeur (comspec_sse_fnc_siteNotify, traduit chez lui).
*/
params [
    ["_action", "", [""]],
    ["_args", [], [[]]]
];

if (!isServer) exitWith { false };

private _owner = if (isRemoteExecuted) then { remoteExecutedOwner } else { clientOwner };
private _allowed = true;
if (isRemoteExecuted && {_owner != 2}) then {
    private _unit = (allPlayers select { owner _x == _owner }) param [0, objNull];
    _allowed = (!isNull _unit && {!isNull (getAssignedCuratorLogic _unit)}) || {(admin _owner) > 0};
};
if (!_allowed) exitWith {
    [format ["siteRequest %1 refusé : poste %2 ni Zeus ni administrateur", _action, _owner], "WARNING"] call comspec_sse_fnc_log;
    false
};

private _notify = {
    params ["_tpl", ["_values", []], ["_kind", "info"]];
    if (isRemoteExecuted) then {
        [_tpl, _values, _kind] remoteExecCall ["comspec_sse_fnc_siteNotify", _owner];
    } else {
        if (hasInterface && {!isNil "comspec_sse_fnc_siteNotify"}) then { [_tpl, _values, _kind] call comspec_sse_fnc_siteNotify; };
    };
};

private _siteDone = {
    params ["_s"];
    if (count _s == 0) exitWith { ["Site non posé : type inconnu ou position invalide.", [], "error"] call _notify; };
    [
        "%1 posé, grid %2 : %3 pièce(s), %4 occupant(s), %5 piège(s). Fiches prêtes dans %6 s.",
        [_s get "label", _s get "grid", count (_s get "evidence"), count (_s get "persons"), count (_s get "mines"), ceil (_s get "readyIn")]
    ] call _notify;
};

switch (toUpper _action) do {
    case "SITE": {
        _args params [["_type", "SAFEHOUSE", [""]], ["_pos", [0, 0, 0], [[]]], ["_dir", 0, [0]], ["_opts", [], [[]]]];
        [[_type, _pos, _dir, _opts] call comspec_sse_fnc_spawnComposition] call _siteDone;
    };
    case "BUILDING": {
        _args params [["_type", "SAFEHOUSE", [""]], ["_pos", [0, 0, 0], [[]]], ["_opts", [], [[]]]];
        // Le bâtiment est retrouvé ici par sa position : un objet de la carte ne voyage pas bien par le réseau.
        private _b = ((nearestObjects [_pos, ["House"], 15]) select { alive _x && {count (_x buildingPos -1) >= 2} }) param [0, objNull];
        if (isNull _b) exitWith { ["Aucun bâtiment habitable sous le module.", [], "warn"] call _notify; };
        private _o = +_opts;
        _o pushBack ["building", _b];
        _o pushBack ["usebuilding", true];
        [[_type, getPosATL _b, getDir _b, _o] call comspec_sse_fnc_spawnComposition] call _siteDone;
    };
    case "SEED": {
        _args params [["_pos", [0, 0, 0], [[]]], ["_radius", 30, [0]], ["_count", 5, [0]], ["_opts", [], [[]]]];
        private _s = [_pos, _radius, _count, _opts] call comspec_sse_fnc_seedEvidence;
        [
            "%1 pièce(s) semée(s) autour de la grid %2, %3 personne(s) reliée(s).",
            [count (_s getOrDefault ["objects", []]), _s getOrDefault ["grid", "?"], count (_s getOrDefault ["persons", []])]
        ] call _notify;
    };
    case "NETWORK": {
        _args params [["_pos", [0, 0, 0], [[]]], ["_count", 4, [0]], ["_radius", 3000, [0]], ["_opts", [], [[]]]];
        private _sites = [_pos, _count, _radius, _opts] call comspec_sse_fnc_spawnSiteNetwork;
        if (_sites isEqualTo []) exitWith { ["Réseau non posé.", [], "error"] call _notify; };
        [
            "Réseau de %1 sites posé :\n%2",
            [count _sites, (_sites apply { format ["%1 — grid %2", _x get "label", _x get "grid"] }) joinString "\n"]
        ] call _notify;
    };
    case "KIT": {
        _args params [["_kit", "TEAM", [""]], ["_pos", [0, 0, 0], [[]]], ["_dir", 0, [0]]];
        private _box = [_kit, _pos, _dir] call comspec_sse_fnc_spawnKitBox;
        if (isNull _box) exitWith { ["Caisse non posée : kit inconnu.", [], "error"] call _notify; };
        ["%1 posée, grid %2.", [getText (configOf _box >> "displayName"), mapGridPosition _box]] call _notify;
    };
    default {
        [format ["siteRequest : action inconnue %1", _action], "WARNING"] call comspec_sse_fnc_log;
    };
};
true
