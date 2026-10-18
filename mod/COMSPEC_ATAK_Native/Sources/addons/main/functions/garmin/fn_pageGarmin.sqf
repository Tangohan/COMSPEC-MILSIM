/*
    App Garmin (montre connectée), dessinée comme l'application de la marque en thème sombre :
      ACCUEIL    : Body Battery du jour et sa courbe (stress orange, repos bleu, niveau en blanc), aperçu en cartes
                   (fréquence cardiaque, stress, sommeil / repos, pas), hier, 7 derniers jours ;
      ACTIVITÉS  : activités reconnues seules (course, marche, véhicule, vol, saut, bateau, nage) en cartes de couleur,
                   lieu, distance, temps, allure ou vitesse ;
      DÉFIS      : objectifs du jour et de la semaine avec leur avancement, objectif de pas réglable.
    Données de fn_garminTick (missionNamespace COMSPEC_ATAK_Gm, gardées sur ce PC).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _f = _l get "font";
private _pad = (_l get "pad") * 2;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _st getOrDefault ["gmTab", "HOME"];
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_Gm" }) then { ["tick"] call comspec_atak_native_fnc_garminTick; };
private _gm = missionNamespace getVariable ["COMSPEC_ATAK_Gm", createHashMap];
private _days = _gm getOrDefault ["days", createHashMap];
private _goal = _gm getOrDefault ["goal", 10000];

// --- Couleurs (thème sombre de l'app) ---
private _cBg = [0.065, 0.065, 0.065, 1];
private _cCard = [0.145, 0.145, 0.145, 1];
private _cLine = [0.22, 0.22, 0.22, 1];
private _rgb = {
    params ["_h", ["_a", 1]];
    private _d = "0123456789abcdef";
    private _s = toLower (_h select [1]);
    private _v = [];
    for "_i" from 0 to 4 step 2 do { _v pushBack ((((_d find (_s select [_i, 1])) max 0) * 16 + ((_d find (_s select [_i + 1, 1])) max 0)) / 255); };
    _v + [_a]
};
private _hBlue = "#4ea1f3"; private _hOrange = "#f07a1a"; private _hRed = "#f0566a"; private _hGreen = "#35b55a"; private _hPink = "#d42fc4"; private _hDim = "#9a9a9a";
private _data = "\z\comspec_atak_native\addons\main\data\";

// --- Briques de dessin (coordonnées de la zone de contenu, défilante) ---
private _box = { params ["_x", "_y", "_w", "_h", "_col"]; private _c = ["COMSPEC_RscText", [_x, _y, _w, _h]] call comspec_atak_native_fnc_pageCtrl; _c ctrlSetBackgroundColor _col; _c };
private _txt = { params ["_x", "_y", "_w", "_h", "_t"]; private _c = ["COMSPEC_RscStructuredText", [_x, _y, _w, _h]] call comspec_atak_native_fnc_pageCtrl; _c ctrlSetStructuredText parseText ((_t) call comspec_atak_native_fnc_tr); _c };
private _ico = { params ["_x", "_y", "_s", "_name", "_hex"]; private _c = ["COMSPEC_RscPicture", [_x, _y, _s, _s], _data + _name + ".paa"] call comspec_atak_native_fnc_pageCtrl; _c ctrlSetTextColor ([_hex] call _rgb); _c };
private _click = {
    params ["_rect", "_code", ["_inGroup", true]];
    private _b = ["COMSPEC_RscButtonOverlay", _rect, "", _inGroup] call comspec_atak_native_fnc_pageCtrl;
    _b setVariable ["comspec_click", _code];
    _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ [] call _this; }, _c getVariable ["comspec_click", {}]] call CBA_fnc_execNextFrame; }];
    _b
};
private _redraw = { [{ ['GARMIN'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };

// --- Mise en forme des nombres ---
private _thou = {
    params ["_n"];
    private _s = str (round _n);
    private _o = "";
    private _k = 0;
    for "_i" from ((count _s) - 1) to 0 step -1 do { _o = (_s select [_i, 1]) + _o; _k = _k + 1; if (_k mod 3 isEqualTo 0 && {_i > 0}) then { _o = " " + _o; }; };
    _o
};
private _km = { params ["_m", ["_dec", 2]]; [(_m / 1000) toFixed _dec, ".", ","] call CBA_fnc_replace };
private _two = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
private _hm = { params ["_s"]; private _m = floor (_s / 60); if (_m < 60) then { format ["%1m", _m] } else { format ["%1h %2m", floor (_m / 60), _m mod 60] } };
private _clock = { params ["_s"]; _s = round _s; private _h = floor (_s / 3600); if (_h > 0) then { format ["%1:%2:%3", _h, [floor ((_s mod 3600) / 60)] call _two, [_s mod 60] call _two] } else { format ["%1:%2", floor (_s / 60), [_s mod 60] call _two] } };

// --- Jours (clés AAAA-MM-JJ, date réelle du PC) ---
private _keyOf = { params ["_dt"]; _dt params ["_y", "_mo", "_d"]; format ["%1-%2-%3", _y, [_mo] call _two, [_d] call _two] };
private _ago = {
    params ["_n"];
    systemTime params ["_y", "_mo", "_d"];
    private _yl = [365, 366] select (_y mod 4 isEqualTo 0);
    private _fr = (dateToNumber [_y, _mo, _d, 12, 0]) - _n / _yl;
    if (_fr < 0) then { _y = _y - 1; _fr = _fr + 1; };
    [numberToDate [_y, _fr]] call _keyOf
};
private _today = [0] call _ago;
private _yest = [1] call _ago;
private _week = [6] call _ago;
private _dayOf = { params ["_k"]; _days getOrDefault [_k, createHashMap] };
private _stressAvg = { params ["_dh"]; round ((_dh getOrDefault ["stress_sum", 0]) / ((_dh getOrDefault ["stress_t", 0]) max 1)) };
private _restHr = { params ["_dh"]; private _r = _dh getOrDefault ["hr_rest", 999]; if (_r > 300) then { _gm getOrDefault ["rest_hr", 60] } else { round _r } };
// Clés de jour « AAAA-MM-JJ » comparées en nombre (SQF ne compare pas les chaînes avec < ou >).
private _kn = { parseNumber ((_this splitString "-") joinString "") };
private _weekKeys = (keys _days) select { private _n = _x call _kn; _n >= (_week call _kn) && {_n <= (_today call _kn)} };
private _acts = +(_gm getOrDefault ["acts", []]);
reverse _acts;

// Courbe du jour en 48 demi-heures : [points, somme du stress, somme du repos, dernier BB].
private _buckets = {
    params ["_ser"];
    private _b = [];
    for "_i" from 0 to 47 do { _b pushBack [0, 0, 0, -1]; };
    {
        _x params ["_h", "_bbv", "_sv", "", ["_sl", 0]];
        private _e = _b select ((floor (_h * 2)) min 47 max 0);
        _e set [0, (_e select 0) + 1]; _e set [1, (_e select 1) + _sv]; _e set [2, (_e select 2) + _sl]; _e set [3, _bbv];
    } forEach _ser;
    _b
};
private _todayB = [(([_today] call _dayOf) getOrDefault ["series", []])] call _buckets;

// Graphique en barres : "battery" (stress / repos + niveau BB), "stress", "sleep".
private _chart = {
    params ["_x", "_y", "_w", "_h", "_mode", "_b"];
    private _bw = _w / 48;
    [_x, _y + _h, _w, _f * 0.05, _cLine] call _box;
    {
        _x params ["_n", "_ss", "_sl", "_bbv"];
        if (_n > 0) then {
            private _bxi = (_this select 0) + _forEachIndex * _bw;
            private _sv = _ss / _n;
            private _rest = (_sl / _n) > 0.5;
            switch (_mode) do {
                case "sleep": {
                    if ((_sl / _n) > 0) then {
                        private _deep = (_sl / _n) > 0.85;
                        private _hh = _h * ([0.55, 0.85] select _deep);
                        [_bxi, _y + _h - _hh, _bw * 1.02, _hh, [[_hBlue] call _rgb, [_hPink] call _rgb] select _deep] call _box;
                    };
                };
                default {
                    private _hh = (_h * _sv / 100) max (_f * 0.08);
                    [_bxi + _bw * 0.1, _y + _h - _hh, _bw * 0.8, _hh, [[[_hOrange] call _rgb, [_hBlue] call _rgb] select (_rest || {_sv < 26}), [_hBlue] call _rgb] select (_mode isEqualTo "battery" && {_rest})] call _box;
                    if (_mode isEqualTo "battery" && {_bbv >= 0}) then {
                        private _ly = _y + _h * (1 - _bbv / 100);
                        [_bxi, _ly, _bw, (_y + _h) - _ly, [0.75, 0.75, 0.75, 0.16]] call _box;
                        [_bxi, _ly, _bw * 1.02, _f * 0.1, [1, 1, 1, 1]] call _box;
                    };
                };
            };
        };
    } forEach _b;
};

private _x0 = _pad;
private _W = _bw - 0.014 - 2 * _pad;
private _gap = _pad;
private _y = _pad;
private _tabH = _f * 2.7;
private _bg = [0, 0, _bw, _bh, _cBg] call _box;

// --- En-tête : indicatif, état de la montre, synchro ---
private _call = [player] call comspec_atak_native_fnc_unitCallsign;
private _syncAgo = round (diag_tickTime - (_gm getOrDefault ["tick", diag_tickTime]));
[_x0, _y, _f * 1.6, "app_garmin", "#ffffff"] call _ico;
[_x0 + _f * 2, _y - _f * 0.1, _W - _f * 6, _f * 2, format ["<t font='COMSPEC_BarlowBold' size='1.15'>%1</t><br/><t size='0.8' color='%2'>Montre synchronisée il y a %3 s</t>", [_call, name player] select (_call isEqualTo ""), _hDim, _syncAgo]] call _txt;
[_x0 + _W - _f * 1.7, _y, _f * 1.6, "g_sync", "#ffffff"] call _ico;
[[_x0 + _W - _f * 2, _y - _f * 0.2, _f * 2, _f * 2], { ["tick"] call comspec_atak_native_fnc_garminTick; ['GARMIN'] call comspec_atak_native_fnc_pageRender; }] call _click;
_y = _y + _f * 2.4;

// Titre de section + lien à droite.
private _head = {
    params ["_t", ["_link", ""], ["_code", {}]];
    [_x0, _y, _W * 0.7, _f * 1.8, format ["<t font='COMSPEC_BarlowBold' size='1.45'>%1</t>", _t]] call _txt;
    if (_link isNotEqualTo "") then {
        [_x0 + _W * 0.5, _y + _f * 0.3, _W * 0.5, _f * 1.4, format ["<t align='right' color='%1'>%2</t>", _hBlue, _link]] call _txt;
        [[_x0 + _W * 0.6, _y, _W * 0.4, _f * 1.8], _code] call _click;
    };
    _y = _y + _f * 2.1;
};
// Liste en carte : [icône, couleur, libellé, valeur, coché]
private _list = {
    params ["_rows"];
    private _rh = _f * 2.3;
    [_x0, _y, _W, _rh * (count _rows), _cCard] call _box;
    {
        _x params ["_icon", "_hex", "_lbl", "_val", ["_ok", false]];
        private _ry = _y + _forEachIndex * _rh;
        if (_forEachIndex > 0) then { [_x0, _ry, _W, _f * 0.05, _cLine] call _box; };
        [_x0 + _f * 0.6, _ry + _f * 0.55, _f * 1.2, _icon, _hex] call _ico;
        [_x0 + _f * 2.3, _ry + _f * 0.45, _W * 0.42, _f * 1.5, format ["<t size='1.05'>%1</t>", _lbl]] call _txt;
        [_x0 + _W * 0.48, _ry + _f * 0.45, _W * 0.45, _f * 1.5, format ["<t size='1.05'>%1</t>", _val]] call _txt;
        if (_ok) then { [_x0 + _W - _f * 1.9, _ry + _f * 0.5, _f * 1.3, "g_check", _hGreen] call _ico; };
    } forEach _rows;
    _y = _y + _rh * (count _rows) + _gap * 2;
};

switch (_tab) do {
    case "ACTS": {
        ["Activités"] call _head;
        private _cur = _gm getOrDefault ["cur", []];
        private _types = createHashMapFromArray [
            ["RUN", ["Course à pied", "g_run", "#e0821f", true]], ["WALK", ["Marche", "g_walk", "#c98a3a", true]],
            ["DRIVE", ["Trajet en véhicule", "g_drive", "#56697d", false]], ["FLIGHT", ["Vol", "g_flight", "#6f5fb8", false]],
            ["JUMP", ["Saut", "g_jump", "#1f9a9a", false]], ["BOAT", ["Bateau", "g_boat", "#3f8fdc", false]], ["SWIM", ["Nage", "g_swim", "#3f8fdc", true]]
        ];
        if (_cur isNotEqualTo []) then {
            _cur params ["_k", "_m", "_s"];
            [_x0, _y, _W, _f * 1.5, format ["<t color='%1'>● EN COURS</t>  <t color='%2'>%3 · %4 km · %5</t>", _hGreen, _hDim, toUpper _k, [_m] call _km, [_s] call _clock]] call _txt;
            _y = _y + _f * 1.8;
        };
        if ((count _acts) isEqualTo 0) then {
            [_x0, _y, _W, _f * 5, format ["<t align='center' color='%1'>Pas encore d'activité.<br/>Une course, une marche, un trajet, un vol ou un saut s'enregistre seul dès que vous bougez,<br/>et se termine après 90 s d'arrêt.</t>", _hDim]] call _txt;
            _y = _y + _f * 5.5;
        };
        private _lastDay = "";
        {
            private _a = _x;
            (_types getOrDefault [_a get "type", ["Activité", "g_run", "#56697d", false]]) params ["_tl", "_ti", "_th", "_pace"];
            private _dk = _a getOrDefault ["day", ""];
            if (_dk isNotEqualTo _lastDay) then {
                _lastDay = _dk;
                (_dk splitString "-") params [["_yy", ""], ["_mm", ""], ["_dd", ""]];
                [_x0, _y, _W, _f * 1.4, format ["<t color='%1'>• %2/%3/%4</t>", _hDim, _dd, _mm, _yy]] call _txt;
                _y = _y + _f * 1.5;
            };
            private _ch = _f * 4.6;
            [_x0, _y, _W, _ch, [_th] call _rgb] call _box;
            [_x0 + _W * 0.7, _y, _W * 0.3, _ch, [1, 1, 1, 0.06]] call _box;
            [_x0 + _f * 0.7, _y + _f * 0.55, _f * 1.1, _ti, "#ffffff"] call _ico;
            [_x0 + _f * 2.1, _y + _f * 0.4, _W - _f * 2.6, _f * 1.4, format ["<t size='1.05'>%1 %2</t><t size='0.8' color='#aaffffff'>  %3</t>", _a getOrDefault ["place", ""], _tl, _a getOrDefault ["at", ""]]] call _txt;
            private _m = _a getOrDefault ["m", 0];
            private _s = _a getOrDefault ["sec", 0];
            [_x0 + _f * 0.7, _y + _f * 1.8, _W * 0.42, _f * 2.6, format ["<t font='COMSPEC_BarlowBold' size='2.4'>%1</t><t size='1'> KM</t>", [_m] call _km]] call _txt;
            [_x0 + _W * 0.45, _y + _f * 2, _W * 0.25, _f * 2.4, format ["<t size='0.8'>TEMPS</t><br/><t size='1.35'>%1</t>", [_s] call _clock]] call _txt;
            private _pv = if (_pace) then { format ["<t size='0.8'>ALLURE</t><br/><t size='1.35'>%1 /km</t>", [(_s / ((_m / 1000) max 0.001)) min 5999] call _clock] } else { format ["<t size='0.8'>VITESSE</t><br/><t size='1.35'>%1 km/h</t>", round (3.6 * _m / (_s max 1))] };
            [_x0 + _W * 0.72, _y + _f * 2, _W * 0.28, _f * 2.4, _pv] call _txt;
            _y = _y + _ch + _gap * 2;
        } forEach _acts;
    };
    case "CHALLENGES": {
        ["Défis"] call _head;
        private _wk = { params ["_k"]; private _t = 0; { _t = _t + ((_days get _x) getOrDefault [_k, 0]); } forEach _weekKeys; _t };
        private _wActs = _acts select { ((_x getOrDefault ["day", "0"]) call _kn) >= (_week call _kn) };
        private _bestRun = 0; { if ((_x get "type") isEqualTo "RUN") then { _bestRun = _bestRun max (_x get "m"); }; } forEach _wActs;
        private _ch = [
            ["g_steps", _hBlue, "Objectif du jour", format ["%1 pas aujourd'hui", [_goal] call _thou], ([_today] call _dayOf) getOrDefault ["steps", 0], _goal, "steps"],
            ["g_steps", _hBlue, "100 000 pas", "en 7 jours", ["steps"] call _wk, 100000, "steps"],
            ["g_run", _hOrange, "Marathon de la semaine", "42,2 km à pied en 7 jours", ["foot_m"] call _wk, 42195, "km"],
            ["g_run", _hOrange, "Coureur", "3 courses en 7 jours", ["run_n"] call _wk, 3, "n"],
            ["g_run", _hOrange, "5 km d'une traite", "une course de 5 km ou plus en 7 jours", _bestRun, 5000, "km"],
            ["g_jump", "#1fb5b5", "Para", "un saut en 7 jours", { (_x get "type") isEqualTo "JUMP" } count _wActs, 1, "n"],
            ["g_battery", _hBlue, "Récupération", "Body Battery à 80 ou plus", _gm getOrDefault ["bb", 0], 80, "n"]
        ];
        {
            _x params ["_icon", "_hex", "_t", "_sub", "_v", "_target", "_unit"];
            private _done = _v >= _target;
            private _cardH = _f * 3.6;
            [_x0, _y, _W, _cardH, _cCard] call _box;
            [_x0 + _f * 0.4, _y + _f * 0.6, _f * 2.2, _f * 2.2, [0.24, 0.24, 0.24, 1]] call _box;
            [_x0 + _f * 0.75, _y + _f * 0.95, _f * 1.5, [_icon, "g_laurel"] select _done, [_hex, "#f2c94c"] select _done] call _ico;
            private _vt = switch (_unit) do { case "km": { format ["%1 / %2 km", [_v, 1] call _km, [_target, 1] call _km] }; case "steps": { format ["%1 / %2", [_v] call _thou, [_target] call _thou] }; default { format ["%1 / %2", round _v, _target] } };
            [_x0 + _f * 3.2, _y + _f * 0.35, _W - _f * 3.6, _f * 1.5, format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='%2'>%3</t>", _t, _hDim, _sub]] call _txt;
            [_x0 + _f * 3.2, _y + _f * 1.75, _W - _f * 3.6, _f * 1.3, format ["<t size='0.9' color='%1'>%2</t>%3", [_hDim, _hGreen] select _done, _vt, ["", "  <t size='0.9' color='#35b55a'>RELEVÉ</t>"] select _done]] call _txt;
            private _pw = _W - _f * 4;
            [_x0 + _f * 3.2, _y + _f * 3, _pw, _f * 0.25, [0.27, 0.27, 0.27, 1]] call _box;
            [_x0 + _f * 3.2, _y + _f * 3, _pw * ((_v / _target) min 1) max (_f * 0.05), _f * 0.25, [[_hex] call _rgb, [_hGreen] call _rgb] select _done] call _box;
            _y = _y + _cardH + _gap;
        } forEach _ch;
        _y = _y + _gap;
        ["Objectif de pas"] call _head;
        private _opts = [6000, 8000, 10000, 12000, 15000];
        private _ow = (_W - _gap * 4) / 5;
        {
            private _on = _x isEqualTo _goal;
            private _r = [_x0 + _forEachIndex * (_ow + _gap), _y, _ow, _f * 1.8];
            [_r select 0, _r select 1, _r select 2, _r select 3, [_cCard, [_hBlue] call _rgb] select _on] call _box;
            [_r select 0, (_r select 1) + _f * 0.25, _r select 2, _f * 1.4, format ["<t align='center'>%1</t>", [_x] call _thou]] call _txt;
            [_r, compile format ["(missionNamespace getVariable ['COMSPEC_ATAK_Gm', createHashMap]) set ['goal', %1]; ['GARMIN'] call comspec_atak_native_fnc_pageRender;", _x]] call _click;
        } forEach _opts;
        _y = _y + _f * 2.4;
    };
    default {
        // --- Body Battery ---
        private _bbv = round (_gm getOrDefault ["bb", 75]);
        private _td = [_today] call _dayOf;
        private _cardH = _f * 13;
        [_x0, _y, _W, _cardH, _cCard] call _box;
        [_x0 + _f * 0.6, _y + _f * 0.6, _f * 1.4, "g_battery", _hBlue] call _ico;
        [_x0 + _f * 2.4, _y + _f * 0.6, _W * 0.5, _f * 1.5, "<t size='1.05'>Body Battery</t>"] call _txt;
        [_x0 + _f * 0.6, _y + _f * 2.2, _W * 0.4, _f * 3.4, format ["<t size='3.2'>%1</t>", _bbv]] call _txt;
        [_x0 + _W * 0.45, _y + _f * 2.4, _W * 0.25, _f * 2.6, format ["<t size='1.4'>+%1</t><br/><t color='%2'>Chargée</t>", round (_td getOrDefault ["bb_up", 0]), _hDim]] call _txt;
        [_x0 + _W * 0.7, _y + _f * 2.4, _W * 0.3, _f * 2.6, format ["<t size='1.4'>-%1</t><br/><t color='%2'>Énergie dépensée</t>", round (_td getOrDefault ["bb_down", 0]), _hDim]] call _txt;
        [_x0 + _f * 0.8, _y + _f * 6, _W - _f * 1.6, _f * 5.2, "battery", _todayB] call _chart;
        systemTime params ["", "", "", "_hNow", "_mNow"];
        [_x0 + _f * 0.6, _y + _f * 11.4, _W * 0.3, _f * 1.3, format ["<t color='%1'>00 h</t>", _hDim]] call _txt;
        [_x0 + _W * 0.3, _y + _f * 11.4, _W * 0.7 - _f * 0.6, _f * 1.3, format ["<t align='right' color='%1'>%2:%3 · 24 h</t>", _hDim, [_hNow] call _two, [_mNow] call _two]] call _txt;
        _y = _y + _cardH + _gap;
        (_gm getOrDefault ["away", [0, 0]]) params ["_away", "_gain"];
        if (_gain >= 1) then {
            [_x0, _y, _W, _f * 1.4, format ["<t size='0.85' color='%1'>+%2 rechargés pendant %3 hors du jeu</t>", _hDim, round _gain, [_away] call _hm]] call _txt;
            _y = _y + _f * 1.6;
        };
        _y = _y + _gap;

        // --- Aperçu : 4 cartes ---
        ["Aperçu"] call _head;
        private _cw = (_W - _gap) / 2;
        private _ch = _f * 9.5;
        private _card = { params ["_col", "_row", "_icon", "_hex", "_t"]; private _cx = _x0 + _col * (_cw + _gap); private _cy = _y + _row * (_ch + _gap); [_cx, _cy, _cw, _ch, _cCard] call _box; [_cx + _f * 0.6, _cy + _f * 0.6, _f * 1.3, _icon, _hex] call _ico; [_cx + _f * 2.3, _cy + _f * 0.55, _cw - _f * 2.6, _f * 1.5, format ["<t size='1.0'>%1</t>", _t]] call _txt; [_cx, _cy] };
        // Fréquence cardiaque : valeur, zones, repos.
        private _hr = round (_gm getOrDefault ["hr", 60]);
        ([0, 0, "g_heart", _hRed, "Fréquence cardiaque"] call _card) params ["_cx", "_cy"];
        [_cx + _f * 0.6, _cy + _f * 2.4, _cw - _f * 1.2, _f * 3, format ["<t align='center' size='2.6'>%1</t><t size='0.9' color='%2'> bpm</t>", _hr, _hDim]] call _txt;
        private _zw = (_cw - _f * 1.6) / 5;
        { [_cx + _f * 0.8 + _forEachIndex * _zw, _cy + _f * 5.7, _zw * 0.94, _f * 0.3, [_x] call _rgb] call _box; } forEach ["#6b6b6b", _hBlue, _hGreen, _hOrange, "#e53935"];
        private _zx = _cx + _f * 0.8 + (_cw - _f * 1.6) * ((_hr - 40) / 160 max 0 min 1);
        [_zx - _f * 0.15, _cy + _f * 5.45, _f * 0.3, _f * 0.8, [1, 1, 1, 1]] call _box;
        [_cx + _f * 0.6, _cy + _f * 6.6, _cw - _f * 1.2, _f * 2.6, format ["<t size='1.5'>%1 bpm</t><br/><t color='%2'>Repos</t>", [_td] call _restHr, _hDim]] call _txt;
        // Stress : valeur, part calme / stressée du jour, courbe.
        private _sv = round (_gm getOrDefault ["stress", 0]);
        ([1, 0, "g_stress", _hOrange, "Stress"] call _card) params ["_cx", "_cy"];
        [_cx + _f * 0.6, _cy + _f * 2.4, _cw - _f * 1.2, _f * 3, format ["<t align='center' size='2.6'>%1</t>", _sv]] call _txt;
        private _cnt = 0; private _hi = 0; { if ((_x select 0) > 0) then { _cnt = _cnt + 1; if (((_x select 1) / (_x select 0)) >= 26) then { _hi = _hi + 1; }; }; } forEach _todayB;
        private _hiW = (_cw - _f * 1.6) * (_hi / (_cnt max 1));
        [_cx + _f * 0.8, _cy + _f * 5.6, _cw - _f * 1.6, _f * 0.3, [_hBlue] call _rgb] call _box;
        if (_hiW > 0) then { [_cx + _f * 0.8, _cy + _f * 5.6, _hiW, _f * 0.3, [_hOrange] call _rgb] call _box; };
        [_cx + _f * 0.8, _cy + _f * 6.3, _cw - _f * 1.6, _f * 2.3, "stress", _todayB] call _chart;
        // Sommeil / repos : durée, phases.
        ([0, 1, "g_sleep", _hBlue, "Sommeil"] call _card) params ["_cx", "_cy"];
        [_cx + _f * 0.6, _cy + _f * 2.2, _cw - _f * 1.2, _f * 2.6, format ["<t size='1.6'>%1</t><br/><t color='%2'>Durée (repos et inconscience)</t>", [_td getOrDefault ["sleep_s", 0]] call _hm, _hDim]] call _txt;
        [_cx + _f * 0.8, _cy + _f * 5.2, _cw - _f * 1.6, _f * 3.4, "sleep", _todayB] call _chart;
        // Pas : du jour, objectif, distance.
        private _steps = _td getOrDefault ["steps", 0];
        ([1, 1, "g_steps", _hBlue, "Pas"] call _card) params ["_cx", "_cy"];
        [_cx + _f * 0.6, _cy + _f * 2.4, _cw - _f * 1.2, _f * 3, format ["<t size='2.3'>%1</t>", [_steps] call _thou]] call _txt;
        [_cx + _f * 0.8, _cy + _f * 5.6, _cw - _f * 1.6, _f * 0.3, [0.27, 0.27, 0.27, 1]] call _box;
        [_cx + _f * 0.8, _cy + _f * 5.6, (_cw - _f * 1.6) * ((_steps / _goal) min 1) max (_f * 0.05), _f * 0.3, [[_hBlue] call _rgb, [_hGreen] call _rgb] select (_steps >= _goal)] call _box;
        [_cx + _f * 0.6, _cy + _f * 6.3, _cw - _f * 1.2, _f * 2.6, format ["<t color='%1'>Objectif %2</t><br/><t size='1.2'>%3 km</t>", _hDim, [_goal] call _thou, [_td getOrDefault ["foot_m", 0]] call _km]] call _txt;
        _y = _y + 2 * (_ch + _gap) + _gap;

        // --- Hier ---
        private _yd = [_yest] call _dayOf;
        ["Hier"] call _head;
        if ((count _yd) isEqualTo 0) then {
            [_x0, _y, _W, _f * 1.6, format ["<t color='%1'>Pas de partie hier.</t>", _hDim]] call _txt;
            _y = _y + _f * 2.2;
        } else {
            [[
                ["g_battery", _hBlue, "Body Battery", format ["+%1 / -%2", round (_yd getOrDefault ["bb_up", 0]), round (_yd getOrDefault ["bb_down", 0])]],
                ["g_steps", _hBlue, "Pas", [_yd getOrDefault ["steps", 0]] call _thou, (_yd getOrDefault ["steps", 0]) >= _goal],
                ["g_heart", _hRed, "Fréquence cardiaque", format ["%1 Repos / %2 + haut", [_yd] call _restHr, round (_yd getOrDefault ["hr_max", 0])]],
                ["g_stress", _hOrange, "Stress", str ([_yd] call _stressAvg)],
                ["g_sleep", _hBlue, "Sommeil", [_yd getOrDefault ["sleep_s", 0]] call _hm]
            ]] call _list;
        };

        // --- 7 derniers jours ---
        ["7 derniers jours"] call _head;
        private _n = (count _weekKeys) max 1;
        private _sum = { params ["_k"]; private _t = 0; { _t = _t + ((_days get _x) getOrDefault [_k, 0]); } forEach _weekKeys; _t };
        private _hrSum = 0; { _hrSum = _hrSum + ([_days get _x] call _restHr); } forEach _weekKeys;
        private _stSum = 0; { _stSum = _stSum + ([_days get _x] call _stressAvg); } forEach _weekKeys;
        [[
            ["g_run", _hOrange, format ["%1 course(s)", ["run_n"] call _sum], format ["%1 km", [["run_m"] call _sum, 1] call _km]],
            ["g_steps", _hBlue, "Pas", format ["%1 Moyenne", [(["steps"] call _sum) / _n] call _thou]],
            ["g_heart", _hRed, "Fréquence cardiaque", format ["%1 Repos moyen", round (_hrSum / _n)]],
            ["g_stress", _hOrange, "Stress", format ["%1 Moyenne", round (_stSum / _n)]],
            ["g_sleep", _hBlue, "Sommeil", format ["%1 moyenne", [(["sleep_s"] call _sum) / _n] call _hm]]
        ]] call _list;
        [_x0, _y, _W, _f * 2.4, format ["<t size='0.8' color='%1'>Mesures de la montre portée en jeu, gardées sur ce PC (14 jours). Fréquence cardiaque d'ACE quand le mod est chargé, sinon estimée d'après l'effort, la fatigue et le stress.</t>", _hDim]] call _txt;
        _y = _y + _f * 2.6;
        // Envoi au site (fiche du membre), toutes les 5 min.
        (_gm getOrDefault ["web", [-1, ""]]) params ["_wt", "_ws"];
        private _web = switch (true) do {
            case (_wt < 0): { "Envoi au site : dans les 5 premières minutes de jeu" };
            case (_ws isEqualTo "OK"): { format ["Envoyé au site il y a %1 min (fiche du membre)", floor ((diag_tickTime - _wt) / 60)] };
            case (_ws isEqualTo "SANS LIAISON"): { "Pas envoyé au site : téléphone non relié à Athena" };
            default { format ["Pas envoyé au site : %1", _ws] };
        };
        [_x0, _y, _W, _f * 1.3, format ["<t size='0.8' color='%1'>%2</t>", _hDim, _web]] call _txt;
        _y = _y + _f * 1.5;
    };
};

// Fond étendu à toute la hauteur dessinée (plus la barre d'onglets), pour défiler jusqu'en bas.
_y = _y + _tabH + _gap;
_bg ctrlSetPosition [0, 0, _bw, _y max _bh];
_bg ctrlCommit 0;

// --- Barre d'onglets, fixe en bas ---
private _ty = _by + _bh - _tabH;
private _tb = ["COMSPEC_RscText", [_bx, _ty, _bw, _tabH], "", false] call comspec_atak_native_fnc_pageCtrl;
_tb ctrlSetBackgroundColor [0.04, 0.04, 0.04, 0.98];
private _tabs = [["HOME", "Accueil", "g_home"], ["CHALLENGES", "Défis", "g_laurel"], ["ACTS", "Activités", "g_list"]];
private _tw = _bw / (count _tabs);
{
    _x params ["_k", "_lbl", "_icon"];
    private _on = _k isEqualTo _tab;
    private _hex = ["#c8c8c8", "#4ea1f3"] select _on;
    private _tx = _bx + _forEachIndex * _tw;
    private _p = ["COMSPEC_RscPicture", [_tx + (_tw - _f * 1.3) / 2, _ty + _f * 0.25, _f * 1.3, _f * 1.3], _data + _icon + ".paa", false] call comspec_atak_native_fnc_pageCtrl;
    _p ctrlSetTextColor ([_hex] call _rgb);
    private _c = ["COMSPEC_RscStructuredText", [_tx, _ty + _f * 1.55, _tw, _f * 1.1], "", false] call comspec_atak_native_fnc_pageCtrl;
    _c ctrlSetStructuredText parseText ((format ["<t align='center' size='0.8' color='%1'>%2</t>", _hex, _lbl]) call comspec_atak_native_fnc_tr);
    [[_tx, _ty, _tw, _tabH], compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['gmTab', '%1']; ['GARMIN'] call comspec_atak_native_fnc_pageRender;", _k], false] call _click;
} forEach _tabs;
true
