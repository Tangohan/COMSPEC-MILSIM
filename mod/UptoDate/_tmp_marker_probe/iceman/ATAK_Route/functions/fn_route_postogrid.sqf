#include "..\script_component.hpp"

params ["_pos"];

if !(_pos isEqualType []) exitWith {"not set"};
if ((count _pos) < 2) exitWith {"not set"};

private _pad5 = {
    params ["_value"];
    private _text = str ((0 max floor _value) min 99999);
    while {count _text < 5} do {
        _text = "0" + _text;
    };
    _text
};

format ["%1%2", [_pos # 0] call _pad5, [_pos # 1] call _pad5]
