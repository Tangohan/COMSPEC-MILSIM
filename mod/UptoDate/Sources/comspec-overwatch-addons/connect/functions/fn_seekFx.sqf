/*
    SEEK II : animation du lecteur d'empreintes (vitre verte), mini-jeux facultatifs à la souris et réglage « Interactions ».

    Tout se passe dans le dialogue du terminal : jamais de toast, de message de chat ni de popup. Les retours vont dans la
    ligne d'état du lecteur. L'état des mini-jeux est local au joueur ; seul le résultat (qualité, motifs) est utilisé
    par la capture, puis transmis avec la fiche. Rien ne dépend de la fréquence d'images : toutes les durées viennent de
    diag_tickTime. Aucun mini-jeu ne bloque définitivement : bouton PASSER, délai maximum, résultat toujours rendu.

    Réglage « Interactions SEEK II » (comspec_seek_fx_mode, préférence client modifiable par le bouton INTERACTIONS de
    l'accueil, imposable par le serveur ou la mission via les réglages CBA) :
      0 aucune     capture automatique comme avant (barre de progression ACE)
      1 simples    animation du lecteur, sans geste à faire
      2 complètes  animation et mini-jeux : doigt tenu sur la vitre, iris aligné, câbles, nettoyage de la vitre

    [_op, _args] call comspec_overwatch_connect_fnc_seekFx
      "mode"           -> 0, 1 ou 2
      "cycleMode"      mode suivant (mémorisé dans le profil)
      "hook"           accroche la souris et le clavier du dialogue (une fois par affichage)
      "capture" [kind, target, callback]   À LANCER AVEC spawn. kind = "empreintes" | "iris".
                       callback reçoit une HashMap : quality (0-100), fingers (10 valeurs 0-100, -2 non capturable),
                       eyes ([OD, OG], -2 non capturable), notes (motifs lisibles), skipped, source
    Dispositions (médical) : fn_bioMedical du pack SSE (ACE Medical, KAT, ACM, repli vanilla), désactivable par
    comspec_seek_fx_medical.
*/
params [["_op", "mode", [""]], ["_args", []]];
if (!hasInterface) exitWith { 0 };

private _mode = profileNamespace getVariable ["COMSPEC_SeekFxMode", missionNamespace getVariable ["comspec_seek_fx_mode", 2]];
if (!(_mode isEqualType 0)) then { _mode = 2; };
_mode = (round _mode) max 0 min 2;
if (_op isEqualTo "mode") exitWith { _mode };

if (_op isEqualTo "cycleMode") exitWith {
    _mode = (_mode + 1) mod 3;
    profileNamespace setVariable ["COMSPEC_SeekFxMode", _mode];
    if (!isNil "comspec_overwatch_connect_fnc_saveProfile") then { [] call comspec_overwatch_connect_fnc_saveProfile; };
    ["refresh"] call comspec_overwatch_connect_fnc_seekScale;
    _mode
};

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith { 0 };

if (_op isEqualTo "hook") exitWith {
    if (_disp getVariable ["COMSPEC_SeekFxHooked", false]) exitWith { 1 };
    _disp setVariable ["COMSPEC_SeekFxHooked", true];
    uiNamespace setVariable ["COMSPEC_SeekFxMouse", [0.5, 0.5, false]];
    _disp displayAddEventHandler ["MouseMoving", {
        params ["", "_mx", "_my"];
        private _m = uiNamespace getVariable ["COMSPEC_SeekFxMouse", [0.5, 0.5, false]];
        uiNamespace setVariable ["COMSPEC_SeekFxMouse", [_mx, _my, _m select 2]];
    }];
    _disp displayAddEventHandler ["MouseButtonDown", {
        params ["", "_btn", "_mx", "_my"];
        if (_btn isEqualTo 0) then { uiNamespace setVariable ["COMSPEC_SeekFxMouse", [_mx, _my, true]]; };
    }];
    _disp displayAddEventHandler ["MouseButtonUp", {
        params ["", "_btn", "_mx", "_my"];
        if (_btn isEqualTo 0) then { uiNamespace setVariable ["COMSPEC_SeekFxMouse", [_mx, _my, false]]; };
    }];
    // Pavé numérique + et - : taille du terminal (comme le bouton TAILLE de l'accueil).
    _disp displayAddEventHandler ["KeyDown", {
        params ["", "_key"];
        if (_key isEqualTo 78) then { ["cycle", [-1]] call comspec_overwatch_connect_fnc_seekScale; };
        if (_key isEqualTo 74) then { ["cycle", [1]] call comspec_overwatch_connect_fnc_seekScale; };
        false
    }];
    1
};

