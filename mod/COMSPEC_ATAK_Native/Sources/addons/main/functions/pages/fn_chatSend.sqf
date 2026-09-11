disableSerialization; private _edit=uiNamespace getVariable ["COMSPEC_ATAK_ChatEdit",controlNull]; if (isNull _edit) exitWith {false}; private _message=trim ctrlText _edit; if (_message isEqualTo "") exitWith {false};
private _author=if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {[] call comspec_overwatch_connect_fnc_getCallsign} else {name player};
private _raw=["SendChat",[_author,_message]] call comspec_atak_native_fnc_extensionCall; private _ok=(_raw find "OK|") isEqualTo 0;
if (_ok) then {_edit ctrlSetText ""; ["SUCCESS","Message envoyé",3,20] call comspec_atak_native_fnc_notify;} else {["WARNING","Message conservé localement — Athena indisponible",4,30] call comspec_atak_native_fnc_notify;}; _ok
