/*
    Post-init serveur : enregistre matériel (alias) + import modules déjà posés.
*/
if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith {};
if !([] call comspec_sse_fnc_biiIsPresent) exitWith {};

[] call comspec_sse_fnc_biiRegisterEquipment;

// Import différé des unités déjà seedées par modules BII Identity
[{
    if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith {};
    {
        if (_x isKindOf "CAManBase") then {
            [_x] call comspec_sse_fnc_biiImportEntityVars;
        };
        if (!(_x isKindOf "CAManBase") && {_x getVariable ["BII_Identifi_authoredEvidence", false]}) then {
            [_x] call comspec_sse_fnc_biiImportObject;
        };
    } forEach allUnits + vehicles + (allMissionObjects "ThingX");
}, [], 3] call CBA_fnc_waitAndExecute;