if (_op isNotEqualTo "capture") exitWith { 0 };
if (!canSuspend) exitWith { _this spawn comspec_overwatch_connect_fnc_seekFx; 0 };

// =====================================================================================================================
//  Séquence de capture
// =====================================================================================================================
_args params [["_kind", "empreintes", [""]], ["_target", objNull, [objNull]], ["_cb", {}, [{}]]];
private _full = _mode >= 2;
private _res = createHashMapFromArray [["quality", 0], ["fingers", [-1, -1, -1, -1, -1, -1, -1, -1, -1, -1]], ["eyes", [-1, -1]], ["notes", []], ["skipped", false], ["source", "seek"]];
private _notes = _res get "notes";

private _medOf = {
    params ["_u"];
    if (isNil "comspec_sse_fnc_bioMedical") then { createHashMap } else { [_u] call comspec_sse_fnc_bioMedical }
};
private _med = [_target] call _medOf;
private _tremor = (([player] call _medOf) getOrDefault ["tremor", 0]) * (if (_full) then { 1 } else { 0 });
_res set ["source", _med getOrDefault ["source", "vanilla"]];
private _hands = _med getOrDefault ["hand", [["ok", 0, ""], ["ok", 0, ""]]];
private _head = _med getOrDefault ["head", ["ok", 0, ""]];

uiNamespace setVariable ["COMSPEC_SeekFxBusy", true];
uiNamespace setVariable ["COMSPEC_SeekFxSkip", false];
private _ctrls = [];
private _done = {
    { if (!isNull _x) then { ctrlDelete _x; }; } forEach _ctrls;
    _ctrls = [];
};
private _mk = {
    params ["_cls", "_px", "_py", "_pw", "_ph", ["_col", [0, 0, 0, 0]]];
    private _c = _disp ctrlCreate [_cls, -1];
    _c ctrlSetPosition [_px, _py, _pw, _ph];
    _c ctrlSetBackgroundColor _col;
    _c ctrlCommit 0;
    _ctrls pushBack _c;
    _c
};
private _alive = { !isNull _disp && {!isNull (uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull])} };
private _snd = { params ["_n"]; if (isClass (configFile >> "CfgSounds" >> _n)) then { playSound _n; }; };
private _mouse = { uiNamespace getVariable ["COMSPEC_SeekFxMouse", [0.5, 0.5, false]] };

// --- Où est la vitre verte : sur le châssis, ou dans une fenêtre fixe si elle sort de l'écran ----------------------
private _chas = (allControls _disp) param [0, controlNull];
private _cp = if (isNull _chas) then { [safezoneX, safezoneY, safezoneW, safezoneH] } else { ctrlPosition _chas };
private _k = if ((count (_disp getVariable ["COMSPEC_SeekBase", []])) > 0) then { ["k"] call comspec_overwatch_connect_fnc_seekScale } else { 1 };
private _dw = (_cp select 2) * _k;
private _dh = (_cp select 3) * _k;
private _dx = (_cp select 0) + ((_cp select 2) - _dw) / 2;
private _dy = (_cp select 1) + ((_cp select 3) - _dh) / 2;
private _R = [_dx + _dw * 0.398, _dy + _dh * 0.703, _dw * 0.196, _dh * 0.164];
private _onGlass = (_R select 2) >= 0.13 && {(_R select 1) >= safezoneY} && {((_R select 1) + (_R select 3)) <= (safezoneY + safezoneH - 0.005)};
if (!_onGlass) then {
    _R = [safezoneX + safezoneW / 2 - 0.17, safezoneY + safezoneH - 0.30, 0.34, 0.26];
} else {
    // Fenêtre un peu plus large que la vitre pour que les gestes restent confortables.
    _R = [(_R select 0) - (_R select 2) * 0.35, (_R select 1) - (_R select 3) * 0.25, (_R select 2) * 1.7, (_R select 3) * 1.5];
};
_R params ["_rx", "_ry", "_rw", "_rh"];
private _zx = _rx + _rw * 0.32;
private _zy = _ry + _rh * 0.20;
private _zw = _rw * 0.36;
private _zh = _rh * 0.58;
private _zcx = _zx + _zw / 2;
private _zcy = _zy + _zh / 2;

