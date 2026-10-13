/*
    9-LINE du JTAC calculé depuis l'état de l'app (cible, IP, appareil, armement, marquage, saisie).
      1 IP/BP (nom)                    2 cap IP → cible (degrés ; magnétique = vrai sur les cartes Arma)
      3 distance IP → cible (NM)       4 altitude de la cible (ft MSL)
      5 description (saisie)           6 position (grille 10 chiffres)
      7 marquage (LASER + code, IR, fumigène, aucun)
      8 amis : direction et distance depuis la cible (ami le plus proche, hors aéronefs)
      9 dégagement (saisie)
    Remarques : axe final d'attaque (FAH), ligne laser-cible (LTL), DANGER CLOSE et initiales, menaces, armement,
    type de contrôle et méthode d'attaque.
    Complétion automatique (« tout compléter »), seulement si l'admin l'active sur Athena (Contrôle serveur, clé
    native_jtac_autofill = "on") ; par défaut, réalisme : le JTAC saisit lui-même IP, lignes 5 et 9 et remarques.
    Quand elle est active, tout ce qui peut se déduire de la cible, de la position du joueur,
    de l'appareil et de la carte est proposé sans saisie, et la saisie du joueur l'emporte toujours :
      appareil (le plus proche en station s'il n'y en a pas de choisi), cible (point laser du joueur s'il désigne),
      IP (5 NM de la cible du côté de l'appareil, ou à l'opposé des amis ; nommé d'après le lieu-dit le plus proche),
      ligne 5 (ennemis connus près de la cible : nombre, type, à l'arrêt ou en mouvement), ligne 9 (dégagement vers l'IP,
      altitude de l'appareil), FAH (axe IP → cible ± 20°), menaces (sol-air et blindés ennemis connus à 5 km).
      Les propositions sont rangées sous auto_<clé> dans l'état (la page les affiche en vert dans les champs ; fn_jtacAction
      "save" ne garde pas comme saisie une proposition laissée telle quelle).
    Renvoie un hashmap : lines, remarks, type, method, ord, code, target, fah, dc, friend [distance, direction],
    risk (distance de risque de l'armement), ltl, missing (ce qui empêche l'envoi, "" sinon), auto (clés proposées).
*/
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
private _card = { params ["_deg"]; ["NORTH", "NORTHEAST", "EAST", "SOUTHEAST", "SOUTH", "SOUTHWEST", "WEST", "NORTHWEST"] select ((round (_deg / 45)) mod 8) };
private _cardFr = { params ["_deg"]; ["NORD", "NORD-EST", "EST", "SUD-EST", "SUD", "SUD-OUEST", "OUEST", "NORD-OUEST"] select ((round (_deg / 45)) mod 8) };
private _h = createHashMap;
private _side = side group player;
private _autoOn = (["native_jtac_autofill"] call comspec_atak_native_fnc_tenantRule) isEqualTo "on";
// Appareil : le plus proche en station quand aucun n'est choisi.
private _air = _s getOrDefault ["air", objNull];
if (!isNull _air && {!alive _air}) then { _air = objNull; };
if (isNull _air && {_autoOn}) then {
    private _airs = (vehicles select { _x isKindOf "Air" && {alive _x} && {crew _x isNotEqualTo []} && {side group (effectiveCommander _x) isEqualTo _side} && {!(_x isKindOf "UAV_01_base_F")} && {(_x distance2D player) < 40000} }) apply { [_x distance2D player, _x] };
    _airs sort true;
    if (_airs isNotEqualTo []) then { _air = (_airs select 0) select 1; _s set ["air", _air]; _s set ["airAuto", true]; };
};
// Cible : point laser du joueur quand rien n'est verrouillé.
private _tgt = _s getOrDefault ["target", []];
if ((count _tgt) < 2 && {_autoOn}) then {
    private _t = [] call comspec_atak_native_fnc_jtacTarget;
    if (_t isNotEqualTo [] && {(_t select 1) isEqualTo "laser"}) then {
        _tgt = _t select 0;
        _s set ["target", _tgt];
        _s set ["source", format ["laser · %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
    };
};
private _hasT = (count _tgt) >= 2;
// Ami au sol le plus proche de la cible (sert à la ligne 8 et au choix de l'IP).
private _frU = objNull;
private _friend = [];
if (_hasT) then {
    private _fr = (allUnits select { alive _x && {side group _x isEqualTo _side} && {!((vehicle _x) isKindOf "Air")} && {(_x distance2D _tgt) < 3000} }) apply { [_x distance2D _tgt, _x] };
    _fr sort true;
    if (_fr isNotEqualTo []) then { (_fr select 0) params ["_d", "_u"]; _frU = _u; _friend = [round _d, [_tgt getDir _u] call _card]; };
};
// IP : posé par le joueur, sinon proposé à 5 NM de la cible (côté appareil, sinon à l'opposé des amis, sinon à l'ouest).
private _ip = _s getOrDefault ["ip", []];
// Complétion coupée : un IP proposé plus tôt (complétion active à ce moment-là) n'est pas gardé.
if (!_autoOn && {_s getOrDefault ["ipAuto", false]}) then { _ip = []; _s set ["ip", []]; };
private _auto = createHashMap;
if (_autoOn && {_hasT} && {(_s getOrDefault ["ipAuto", true]) || {(count _ip) < 2}}) then {
    private _brg = switch (true) do {
        case (!isNull _air): { _tgt getDir _air };
        case (!isNull _frU): { (_tgt getDir _frU) + 180 };
        default { 270 };
    };
    private _p = _tgt getPos [9260, _brg];
    _p = [(_p select 0) max 200 min (worldSize - 200), (_p select 1) max 200 min (worldSize - 200)];
    _ip = [_p select 0, _p select 1, getTerrainHeightASL _p];
    _s set ["ip", _ip];
    _s set ["ipAuto", true];
    private _locs = nearestLocations [_p, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal", "Hill", "NameMarine"], 2500];
    private _nm = "";
    { if (_nm isEqualTo "" && {(text _x) isNotEqualTo ""}) then { _nm = toUpper (text _x); }; } forEach _locs;
    _auto set ["jIp", ["IP " + _nm, format ["IP %1", [_ip, 6] call comspec_atak_native_fnc_gridRef]] select (_nm isEqualTo "")];
};
private _code = player getVariable ["ace_laser_code", 1111];
if !(_code isEqualType 0) then { _code = 1111; };
private _mark = _s getOrDefault ["mark", "LASER"];
private _type = _s getOrDefault ["type", 2];
private _method = _s getOrDefault ["method", "BOT"];

// Armement choisi (ou le plus dangereux de l'appareil) et sa distance de risque.
private _ords = if (isNull _air) then { [] } else { ([_air] call comspec_atak_native_fnc_jtacAir) getOrDefault ["ord", []] };
private _ordName = _s getOrDefault ["ord", ""];
private _oi = _ords findIf { (_x select 0) isEqualTo _ordName };
private _o = if (_oi >= 0) then { _ords select _oi } else { [] };
private _risk = if ((count _o) > 0) then { _o select 2 } else { selectMax ((_ords apply { _x select 2 }) + [0]) };
if ((count _o) isEqualTo 0) then { _ordName = ""; };
// Rien de choisi : le premier armement air-sol de la fiche de check-in (la distance de risque reste la plus grande).
if (_autoOn && {_ordName isEqualTo ""} && {_ords isNotEqualTo []}) then { _ordName = (_ords select 0) select 0; };

private _dc = _hasT && {(count _friend) > 0} && {_risk > 0} && {(_friend select 0) < _risk};

// Ennemis connus près de la cible (ligne 5) et menaces à 5 km (remarques).
if (_autoOn && {_hasT}) then {
    private _known = { params ["_u"]; ((group player) knowsAbout _u) > 0.2 || {(player knowsAbout _u) > 0.2} };
    private _en = (_tgt nearEntities [["Man", "LandVehicle", "StaticWeapon", "Ship"], 150]) select { alive _x && {[_side, side group _x] call BIS_fnc_sideIsEnemy} && {[_x] call _known} };
    private _veh = _en select { !(_x isKindOf "Man") };
    private _inf = _en select { _x isKindOf "Man" };
    private _desc = [];
    private _types = createHashMap;
    { private _n = getText (configOf _x >> "displayName"); _types set [_n, (_types getOrDefault [_n, 0]) + 1]; } forEach _veh;
    { _desc pushBack format ["%1 × %2", _y, toUpper _x]; } forEach _types;
    if (_inf isNotEqualTo []) then { _desc pushBack format ["%1 × INFANTERIE", count _inf]; };
    private _moving = (_en findIf { (speed _x) > 3 }) >= 0;
    if (_desc isNotEqualTo []) then {
        _auto set ["j5", format ["%1, %2", (_desc select [0, 3]) joinString ", ", ["À L'ARRÊT", "EN MOUVEMENT"] select _moving]];
    } else {
        private _bld = nearestObjects [_tgt, ["House", "Building"], 25];
        _auto set ["j5", ["POSITION ENNEMIE", "BÂTIMENT OCCUPÉ"] select (_bld isNotEqualTo [])];
    };
    // Menaces : sol-air (missiles, canons), blindés, armes légères.
    private _thr = [];
    {
        private _u = _x;
        private _v = vehicle _u;
        private _aa = 0;
        private _mags = if (_v isEqualTo _u) then { [secondaryWeaponMagazine _u] } else { (magazinesAllTurrets _v) apply { [_x select 0] } };
        {
            {
                private _ammo = getText (configFile >> "CfgMagazines" >> _x >> "ammo");
                _aa = _aa max (getNumber (configFile >> "CfgAmmo" >> _ammo >> "airLock"));
            } forEach _x;
        } forEach _mags;
        private _kind = switch (true) do {
            case (_aa >= 2 && {_v isEqualTo _u}): { "MANPADS" };
            case (_aa >= 2): { "SAM" };
            case (_aa >= 1 && {_v isKindOf "LandVehicle" || {_v isKindOf "StaticWeapon"}} && {!(_v isKindOf "Car")}): { "AAA" };
            default { "" };
        };
        if (_kind isNotEqualTo "") then { _thr pushBack [_v distance2D _tgt, format ["%1 %2 %3", _kind, [_v distance2D _tgt] call { params ["_m"]; [format ["%1 m", round (_m / 100) * 100], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) }, [_tgt getDir _v] call _card]]; };
    } forEach ((allUnits select { alive _x && {(_x distance2D _tgt) < 5000} && {[_side, side group _x] call BIS_fnc_sideIsEnemy} && {[_x] call _known} }) select [0, 200]);
    _thr sort true;
    private _tt = [];
    { _tt pushBackUnique (_x select 1); } forEach _thr;
    if ((_veh findIf { _x isKindOf "Tank" || {_x isKindOf "Wheeled_APC_F"} }) >= 0) then { _tt pushBack "BLINDÉS SUR CIBLE"; };
    if (_inf isNotEqualTo []) then { _tt pushBack "ARMES LÉGÈRES"; };
    _auto set ["jThreat", [(_tt select [0, 4]) joinString " · ", "AUCUNE MENACE SOL-AIR CONNUE"] select (_tt isEqualTo [])];
};
// FAH proposé : l'axe IP → cible, ± 20°.
if (_autoOn && {_hasT} && {(count _ip) >= 2}) then { _auto set ["jFah", format ["%1 ± 20", [round (_ip getDir _tgt), 3] call CBA_fnc_formatNumber]]; };
// Dégagement proposé : vers l'IP, à l'altitude actuelle de l'appareil (arrondie à 1000 ft, 500 ft pour un hélicoptère).
if (_autoOn && {_hasT} && {(count _ip) >= 2}) then {
    private _alt = if (isNull _air) then { "" } else {
        private _ft = ((getPosASL _air) select 2) * 3.28084;
        if (_air isKindOf "Helicopter") then { ", BAS" } else { format [", %1 ft", (round (_ft / 1000) * 1000) max 10000] }
    };
    _auto set ["j9", format ["%1, retour %2%3", [_tgt getDir _ip] call _cardFr, [trim (_s getOrDefault ["jIp", ""]), _auto getOrDefault ["jIp", "IP"]] select ((trim (_s getOrDefault ["jIp", ""])) isEqualTo ""), _alt]];
};
{ _s set ["auto_" + _x, _auto getOrDefault [_x, ""]]; } forEach ["jIp", "j5", "j9", "jFah", "jThreat"];
// Valeur d'un champ : la saisie du joueur, sinon la proposition.
private _val = { params ["_k"]; private _v = trim (_s getOrDefault [_k, ""]); [_v, _auto getOrDefault [_k, ""]] select (_v isEqualTo "") };

private _l1 = ["jIp"] call _val;
private _l2 = "";
private _l3 = "";
if ((count _ip) >= 2 && _hasT) then {
    _l2 = format ["%1°", [round (_ip getDir _tgt), 3] call CBA_fnc_formatNumber];
    _l3 = format ["%1 NM", ((_ip distance2D _tgt) / 1852) toFixed 1];
};
private _l4 = if (_hasT) then { format ["%1 ft MSL", round ((getTerrainHeightASL _tgt) * 3.28084)] } else { "" };
private _l5 = ["j5"] call _val;
private _l6 = if (_hasT) then { [_tgt, 10] call comspec_atak_native_fnc_gridRef } else { "" };
private _l7 = switch (_mark) do {
    case "LASER": { format ["LASER %1", _code] };
    case "IR": { "IR POINTER" };
    case "FUMI": { "FUMIGÈNE" };
    default { "AUCUN" };
};
private _l8 = if ((count _friend) > 0) then { format ["%1 %2 m", _friend select 1, _friend select 0] } else { ["", "aucun à 3 km"] select _hasT };
private _l9 = ["j9"] call _val;

private _fah = ["jFah"] call _val;
private _ltl = "";
if (_mark isEqualTo "LASER" && _hasT) then { _ltl = format ["%1°", [round (player getDir _tgt), 3] call CBA_fnc_formatNumber]; };
private _rem = [];
if (_fah isNotEqualTo "") then { _rem pushBack format ["FAH %1", _fah]; };
if (_ltl isNotEqualTo "") then { _rem pushBack format ["LTL %1", _ltl]; };
private _dcInit = trim (_s getOrDefault ["jDc", ""]);
if (_dc) then { _rem pushBack format ["DANGER CLOSE%1", ["", format [", initiales %1", toUpper _dcInit]] select (_dcInit isNotEqualTo "")]; };
private _thr = ["jThreat"] call _val;
if (_thr isNotEqualTo "") then { _rem pushBack format ["Menaces : %1", _thr]; };
if (_ordName isNotEqualTo "") then { _rem pushBack format ["Armement : %1", _ordName]; };
_rem pushBack format ["TYPE %1, %2", _type, _method];

private _missing = [];
if (isNull _air) then { _missing pushBack "appareil (aucun appareil ami en vol : attendez son check-in, puis CHOISIR dans SITU)"; };
if (!_hasT) then { _missing pushBack "cible (VERROUILLER en la visant ou en la désignant au laser, ou POSER LA CIBLE sur la carte)"; };
if (_l1 isEqualTo "") then { _missing pushBack "ligne 1 (IP)"; };
if (_l5 isEqualTo "") then { _missing pushBack "ligne 5 (description)"; };
if (_l9 isEqualTo "") then { _missing pushBack "ligne 9 (dégagement)"; };
if (_dc && {_dcInit isEqualTo ""}) then { _missing pushBack "initiales DANGER CLOSE"; };

_h set ["lines", [_l1, ["-", _l2] select (_l2 isNotEqualTo ""), ["-", _l3] select (_l3 isNotEqualTo ""), _l4, _l5, _l6, _l7, _l8, _l9]];
_h set ["remarks", _rem joinString " · "];
_h set ["type", _type];
_h set ["method", _method];
_h set ["ord", _ordName];
_h set ["code", _code];
_h set ["target", _tgt];
_h set ["fah", _fah];
_h set ["dc", _dc];
_h set ["friend", _friend];
_h set ["risk", _risk];
_h set ["ltl", _ltl];
_h set ["missing", _missing joinString ", "];
_h set ["auto", _auto];
_h set ["autoOn", _autoOn];
_h
