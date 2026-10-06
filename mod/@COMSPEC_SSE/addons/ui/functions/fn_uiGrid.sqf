/*
    Grille UI SSE côté SQF — mêmes valeurs que ui_macros.hpp.
    [] call comspec_sse_fnc_uiGrid → [gridX, gridY, caseW, caseH]
    (40 x 25 cases centrées dans la safeZone, largeur bornée à 1.2)
*/
private _wAbs = (safezoneW / safezoneH) min 1.2;
private _hAbs = _wAbs / 1.2;
[
    safezoneX + (safezoneW - _wAbs) / 2,
    safezoneY + (safezoneH - _hAbs) / 2,
    _wAbs / 40,
    _hAbs / 25
]
