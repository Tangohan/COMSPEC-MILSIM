/*
    Carte de la montre : mon point et les alliés du suivi, avec les mêmes couleurs et les mêmes filtres que la carte
    du téléphone (filtres appliqués à la source par fn_localDataRefresh). L'altitude est mise à 0 avant le dessin : un point qui garde sa hauteur est placé hors du cadran.
    Liste de dessin des alliés (perf lot 2) : symbole, couleur, cercle de précision et indicatif de chaque allié étaient
    recalculés à chaque image (1,3 ms par image dans les journaux) alors que le suivi ne change que 1 à 4 fois par
    seconde. Ils sont préparés une fois par révision du magasin « units » (ou changement de couleur, de symboles, de
    zoom de la montre, de police, de cercles de précision) et rejoués tels quels, dans le même ordre ; mon point et le
    point de passage restent dessinés en direct. Mesure au RPT : « carte montre (image) » (fn_watch).
    Params : [contrôle carte]
*/
params ["_mc"];
private _flat = {
    params ["_p"];
    if ((count _p) < 2) exitWith { [] };
    private _o = +_p;
    if ((count _o) < 3) then { _o pushBack 0; } else { _o set [2, 0]; };
    _o
};
private _pal = uiNamespace getVariable "COMSPEC_ATAK_MapPalette";
if (isNil "_pal") then {
    _pal = createHashMapFromArray [["BLUE", [0.28, 0.70, 1, 1]], ["CYAN", [0.20, 0.90, 0.95, 1]], ["GREEN", [0.36, 0.85, 0.42, 1]], ["WHITE", [0.95, 0.95, 0.95, 1]], ["YELLOW", [1, 0.85, 0.15, 1]], ["ORANGE", [1, 0.55, 0.15, 1]], ["PINK", [1, 0.45, 0.75, 1]], ["FUCHSIA", [1,0.12,0.80,1]], ["LIME", [0.62,1,0.05,1]], ["VIOLET", [0.74,0.40,1,1]], ["TURQ", [0.10,1,0.88,1]], ["NEON", [1,1,0.20,1]]];
    uiNamespace setVariable ["COMSPEC_ATAK_MapPalette", _pal];
};
private _ally = _pal getOrDefault [profileNamespace getVariable ["COMSPEC_ATAK_AllyColor", "BLUE"], [0.28, 0.70, 1, 1]];
private _selfC = _pal getOrDefault [profileNamespace getVariable ["COMSPEC_ATAK_SelfColor", "CYAN"], [0.20, 0.90, 0.95, 1]];
private _dot = "\A3\ui_f\data\map\markers\military\circle_CA.paa";
private _tri = "\A3\ui_f\data\map\markers\military\triangle_CA.paa";
private _clear = "#(argb,8,8,3)color(0,0,0,0)";
private _font = "PuristaMedium";
if (!isNil "COMSPEC_ATAK_FontSet" && {COMSPEC_ATAK_FontSet isEqualType []}) then { _font = COMSPEC_ATAK_FontSet param [1, _font]; };
private _lbl = ((uiNamespace getVariable ["COMSPEC_ATAK_Watch", createHashMap]) getOrDefault ["zoom", 0]) <= 1;
private _nato = (profileNamespace getVariable ["COMSPEC_ATAK_UnitSymbols", "NATO"]) in ["NATO", "MILSTD"];

private _me = [[] call comspec_atak_native_fnc_gpsPos] call _flat;
if (_me isNotEqualTo []) then {
    _mc drawIcon [_dot, [0, 0, 0, 0.9], _me, 30, 30, 0, "", 0];
    _mc drawIcon [_dot, _selfC, _me, 22, 22, 0, "", 0];
    _mc drawIcon [_tri, [1, 1, 1, 1], _me, 14, 14, getDir (vehicle player), "", 0];
};

private _wm = (missionNamespace getVariable ["COMSPEC_ATAK_WatchM", createHashMap]) getOrDefault ["wpt", []];
if (_wm isNotEqualTo []) then {
    private _wp = [_wm select 0] call _flat;
    if (_wp isNotEqualTo []) then {
        _mc drawIcon [_dot, [0, 0, 0, 0.85], _wp, 20, 20, 0, "", 0];
        _mc drawIcon ["\z\comspec_atak_native\addons\main\data\gw_pin.paa", [1, 0.27, 0.33, 1], _wp, 16, 16, 0, "", 0];
    };
};

private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
private _rings = profileNamespace getVariable ["COMSPEC_ATAK_AccRings", false];
// Tout ce dont dépend le dessin d'un allié ; la révision change à chaque écriture du suivi (fn_storeSet).
private _sig = [(_data getOrDefault ["revisions", createHashMap]) getOrDefault ["units", -1], _ally, _nato, _lbl, _font, _rings];
// Gardée sur le contrôle de carte : il est recréé avec la page de la montre, la liste ne survit pas à la mission.
private _dl = _mc getVariable ["comspec_watchDl", []];
if ((_dl param [0, []]) isNotEqualTo _sig) then {
// Commandes dans l'ordre du dessin : [0, arguments de drawIcon] ou [1, arguments de drawEllipse].
private _cmds = [];
{
    private _e = _y;
    if (_e getOrDefault ["self", false]) then { continue };
    if ((_e getOrDefault ["affiliation", ""]) isNotEqualTo "friend") then { continue };
    private _p = [_e getOrDefault ["position", []]] call _flat;
    if (_p isEqualTo []) then { continue };
    private _a = 1;
    private _fr = _e getOrDefault ["freshness", "LIVE"];
    if (_fr isEqualTo "STALE") then { _a = 0.65; };
    if (_fr in ["LOST", "OFFLINE"]) then { _a = 0.35; };
    private _c = +_ally;
    _c set [3, _a];
    private _acc = _e getOrDefault ["acc", 0];
    if (_acc >= 15 && {_rings}) then { _cmds pushBack [1, [_p, _acc, _acc, 0, [_c select 0, _c select 1, _c select 2, _a * 0.4], ""]]; };
    private _icon = "";
    private _dir = _e getOrDefault ["heading", 0];
    if (_nato) then { _icon = ([_e] call comspec_atak_native_fnc_natoSymbol) param [0, ""]; _dir = 0; };
    if (_icon isEqualTo "") then { _icon = ([_e] call comspec_atak_native_fnc_symbology) param [0, _dot]; };
    _cmds pushBack [0, [_dot, [0, 0, 0, 0.8 * _a], _p, 22, 22, 0, "", 0]];
    _cmds pushBack [0, [_icon, _c, _p, 18, 18, _dir, "", 0]];
    if (_lbl) then {
        private _cs = _e getOrDefault ["callsign", ""];
        if ((count _cs) > 10) then { _cs = _cs select [0, 10]; };
        if (_cs isNotEqualTo "") then { _cmds pushBack [0, [_clear, [1, 1, 1, _a], _p, 18, 18, 0, _cs, 2, 0.032, _font, "right"]]; };
    };
} forEach (_data getOrDefault ["units", createHashMap]);
_dl = [_sig, _cmds];
_mc setVariable ["comspec_watchDl", _dl];
};
{ if ((_x select 0) isEqualTo 0) then { _mc drawIcon (_x select 1); } else { _mc drawEllipse (_x select 1); }; } forEach (_dl select 1);
