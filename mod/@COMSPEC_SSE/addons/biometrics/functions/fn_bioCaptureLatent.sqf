/*
    Relevé d'empreintes latentes sur un objet (poudre et ruban adhésif).

    Une latente est partielle : 1 à 3 doigts, NFIQ 3 à 5, dégradée par la pluie et
    les manipulations. Un objet peut ne rien porter d'exploitable.

    [_object, _operator] call comspec_sse_fnc_bioCaptureLatent -> HashMap
      ok, blocked, ownerKey, ownerUnit, fingers [indices], nfiqs [10], label, attempt
*/
params [["_obj", objNull, [objNull]], ["_operator", objNull, [objNull]]];

private _out = createHashMapFromArray [["ok", false], ["blocked", ""], ["fingers", []], ["nfiqs", [0,0,0,0,0,0,0,0,0,0]], ["ownerKey", ""], ["ownerUnit", objNull]];
if (isNull _obj) exitWith { _out set ["blocked", "Aucun objet."]; _out };
if !(missionNamespace getVariable ["comspec_sse_bio_latents", true]) exitWith { _out set ["blocked", "Empreintes latentes désactivées pour cette mission."]; _out };

private _n = (_obj getVariable ["comspec_sse_bioLatentTries", 0]) + 1;
_obj setVariable ["comspec_sse_bioLatentTries", _n, true];
_out set ["attempt", _n];

private _label = getText ((configOf _obj) >> "displayName");
if (_obj isKindOf "WeaponHolder" || {_obj isKindOf "WeaponHolderSimulated"} || {_obj isKindOf "GroundWeaponHolder"}) then {
    private _w = (weaponCargo _obj) param [0, ""];
    if (_w isNotEqualTo "") then { _label = getText (configFile >> "CfgWeapons" >> _w >> "displayName"); };
};
if (_label isEqualTo "") then { _label = typeOf _obj; };
_out set ["label", _label];

private _owners = [_obj] call comspec_sse_fnc_bioLatentOwners;
private _okey = format ["OBJ-%1", netId _obj];
private _r = { params ["_s"]; [_okey, format ["lat|%1|%2", _n, _s]] call comspec_sse_fnc_bioRand };

if (_owners isEqualTo [] || {(["none"] call _r) < 0.2}) exitWith {
    _out set ["blocked", "Aucune trace exploitable sur cet objet (surface essuyée ou trop manipulée)."];
    _out
};

(_owners select (floor ((["who"] call _r) * (count _owners)))) params ["_key", "_unit"];
private _profile = [_unit, _key] call comspec_sse_fnc_bioProfile;
private _fingers = _profile getOrDefault ["fingers", []];

// Doigts qui laissent le plus de traces en tenant un objet : pouce, index, majeur.
private _pool = [0, 1, 2, 5, 6, 7] select { ((_fingers param [_x, ["", "", "normal"]]) select 2) isNotEqualTo "ampute" };
private _count = 1 + floor ((["cnt"] call _r) * 3);
private _picked = [];
for "_i" from 0 to 5 do {
    if ((count _picked) < _count && {_pool isNotEqualTo []}) then {
        private _f = _pool select (floor ((["p" + str _i] call _r) * (count _pool)));
        _picked pushBackUnique _f;
    };
};
private _wet = rain > 0.2 && {(count (lineIntersectsSurfaces [getPosASL _obj, (getPosASL _obj) vectorAdd [0, 0, 12], _obj, objNull, true, 1])) == 0};
private _nfiqs = [0,0,0,0,0,0,0,0,0,0];
{
    private _q = 58 + ((["q" + str _x] call _r) * 22) - ([0, 15] select _wet);
    _nfiqs set [_x, switch (true) do { case (_q >= 72): { 3 }; case (_q >= 58): { 4 }; default { 5 }; }];
} forEach _picked;
if (({ _x >= 1 && {_x <= 4} } count _nfiqs) == 0) exitWith {
    _out set ["blocked", "Traces trop partielles pour une comparaison (NFIQ 5)."];
    _out
};

_out set ["ok", true];
_out set ["ownerKey", _key];
_out set ["ownerUnit", _unit];
_out set ["fingers", _picked];
_out set ["nfiqs", _nfiqs];
_out
