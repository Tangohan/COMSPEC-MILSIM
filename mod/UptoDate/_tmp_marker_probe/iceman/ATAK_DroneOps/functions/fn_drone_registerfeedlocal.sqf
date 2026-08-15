params [["_drone", objNull]];

if (!([_drone] call Iceman_fnc_drone_isSupported)) exitWith {false};

if (isNil "cTabUAVlist") then {
    cTabUAVlist = [];
};

private _added = cTabUAVlist pushBackUnique _drone;
if (isNil "cTabActUav" || {isNull cTabActUav}) then {
    cTabActUav = _drone;
};

if (_added >= 0 && {!(isNil "cTabIfOpen")} && {!(isNil "cTab_fnc_updateInterface")}) then {
    [[["uavListUpdate", true]]] call cTab_fnc_updateInterface;
};

true
