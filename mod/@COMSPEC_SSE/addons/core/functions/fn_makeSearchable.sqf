/*
    Marque un objet comme exploitable SSE et initialise un modèle léger (lazy).
    [_object, "PHONE", _profile, _complexity] call comspec_sse_fnc_makeSearchable
*/
params [
    ["_entity", objNull, [objNull]],
    ["_type", "OBJECT", [""]],
    ["_profile", "RANDOM", [""]],
    ["_complexity", "STANDARD", [""]]
];

if (isNull _entity) exitWith { false };
if (!isNil "comspec_sse_fnc_isHatchetVehicle" && {[_entity] call comspec_sse_fnc_isHatchetVehicle}) exitWith { false };

private _existing = [_entity] call comspec_sse_fnc_getData;
if (!isNil "_existing") exitWith {
    _entity setVariable ["comspec_sse_enabled", true, true];
    // Déjà SSE : ne pas re-pousser entityEnabled (sinon menus ACE dupliqués).
    if (
        hasInterface
        && {!isNil "CBA_fnc_localEvent"}
        && {!(_entity getVariable ["comspec_sse_aceMenusInstalled", false])}
    ) then {
        ["comspec_sse_entityEnabled", [_entity]] call CBA_fnc_localEvent;
    };
    true
};

// Graine déterministe (mission + classe + position) : même fiche sur toutes les machines.
private _seed = [_entity] call comspec_sse_fnc_entitySeed;

private _data = [_type, "SCRIPT", _profile, _complexity, _seed] call comspec_sse_fnc_createDataModel;
// lazyReady=false → contenu détaillé généré au premier examen
[_entity, _data, true] call comspec_sse_fnc_setData;
_entity setVariable ["comspec_sse_searchable", true, true];

[format ["makeSearchable %1 type=%2 seed=%3", _entity, _type, _seed]] call comspec_sse_fnc_log;
true
