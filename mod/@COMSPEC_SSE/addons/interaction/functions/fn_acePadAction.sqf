/*
    ACE createAction renvoie _this (les arguments tels quels).
    Sans le 11e argument (modificateur, index 10), collectActiveActionTree plante.
    [_action] call comspec_sse_fnc_acePadAction
*/
params [
    ["_act", [], [[]]]
];

if (!(_act isEqualType []) || {(count _act) < 5} || {!((_act select 0) isEqualType "")}) exitWith { [] };
_act = +_act;
private _n = count _act;
if (_n < 6) then { _act pushBack { [] }; _n = _n + 1; };
if (_n < 7) then { _act pushBack []; _n = _n + 1; };
if (_n < 8) then { _act pushBack {[0, 0, 0]}; _n = _n + 1; };
if (_n < 9) then { _act pushBack 2; _n = _n + 1; };
if (_n < 10) then { _act pushBack [false, false, false, false, true]; _n = _n + 1; };
if (_n < 11) then { _act pushBack {}; };
if (!((_act select 5) isEqualType {})) then { _act set [5, { [] }]; };
if (!((_act select 6) isEqualType [])) then { _act set [6, []]; };
if (!((_act select 9) isEqualType []) || {count (_act select 9) < 5}) then {
    _act set [9, [false, false, false, false, true]];
};
if (!((_act select 10) isEqualType {})) then { _act set [10, {}]; };
_act
