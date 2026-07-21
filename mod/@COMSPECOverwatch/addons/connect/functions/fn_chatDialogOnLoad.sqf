/*
    Initialisation du terminal messagerie (appelé depuis onLoad du dialog).
*/
params [["_display", displayNull]];
if (isNull _display) exitWith {};

uiNamespace setVariable ["COMSPEC_Chat_Display", _display];

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
private _host = "Athena";
if (_url != "") then {
    private _parts = _url splitString "/";
    if (count _parts > 2) then {
        private _h = _parts select 2;
        if (_h != "") then { _host = _h; };
    };
};

private _urlCtrl = _display displayCtrl 1399;
if (!isNull _urlCtrl) then { _urlCtrl ctrlSetText ("Portail : " + _host); };

private _ip = missionNamespace getVariable ["COMSPEC_userIp", "—"];
private _ipCtrl = _display displayCtrl 1398;
if (!isNull _ipCtrl) then { _ipCtrl ctrlSetText ("Votre adresse : " + _ip); };

private _log = missionNamespace getVariable ["COMSPEC_Log", ""];
private _logCtrl = _display displayCtrl 1402;
if (!isNull _logCtrl && {_log != ""}) then { _logCtrl ctrlSetText _log; };

[] call comspec_overwatch_connect_fnc_updateStatusBadges;
