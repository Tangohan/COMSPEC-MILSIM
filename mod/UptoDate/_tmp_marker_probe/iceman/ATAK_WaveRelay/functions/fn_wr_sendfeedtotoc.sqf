params [["_feed", []]];

if (isNil "Iceman_fnc_toc_startStream" || {isNil "Iceman_fnc_toc_syncStreamGlobal"} || {isNil "Iceman_fnc_toc_isScreenCandidate"} || {isNil "Iceman_fnc_toc_getSettings"}) exitWith {
    ["WAVE RELAY", "TOC screen system is not loaded.", 2] call cTab_fnc_addNotification;
    false
};

private _obj = [_feed] call Iceman_fnc_wr_feedObject;
if (isNull _obj || {!alive _obj}) exitWith {
    ["WAVE RELAY", "Feed source is not available.", 2] call cTab_fnc_addNotification;
    false
};

private _target = objNull;
private _cursor = cursorObject;
if (!isNull _cursor && {[_cursor] call Iceman_fnc_toc_isScreenCandidate}) then {
    _target = _cursor;
};

if (isNull _target) then {
    private _candidates = nearestObjects [player, ["All"], 8] select {
        !isNull _x && {[_x] call Iceman_fnc_toc_isScreenCandidate}
    };
    if !(_candidates isEqualTo []) then {
        _target = _candidates # 0;
    };
};

if (isNull _target) exitWith {
    ["WAVE RELAY", "Look at or stand near a TOC screen first.", 3] call cTab_fnc_addNotification;
    false
};

private _settings = [_target] call Iceman_fnc_toc_getSettings;
[_target, _feed, _settings] call Iceman_fnc_toc_startStream;
[_target, _feed, _settings] call Iceman_fnc_toc_syncStreamGlobal;

["WAVE RELAY", format ["Sent %1 to TOC screen.", _feed # 0], 3] call cTab_fnc_addNotification;
true
