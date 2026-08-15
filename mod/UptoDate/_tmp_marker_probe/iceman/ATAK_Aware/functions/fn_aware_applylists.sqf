if (!hasInterface) exitWith {false};
if (isNil "Iceman_fnc_aware_getMode") exitWith {false};

private _mode = call Iceman_fnc_aware_getMode;
if (_mode == "default") exitWith {false};

if (isNil "cTabBFTmembers" || {isNil "cTabBFTgroups"}) exitWith {false};

cTabBFTmembers = [];
cTabBFTgroups = [];

true
