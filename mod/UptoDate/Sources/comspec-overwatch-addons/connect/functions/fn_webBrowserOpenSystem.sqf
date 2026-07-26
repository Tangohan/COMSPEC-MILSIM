/*
    Ouvre la carte Athena dans le navigateur système (contournement si CT_WEBBROWSER
    remote / allowExternalURL indisponible hors Development).
*/
if (!hasInterface) exitWith {};

private _url = trim (missionNamespace getVariable ["comspec_overwatch_api_url", ""]);
if (_url isEqualTo "") then {
    _url = trim (profileNamespace getVariable ["comspec_overwatch_saved_api_url", ""]);
};
if (_url isEqualTo "") then {
    _url = "https://athena.ttrd.fr/public";
};

while {(_url select [(count _url) - 1, 1]) isEqualTo "/"} do {
    _url = _url select [0, (count _url) - 1];
};

private _atakUrl = _url + "/atak";

private _now = diag_tickTime;
private _last = missionNamespace getVariable ["COMSPEC_WebBrowser_SystemOpenAt", -1e9];
if ((_now - _last) < 3) exitWith {};
missionNamespace setVariable ["COMSPEC_WebBrowser_SystemOpenAt", _now];
missionNamespace setVariable ["COMSPEC_WebBrowser_AthenaOpenAt", _now];

openURL _atakUrl;

private _display = findDisplay 9974;
if (!isNull _display) then {
    private _hint = _display displayCtrl 9403;
    if (!isNull _hint) then {
        _hint ctrlSetStructuredText parseText "<t align='right' size='0.5' color='#7dffb3'>Ouverture sur le PC…</t>";
    };
};

private _lastNotify = missionNamespace getVariable ["COMSPEC_WebBrowser_AthenaNotifyAt", -1e9];
if ((_now - _lastNotify) >= 12) then {
    missionNamespace setVariable ["COMSPEC_WebBrowser_AthenaNotifyAt", _now];
    ["COMSPEC_Info", ["Ouverture du portail Athena dans le navigateur de votre PC…"]] call comspec_overwatch_connect_fnc_showNotification;
};
