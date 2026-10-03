/*
    App Tireur d'élite : arme et munition détectées (vitesse initiale, frottement, zérotage), télémètre,
    solution de tir (élévation et dérive en MRAD / MOA, temps de vol), vent du jeu ou saisi, et table de tir.
    Modèle balistique d'Arma ; la dérive due au vent suit la règle du retard (vent travers × (TdV − D/V0)).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Sniper", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Sniper", _s];
private _grey = "#8a9a93";
private _wpn = currentWeapon player;
if (_wpn isEqualTo "") then { _wpn = primaryWeapon player; };
private _mag = currentMagazine player;
if (_mag isEqualTo "" && {_wpn isNotEqualTo ""}) then { _mag = (getArray (configFile >> "CfgWeapons" >> _wpn >> "magazines")) param [0, ""]; };
private _rows = [];
if (_wpn isEqualTo "" || {_mag isEqualTo ""}) exitWith {
    _rows pushBack ["section", "Tireur d'élite", "Aucune arme"];
    _rows pushBack ["text", format ["<t color='%1'>Prenez une arme chargée : la munition est détectée automatiquement.</t>", _grey]];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _ammo = getText (configFile >> "CfgMagazines" >> _mag >> "ammo");
private _v0 = getNumber (configFile >> "CfgMagazines" >> _mag >> "initSpeed");
private _wInit = getNumber (configFile >> "CfgWeapons" >> _wpn >> "initSpeed");
if (_wInit > 0) then { _v0 = _wInit; };
if (_wInit < 0) then { _v0 = _v0 * abs _wInit; };
if (_v0 <= 0) then { _v0 = 800; };
private _k = getNumber (configFile >> "CfgAmmo" >> _ammo >> "airFriction");
if (_k >= 0) then { _k = -0.0009; };
private _zero = currentZeroing player;
if !(_zero isEqualType 0) then { _zero = _zero param [0, 100]; };
if (_zero <= 0) then { _zero = 100; };
private _max = ((round ((_v0 * 1.6) / 100)) * 100) min 2000 max 600;

// Table recalculée seulement quand l'arme, la munition ou le zérotage change.
private _sig = [_wpn, _mag, _zero];
if ((_s getOrDefault ["sig", []]) isNotEqualTo _sig) then {
    _s set ["sig", _sig];
    _s set ["table", [_v0, _k, _zero, 0.06, _max, 50] call comspec_atak_native_fnc_sniperBallistics];
};
private _table = _s getOrDefault ["table", []];
private _at = {
    params ["_d"];
    private _i = (floor (_d / 50)) - 1;
    if (_i < 0 || {_i >= (count _table) - 1}) exitWith { _table param [((_i max 0) min ((count _table) - 1)), [_d, 0, 0, _v0]] };
    (_table select _i) params ["_d0", "_y0", "_t0", "_s0"];
    (_table select (_i + 1)) params ["", "_y1", "_t1", "_s1"];
    private _f = (_d - _d0) / 50;
    [_d, _y0 + (_y1 - _y0) * _f, _t0 + (_t1 - _t0) * _f, _s0 + (_s1 - _s0) * _f]
};
// Vent : celui du jeu projeté sur l'axe de tir, ou saisi (m/s, positif = vient de la gauche).
private _dir = getDir player;
private _w = wind;
private _cross = ((_w select 0) * cos _dir) - ((_w select 1) * sin _dir);
private _head = -(((_w select 0) * sin _dir) + ((_w select 1) * cos _dir));
private _manual = _s getOrDefault ["windManual", false];
if (_manual) then { _cross = (parseNumber (_s getOrDefault ["windSpeed", "0"])) * ([1, -1] select ((_s getOrDefault ["windFrom", "L"]) isEqualTo "R")); _head = 0; };
private _wSpd = vectorMagnitude [_w select 0, _w select 1, 0];
private _wFrom = (((_w select 0) atan2 (_w select 1)) + 180) mod 360;
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _drift = { params ["_d", "_t"]; _cross * ((_t - _d / _v0) max 0) };
private _mil = { params ["_m", "_d"]; (_m / (_d max 1)) * 1000 };

// Cible : distance saisie ou télémétrée, angle de site (règle du fusilier : distance horizontale pour la chute).
private _range = (parseNumber (_s getOrDefault ["range", "400"])) max 25;
private _incl = _s getOrDefault ["incl", 0];
private _hRange = _range * cos _incl;
([_hRange min (_max - 50)] call _at) params ["", "_y", "_tof", "_vi"];
private _elev = -([_y, _hRange] call _mil);
private _dr = [[_hRange, _tof] call _drift, _hRange] call _mil;
private _side = ["balle à droite, corrigez à GAUCHE", "balle à gauche, corrigez à DROITE"] select (_dr < 0);
if ((abs _dr) < 0.05) then { _side = "aucune"; };
_rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_sniper.paa", format ["<t size='1.15' font='RobotoCondensedBold'>%1</t><br/><t size='0.85' color='%2'>%3 · V0 %4 m/s · zéro %5 m</t>",
    getText (configFile >> "CfgWeapons" >> _wpn >> "displayName"), _grey, getText (configFile >> "CfgMagazines" >> _mag >> "displayName"), round _v0, _zero]];
_rows pushBack ["section", "Solution", format ["Cible à %1 m%2", round _range, ["", format [" · site %1°", round _incl]] select ((abs _incl) >= 1)]];
_rows pushBack ["text", format ["<t size='1.6' font='RobotoCondensedBold' color='#5cc76b'>%1%2 MRAD</t><t color='%3'>  élévation · %4 MOA</t><br/><t size='1.6' font='RobotoCondensedBold' color='#f2ab33'>%5 MRAD</t><t color='%3'>  dérive : %6 · %7 MOA</t><br/><t color='%3'>Temps de vol </t>%8 s<t color='%3'> · vitesse à l'impact </t>%9 m/s",
    ["", "+"] select (_elev >= 0), _elev toFixed 1, _grey, (_elev * 3.438) toFixed 1, (abs _dr) toFixed 1, _side, ((abs _dr) * 3.438) toFixed 1, _tof toFixed 2, round _vi]];
_rows pushBack ["edit", "sniRange", "Distance de la cible (m)", str round _range];
_rows pushBack ["buttons", [
    ["TÉLÉMÉTRER", { ['laser'] call comspec_atak_native_fnc_sniperAction; }, true],
    ["CALCULER", { ['range'] call comspec_atak_native_fnc_sniperAction; }]
]];

_rows pushBack ["section", "Vent", ["Vent du jeu, projeté sur votre axe de tir", "Vent saisi"] select _manual];
_rows pushBack ["info", "Vent du jeu", format ["%1 m/s du %2 (%3°)", _wSpd toFixed 1, [_wFrom] call _card, round _wFrom]];
_rows pushBack ["info", "Travers · face", format ["%1 m/s %2 · %3 m/s %4", (abs _cross) toFixed 1, ["de la droite", "de la gauche"] select (_cross >= 0), (abs _head) toFixed 1, ["arrière", "de face"] select (_head >= 0)]];
_rows pushBack ["segment", "Source", [["JEU", { ['wind', false] call comspec_atak_native_fnc_sniperAction; }, !_manual], ["SAISI", { ['wind', true] call comspec_atak_native_fnc_sniperAction; }, _manual]]];
if (_manual) then {
    _rows pushBack ["edit", "sniWind", "Vent travers (m/s)", _s getOrDefault ["windSpeed", "0"]];
    _rows pushBack ["segment", "Vient de", [["GAUCHE (9 h)", { ['from', 'L'] call comspec_atak_native_fnc_sniperAction; }, (_s getOrDefault ["windFrom", "L"]) isEqualTo "L"], ["DROITE (3 h)", { ['from', 'R'] call comspec_atak_native_fnc_sniperAction; }, (_s getOrDefault ["windFrom", "L"]) isEqualTo "R"]]];
};

// Table de tir.
private _lines = [format ["<t color='%1'>DIST   ÉLÉV    MOA  DÉRIVE   TdV    V</t>", _grey]];
private _pad = { params ["_t", "_n"]; while { (count _t) < _n } do { _t = " " + _t; }; _t };
for "_d" from 100 to (_max - 50) step 100 do {
    ([_d] call _at) params ["", "_yy", "_tt", "_vv"];
    private _e = -([_yy, _d] call _mil);
    private _wd = [[_d, _tt] call _drift, _d] call _mil;
    _lines pushBack format ["<t color='%7'>%1</t> %2 %3 %4 %5 %6", [str _d, 4] call _pad, [_e toFixed 1, 6] call _pad, [(_e * 3.438) toFixed 1, 6] call _pad,
        [format ["%1%2", (abs _wd) toFixed 1, ["D", "G"] select (_wd < 0)], 7] call _pad, [_tt toFixed 2, 6] call _pad, [str round _vv, 5] call _pad, ["#c9d4cf", "#5cc76b"] select ((abs (_d - _range)) < 50)];
};
_rows pushBack ["section", "Table de tir", format ["Élévation en MRAD depuis le zéro de %1 m · dérive pour le vent ci-dessus", _zero]];
_rows pushBack ["text", format ["<t font='EtelkaMonospacePro' size='0.8'>%1</t>", _lines joinString "<br/>"]];
_rows pushBack ["text", format ["<t size='0.75' color='%1'>Modèle balistique d'Arma (frottement %2). Avec la balistique avancée d'ACE, préférez l'ATragMX pour les très longues distances.</t>", _grey, _k]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
