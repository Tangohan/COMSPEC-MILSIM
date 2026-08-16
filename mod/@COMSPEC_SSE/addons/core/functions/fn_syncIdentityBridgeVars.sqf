/*
    Miroir identité SSE → variables Eden / terminal SEEK (Overwatch).
    [_entity] call comspec_sse_fnc_syncIdentityBridgeVars

    Sans ce pont, le dialog SEEK ne voit que COMSPEC_SSE_* ou name _unit,
    et remonte le nom généré en « alias » Athena (nom/prénom vides, FALCON perdu).
*/
params [
    ["_entity", objNull, [objNull]],
    ["_public", true, [true]]
];

if (isNull _entity) exitWith { false };

private _identity = [_entity, "identity"] call comspec_sse_fnc_getSection;
if (isNil "_identity" || {!(_identity isEqualType createHashMap)}) exitWith { false };

private _name = _identity getOrDefault ["name", ""];
private _alias = _identity getOrDefault ["alias", ""];
private _first = _identity getOrDefault ["first_name", ""];
private _last = _identity getOrDefault ["last_name", ""];

if (_first isEqualTo "" && {_last isEqualTo ""} && {_name isNotEqualTo ""}) then {
    private _parts = _name splitString " ";
    if ((count _parts) > 1) then {
        _first = _parts select 0;
        _last = (_parts select [1, (count _parts) - 1]) joinString " ";
    } else {
        _first = _name;
        _last = "";
    };
};

_entity setVariable ["COMSPEC_SSE_FirstName", _first, _public];
_entity setVariable ["COMSPEC_SSE_LastName", _last, _public];
_entity setVariable ["COMSPEC_SSE_Alias", _alias, _public];
_entity setVariable ["COMSPEC_SSE_Nationality", _identity getOrDefault ["nationality", ""], _public];
_entity setVariable ["COMSPEC_SSE_Language", _identity getOrDefault ["language", ""], _public];

true
