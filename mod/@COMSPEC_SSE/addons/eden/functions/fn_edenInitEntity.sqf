/*
    Init Eden/runtime d'une entité marquée SSE.
    CBA Extended_InitPost passe [_unit] dans _this — ne pas rewraper.

    La génération est différée / étalée : InitPost synchrone + generateData +
    aceDogtagSync sur un peloton d’unités empile la pile SQF avec ACE Medical
    (« Was unit a player? ») → C00000FD STACK_OVERFLOW / tbb4malloc.
*/
params [
    ["_entity", objNull, [objNull, []]]
];

if (_entity isEqualType []) then {
    _entity = _entity param [0, objNull, [objNull]];
};

if (isNull _entity) exitWith {};
if (!isServer) exitWith {};

if !(_entity getVariable ["comspec_sse_enabled", false]
    || {_entity getVariable ["comspec_sse_domex_enabled", false]}
) exitWith {};

// Déjà planifié ou en cours → ne pas re-empiler.
if (_entity getVariable ["comspec_sse_edenInitQueued", false]) exitWith {};
if (_entity getVariable ["comspec_sse_generating", false]) exitWith {};
_entity setVariable ["comspec_sse_edenInitQueued", true];

private _nid = netId _entity;
private _tail = if ((count _nid) >= 2) then { _nid select [(count _nid) - 2] } else { _nid };
private _stagger = ((parseNumber _tail) max 0) mod 25;
private _delay = 0.05 + (_stagger * 0.04);

[{
    params ["_e"];
    if (isNull _e) exitWith {};
    _e setVariable ["comspec_sse_edenInitQueued", false];
    if !(_e getVariable ["comspec_sse_enabled", false]
        || {_e getVariable ["comspec_sse_domex_enabled", false]}
    ) exitWith {};
    [_e] call comspec_sse_fnc_edenApplyAttributes;
}, [_entity], _delay] call CBA_fnc_waitAndExecute;
