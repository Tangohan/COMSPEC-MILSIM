/*
    Module Eden « Atelier IA » (SERVEUR) : le profil du module est appliqué à chaque IA synchronisée (et à son groupe).
    « Une personnalité par IA » coché : un tirage par IA (bouton Générer), champs verrouillés du module gardés pour tous.
    [_logic, _units, _activated] call comspec_sse_fnc_iarpModule
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (!isServer || {isNull _logic} || {!_activated}) exitWith { true };
private _p = ["parse", _logic getVariable ["IarpProfile", ""]] call comspec_sse_fnc_iarpProfile;
if ((count _p) isEqualTo 0) then { _p = ["defaults"] call comspec_sse_fnc_iarpProfile; };
private _each = _logic getVariable ["IarpEach", false];
private _ai = [];
{
    if (_x isKindOf "CAManBase") then { { if (!isPlayer _x) then { _ai pushBackUnique _x; }; } forEach (units group _x); };
} forEach ((synchronizedObjects _logic) + _units);
{
    private _q = if (_each) then { ["generate", [_p, _p get "locks"]] call comspec_sse_fnc_iarpProfile } else { +_p };
    ["apply", [_x, _q]] call comspec_sse_fnc_iarpProfile;
} forEach _ai;
diag_log text format ["[COMSPEC SSE] IA RP : module Atelier IA, profil appliqué à %1 IA.", count _ai];
true
