/*
    Actions de l'app Inter-team. Params : [action, arguments]
      "mapGroup"  [netId du groupe]          carte centrée sur le chef
      "mapTeam"   [netId du groupe, équipe]  carte centrée sur l'équipe
      "routeTeam" [netId du groupe, équipe]  itinéraire GPS vers le centre de l'équipe
      "sync"                                 le serveur revérifie les équipes du camp et renvoie l'état à tous
      "push"                                 envoi immédiat de mon groupe à Athena
*/
params [["_act", ""], ["_args", []]];
_args params [["_nid", ""], ["_tid", ""]];
private _g = groupFromNetId _nid;
switch (_act) do {
    case "mapGroup": {
        if (isNull _g) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [leader _g, 0.03] call comspec_atak_native_fnc_mapCenter;
    };
    case "mapTeam";
    case "routeTeam": {
        private _c = [_g, _tid] call comspec_atak_native_fnc_ftCenter;
        if ((count _c) isEqualTo 0) exitWith { ["INFO", "Position de l'équipe inconnue", 3] call comspec_atak_native_fnc_notify; };
        if (_act isEqualTo "routeTeam") then {
            private _name = (((_g getVariable ["COMSPEC_FireTeams", []]) select { (_x select 0) isEqualTo _tid }) param [0, ["", "Équipe"]]) select 1;
            [[_c select 0, _c select 1, 0], _name] spawn comspec_atak_native_fnc_routeCompute;
        };
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [ASLToAGL _c, 0.03] call comspec_atak_native_fnc_mapCenter;
    };
    case "sync": {
        ["comspec_atak_native_ftCheck", [player]] call CBA_fnc_serverEvent;
        ["INFO", "Resynchronisation demandée au serveur", 3] call comspec_atak_native_fnc_notify;
    };
    case "push": {
        [true] call comspec_atak_native_fnc_squadSync;
        ["INFO", "Escouade envoyée à Athena", 3] call comspec_atak_native_fnc_notify;
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "INTERTEAM") then { ["INTERTEAM"] call comspec_atak_native_fnc_pageRender; }; }, [], 1.5] call CBA_fnc_waitAndExecute;
    };
};
true
