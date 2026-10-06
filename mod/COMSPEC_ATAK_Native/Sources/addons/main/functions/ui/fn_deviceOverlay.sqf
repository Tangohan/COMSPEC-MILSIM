/*
    Dégâts visibles sur l'écran (textures générées par tools/gen_assets.py) :
      fêlé 1-2 : toile d'araignée (crack_<n>_<v>), taches d'encre dès le niveau 2, quelques lignes mortes fixes ;
      fêlé 3   : grandes zones mortes, lignes mortes qui scintillent (glitch_<v>_<trame>) ;
      détruit  : verre noir éclaté (shatter_<v>) ; éteint : écran noir, puis écran de démarrage COMSPEC ATAK
                 (boot_<port|land>, barre de progression, étapes, version) pendant les 5 dernières secondes.
    Zones mortes tactiles (fêlé 3) : là où l'encre a noirci l'écran (rectangles relevés sur les textures crack_3_<v>),
    les clics ne passent plus. Coupures noires de plus en plus fréquentes avec les dégâts.
    La variante est tirée au premier choc et gardée (COMSPEC_ATAK_DevLook) : la toile grandit depuis le même impact,
    les lignes mortes changent à chaque nouveau choc, tout est oublié à la réparation.
    Mode nuit (profil COMSPEC_ATAK_NightMode) : filtre rouge (RED) ou écran assombri (DIM) par-dessus le contenu.
    Contrôles de page (hors zone de contenu) recréés à chaque rendu pour rester au-dessus ; mis à jour chaque seconde.
    Par-dessus les textures : fêlures procédurales (éclats en étoile autour d'un ou deux impacts, graine tirée au
    premier choc), différentes d'un appareil et d'un choc à l'autre.
    Premier plan : Arma dessine le contrôle qui a le focus (zone de contenu après un clic) devant tout le reste ;
    tant qu'un effet est affiché, le focus est rendu au bouton invisible hors écran (sauf saisie de texte ou liste
    déroulante) pour que les fêlures, le filtre de nuit et les écrans d'arrêt / démarrage restent devant l'app.
    Écran éteint ou animation d'alimentation : la zone de contenu (fenêtres d'app) est masquée.
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _rect = _l get "device";
(uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]) params [["_crack", controlNull], ["_black", controlNull], ["_txt", controlNull], ["_block", controlNull], ["_tint", controlNull], ["_glitch", controlNull], ["_shatter", controlNull], ["_boot", controlNull], ["_barBg", controlNull], ["_bar", controlNull], ["_bootTxt", controlNull], ["_dead", []], ["_deadSig", ""], ["_lines", []], ["_lineSig", ""]];
if (isNull _crack || {isNull _tint} || {isNull _glitch} || {isNull _shatter} || {isNull _boot}) then {
    _tint = ["COMSPEC_RscText", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _tint ctrlEnable false;
    _crack = ["COMSPEC_RscPhone", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _crack ctrlEnable false;
    _glitch = ["COMSPEC_RscPhone", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _glitch ctrlEnable false;
    _shatter = ["COMSPEC_RscPhone", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _shatter ctrlEnable false;
    _black = ["COMSPEC_RscText", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _black ctrlSetBackgroundColor [0, 0, 0, 1];
    _black ctrlEnable false;
    _txt = ["COMSPEC_RscStructuredText", [_rect select 0, (_rect select 1) + (_rect select 3) * 0.4, _rect select 2, (_rect select 3) * 0.3], "", false] call comspec_atak_native_fnc_pageCtrl;
    _txt ctrlEnable false;
    // Écran de démarrage : image, barre de progression (sa largeur glisse d'une seconde à l'autre), étape et version.
    _rect params ["_rx", "_ry", "_rw", "_rh"];
    private _land = _l get "landscape";
    _boot = ["COMSPEC_RscPhone", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _boot ctrlEnable false;
    private _by = _ry + _rh * ([0.72, 0.855] select _land);
    private _bh = _rh * ([0.006, 0.012] select _land);
    _barBg = ["COMSPEC_RscText", [_rx + _rw * 0.2, _by, _rw * 0.6, _bh], "", false] call comspec_atak_native_fnc_pageCtrl;
    _barBg ctrlSetBackgroundColor [0.16, 0.24, 0.20, 1];
    _barBg ctrlEnable false;
    _bar = ["COMSPEC_RscText", [_rx + _rw * 0.2, _by, 0, _bh], "", false] call comspec_atak_native_fnc_pageCtrl;
    _bar ctrlSetBackgroundColor [0.36, 0.78, 0.42, 1];
    _bar ctrlEnable false;
    _bootTxt = ["COMSPEC_RscStructuredText", [_rx, _by + _bh * 2.5, _rw, _rh * ([0.12, 0.13] select _land)], "", false] call comspec_atak_native_fnc_pageCtrl;
    _bootTxt ctrlEnable false;
    _dead = [];
    _deadSig = "";
    { ctrlDelete _x; } forEach _lines;
    _lines = [];
    _lineSig = "";
    // Écran éteint ou détruit : les clics ne passent plus. Détruit : un appui fait seulement sauter l'image.
    _block = ["COMSPEC_RscButtonOverlay", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    _block ctrlAddEventHandler ["ButtonClick", {
        if ((([] call comspec_atak_native_fnc_deviceHealth) get "state") isNotEqualTo "BROKEN") exitWith {};
        private _b = (uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]) param [1, controlNull];
        if (isNull _b) exitWith {};
        _b ctrlShow true; _b ctrlSetFade (random 0.3); _b ctrlCommit 0;
        _b ctrlSetFade 1; _b ctrlCommit (0.1 + random 0.25);
        private _g = (uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]) param [5, controlNull];
        if (!isNull _g) then { _g ctrlSetFade 0; _g ctrlCommit 0; _g ctrlSetFade 0.5; _g ctrlCommit 0.4; };
        playSound "ClickSoft";
    }];
};
switch (profileNamespace getVariable ["COMSPEC_ATAK_NightMode", "OFF"]) do {
    case "RED": { _tint ctrlShow true; _tint ctrlSetBackgroundColor [0.55, 0, 0, 0.42]; };
    case "DIM": { _tint ctrlShow true; _tint ctrlSetBackgroundColor [0, 0, 0, 0.58]; };
    default { _tint ctrlShow false; };
};
private _state = _hp get "state";
private _lvl = _hp get "crack";
private _dmg = _hp get "damage";
private _broken = _state isEqualTo "BROKEN";
private _ori = ["port", "land"] select (_l get "landscape");
private _tex = { format ["\z\comspec_atak_native\addons\main\data\%1_%2.paa", _this, _ori] };
// Variantes (3 fêlures, 2 lignes mortes à 3 trames, 2 verres brisés) : tirées une fois par choc, pas à chaque rendu.
private _look = uiNamespace getVariable ["COMSPEC_ATAK_DevLook", createHashMap];
if (_lvl isEqualTo 0 && {!_broken}) then {
    _look = createHashMap;
} else {
    if !("crackVar" in _look) then { _look set ["crackVar", floor random 3]; };
    if !("seed" in _look) then { _look set ["seed", floor random 10000]; };
    if (_lvl >= 2 && {_dmg > (_look getOrDefault ["damage", -1])}) then { _look set ["glitchVar", floor random 2]; };
    if (_broken && {!("shatterVar" in _look)}) then { _look set ["shatterVar", floor random 2]; };
    _look set ["damage", _dmg];
};
uiNamespace setVariable ["COMSPEC_ATAK_DevLook", _look];
// Fêlures et encre : partiellement transparentes aux niveaux 1-2 pour que l'écran reste utilisable.
private _showCrack = _lvl > 0 && {!_broken};
_crack ctrlShow _showCrack;
if (_showCrack) then {
    _crack ctrlSetText (format ["crack_%1_%2", _lvl, _look get "crackVar"] call _tex);
    _crack ctrlSetTextColor [1, 1, 1, [0.85, 0.9, 1] select (_lvl - 1)];
};
// Verre noir éclaté : appareil détruit.
_shatter ctrlShow _broken;
if (_broken) then { _shatter ctrlSetText (format ["shatter_%1", _look get "shatterVar"] call _tex); };
// Lignes mortes : fixes et discrètes au niveau 2, scintillantes (changement de trame ~1 fois/s) quand c'est grave.
private _showGlitch = (_lvl >= 2 || _broken) && {_state isNotEqualTo "OFF"};
_glitch ctrlShow _showGlitch;
if (_showGlitch) then {
    private _gv = _look getOrDefault ["glitchVar", 0];
    if (_lvl >= 3 || {_dmg >= 0.85} || _broken) then {
        private _frame = ((_look getOrDefault ["frame", 0]) + 1 + floor random 2) mod 3;
        _look set ["frame", _frame];
        _glitch ctrlSetText (format ["glitch_%1_%2", _gv, _frame] call _tex);
        _glitch ctrlSetTextColor [1, 1, 1, [0.9, 0.55] select _broken];
        // Battement : la trame apparaît d'un coup puis pâlit avant la suivante ; parfois l'écran « décroche ».
        _glitch ctrlSetFade ([0, 0.85] select (random 1 < 0.15)); _glitch ctrlCommit 0;
        _glitch ctrlSetFade (0.25 + random 0.45); _glitch ctrlCommit (0.5 + random 0.4);
    } else {
        _glitch ctrlSetText (format ["glitch_%1_0", _gv] call _tex);
        _glitch ctrlSetTextColor [1, 1, 1, 0.35];
        _glitch ctrlSetFade 0; _glitch ctrlCommit 0;
    };
};
private _fx = uiNamespace getVariable ["COMSPEC_ATAK_PowerFx", []];
private _off = _state isEqualTo "OFF" && {!((_fx param [0, ""]) isEqualTo "shutdown" && {(_fx param [1, -1]) > diag_tickTime})};
_block ctrlShow (_off || _broken);
_block ctrlSetTooltip (["Téléphone éteint", "Téléphone détruit"] select _broken);
// Démarrage : les 5 dernières secondes de l'extinction (ou toute la durée si elle est plus courte).
private _left = ((_hp getOrDefault ["offUntil", -1]) - time) max 0;
private _total = [5, ((_hp getOrDefault ["offUntil", -1]) - (_hp getOrDefault ["offFrom", -1])) max 1] select ((_hp getOrDefault ["offFrom", -1]) >= 0);
private _bootLen = 5 min _total;
private _booting = _off && {(_hp get "reason") isNotEqualTo "Batterie vide"} && {(_hp getOrDefault ["offUntil", -1]) > 0} && {_left <= _bootLen};
{ _x ctrlShow _booting; } forEach [_boot, _barBg, _bar, _bootTxt];
_txt ctrlShow (_off && !_booting);
if (_off) then {
    _black ctrlShow true; _black ctrlSetFade 0; _black ctrlCommit 0;
    if (_booting) then {
        private _p = (1 - _left / _bootLen) max 0 min 1;
        _boot ctrlSetText ("boot" call _tex);
        // La barre rejoint la valeur de la seconde suivante en une seconde : progression continue.
        (ctrlPosition _barBg) params ["_bx", "_by", "_bw", "_bh"];
        _bar ctrlSetPosition [_bx, _by, _bw * _p, _bh]; _bar ctrlCommit 0;
        _bar ctrlSetPosition [_bx, _by, _bw * ((_p + 1 / _bootLen) min 1), _bh]; _bar ctrlCommit 1;
        private _step = switch (true) do {
            case (_p < 0.2): { "Démarrage…" };
            case (_p < 0.45): { "Vérification du matériel" };
            case (_p < 0.7): { "Chargement des cartes et des calques" };
            case (_p < 0.9): { "Recherche du réseau" };
            default { "Ouverture de COMSPEC ATAK" };
        };
        _bootTxt ctrlSetStructuredText parseText format ["<t align='center' size='0.85' color='#c9d4cf'>%1</t><br/><t align='center' size='0.7' color='#5f6f68' font='EtelkaMonospacePro'>COMSPEC ATAK v%2 · %3</t>",
            _step, missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "1.6.0"], _hp get "reason"];
    } else {
        _txt ctrlSetStructuredText parseText format ["<t align='center' size='1.2' color='#5f6f68' font='RobotoCondensedBold'>ÉCRAN ÉTEINT</t><br/><t align='center' color='#c9d4cf'>%1</t><br/><t align='center' size='0.85' color='#8a9a93'>%2</t>", _hp get "reason", switch (true) do {
            case ((_hp get "reason") isEqualTo "Batterie vide"): { "Rechargez en véhicule ou changez la batterie" };
            case ((_hp get "offLeft") > 0): { format ["Redémarrage dans %1 s", _hp get "offLeft"] };
            default { "Redémarrage en cours…" };
        }];
    };
} else {
    // Écran abîmé : il saute de temps en temps, d'autant plus souvent qu'il est touché.
    private _flick = [0, 0.04 + 0.3 * ((_dmg - 0.4) max 0)] select (_lvl >= 2 || _broken);
    if (random 1 < _flick) then {
        _black ctrlShow true; _black ctrlSetFade ([0.1, 0] select (_lvl >= 3)); _black ctrlCommit 0;
        _black ctrlSetFade 1; _black ctrlCommit (0.15 + random ([0.3, 0.6] select (_lvl >= 3)));
    } else {
        if (ctrlFade _black >= 1 || {!ctrlShown _black}) then { _black ctrlShow false; } else { _black ctrlSetFade 1; _black ctrlCommit 0.3; };
    };
};
// Zones mortes tactiles (fêlé 3) : rectangles portrait, tournés comme la texture en paysage ((x, y) -> (1 - y, x)).
private _zones = if (_lvl >= 3 && {!_broken} && {!_off}) then {
    [
        [[0.625, 0, 0.375, 0.0625], [0.75, 0.0625, 0.25, 0.0625], [0, 0.25, 0.25, 0.1875], [0.625, 0.25, 0.125, 0.0625], [0.875, 0.25, 0.125, 0.0625], [0.75, 0.3125, 0.25, 0.0625], [0.75, 0.375, 0.125, 0.0625], [0, 0.4375, 0.375, 0.0625], [0, 0.5, 0.5, 0.0625], [0, 0.5625, 0.375, 0.0625], [0, 0.625, 0.25, 0.0625], [0, 0.6875, 0.375, 0.0625], [0, 0.75, 0.25, 0.0625], [0.75, 0.75, 0.125, 0.0625], [0, 0.8125, 0.125, 0.0625], [0, 0.875, 0.25, 0.0625], [0, 0.9375, 0.125, 0.0625]],
        [[0.875, 0, 0.125, 0.0625], [0.75, 0.0625, 0.25, 0.0625], [0.375, 0.125, 0.25, 0.0625], [0.875, 0.125, 0.125, 0.1875], [0.5, 0.1875, 0.125, 0.0625], [0.75, 0.3125, 0.25, 0.25], [0, 0.5625, 1, 0.0625], [0.75, 0.6875, 0.25, 0.0625], [0.875, 0.75, 0.125, 0.25], [0.25, 0.8125, 0.25, 0.0625], [0.25, 0.875, 0.125, 0.0625]],
        [[0.375, 0, 0.125, 0.125], [0.375, 0.125, 0.375, 0.0625], [0, 0.1875, 0.25, 0.125], [0.5, 0.1875, 0.5, 0.0625], [0.625, 0.25, 0.375, 0.125], [0.625, 0.375, 0.125, 0.0625], [0.875, 0.375, 0.125, 0.0625], [0.75, 0.4375, 0.25, 0.1875], [0.125, 0.5625, 0.125, 0.0625], [0, 0.625, 1, 0.0625], [0.25, 0.6875, 0.25, 0.0625], [0.75, 0.6875, 0.25, 0.125]]
    ] param [_look getOrDefault ["crackVar", 0], []]
} else { [] };
private _sig = format ["%1|%2|%3", _zones isNotEqualTo [], _look getOrDefault ["crackVar", -1], _ori];
if (_sig isNotEqualTo _deadSig) then {
    { ctrlDelete _x; } forEach _dead;
    _dead = [];
    _rect params ["_rx", "_ry", "_rw", "_rh"];
    {
        _x params ["_fx", "_fy", "_fw", "_fh"];
        if (_ori isEqualTo "land") then { private _t = _fx; _fx = 1 - _fy - _fh; _fy = _t; _t = _fw; _fw = _fh; _fh = _t; };
        private _z = ["COMSPEC_RscButtonInvisible", [_rx + _rw * _fx, _ry + _rh * _fy, _rw * _fw, _rh * _fh], "", false] call comspec_atak_native_fnc_pageCtrl;
        _z ctrlSetTooltip "Zone morte : l'écran ne réagit plus ici";
        _dead pushBack _z;
    } forEach _zones;
    _deadSig = _sig;
};
// Fêlures procédurales : éclats en étoile (rayons brisés, ramifications) autour d'un impact, deux quand c'est grave.
// Graine tirée au premier choc (COMSPEC_ATAK_DevLook) : même dessin d'une seconde à l'autre, nouveau après réparation.
private _fxOn = ((_fx param [1, -1]) > diag_tickTime);
private _lsig = format ["%1|%2|%3|%4|%5", _look getOrDefault ["seed", -1], _lvl, _broken, _ori, _rect];
if (_lsig isNotEqualTo _lineSig) then {
    { ctrlDelete _x; } forEach _lines;
    _lines = [];
    _lineSig = _lsig;
    if (_lvl > 0 || _broken) then {
        _rect params ["_rx", "_ry", "_rw", "_rh"];
        private _ratio = pixelH / pixelW;
        private _seed = _look getOrDefault ["seed", 0];
        private _n = 0;
        // Pseudo-aléatoire reproductible (même graine, même dessin).
        private _rnd = { _n = _n + 1; private _v = (sin (_seed * 0.917 + _n * 113.37)) * 4375.85; _v - floor _v };
        private _sev = [_lvl, 4] select _broken;
        private _thick = pixelH * ([1.2, 1.6] select (_sev >= 3));
        // Segment de (x, y), angle en degrés (0 = droite, sens horaire), longueur en unités verticales ; coupé au bord de l'écran.
        private _seg = {
            params ["_x1", "_y1", "_a", "_len", "_alpha"];
            private _x2 = _x1 + (cos _a) * _len / _ratio;
            private _y2 = _y1 + (sin _a) * _len;
            private _k = 1;
            while { _k > 0.1 && {_x2 < _rx || {_x2 > _rx + _rw} || {_y2 < _ry} || {_y2 > _ry + _rh}} } do {
                _k = _k * 0.6;
                _x2 = _x1 + (cos _a) * _len * _k / _ratio;
                _y2 = _y1 + (sin _a) * _len * _k;
            };
            private _w = _len * _k / _ratio;
            private _c = ["COMSPEC_RscPhone", [(_x1 + _x2) / 2 - _w / 2, (_y1 + _y2) / 2 - _thick / 2, _w, _thick], "#(argb,8,8,3)color(1,1,1,1)", false] call comspec_atak_native_fnc_pageCtrl;
            _c ctrlEnable false;
            _c ctrlSetTextColor [0.9, 0.95, 1, _alpha];
            _c ctrlSetAngle [_a, 0.5, 0.5];
            _lines pushBack _c;
            [_x2, _y2]
        };
        for "_i" from 1 to ([1, 2] select (_sev >= 3)) do {
            private _px = _rx + _rw * (0.12 + 0.76 * call _rnd);
            private _py = _ry + _rh * (0.08 + 0.84 * call _rnd);
            private _rays = [0, 5, 8, 11, 14] select _sev;
            private _a0 = 360 * call _rnd;
            for "_j" from 0 to (_rays - 1) do {
                private _a = _a0 + _j * 360 / _rays + (call _rnd - 0.5) * (300 / _rays);
                private _len = _rh * (0.06 + 0.30 * call _rnd) * (0.55 + 0.2 * _sev);
                private _alpha = 0.35 + 0.35 * call _rnd;
                // Rayon brisé : deux tronçons, le second dévié ; ramification courte au coude dès le niveau 2.
                private _mid = [_px, _py, _a, _len * (0.4 + 0.2 * call _rnd), _alpha] call _seg;
                private _a2 = _a + (call _rnd - 0.5) * 50;
                [_mid select 0, _mid select 1, _a2, _len * 0.55, _alpha * 0.8] call _seg;
                if (_sev >= 2 && {call _rnd < 0.6}) then {
                    [_mid select 0, _mid select 1, _a2 + ([-1, 1] select (call _rnd < 0.5)) * (35 + 30 * call _rnd), _len * (0.15 + 0.2 * call _rnd), _alpha * 0.7] call _seg;
                };
            };
            // Anneau d'éclats autour de l'impact (verre écrasé), niveau 2 et plus.
            if (_sev >= 2) then {
                private _r = _rh * (0.025 + 0.02 * call _rnd);
                private _k = 5 + floor (4 * call _rnd);
                for "_j" from 0 to (_k - 1) do {
                    private _a = _a0 + _j * 360 / _k;
                    [_px + (cos _a) * _r / _ratio, _py + (sin _a) * _r, _a + 90 + (call _rnd - 0.5) * 30, _r * (0.8 + 0.6 * call _rnd), 0.55] call _seg;
                };
            };
        };
    };
};
{ _x ctrlShow (!_off && !_fxOn); } forEach _lines;
// Écran éteint ou animation d'alimentation : les fenêtres d'app ne passent plus devant l'écran noir ou de démarrage.
private _content = _d displayCtrl 88531;
private _isMap = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP";
if (_off || _fxOn) then {
    _content ctrlShow false;
    (_d displayCtrl 88530) ctrlShow false;
    uiNamespace setVariable ["COMSPEC_ATAK_DevCovered", true];
} else {
    if (uiNamespace getVariable ["COMSPEC_ATAK_DevCovered", false]) then {
        uiNamespace setVariable ["COMSPEC_ATAK_DevCovered", false];
        _content ctrlShow !_isMap;
        (_d displayCtrl 88530) ctrlShow _isMap;
    };
};
// Effet visible : focus rendu au bouton hors écran (le contrôle focalisé est dessiné devant tout).
private _front = _showCrack || _broken || _off || _fxOn || {ctrlShown _tint};
uiNamespace setVariable ["COMSPEC_ATAK_DevFront", _front];
if (_front) then { [] call comspec_atak_native_fnc_overlayFront; };
uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", [_crack, _black, _txt, _block, _tint, _glitch, _shatter, _boot, _barBg, _bar, _bootTxt, _dead, _deadSig, _lines, _lineSig]];
// Redémarrage terminé : on redessine la page.
private _wasOff = uiNamespace getVariable ["COMSPEC_ATAK_DevWasOff", false];
uiNamespace setVariable ["COMSPEC_ATAK_DevWasOff", _off];
if (_wasOff && {!_off}) then {
    ["INFO", "Téléphone redémarré", 3, 20] call comspec_atak_native_fnc_notify;
    [{ [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "LAUNCHER"]] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
true
