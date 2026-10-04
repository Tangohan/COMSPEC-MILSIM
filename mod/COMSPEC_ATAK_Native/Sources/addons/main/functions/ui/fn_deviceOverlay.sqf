/*
    Dégâts visibles sur l'écran (textures générées par tools/gen_assets.py) :
      fêlé 1-2 : toile d'araignée (crack_<n>_<v>), taches d'encre dès le niveau 2, quelques lignes mortes fixes ;
      fêlé 3   : grandes zones mortes, lignes mortes qui scintillent (glitch_<v>_<trame>) ;
      détruit  : verre noir éclaté (shatter_<v>) ; éteint : écran noir au redémarrage.
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
(uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]) params [["_crack", controlNull], ["_black", controlNull], ["_txt", controlNull], ["_block", controlNull], ["_tint", controlNull], ["_glitch", controlNull], ["_shatter", controlNull]];
if (isNull _crack || {isNull _tint} || {isNull _glitch} || {isNull _shatter}) then {
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
    // Écran éteint : les clics ne passent plus.
    _block = ["COMSPEC_RscButtonOverlay", _rect, "", false] call comspec_atak_native_fnc_pageCtrl;
    uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", [_crack, _black, _txt, _block, _tint, _glitch, _shatter]];
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
private _off = _state isEqualTo "OFF";
_block ctrlShow (_off || _broken);
_block ctrlSetTooltip (["Téléphone éteint", "Téléphone détruit"] select _broken);
_txt ctrlShow _off;
if (_off) then {
    _black ctrlShow true; _black ctrlSetFade 0; _black ctrlCommit 0;
    _txt ctrlSetStructuredText parseText format ["<t align='center' size='1.4' color='#5cc76b'>ANDROID</t><br/><t align='center' color='#c9d4cf'>%1</t><br/><t align='center' size='0.85' color='#8a9a93'>%2</t>", _hp get "reason", switch (true) do {
        case ((_hp get "reason") isEqualTo "Batterie vide"): { "Rechargez en véhicule ou changez la batterie" };
        case ((_hp get "offLeft") > 0): { format ["Redémarrage dans %1 s", _hp get "offLeft"] };
        default { "Redémarrage en cours…" };
    }];
} else {
    // Écran abîmé : il saute de temps en temps.
    if (_lvl >= 2 && {random 1 < ([0.06, 0.16] select (_lvl >= 3))}) then {
        _black ctrlShow true; _black ctrlSetFade 0.1; _black ctrlCommit 0;
        _black ctrlSetFade 1; _black ctrlCommit (0.25 + random 0.4);
    } else {
        if (ctrlFade _black >= 1 || {!ctrlShown _black}) then { _black ctrlShow false; } else { _black ctrlSetFade 1; _black ctrlCommit 0.3; };
    };
};
// Redémarrage terminé : on redessine la page.
private _wasOff = uiNamespace getVariable ["COMSPEC_ATAK_DevWasOff", false];
uiNamespace setVariable ["COMSPEC_ATAK_DevWasOff", _off];
if (_wasOff && {!_off}) then {
    ["INFO", "Téléphone redémarré", 3, 20] call comspec_atak_native_fnc_notify;
    [{ [(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "LAUNCHER"]] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
true
