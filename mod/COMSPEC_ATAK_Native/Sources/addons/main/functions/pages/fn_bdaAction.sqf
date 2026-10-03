/*
    App BDA (bilan des dégâts). Params : [action, argument, valeur]
      "set" clé valeur : choix du formulaire     "here" : grille = ma position
      "send" : diffuse le bilan au camp (et au poste Athena avec Overwatch, rapport BDA)
      "recv" [bilan, camp] : réception (événement comspec_atak_native_bda, rejoué par la synchro serveur)
      "map" id : carte sur le bilan     "del" id : retire un bilan de ma liste
*/
params [["_act", ""], ["_arg", ""], ["_val", ""]];
private _f = uiNamespace getVariable ["COMSPEC_ATAK_BdaForm", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_BdaForm", _f];
private _list = missionNamespace getVariable ["COMSPEC_ATAK_BdaList", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_BdaList", _list];
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "BDA") then { ["BDA"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _save = { { _f set [_x, [_x, _f getOrDefault [_x, ""]] call comspec_atak_native_fnc_formValue]; } forEach ["ekia", "equip", "ammo", "grid", "rem"]; };
switch (_act) do {
    case "set": { call _save; _f set [_arg, _val]; call _rerender; };
    case "here": { call _save; _f set ["grid", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef]; call _rerender; };
    case "send": {
        call _save;
        private _g = ((_f getOrDefault ["grid", ""]) splitString " ,.-") joinString "";
        private _pos = getPosASL player;
        if (_g isNotEqualTo "") then {
            private _p = ([_g] call BIS_fnc_gridToPos) param [0, []];
            if ((count _p) >= 2) then { _pos = [_p select 0, _p select 1, 0]; };
        };
        private _id = format ["BDA%1", floor (random 90000) + 10000];
        private _r = createHashMapFromArray [
            ["id", _id], ["by", [player, true] call comspec_atak_native_fnc_unitCallsign], ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString],
            ["type", _f getOrDefault ["type", "PERS"]], ["res", _f getOrDefault ["res", "DESTROYED"]], ["reatk", _f getOrDefault ["reatk", "NO"]],
            ["ekia", _f getOrDefault ["ekia", ""]], ["equip", _f getOrDefault ["equip", ""]], ["ammo", _f getOrDefault ["ammo", ""]],
            ["rem", _f getOrDefault ["rem", ""]], ["pos", [_pos select 0, _pos select 1, 0]], ["grid", [_pos, 8] call comspec_atak_native_fnc_gridRef]
        ];
        [{ params ["_r", "_side"]; ["comspec_atak_native_bda", [_r, _side]] call CBA_fnc_globalEvent; }, [_r, str side group player], "BDA", 1] call comspec_atak_native_fnc_netSend;
        { _f deleteAt _x; } forEach ["ekia", "equip", "ammo", "rem"];
        ["SUCCESS", "Bilan des dégâts transmis au camp", 4, 40] call comspec_atak_native_fnc_notify;
        // Avec Overwatch : rapport BDA au poste web.
        if (!isNil "comspec_overwatch_connect_fnc_submitTacticalReport") then {
            private _labels = [_r] call comspec_atak_native_fnc_bdaLabels;
            private _sd = createHashMapFromArray [["kind", "bda"], ["grid", _r get "grid"], ["type", _labels select 0], ["desc", _labels select 1],
                ["ekia", _r get "ekia"], ["equip", _r get "equip"], ["ordnance", _r get "ammo"], ["reattack", _labels select 2], ["remarks", _r get "rem"]];
            ["BDA", ["ROUTINE", "PRIORITY"] select ((_r get "reatk") isEqualTo "YES"), format ["BDA %1 · %2 %3", _r get "grid", _labels select 0, _labels select 1],
                format ["%1 %2 en %3. EKIA %4, matériel %5. %6. %7", _labels select 0, _labels select 1, _r get "grid", _r get "ekia", _r get "equip", _labels select 2, _r get "rem"], _sd, _r get "pos"] call comspec_overwatch_connect_fnc_submitTacticalReport;
        };
        call _rerender;
    };
    case "recv": {
        _arg params [["_r", createHashMap], ["_side", ""]];
        if (_side isNotEqualTo str side group player) exitWith {};
        private _id = _r getOrDefault ["id", ""];
        if (_id isEqualTo "" || {_id in _list}) exitWith {};
        _list set [_id, _r];
        if !(missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false]) then {
            private _labels = [_r] call comspec_atak_native_fnc_bdaLabels;
            ["TACTICAL", format ["BDA de %1 : %2 %3 en %4", _r get "by", _labels select 0, _labels select 1, _r get "grid"], 5, 40] call comspec_atak_native_fnc_notify;
        };
        call _rerender;
    };
    case "map": {
        private _p = (_list getOrDefault [_arg, createHashMap]) getOrDefault ["pos", []];
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;
    };
    case "del": { _list deleteAt _arg; call _rerender; };
};
true
