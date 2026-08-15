params [["_target", objNull], ["_feed", []], ["_settings", []]];

if (isNull _target || {_feed isEqualTo []}) exitWith {};

_settings = [_settings] call Iceman_fnc_toc_normalizeSettings;
private _surfaceIndex = _settings param [9, 0];

private _streams = _target getVariable ["Iceman_TOC_streamsGlobal", []];
_streams = _streams select {(_x param [0, -1]) != _surfaceIndex};
_streams pushBack [_surfaceIndex, _feed, _settings];
_target setVariable ["Iceman_TOC_streamsGlobal", _streams, true];
_target setVariable ["Iceman_TOC_feed", _feed, true];
_target setVariable ["Iceman_TOC_settings", _settings, true];

private _vision = _settings param [10, "normal"];
private _visionPairs = _target getVariable ["Iceman_TOC_visionModes", []];
_visionPairs = _visionPairs select {(_x param [0, -1]) != _surfaceIndex};
_visionPairs pushBack [_surfaceIndex, _vision];
_target setVariable ["Iceman_TOC_visionModes", _visionPairs, true];

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

private _newJipId = "";
if (!isNil "CBA_fnc_globalEventJIP") then {
    _newJipId = ["Iceman_TOC_stream", [_target, _feed, _settings], _target] call CBA_fnc_globalEventJIP;
} else {
    ["Iceman_TOC_stream", [_target, _feed, _settings]] call CBA_fnc_globalEvent;
};

if (_newJipId isNotEqualTo "") then {
    _keptPairs pushBack [_surfaceIndex, _newJipId];
};

_target setVariable ["Iceman_TOC_streamJipIds", _keptPairs, true];
