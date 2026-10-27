/*
    Définit l'identité SSE d'une entité.
    [_entity, [["name","Karim Haddad"],["alias","ABU HAMZA"]]] call comspec_sse_fnc_setIdentity
*/
params [
    ["_entity", objNull, [objNull]],
    ["_pairs", [], [[]]],
    ["_public", true, [true]]
];

if (isNull _entity) exitWith { false };

private _identity = [_entity, "identity"] call comspec_sse_fnc_getSection;
if (isNil "_identity" || {!(_identity isEqualType createHashMap)}) then {
    _identity = createHashMap;
};

{
    _x params ["_k", "_v"];
    _identity set [toLower _k, _v];
} forEach _pairs;

// Écritures locales puis un seul commit public en fin (une seule diffusion de la fiche).
[_entity, "identity", _identity, false] call comspec_sse_fnc_setSection;

private _authoredKeys = [];
{ _authoredKeys pushBack (toLower (_x select 0)); } forEach _pairs;
if (
    ("name" in _authoredKeys)
    || {"first_name" in _authoredKeys}
    || {"last_name" in _authoredKeys}
) then {
    _entity setVariable ["COMSPEC_SSE_NameAuthored", true, _public];
};

if (!isNil "comspec_sse_fnc_syncIdentityBridgeVars") then {
    [_entity, _public, false] call comspec_sse_fnc_syncIdentityBridgeVars;
};

private _status = [_entity, "sectionStatus"] call comspec_sse_fnc_getSection;
if (!isNil "_status" && {_status isEqualType createHashMap}) then {
    _status set ["identity", "complete"];
    [_entity, "sectionStatus", _status, false] call comspec_sse_fnc_setSection;
};

if (_public) then {
    private _data = [_entity] call comspec_sse_fnc_getData;
    if (!isNil "_data" && {_data isEqualType []}) then {
        [_entity, _data, true] call comspec_sse_fnc_setData;
    };
};

// Ne pas sync dogtag pendant une génération en cours (réentrée ACE).
if (
    !isNil "comspec_sse_fnc_aceDogtagSync"
    && {!(_entity getVariable ["comspec_sse_generating", false])}
) then {
    if (!isNil "CBA_fnc_waitAndExecute") then {
        [{
            params ["_e"];
            if (isNull _e) exitWith {};
            if (_e getVariable ["comspec_sse_generating", false]) exitWith {};
            [_e] call comspec_sse_fnc_aceDogtagSync;
        }, [_entity], 0.15] call CBA_fnc_waitAndExecute;
    } else {
        [_entity] call comspec_sse_fnc_aceDogtagSync;
    };
};

true
