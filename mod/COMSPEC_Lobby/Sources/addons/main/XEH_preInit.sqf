/*
    COMSPEC Lobby : réglages CBA (Options > Réglages de l'addon > COMSPEC Lobby). Réglages « serveur » : la valeur du serveur s'impose.
*/
private _cat = "COMSPEC Lobby";
private _add = {
    params ["_name", "_type", "_key", "_values", ["_global", 1]];
    [_name, _type, [localize format ["STR_COMSPEC_LOBBY_Set_%1", _key], localize format ["STR_COMSPEC_LOBBY_Set_%1_Tip", _key]], _cat, _values, _global] call CBA_fnc_addSetting;
};
["comspec_lobby_enabled", "CHECKBOX", "Enabled", true] call _add;
["comspec_lobby_attempts", "SLIDER", "Attempts", [1, 10, 3, 0]] call _add;
["comspec_lobby_grace", "SLIDER", "Grace", [10, 180, 45, 0]] call _add;
["comspec_lobby_kick", "CHECKBOX", "Kick", true] call _add;
