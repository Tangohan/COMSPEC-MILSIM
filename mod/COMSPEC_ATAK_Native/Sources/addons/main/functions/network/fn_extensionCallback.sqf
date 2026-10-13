params ["_name","_function",["_payload",""]];
if (_name isNotEqualTo "comspec") exitWith {};
// Réponse d'un appel non bloquant (fn_extAwait), quelle que soit la DLL qui rappelle.
if (_function isEqualType "" && {(_function select [0, 6]) isEqualTo "async:"}) exitWith {
    private _pending = missionNamespace getVariable ["COMSPEC_ATAK_ExtPending", createHashMap];
    private _ticket = _function select [6];
    if (_ticket in _pending) then { _pending set [_ticket, [_payload]]; };
};
// Présentation Google chargée depuis l'engrenage de l'app Briefing (fn_briefingCfg) : l'erreur est gardée pour
// l'expliquer dans le panneau, et l'ancien tableau de briefing ouvert par Overwatch sur google_deck_ready est refermé
// (le téléphone affiche déjà la diapositive). Le rappel reste traité par Overwatch ensuite.
if (_function isEqualTo "google_deck_error") then {
    private _e = parseSimpleArray _payload;
    if ((_e param [0, ""]) isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_BriefOwnLoad", "-"])) then { uiNamespace setVariable ["COMSPEC_ATAK_BriefGErr", _e]; };
};
if (_function isEqualTo "google_deck_ready" && {((parseSimpleArray _payload) param [0, ""]) isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_BriefOwnLoad", "-"])}) then {
    [{ [{ if (!isNull (findDisplay 9970) && {!isNull ([] call comspec_atak_native_fnc_display)}) then { (findDisplay 9970) closeDisplay 2; }; }] call CBA_fnc_execNextFrame; }] call CBA_fnc_execNextFrame;
};
// Les deux DLL rappellent sous le même nom : avec Overwatch, ces rappels sont les siens, pas ceux du natif.
if ([] call comspec_atak_native_fnc_bridge) exitWith {};

private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _event = toLower (if (_function isEqualType "") then { _function } else { str _function });
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
