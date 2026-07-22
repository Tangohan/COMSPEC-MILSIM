/*
    Envoie une photo aux membres via UploadReconImage (Transmission),
    avec la légende saisie dans la messagerie.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_Chat_Display", displayNull];
private _caption = "";
if (!isNull _display) then {
    private _ctrl = _display displayCtrl 1400;
    if (!isNull _ctrl) then { _caption = trim (ctrlText _ctrl); };
};
if (_caption isEqualTo "") then {
    _caption = format ["Photo — %1", [] call comspec_overwatch_connect_fnc_getCallsign];
};

["", _caption] call comspec_overwatch_connect_fnc_captureReconImage;

if (!isNull _display) then {
    private _console = _display displayCtrl 1401;
    if (!isNull _console) then {
        private _nl = toString [10];
        private _line = format ["[Photo] Envoyée — %1", _caption];
        _console ctrlSetText ((ctrlText _console) + _line + _nl);
    };
    private _ctrl = _display displayCtrl 1400;
    if (!isNull _ctrl) then { _ctrl ctrlSetText ""; };
};

["_PHOTO", createHashMapFromArray [
    ["caption", _caption],
    ["author", [] call comspec_overwatch_connect_fnc_getCallsign]
]] call comspec_overwatch_connect_fnc_publishEvent;
