params [["_target", objNull], ["_surfaceIndex", 0]];

if (isNull _target) exitWith {};

private _streams = _target getVariable ["Iceman_TOC_streamsGlobal", []];
_streams = _streams select {(_x param [0, -1]) != _surfaceIndex};
_target setVariable ["Iceman_TOC_streamsGlobal", _streams, true];

private _jipPairs = _target getVariable ["Iceman_TOC_streamJipIds", []];
private _keptPairs = [];

{
    _x params [["_slot", -1], ["_jipId", ""]];
    if (_slot == _surfaceIndex) then {
        if (_jipId isNotEqualTo "" && {!isNil "CBA_fnc_removeGlobalEventJIP"}) then {
            [_jipId] call CBA_fnc_removeGlobalEventJIP;
        };
    } else {
        _keptPairs pushBack _x;
    };
} forEach _jipPairs;

_target setVariable ["Iceman_TOC_streamJipIds", _keptPairs, true];

if (_streams isEqualTo []) then {
    _target setVariable ["Iceman_TOC_feed", nil, true];
    _target setVariable ["Iceman_TOC_settings", nil, true];
} else {
    private _last = _streams # ((count _streams) - 1);
    _target setVariable ["Iceman_TOC_feed", _last param [1, []], true];
    _target setVariable ["Iceman_TOC_settings", _last param [2, []], true];
};

["Iceman_TOC_stop", [_target, _surfaceIndex]] call CBA_fnc_globalEvent;