// --- Panneau --------------------------------------------------------------------------------------------------------
["RscText", _rx, _ry, _rw, _rh, [0.03, 0.17, 0.09, 0.96]] call _mk;
["RscText", _rx, _ry, _rw, _rh * 0.012, [0.45, 1, 0.65, 0.9]] call _mk;
private _title = ["RscStructuredText", _rx + _rw * 0.03, _ry + _rh * 0.02, _rw * 0.7, _rh * 0.16] call _mk;
private _msg = ["RscStructuredText", _rx + _rw * 0.03, _ry + _rh * 0.80, _rw * 0.94, _rh * 0.10] call _mk;
["RscText", _zx, _zy, _zw, _zh, [0.01, 0.08, 0.04, 1]] call _mk;
private _blob = ["RscPicture", _zx - _zw * 0.05, _zy - _zh * 0.05, _zw * 1.1, _zh * 1.1] call _mk;
_blob ctrlSetText "\A3\ui_f\data\map\markers\military\dot_CA.paa";
_blob ctrlSetTextColor [0.5, 1, 0.6, 0];
_blob ctrlCommit 0;
private _ridgeN = 14;
private _ridges = [];
for "_i" from 0 to (_ridgeN - 1) do {
    private _arch = sin (180 * (_i + 0.5) / _ridgeN);
    private _bw = _zw * (0.30 + 0.62 * _arch);
    private _bh = (_zh / _ridgeN) * 0.45;
    private _by = _zy + _zh * (_i + 0.5) / _ridgeN - _bh / 2;
    private _bx = _zcx - _bw / 2 + (_zw * 0.06 * sin (_i * 41));
    _ridges pushBack (["RscText", _bx, _by, _bw, _bh, [0.55, 1, 0.65, 0]] call _mk);
};
private _scan = ["RscText", _zx, _zy, _zw, _zh * 0.03, [0.75, 1, 0.85, 0]] call _mk;
private _gaugeBg = ["RscText", _rx + _rw * 0.06, _ry + _rh * 0.92, _rw * 0.88, _rh * 0.045, [0, 0, 0, 0.6]] call _mk;
private _gauge = ["RscText", _rx + _rw * 0.06, _ry + _rh * 0.92, 0.0001, _rh * 0.045, [0.35, 0.95, 0.5, 1]] call _mk;
private _skipBtn = ["COMSPEC_SeekBtn", _rx + _rw * 0.76, _ry + _rh * 0.025, _rw * 0.21, _rh * 0.11] call _mk;
_skipBtn ctrlSetText ("PASSER" call comspec_overwatch_connect_fnc_tr);
_skipBtn ctrlAddEventHandler ["ButtonClick", { uiNamespace setVariable ["COMSPEC_SeekFxSkip", true]; }];
_skipBtn ctrlShow _full;

// Texte du lecteur : une chaîne, ou [modèle, argument…] dont le modèle et chaque argument sont traduits avant d'être assemblés.
private _say = {
    params ["_txt", ["_col", "#d8ffe2"]];
    if (_txt isEqualType []) then {
        _txt = format ([(_txt select 0) call comspec_overwatch_connect_fnc_tr] + ((_txt select [1, 4]) apply { _x call comspec_overwatch_connect_fnc_tr }));
    };
    if (!isNull _msg) then {
        _msg ctrlSetStructuredText parseText format ["<t size='0.9' color='%2' align='center'>%1</t>", _txt call comspec_overwatch_connect_fnc_tr, _col];
    };
};
private _head1 = {
    params ["_txt"];
    if (!isNull _title) then {
        _title ctrlSetStructuredText parseText format ["<t size='1.0' font='TahomaB' color='#9dffb8'>%1</t>", _txt call comspec_overwatch_connect_fnc_tr];
    };
};
private _setGauge = {
    params ["_f"];
    _gauge ctrlSetPosition [_rx + _rw * 0.06, _ry + _rh * 0.92, (_rw * 0.88) * ((_f max 0.0001) min 1), _rh * 0.045];
    private _lvl = 0;
    if (_f > 0.45) then { _lvl = 1; };
    if (_f > 0.75) then { _lvl = 2; };
    _gauge ctrlSetBackgroundColor ([[0.9, 0.35, 0.25, 1], [0.95, 0.75, 0.2, 1], [0.35, 0.95, 0.5, 1]] select _lvl);
    _gauge ctrlCommit 0;
};
private _showRidges = {
    params ["_p", "_a"];
    {
        private _f = ((_p * (_ridgeN + 2) - _forEachIndex) / 1.6) max 0 min 1;
        _x ctrlSetBackgroundColor [0.55, 1, 0.65, _f * _a];
        _x ctrlCommit 0;
    } forEach _ridges;
};
private _resetGlass = {
    [0, 0] call _showRidges;
    _scan ctrlSetBackgroundColor [0.75, 1, 0.85, 0];
    _scan ctrlCommit 0;
    _blob ctrlSetTextColor [0.5, 1, 0.6, 0];
    _blob ctrlCommit 0;
    [0] call _setGauge;
};
private _wait = {
    params ["_s"];
    private _t = diag_tickTime + _s;
    waitUntil { uiSleep 0.03; diag_tickTime >= _t || {!(call _alive)} };
};

