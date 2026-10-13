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
    Renvoie un hashmap : lines, remarks, type, method, ord, code, target, fah, dc, friend [distance, direction],
    risk (distance de risque de l'armement), ltl, missing (ce qui empêche l'envoi, "" sinon).
*/
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
private _card = { params ["_deg"]; ["NORTH", "NORTHEAST", "EAST", "SOUTHEAST", "SOUTH", "SOUTHWEST", "WEST", "NORTHWEST"] select ((round (_deg / 45)) mod 8) };
private _h = createHashMap;
private _tgt = _s getOrDefault ["target", []];
private _ip = _s getOrDefault ["ip", []];
private _air = _s getOrDefault ["air", objNull];
private _hasT = (count _tgt) >= 2;
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

// Ami le plus proche de la cible (au sol).
private _friend = [];
if (_hasT) then {
    private _fr = (allUnits select { alive _x && {side group _x isEqualTo side group player} && {!((vehicle _x) isKindOf "Air")} && {(_x distance2D _tgt) < 3000} }) apply { [_x distance2D _tgt, _x] };
    _fr sort true;
    if ((count _fr) > 0) then { (_fr select 0) params ["_d", "_u"]; _friend = [round _d, [_tgt getDir _u] call _card]; };
};
private _dc = _hasT && {(count _friend) > 0} && {_risk > 0} && {(_friend select 0) < _risk};

private _l1 = trim (_s getOrDefault ["jIp", ""]);
private _l2 = "";
private _l3 = "";
if ((count _ip) >= 2 && _hasT) then {
    _l2 = format ["%1°", [round (_ip getDir _tgt), 3] call CBA_fnc_formatNumber];
    _l3 = format ["%1 NM", ((_ip distance2D _tgt) / 1852) toFixed 1];
};
private _l4 = if (_hasT) then { format ["%1 ft MSL", round ((getTerrainHeightASL _tgt) * 3.28084)] } else { "" };
private _l5 = trim (_s getOrDefault ["j5", ""]);
private _l6 = if (_hasT) then { [_tgt, 10] call comspec_atak_native_fnc_gridRef } else { "" };
private _l7 = switch (_mark) do {
    case "LASER": { format ["LASER %1", _code] };
    case "IR": { "IR POINTER" };
    case "FUMI": { "FUMIGÈNE" };
    default { "AUCUN" };
};
private _l8 = if ((count _friend) > 0) then { format ["%1 %2 m", _friend select 1, _friend select 0] } else { ["", "aucun à 3 km"] select _hasT };
private _l9 = trim (_s getOrDefault ["j9", ""]);

private _fah = trim (_s getOrDefault ["jFah", ""]);
private _ltl = "";
if (_mark isEqualTo "LASER" && _hasT) then { _ltl = format ["%1°", [round (player getDir _tgt), 3] call CBA_fnc_formatNumber]; };
private _rem = [];
if (_fah isNotEqualTo "") then { _rem pushBack format ["FAH %1", _fah]; };
if (_ltl isNotEqualTo "") then { _rem pushBack format ["LTL %1", _ltl]; };
private _dcInit = trim (_s getOrDefault ["jDc", ""]);
if (_dc) then { _rem pushBack format ["DANGER CLOSE%1", ["", format [", initiales %1", toUpper _dcInit]] select (_dcInit isNotEqualTo "")]; };
private _thr = trim (_s getOrDefault ["jThreat", ""]);
if (_thr isNotEqualTo "") then { _rem pushBack format ["Menaces : %1", _thr]; };
if (_ordName isNotEqualTo "") then { _rem pushBack format ["Armement : %1", _ordName]; };
_rem pushBack format ["TYPE %1, %2", _type, _method];

private _missing = [];
if (isNull _air) then { _missing pushBack "appareil"; };
if (!_hasT) then { _missing pushBack "cible"; };
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
_h
