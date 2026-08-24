/*
    Sons roleplay (coupure / zone). Délègue au canal ATAK (pas de bips vanilla).
    Params: _type — "disconnect" | "reconnect" | "glitch" | "warning" | "degraded"
*/
params [["_type", "", [""]]];

if (_type isEqualTo "") exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", false])) exitWith {};

[_type] call comspec_overwatch_connect_fnc_playAtakEnhancedSound;
