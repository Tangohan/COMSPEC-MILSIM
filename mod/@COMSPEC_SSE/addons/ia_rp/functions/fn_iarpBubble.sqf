/*
    Bulles 3D du texte libre (client), propres au SSE : rien ne dépend de l'ATAK.
      "add"     [unité, texte, nom, genre] -> vrai si la bulle est vue tout de suite (portée et ligne de vue)
                                             genre say | me | do | todo | tryOk | tryKo | ai (couleur du réglage) ;
                                             une seule bulle par personne : la nouvelle remplace l'ancienne
      "typing"  [unité, vrai | faux, nom]    « … » animé tant que la personne écrit (8 s au plus sans nouvelle)
      "draw"    à chaque image (Draw3D), tant qu'il reste une bulle      "clear"  tout retirer
    Coût : aucun Draw3D sans bulle ; 8 bulles au plus, une par personne ; distance et ligne de vue recalculées 4 fois
    par seconde, jamais à chaque image. Aucune bulle au-delà de la portée (réglage « Portée des bulles ») ni hors vue.
    Texte : caractères de contrôle retirés, 6 lignes de 42 caractères au plus, coupées aux espaces (points de code).
    Durée : environ 14 caractères lus par seconde, entre 4 et 15 s. Aucun son.
    Bulle : [0 unité, 1 lignes, 2 nom, 3 couleur, 4 début, 5 fin, 6 vue, 7 opacité, 8 écrit]
*/
params [["_act", ""], ["_arg", []]];
#define IARP_MAX 8
#define IARP_TEXT 0.03
#define IARP_TAG 0.024
#define IARP_LINE 0.034
if (!hasInterface) exitWith { false };
private _range = missionNamespace getVariable ["comspec_sse_iarp_range", 20];
private _sees = {
    params ["_u", "_cam"];
    private _eye = eyePos _u;
    ((_cam vectorDistance _eye) <= _range) && { (lineIntersectsSurfaces [_cam, _eye, vehicle player, vehicle _u, true, 1, "VIEW", "FIRE"]) isEqualTo [] }
};
private _color = {
    params ["_kind"];
    private _c = switch (_kind) do {
        case "me": { missionNamespace getVariable ["comspec_sse_iarp_colMe", [0.8, 0.62, 1]] };
        case "do": { missionNamespace getVariable ["comspec_sse_iarp_colDo", [0.55, 0.8, 1]] };
        case "todo": { missionNamespace getVariable ["comspec_sse_iarp_colTodo", [1, 0.75, 0.35]] };
        case "tryOk": { [0.5, 0.95, 0.55] };
        case "tryKo": { [1, 0.45, 0.4] };
        case "ai": { missionNamespace getVariable ["comspec_sse_iarp_colAi", [0.62, 0.9, 1]] };
        case "typing": { [0.75, 0.78, 0.76] };
        default { missionNamespace getVariable ["comspec_sse_iarp_colSay", [0.97, 0.98, 0.95]] };
    };
    [_c param [0, 1], _c param [1, 1], _c param [2, 1], 1]
};
private _ensure = {
    if ((missionNamespace getVariable ["comspec_sse_iarpBubbleEh", -1]) < 0) then {
        missionNamespace setVariable ["comspec_sse_iarpBubbleLast", time];
        missionNamespace setVariable ["comspec_sse_iarpBubbleEh", addMissionEventHandler ["Draw3D", { ["draw"] call comspec_sse_fnc_iarpBubble; }]];
    };
};
private _put = {
    params ["_entry"];
    private _list = missionNamespace getVariable ["comspec_sse_iarpBubbles", []];
    private _same = _list findIf { (_x select 0) isEqualTo (_entry select 0) };
    if (_same >= 0) then { _list deleteAt _same; };
    if ((count _list) >= IARP_MAX) then {
        private _old = 0;
        { if ((_x select 4) < ((_list select _old) select 4)) then { _old = _forEachIndex; }; } forEach _list;
        _list deleteAt _old;
    };
    _list pushBack _entry;
    missionNamespace setVariable ["comspec_sse_iarpBubbles", _list];
    call _ensure;
};

