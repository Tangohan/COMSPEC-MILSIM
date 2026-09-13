/*
    Connexion Athena par l’identifiant Steam du joueur.
    _silent : démarrage mission (pas de fenêtre). Sinon messages dans l’écran Connexion.
*/
params [["_silent", false, [true]]];

if (!_silent) then {
    private _d0 = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
    if (!isNull _d0) then {
        (_d0 displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#7aa89a'>Authentification en cours…</t>";
    };
};

private _deadline = diag_tickTime + 8;
waitUntil {
    (!isNull player && {(count (getPlayerUID player)) >= 8})
    || {diag_tickTime > _deadline}
};

private _steam = if (!isNull player) then { getPlayerUID player } else { "" };
if ((count _steam) < 8) then {
    _steam = getPlayerUID player;
};
if ((count _steam) < 8) exitWith {
    if (!_silent) then {
        private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
        if (!isNull _d) then {
            (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Steam n’est pas disponible sur cet ordinateur.</t>";
        };
    };
    false
};

private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
["INFO", "Athena", "Connexion Steam"] call comspec_overwatch_connect_fnc_log;
private _raw = ["COMSPECExtension" callExtension ["AuthSteam", [_url, _steam, [] call comspec_overwatch_connect_fnc_packVersion]]] call comspec_overwatch_connect_fnc_extResult;
["INFO", "Athena", format ["Connexion Steam — %1", _raw]] call comspec_overwatch_connect_fnc_log;
[] call comspec_overwatch_connect_fnc_pollAuth;

if (_raw find "OK|READY" == 0) exitWith {
    [] call comspec_overwatch_connect_fnc_applyBootstrap;
    true
};

if (!_silent) then {
    private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
    if (!isNull _d && {_raw find "STEAM_NOT_LINKED" >= 0}) then {
        (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Ce Steam n’est pas encore associé à un compte Athena. Connectez-vous une fois avec votre e-mail, ou faites lier Steam sur le portail.</t>";
    };
};
false
