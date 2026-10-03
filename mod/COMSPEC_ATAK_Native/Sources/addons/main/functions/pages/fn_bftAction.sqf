/* Actions de l'app BFT sur la piste choisie. Params : ["center" | "sms"] */
params [["_act", "center"]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _e = ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["units", createHashMap]) getOrDefault [_s getOrDefault ["bftSel", ""], createHashMap];
if ((count _e) isEqualTo 0) exitWith { false };
private _obj = _e getOrDefault ["object", objNull];
switch (_act) do {
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
