/*
    Indicatif tactique d'une unité (jamais le pseudo quand un indicatif existe).
    Ordre : indicatif Overwatch du joueur local, COMSPEC_CallsignPublic, COMSPEC_AllyCallsign.
    Params : [unité (player), autoriser vide (false)]. Sans indicatif : "" ou le pseudo / nom de groupe.
*/
params [["_unit", player], ["_allowEmpty", false]];
private _take = {
    params ["_raw"];
    if !(_raw isEqualType "") then { _raw = str _raw; };
    _raw = trim _raw;
    if (_raw in ["", "Operateur", "<null>", "any"]) then { "" } else { _raw }
};
private _cs = "";
if (_unit isEqualTo player && {!isNil "comspec_overwatch_connect_fnc_getCallsign"}) then {
    _cs = [[true] call comspec_overwatch_connect_fnc_getCallsign] call _take;
};
if (_cs isEqualTo "" && {!isNull _unit}) then { _cs = [_unit getVariable ["COMSPEC_CallsignPublic", ""]] call _take; };
if (_cs isEqualTo "" && {!isNull _unit}) then { _cs = [_unit getVariable ["COMSPEC_AllyCallsign", ""]] call _take; };
if (_cs isNotEqualTo "" || {_allowEmpty}) exitWith { _cs };
if (isNull _unit) exitWith { "" };
[groupId group _unit, name _unit] select (isPlayer _unit)
