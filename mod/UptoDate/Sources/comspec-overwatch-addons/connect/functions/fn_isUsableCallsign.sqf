/*
    True si la chaîne peut servir d’indicatif opérateur (Effectifs),
    jamais le nom de communauté ni une adresse web.
*/
params [["_cs", "", [""]]];

if (!(_cs isEqualType "")) then { _cs = str _cs; };
_cs = trim _cs;
if (_cs isEqualTo "" || {_cs isEqualTo "-"}) exitWith { false };

private _low = toLower _cs;
if (_low in ["unknown", "inconnu", "operateur", "operator", "none", "n/a"]) exitWith { false };
if ((_low find "http://") == 0 || {(_low find "https://") == 0} || {(_cs find "://") >= 0}) exitWith { false };
if ((count _cs) > 40) exitWith { false };

private _fncSameOrTruncated = {
    params ["_a", "_b"];
    if (_a isEqualTo "" || {_b isEqualTo ""}) exitWith { false };
    if (_a isEqualTo _b) exitWith { true };
    if ((count _a) >= 16 && {(count _b) >= 16} && {((_a find _b) == 0) || {(_b find _a) == 0}}) exitWith { true };
    false
};

private _tenant = toLower (trim (str (missionNamespace getVariable ["comspec_tenant_name", ""])));
if ([_low, _tenant] call _fncSameOrTruncated) exitWith { false };

private _unit = toLower (trim (str (missionNamespace getVariable ["comspec_profile_unit", ""])));
if (_unit isNotEqualTo "" && {_low isEqualTo _unit}) exitWith { false };

private _pname = toLower (trim (str (missionNamespace getVariable ["comspec_profile_name", ""])));
if (_pname isNotEqualTo "" && {_low isEqualTo _pname}) exitWith { false };

true
