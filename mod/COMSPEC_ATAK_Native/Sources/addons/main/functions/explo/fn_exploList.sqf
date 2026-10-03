/*
    Charges posées par le joueur (ACE, raccordées ATAK par Overwatch, minuteries, mines vanilla).
    Renvoie [HashMap...] : obj, key, kind ("atak" | "command" | "timer" | "mine"), label, cid, placedAt, fuse, remaining, grid.
    Mise en cache une demi-seconde (la carte l'appelle à chaque image).
*/
params [["_force", false]];
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_ExploCache", [-1, []]];
if (!_force && {(diag_tickTime - (_cache select 0)) < 0.5}) exitWith { _cache select 1 };
private _seen = [];
private _out = [];
private _add = {
    params ["_e", ["_kindHint", ""]];
    if (isNull _e || {!alive _e} || {_e in _seen}) exitWith {};
    if (_e getVariable ["COMSPEC_detonateFired", false]) exitWith {};
    _seen pushBack _e;
    private _kind = toLower (_e getVariable ["COMSPEC_triggerKind", _kindHint]);
    if (_kind in ["", "clacker", "cellphone"]) then { _kind = "command"; };
    private _mag = _e getVariable ["ace_explosives_class", ""];
    if !(_mag isEqualType "") then { _mag = ""; };
    private _label = if (_mag isNotEqualTo "") then { getText (configFile >> "CfgMagazines" >> _mag >> "displayName") } else { "" };
    if (_label isEqualTo "") then { _label = getText (configOf _e >> "displayName"); };
    if (_label isEqualTo "") then { _label = "Charge"; };
    private _placed = _e getVariable ["COMSPEC_ATAK_PlacedAt", -1];
    private _fuse = _e getVariable ["COMSPEC_fuseSeconds", 0];
    if !(_fuse isEqualType 0) then { _fuse = 0; };
    private _rem = if (_kind isEqualTo "timer" && {_fuse > 0} && {_placed >= 0}) then { (_fuse - (time - _placed)) max 0 } else { -1 };
    private _cid = _e getVariable ["COMSPEC_chargeId", ""];
    if !(_cid isEqualType "") then { _cid = ""; };
    _out pushBack createHashMapFromArray [["obj", _e], ["key", _e call BIS_fnc_netId], ["kind", _kind], ["label", _label], ["cid", _cid],
        ["placedAt", _placed], ["fuse", _fuse], ["remaining", _rem], ["grid", [getPosASL _e, 8] call comspec_atak_native_fnc_gridRef]];
};
// Ordre de pose (suivi par l'événement ace_explosives_place), puis ce qu'ACE et Overwatch connaissent en plus.
{ [_x] call _add; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MyCharges", []]);
{ if ((_x isEqualType []) && {(count _x) > 0}) then { [_x select 0, "command"] call _add; }; } forEach (player getVariable ["ace_explosives_clackers", []]);
if (!isNil "comspec_overwatch_connect_fnc_chargeOwnedAtak") then { { [_x select 1, "atak"] call _add; } forEach ([player] call comspec_overwatch_connect_fnc_chargeOwnedAtak); };
{ [_x, "mine"] call _add; } forEach (getAllOwnedMines player);
uiNamespace setVariable ["COMSPEC_ATAK_ExploCache", [diag_tickTime, _out]];
_out
