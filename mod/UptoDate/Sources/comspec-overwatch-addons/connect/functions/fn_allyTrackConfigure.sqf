/*
    Dialogue Zeus : activer le suivi ATAK d’une IA et choisir son indicatif.
    Params: [_unit, _delay]
*/
params [
    ["_unit", objNull, [objNull]],
    ["_delay", 0],
    ["_retried", false]
];

if (!hasInterface) exitWith { false };
if (isNull _unit || {!(_unit isKindOf "CAManBase")} || {isPlayer _unit}) exitWith {
    ["Sélectionnez une IA.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

if (_delay > 0 && {!isNil "CBA_fnc_waitAndExecute"}) exitWith {
    [
        { [_this, 0] call comspec_overwatch_connect_fnc_allyTrackConfigure },
        _unit,
        _delay
    ] call CBA_fnc_waitAndExecute;
    true
};

private _currentlyOn = [_unit, "COMSPEC_AllyTrack"] call comspec_overwatch_connect_fnc_isObjectFlag;
private _who = name _unit;
private _cs = _unit getVariable ["COMSPEC_AllyCallsign", ""];
if (!(_cs isEqualType "")) then { _cs = "" };
_cs = trim _cs;
if (_cs isEqualTo "") then {
    _cs = [_unit] call comspec_overwatch_connect_fnc_allyTrackCallsign;
};

private _apply = {
    params ["_unit", "_enabled", "_callsign", ["_scope", "leader"]];
    if (isNull _unit) exitWith {};
    private _targets = [_unit];
    if (_enabled) then {
        private _grp = group _unit;
        private _mates = (units _grp) select {
            alive _x && {!isPlayer _x} && {_x isKindOf "CAManBase"}
        };
        if (_scope isEqualTo "group" && {(count _mates) > 1}) then {
            _targets = _mates;
        };
        if (_scope isEqualTo "leader" && {(count _mates) > 1}) then {
            private _sl = leader _grp;
            if (!isNull _sl && {!isPlayer _sl} && {alive _sl} && {_sl isKindOf "CAManBase"}) then {
                _targets = [_sl];
            } else {
                _targets = [_unit];
            };
        };
    };
    {
        [_x, _enabled, _callsign] remoteExecCall ["comspec_overwatch_connect_fnc_setAllyTrack", 0];
        if (_enabled) then {
            _x setVariable ["COMSPEC_AllyTrackLastAt", -1e9, false];
            [_x] call comspec_overwatch_connect_fnc_reportAllyPosition;
        };
    } forEach _targets;
    if (_enabled) then {
        private _shown = [_targets select 0] call comspec_overwatch_connect_fnc_allyTrackCallsign;
        if ((count _targets) > 1) then {
            [format ["%1 unités de la section apparaissent sur l’ATAK (%2).", count _targets, _shown], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        } else {
            [format ["%1 apparaît sur l’ATAK (%2).", name (_targets select 0), _shown], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
        };
    } else {
        [format ["Suivi ATAK retiré pour %1.", name _unit], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    };
};

if (isNil "zen_dialog_fnc_create") exitWith {
    [_unit, !_currentlyOn, _cs, "leader"] call _apply;
    true
};

private _grp = group _unit;
private _mateCount = count ((units _grp) select { alive _x && {!isPlayer _x} && {_x isKindOf "CAManBase"} });
private _fields = [
    ["CHECKBOX", ["Visible sur l’ATAK", "L’IA apparaît comme une unité de terrain. Décochez pour retirer."], true],
    ["EDIT", ["Indicatif", "Nom court affiché sur la carte et dans les effectifs (ex. RAVEN). Vide : groupe et nom de l’IA."], _cs]
];
if (_mateCount > 1) then {
    _fields pushBack [
        "LIST",
        ["Section", "Cette IA fait partie d’un groupe. Choisissez le chef seulement, ou toute la section."],
        [["leader", "group"], ["Chef de section seulement", "Toute la section"], 0]
    ];
};

private _opened = [
    format ["IA alliée — %1", _who],
    _fields,
    {
        params ["_values", "_args"];
        _values params ["_enabled", "_callsign", ["_scope", "leader"]];
        _args params ["_unit", "_apply"];
        if (!(_scope isEqualType "")) then { _scope = "leader" };
        [_unit, _enabled, trim _callsign, _scope] call _apply;
    },
    {},
    [_unit, _apply]
] call zen_dialog_fnc_create;

if (_opened isEqualTo false) exitWith {
    if (!_retried && {!isNil "CBA_fnc_waitAndExecute"}) then {
        [
            { [_this, 0, true] call comspec_overwatch_connect_fnc_allyTrackConfigure },
            _unit,
            0.2
        ] call CBA_fnc_waitAndExecute;
    };
    true
};

true
