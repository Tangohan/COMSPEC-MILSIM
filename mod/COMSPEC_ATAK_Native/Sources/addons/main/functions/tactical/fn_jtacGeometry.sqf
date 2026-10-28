/*
    Géométrie d'attaque d'un appareil vers la cible de la mission : d'où il arrive, son cap, s'il pointe sur la cible
    et s'il respecte l'axe final d'attaque (FAH) des remarques.
    FAH saisi : « 270 » (± 20°), « 250-290 » (fenêtre) ou « 270 ± 15 ».
    Params : [appareil, mission (hashmap avec brief)]. Renvoie [texte, dans l'axe (bool)] ou ["", true] sans données.
*/
params [["_air", objNull], ["_m", createHashMap]];
private _b = _m getOrDefault ["brief", createHashMap];
private _tgt = _b getOrDefault ["target", []];
if (isNull _air || {(count _tgt) < 2}) exitWith { ["", true] };
private _card = { params ["_deg"]; ["NORTH", "NORTHEAST", "EAST", "SOUTHEAST", "SOUTH", "SOUTHWEST", "WEST", "NORTHWEST"] select ((round (_deg / 45)) mod 8) };
private _diff = { params ["_a", "_b"]; abs ((((_a - _b) mod 360) + 540) mod 360 - 180) };
private _hdg = getDir _air;
private _brg = _air getDir _tgt;
private _off = [_hdg, _brg] call _diff;
private _ok = _off <= 30;
private _txt = format ["IN FROM THE %1, cap %2°, cible à %3° de son nez", [_tgt getDir _air] call _card, round _hdg, round _off];
// Axe final d'attaque : fenêtre lue dans la saisie (fn_jtacFah).
private _fah = _b getOrDefault ["fah", ""];
private _win = [_fah] call comspec_atak_native_fnc_jtacFah;
if ((count _win) isEqualTo 2) then {
    _win params ["_lo", "_hi"];
    private _center = _lo + ((((_hi - _lo) + 360) mod 360) / 2);
    private _half = ((((_hi - _lo) + 360) mod 360) / 2) max 1;
    private _inFah = ([_hdg, _center] call _diff) <= _half;
    _ok = _ok && _inFah;
    _txt = _txt + " · " + (["FAH HORS AXE", "FAH respecté"] select _inFah);
};
[_txt, _ok]
