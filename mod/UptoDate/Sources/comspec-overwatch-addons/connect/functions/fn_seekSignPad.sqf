/*
    Pad de signature du SEEK II (page 11 du terminal) : l'opérateur trace sa signature à la souris.

    Les traits sont gardés en vecteurs (points normalisés 0..1000 dans le pad), jamais en image : le site
    redessine le PNG (App\Services\Sse\SseSignatureStrokes). Le tracé s'affiche avec de petits points posés
    dans le dialogue ; aucune alerte à l'écran, les retours passent par la ligne d'état du pad.

    [_op, _args] call comspec_overwatch_connect_fnc_seekSignPad
      "init"            au chargement du dialogue : accroche la souris, remet à zéro si rien n'est signé
      "open" / "back"   va à la page signature / revient à la page ENVOI
      "clear"           efface le tracé
      "accept"          valide le tracé, signe la fiche (sseSignAtak) et revient à ENVOI
      "show" [bool]     affiche ou masque les points (changement de page)
      "json"            => fragment JSON  ,"strokes":[[[x,y],...]],"aspect":2.5  (chaîne vide si aucun tracé)
      "down" "move" "up"  évènements souris du dialogue

    Bornes (le serveur re-valide, et accepte davantage) : 8 traits, 150 points par trait, 300 points au total,
    pour que la fiche reste de taille raisonnable pour l'extension.
*/
params [["_op", "init", [""]], ["_args", []]];
if (!hasInterface) exitWith { "" };

private _maxStrokes = 8;
private _maxPerStroke = 150;
private _maxPoints = 300;
private _minDistPx = 5;
private _dotPx = 3;
private _maxDots = 900;

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith { "" };

private _st = uiNamespace getVariable ["COMSPEC_SeekPad", createHashMap];
if (!(_st isEqualType createHashMap)) then { _st = createHashMap; };
if (!("strokes" in _st)) then {
    _st set ["strokes", []];
    _st set ["cur", []];
    _st set ["drawing", false];
    _st set ["dots", []];
    _st set ["total", 0];
    _st set ["full", false];
};
uiNamespace setVariable ["COMSPEC_SeekPad", _st];

private _pad = _disp displayCtrl 9700;
private _say = {
    params ["_txt", ["_col", "#44515c"]];
    private _c = _disp displayCtrl 9701;
    if (!isNull _c) then {
        _c ctrlSetStructuredText parseText format ["<t size='0.44' color='%2' align='center'>%1</t>", _txt call comspec_overwatch_connect_fnc_tr, _col];
    };
};
private _wipe = {
    { if (!isNull _x) then { ctrlDelete _x; }; } forEach (_st get "dots");
    _st set ["dots", []];
    _st set ["strokes", []];
    _st set ["cur", []];
    _st set ["drawing", false];
    _st set ["total", 0];
    _st set ["full", false];
};
private _aspect = {
    private _p = ctrlPosition _pad;
    private _pw = pixelW max 0.0001;
    private _ph = pixelH max 0.0001;
    (((_p select 2) / _pw) / (((_p select 3) / _ph) max 1)) max 1 min 6
};
private _inside = {
    params ["_xp", "_yp"];
    private _p = ctrlPosition _pad;
    (_xp >= (_p select 0)) && {_xp <= ((_p select 0) + (_p select 2))} && {_yp >= (_p select 1)} && {_yp <= ((_p select 1) + (_p select 3))}
};
private _addDot = {
    params ["_sx", "_sy"];
    private _dots = _st get "dots";
    if ((count _dots) >= _maxDots) exitWith {};
    private _d = _disp ctrlCreate ["RscText", -1];
    _d ctrlSetPosition [_sx - (_dotPx * pixelW) / 2, _sy - (_dotPx * pixelH) / 2, _dotPx * pixelW, _dotPx * pixelH];
    _d ctrlSetBackgroundColor [0.07, 0.12, 0.28, 1];
    _d ctrlCommit 0;
    _dots pushBack _d;
};

