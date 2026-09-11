/*
    Liste paginée des tenues communauté (évite la troncature 8 Ko de ListWardrobes).
    Retour : tableau de lignes brutes "id\tname\t…" (sans en-tête OK|).
*/
private _lines = [];
private _start = 0;
private _guard = 0;

while {_guard < 64} do {
    _guard = _guard + 1;
    private _raw = ["COMSPECExtension" callExtension ["ListWardrobes", [str _start]]] call comspec_overwatch_connect_fnc_extResult;
    if (!(_raw isEqualType "") || {_raw find "OK|" != 0}) exitWith {};
    private _body = _raw select [3];
    private _nl = _body find toString [10];
    if (_nl < 0) then { _nl = _body find toString [13]; };
    if (_nl < 0) exitWith {};
    private _head = _body select [0, _nl];
    private _rest = _body select [_nl + 1];
    // Retirer éventuel CR restant
    if ((_rest select [0, 1]) isEqualTo toString [10]) then {
        _rest = _rest select [1];
    };
    if ((_head select [0, 4]) isEqualTo "NEXT") then {
        private _hp = _head splitString toString [9];
        if ((count _hp) < 2) exitWith {};
        private _next = parseNumber (_hp select 1);
        private _pageLines = if (_rest isEqualTo "") then { [] } else { _rest splitString endl };
        { if (_x isNotEqualTo "") then { _lines pushBack _x }; } forEach _pageLines;
        if (_next <= _start) exitWith {};
        _start = _next;
    } else {
        // END\n… ou END seul
        private _pageLines = if (_rest isEqualTo "") then {
            if ((_head find "END") == 0) then { [] } else { [_head] }
        } else {
            _rest splitString endl
        };
        { if (_x isNotEqualTo "") then { _lines pushBack _x }; } forEach _pageLines;
        break;
    };
};

_lines
