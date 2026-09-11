/*
    Démarre l’enregistrement et la surveillance de la fiche opérateur jeu.
    Séparé des ticks de position : loadout / visage / versions seulement si ça change.
    Premier register différé (évite HTTP sync pendant le quiet / open ATAK).
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_OperatorProfileSyncStarted", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_OperatorProfileSyncStarted", true, false];

[{
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]) exitWith {};
    if (isNull player || {!alive player}) exitWith {};
    // Sans force : le backoff 401 s’applique (pas de martèlement sync).
    ["register", "first_connect", false] call comspec_overwatch_connect_fnc_syncOperatorProfile;
}, [], 8] call CBA_fnc_waitAndExecute;

private _pfh = missionNamespace getVariable ["COMSPEC_OperatorProfilePfh", -1];
if (!(_pfh isEqualType 0) || {_pfh < 0}) then {
    _pfh = [{
        if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]) exitWith {};
        ["periodic"] call comspec_overwatch_connect_fnc_operatorProfileTick;
    }, 8] call CBA_fnc_addPerFrameHandler;
    missionNamespace setVariable ["COMSPEC_OperatorProfilePfh", _pfh, false];
};

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
