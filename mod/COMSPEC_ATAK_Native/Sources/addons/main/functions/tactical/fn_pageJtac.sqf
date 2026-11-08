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
    _map ctrlAddEventHandler ["Draw", { params ["_c"]; private _t = diag_tickTime; [_c, _c getVariable ["mode", "JTAC"]] call comspec_atak_native_fnc_jtacDraw; ["carte JTAC (image)", _t] call comspec_atak_native_fnc_perfAcc; }];
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
    [{ params ["_g", "_v"]; if (!isNull _g) then { _g ctrlSetScrollValues [_v, -1]; }; }, [_grp, _v]] call comspec_atak_native_fnc_nextFrame;
};
// Paysage plein écran : carte à gauche, onglets à droite.
private _split = (_l getOrDefault ["landscape", false]) && {!(_l getOrDefault ["mini", false])};
private _mapW = [0, _bw * 0.44] select _split;
private _rows = [];

// Vues : JTAC, BRIEFS AIR (9-LINE et 5-LINE reçus par le pilote, fn_airBrief) et, quand un brief de mission CAS a été
// reçu, ÉQUIPAGE. À bord d'un aéronef, l'app s'ouvre d'abord sur BRIEFS AIR.
private _hasPilot = (count _p) > 0;
private _inAirV = (vehicle player) isNotEqualTo player && {(vehicle player) isKindOf "Air"};
private _view = _s getOrDefault ["view", ["JTAC", "AIR"] select _inAirV];
if (_view isEqualTo "PILOT" && {!_hasPilot}) then { _view = "JTAC"; };
private _unread = ["unread"] call comspec_atak_native_fnc_airBrief;
private _views = [["JTAC", "JTAC"], [["BRIEFS AIR", format ["BRIEFS AIR (%1)", _unread]] select (_unread > 0), "AIR"]];
if (_hasPilot) then { _views pushBack [format ["ÉQUIPAGE · %1", _p getOrDefault ["jtacName", ""]], "PILOT"]; };
_rows pushBack ["segment", "", _views apply { [_x select 0, ["view", _x select 1] call _act, _view isEqualTo (_x select 1)] }];

