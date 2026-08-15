params [["_target", objNull]];

private _screens = missionNamespace getVariable ["Iceman_TOC_activeScreensLocal", []];
_screens = _screens select {
    !isNull _x && {_x != _target} && {!((_x getVariable ["Iceman_TOC_streamsLocal", []]) isEqualTo [])}
};

missionNamespace setVariable ["Iceman_TOC_activeScreensLocal", _screens];
