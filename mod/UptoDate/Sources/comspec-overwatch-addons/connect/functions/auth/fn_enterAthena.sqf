/*
    Bouton ENTRER : tente d’ouvrir le canal poste avec le Steam du joueur,
    applique le profil, démarre les transmissions si canal OK, puis ferme.
*/
if (!hasInterface) exitWith {};

private _work = {
    private _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
    if (!isNull _d) then {
        [(_d displayCtrl 9410), "<t align='center' size='0.55' color='#7aa89a'>Ouverture du canal poste…</t>"] call comspec_overwatch_connect_fnc_setPlainText;
    };

    private _steam = if (!isNull player) then { getPlayerUID player } else { "" };
    if ((count _steam) >= 8) then {
        [["COMSPECExtension", "SetSteamId", [_steam]] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    };

    // OPT-08 : ConnectC2 hors fil jeu.
    private _raw = [["COMSPECExtension", "ConnectC2", [], 40] call comspec_overwatch_connect_fnc_extAwait] call comspec_overwatch_connect_fnc_extResult;
    ["INFO", "Athena", format ["ENTRER — canal poste %1", _raw]] call comspec_overwatch_connect_fnc_log;

    private _opened = false;
    if (!isNil "comspec_overwatch_connect_fnc_reopenTransmitChannel") then {
        _opened = [] call comspec_overwatch_connect_fnc_reopenTransmitChannel;
    } else {
        [] call comspec_overwatch_connect_fnc_applyBootstrap;
        [] call comspec_overwatch_connect_fnc_pollAuth;
        if ([] call comspec_overwatch_connect_fnc_canStartSync) then {
            [] call comspec_overwatch_connect_fnc_startSyncLoops;
            _opened = true;
        };
    };

    _d = uiNamespace getVariable ["COMSPEC_AthenaAuth_Display", displayNull];
    if (!_opened && {!isNull _d}) then {
        [(_d displayCtrl 9410), "<t align='center' size='0.55' color='#e8b84a'>Canal poste encore refusé — reconnectez-vous ou utilisez un nouveau code Appairer.</t>"] call comspec_overwatch_connect_fnc_setPlainText;
    };

    closeDialog 1;
};

if (canSuspend) exitWith { call _work };
[] spawn _work;
