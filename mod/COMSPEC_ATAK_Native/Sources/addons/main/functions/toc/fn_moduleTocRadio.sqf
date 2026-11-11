/*
    Module « Activité radio » (Zeus et Eden) : qui parle, sur quel réseau, quelle fréquence (fn_tocRadio, fn_tocRadioUi).
    Zeus : lâché n'importe où, il ouvre la console radio avec « Tout surveiller » coché (tous les camps, tous les
    réseaux) ; la liste de veille de chaque camp s'y modifie. Il ne pose rien et se réutilise autant de fois qu'on veut.
    Eden : attributs Camp et Réseaux surveillés (« CMD NET=31.000*, AIR=40.500, 152.250 »). Au lancement, le serveur
    pose cette liste de veille pour le camp et allume la collecte sans attendre un premier écran. Sans réseau écrit,
    le réglage serveur « Réseaux surveillés par défaut » reste en place.
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (is3DEN) exitWith { true };
private _ctx = [_logic, _units, _activated] call comspec_atak_native_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };
// Module de la mission : ses attributs Eden sont posés. Module venu d'un Zeus : aucun attribut, la console s'ouvre.
private _eden = !isNil { _logic getVariable "RadioSide" };
if (_eden || {!hasInterface}) exitWith {
    if (!isServer) exitWith { true };
    private _side = _logic getVariable ["RadioSide", "WEST"];
    private _nets = ["parse", _logic getVariable ["RadioNets", ""]] call comspec_atak_native_fnc_tocRadio;
    if (_side in ["WEST", "EAST", "GUER", "CIV"] && {_nets isNotEqualTo []}) then {
        missionNamespace setVariable [format ["COMSPEC_ATAK_TocRadioNets_%1", _side], _nets, true];
    };
    ["srvOn"] call comspec_atak_native_fnc_tocRadio;
    true
};
private _zeus = (_ctx get "zeus") || {hasInterface && {time > 1} && {!isNull (getAssignedCuratorLogic player)}};
deleteVehicle _logic;
if (!_zeus) exitWith { true };
[{ ["open", ["", true]] call comspec_atak_native_fnc_tocRadioUi; }, [], 0.2] call comspec_atak_native_fnc_waitAndExec;
true
