params [["_target", objNull]];

if (isNull _target) exitWith {};

private _screens = missionNamespace getVariable ["Iceman_TOC_activeScreensLocal", []];
_screens = _screens select {
    !isNull _x && {!((_x getVariable ["Iceman_TOC_streamsLocal", []]) isEqualTo [])}
};

if !(_target in _screens) then {
    _screens pushBack _target;
};

missionNamespace setVariable ["Iceman_TOC_activeScreensLocal", _screens];
