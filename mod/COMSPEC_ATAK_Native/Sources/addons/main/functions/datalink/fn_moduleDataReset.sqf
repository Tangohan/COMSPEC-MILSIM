/*
    Module Zeus « Remettre / retirer les données » : sur un support, remet à zéro les essais, le verrou et
    l'effacement (les fichiers redeviennent disponibles), ou retire toutes les données de l'objet.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
private _targets = (_ctx get "targets") select { !isNil { _x getVariable "COMSPEC_ATAK_Data" } };
private _near = _ctx get "near";
if (_targets isEqualTo [] && {!isNull _near} && {!isNil { _near getVariable "COMSPEC_ATAK_Data" }}) then { _targets pushBack _near; };
deleteVehicle _logic;
if (_targets isEqualTo []) exitWith { ["comspec_atak_native_dataNote", ["Aucun support de données sous le module."]] call CBA_fnc_localEvent; true };
private _apply = {
    params ["_vals", "_targets"];
    { ["comspec_atak_native_dataSrv", [_vals select 0, _x, player, []]] call CBA_fnc_serverEvent; } forEach _targets;
    ["comspec_atak_native_dataNote", [format ["%1 support(s) : %2.", count _targets, ["remis en état", "données retirées"] select ((_vals select 0) isEqualTo "remove")]]] call CBA_fnc_localEvent;
};
if (_ctx get "zeus") then {
    ["Données du support", [["COMBO", "Action", [["Remettre en état (essais, verrou, effacement)", "reset"], ["Retirer les données", "remove"]], "reset"]], _apply, _targets] call comspec_atak_native_fnc_zeusForm;
} else {
    [["reset"], _targets] call _apply;
};
true