// --- Mini-jeu : tenir le doigt sur la vitre pendant le balayage ----------------------------------------------------
// Retourne la qualité 0..1. Cliquer et maintenir le bouton gauche dans la zone ; en sortir met le balayage en pause.
private _hold = {
    params ["_dur"];
    private _prog = 0;
    private _steady = 0;
    private _waste = 0;
    private _slips = 0;
    private _engaged = false;
    private _t0 = diag_tickTime;
    private _last = _t0;
    uiNamespace setVariable ["COMSPEC_SeekFxSkip", false];
    while { _prog < 1 && {call _alive} && {(diag_tickTime - _t0) < (_dur * 3 + 6)} && {!(uiNamespace getVariable ["COMSPEC_SeekFxSkip", false])} } do {
        private _now = diag_tickTime;
        private _dt = (_now - _last) min 0.2;
        _last = _now;
        private _m = call _mouse;
        // La zone tremble avec les bras blessés de l'opérateur.
        private _jx = _tremor * _zw * 0.18 * sin (_now * 520);
        private _jy = _tremor * _zh * 0.18 * sin (_now * 430 + 1);
        private _inside = ((abs ((_m select 0) - (_zcx + _jx))) < _zw * 0.5) && {(abs ((_m select 1) - (_zcy + _jy))) < _zh * 0.5};
        private _down = _m select 2;
        if (_down && _inside) then {
            _prog = _prog + _dt / _dur;
            _steady = _steady + _dt;
            _engaged = true;
            _blob ctrlSetTextColor [0.5, 1, 0.6, 0.4];
        } else {
            _blob ctrlSetTextColor [0.5, 1, 0.6, 0.08];
            if (_engaged) then { _slips = _slips + 1; _engaged = false; _prog = (_prog - 0.07) max 0; call { ["Doigt qui bouge : recommencez le balayage"] call _say; }; };
            if (_down && !_inside) then { _waste = _waste + _dt; };
        };
        _blob ctrlCommit 0;
        [_prog min 1, 0.95] call _showRidges;
        _scan ctrlSetPosition [_zx, _zy + (_zh * 0.97) * (_prog min 1), _zw, _zh * 0.03];
        _scan ctrlSetBackgroundColor [0.75, 1, 0.85, [0, 0.9] select (_down && _inside)];
        _scan ctrlCommit 0;
        [(_prog min 1) * 0.9] call _setGauge;
        uiSleep 0.03;
    };
    private _q = 0.55 + 0.45 * (_steady / (_steady + _waste * 1.5 + _slips * 0.35 + 0.001));
    if (_prog < 1) then { _q = _q * 0.7; };
    _q max 0.2 min 1
};

// --- Simple : le lecteur balaye seul (même rendu, sans geste) ------------------------------------------------------
private _auto = {
    params ["_dur"];
    private _t0 = diag_tickTime;
    _blob ctrlSetTextColor [0.5, 1, 0.6, 0.4];
    _blob ctrlCommit 0;
    while { call _alive && {(diag_tickTime - _t0) < _dur} } do {
        private _p = (diag_tickTime - _t0) / _dur;
        [_p, 0.95] call _showRidges;
        _scan ctrlSetPosition [_zx, _zy + (_zh * 0.97) * _p, _zw, _zh * 0.03];
        _scan ctrlSetBackgroundColor [0.75, 1, 0.85, 0.9];
        _scan ctrlCommit 0;
        [_p * 0.9] call _setGauge;
        uiSleep 0.03;
    };
    0.75 + random 0.2
};