switch (_op) do {
    case "init": {
        private _sig = uiNamespace getVariable ["COMSPEC_SsePerson_Signature", []];
        // Un nouveau dialogue repart d'un pad vide (les points affichés sont détruits avec l'ancien) ;
        // le tracé déjà validé ne survit que si la fiche est encore signée.
        call _wipe;
        if (!(_sig isEqualType []) || {(count _sig) < 4}) then { uiNamespace setVariable ["COMSPEC_SsePerson_SignStrokes", []]; };
        if (!(_disp getVariable ["COMSPEC_SeekPadHooked", false])) then {
            _disp setVariable ["COMSPEC_SeekPadHooked", true];
            _disp displayAddEventHandler ["MouseButtonDown", {
                params ["", "_btn", "_xp", "_yp"];
                if (_btn isEqualTo 0) then { ["down", [_xp, _yp]] call comspec_overwatch_connect_fnc_seekSignPad; };
            }];
            _disp displayAddEventHandler ["MouseMoving", {
                params ["", "_xp", "_yp"];
                ["move", [_xp, _yp]] call comspec_overwatch_connect_fnc_seekSignPad;
            }];
            _disp displayAddEventHandler ["MouseButtonUp", {
                params ["", "_btn"];
                if (_btn isEqualTo 0) then { ["up"] call comspec_overwatch_connect_fnc_seekSignPad; };
            }];
        };
        ""
    };
    case "open": {
        // _args = [true] : appelé par la touche SIGN, VALIDER relance l'envoi (fn_seekSend) après la signature.
        _st set ["sendAfter", _args param [0, false]];
        [11] call comspec_overwatch_connect_fnc_sseTerminalPage;
        ["Tracez votre signature à la souris, bouton gauche maintenu."] call _say;
        ""
    };
    case "back": {
        _st set ["sendAfter", false];
        [6] call comspec_overwatch_connect_fnc_sseTerminalPage;
        ""
    };
    case "clear": {
        call _wipe;
        ["Tracé effacé. Signez de nouveau."] call _say;
        ""
    };
    case "show": {
        private _vis = _args param [0, false];
        { if (!isNull _x) then { _x ctrlShow _vis; }; } forEach (_st get "dots");
        ""
    };
    case "down": {
        if ((uiNamespace getVariable ["COMSPEC_SsePerson_Page", 0]) isNotEqualTo 11) exitWith { "" };
        _args params ["_xp", "_yp"];
        private _p = ctrlPosition _pad;
        if !([_xp, _yp] call _inside) exitWith { "" };
        if ((count (_st get "strokes")) >= _maxStrokes) exitWith {
            ["Trop de traits : effacez et signez plus simplement."] call _say;
            ""
        };
        _st set ["drawing", true];
        _st set ["cur", []];
        _st set ["last", [_xp, _yp]];
        ["pt", [_xp, _yp]] call comspec_overwatch_connect_fnc_seekSignPad;
        ""
    };
    case "pt": {
        _args params ["_xp", "_yp"];
        private _p = ctrlPosition _pad;
        private _cur = _st get "cur";
        if ((count _cur) >= _maxPerStroke || {(_st get "total") >= _maxPoints}) exitWith {
            if (!(_st get "full")) then {
                _st set ["full", true];
                ["Signature assez longue : validez ou effacez."] call _say;
            };
            ""
        };
        private _nx = round (((((_xp - (_p select 0)) / ((_p select 2) max 0.0001)) max 0) min 1) * 1000);
        private _ny = round (((((_yp - (_p select 1)) / ((_p select 3) max 0.0001)) max 0) min 1) * 1000);
        _cur pushBack [_nx, _ny];
        _st set ["total", (_st get "total") + 1];
        [_xp, _yp] call _addDot;
        ""
    };
    case "move": {
        if (!(_st getOrDefault ["drawing", false])) exitWith { "" };
        if ((uiNamespace getVariable ["COMSPEC_SsePerson_Page", 0]) isNotEqualTo 11) exitWith { "" };
        _args params ["_xp", "_yp"];
        private _p = ctrlPosition _pad;
        // Sorti du pad : le trait s'arrête (reprise possible en revenant bouton relâché puis enfoncé).
        if !([_xp, _yp] call _inside) exitWith {
            ["up"] call comspec_overwatch_connect_fnc_seekSignPad;
            ""
        };
        private _last = _st getOrDefault ["last", [_xp, _yp]];
        private _dx = (_xp - (_last select 0)) / (pixelW max 0.0001);
        private _dy = (_yp - (_last select 1)) / (pixelH max 0.0001);
        private _dist = sqrt (_dx * _dx + _dy * _dy);
        if (_dist < _minDistPx) exitWith { "" };
        // Points intermédiaires d'affichage seulement : un mouvement rapide ne laisse pas de trou.
        private _n = floor (_dist / (_dotPx * 2));
        for "_i" from 1 to _n do {
            private _f = _i / (_n + 1);
            [(_last select 0) + (_xp - (_last select 0)) * _f, (_last select 1) + (_yp - (_last select 1)) * _f] call _addDot;
        };
        _st set ["last", [_xp, _yp]];
        ["pt", [_xp, _yp]] call comspec_overwatch_connect_fnc_seekSignPad;
        ""
    };
    case "up": {
        if (!(_st getOrDefault ["drawing", false])) exitWith { "" };
        _st set ["drawing", false];
        private _cur = _st get "cur";
        if ((count _cur) > 0) then { (_st get "strokes") pushBack _cur; };
        _st set ["cur", []];
        ""
    };
    case "accept": {
        ["up"] call comspec_overwatch_connect_fnc_seekSignPad;
        private _strokes = _st get "strokes";
        private _all = [];
        { _all append _x; } forEach _strokes;
        private _xs = _all apply { _x select 0 };
        private _ys = _all apply { _x select 1 };
        if ((count _all) < 6 || {(((selectMax _xs) - (selectMin _xs)) < 20) && {((selectMax _ys) - (selectMin _ys)) < 20}}) exitWith {
            ["Signature trop courte : tracez-la en entier.", "#b3261e"] call _say;
            ""
        };
        uiNamespace setVariable ["COMSPEC_SsePerson_SignStrokes", [_strokes, call _aspect]];
        [] call comspec_overwatch_connect_fnc_sseSignAtak;
        if (_st getOrDefault ["sendAfter", false]) then {
            _st set ["sendAfter", false];
            ["send"] call comspec_overwatch_connect_fnc_seekSend;
        } else {
            [6] call comspec_overwatch_connect_fnc_sseTerminalPage;
        };
        ""
    };
    case "json": {
        private _ss = uiNamespace getVariable ["COMSPEC_SsePerson_SignStrokes", []];
        if (!(_ss isEqualType []) || {(count _ss) < 2}) exitWith { "" };
        _ss params ["_strokes", "_asp"];
        if (!(_strokes isEqualType []) || {(count _strokes) == 0}) exitWith { "" };
        private _parts = _strokes apply {
            "[" + ((_x apply { format ["[%1,%2]", round (_x select 0), round (_x select 1)] }) joinString ",") + "]"
        };
        format [',"strokes":[%1],"aspect":%2', _parts joinString ",", (_asp toFixed 2)]
    };
    default { "" };
};
