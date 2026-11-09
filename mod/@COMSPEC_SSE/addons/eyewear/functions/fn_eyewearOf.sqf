/*
    Lunettes de sport COMSPEC portées par une unité, quel que soit le slot.
    Renvoie [id, slot] : slot "virtual" (slot COMSPEC), "glasses" (visage) ou "nvg" (JVN) ; ["", ""] sans lunettes COMSPEC.
    Le slot virtuel passe en premier : c'est lui qu'on voit devant les yeux s'il y a aussi un masque ou des JVN.
*/
params [["_unit", objNull, [objNull]]];
if (isNull _unit) exitWith { ["", ""] };
private _byClass = ([] call comspec_sse_fnc_eyewearCatalog) select 1;
private _v = _unit getVariable ["comspec_sse_eyewear_worn", ""];
if (_v isNotEqualTo "") exitWith { [_v, "virtual"] };
private _g = _byClass getOrDefault [toLower (goggles _unit), ""];
if (_g isNotEqualTo "") exitWith { [_g, "glasses"] };
private _n = _byClass getOrDefault [toLower (hmd _unit), ""];
if (_n isNotEqualTo "") exitWith { [_n, "nvg"] };
["", ""]
