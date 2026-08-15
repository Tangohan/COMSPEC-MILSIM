#include "..\script_component.hpp"

params ["_pos"];

if !(_pos isEqualType []) exitWith {"0000-0000"};
if ((count _pos) < 2) exitWith {"0000-0000"};

private _pad5 = {
    params ["_value"];
    private _text = str ((0 max floor _value) min 99999);
    while {count _text < 5} do {
        _text = "0" + _text;
    };
    _text
};

format ["%1%2", [_pos # 0] call _pad5, [_pos # 1] call _pad5]