switch (_act) do {
    case "add": {
        _arg params [["_u", objNull, [objNull]], ["_text", "", [""]], ["_tag", "", [""]], ["_kind", "say", [""]]];
        if (isNull _u || {!alive _u}) exitWith { false };
        private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
        if ((_cam vectorDistance (eyePos _u)) > _range) exitWith { false };
        private _all = (toArray _text) apply { [_x, 32] select (_x < 32 || {_x isEqualTo 127}) };
        private _words = (toString _all) splitString " ";
        if (_words isEqualTo []) exitWith { false };
        private _lines = [];
        private _cur = [];
        {
            private _w = toArray _x;
            // Didascalie « (Il hoche la tête.) » : sur sa propre ligne, jamais collée à la parole.
            if ((_w param [0, 0]) isEqualTo 40 && {_cur isNotEqualTo []}) then { _lines pushBack (toString _cur); _cur = []; };
            while { (count _w) > 42 } do {
                if (_cur isNotEqualTo []) then { _lines pushBack (toString _cur); _cur = []; };
                _lines pushBack (toString (_w select [0, 42]));
                _w = _w select [42];
            };
            if (_cur isEqualTo []) then { _cur = _w; } else {
                if (((count _cur) + 1 + (count _w)) <= 42) then { _cur = _cur + [32] + _w; } else { _lines pushBack (toString _cur); _cur = _w; };
            };
            if ((_w param [(count _w) - 1, 0]) isEqualTo 41 && {_cur isNotEqualTo []}) then { _lines pushBack (toString _cur); _cur = []; };
        } forEach _words;
        if (_cur isNotEqualTo []) then { _lines pushBack (toString _cur); };
        if ((count _lines) > 6) then { _lines resize 6; _lines set [5, (_lines select 5) + "…"]; };
        private _dur = ((3.5 + ((count _all) / 14)) max 4) min 15;
        private _tagCp = ((toArray _tag) select { _x >= 32 }) select [0, 32];
        private _vis = [_u, _cam] call _sees;
        [[_u, _lines, toUpper (toString _tagCp), [_kind] call _color, time, time + _dur, _vis, 0, false]] call _put;
        _vis
    };
    case "typing": {
        _arg params [["_u", objNull, [objNull]], ["_on", false], ["_tag", ""]];
        if (isNull _u) exitWith { false };
        private _list = missionNamespace getVariable ["comspec_sse_iarpBubbles", []];
        private _i = _list findIf { (_x select 0) isEqualTo _u };
        // Fin d'écriture : la bulle « … » part, une vraie bulle reste.
        if (!_on) exitWith { if (_i >= 0 && {(_list select _i) select 8}) then { _list deleteAt _i; }; false };
        // Une phrase encore affichée n'est pas remplacée par « … » avant d'avoir été lue (2 s).
        if (_i >= 0 && {!((_list select _i) select 8)} && {(time - ((_list select _i) select 4)) < 2}) exitWith { false };
        private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
        if ((_cam vectorDistance (eyePos _u)) > _range) exitWith { false };
        [[_u, ["…"], toUpper _tag, ["typing"] call _color, time, time + 8, [_u, _cam] call _sees, 0, true]] call _put;
        true
    };
    case "draw": {
        private _now = time;
        private _list = (missionNamespace getVariable ["comspec_sse_iarpBubbles", []]) select { (_x select 5) > _now && {alive (_x select 0)} };
        if (_list isEqualTo []) exitWith { ["clear"] call comspec_sse_fnc_iarpBubble; };
        if (_now >= (missionNamespace getVariable ["comspec_sse_iarpBubbleNext", 0])) then {
            missionNamespace setVariable ["comspec_sse_iarpBubbleNext", _now + 0.25];
            private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
            { _x set [6, [_x select 0, _cam] call _sees]; } forEach _list;
        };
        missionNamespace setVariable ["comspec_sse_iarpBubbles", _list];
        private _dt = (_now - (missionNamespace getVariable ["comspec_sse_iarpBubbleLast", _now])) max 0;
        missionNamespace setVariable ["comspec_sse_iarpBubbleLast", _now];
        if (visibleMap) exitWith {};
        {
            _x params ["_u", "_lines", "_tag", "_col", "_t0", "_t1", "_vis", "_va", "_typing"];
            _va = [(_va - _dt * 4) max 0, (_va + _dt * 4) min 1] select _vis;
            _x set [7, _va];
            if (_va <= 0.01) then { continue };
            private _head = (_u modelToWorldVisual (_u selectionPosition "pilot")) vectorAdd [0, 0, 0.35];
            private _s0 = worldToScreen _head;
            if (_s0 isEqualTo []) then { continue };
            private _s1 = worldToScreen (_head vectorAdd [0, 0, 1]);
            if (_s1 isEqualTo []) then { continue };
            private _perM = ((_s0 select 1) - (_s1 select 1)) max 0.002;
            private _a = _va * (((_now - _t0) / 0.2) min (((_t1 - _now) / 0.8) min 1));
            if (_typing) then { _lines = [["·", "··", "···"] select (floor (_now * 3) mod 3)]; };
            private _n = count _lines;
            private _at = { params ["_up"]; _head vectorAdd [0, 0, _up / _perM] };
            drawIcon3D ["", [_col select 0, _col select 1, _col select 2, _a], [(_n + 0.45) * IARP_LINE] call _at, 0, 0, 0, _tag, 2, IARP_TAG, "RobotoCondensedBold", "center"];
            {
                drawIcon3D ["", [_col select 0, _col select 1, _col select 2, _a], [(_n - _forEachIndex - 0.5) * IARP_LINE] call _at, 0, 0, 0, _x, 2, IARP_TEXT, "RobotoCondensed", "center"];
            } forEach _lines;
        } forEach _list;
    };
    case "clear": {
        private _eh = missionNamespace getVariable ["comspec_sse_iarpBubbleEh", -1];
        if (_eh >= 0) then { removeMissionEventHandler ["Draw3D", _eh]; };
        missionNamespace setVariable ["comspec_sse_iarpBubbleEh", -1];
        missionNamespace setVariable ["comspec_sse_iarpBubbles", []];
    };
};
