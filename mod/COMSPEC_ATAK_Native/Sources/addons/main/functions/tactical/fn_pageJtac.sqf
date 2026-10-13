/*
    App JTAC : contrôle d'un appui aérien rapproché (CAS) du check-in au BDA, côté JTAC et côté équipage.
    Onglets JTAC :
      SITUATION  désignation (laser ACE, visée ou carte), cible (grille, altitude, amis, danger close),
                 appareils en station avec leur fiche de check-in (armement, carburant, altitude, nacelle)
      BRIEF      plan d'attaque (type de contrôle, méthode, armement, marquage) et 9-LINE calculé + remarques
      CONTRÔLE   état de la mission, readback, géométrie de l'appareil (FAH), marquage, autorisation de tir, BDA
      JOURNAL    les appels radio de la mission, horodatés
    Vue ÉQUIPAGE (joueur à bord d'un appareil qui a reçu un brief) : le 9-LINE reçu et les appels du pilote.
    Actions : fn_jtacAction ; 9-LINE : fn_jtacLines ; check-in : fn_jtacAir ; géométrie : fn_jtacGeometry.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Jtac", _s];
private _p = uiNamespace getVariable ["COMSPEC_ATAK_JtacPilot", createHashMap];
private _grey = "#8a9a93";
private _green = "#5cc76b";
private _amber = "#f2ab33";
private _red = "#e5483a";
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_jtacAction;", str _a, [str _v, _v] select (_v isEqualType 0)] };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_deg / 45)) mod 8) };
private _dist = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _steps = ["CHECK-IN", "BRIEF", "READBACK", "CORRÉLATION", "IN", "AUTORISÉ", "OFF", "BDA"];
// Frise de la mission : étapes passées en vert, étape en cours en couleur d'état, à venir en gris.
private _chips = {
    params ["_m"];
    private _st = _m getOrDefault ["step", 0];
    private _now = [_amber, _red] select (_m getOrDefault ["aborted", false]);
    if (_st isEqualTo 5 && {!(_m getOrDefault ["dry", false])}) then { _now = _red; };
    private _out = [];
    {
        private _i = _forEachIndex;
        private _lbl = _x;
        if (_i isEqualTo 5 && {_st >= 5}) then { _lbl = ["CLEARED HOT", "CONTINUE DRY"] select (_m getOrDefault ["dry", false]); };
        if (_i isEqualTo 3 && {_m getOrDefault ["aborted", false]}) then { _lbl = "ABORT"; };
        _out pushBack (switch (true) do {
            case (_i < _st): { format ["<t color='%1'>● %2</t>", _green, _lbl] };
            case (_i isEqualTo _st): { format ["<t color='%1' font='RobotoCondensedBold'>● %2</t>", _now, _lbl] };
            default { format ["<t color='#4a5550'>○ %1</t>", _lbl] };
        });
    } forEach _steps;
    _out joinString "  "
};
private _logRows = {
    params ["_m", ["_n", 60]];
    private _r = [];
    { _x params ["_t", "_who", "_txt"]; _r pushBack ["text", format ["<t size='0.85'><t color='%1' font='EtelkaMonospacePro'>%2</t>  <t font='RobotoCondensedBold'>%3</t>  %4</t>", _grey, _t, _who, _txt]]; } forEach ((_m getOrDefault ["log", []]) select [0, _n]);
    if ((count _r) isEqualTo 0) then { _r pushBack ["text", format ["<t color='%1'>Aucun appel pour l'instant.</t>", _grey]]; };
    _r
};
private _rows = [];

// Vue équipage disponible quand un brief a été reçu.
private _hasPilot = (count _p) > 0;
private _view = [_s getOrDefault ["view", "JTAC"], "JTAC"] select (!_hasPilot);
if (_hasPilot) then {
    _rows pushBack ["segment", "", [["JTAC", ["view", "JTAC"] call _act, _view isEqualTo "JTAC"], [format ["ÉQUIPAGE · %1", _p getOrDefault ["jtacName", ""]], ["view", "PILOT"] call _act, _view isEqualTo "PILOT"]]];
};

// ---------------------------------------------------------------- Vue ÉQUIPAGE
if (_view isEqualTo "PILOT") exitWith {
    private _d = _p getOrDefault ["data", []];
    _d params [["_lines", []], ["_rem", ""], ["_type", 2], ["_method", ""], ["_ord", ""], ["_code", 1111], ["_tpos", []], ["_fah", ""], ["_dc", false]];
    private _st = _p getOrDefault ["step", 0];
    private _veh = vehicle player;
    _rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_jtac.paa", format ["<t size='1.2' font='RobotoCondensedBold'>CAS · %1</t>%2<br/><t size='0.85' color='%3'>TYPE %4 · %5%6</t>",
        _p getOrDefault ["jtacName", ""], ["", format ["  <t font='RobotoCondensedBold' color='%1'>DANGER CLOSE</t>", _red]] select _dc, _grey, _type, _method, ["", format [" · %1", _ord]] select (_ord isNotEqualTo "")]];
    _rows pushBack ["text", format ["<t size='0.8'>%1</t>", [_p] call _chips]];
    _rows pushBack ["section", "9-LINE reçu", ""];
    {
        _rows pushBack ["line", str (_forEachIndex + 1), _x, _lines param [_forEachIndex, "-"], "auto", "", "-"];
    } forEach ["IP / BP", "Cap IP vers cible", "Distance IP vers cible", "Altitude cible", "Description", "Position", "Marquage", "Amis", "Dégagement"];
    if (_rem isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>Remarques : </t><t size='0.85'>%2</t>", _grey, _rem]]; };
    if ((count _tpos) >= 2) then {
        private _dd = _veh distance2D _tpos;
        _rows pushBack ["info", "Cible depuis l'appareil", format ["%1 · %2° · %3 NM", [_veh getDir _tpos] call _card, round (_veh getDir _tpos), (_dd / 1852) toFixed 1]];
    };
    _rows pushBack ["buttons", [["CARTE", ["pmap"] call _act]]];
    // Appels du pilote, dans l'ordre du déroulé ; ceux qui n'ont pas encore de sens restent grisés.
    private _pb = { params ["_lbl", "_code", ["_en", true], ["_tip", ""], ["_state", ""]]; [_lbl, ["pilot", _code] call _act, _state, _tip, _en] };
    _rows pushBack ["bar", [
        ["Brief", [["READBACK", "READBACK", _st >= 1 && {_st < 3}, "Relit lignes 4, 6 et l'axe d'attaque au JTAC", ["", "main"] select (_st isEqualTo 1)] call _pb, ["UNABLE", "UNABLE", _st >= 1, "Mission impossible (armement, carburant, menace)"] call _pb]],
        ["Corrélation", [["CONTACT", "CONTACT", _st >= 2] call _pb, ["TALLY", "TALLY", _st >= 2, "Cible vue"] call _pb, ["SPOT", "SPOT", _st >= 2, "Tache laser acquise"] call _pb, ["VISUAL", "VISUAL", _st >= 2, "Amis vus"] call _pb, ["NO JOY", "NOJOY", _st >= 2, "Cible non vue"] call _pb]]
    ]];
    _rows pushBack ["bar", [
        ["Attaque", [["IN", "IN", _st >= 2 && {_st < 5}, "Annonce du passage en attaque (direction et cap envoyés)", ["", "main"] select (_st isEqualTo 3)] call _pb, ["WEAPONS AWAY", "AWAY", _st isEqualTo 5 && {!(_p getOrDefault ["dry", false])}] call _pb, ["OFF", "OFF", _st >= 4 && {_st < 7}, "Dégagement"] call _pb]]
    ]];
    if (_st isEqualTo 5) then {
        _rows pushBack ["text", format ["<t size='1.1' font='RobotoCondensedBold' color='%1'>%2</t>", [_red, _amber] select (_p getOrDefault ["dry", false]), ["CLEARED HOT : tir autorisé", "CONTINUE DRY : pas de tir"] select (_p getOrDefault ["dry", false])]];
    };
    if (_p getOrDefault ["aborted", false]) then { _rows pushBack ["text", format ["<t size='1.1' font='RobotoCondensedBold' color='%1'>ABORT : ne pas tirer, dégagez et recommencez sur ordre</t>", _red]]; };
    _rows pushBack ["section", "Journal radio", ""];
    _rows append ([_p, 12] call _logRows);
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

// ---------------------------------------------------------------- Vue JTAC
private _tab = _s getOrDefault ["tab", "SIT"];
private _code = player getVariable ["ace_laser_code", 1111];
if !(_code isEqualType 0) then { _code = 1111; };
private _tgtNow = [] call comspec_atak_native_fnc_jtacTarget;
private _lasing = (count _tgtNow) > 0 && {(_tgtNow select 1) isEqualTo "laser"};
private _m = _s getOrDefault ["msn", createHashMap];
private _hasM = (count _m) > 0;
private _air = _s getOrDefault ["air", objNull];
if (!isNull _air && {!alive _air}) then { _air = objNull; _s set ["air", objNull]; };
private _b = [] call comspec_atak_native_fnc_jtacLines;
private _locked = _s getOrDefault ["target", []];
private _hasT = (count _locked) >= 2;

_rows pushBack ["hero", "\z\comspec_atak_native\addons\main\data\app_jtac.paa", format ["<t size='1.2' font='RobotoCondensedBold'>JTAC %1</t>  <t font='RobotoCondensedBold' color='%2'>● %3</t><br/><t size='0.85' color='%4'>Code laser %5 · %6</t>",
    [player] call comspec_atak_native_fnc_unitCallsign, [_grey, _red] select _lasing, ["LASER ÉTEINT", "LASER ACTIF"] select _lasing, _grey, _code,
    if (_hasM) then { format ["mission avec %1", groupId group (_m getOrDefault ["air", objNull])] } else { "aucune mission en cours" }]];
if (_hasM) then { _rows pushBack ["text", format ["<t size='0.8'>%1</t>", [_m] call _chips]]; };
_rows pushBack ["segment", "", [["SITUATION", "SIT"], ["BRIEF", "BRIEF"], ["CONTRÔLE", "CTRL"], ["JOURNAL", "LOG"]] apply { [_x select 0, ["tab", _x select 1] call _act, _tab isEqualTo (_x select 1)] }];

switch (_tab) do {
    case "BRIEF": {
        if (isNull _air) then { _rows pushBack ["text", format ["<t color='%1'>Choisissez d'abord un appareil dans SITUATION.</t>", _amber]]; };
        // Plan d'attaque.
        _rows pushBack ["section", "Plan d'attaque", "Game plan annoncé avant le 9-LINE"];
        private _type = _s getOrDefault ["type", 2];
        _rows pushBack ["segment", "Type de contrôle", [1, 2, 3] apply { [format ["TYPE %1", _x], ["type", _x] call _act, _x isEqualTo _type, ["Vous voyez l'appareil et la cible à chaque passe", "Vous voyez la cible ou l'appareil, pas forcément les deux", "Plusieurs passes autorisées dans une zone, sans contrôle de chaque passe"] select (_x - 1)] }];
        private _meth = _s getOrDefault ["method", "BOT"];
        _rows pushBack ["segment", "Méthode d'attaque", [["BOT", "Bomb on target : l'équipage voit la cible"], ["BOC", "Bomb on coordinates : tir sur la grille"]] apply { [_x select 0, ["method", _x select 0] call _act, (_x select 0) isEqualTo _meth, _x select 1] }];
        private _ords = if (isNull _air) then { [] } else { ([_air] call comspec_atak_native_fnc_jtacAir) getOrDefault ["ord", []] };
        if ((count _ords) > 0) then {
            _rows pushBack ["segment", "Armement demandé", (_ords select [0, 4]) apply { [_x select 0, ["ord", _x select 0] call _act, (_x select 0) isEqualTo (_b get "ord"), format ["%1 restant(s) · distance de risque %2 m", _x select 1, _x select 2]] }];
        };
        private _mark = _s getOrDefault ["mark", "LASER"];
        _rows pushBack ["segment", "Marquage de la cible", [["LASER", "LASER"], ["IR", "IR"], ["FUMIGÈNE", "FUMI"], ["AUCUN", "AUCUN"]] apply { [_x select 0, ["mark", _x select 1] call _act, (_x select 1) isEqualTo _mark] }];
        if (_mark isEqualTo "LASER") then {
            _rows pushBack ["segment", "Code laser", [1111, 1688, 1788, 1888] apply { [str _x, ["code", _x] call _act, _x isEqualTo _code] }];
        };
        // 9-LINE.
        private _ln = _b get "lines";
        _rows pushBack ["section", "9-LINE", ["Lignes 2, 3, 4, 6, 7 et 8 calculées", "Verrouillez d'abord une cible (SITUATION)"] select (!_hasT)];
        private _dcOn = _b get "dc";
        _rows pushBack ["line", "1", "IP / BP", _s getOrDefault ["jIp", ""], "edit", "jIp", "nom du point initial"];
        _rows pushBack ["buttons", [["POINTER L'IP SUR LA CARTE", ["pick", "ip"] call _act]]];
        _rows pushBack ["line", "2", "Cap IP vers cible", _ln select 1, "auto", "", "pointez l'IP"];
        _rows pushBack ["line", "3", "Distance IP vers cible", _ln select 2, "auto", "", "pointez l'IP"];
        _rows pushBack ["line", "4", "Altitude cible", _ln select 3, "auto", "", "verrouillez la cible"];
        _rows pushBack ["line", "5", "Description", _s getOrDefault ["j5", ""], "edit", "j5", "nombre, type, posture"];
        _rows pushBack ["line", "6", "Position", _ln select 5, "auto", "", "verrouillez la cible"];
        _rows pushBack ["line", "7", "Marquage", _ln select 6, "auto", "", ""];
        _rows pushBack ["line", "8", "Amis", _ln select 7, ["auto", "warn"] select _dcOn, "", "verrouillez la cible"];
        _rows pushBack ["line", "9", "Dégagement", _s getOrDefault ["j9", ""], "edit", "j9", "direction, altitude, point de retour"];
        // Remarques et restrictions.
        _rows pushBack ["section", "Remarques et restrictions", "Valeurs en couleur : calculées"];
        _rows pushBack ["line", "", "Axe final (FAH)", _s getOrDefault ["jFah", ""], "edit", "jFah", "270, 250-290 ou 270 ± 15"];
        if ((_b get "ltl") isNotEqualTo "") then { _rows pushBack ["line", "", "Ligne laser-cible", _b get "ltl", "auto", "", ""]; };
        _rows pushBack ["line", "", "Menaces", _s getOrDefault ["jThreat", ""], "edit", "jThreat", "sol-air, armes légères"];
        if (_dcOn) then {
            _rows pushBack ["text", format ["<t color='%1' font='RobotoCondensedBold'>DANGER CLOSE</t><t size='0.85'> : amis à %2 m de la cible, sous la distance de risque de l'armement (%3 m, indicatif). Le chef au sol accepte le risque et donne ses initiales.</t>", _red, (_b get "friend") select 0, _b get "risk"]];
            _rows pushBack ["line", "", "Initiales du chef", _s getOrDefault ["jDc", ""], "edit", "jDc", "accepte le risque"];
        };
        _rows pushBack ["text", format ["<t size='0.85' color='%1'>Remarques transmises : </t><t size='0.85'>%2</t>", _grey, _b get "remarks"]];
        private _miss = _b get "missing";
        if (_miss isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>Manque : %2</t>", _amber, _miss]]; };
        _rows pushBack ["buttons", [[["TRANSMETTRE LE 9-LINE", "RETRANSMETTRE (CORRECTION)"] select (_hasM && {(_m getOrDefault ["step", 0]) in [1, 2]}), ["brief"] call _act, true, _miss isEqualTo ""]]];
    };
    case "CTRL": {
        if (!_hasM) exitWith {
            _rows pushBack ["text", format ["<t color='%1'>Aucune mission : choisissez un appareil (SITUATION), remplissez le BRIEF et transmettez le 9-LINE.</t>", _grey]];
        };
        private _ma = _m getOrDefault ["air", objNull];
        private _st = _m getOrDefault ["step", 0];
        private _mb = _m getOrDefault ["brief", createHashMap];
        private _type = _mb getOrDefault ["type", 2];
        private _ai = isNull _ma || {((crew _ma) findIf { isPlayer _x }) < 0};
        // État de l'appareil.
        _rows pushBack ["section", format ["Appareil %1", groupId group _ma], [["équipage joueur : appels reçus sur son téléphone", "équipage IA : notez ses appels radio ci-dessous"] select _ai, "appareil perdu"] select (isNull _ma || {!alive _ma})];
        if (!isNull _ma && {alive _ma}) then {
            private _tp = _mb getOrDefault ["target", []];
            _rows pushBack ["info", "Altitude · vitesse", format ["%1 ft · %2 kt", round (((getPosASL _ma) select 2) * 3.28084), round ((speed _ma) * 0.539957)]];
            if ((count _tp) >= 2) then {
                _rows pushBack ["info", "Depuis la cible", format ["%1 · %2 NM", [_tp getDir _ma] call _card, ((_ma distance2D _tp) / 1852) toFixed 1]];
                private _g = [_ma, _m] call comspec_atak_native_fnc_jtacGeometry;
                if ((_g select 0) isNotEqualTo "") then { _rows pushBack ["info", "Géométrie", format ["<t color='%1'>%2</t>", [_amber, _green] select (_g select 1), _g select 0]]; };
            };
        };
        private _inC = _m getOrDefault ["inCheck", []];
        if (_st >= 4 && {(count _inC) > 0}) then { _rows pushBack ["info", "Au moment du IN", format ["<t color='%1'>%2</t>", [_red, _green] select (_inC select 1), _inC select 0]]; };
        // Readback.
        private _rb = _m getOrDefault ["readback", ""];
        if (_st >= 1 && {!(_m getOrDefault ["rbOk", false])}) then {
            _rows pushBack ["section", "Readback", "Lignes 4, 6 et restrictions relues par l'équipage"];
            _rows pushBack ["info", "Attendu", format ["%1, %2%3", (_mb get "lines") select 3, (_mb get "lines") select 5, ["", format [", FAH %1", _mb get "fah"]] select ((_mb get "fah") isNotEqualTo "")]];
            _rows pushBack ["info", "Reçu", [format ["<t color='%1'>en attente</t>", _amber], _rb] select (_rb isNotEqualTo "")];
            _rows pushBack ["buttons", [["READBACK CORRECT", ["call", "RBC"] call _act, true, _rb isNotEqualTo ""], ["CORRECTION", ["call", "CORRECTION"] call _act, false, _rb isNotEqualTo ""]]];
        };
        // Marquage et corrélation.
        private _corr = _m getOrDefault ["corr", []];
        _rows pushBack ["section", "Marquage et corrélation", [format ["équipage : %1", _corr joinString ", "], "équipage : rien annoncé"] select ((count _corr) isEqualTo 0)];
        _rows pushBack ["buttons", [["LASER ON", ["call", "LASER_ON"] call _act, false, _st >= 2], ["LASER OFF", ["call", "LASER_OFF"] call _act, false, _m getOrDefault ["laser", false]], ["MARQUE", ["call", "MARK"] call _act, false, _st >= 2]]];
        // Autorisation de tir : conditions de la doctrine, rappelées quand elles manquent.
        _rows pushBack ["section", "Autorisation", ["", "Type 3 : autorisation pour la zone, sans contrôle de chaque passe"] select (_type isEqualTo 3)];
        private _why = [];
        if (!(_m getOrDefault ["rbOk", false])) then { _why pushBack "readback correct"; };
        if (_type isEqualTo 3) then {
            if (_st < 3) then { _why pushBack "TALLY ou SPOT de l'équipage"; };
        } else {
            if (_st < 4 || {_st > 4}) then { _why pushBack "appel IN de l'appareil"; };
        };
        if ((_mb getOrDefault ["dc", false]) && {(_s getOrDefault ["jDc", ""]) isEqualTo ""}) then { _why pushBack "initiales DANGER CLOSE"; };
        private _canHot = (count _why) isEqualTo 0;
        if (!_canHot && {_st < 5}) then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>Avant d'autoriser : %2.</t>", _amber, _why joinString ", "]]; };
        _rows pushBack ["bar", [
            ["Tir", [[["CLEARED HOT", "CLEARED TO ENGAGE"] select (_type isEqualTo 3), ["call", "HOT"] call _act, "red", "Tir autorisé", _canHot], ["CONTINUE DRY", ["call", "DRY"] call _act, "", "Passe sans tir", _st isEqualTo 4]]],
            ["Arrêt", [["ABORT", ["call", "ABORT"] call _act, "red", "Arrêt immédiat de l'attaque", _st >= 2 && {_st < 7}]]]
        ]];
        // Appels du pilote entendus à la radio.
        private _rb2 = { params ["_lbl", "_code", ["_en", true]]; [_lbl, ["radio", _code] call _act, "", "Noter cet appel du pilote (reçu à la radio)", _en] };
        _rows pushBack ["bar", [
            ["Appels du pilote (radio)", [["READBACK", "READBACK", _st in [1]] call _rb2, ["TALLY", "TALLY", _st >= 2] call _rb2, ["SPOT", "SPOT", _st >= 2] call _rb2, ["IN", "IN", _st >= 2 && {_st < 5}] call _rb2, ["OFF", "OFF", _st >= 4 && {_st < 7}] call _rb2, ["UNABLE", "UNABLE"] call _rb2]]
        ]];
        // Bilan.
        if (_st >= 6) then {
            _rows pushBack ["section", "BDA", "Bilan des effets transmis à l'équipage"];
            _rows pushBack ["edit", "jBda", "Détail (ex. 2 véhicules détruits, infanterie dispersée)", _s getOrDefault ["jBda", ""]];
            _rows pushBack ["bar", [["Résultat", ["DÉTRUIT", "ENDOMMAGÉ", "SANS EFFET"] apply { [_x, ["bda", _x] call _act, "", "", _st < 7] }], ["Suite", [["RE-ATTACK", ["call", "REATTACK"] call _act, "", "Nouvelle passe sur la même cible", _st isEqualTo 6]]]]];
        };
        _rows pushBack ["buttons", [["NOUVELLE MISSION", ["new"] call _act, _st >= 7]]];
    };
    case "LOG": {
        if (!_hasM) exitWith { _rows pushBack ["text", format ["<t color='%1'>Aucune mission.</t>", _grey]]; };
        _rows pushBack ["section", "Journal radio", format ["mission avec %1", groupId group (_m getOrDefault ["air", objNull])]];
        _rows append ([_m] call _logRows);
    };
    default {
        // Désignation et cible.
        _rows pushBack ["section", "Cible", _s getOrDefault ["source", ""]];
        _rows pushBack ["buttons", [["VERROUILLER", ["lock"] call _act, true], ["SUR LA CARTE", ["pick", "tgt"] call _act], ["EFFACER", ["unlock"] call _act, false, _hasT]]];
        if (_hasT) then {
            private _d = player distance2D _locked;
            private _az = player getDir _locked;
            _rows pushBack ["info", "Grille", format ["<t font='EtelkaMonospacePro'>%1</t>", [_locked, 10] call comspec_atak_native_fnc_gridRef]];
            _rows pushBack ["info", "Altitude", format ["%1 ft MSL (%2 m)", round ((getTerrainHeightASL _locked) * 3.28084), round (getTerrainHeightASL _locked)]];
            _rows pushBack ["info", "Depuis moi", format ["%1 · %2° (%3 mils) %4", [_d] call _dist, round _az, round (_az * 17.7778), [_az] call _card]];
            private _fr = _b get "friend";
            _rows pushBack ["info", "Amis les plus proches", if ((count _fr) isEqualTo 0) then { format ["<t color='%1'>aucun à moins de 3 km</t>", _green] } else {
                format ["<t color='%1' font='RobotoCondensedBold'>%2 m au %3%4</t>", [_green, _red] select (_b get "dc"), _fr select 0, _fr select 1, ["", " · DANGER CLOSE"] select (_b get "dc")]
            }];
            _rows pushBack ["buttons", [["CARTE", ["map"] call _act]]];
        } else {
            _rows pushBack ["text", format ["<t color='%1'>Visez la cible ou désignez-la au laser puis VERROUILLER, ou pointez-la SUR LA CARTE.</t>", _grey]];
        };
        // Appareils en station : fiche de check-in.
        private _airs = vehicles select { _x isKindOf "Air" && {alive _x} && {(count crew _x) > 0} && {side group (effectiveCommander _x) isEqualTo side group player} && {!(_x isKindOf "UAV_01_base_F")} && {(_x distance2D player) < 40000} };
        _airs = (_airs apply { [_x distance2D player, _x] });
        _airs sort true;
        _airs = _airs apply { _x select 1 };
        _rows pushBack ["section", "Appareils en station", ["Aucun appareil ami en vol", format ["%1 en station · fiche de check-in", count _airs]] select ((count _airs) > 0)];
        {
            private _ci = [_x] call comspec_atak_native_fnc_jtacAir;
            private _isSel = _x isEqualTo _air;
            private _ords = (_ci get "ord") apply { format ["%1 × %2", _x select 1, _x select 0] };
            private _crewTxt = if (_ci get "ai") then { "équipage IA" } else { ((_ci get "players") apply { name _x }) joinString ", " };
            _rows pushBack ["person", getText (configOf _x >> "picture"), format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t size='0.85' color='%3'>%4</t><br/><t size='0.8' color='%3'>%5 · %6 ft · %7 kt · %8 NM %9 · carburant %10%11</t><br/><t size='0.8'>%12</t>",
                ["#c9d4cf", _green] select _isSel, _ci get "callsign", _grey, _ci get "type", _crewTxt, _ci get "alt", _ci get "kts",
                ((_x distance2D player) / 1852) toFixed 1, [player getDir _x] call _card, (str (_ci get "fuel")) + " %", ["", " · nacelle"] select (_ci get "tgp"),
                [_ords joinString " · ", "<t color='#8a9a93'>pas d'armement air-sol</t>"] select ((count _ords) isEqualTo 0)],
                [[["CHOISIR", "CHOISI"] select _isSel, ["air", _x call BIS_fnc_netId] call _act, _isSel]]];
        } forEach (_airs select [0, 8]);
        if (!isNull _air) then { _rows pushBack ["buttons", [["PRÉPARER LE BRIEF", ["tab", "BRIEF"] call _act, true]]]; };
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
