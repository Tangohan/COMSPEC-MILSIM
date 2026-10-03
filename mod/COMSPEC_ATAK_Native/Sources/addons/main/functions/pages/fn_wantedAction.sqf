/*
    App Avis de recherche : données et actions.
      ["load"]          relit les avis d'Athena (DLL GetWantedNotices) puis télécharge les photos ;
      ["filter", f]     ALL | person | watchlist | interest ;
      ["open", clé]     ouvre un avis (clé = "type:id"), "" pour revenir à la liste.
    État : uiNamespace COMSPEC_ATAK_Wanted = [avis, heure, erreur] ; avis = [type, id, réf, nom, alias, niveau, détails, url photo, maj]
           uiNamespace COMSPEC_ATAK_WantedImg = HashMap clé → chemin local de la photo ("" : PNG ou échec).
*/
params [["_act", "load"], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "WANTED") then { ["WANTED"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _res = { params ["_r"]; if (!isNil "comspec_overwatch_connect_fnc_extResult") then { [_r] call comspec_overwatch_connect_fnc_extResult } else { _r } };
switch (_act) do {
    case "filter": { _s set ["wantedFilter", _arg]; _s set ["wantedOpen", ""]; call _render; };
    case "open": { _s set ["wantedOpen", _arg]; call _render; };
    case "load": {
        private _now = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
        private _raw = ["COMSPECExtension" callExtension ["GetWantedNotices", []]] call _res;
        if !(_raw isEqualType "") then { _raw = str _raw; };
        if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith {
            private _old = (uiNamespace getVariable ["COMSPEC_ATAK_Wanted", [[]]]) select 0;
            uiNamespace setVariable ["COMSPEC_ATAK_Wanted", [_old, _now, switch (true) do {
                case (_raw isEqualTo ""): { "La DLL Overwatch ne connaît pas encore les avis de recherche : mettez-la à jour." };
                case ((_raw find "unauthorized") >= 0): { "Connexion Athena requise." };
                case ((_raw find "forbidden") >= 0): { "Votre profil n'a pas accès au SSE." };
                default { format ["Athena ne répond pas (%1).", _raw select [0, 60]] };
            }]];
            call _render;
        };
        private _list = [];
        {
            private _c = [_x, toString [9]] call comspec_overwatch_connect_fnc_splitKeepEmpty;
            if ((count _c) >= 8) then { _list pushBack (_c select [0, 9]); };
        } forEach ((_raw select [3]) splitString toString [10]);
        uiNamespace setVariable ["COMSPEC_ATAK_Wanted", [_list, _now, ""]];
        call _render;
        // Photos : une par avis, en cache local de la DLL ; Arma n'affiche que JPG et PAA.
        private _imgs = uiNamespace getVariable ["COMSPEC_ATAK_WantedImg", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_WantedImg", _imgs];
        [_list, _imgs, _res, _render] spawn {
            params ["_list", "_imgs", "_res", "_render"];
            {
                _x params ["_kind", "_id", "", "", "", "", "", ["_url", ""]];
                private _key = format ["%1:%2", _kind, _id];
                if (_url isNotEqualTo "" && {!(_key in _imgs)}) then {
                    private _dl = (["COMSPECExtension" callExtension ["DownloadBriefingSlideImage", [_url, format ["wanted_%1_%2", _kind, _id]]]] call _res) splitString "|";
                    private _p = if ((_dl param [0, ""]) isEqualTo "OK") then { ((_dl param [1, ""]) splitString (toString [92])) joinString "/" } else { "" };
                    private _lp = toLower _p;
                    _imgs set [_key, [_p, ""] select !((_lp select [(count _lp) - 4]) in [".jpg", "jpeg", ".paa"])];
                    sleep 0.05;
                };
            } forEach _list;
            call _render;
        };
    };
};
true
