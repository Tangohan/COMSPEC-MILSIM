/*
    Fiches FRS / FRM d'Athena sur le téléphone (Overwatch connect, commande ListSseFieldNotes de la DLL).
      ["load", "mine" | "all"] : récupère les fiches (les miennes par mon UID Steam, ou toutes celles de la communauté) ;
      ["images", id]           : télécharge les photos de la fiche id (cache local de la DLL).
    État : uiNamespace COMSPEC_ATAK_FrsLib = HashMap portée → [fiches, heure de synchro, erreur]
           fiche = [id, réf, type, titre, carroyage, urgence, statut, date, auteur, texte, [urls]]
           uiNamespace COMSPEC_ATAK_FrsImg = HashMap id → [chemins locaux] (vide pour une photo PNG, qu'Arma n'affiche pas).
    L'app FRS est redessinée à la fin.
*/
params [["_act", "load"], ["_arg", "mine"]];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "FRS") then { ["FRS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _res = { params ["_r"]; if (!isNil "comspec_overwatch_connect_fnc_extResult") then { [_r] call comspec_overwatch_connect_fnc_extResult } else { _r } };
switch (_act) do {
    case "load": {
        private _lib = uiNamespace getVariable ["COMSPEC_ATAK_FrsLib", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_FrsLib", _lib];
        private _steam = if (_arg isEqualTo "mine") then { getPlayerUID player } else { "" };
        private _raw = ["COMSPECExtension" callExtension ["ListSseFieldNotes", ["25", _steam]]] call _res;
        if !(_raw isEqualType "") then { _raw = str _raw; };
        if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith {
            private _err = switch (true) do {
                case (_raw isEqualTo ""): { "La DLL Overwatch ne connaît pas encore les fiches : mettez-la à jour." };
                case ((_raw find "unauthorized") >= 0): { "Connexion Athena requise." };
                case ((_raw find "forbidden") >= 0): { "Votre profil n'a pas accès aux fiches." };
                default { format ["Athena ne répond pas (%1).", _raw select [0, 60]] };
            };
            _lib set [_arg, [(_lib getOrDefault [_arg, [[]]]) select 0, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _err]];
            call _render;
        };
        private _notes = [];
        {
            private _c = if (!isNil "comspec_overwatch_connect_fnc_splitKeepEmpty") then { [_x, toString [9]] call comspec_overwatch_connect_fnc_splitKeepEmpty } else { _x splitString toString [9] };
            if ((count _c) >= 10 && {(_c select 0) isNotEqualTo ""}) then {
                _notes pushBack [_c select 0, _c select 1, _c select 2, _c select 3, _c select 4, _c select 5, _c select 6, _c select 7, _c select 8,
                    _c select 9, (_c param [10, ""]) splitString "|"];
            };
        } forEach ((_raw select [3]) splitString toString [10]);
        _lib set [_arg, [_notes, [dayTime, "HH:MM"] call BIS_fnc_timeToString, ""]];
        call _render;
    };
    case "images": {
        private _imgs = uiNamespace getVariable ["COMSPEC_ATAK_FrsImg", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_FrsImg", _imgs];
        if (_arg in _imgs) exitWith { call _render; };
        private _note = [];
        { private _f = (_x select 0) findIf { (_x select 0) isEqualTo _arg }; if (_f >= 0) exitWith { _note = (_x select 0) select _f; }; } forEach values (uiNamespace getVariable ["COMSPEC_ATAK_FrsLib", createHashMap]);
        private _paths = [];
        {
            private _dl = (["COMSPECExtension" callExtension ["DownloadBriefingSlideImage", [_x, format ["frs_%1_%2", _arg, _forEachIndex]]]] call _res) splitString "|";
            private _p = if ((_dl param [0, ""]) isEqualTo "OK") then { ((_dl param [1, ""]) splitString (toString [92])) joinString "/" } else { "" };
            private _lp = toLower _p;
            _paths pushBack ([_p, ""] select !((_lp select [(count _lp) - 4]) in [".jpg", "jpeg", ".paa"]));
        } forEach (_note param [10, []]);
        _imgs set [_arg, _paths];
        call _render;
    };
};