// --- Mini-jeu : nettoyer la vitre (faire glisser la souris, bouton gauche maintenu, sur les taches) --------------
private _wipe = {
    private _cols = 4;
    private _rows = 3;
    private _cells = [];
    for "_cy" from 0 to (_rows - 1) do {
        for "_cx" from 0 to (_cols - 1) do {
            private _c = ["RscText", _zx + _zw * _cx / _cols, _zy + _zh * _cy / _rows, _zw / _cols, _zh / _rows, [0.82, 0.86, 0.8, 0.55]] call _mk;
            _cells pushBack [_c, true];
        };
    };
    ["Vitre sale ou humide : essuyez-la (bouton gauche maintenu)", "#ffe9a8"] call _say;
    private _t0 = diag_tickTime;
    uiNamespace setVariable ["COMSPEC_SeekFxSkip", false];
    while { call _alive && {(diag_tickTime - _t0) < 10} && {!(uiNamespace getVariable ["COMSPEC_SeekFxSkip", false])} && {(_cells findIf { _x select 1 }) >= 0} } do {
        private _m = call _mouse;
        if (_m select 2) then {
            {
                _x params ["_c", "_dirty"];
                if (_dirty) then {
                    private _p = ctrlPosition _c;
                    if ((_m select 0) >= _p select 0 && {(_m select 0) <= (_p select 0) + (_p select 2)} && {(_m select 1) >= _p select 1} && {(_m select 1) <= (_p select 1) + (_p select 3)}) then {
                        _c ctrlSetBackgroundColor [0.82, 0.86, 0.8, 0];
                        _c ctrlCommit 0;
                        (_cells select _forEachIndex) set [1, false];
                    };
                };
            } forEach _cells;
        };
        uiSleep 0.03;
    };
    { ctrlDelete (_x select 0); } forEach _cells;
    _ctrls = _ctrls select { !isNull _x };
    true
};

// --- Une pose de doigts (une ou plusieurs doigts), avec rejet réaliste et reprise --------------------------------
// Retourne la qualité 0..1 (le résultat de la dernière tentative, jamais bloquant).
private _place = {
    params ["_label", "_idx", "_pReject"];
    private _try = 0;
    private _q = 0.8;
    private _ok = false;
    while { !_ok && {call _alive} } do {
        _try = _try + 1;
        call _resetGlass;
        [_label] call _head1;
        { private _tip = _disp displayCtrl (9610 + _x); if (!isNull _tip) then { _tip ctrlSetBackgroundColor [1, 0.85, 0.2, 1]; }; } forEach _idx;
        if (_full) then {
            ["Posez le doigt dans la zone verte et maintenez le bouton gauche pendant le balayage"] call _say;
            _q = [4 + random 1.5] call _hold;
        } else {
            ["Lecture en cours : ne bougez pas"] call _say;
            _q = [2.4 + random 0.8] call _auto;
        };
        if (!(call _alive) || {uiNamespace getVariable ["COMSPEC_SeekFxSkip", false]}) exitWith { _ok = true; };
        // Rejet : doigt mal posé, humide, vitre sale. Plus probable si la qualité du geste est faible.
        private _reject = _try < 3 && {(random 1) < (_pReject + (1 - _q) * 0.5)};
        if (_reject) then {
            private _why = selectRandom ["Doigt mal posé", "Doigt humide", "Vitre sale"];
            ["Lecture rejetée : " + _why, "#ff9c8a"] call _say;
            _notes pushBackUnique format ["rejet : %1", toLower _why];
            ["COMSPEC_ATAK_Disconnect"] call _snd;
            [0.2] call _setGauge;
            [1.0] call _wait;
            if (_why in ["Doigt humide", "Vitre sale"]) then {
                if (_full) then { call _wipe; } else { ["Essuyage automatique", "#ffe9a8"] call _say; [1.2] call _wait; };
            };
        } else {
            _ok = true;
        };
    };
    [_q min 1] call _setGauge;
    [1] call _showRidges;
    ["COMSPEC_ATAK_Beep"] call _snd;
    [0.5] call _wait;
    _q
};

