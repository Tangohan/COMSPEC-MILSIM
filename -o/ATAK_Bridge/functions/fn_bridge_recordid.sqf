params [["_record", []]];

if !(_record isEqualType [] && {(count _record) >= 2}) exitWith {""};
format ["%1:%2", _record # 0, _record # 1]
