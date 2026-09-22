/*
    Fixe l’étage du découpage sur le bâtiment désigné.
    Params: [_floorIndex, _announce]
*/
params [["_sel", 0, [0]], ["_announce", true, [true]]];

if (!hasInterface) exitWith { -1 };
if (!([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)) exitWith {
    if (_announce) then {
        ["Affichage situation indisponible.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
    };
    -1
};

private _cutaway = missionNamespace getVariable ["comspec_overwatch_ecoti_building_cutaway", false];
if (!(_cutaway isEqualType true) || {!_cutaway}) exitWith {
    if (_announce) then {
        ["Activez d’abord le découpage d’étage (Paramètres).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
    };
    -1
};

private _bldg = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull];
if (isNull _bldg) exitWith {
    if (_announce) then {
        ["Désignez d’abord un bâtiment (menu ACE ou fiche téléphone).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
    };
    -1
};

private _floors = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloorCount", 1];
if (!(_floors isEqualType 0) || {_floors < 1}) then { _floors = 1; };
_floors = (round _floors) max 1;

if (!(_sel isEqualType 0)) then { _sel = 0; };
_sel = (round _sel) max 0 min (_floors - 1);

private _prev = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloor", 0];
if (!(_prev isEqualType 0)) then { _prev = 0; };
if (_sel isEqualTo (round _prev)) exitWith { _sel };

missionNamespace setVariable ["COMSPEC_EcotiCutawayFloor", _sel, false];

if (_announce) then {
    [
        format ["Découpage : étage %1 sur %2.", _sel + 1, _floors],
        "system",
        "info"
    ] call comspec_overwatch_connect_fnc_announce;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updateBuildingSheet") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updateBuildingSheet;
};
_sel