// --- Mini-jeu : brancher les câbles USB et ALIMENTATION en les faisant glisser sur le bon port ------------------------
private _cables = {
    private _types = [["USB", [0.3, 0.55, 1, 1]], ["ALIM", [1, 0.6, 0.15, 1]]];
    private _order = selectRandom [[0, 1], [1, 0]];
    private _pw = _rw * 0.20;
    private _ph = _rh * 0.16;
    private _plugs = [];
    private _ports = [];
    {
        private _t = _types select _x;
        private _y = _ry + _rh * (0.28 + 0.26 * _forEachIndex);
        private _plug = ["RscText", _rx + _rw * 0.06, _y, _pw, _ph, _t select 1] call _mk;
        _plug ctrlSetText "";
        private _lab = ["RscStructuredText", _rx + _rw * 0.06, _y, _pw, _ph] call _mk;
        _lab ctrlSetStructuredText parseText format ["<t size='0.9' font='TahomaB' align='center' color='#101418'>%1</t>", _t select 0];
        _plugs pushBack [_plug, [_rx + _rw * 0.06, _y, _pw, _ph], _x, false];
    } forEach [0, 1];
    {
        private _t = _types select _x;
        private _y = _ry + _rh * (0.28 + 0.26 * _forEachIndex);
        private _port = ["RscText", _rx + _rw * 0.74, _y, _pw, _ph, [0.12, 0.2, 0.15, 1]] call _mk;
        private _lab = ["RscStructuredText", _rx + _rw * 0.74, _y, _pw, _ph] call _mk;
        _lab ctrlSetStructuredText parseText format ["<t size='0.9' font='TahomaB' align='center' color='#cfe9d6'>PORT %1</t>", _t select 0];
        _ports pushBack [_port, [_rx + _rw * 0.74, _y, _pw, _ph], _x];
    } forEach _order;
    // Câbles : une chaîne de petits points entre la prise et la souris (ou le port une fois branché).
    private _chainN = 12;
    private _chains = [];
    for "_j" from 0 to 1 do {
        private _col = (_types select _j) select 1;
        private _dots = [];
        for "_d" from 0 to (_chainN - 1) do { _dots pushBack (["RscText", 0, 0, 0.006, 0.01, _col] call _mk); };
        _chains pushBack _dots;
    };
    ["Reliez chaque câble à son port : glissez, bouton gauche maintenu", "#ffe9a8"] call _say;
    private _drag = -1;
    private _t0 = diag_tickTime;
    uiNamespace setVariable ["COMSPEC_SeekFxSkip", false];
    private _link = {
        params ["_j", "_ex", "_ey"];
        private _p = (_plugs select _j) select 1;
        private _sx = (_p select 0) + (_p select 2);
        private _sy = (_p select 1) + (_p select 3) / 2;
        {
            private _f = _forEachIndex / (_chainN - 1);
            _x ctrlSetPosition [_sx + (_ex - _sx) * _f - 0.003, _sy + (_ey - _sy) * _f + 0.02 * sin (_f * 180) - 0.005, 0.006, 0.01];
            _x ctrlCommit 0;
        } forEach (_chains select _j);
    };
    { [_forEachIndex, _rx + _rw * 0.06 + _pw, _ry + _rh * (0.28 + 0.26 * _forEachIndex) + _ph / 2] call _link; } forEach _plugs;
    while { call _alive && {(diag_tickTime - _t0) < 25} && {!(uiNamespace getVariable ["COMSPEC_SeekFxSkip", false])} && {(_plugs findIf { !(_x select 3) }) >= 0} } do {
        private _m = call _mouse;
        private _mx = _m select 0;
        private _my = _m select 1;
        private _inRect = {
            params ["_r"];
            _mx >= _r select 0 && {_mx <= (_r select 0) + (_r select 2)} && {_my >= _r select 1} && {_my <= (_r select 1) + (_r select 3)}
        };
        if (_m select 2) then {
            if (_drag < 0) then {
                { if (!(_x select 3) && {[_x select 1] call _inRect}) exitWith { _drag = _forEachIndex; }; } forEach _plugs;
            };
            if (_drag >= 0) then { [_drag, _mx, _my] call _link; };
        } else {
            if (_drag >= 0) then {
                private _pi = _ports findIf { [_x select 1] call _inRect };
                if (_pi >= 0 && {((_ports select _pi) select 2) isEqualTo ((_plugs select _drag) select 2)}) then {
                    private _pp = (_ports select _pi) select 1;
                    [_drag, _pp select 0, (_pp select 1) + (_pp select 3) / 2] call _link;
                    (_plugs select _drag) set [3, true];
                    ["COMSPEC_ATAK_Beep"] call _snd;
                    ["Câble branché", "#d8ffe2"] call _say;
                } else {
                    [_drag, _rx + _rw * 0.06 + _pw, _ry + _rh * (0.28 + 0.26 * _drag) + _ph / 2] call _link;
                    if (_pi >= 0) then { ["Mauvais port : essayez l'autre", "#ff9c8a"] call _say; };
                };
                _drag = -1;
            };
        };
        uiSleep 0.03;
    };
    private _all = (_plugs findIf { !(_x select 3) }) < 0;
    if (_all) then { ["Lecteur raccordé", "#d8ffe2"] call _say; [0.8] call _wait; };
    _all
};

