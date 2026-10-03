/* Actions de l'app BFT sur la piste choisie. Params : ["center" | "route" | "sms" | "buzz"] */
params [["_act", "center"]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _e = ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]) getOrDefault [_s getOrDefault ["bftSel", ""], createHashMap];
if ((count _e) isEqualTo 0) exitWith { false };
private _obj = _e getOrDefault ["object", objNull];
switch (_act) do {
    case "route": {
        private _p = [_e getOrDefault ["position", [0, 0, 0]], getPosATL _obj] select !isNull _obj;
        [[_p select 0, _p select 1, 0], _e getOrDefault ["callsign", "Piste"]] spawn comspec_atak_native_fnc_routeCompute;
        ["MAP"] call comspec_atak_native_fnc_navigate;
    };
    case "buzz": {
        if (isNull _obj || {!isPlayer _obj}) exitWith {};
        private _cd = missionNamespace getVariable ["COMSPEC_ATAK_BuzzAt", createHashMap];
        private _k = netId _obj;
        if ((diag_tickTime - (_cd getOrDefault [_k, -1e9])) < 10) exitWith { ["INFO", "Patientez quelques secondes avant de refaire vibrer.", 3] call comspec_atak_native_fnc_notify; };
        _cd set [_k, diag_tickTime];
        missionNamespace setVariable ["COMSPEC_ATAK_BuzzAt", _cd];
        [{ params ["_t", "_from", "_grid"]; ["comspec_atak_native_buzz", [_from, _grid], _t] call CBA_fnc_targetEvent; },
            [_obj, [player] call comspec_atak_native_fnc_unitCallsign, [getPosASL player, 6] call comspec_atak_native_fnc_gridRef], "Vibreur", 1] call comspec_atak_native_fnc_netSend;
        ["INFO", format ["Vibreur envoyé à %1", _e getOrDefault ["callsign", name _obj]], 3] call comspec_atak_native_fnc_notify;
    };
    case "sms": {
        if (isNull _obj) exitWith {};
        _s set ["chatPeer", name _obj];
        ["CHAT"] call comspec_atak_native_fnc_navigate;
    };
    default {
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [[_obj, _e getOrDefault ["position", [0, 0, 0]]] select (isNull _obj), 0.03] call comspec_atak_native_fnc_mapCenter;
    };
};
true
