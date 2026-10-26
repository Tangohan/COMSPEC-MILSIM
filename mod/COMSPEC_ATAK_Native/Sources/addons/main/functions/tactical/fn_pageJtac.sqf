/*
    App JTAC : contrôle d'un appui aérien rapproché (CAS) du check-in au BDA, côté JTAC et côté équipage.
    Présentation façon terminal JTAC (KILSWITCH, Nett Warrior) : bandeau du terminal (indicatif, code et état du laser,
    heure), frise de la mission en cases (CHECK-IN … BDA), bandeau d'état en gros (ce qu'on attend, ce qu'on peut
    décider), fiche CAS dense en police à chasse fixe, gros boutons pour la décision de tir, journal radio horodaté.
    Onglets JTAC :
      SITU     désignation (laser ACE, visée ou carte), données de la cible (grille, altitude, azimut, amis),
               appareils en station avec leur fiche de check-in (armement, carburant, altitude, nacelle)
      9-LINE   game plan (type de contrôle, méthode, armement, marquage) et fiche 9-LINE calculée + remarques
      CARTE    carte tactique (fn_jtacDraw) : cible, IP, axe d'entrée, cône FAH, amis, LTL, appareils ;
               poser la cible ou l'IP d'un appui sur la carte
      CTRL     état de la mission, readback, géométrie de l'appareil (FAH), marquage, autorisation de tir, BDA
      LOG      les appels radio de la mission, horodatés
    En paysage (hors mini), la carte tactique reste affichée à gauche des onglets SITU, 9-LINE et CTRL.
    Vue ÉQUIPAGE (joueur à bord d'un appareil qui a reçu un brief) : le 9-LINE reçu et les appels du pilote.
    Actions : fn_jtacAction ; 9-LINE : fn_jtacLines ; check-in : fn_jtacAir ; géométrie : fn_jtacGeometry.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _fs = _l get "fontSmall";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Jtac", _s];
private _p = uiNamespace getVariable ["COMSPEC_ATAK_JtacPilot", createHashMap];
private _grey = "#8a9a93";
private _green = "#5cc76b";
private _amber = "#f2ab33";
private _red = "#e5483a";
private _cardBg = [0.045, 0.058, 0.052, 1];
private _act = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_jtacAction;", str _a, [str _v, _v] select (_v isEqualType 0)] };
private _card = { params ["_deg"]; ["N", "NE", "E", "SE", "S", "SW", "W", "NW"] select ((round (_deg / 45)) mod 8) };
private _dist = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _three = { params ["_d"]; [round _d, 3] call CBA_fnc_formatNumber };
// Texte en police à chasse fixe (valeurs d'un terminal).
private _mono = { params ["_t", ["_c", ""], ["_sz", 1], ["_bold", false]]; format ["<t font='%1' size='%2'%3>%4</t>", ["EtelkaMonospacePro", "EtelkaMonospaceProBold"] select _bold, _sz, ["", format [" color='%1'", _c]] select (_c isNotEqualTo ""), _t] };
private _clock = format ["%1L", ([dayTime, "HH:MM"] call BIS_fnc_timeToString) splitString ":" joinString ""];

// Frise de la mission : une case par étape (passée, en cours, à venir ; tir autorisé et ABORT en rouge).
private _steps = {
    params ["_m"];
    private _st = _m getOrDefault ["step", 0];
    private _ab = _m getOrDefault ["aborted", false];
    private _dry = _m getOrDefault ["dry", false];
    private _out = [];
    {
        private _i = _forEachIndex;
        private _lbl = _x;
        if (_i isEqualTo 5 && {_st >= 5}) then { _lbl = ["HOT", "DRY"] select _dry; };
        if (_i isEqualTo 3 && {_ab}) then { _lbl = "ABORT"; };
        _out pushBack [_lbl, switch (true) do {
            case (_i isEqualTo 3 && {_ab}): { "abort" };
            case (_i < _st): { "done" };
            case (_i isEqualTo _st): { ["now", "hot"] select (_i isEqualTo 5 && {!_dry}) };
            default { "todo" };
        }];
    } forEach ["CHECK-IN", "BRIEF", "READBACK", "CORREL", "IN", "CLEARED", "OFF", "BDA"];
    ["steps", _out]
};
// Bandeau d'état : gros titre (chasse fixe) + consigne en dessous.
private _banner = {
    params ["_title", "_sub", "_kind"];
    private _bg = (createHashMapFromArray [["red", [0.62, 0.13, 0.1, 1]], ["amber", [0.42, 0.29, 0.06, 1]], ["green", [0.12, 0.32, 0.16, 1]]]) getOrDefault [_kind, [0.07, 0.085, 0.078, 1]];
    ["banner", format ["%1%2", [_title, "#ffffff", 1.35, true] call _mono, ["", format ["<br/><t size='0.82' color='#e6ece9'>%1</t>", _sub]] select (_sub isNotEqualTo "")], _bg]
};
// Journal radio : heure, émetteur, appel ; appels critiques en rouge.
private _logRows = {
    params ["_m", ["_n", 60], ["_me", "JTAC"]];
    private _r = [];
    {
        _x params ["_t", "_who", "_txt"];
        private _crit = (["ABORT", "CLEARED HOT", "CLEARED TO ENGAGE", "DANGER CLOSE"] findIf { (_txt find _x) >= 0 }) >= 0;
        private _mine = _who isEqualTo _me;
        _r pushBack ["text", format ["%1  %2  <t size='0.88' color='%4'%5>%3</t>",
            [_t, _grey, 0.8] call _mono, [_who, [_green, "#7fd3e0"] select _mine, 0.8, true] call _mono, _txt,
            ["#e6ece9", _red] select _crit, ["", " font='COMSPEC_BarlowBold'"] select _crit], [[0.04, 0.05, 0.046, 1], [0.035, 0.044, 0.04, 1]] select ((_forEachIndex mod 2) isEqualTo 1)];
    } forEach ((_m getOrDefault ["log", []]) select [0, _n]);
    if ((count _r) isEqualTo 0) then { _r pushBack ["text", format ["<t color='%1'>Aucun appel pour l'instant.</t>", _grey]]; };
    _r
};
// Carte tactique (vue JTAC ou équipage) dans un rectangle de la zone de contenu ; vue gardée d'un rendu à l'autre.
private _mapAt = {
    params ["_rect", "_mode", ["_pts", []]];
    _rect params ["_mx", "_my", "_mw", "_mh"];
    private _bgm = ["COMSPEC_RscText", [_mx, _my, _mw, _mh]] call comspec_atak_native_fnc_pageCtrl;
    _bgm ctrlSetBackgroundColor [0.02, 0.028, 0.024, 1];
    private _map = ["COMSPEC_RscMapJtac", [_bx + _mx, _by + _my, _mw, _mh], "", false] call comspec_atak_native_fnc_pageCtrl;
    _map setVariable ["mode", _mode];
    _map ctrlAddEventHandler ["Draw", { params ["_c"]; [_c, _c getVariable ["mode", "JTAC"]] call comspec_atak_native_fnc_jtacDraw; }];
    _map ctrlAddEventHandler ["MouseButtonClick", {
        params ["_c", "_btn", "_x", "_y"];
        private _k = (uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap]) getOrDefault ["mapPick", ""];
        if (_btn isEqualTo 0 && {_k isNotEqualTo ""}) then { ["mapSet", [_k, _c ctrlMapScreenToWorld [_x, _y]]] call comspec_atak_native_fnc_jtacAction; };
    }];
    // Une carte toute neuve affiche d'abord le coin haut gauche du monde : fn_jtacDraw n'enregistre la vue qu'une fois
    // la carte calée sur ce but (sinon chaque rafraîchissement de la page repartait de ce coin).
    private _view = uiNamespace getVariable [format ["COMSPEC_ATAK_JtacView_%1", _mode], []];
    if ((count _view) isNotEqualTo 2) then {
        // Première ouverture : cible, IP et JTAC dans le cadre.
        private _pp = (_pts select { (count _x) >= 2 }) + [getPosASL player];
        private _xs = _pp apply { _x select 0 };
        private _ys = _pp apply { _x select 1 };
        private _span = ((selectMax _xs - selectMin _xs) max (selectMax _ys - selectMin _ys)) max 1500;
        _view = [((_span / worldSize) * 1.5) min 1, [(selectMin _xs + selectMax _xs) / 2, (selectMin _ys + selectMax _ys) / 2]];
    };
    _map setVariable ["comspec_jtacAim", _view];
    _map setVariable ["comspec_jtacBorn", diag_tickTime];
    _map ctrlMapAnimAdd [0, _view select 0, [(_view select 1) select 0, (_view select 1) select 1, 0]];
    ctrlMapAnimCommit _map;
    uiNamespace setVariable ["COMSPEC_ATAK_JtacMap", _map];
    _map
};
// Défilement de la fiche gardé d'un rendu à l'autre (fn_pageRender relève les groupes avant d'effacer la page) :
// le rafraîchissement du contrôle (3 s) ou un appel radio reçu ne ramène plus la fiche en haut.
private _keepScroll = {
    params ["_grp", "_name"];
    private _groups = uiNamespace getVariable ["COMSPEC_ATAK_ScrollGroups", []];
    _groups pushBack [_name, _grp];
    uiNamespace setVariable ["COMSPEC_ATAK_ScrollGroups", _groups];
    private _v = (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["scrollGroups", createHashMap]) getOrDefault [_name, []]) param [0, -1];
    if (_v <= 0) exitWith {};
    _grp ctrlSetScrollValues [_v, -1];
    // Encore à l'image suivante : la hauteur du contenu n'est connue qu'une fois les contrôles posés.
    [{ params ["_g", "_v"]; if (!isNull _g) then { _g ctrlSetScrollValues [_v, -1]; }; }, [_grp, _v]] call CBA_fnc_execNextFrame;
};
// Paysage plein écran : carte à gauche, onglets à droite.
private _split = (_l getOrDefault ["landscape", false]) && {!(_l getOrDefault ["mini", false])};
private _mapW = [0, _bw * 0.44] select _split;
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
    _d params [["_lines", []], ["_rem", ""], ["_type", 2], ["_method", ""], ["_ord", ""], ["_code", 1111], ["_tpos", []], ["_fah", ""], ["_dc", false], ["_ipPos", []]];
    private _st = _p getOrDefault ["step", 0];
    private _dry = _p getOrDefault ["dry", false];
    private _veh = vehicle player;
    _rows pushBack ["strip", format ["%1  %2", ["CAS", _amber, 1, true] call _mono, ["JTAC " + (_p getOrDefault ["jtacName", ""]), "", 0.9] call _mono], format ["%1%2  %3",
        [format ["T%1 %2%3", _type, _method, ["", " " + _ord] select (_ord isNotEqualTo "")], _grey, 0.85] call _mono, ["", "  " + (["DANGER CLOSE", _red, 0.85, true] call _mono)] select _dc, [_clock, "", 0.85] call _mono]];
    _rows pushBack ([_p] call _steps);
    _rows pushBack (switch (true) do {
        case (_p getOrDefault ["aborted", false]): { ["ABORT ABORT ABORT", "Ne tirez pas, dégagez et recommencez sur ordre", "red"] call _banner };
        case (_st isEqualTo 5): { [["CLEARED HOT", "CONTINUE DRY"] select _dry, ["Tir autorisé", "Passe sans tir"] select _dry, ["red", "amber"] select _dry] call _banner };
        case (_st isEqualTo 4): { ["IN", "Attendez l'autorisation du JTAC", "amber"] call _banner };
        case (_st isEqualTo 3): { ["CORRÉLÉ", "Annoncez IN au début de la passe", "green"] call _banner };
        case (_st isEqualTo 2): { ["READBACK CORRECT", "Corrélez la cible : TALLY, SPOT, CONTACT", "green"] call _banner };
        case (_st isEqualTo 6): { ["OFF", "Attendez le BDA", ""] call _banner };
        case (_st >= 7): { ["BDA REÇU", "Mission terminée", "green"] call _banner };
        default { ["9-LINE REÇU", "Relisez les lignes 4, 6 et les restrictions (READBACK)", "amber"] call _banner };
    });
    _rows pushBack ["section", "9-LINE", ""];
    {
        _rows pushBack ["line", str (_forEachIndex + 1), _x, _lines param [_forEachIndex, "-"], "auto", "", "-", "mono"];
    } forEach ["IP / BP", "Cap", "Distance", "Altitude cible", "Description", "Position cible", "Marquage", "Amis", "Dégagement"];
    if (_rem isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.72' color='%1'>REMARQUES / RESTRICTIONS</t><br/>%2", _grey, [_rem, "", 0.82] call _mono], _cardBg]; };
    if ((count _tpos) >= 2) then {
        private _dd = _veh distance2D _tpos;
        _rows pushBack ["line", "", "Cible depuis l'appareil", format ["%1° %2 · %3 NM", [_veh getDir _tpos] call _three, [_veh getDir _tpos] call _card, (_dd / 1852) toFixed 1], "auto", "", "", "mono"];
    };
    // Appels du pilote, dans l'ordre du déroulé ; ceux qui n'ont pas encore de sens restent grisés.
    private _pb = { params ["_lbl", "_code", ["_en", true], ["_tip", ""], ["_state", ""]]; [_lbl, ["pilot", _code] call _act, _state, _tip, _en] };
    _rows pushBack ["big", [
        ["READBACK", ["pilot", "READBACK"] call _act, ["", "amber"] select (_st isEqualTo 1), _st >= 1 && {_st < 3}, "Relit lignes 4, 6 et l'axe d'attaque au JTAC"],
        ["IN", ["pilot", "IN"] call _act, ["", "amber"] select (_st isEqualTo 3), _st >= 2 && {_st < 5}, "Annonce du passage en attaque (direction et cap envoyés)"],
        ["WEAPONS AWAY", ["pilot", "AWAY"] call _act, ["", "red"] select (_st isEqualTo 5 && {!_dry}), _st isEqualTo 5 && {!_dry}]
    ]];
    _rows pushBack ["bar", [
        ["Corrélation", [["CONTACT", "CONTACT", _st >= 2] call _pb, ["TALLY", "TALLY", _st >= 2, "Cible vue"] call _pb, ["SPOT", "SPOT", _st >= 2, "Tache laser acquise"] call _pb, ["VISUAL", "VISUAL", _st >= 2, "Amis vus"] call _pb, ["NO JOY", "NOJOY", _st >= 2, "Cible non vue"] call _pb]],
        ["Passe", [["OFF", "OFF", _st >= 4 && {_st < 7}, "Dégagement"] call _pb, ["UNABLE", "UNABLE", _st >= 1, "Mission impossible (armement, carburant, menace)"] call _pb, ["CARTE ATAK", ["pmap"] call _act, "", "Ouvre la carte du téléphone sur la cible", true]]]
    ]];
    _rows pushBack ["section", "Journal radio", ""];
    _rows append ([_p, 12, [player] call comspec_atak_native_fnc_unitCallsign] call _logRows);
    if (_split) then { [[0, 0, _mapW, _bh], "PILOT", [_tpos, _ipPos]] call _mapAt; };
    [[_rows, [_mapW, 0, _bw - _mapW, _bh]] call comspec_atak_native_fnc_formRender, "JTAC:PILOT"] call _keepScroll;
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
// 9-LINE d'abord : il complète l'appareil, la cible (laser) et l'IP quand rien n'est choisi.
private _b = [] call comspec_atak_native_fnc_jtacLines;
private _air = _s getOrDefault ["air", objNull];
if (!isNull _air && {!alive _air}) then { _air = objNull; _s set ["air", objNull]; };
private _locked = _s getOrDefault ["target", []];
private _hasT = (count _locked) >= 2;
private _ma = _m getOrDefault ["air", objNull];
private _mb = _m getOrDefault ["brief", createHashMap];
private _stM = _m getOrDefault ["step", 0];

// Bandeau du terminal : indicatif, laser (code et état), heure ; mission en cours en dessous.
_rows pushBack ["strip", format ["%1  %2", ["JTAC", _amber, 1, true] call _mono, [[player] call comspec_atak_native_fnc_unitCallsign, "", 0.9] call _mono],
    format ["%1 %2  %3", [format ["LSR %1", _code], _grey, 0.85] call _mono, [["○ OFF", "● ON"] select _lasing, [_grey, _red] select _lasing, 0.85, true] call _mono, [_clock, "", 0.85] call _mono]];
if (_hasM) then {
    _rows pushBack ["strip", format ["%1  %2", ["MSN", _grey, 0.8] call _mono, [format ["%1 · T%2 %3%4", groupId group _ma, _mb getOrDefault ["type", 2], _mb getOrDefault ["method", ""], ["", " · " + (_mb getOrDefault ["ord", ""])] select ((_mb getOrDefault ["ord", ""]) isNotEqualTo "")], "", 0.85, true] call _mono],
        ["", ["DANGER CLOSE", _red, 0.85, true] call _mono] select (_mb getOrDefault ["dc", false]), [0.03, 0.04, 0.035, 1]];
    _rows pushBack ([_m] call _steps);
};
_rows pushBack ["segment", "", [["SITU", "SIT"], ["9-LINE", "BRIEF"], ["CARTE", "MAP"], ["CTRL", "CTRL"], ["LOG", "LOG"]] apply { [_x select 0, ["tab", _x select 1] call _act, _tab isEqualTo (_x select 1)] }];

switch (_tab) do {
    case "MAP": {};
    case "BRIEF": {
        if (isNull _air) then { _rows pushBack ["text", format ["<t color='%1'>Aucun appareil ami en vol : la fiche se prépare quand même, choisissez l'appareil dans SITU dès son check-in.</t>", _amber]]; };
        // Game plan.
        _rows pushBack ["section", "Game plan", "Annoncé avant le 9-LINE"];
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
        // Fiche 9-LINE : lignes numérotées, valeurs en chasse fixe, calculées en couleur.
        private _ln = _b get "lines";
        private _dcOn = _b get "dc";
        // Champs à saisie : la proposition automatique (fn_jtacLines) en vert tant que le joueur ne l'a pas changée.
        private _au = _b getOrDefault ["auto", createHashMap];
        private _fld = { params ["_k"]; private _v = _s getOrDefault [_k, ""]; [[_v, "edit"], [_au getOrDefault [_k, ""], "autoedit"]] select ((trim _v) isEqualTo "" && {(_au getOrDefault [_k, ""]) isNotEqualTo ""}) };
        // Complétion automatique : réglage admin Athena (native_jtac_autofill), coupée par défaut (réalisme).
        private _autoOn = _b getOrDefault ["autoOn", false];
        private _hint = switch (true) do {
            case (!_autoOn): { "Lignes 2, 3, 4, 6, 7, 8 calculées · le reste à saisir" };
            case (!_hasT): { "Rien à compléter sans cible" };
            default { "En vert : complété automatiquement, modifiable" };
        };
        _rows pushBack ["strip", ["CAS BRIEF · 9-LINE", "", 0.9, true] call _mono, [_hint, _grey, 0.72] call _mono];
        if (!_hasT) then {
            _rows pushBack ["text", format ["<t color='%1' size='0.9'>%2</t>", _amber, ["Il faut d'abord une cible : VERROUILLER dans SITU en la visant ou en la désignant au laser, ou POSER LA CIBLE sur la carte ci-dessous. Les lignes 2, 3, 4, 6, 7 et 8 se calculent ensuite ; IP, lignes 5 et 9 et remarques sont à saisir.", "Pour compléter la fiche, il faut une cible : VERROUILLER dans SITU en la visant ou en la désignant au laser (prise toute seule si votre laser est allumé), ou POSER LA CIBLE sur la carte ci-dessous. Tout le reste se remplit ensuite."] select _autoOn], [0.2, 0.14, 0.03, 1]];
        };
        (["jIp"] call _fld) params ["_v1", "_k1"];
        _rows pushBack ["line", "1", "IP / BP", _v1, _k1, "jIp", "nom du point initial", "mono"];
        _rows pushBack ["line", "2", "Cap magnétique", _ln select 1, "auto", "", "posez l'IP", "mono"];
        _rows pushBack ["line", "3", "Distance", _ln select 2, "auto", "", "posez l'IP", "mono"];
        _rows pushBack ["line", "4", "Altitude cible", _ln select 3, "auto", "", "verrouillez la cible", "mono"];
        (["j5"] call _fld) params ["_v5", "_k5"];
        _rows pushBack ["line", "5", "Description", _v5, _k5, "j5", "nombre, type, posture", "mono"];
        _rows pushBack ["line", "6", "Position cible", _ln select 5, "auto", "", "verrouillez la cible", "mono"];
        _rows pushBack ["line", "7", "Marquage", _ln select 6, "auto", "", "", "mono"];
        _rows pushBack ["line", "8", "Amis", _ln select 7, ["auto", "warn"] select _dcOn, "", "verrouillez la cible", "mono"];
        (["j9"] call _fld) params ["_v9", "_k9"];
        _rows pushBack ["line", "9", "Dégagement", _v9, _k9, "j9", "direction, altitude, point de retour", "mono"];
        if (_autoOn) then {
            _rows pushBack ["buttons", [["POSER L'IP SUR LA CARTE", ["pickIn", "ip"] call _act], ["IP AUTO", ["ipAuto"] call _act, false, !(_s getOrDefault ["ipAuto", true]), "L'IP est de nouveau proposé à 5 NM de la cible, côté appareil"], ["POSER LA CIBLE", ["pickIn", "tgt"] call _act]]];
        } else {
            _rows pushBack ["buttons", [["POSER L'IP SUR LA CARTE", ["pickIn", "ip"] call _act], ["POSER LA CIBLE", ["pickIn", "tgt"] call _act]]];
        };
        // Remarques et restrictions.
        _rows pushBack ["strip", ["REMARQUES / RESTRICTIONS", "", 0.9, true] call _mono, ""];
        (["jFah"] call _fld) params ["_vF", "_kF"];
        _rows pushBack ["line", "", "Axe final (FAH)", _vF, _kF, "jFah", "270, 250-290 ou 270 ± 15", "mono"];
        if ((_b get "ltl") isNotEqualTo "") then { _rows pushBack ["line", "", "Ligne laser-cible", _b get "ltl", "auto", "", "", "mono"]; };
        (["jThreat"] call _fld) params ["_vT", "_kT"];
        _rows pushBack ["line", "", "Menaces", _vT, _kT, "jThreat", "sol-air, armes légères", "mono"];
        if (_dcOn) then {
            _rows pushBack ["text", format ["<t color='%1' font='COMSPEC_BarlowBold'>DANGER CLOSE</t><t size='0.85'> : amis à %2 m de la cible, sous la distance de risque de l'armement (%3 m, indicatif). Le chef au sol accepte le risque et donne ses initiales.</t>", _red, (_b get "friend") select 0, _b get "risk"], [0.2, 0.06, 0.05, 1]];
            _rows pushBack ["line", "", "Initiales du chef", _s getOrDefault ["jDc", ""], "edit", "jDc", "accepte le risque", "mono"];
        };
        _rows pushBack ["text", format ["<t size='0.72' color='%1'>TRANSMIS EN REMARQUES</t><br/>%2", _grey, [_b get "remarks", "", 0.82] call _mono], _cardBg];
        private _miss = _b get "missing";
        if (_miss isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>Il manque : %2.</t>", _amber, _miss]]; };
        _rows pushBack ["big", [[["TRANSMETTRE LE 9-LINE", "RETRANSMETTRE (CORRECTION)"] select (_hasM && {_stM in [1, 2]}), ["brief"] call _act, "green", _miss isEqualTo "", "L'équipage relira les lignes 4, 6 et les restrictions"]]];
        // Outil carte IP → cible (cap magnétique, distance, altitude : fn_jtacTool) et 9-LINE sur un autre écran : page
        // Athena /9-line (QR code fixe de l'adresse de production, tools/gen_remote_qr.py), sur le vrai téléphone ou dans
        // une seconde fenêtre du PC.
        _rows pushBack ["buttons", [
            ["OUTIL CARTE IP → CIBLE", { ["MAP"] call comspec_atak_native_fnc_navigate; [{ ["IPTGT"] call comspec_atak_native_fnc_mapToolSet; ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }, false, true, "Clic sur l'IP puis sur la cible : cap magnétique, distance, altitude, repris ici"],
            ["OUVRIR LA PAGE 9-LINE", {
                private _url = "https://athena.ttrd.fr/public/9-line";
                private _r = (["COMSPECATAKNativeExtension", "COMSPECExtension"] select ([] call comspec_atak_native_fnc_bridge)) callExtension ["OpenAthenaLink", [_url]];
                if (_r isEqualType []) then { _r = _r param [0, ""]; };
                if ((_r select [0, 2]) isNotEqualTo "OK") then { ["INFO", format ["Ouvrez %1 dans votre navigateur", _url], 6, 30] call comspec_atak_native_fnc_notify; };
            }, false, true, "Ouvre le 9-LINE dans votre navigateur (seconde fenêtre, second écran)"]
        ]];
        _rows append [
            ["section", "9-LINE sur un autre écran", "Scannez avec votre vrai téléphone, connecté à votre compte Athena : le dernier 9-LINE transmis s'affiche en grand, avec un calculateur IP → cible."],
            ["image", "\z\comspec_atak_native\addons\main\data\nineline_qr_ca.paa"],
            ["text", "<t size='0.85' color='#8a9a93'>https://athena.ttrd.fr/public/9-line</t>"]
        ];
    };
    case "CTRL": {
        if (!_hasM) exitWith {
            _rows pushBack ["text", format ["<t color='%1'>Aucune mission : choisissez un appareil (SITU), remplissez le 9-LINE et transmettez-le.</t>", _grey]];
        };
        private _st = _stM;
        private _type = _mb getOrDefault ["type", 2];
        private _ai = isNull _ma || {((crew _ma) findIf { isPlayer _x }) < 0};
        private _dry = _m getOrDefault ["dry", false];
        // Bandeau d'état : ce qu'on attend de l'appareil, ou la décision à prendre.
        _rows pushBack (switch (true) do {
            case (_m getOrDefault ["aborted", false]): { ["ABORT", "Attaque arrêtée : RE-ATTACK ou nouveau brief", "red"] call _banner };
            case (_st isEqualTo 1): { ["ATTENTE READBACK", "L'équipage relit les lignes 4, 6 et les restrictions", "amber"] call _banner };
            case (_st isEqualTo 2): { ["CORRÉLATION", "Marquez la cible, attendez TALLY ou SPOT", "amber"] call _banner };
            case (_st isEqualTo 3): { ["CORRÉLÉ", ["Attendez l'appel IN de l'appareil", "Type 3 : vous pouvez autoriser la zone"] select (_type isEqualTo 3), "green"] call _banner };
            case (_st isEqualTo 4): { [format ["%1 IN", groupId group _ma], "Décision : CLEARED HOT, CONTINUE DRY ou ABORT", "red"] call _banner };
            case (_st isEqualTo 5): { [[["CLEARED HOT", "CLEARED TO ENGAGE"] select (_type isEqualTo 3), "CONTINUE DRY"] select _dry, ["Tir autorisé, attendez OFF", "Passe sans tir, attendez OFF"] select _dry, ["red", "amber"] select _dry] call _banner };
            case (_st isEqualTo 6): { ["OFF", "Transmettez le BDA ou demandez une nouvelle passe", ""] call _banner };
            default { ["MISSION TERMINÉE", "BDA transmis", "green"] call _banner };
        });
        // État de l'appareil.
        _rows pushBack ["strip", format ["%1  %2", ["ACFT", _grey, 0.8] call _mono, [groupId group _ma, "", 0.9, true] call _mono], [[["équipage joueur : appels sur son téléphone", "équipage IA : notez ses appels radio"] select _ai, "appareil perdu"] select (isNull _ma || {!alive _ma}), _grey, 0.72] call _mono];
        if (!isNull _ma && {alive _ma}) then {
            private _tp = _mb getOrDefault ["target", []];
            _rows pushBack ["line", "", "Alt · vitesse", format ["%1 FT · %2 KT", round (((getPosASL _ma) select 2) * 3.28084), round ((speed _ma) * 0.539957)], "auto", "", "", "mono"];
            if ((count _tp) >= 2) then {
                _rows pushBack ["line", "", "Depuis la cible", format ["%1 · %2 NM", [_tp getDir _ma] call _card, ((_ma distance2D _tp) / 1852) toFixed 1], "auto", "", "", "mono"];
                private _g = [_ma, _m] call comspec_atak_native_fnc_jtacGeometry;
                if ((_g select 0) isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.72' color='%1'>GÉOMÉTRIE</t><br/>%2", _grey, [_g select 0, [_amber, _green] select (_g select 1), 0.8] call _mono], _cardBg]; };
            };
        };
        private _inC = _m getOrDefault ["inCheck", []];
        if (_st >= 4 && {(count _inC) > 0}) then { _rows pushBack ["text", format ["<t size='0.72' color='%1'>AU MOMENT DU IN</t><br/>%2", _grey, [_inC select 0, [_red, _green] select (_inC select 1), 0.8] call _mono], _cardBg]; };
        // Readback.
        private _rb = _m getOrDefault ["readback", ""];
        if (_st >= 1 && {!(_m getOrDefault ["rbOk", false])}) then {
            _rows pushBack ["section", "Readback", "Lignes 4, 6 et restrictions relues par l'équipage"];
            _rows pushBack ["line", "", "Attendu", format ["%1, %2%3", (_mb get "lines") select 3, (_mb get "lines") select 5, ["", format [", FAH %1", _mb get "fah"]] select ((_mb get "fah") isNotEqualTo "")], "auto", "", "", "mono"];
            _rows pushBack ["line", "", "Reçu", _rb, ["auto", "warn"] select (_rb isEqualTo ""), "", "en attente", "mono"];
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
        _rows pushBack ["big", [
            [["CLEARED HOT", "CLEARED TO ENGAGE"] select (_type isEqualTo 3), ["call", "HOT"] call _act, "red", _canHot, "Tir autorisé"],
            ["CONTINUE DRY", ["call", "DRY"] call _act, "amber", _st isEqualTo 4, "Passe sans tir"]
        ]];
        _rows pushBack ["big", [["ABORT ABORT ABORT", ["call", "ABORT"] call _act, "red", _st >= 2 && {_st < 7}, "Arrêt immédiat de l'attaque"]]];
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
        // Derniers appels, sans changer d'onglet.
        _rows pushBack ["section", "Derniers appels", ""];
        _rows append ([_m, 4] call _logRows);
        _rows pushBack ["buttons", [["NOUVELLE MISSION", ["new"] call _act, _st >= 7]]];
    };
    case "LOG": {
        if (!_hasM) exitWith { _rows pushBack ["text", format ["<t color='%1'>Aucune mission.</t>", _grey]]; };
        _rows pushBack ["strip", ["JOURNAL RADIO", "", 0.9, true] call _mono, [format ["avec %1", groupId group _ma], _grey, 0.8] call _mono];
        _rows append ([_m] call _logRows);
    };
    default {
        // Désignation et cible.
        _rows pushBack ["section", "Cible", _s getOrDefault ["source", ""]];
        _rows pushBack ["buttons", [["VERROUILLER", ["lock"] call _act, true], ["SUR LA CARTE", ["pickIn", "tgt"] call _act], ["EFFACER", ["unlock"] call _act, false, _hasT]]];
        if (_hasT) then {
            private _d = player distance2D _locked;
            private _az = player getDir _locked;
            _rows pushBack ["line", "", "Grille", [_locked, 10] call comspec_atak_native_fnc_gridRef, "auto", "", "", "mono"];
            _rows pushBack ["line", "", "Altitude", format ["%1 FT MSL (%2 m)", round ((getTerrainHeightASL _locked) * 3.28084), round (getTerrainHeightASL _locked)], "auto", "", "", "mono"];
            _rows pushBack ["line", "", "Depuis moi", format ["%1° %2 · %3 MIL · %4", [_az] call _three, [_az] call _card, round (_az * 17.7778), [_d] call _dist], "auto", "", "", "mono"];
            private _fr = _b get "friend";
            _rows pushBack ["line", "", "Amis les plus proches", if ((count _fr) isEqualTo 0) then { "aucun à moins de 3 km" } else { format ["%1 m %2%3", _fr select 0, _fr select 1, ["", " · DANGER CLOSE"] select (_b get "dc")] }, ["auto", "warn"] select (_b get "dc"), "", "", "mono"];
        } else {
            _rows pushBack ["text", format ["<t color='%1'>Visez la cible ou désignez-la au laser puis VERROUILLER, ou posez-la SUR LA CARTE.</t>", _grey]];
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
            private _ords = (_ci get "ord") apply { format ["%1×%2", _x select 1, _x select 0] };
            private _crewTxt = if (_ci get "ai") then { "équipage IA" } else { ((_ci get "players") apply { name _x }) joinString ", " };
            _rows pushBack ["person", getText (configOf _x >> "picture"), format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t>  <t size='0.82' color='%3'>%4 · %5</t><br/>%6<br/>%7",
                ["#c9d4cf", _green] select _isSel, _ci get "callsign", _grey, _ci get "type", _crewTxt,
                [format ["%1FT %2KT %3NM %4 FUEL %5", _ci get "alt", _ci get "kts", ((_x distance2D player) / 1852) toFixed 1, [player getDir _x] call _card, (str (_ci get "fuel")) + "%" + (["", " TGP"] select (_ci get "tgp"))], "#c9d4cf", 0.72] call _mono,
                [[_ords joinString "  ", "pas d'armement air-sol"] select ((count _ords) isEqualTo 0), [_amber, _grey] select ((count _ords) isEqualTo 0), 0.72] call _mono],
                [[["CHOISIR", "CHOISI"] select _isSel, ["air", _x call BIS_fnc_netId] call _act, _isSel]]];
        } forEach (_airs select [0, 8]);
        if (!isNull _air) then { _rows pushBack ["big", [["PRÉPARER LE 9-LINE", ["tab", "BRIEF"] call _act, "green", true]]]; };
    };
};

private _ipPos = _s getOrDefault ["ip", []];
private _tgtMap = [_locked, _mb getOrDefault ["target", _locked]] select (_hasM && {_stM < 7});
if (_tab isEqualTo "MAP") exitWith {
    // Carte tactique : en-tête, carte, barre d'outils (centrer, poser la cible ou l'IP d'un appui).
    private _pick = _s getOrDefault ["mapPick", ""];
    if (_pick isNotEqualTo "") then {
        _rows pushBack ["strip", [["TOUCHEZ LA CARTE : POSITION DE LA CIBLE", "TOUCHEZ LA CARTE : POSITION DE L'IP"] select (_pick isEqualTo "ip"), "#1a1206", 0.85, true] call _mono, "", [0.95, 0.67, 0.2, 1]];
    } else {
        if ((count _tgtMap) >= 2) then {
            _rows pushBack ["strip", [format ["TGT %1", [_tgtMap, 10] call comspec_atak_native_fnc_gridRef], "", 0.8, true] call _mono,
                [format ["%1° %2", [player getDir _tgtMap] call _three, [player distance2D _tgtMap] call _dist], _grey, 0.8] call _mono];
        } else {
            _rows pushBack ["strip", ["PAS DE CIBLE", _grey, 0.8, true] call _mono, ""];
        };
    };
    private _form = [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    private _hh = uiNamespace getVariable ["COMSPEC_ATAK_FormH", _bh * 0.3];
    _form ctrlSetPositionH _hh; _form ctrlCommit 0;
    private _fbg = _form getVariable ["bg", controlNull];
    if (!isNull _fbg) then { _fbg ctrlSetPositionH _hh; _fbg ctrlCommit 0; };
    private _rowH = (_l get "font") * 1.5;
    private _tbH = _rowH + _pad;
    [[0, _hh, _bw, _bh - _hh - _tbH], "JTAC", [_tgtMap, _ipPos]] call _mapAt;
    private _tbBg = ["COMSPEC_RscPanel", [0, _bh - _tbH, _bw, _tbH]] call comspec_atak_native_fnc_pageCtrl;
    _tbBg ctrlSetBackgroundColor [0.035, 0.045, 0.04, 1];
    private _btns = [
        ["CIBLE", ["center", "tgt"] call _act, (count _tgtMap) >= 2, ""],
        ["IP", ["center", "ip"] call _act, (count _ipPos) >= 2, ""],
        ["MOI", ["center", "me"] call _act, true, ""],
        ["ACFT", ["center", "air"] call _act, !isNull ([_air, _ma] select _hasM), ""],
        ["POSER TGT", ["mapPick", "tgt"] call _act, true, ["", "on"] select (_pick isEqualTo "tgt")],
        ["POSER IP", ["mapPick", "ip"] call _act, true, ["", "on"] select (_pick isEqualTo "ip")]
    ];
    private _n = count _btns;
    private _w1 = (_bw - 2 * _pad - (_n - 1) * _pad / 3) / _n;
    {
        _x params ["_t", "_code", "_en", "_state"];
        private _bt = ["COMSPEC_RscButton", [_pad + _forEachIndex * (_w1 + _pad / 3), _bh - _tbH + _pad / 2, _w1, _rowH], _t] call comspec_atak_native_fnc_pageCtrl;
        _bt ctrlSetFont "EtelkaMonospaceProBold";
        _bt ctrlSetFontHeight (_fs * 0.8);
        if (_state isEqualTo "on") then { _bt ctrlSetBackgroundColor [0.95, 0.67, 0.2, 1]; _bt ctrlSetTextColor [0.05, 0.04, 0.02, 1]; } else { _bt ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; _bt ctrlSetTextColor ([[0.45, 0.5, 0.47, 1], [0.92, 0.96, 0.93, 1]] select _en); };
        _bt ctrlEnable _en;
        _bt setVariable ["code", _code];
        _bt ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ [] call _this; }, _c getVariable ["code", {}]] call CBA_fnc_execNextFrame; }];
    } forEach _btns;
    true
};
if (_split) then { [[0, 0, _mapW, _bh], "JTAC", [_tgtMap, _ipPos]] call _mapAt; };
[[_rows, [_mapW, 0, _bw - _mapW, _bh]] call comspec_atak_native_fnc_formRender, format ["JTAC:%1", _tab]] call _keepScroll;
true
