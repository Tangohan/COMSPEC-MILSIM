/*
    Passe à l’étage suivant (mode découpage) sur le bâtiment désigné.
*/
if (!hasInterface) exitWith {};
if (!([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)) exitWith {
    ["Affichage situation indisponible.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _cutaway = missionNamespace getVariable ["comspec_overwatch_ecoti_building_cutaway", false];
if (!(_cutaway isEqualType true) || {!_cutaway}) exitWith {
    ["Activez d’abord le découpage d’étage (Paramètres ou Options CBA).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _bldg = missionNamespace getVariable ["COMSPEC_EcotiMarkedBuilding", objNull];
if (isNull _bldg) exitWith {
    ["Désignez d’abord un bâtiment (menu ACE).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _floors = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloorCount", 1];
if (!(_floors isEqualType 0) || {_floors < 1}) then { _floors = 1; };
_floors = (round _floors) max 1;

private _sel = missionNamespace getVariable ["COMSPEC_EcotiCutawayFloor", 0];
if (!(_sel isEqualType 0)) then { _sel = 0; };
_sel = ((round _sel) + 1) mod _floors;
missionNamespace setVariable ["COMSPEC_EcotiCutawayFloor", _sel, false];

[
    format ["Découpage : étage %1 sur %2.", _sel + 1, _floors],
    "system",
    "info"
] call comspec_overwatch_connect_fnc_announce;
_sel
