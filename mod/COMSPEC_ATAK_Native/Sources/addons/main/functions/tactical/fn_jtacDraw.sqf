/*
    Carte tactique de l'app JTAC (événement Draw), à la façon d'un terminal JTAC :
      cible (losange rouge, grille), cercle de la distance de risque de l'armement (rouge si DANGER CLOSE),
      IP et axe d'entrée IP → cible (cap et distance), cône de l'axe final d'attaque (FAH) côté arrivée,
      amis au sol près de la cible (le plus proche relié à la cible, distance), JTAC et ligne laser-cible (LTL),
      appareils amis en vol, celui de la mission en vert avec son vecteur de cap, son altitude et sa distance.
    Libellés en brièveté air-sol (TGT, IP, FAH, LTL), comme sur les vrais outils.
    Params : [contrôle carte, vue ("JTAC" | "PILOT")]
*/
params ["_map", ["_mode", "JTAC"]];
private _cp = ctrlPosition _map;
// Vue gardée d'un rendu à l'autre (la page se redessine toutes les 3 s pendant le contrôle).
uiNamespace setVariable [format ["COMSPEC_ATAK_JtacView_%1", _mode], [ctrlMapScale _map, _map ctrlMapScreenToWorld [(_cp select 0) + (_cp select 2) / 2, (_cp select 1) + (_cp select 3) / 2]]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
private _pilot = _mode isEqualTo "PILOT";
private _amber = [0.95, 0.67, 0.2, 1];
private _red = [0.92, 0.26, 0.2, 1];
private _green = [0.36, 0.85, 0.42, 1];
private _blue = [0.32, 0.66, 1, 1];
private _cyan = [0.2, 0.9, 0.95, 1];
private _none = "#(argb,8,8,3)color(0,0,0,0)";
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _mono = "EtelkaMonospaceProBold";
private _tsz = 0.032;
private _label = { params ["_pos", "_txt", "_col", ["_align", "center"]]; _map drawIcon [_none, _col, _pos, 0, 0, 0, _txt, 2, _tsz, _mono, _align]; };
private _mid = { params ["_a", "_b"]; [((_a select 0) + (_b select 0)) / 2, ((_a select 1) + (_b select 1)) / 2, 0] };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SW", "W", "NW"] select ((round (_deg / 45)) mod 8) };

// Données de la mission (JTAC) ou du brief reçu (équipage).
private _tgt = [];
private _ip = [];
private _ipName = "";
private _fah = "";
private _air = objNull;
private _laser = false;
private _risk = 0;
private _dc = false;
if (_pilot) then {
    private _d = (uiNamespace getVariable ["COMSPEC_ATAK_JtacPilot", createHashMap]) getOrDefault ["data", []];
    _tgt = _d param [6, []];
    _fah = _d param [7, ""];
    _dc = _d param [8, false];
    _ip = _d param [9, []];
    _risk = _d param [10, 0];
    _ipName = (_d param [0, []]) param [0, ""];
    _air = vehicle player;
} else {
    private _m = _s getOrDefault ["msn", createHashMap];
    private _live = (count _m) > 0 && {(_m getOrDefault ["step", 0]) < 7};
    private _b = _m getOrDefault ["brief", createHashMap];
    _tgt = _s getOrDefault ["target", []];
    if (_live) then { _tgt = _b getOrDefault ["target", _tgt]; _air = _m getOrDefault ["air", objNull]; _risk = _b getOrDefault ["risk", 0]; _dc = _b getOrDefault ["dc", false]; };
    if (isNull _air) then { _air = _s getOrDefault ["air", objNull]; };
    _ip = _s getOrDefault ["ip", []];
    _ipName = _s getOrDefault ["jIp", ""];
    if ((trim _ipName) isEqualTo "") then { _ipName = _s getOrDefault ["auto_jIp", ""]; };
    _fah = _s getOrDefault ["jFah", ""];
    if (_live && {(_b getOrDefault ["fah", ""]) isNotEqualTo ""}) then { _fah = _b get "fah"; };
    _laser = (_s getOrDefault ["mark", "LASER"]) isEqualTo "LASER";
};
private _hasT = (count _tgt) >= 2;

// Amis au sol et appareils : relus une fois par seconde (pas à chaque image).
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_JtacDrawCache", [-10, [], []]];
if (diag_tickTime - (_cache select 0) > 1) then {
    private _c = [getPosASL player, _tgt] select _hasT;
    private _side = side group player;
    private _fr = (allUnits select { alive _x && {side group _x isEqualTo _side} && {!((vehicle _x) isKindOf "Air")} && {(_x distance2D _c) < 4000} && {_x isNotEqualTo player} }) apply { [getPosASL _x, getDir _x] };
    private _airs = vehicles select { _x isKindOf "Air" && {alive _x} && {(count crew _x) > 0} && {side group (effectiveCommander _x) isEqualTo _side} && {(_x distance2D player) < 40000} };
    _cache = [diag_tickTime, _fr, _airs];
    uiNamespace setVariable ["COMSPEC_ATAK_JtacDrawCache", _cache];
};
_cache params ["", "_friends", "_airs"];

if (_hasT) then {
    // Cône de l'axe final d'attaque : l'appareil vole au cap FAH vers la cible, il arrive donc du côté opposé.
    private _win = [_fah] call comspec_atak_native_fnc_jtacFah;
    if ((count _win) isEqualTo 2) then {
        _win params ["_lo", "_hi"];
        private _span = (((_hi - _lo) + 360) mod 360) max 2;
        private _r = if ((count _ip) >= 2) then { ((_ip distance2D _tgt) * 1.1) max 3000 min 15000 } else { 6000 };
        private _pts = [];
        for "_a" from 0 to _span step ((_span / 12) max 1) do { _pts pushBack (_tgt getPos [_r, _lo + _a + 180]); };
        for "_i" from 0 to ((count _pts) - 2) do {
            _map drawTriangle [[_tgt, _pts select _i, _pts select (_i + 1)], [0.95, 0.67, 0.2, 0.14], _fill];
            _map drawLine [_pts select _i, _pts select (_i + 1), _amber];
        };
        _map drawLine [_tgt, _pts select 0, _amber];
        _map drawLine [_tgt, _pts select ((count _pts) - 1), _amber];
        [_tgt getPos [_r * 0.7, _lo + _span / 2 + 180], format ["FAH %1", _fah], _amber] call _label;
    };
    // Distance de risque de l'armement.
    if (_risk > 0) then {
        _map drawEllipse [_tgt, _risk, _risk, 0, [[0.95, 0.67, 0.2, 0.9], _red] select _dc, ""];
        [_tgt getPos [_risk, 135], format ["RED %1 m", round _risk], [_amber, _red] select _dc, "right"] call _label;
    };
    // IP et axe d'entrée.
    if ((count _ip) >= 2) then {
        _map drawArrow [_ip, _tgt, _green];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\triangle_CA.paa", _green, _ip, 20, 20, 0, format [" %1", [_ipName, "IP"] select (_ipName isEqualTo "")], 2, _tsz, _mono, "right"];
        [[_ip, _tgt] call _mid, format ["%1° · %2 NM", [round (_ip getDir _tgt), 3] call CBA_fnc_formatNumber, ((_ip distance2D _tgt) / 1852) toFixed 1], _green] call _label;
    };
    // Amis : le plus proche relié à la cible.
    private _near = [];
    { if ((count _near) isEqualTo 0 || {((_x select 0) distance2D _tgt) < ((_near select 0) distance2D _tgt)}) then { _near = _x; }; } forEach _friends;
    if ((count _near) > 0 && {((_near select 0) distance2D _tgt) < 3000}) then {
        private _fc = [_green, _red] select _dc;
        _map drawLine [_tgt, _near select 0, _fc];
        [[_tgt, _near select 0] call _mid, format ["%1 m %2", round ((_near select 0) distance2D _tgt), [_tgt getDir (_near select 0)] call _card], _fc] call _label;
    };
};
{ _map drawIcon ["\A3\ui_f\data\map\markers\nato\b_inf.paa", _blue, _x select 0, 18, 18, 0, "", 0, _tsz, _mono, "right"]; } forEach _friends;

// JTAC et ligne laser-cible.
if (!_pilot) then {
    _map drawIcon ["\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa", _cyan, getPosASL player, 24, 24, getDir player, " JTAC", 2, _tsz, _mono, "right"];
    if (_hasT && {_laser}) then {
        _map drawLine [getPosASL player, _tgt, [1, 0.3, 0.25, 0.75]];
        [(getPosASL player) getPos [(player distance2D _tgt) * 0.3, player getDir _tgt], format ["LTL %1°", [round (player getDir _tgt), 3] call CBA_fnc_formatNumber], [1, 0.45, 0.4, 1]] call _label;
    };
};

// Appareils : celui de la mission en vert vif avec son vecteur de cap.
{
    private _mine = _x isEqualTo _air;
    private _ic = ["\A3\ui_f\data\map\vehicleicons\iconPlane_ca.paa", "\A3\ui_f\data\map\vehicleicons\iconHelicopter_ca.paa"] select (_x isKindOf "Helicopter");
    private _p = getPosASL _x;
    private _txt = format [" %1 %2", groupId group _x, format ["%1FT", round ((_p select 2) * 3.28084 / 100) * 100]];
    if (_mine) then {
        _map drawLine [_p, _p getPos [((speed _x) / 3.6 * 30) max 600, getDir _x], _green];
        if (_hasT) then { _txt = _txt + format [" %1NM", ((_x distance2D _tgt) / 1852) toFixed 1]; };
    };
    _map drawIcon [_ic, [[0.36, 0.85, 0.42, 0.45], _green] select _mine, _p, [20, 28] select _mine, [20, 28] select _mine, getDir _x, _txt, 2, _tsz, _mono, "right"];
} forEach ([_airs, _airs + [_air]] select (_pilot && {!isNull _air} && {!(_air in _airs)}));

// Cible au-dessus du reste.
if (_hasT) then {
    _map drawIcon ["\A3\ui_f\data\map\markers\nato\o_unknown.paa", _red, _tgt, 26, 26, 0, " TGT", 2, _tsz, _mono, "right"];
};
