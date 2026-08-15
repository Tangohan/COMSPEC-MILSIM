params [["_feed", []]];

if !(_feed isEqualType [] && {(count _feed) >= 4}) exitWith {objNull};

private _type = _feed # 1;
private _data = _feed # 2;
private _obj = objNull;

if (_data isEqualType objNull) exitWith {_data};
if !(_data isEqualType "") exitWith {objNull};

switch (_type) do {
    case "helmet": {
        {
            if (str _x == _data) exitWith {_obj = _x};
        } forEach allPlayers;
        if (isNull _obj) then {
            {
                if (str _x == _data) exitWith {_obj = _x};
            } forEach allUnits;
        };
    };
    case "vehicle": {
        {
            if (str _x == _data) exitWith {_obj = _x};
        } forEach vehicles;
    };
};

_obj
