/*
    Zones interdites au drone, partagées par le camp (variable publique COMSPEC_ATAK_NoFly :
    [[id, [x, y], rayon, auteur, camp]...]). Posées d'un clic droit sur la carte, listées et retirées dans l'app
    MARQUER TRACKING, dessinées sur la carte (cercle rouge hachuré).
      ["list"]               → zones du camp du joueur : [[id, [x, y], rayon, auteur]...] ;
      ["add", [pos, rayon]]  pose une zone (rayon 50 à 2000 m) ;
      ["remove", id]         la retire pour tout le camp ;
      ["clear"]              retire toutes les zones du camp du joueur ;
      ["inside", pos]        → zone du camp qui contient la position ([] sinon) ;
      ["exit", pos]          → point hors de toutes les zones, au plus près, en sortant par le bord le plus proche ;
      ["draw", carte]        dessin des zones (fn_mapOnDraw).
*/
params [["_act", "list"], ["_arg", []]];
private _all = missionNamespace getVariable ["COMSPEC_ATAK_NoFly", []];
if !(_all isEqualType []) then { _all = []; };
private _mine = _all select { (_x param [4, sideUnknown]) isEqualTo (side group player) };
switch (_act) do {
    case "list": { _mine apply { _x select [0, 4] } };
    case "add": {
        _arg params [["_p", []], ["_r", 200]];
        if ((count _p) < 2) exitWith { "" };
        _r = ((round _r) max 50) min 2000;
        private _id = format ["NF%1%2", round (diag_tickTime * 10) mod 100000, floor random 1000];
        _all pushBack [_id, [_p select 0, _p select 1], _r, [player] call comspec_atak_native_fnc_unitCallsign, side group player];
        missionNamespace setVariable ["COMSPEC_ATAK_NoFly", _all, true];
        ["SUCCESS", format ["Zone interdite au drone posée en %1 (rayon %2 m)", [_p, 8] call comspec_atak_native_fnc_gridRef, _r], 3, 20] call comspec_atak_native_fnc_notify;
        _id
    };
    case "remove": {
        missionNamespace setVariable ["COMSPEC_ATAK_NoFly", _all select { (_x select 0) isNotEqualTo _arg }, true];
        ["INFO", "Zone interdite retirée", 3, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "clear": {
        private _n = count _mine;
        if (_n isEqualTo 0) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_NoFly", _all select { (_x param [4, sideUnknown]) isNotEqualTo (side group player) }, true];
        ["INFO", format ["%1 zones interdites retirées", _n], 3, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "inside": {
        private _i = _mine findIf { ([_arg select 0, _arg select 1] distance2D (_x select 1)) < (_x select 2) };
        if (_i < 0) then { [] } else { _mine select _i }
    };
    case "exit": {
        private _p = [_arg select 0, _arg select 1];
        // Quelques passes : sortir d'une zone peut faire entrer dans une zone voisine.
        for "_n" from 1 to 4 do {
            private _i = _mine findIf { (_p distance2D (_x select 1)) < (_x select 2) };
            if (_i < 0) exitWith {};
            (_mine select _i) params ["", "_c", "_r"];
            private _dir = (_c getDir _p);
            if ((_p distance2D _c) < 1) then { _dir = random 360; };
            _p = [(_c select 0) + (_r + 40) * sin _dir, (_c select 1) + (_r + 40) * cos _dir];
        };
        _p
    };
    case "draw": {
        if (isNull _arg) exitWith {};
        {
            _x params ["", "_c", "_r"];
            _arg drawEllipse [_c, _r, _r, 0, [0.9, 0.2, 0.18, 0.9], ""];
            _arg drawEllipse [_c, _r, _r, 0, [0.9, 0.2, 0.18, 0.35], "\A3\ui_f\data\map\markerbrushes\bdiagonal_ca.paa"];
            _arg drawIcon ["#(argb,8,8,3)color(0,0,0,0)", [0.95, 0.3, 0.25, 1], _c, 0, 0, 0, "ZONE INTERDITE DRONE", 2, 0.03, "RobotoCondensedBold", "center"];
        } forEach _mine;
    };
};
