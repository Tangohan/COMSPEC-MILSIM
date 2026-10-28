/*
    Préremplit le dialog Connexion Athena et démarre la barre de transmission / ms.
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull];
if (isNull _display) exitWith {};

private _steam = profileNamespace getVariable ["comspec_overwatch_saved_steam_uid", ""];
if (_steam isEqualTo "") then {
    private _uid = getPlayerUID player;
    if ((count _uid) >= 15) then { _steam = _uid; };
};
private _steamCtrl = _display displayCtrl 9206;
if (!isNull _steamCtrl) then { _steamCtrl ctrlSetText _steam; }; // i18n:brut

private _status = _display displayCtrl 9203;
if (!isNull _status) then {
    private _hint = if (_steam isEqualTo "") then {
        "Collez le code généré sur le portail (Appairer), ou votre identifiant Steam déjà lié."
    } else {
        "Préférez le code du portail. Sinon, validez avec Steam déjà lié au profil."
    };
    [_status, format ["<t align='center' size='0.55' color='#6a7c90'>%1</t>", _hint]] call comspec_overwatch_connect_fnc_setPlainText;
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
