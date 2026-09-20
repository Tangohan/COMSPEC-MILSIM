/*
    Tiroir : Relais AT (AtakRelay) en tête, pas de doublon Wave Relay IceMan.
*/
params [["_apps", [], [[]]]];

if (!(_apps isEqualType [])) exitWith { [] };

_apps = _apps select { _x isNotEqualTo "WaveRelay" };

if (!("AtakRelay" in _apps) && {isClass (configFile >> "ATAK_APPs" >> "AtakRelay")}) then {
    private _i = _apps find "AtakP2P";
    if (_i < 0) then { _i = _apps find "message"; };
    if (_i < 0) then {
        _apps pushBack "AtakRelay";
    } else {
        private _head = _apps select [0, _i + 1];
        private _tail = _apps select [_i + 1, (count _apps) - (_i + 1)];
        _apps = _head + ["AtakRelay"] + _tail;
    };
};

_apps
