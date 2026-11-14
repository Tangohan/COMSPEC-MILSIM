/*
    SERVEUR. Garde dans le profil du serveur les codes posés par Zeus (source ZEUS), pour qu'ils survivent au redémarrage.
    [] call comspec_lobby_fnc_pinSave
*/
if (!isServer) exitWith {};
private _zeus = [];
{ if ((_y select 1) isEqualTo "ZEUS") then { _zeus pushBack [_x, _y select 0]; }; } forEach (missionNamespace getVariable ["comspec_lobby_pins", createHashMap]);
profileNamespace setVariable ["COMSPEC_LOBBY_Pins", _zeus];
saveProfileNamespace;
