params ["_name","_function",["_payload",""]];
if (_name isNotEqualTo "comspec") exitWith {};

private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _event = toLower str _function;
switch _event do {
    case "connected": {
        _state set ["networkState","CONNECTED"];
        ["SUCCESS","ATHENA RECONNECTÉ",4,60] call comspec_atak_native_fnc_notify;
    };
    case "error": {
        _state set ["networkState","OFFLINE"];
        ["WARNING","ATHENA OFFLINE",4,50] call comspec_atak_native_fnc_notify;
    };
    case "networkhiccup": {
        _state set ["networkState","DEGRADED"];
        private _seconds = ((parseNumber _payload) max 1) min 30;
        missionNamespace setVariable ["COMSPEC_ATAK_NativeBackoffUntil",diag_tickTime + _seconds,false];
    };
    case "ratelimited": {
        _state set ["networkState","DEGRADED"];
        private _seconds = ((parseNumber _payload) max 2) min 600;
        missionNamespace setVariable ["COMSPEC_ATAK_NativeBackoffUntil",diag_tickTime + _seconds,false];
    };
    case "ratelimitclear": {
        _state set ["networkState","CONNECTED"];
        missionNamespace setVariable ["COMSPEC_ATAK_NativeBackoffUntil",0,false];
    };
    case "accessdenied": {
        _state set ["networkState","OFFLINE"];
        ["ERROR","Accès Athena refusé",5,80] call comspec_atak_native_fnc_notify;
    };
    case "bftidentity": {
        private _parts = _payload splitString (toString [9]);
        private _profile = _state getOrDefault ["userProfile",createHashMap];
        _profile set ["callsign",_parts param [0,""]];
        _profile set ["bftId",_parts param [1,""]];
    };
    case "google_deck_ready": {
        private _payloadArray = parseSimpleArray _payload;
        private _briefing = (uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["briefing",createHashMap];
        _briefing set ["index",_payloadArray param [2,0]];
        _briefing set ["total",_payloadArray param [3,1]];
        _briefing set ["path",_payloadArray param [4,""]];
    };
    case "google_slide_ready": {
        private _payloadArray = parseSimpleArray _payload;
        private _briefing = (uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["briefing",createHashMap];
        _briefing set ["path",_payloadArray param [1,""]];
        _briefing set ["index",_payloadArray param [2,0]];
        _briefing set ["total",_payloadArray param [3,1]];
    };
    case "google_deck_error": {
        ["ERROR","Briefing indisponible",5,50] call comspec_atak_native_fnc_notify;
    };
};
["INFO","EXT",format ["Callback %1",_function]] call comspec_atak_native_fnc_log;
