private _steam = if (!isNull player) then { getPlayerUID player } else { "" };
if ((count _steam) < 8) then {
    _steam = getPlayerUID player;
};
if ((count _steam) < 8) exitWith {
    private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
    if (!isNull _d) then {
        (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Steam n’est pas disponible sur cet ordinateur.</t>";
    };
};
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
private _raw = ["COMSPECExtension" callExtension ["AuthSteam", [_url, _steam, [] call comspec_overwatch_connect_fnc_packVersion]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw find "STEAM_NOT_LINKED" >= 0) then {
    private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
    if (!isNull _d) then {
        (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#e8b84a'>Steam n’est pas encore associé à ce poste. Connectez-vous une première fois avec votre e-mail.</t>";
    };
} else {
    [] call comspec_overwatch_connect_fnc_pollAuth;
};
