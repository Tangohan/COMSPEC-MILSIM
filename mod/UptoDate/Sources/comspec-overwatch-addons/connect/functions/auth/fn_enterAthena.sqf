/*
    Bouton ENTRER : tente d’ouvrir le canal poste avec le Steam du joueur,
    applique le profil, démarre les transmissions si READY, puis ferme.
*/
if (!hasInterface) exitWith {};

private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
if (!isNull _d) then {
    (_d displayCtrl 9410) ctrlSetStructuredText parseText "<t align='center' size='0.55' color='#7aa89a'>Ouverture du canal poste…</t>";
};

private _steam = if (!isNull player) then { getPlayerUID player } else { "" };
if ((count _steam) >= 8) then {
    ["COMSPECExtension" callExtension ["SetSteamId", [_steam]]] call comspec_overwatch_connect_fnc_extResult;
};

private _raw = ["COMSPECExtension" callExtension ["ConnectC2", []]] call comspec_overwatch_connect_fnc_extResult;
["INFO", "Athena", format ["ENTRER — canal poste %1", _raw]] call comspec_overwatch_connect_fnc_log;

[] call comspec_overwatch_connect_fnc_applyBootstrap;
[] call comspec_overwatch_connect_fnc_pollAuth;

if ([] call comspec_overwatch_connect_fnc_isReady) then {
    [] call comspec_overwatch_connect_fnc_startSyncLoops;
    closeDialog 1;
} else {
    // Comme le pack Workshop 06-09 : fermer quand même si le profil est déjà là.
    closeDialog 1;
};
