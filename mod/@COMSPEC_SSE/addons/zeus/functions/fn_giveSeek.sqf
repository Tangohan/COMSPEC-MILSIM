/*
    Remet un terminal SEEK à une unité (appelé là où l'unité est locale).
    Avec Overwatch : le terminal SEEK II d'Overwatch ; sans : le SEEK II du pack SSE.
    [_unit] call comspec_sse_fnc_giveSeek
*/
params [["_unit", objNull, [objNull]]];
if (isNull _unit || {!local _unit}) exitWith { false };
if (!isNil "comspec_overwatch_connect_fnc_giveSeekTerminal") exitWith {
    [_unit, "COMSPEC_Item_SeekTerminal"] call comspec_overwatch_connect_fnc_giveSeekTerminal;
    true
};
if ("COMSPEC_SSE_SEEKII" in (items _unit)) exitWith { true };
if (_unit canAdd "COMSPEC_SSE_SEEKII") then { _unit addItem "COMSPEC_SSE_SEEKII"; true } else { false }
