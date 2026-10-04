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
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _hp = [] call comspec_atak_native_fnc_deviceHealth;
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _rect = _l get "device";
(uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]) params [["_crack", controlNull], ["_black", controlNull], ["_txt", controlNull], ["_block", controlNull], ["_tint", controlNull], ["_glitch", controlNull], ["_shatter", controlNull], ["_boot", controlNull], ["_barBg", controlNull], ["_bar", controlNull], ["_bootTxt", controlNull], ["_dead", []], ["_deadSig", ""]];
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
    // Écran éteint : les clics ne passent plus.
    _block = ["COMSPEC_RscButtonOverlay", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
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
            _step, missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", "1.4.0"], _hp get "reason"];
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
uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", [_crack, _black, _txt, _block, _tint, _glitch, _shatter, _boot, _barBg, _bar, _bootTxt, _dead, _deadSig]];
// Redémarrage terminé : on redessine la page.
private _wasOff = uiNamespace getVariable ["COMSPEC_ATAK_DevWasOff", false];
uiNamespace setVariable ["COMSPEC_ATAK_DevWasOff", _off];
if (_wasOff && {!_off}) then {
    ["INFO", "Téléphone redémarré", 3, 20] call comspec_atak_native_fnc_notify;
    [{ [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "LAUNCHER"]] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
true
