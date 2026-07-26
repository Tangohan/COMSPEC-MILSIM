/*
    Préremplit le dialog Connexion Athena et démarre la barre de transmission / ms.
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull];
if (isNull _display) exitWith {};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (!(_url isEqualType "")) then { _url = ""; };
_url = trim _url;
if (_url isEqualTo "" || {(count _url) < 12}) then {
    _url = profileNamespace getVariable ["comspec_overwatch_saved_api_url", "https://athena.ttrd.fr/public"];
    if (!(_url isEqualType "")) then { _url = "https://athena.ttrd.fr/public"; };
    _url = trim _url;
};
if (_url isEqualTo "" || {(count _url) < 12}) then { _url = "https://athena.ttrd.fr/public"; };
private _urlLower = toLower _url;
if (((_urlLower find "https://") != 0) && {(_urlLower find "http://") != 0}) then {
    _url = "https://athena.ttrd.fr/public";
};

private _urlCtrl = _display displayCtrl 9201;
if (!isNull _urlCtrl) then { _urlCtrl ctrlSetText _url; };

private _steam = profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""];
if (_steam isEqualTo "") then {
    private _uid = getPlayerUID player;
    if ((count _uid) >= 15) then { _steam = _uid; };
};
private _steamCtrl = _display displayCtrl 9206;
if (!isNull _steamCtrl) then { _steamCtrl ctrlSetText _steam; };

private _status = _display displayCtrl 9203;
if (!isNull _status) then {
    private _hint = if (_steam isEqualTo "") then {
        "Collez votre identifiant Steam (profil Athena), ou générez un code sur le portail."
    } else {
        "Steam prérempli — validez la liaison, ou saisissez un code."
    };
    _status ctrlSetStructuredText parseText format ["<t align='center' size='0.55' color='#6a7c90'>%1</t>", _hint];
};

[] call comspec_overwatch_connect_fnc_refreshAccountLinkStatusBar;

private _token = diag_tickTime;
missionNamespace setVariable ["COMSPEC_AccountLink_StatusToken", _token, false];
[_token] spawn {
    params ["_token"];
    while {
        (missionNamespace getVariable ["COMSPEC_AccountLink_StatusToken", -1]) isEqualTo _token
        && {!isNull (uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull])}
    } do {
        [] call comspec_overwatch_connect_fnc_refreshAccountLinkStatusBar;
        uiSleep 2.5;
    };
};