// ---------------------------------------------------------------- Vue BRIEFS AIR (pilote)
if (_view isEqualTo "AIR") exitWith {
    private _inbox = missionNamespace getVariable ["COMSPEC_ATAK_AirInbox", []];
    private _open = _s getOrDefault ["airOpen", ""];
    private _bi = _inbox findIf { (_x get "ref") isEqualTo _open };
    private _ico = "\z\comspec_atak_native\addons\main\data\app_jtac.paa";
    private _fmtName = { params ["_f"]; (createHashMapFromArray [["5", "5-LINE"], ["M", "MEDEVAC"]]) getOrDefault [_f, "9-LINE"] };
    if (_bi >= 0) then {
        // Lecture : une ligne par carte, valeur en gros (lisible en vol), puis WILCO / UNABLE.
        private _b = _inbox select _bi;
        // Ouvert (clic sur la liste ou sur l'alerte) : lu, accusé LU renvoyé à l'émetteur.
        if !(_b getOrDefault ["read", false]) then { ["ack", [_open, "READ"]] call comspec_atak_native_fnc_airBrief; };
        private _fmt = _b getOrDefault ["fmt", "9"];
        private _labels = switch (_fmt) do {
            case "5": { ["Observateur / ordre d'alerte", "Position et marquage des amis", "Position de la cible", "Description et marquage de la cible", "Remarques et restrictions"] };
            case "M": { ["Point de prise en charge", "Fréquence et indicatif", "Patients par urgence", "Matériel spécial", "Patients couchés / valides", "Sécurité de la zone", "Marquage de la zone", "Nationalité des patients", "Contamination / terrain"] };
            default { ["IP / BP", "Cap", "Distance", "Altitude cible", "Description", "Position cible", "Marquage", "Amis", "Dégagement"] };
        };
        private _ack = _b getOrDefault ["ack", ""];
        _rows pushBack ["strip", format ["%1  %2", [[_fmt] call _fmtName, _amber, 1, true] call _mono, [format ["DE %1", _b getOrDefault ["from", "JTAC"]], "", 0.9, true] call _mono],
            format ["%1  %2", [_b getOrDefault ["at", ""], _grey, 0.85] call _mono, [["JEU", "ATHENA"] select ((_b getOrDefault ["src", "game"]) isEqualTo "athena"), _grey, 0.75] call _mono]];
        _rows pushBack (switch (_ack) do {
            case "WILCO": { ["WILCO TRANSMIS", "Le JTAC sait que vous exécutez", "green"] call _banner };
            case "UNABLE": { ["UNABLE TRANSMIS", "Le JTAC sait que vous ne pouvez pas exécuter", "red"] call _banner };
            default { [format ["%1 REÇU", [_fmt] call _fmtName], "Lisez les lignes, puis répondez WILCO ou UNABLE", "amber"] call _banner };
        });
        private _lines = _b getOrDefault ["lines", []];
        {
            private _v = trim (_lines param [_forEachIndex, ""]);
            _rows pushBack ["text", format ["<t size='0.75' color='%1'>%2 · %3</t><br/>%4", _grey, _forEachIndex + 1, toUpper _x,
                [[_v, "-"] select (_v isEqualTo ""), ["#ffffff", _grey] select (_v in ["", "-"]), 1.45, true] call _mono], [[0.045, 0.058, 0.052, 1], [0.035, 0.046, 0.041, 1]] select ((_forEachIndex mod 2) isEqualTo 1)];
        } forEach _labels;
        private _rem = trim (_b getOrDefault ["rem", ""]);
        if (_rem isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.75' color='%1'>REMARQUES / RESTRICTIONS</t><br/>%2", _grey, [_rem, "#ffffff", 1.1] call _mono], _cardBg]; };
        _rows pushBack ["big", [
            ["WILCO", ["airAck", [_open, "WILCO"]] call _act, ["", "green"] select (_ack isNotEqualTo "UNABLE"), true, "J'exécute : accusé renvoyé au JTAC"],
            ["UNABLE", ["airAck", [_open, "UNABLE"]] call _act, "red", true, "Je ne peux pas exécuter (armement, carburant, menace)"]
        ]];
        _rows pushBack ["buttons", [["RETOUR À LA LISTE", ["airOpen", ""] call _act], ["EFFACER", ["airDel", _open] call _act]]];
    } else {
        // Indicatif de l'aéronef : choisi par le pilote, partagé avec tous et remonté à Athena.
        if (_inAirV) then {
            _rows pushBack ["field", "aCs", "Indicatif de mon appareil", _s getOrDefault ["aCs", ["airCs", vehicle player] call comspec_atak_native_fnc_airBrief], "OK", ["setAirCs"] call _act, false];
            _rows pushBack ["segment", "", ["VIPER", "HOG", "DUDE", "REAPER"] apply { [_x, ["airCsPick", format ["%1 11", _x]] call _act, false, "Indicatif d'aviation courant"] }];
        };
        private _n = count _inbox;
        // « Sur mon téléphone » (2.9.39) : les briefs de l'appareil sur le vrai téléphone du pilote (fn_mobileQr).
        _rows pushBack ["buttons", [["SUR MON TÉLÉPHONE", { [{ ["open", "briefs"] call comspec_atak_native_fnc_mobileQr; }] call comspec_atak_native_fnc_nextFrame; }, false, true, "QR code à scanner avec votre vrai téléphone : lisez les briefs en grand et répondez WILCO ou UNABLE dessus"]]];
        _rows pushBack ["section", "Briefs reçus", [format ["%1 brief(s), %2 non lu(s)", _n, _unread], "9-LINE et 5-LINE des JTAC (jeu ou Athena)"] select (_n isEqualTo 0)];
        if (_n isEqualTo 0) then {
            _rows pushBack ["empty", _ico, "Aucun brief reçu", "Les 9-LINE et 5-LINE envoyés à votre appareil par un JTAC, en jeu ou depuis la page Athena 9-LINE, arrivent ici avec une alerte."];
        } else {
            {
                private _b = _x;
                private _r = _b get "ref";
                private _rd = _b getOrDefault ["read", false];
                private _ack = _b getOrDefault ["ack", ""];
                private _fmt = _b getOrDefault ["fmt", "9"];
                private _lines = _b getOrDefault ["lines", []];
                private _what = if (_fmt isEqualTo "5") then { format ["%1 · %2", _lines param [2, "-"], _lines param [3, "-"]] } else { format ["%1 · %2", _lines param [5, "-"], _lines param [4, "-"]] };
                private _rgb = switch (true) do {
                    case (_ack isEqualTo "UNABLE"): { [0.9, 0.28, 0.23, 1] };
                    case (_ack isEqualTo "WILCO"): { [0.36, 0.78, 0.42, 1] };
                    case (!_rd): { [0.95, 0.67, 0.2, 1] };
                    default { [0.45, 0.5, 0.47, 1] };
                };
                _rows pushBack ["notif", _ico, _rgb, format ["%1%2 · %3", ["", "● "] select (!_rd), [_fmt] call _fmtName, _b getOrDefault ["from", "JTAC"]],
                    format ["%1%2", _b getOrDefault ["at", ""], ["", " · " + _ack] select (_ack isNotEqualTo "")], _what,
                    ["airOpen", _r] call _act, ["airDel", _r] call _act, "Ouvrir le brief"];
            } forEach _inbox;
            _rows pushBack ["buttons", [["TOUT MARQUER LU", ["airReadAll"] call _act, false, _unread > 0], ["TOUT EFFACER", ["airDelAll"] call _act]]];
        };
    };
    [[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender, "JTAC:AIR"] call _keepScroll;
    true
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
_rows pushBack ["segment", "", [["SITU", "SIT"], ["9/5-LINE", "BRIEF"], ["CARTE", "MAP"], ["CTRL", "CTRL"], ["LOG", "LOG"]] apply { [_x select 0, ["tab", _x select 1] call _act, _tab isEqualTo (_x select 1)] }];

switch (_tab) do {
    case "MAP": {};
    case "BRIEF": {
        private _fmt = _s getOrDefault ["fmt", "9"];
        private _free = _s getOrDefault ["free", false];
        private _ab = { params ["_a", ["_v", []]]; [_a, _v] call comspec_atak_native_fnc_airBrief };
        // Format de la demande : 9-LINE (CAS complet), 5-LINE (CAS court, hélicoptère d'attaque), MEDEVAC (app Médical).
        _rows pushBack ["segment", "Format", [
            ["9-LINE", ["fmt", "9"] call _act, _fmt isEqualTo "9", "Brief CAS complet : IP, cap, distance, cible, marquage, amis, dégagement"],
            ["5-LINE", ["fmt", "5"] call _act, _fmt isEqualTo "5", "Brief CAS court (hélicoptère d'attaque) : observateur, amis, cible, description, remarques"],
            ["MEDEVAC", ["medevac"] call _act, false, "Ouvre la demande MEDEVAC de l'app Médical"]
        ]];
        // Indicatif du JTAC : ligne Observateur, auteur sur Athena, alertes et accusés des pilotes.
        _rows pushBack ["field", "jCs", "Indicatif JTAC", _s getOrDefault ["jCs", ["jtacCs"] call _ab], "OK", ["setCs"] call _act, false];
        _rows pushBack ["segment", "", ["HAMMER", "WARHAWK", "SPARTAN", "JTAC"] apply { [_x, ["csPick", format ["%1 11", _x]] call _act, false, "Indicatif JTAC courant"] }];
        if (_fmt isEqualTo "9") then {
            _rows pushBack ["switch", "Saisie libre", _free, ["free"] call _act, "Toutes les lignes à la main : ni cible, ni point sur la carte, ni appareil exigés"];
        };
        // Avertissement non bloquant (saisie libre, 5-LINE) : lignes conseillées encore vides.
        private _soft = {
            params ["_keys", "_nums"];
            private _empty = [];
            { if ((trim (_s getOrDefault [_x, ""])) isEqualTo "") then { _empty pushBack (_nums select _forEachIndex); }; } forEach _keys;
            if ((count _empty) > 0) then { _rows pushBack ["text", format ["<t size='0.85' color='%1'>À vérifier (envoi possible quand même) : ligne(s) %2 vide(s).</t>", _amber, _empty joinString ", "]]; };
        };
        switch (true) do {
            case (_fmt isEqualTo "5"): {
                // 5-LINE (ATP 3-09.32, JP 3-09.3) : 1 observateur / ordre d'alerte, 2 amis (position, marquage), 3 cible
                // (position), 4 cible (description, marquage), 5 remarques et restrictions. Tout à la main, pré-remplissage proposé.
                _rows pushBack ["strip", ["CAS BRIEF · 5-LINE", "", 0.9, true] call _mono, [["Pré-remplissage possible depuis la cible", "Saisie libre"] select (!_hasT), _grey, 0.72] call _mono];
                {
                    _x params ["_n", "_k", "_lbl", "_hlp"];
                    _rows pushBack ["line", _n, _lbl, _s getOrDefault [_k, ""], "edit", _k, _hlp, "mono"];
                } forEach [
                    ["1", "jF1", "Observateur / ordre d'alerte", "ex. HAMMER 11, 5-LINE, OVER"],
                    ["2", "jF2", "Amis : position et marquage", "ex. grille 0234 5678, balise IR"],
                    ["3", "jF3", "Cible : position", "grille, ou direction et distance depuis les amis"],
                    ["4", "jF4", "Cible : description et marquage", "ex. 2 BTR, laser 1688"],
                    ["5", "jF5", "Remarques et restrictions", "danger close, axe d'attaque, menaces"]
                ];
                _rows pushBack ["buttons", [["PRÉ-REMPLIR DEPUIS LA CIBLE", ["prefill"] call _act, false, true, "Complète les lignes vides avec votre position, la cible verrouillée et le marquage"], ["EFFACER LES LIGNES", ["clearFree"] call _act]]];
                [["jF2", "jF3", "jF4"], [2, 3, 4]] call _soft;
            };
            case (_free): {
                _rows pushBack ["strip", ["CAS BRIEF · 9-LINE · SAISIE LIBRE", "", 0.9, true] call _mono, ["Tout à la main", _grey, 0.72] call _mono];
                {
                    _x params ["_lbl", "_hlp"];
                    private _k = format ["jL%1", _forEachIndex + 1];
                    _rows pushBack ["line", str (_forEachIndex + 1), _lbl, _s getOrDefault [_k, ""], "edit", _k, _hlp, "mono"];
                } forEach [
                    ["IP / BP", "nom ou grille du point initial"], ["Cap", "ex. 245° M"], ["Distance", "ex. 4.2 NM"], ["Altitude cible", "ex. 450 ft MSL"],
                    ["Description", "nombre, type, posture"], ["Position cible", "grille"], ["Marquage", "laser et code, IR, fumigène, aucun"],
                    ["Amis", "direction et distance depuis la cible"], ["Dégagement", "direction, altitude, point de retour"]
                ];
                _rows pushBack ["line", "", "Remarques", _s getOrDefault ["jRem", ""], "edit", "jRem", "FAH, menaces, danger close, restrictions", "mono"];
                _rows pushBack ["buttons", [["PRÉ-REMPLIR DEPUIS LA CIBLE", ["prefill"] call _act, false, true, "Complète les lignes vides avec les valeurs calculées (cible, IP, marquage, amis)"], ["EFFACER LES LIGNES", ["clearFree"] call _act]]];
                [["jL4", "jL5", "jL6"], [4, 5, 6]] call _soft;
                _rows pushBack ["big", [[["TRANSMETTRE LE 9-LINE", "RETRANSMETTRE (CORRECTION)"] select (_hasM && {_stM in [1, 2]}), ["brief"] call _act, "green", !isNull _air, "Mission CAS contrôlée avec l'appareil choisi dans SITU (readback, autorisation de tir)"]]];
            };
            default {
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
            };
        };
        // Destinataires : pilotes qui reçoivent le brief dans leur espace BRIEFS AIR (alerte, WILCO / UNABLE en retour).
        private _po = profileNamespace getVariable ["COMSPEC_ATAK_AirDestPlayers", true];
        private _airsD = ["airs", _po] call _ab;
        private _dest = _s getOrDefault ["dest", []];
        _rows pushBack ["section", "Destinataires", "Pilotes qui recevront ce brief dans leur espace BRIEFS AIR"];
        _rows pushBack ["switch", "Joueurs uniquement", _po, ["destPlayers"] call _act, "Masque les aéronefs sans joueur à bord (IA)"];
        if ((count _airsD) isEqualTo 0) then {
            _rows pushBack ["text", format ["<t size='0.85' color='%1'>%2</t>", _grey, ["Aucun aéronef allié avec équipage : saisissez un indicatif ci-dessous.", "Aucun aéronef allié avec un joueur à bord : saisissez un indicatif ci-dessous."] select _po]];
        };
        {
            _x params ["_v", "_cs", "_pl", "_d"];
            private _id = _v call BIS_fnc_netId;
            private _on = _id in _dest;
            _rows pushBack ["person", getText (configOf _v >> "picture"), format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t>  <t size='0.82' color='%3'>%4 · %5 NM</t><br/>%6",
                ["#c9d4cf", _green] select _on, _cs, _grey, getText (configOf _v >> "displayName"), (_d / 1852) toFixed 1,
                [[_pl joinString ", ", "équipage IA"] select ((count _pl) isEqualTo 0), "#c9d4cf", 0.72] call _mono],
                [[["CHOISIR", "CHOISI"] select _on, ["destToggle", _id] call _act, _on]]];
        } forEach (_airsD select [0, 8]);
        _rows pushBack ["line", "", "Indicatifs saisis", _s getOrDefault ["jDest", ""], "edit", "jDest", "ex. HOG 21, VIPER 11 (séparés par des virgules)", "mono"];
        _rows pushBack ["big", [[["ENVOYER LE 9-LINE AUX PILOTES", "ENVOYER LE 5-LINE AUX PILOTES"] select (_fmt isEqualTo "5"), ["sendAir"] call _act, "green", true, "Alerte et brief dans l'espace BRIEFS AIR des pilotes choisis ; leur WILCO ou UNABLE revient ici"]]];
        // Suivi des envois : statut par destinataire (accusés reçus en jeu).
        private _sent = missionNamespace getVariable ["COMSPEC_ATAK_AirSent", []];
        if ((count _sent) > 0) then {
            _rows pushBack ["section", "Suivi des envois", "Envoyé, reçu, lu, WILCO ou UNABLE"];
            private _stCol = createHashMapFromArray [["SENT", _grey], ["RECEIVED", "#c9d4cf"], ["READ", "#7fd3e0"], ["WILCO", _green], ["UNABLE", _red]];
            {
                private _e = _x;
                private _parts = [];
                { _parts pushBack format ["%1 <t color='%2'>%3</t>", _x, _stCol getOrDefault [_y, _grey], ["label", _y] call _ab]; } forEach (_e get "dest");
                _rows pushBack ["text", format ["%1  %2<br/>%3", [format ["%1 %2", ["9-LINE", "5-LINE"] select ((_e get "fmt") isEqualTo "5"), _e get "at"], _amber, 0.85, true] call _mono,
                    [_e getOrDefault ["sum", ""], _grey, 0.75] call _mono, [_parts joinString "  ·  ", "", 0.8] call _mono], _cardBg];
            } forEach (_sent select [0, 5]);
        };
        // Outil carte IP → cible (cap magnétique, distance, altitude : fn_jtacTool) et 9-LINE sur un autre écran : page
        // Athena /9-line (QR code fixe de l'adresse de production, tools/gen_remote_qr.py), sur le vrai téléphone ou dans
        // une seconde fenêtre du PC.
        _rows pushBack ["buttons", [
            ["OUTIL CARTE IP » CIBLE", { ["MAP"] call comspec_atak_native_fnc_navigate; [{ ["IPTGT"] call comspec_atak_native_fnc_mapToolSet; ["MAP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, false, true, "Clic sur l'IP puis sur la cible : cap magnétique, distance, altitude, repris ici"],
            ["OUVRIR LA PAGE 9-LINE", {
                private _url = "https://athena-comspec.eu/public/9-line";
                private _r = (["COMSPECATAKNativeExtension", "COMSPECExtension"] select ([] call comspec_atak_native_fnc_bridge)) callExtension ["OpenAthenaLink", [_url]];
                if (_r isEqualType []) then { _r = _r param [0, ""]; };
                if ((_r select [0, 2]) isNotEqualTo "OK") then { ["INFO", format ["Ouvrez %1 dans votre navigateur", _url], 6, 30] call comspec_atak_native_fnc_notify; };
            }, false, true, "Ouvre le 9-LINE dans votre navigateur (seconde fenêtre, second écran)"]
        ]];
        _rows append [
            ["section", "9-LINE sur un autre écran", "Scannez avec votre vrai téléphone, connecté à votre compte Athena : le dernier 9-LINE transmis s'affiche en grand, avec un calculateur IP » cible."],
            ["image", "\z\comspec_atak_native\addons\main\data\nineline_qr_ca.paa"],
            ["text", "<t size='0.85' color='#8a9a93'>https://athena-comspec.eu/public/9-line</t>"]
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
            // Valeur courte (une ligne) : azimut et distance, les millièmes à part.
            _rows pushBack ["line", "", "Depuis moi", format ["%1° %2 · %3", [_az] call _three, [_az] call _card, [_d] call _dist], "auto", "", "", "mono"];
            _rows pushBack ["line", "", "Azimut", format ["%1 MIL", round (_az * 17.7778)], "auto", "", "", "mono"];
            private _fr = _b get "friend";
            _rows pushBack ["line", "", "Amis les plus proches", if ((count _fr) isEqualTo 0) then { "aucun à moins de 3 km" } else { format ["%1 m %2%3", _fr select 0, _fr select 1, ["", " · DANGER CLOSE"] select (_b get "dc")] }, ["auto", "warn"] select (_b get "dc"), "", "", "mono"];
            // Appui feu typé (fn_fireSupport) : la cible part à l'appel de feu (mortier, obusier, lance-roquettes, naval).
            if (["on"] call comspec_atak_native_fnc_fireSupport) then {
                _rows pushBack ["buttons", [["APPEL DE FEU (ARTILLERIE)", ["cff"] call _act, false, true, "Cible envoyée à l'app CAS, onglet APPEL DE FEU : choisissez le moyen (mortier, obusier, lance-roquettes, naval) puis TIR"]]];
            };
        } else {
            _rows pushBack ["text", format ["<t color='%1'>Visez la cible ou désignez-la au laser puis VERROUILLER, ou posez-la SUR LA CARTE.</t>", _grey]];
        };
        // Appareils en station : fiche de check-in.
        private _airs = vehicles select { _x isKindOf "Air" && {alive _x} && {(count crew _x) > 0} && {side group (effectiveCommander _x) isEqualTo side group player} && {!(_x isKindOf "UAV_01_base_F")} && {(_x distance2D player) < 40000} };
        // Bascule « Joueurs uniquement » (carte JTAC et cette liste, profil, défaut non) : appareils IA masqués.
        private _poMap = profileNamespace getVariable ["COMSPEC_ATAK_JtacMapPlayers", false];
        if (_poMap) then { _airs = _airs select { ((crew _x) findIf { isPlayer _x }) >= 0 }; };
        _airs = (_airs apply { [_x distance2D player, _x] });
        _airs sort true;
        _airs = _airs apply { _x select 1 };
        _rows pushBack ["section", "Appareils en station", ["Aucun appareil ami en vol", format ["%1 en station · fiche de check-in", count _airs]] select ((count _airs) > 0)];
        _rows pushBack ["switch", "Joueurs uniquement (liste et carte)", _poMap, ["mapPlayers"] call _act, "Masque les appareils et unités IA dans cette liste et sur la carte JTAC"];
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
        ["POSER IP", ["mapPick", "ip"] call _act, true, ["", "on"] select (_pick isEqualTo "ip")],
        ["JOUEURS", ["mapPlayers"] call _act, true, ["", "on"] select (profileNamespace getVariable ["COMSPEC_ATAK_JtacMapPlayers", false])]
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
        _bt ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ [] call _this; }, _c getVariable ["code", {}]] call comspec_atak_native_fnc_nextFrame; }];
    } forEach _btns;
    true
};
if (_split) then { [[0, 0, _mapW, _bh], "JTAC", [_tgtMap, _ipPos]] call _mapAt; };
[[_rows, [_mapW, 0, _bw - _mapW, _bh]] call comspec_atak_native_fnc_formRender, format ["JTAC:%1", _tab]] call _keepScroll;
true
