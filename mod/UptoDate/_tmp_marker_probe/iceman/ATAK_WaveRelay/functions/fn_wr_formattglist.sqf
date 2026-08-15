params [["_list", []]];

private _clean = [];
{
    private _tg = round _x;
    if (_tg >= 1 && {_tg <= 16}) then {
        _clean pushBackUnique _tg;
    };
} forEach _list;

_clean sort true;

if (_clean isEqualTo []) exitWith {"None"};
(_clean apply {format ["TG-%1", _x]}) joinString ", "