// --- Mini-jeu : aligner l'iris (garder le réticule sur l'œil qui bouge un peu) ------------------------------------
private _track = {
    params ["_dur"];
    private _eye = ["RscPicture", _zcx - 0.02, _zcy - 0.02, 0.04, 0.05] call _mk;
    _eye ctrlSetText "\A3\ui_f\data\map\markers\military\dot_CA.paa";
    _eye ctrlSetTextColor [0.55, 0.8, 1, 0.9];
    private _ret = ["RscPicture", _zcx - 0.02, _zcy - 0.02, 0.04, 0.05] call _mk;
    _ret ctrlSetText "\A3\ui_f\data\map\markers\military\dot_CA.paa";
    _ret ctrlSetTextColor [1, 1, 1, 0.45];
    private _inside = 0;
    private _t0 = diag_tickTime;
    private _last = _t0;
    uiNamespace setVariable ["COMSPEC_SeekFxSkip", false];
    private _tol = 0.2 + 0.05 * (1 - _tremor);
    while { call _alive && {_inside < _dur} && {(diag_tickTime - _t0) < (_dur * 2.5 + 3)} && {!(uiNamespace getVariable ["COMSPEC_SeekFxSkip", false])} } do {
        private _now = diag_tickTime;
        private _dt = (_now - _last) min 0.2;
        _last = _now;
        private _tx = _zcx + _zw * 0.30 * sin (_now * 80) ;
        private _ty = _zcy + _zh * 0.25 * sin (_now * 110 + 40);
        _eye ctrlSetPosition [_tx - 0.02, _ty - 0.025, 0.04, 0.05];
        _eye ctrlCommit 0;
        private _m = call _mouse;
        private _mx = (_m select 0) + _tremor * _zw * 0.08 * sin (_now * 600);
        private _my = (_m select 1) + _tremor * _zh * 0.08 * sin (_now * 510);
        _ret ctrlSetPosition [_mx - 0.02, _my - 0.025, 0.04, 0.05];
        _ret ctrlCommit 0;
        private _ok = ((abs (_mx - _tx)) < _zw * _tol) && {(abs (_my - _ty)) < _zh * _tol};
        if (_ok) then { _inside = _inside + _dt; };
        _eye ctrlSetTextColor ([[1, 0.6, 0.4, 0.9], [0.5, 1, 0.6, 0.95]] select _ok);
        [_inside / _dur * 0.9] call _setGauge;
        uiSleep 0.03;
    };
    private _spent = (diag_tickTime - _t0) max 0.1;
    private _q = ((_inside / _spent) * 1.25) min 1;
    if (_inside < _dur) then { _q = _q * 0.75; };
    ctrlDelete _eye;
    ctrlDelete _ret;
    _ctrls = _ctrls select { !isNull _x };
    _q max 0.25 min 1
};

// =====================================================================================================================
//  Déroulé
// =====================================================================================================================
if (!isNil "comspec_sse_fnc_bioAnimate" && {isNull objectParent player} && {!isNull _target}) then {
    [player, _target, _kind, "start"] call comspec_sse_fnc_bioAnimate;
};

// Câblage du lecteur : une fois par partie, ou si la batterie est basse (le câble ALIM la recharge).
private _lowBat = (["get"] call comspec_overwatch_connect_fnc_seekBattery) < 15;
if (_full && {!(missionNamespace getVariable ["COMSPEC_SeekFx_Cabled", false]) || _lowBat}) then {
    ["RACCORDER LE LECTEUR"] call _head1;
    private _n0 = count _ctrls;
    private _wired = call _cables;
    missionNamespace setVariable ["COMSPEC_SeekFx_Cabled", true];
    if (_wired && _lowBat) then {
        ["set", [((["get"] call comspec_overwatch_connect_fnc_seekBattery) + 25) min 100]] call comspec_overwatch_connect_fnc_seekBattery;
        _notes pushBackUnique "batterie rechargée par le câble ALIM";
    };
    if (!_wired) then { _notes pushBackUnique "câblage passé"; };
    for "_i" from ((count _ctrls) - 1) to _n0 step -1 do { ctrlDelete (_ctrls select _i); };
    _ctrls resize _n0;
};

