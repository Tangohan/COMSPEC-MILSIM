private _feeds = [];

private _addFeed = {
    private _feed = +_this;
    if ((count _feed) < 4) exitWith {};

    private _id = format ["%1:%2:%3", _feed # 1, _feed # 2, _feed # 3];
    if ((_feeds findIf {format ["%1:%2:%3", _x # 1, _x # 2, _x # 3] == _id}) == -1) then {
        _feeds pushBack _feed;
    };
};

private _helmetList = missionNamespace getVariable ["cTabHcamlist", []];
if (_helmetList isEqualTo []) then {
    _helmetList = allPlayers select {
        alive _x &&
        {
            ("ItemcTabHCam" in (items _x)) ||
            {((headgear _x) in (missionNamespace getVariable ["cTab_helmetClass_has_HCam", []]))}
        }
    };
};

{
    if (!isNull _x && {alive _x}) then {
        [format ["Helmet - %1", name _x], "helmet", str _x, -1] call _addFeed;
    };
} forEach _helmetList;

private _uavList = missionNamespace getVariable ["cTabUAVlist", []];
private _airVehicles = vehicles select {
    alive _x &&
    {
        (_x isKindOf "UAV") ||
        {_x isKindOf "Plane"} ||
        {_x isKindOf "Helicopter"}
    }
};

private _vehicles = [];
{
    if (!isNull _x && {!(_x in _vehicles)}) then {
        _vehicles pushBack _x;
    };
} forEach (_uavList + _airVehicles);

{
    private _vehicle = _x;
    private _cfg = configFile >> "CfgVehicles" >> typeOf _vehicle;
    private _name = getText (_cfg >> "displayName");
    if (_name isEqualTo "") then {
        _name = typeOf _vehicle;
    };

    private _driverPos = getText (_cfg >> "uavCameraDriverPos");
    private _driverDir = getText (_cfg >> "uavCameraDriverDir");
    private _gunnerPos = getText (_cfg >> "uavCameraGunnerPos");
    private _gunnerDir = getText (_cfg >> "uavCameraGunnerDir");

    if ((_driverPos != "" && {_driverDir != ""}) || {_vehicle isKindOf "UAV"} || {_vehicle isKindOf "Plane"} || {_vehicle isKindOf "Helicopter"}) then {
        [format ["Camera - %1", _name], "vehicle", str _vehicle, 0] call _addFeed;
    };

    if (_gunnerPos != "" && {_gunnerDir != ""}) then {
        [format ["TGP - %1", _name], "vehicle", str _vehicle, 1] call _addFeed;
    };
} forEach _vehicles;

_feeds
