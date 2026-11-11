/*
    comspec_fb_fnc_aiRestore : rend à une IA ses compétences d'avant le flash et efface les variables de l'effet.
    [_unit, _compétences] call comspec_fb_fnc_aiRestore
*/
params [["_unit", objNull, [objNull]], ["_orig", [], [[]]]];
if (isNull _unit) exitWith {};
if (count _orig == 6) then {
    {
        _unit setSkill [_x, _orig select _forEachIndex];
    } forEach ["aimingAccuracy", "aimingShake", "aimingSpeed", "spotDistance", "spotTime", "courage"];
};
private _pos = _unit getVariable ["comspec_fb_aiPos", ""];
if (_pos isNotEqualTo "") then { _unit setUnitPos ([_pos, "AUTO"] select (_pos == "Auto")) };
_unit setVariable ["comspec_fb_aiPos", nil];
_unit setVariable ["comspec_fb_aiSkills", nil];
_unit setVariable ["comspec_fb_aiPeak", nil];
_unit setVariable ["comspec_fb_aiEnd", nil];
["ia", format ["%1 : compétences rétablies", _unit]] call comspec_fb_fnc_log;