private _avg = {
    params ["_list"];
    if (_list isEqualTo []) exitWith { 0 };
    private _sum = 0;
    { _sum = _sum + _x; } forEach _list;
    round (_sum / (count _list))
};
private _skipped = false;
switch (_kind) do {
    // ===================================================================== EMPREINTES : 4 + 4 + 2
    case "empreintes": {
        private _fingers = _res get "fingers";
        private _plan = [["MAIN DROITE · 4 DOIGTS", [1, 2, 3, 4]], ["MAIN GAUCHE · 4 DOIGTS", [6, 7, 8, 9]], ["POUCES · 2 DOIGTS", [0, 5]]];
        private _wet = [0, 0.1] select (rain > 0.2);
        {
            _x params ["_lab", "_idx"];
            if (call _alive) then {
                private _okIdx = _idx select { ((_hands select ([0, 1] select (_x >= 5))) select 0) isNotEqualTo "bloque" };
                private _bad = _idx - _okIdx;
                {
                    _fingers set [_x, -2];
                    private _tip = _disp displayCtrl (9610 + _x);
                    if (!isNull _tip) then { _tip ctrlSetBackgroundColor [0.85, 0.2, 0.2, 1]; };
                } forEach _bad;
                if (_bad isNotEqualTo []) then {
                    private _s = [0, 1] select ((_bad select 0) >= 5);
                    private _side = ["droite", "gauche"] select _s;
                    private _why = (_hands select _s) select 2;
                    _notes pushBackUnique format ["main %1 : non capturable (%2)", _side, _why];
                    [["Main %1 non capturable : %2. Doigts marqués en rouge.", _side, _why], "#ff9c8a"] call _say;
                    ["COMSPEC_ATAK_Disconnect"] call _snd;
                    [1.6] call _wait;
                };
                if (_okIdx isNotEqualTo []) then {
                    private _pen = 0;
                    { _pen = _pen max ((_hands select ([0, 1] select (_x >= 5))) select 1); } forEach _okIdx;
                    private _q = [_lab, _okIdx, 0.08 + _wet + ([0, 0.12] select (_pen > 0))] call _place;
                    if (uiNamespace getVariable ["COMSPEC_SeekFxSkip", false]) then { _skipped = true; };
                    {
                        private _h = _hands select ([0, 1] select (_x >= 5));
                        private _fq = round ((((_q * 100) + (random 12) - 6) - ((_h select 1) * 0.6)) max 5 min 100);
                        _fingers set [_x, _fq];
                        private _tip = _disp displayCtrl (9610 + _x);
                        if (!isNull _tip) then { _tip ctrlSetBackgroundColor ([[0.25, 0.75, 0.35, 1], [0.95, 0.6, 0.15, 1]] select (_fq < 60)); };
                        if ((_h select 0) isEqualTo "degrade") then {
                            _notes pushBackUnique format ["main %1 : qualité réduite (%2)", ["droite", "gauche"] select (_x >= 5), _h select 2];
                        };
                    } forEach _okIdx;
                };
            };
        } forEach _plan;
        private _good = _fingers select { _x >= 0 };
        _res set ["quality", [_good] call _avg];
        if ((count _good) == 0) then { _notes pushBackUnique "aucun doigt capturable"; };
    };

    // ===================================================================== IRIS : OD puis OG
    case "iris": {
        private _eyes = _res get "eyes";
        if ((_head select 0) isEqualTo "bloque") then {
            _eyes set [0, -2];
            _eyes set [1, -2];
            _notes pushBackUnique format ["iris non capturable (%1)", _head select 2];
            [["Iris non capturable : %1", _head select 2], "#ff9c8a"] call _say;
            [1.8] call _wait;
        } else {
            {
                if (call _alive) then {
                    call _resetGlass;
                    [_x] call _head1;
                    private _q = 0.8;
                    if (_full) then {
                        ["Gardez le réticule blanc sur l'œil, sans bouger la souris brusquement"] call _say;
                        _q = [3 + random 1] call _track;
                    } else {
                        ["Mise au point de l'objectif"] call _say;
                        _q = [2.2] call _auto;
                    };
                    if (uiNamespace getVariable ["COMSPEC_SeekFxSkip", false]) then { _skipped = true; };
                    _eyes set [_forEachIndex, round ((_q * 100 - (_head select 1) * 0.5) max 5 min 100)];
                    [1] call _setGauge;
                    ["COMSPEC_ATAK_Beep"] call _snd;
                    [0.5] call _wait;
                };
            } forEach ["IRIS · ŒIL DROIT (OD)", "IRIS · ŒIL GAUCHE (OG)"];
            if ((_head select 0) isEqualTo "degrade") then { _notes pushBackUnique format ["iris : qualité réduite (%1)", _head select 2]; };
            private _good = _eyes select { _x >= 0 };
            _res set ["quality", [_good] call _avg];
        };
    };
};
_res set ["skipped", _skipped];
if (_skipped) then { _notes pushBackUnique "geste passé"; };

call _resetGlass;
[] call _done;
uiNamespace setVariable ["COMSPEC_SeekFxBusy", false];
if (!isNil "comspec_sse_fnc_bioAnimate") then { [player, objNull, "", "stop"] call comspec_sse_fnc_bioAnimate; };
[_res] call _cb;
_res
