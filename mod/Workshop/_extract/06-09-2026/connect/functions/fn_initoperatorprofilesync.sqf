/*
    Démarre l’enregistrement et la surveillance de la fiche opérateur jeu.
    Séparé des ticks de position : loadout / visage / versions seulement si ça change.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_OperatorProfileSyncStarted", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_OperatorProfileSyncStarted", true, false];

0 spawn {
    uiSleep 2;
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    if (isNull player || {!alive player}) exitWith {};
    ["register", "first_connect", true] call comspec_overwatch_connect_fnc_syncOperatorProfile;
};

[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    ["periodic"] call comspec_overwatch_connect_fnc_operatorProfileTick;
}, 8] call CBA_fnc_addPerFrameHandler;

if (isNil "COMSPEC_OperatorLoadoutEH") then {
    COMSPEC_OperatorLoadoutEH = ["loadout", {
        params ["_unit"];
        if (_unit isNotEqualTo player) exitWith {};
        [{
            ["loadout_changed"] call comspec_overwatch_connect_fnc_operatorProfileTick;
        }, [], 1.5] call CBA_fnc_waitAndExecute;
    }] call CBA_fnc_addPlayerEventHandler;
};

if (isNil "COMSPEC_OperatorArsenalEH" && {isClass (configFile >> "CfgPatches" >> "ace_arsenal")}) then {
    COMSPEC_OperatorArsenalEH = ["ace_arsenal_displayClosed", {
        [{
            ["loadout_changed"] call comspec_overwatch_connect_fnc_operatorProfileTick;
        }, [], 1] call CBA_fnc_waitAndExecute;
    }] call CBA_fnc_addEventHandler;
};
