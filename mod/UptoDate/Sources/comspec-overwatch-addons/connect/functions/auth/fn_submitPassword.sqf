private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (isNull _d) exitWith {};
private _email = trim (ctrlText (_d displayCtrl 9401));
private _pass = ctrlText (_d displayCtrl 9402);
if ((count _email) < 5 || {(count _pass) < 1}) exitWith {
    [(_d displayCtrl 9410), "<t align='center' size='0.55' color='#e8b84a'>Indiquez votre adresse e-mail et votre mot de passe.</t>"] call comspec_overwatch_connect_fnc_setPlainText;
};
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
private _pack = [] call comspec_overwatch_connect_fnc_packVersion;
private _steam = if (!isNull player) then { getPlayerUID player } else { "" };
[(_d displayCtrl 9410), "<t align='center' size='0.55' color='#7aa89a'>Authentification en cours…</t>"] call comspec_overwatch_connect_fnc_setPlainText;
// OPT-08 : HTTP hors fil jeu (bouton dialog = non suspendable → spawn + extAwait).
[_url, _email, _pass, _pack, _steam] spawn {
    params ["_url", "_email", "_pass", "_pack", "_steam"];
    if ((count _steam) >= 8) then {
        [["COMSPECExtension", "SetSteamId", [_steam]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    };
    [["COMSPECExtension", "AuthPassword", [_url, _email, _pass, _pack, _steam], 40] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    [] call comspec_overwatch_connect_fnc_pollAuth;
};
