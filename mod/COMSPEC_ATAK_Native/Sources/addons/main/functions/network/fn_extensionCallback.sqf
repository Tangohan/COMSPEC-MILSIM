params ["_name","_function",["_payload",""]]; if (_name isNotEqualTo "comspec") exitWith {};
private _s=uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap]; private _f=toLower str _function;
switch _f do {
 case "connected": {_s set ["networkState","CONNECTED"]; ["SUCCESS","ATHENA RECONNECTÉ",4,60] call comspec_atak_native_fnc_notify;};
 case "error": {_s set ["networkState","OFFLINE"]; ["WARNING","ATHENA OFFLINE",4,50] call comspec_atak_native_fnc_notify;};
 case "networkhiccup": {_s set ["networkState","DEGRADED"];};
 case "ratelimited": {_s set ["networkState","DEGRADED"];};
 case "ratelimitclear": {_s set ["networkState","CONNECTED"];};
 case "accessdenied": {_s set ["networkState","OFFLINE"]; ["ERROR","Accès Athena refusé",5,80] call comspec_atak_native_fnc_notify;};
 case "bftidentity": {private _parts=_payload splitString (toString [9]); private _profile=_s getOrDefault ["userProfile",createHashMap]; _profile set ["callsign",_parts param [0,""]]; _profile set ["bftId",_parts param [1,""]];};
 case "google_deck_ready": {private _p=parseSimpleArray _payload; private _b=(uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["briefing",createHashMap]; _b set ["index",_p param [2,0]]; _b set ["total",_p param [3,1]]; _b set ["path",_p param [4,""]];};
 case "google_slide_ready": {private _p=parseSimpleArray _payload; private _b=(uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]) getOrDefault ["briefing",createHashMap]; _b set ["path",_p param [1,""]]; _b set ["index",_p param [2,0]]; _b set ["total",_p param [3,1]];};
 case "google_deck_error": {["ERROR","Briefing indisponible",5,50] call comspec_atak_native_fnc_notify;};
};
["INFO","EXT",format ["Callback %1",_function]] call comspec_atak_native_fnc_log;
