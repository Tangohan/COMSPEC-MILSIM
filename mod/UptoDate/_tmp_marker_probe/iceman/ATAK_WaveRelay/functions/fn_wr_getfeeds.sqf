private _feeds = [];

if !(isNil "Iceman_fnc_toc_getFeeds") then {
    _feeds = call Iceman_fnc_toc_getFeeds;
} else {
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
            _feeds pushBack [format ["Helmet - %1", name _x], "helmet", str _x, -1];
        };
    } forEach _helmetList;

    private _vehicles = vehicles select {
        alive _x &&
        {
            (_x isKindOf "UAV") ||
            {_x isKindOf "Plane"} ||
            {_x isKindOf "Helicopter"}
        }
    };

    {
        private _vehicle = _x;
        private _cfg = configFile >> "CfgVehicles" >> typeOf _vehicle;
        private _name = getText (_cfg >> "displayName");
        if (_name isEqualTo "") then {_name = typeOf _vehicle};

        _feeds pushBack [format ["Camera - %1", _name], "vehicle", str _vehicle, 0];

        private _gunnerPos = getText (_cfg >> "uavCameraGunnerPos");
        private _gunnerDir = getText (_cfg >> "uavCameraGunnerDir");
        if (_gunnerPos != "" && {_gunnerDir != ""}) then {
            _feeds pushBack [format ["TGP - %1", _name], "vehicle", str _vehicle, 1];
        };
    } forEach _vehicles;
};

private _deduped = [];
{
    private _feed = +_x;
    if ((count _feed) >= 4) then {
        private _id = format ["%1:%2:%3", _feed # 1, _feed # 2, _feed # 3];
        if ((_deduped findIf {format ["%1:%2:%3", _x # 1, _x # 2, _x # 3] == _id}) < 0) then {
            _deduped pushBack _feed;
        };
    };
} forEach _feeds;

(call Iceman_fnc_wr_getState) set ["lastFeeds", _deduped];
_deduped
