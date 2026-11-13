/*
    Vue UCAV de l'app Drone (clé droneUcav de l'état, ouverte par fn_ucav "view") : drone armé du camp pris en main
    depuis le téléphone, sans appairage (fn_ucav, fn_ucavCmd).
      Sans UCAV commandé : UCAV armés du camp (distance, carburant, munitions, qui le commande, liaison) et PRENDRE LA MAIN.
      UCAV commandé : flux de la nacelle (même caméra que Vidéo Live, fn_livecamUav : elle suit le servant IA, qui
      regarde la cible pendant une frappe), état et étapes de la frappe (EN ROUTE › EN POSITION › VERROUILLÉ › TIR ›
      IMPACT › BDA), puis quatre onglets (clé ucavTab) :
        MISSION : orbite (altitude, rayon, point), Rechercher et tuer (règle d'engagement), rendre l'UCAV ;
        CIBLES  : arme, cible venue de la carte, détections de l'UCAV, cibles marquées, laser ami, grille ;
        FILE    : frappes en attente, annulables tant que le tir n'est pas parti ; munitions restantes ;
        JOURNAL : événements datés, dernière frappe et son compte rendu BDA pré-rempli.
    Paysage (référence) : onglets à gauche, image et état à droite ; portrait : image en haut, onglets dessous.
    [ucav, liaison] call : texte de l'incrustation d'état (rafraîchie chaque seconde par fn_ucav "tick").
*/
params [["_ou", objNull], ["_olk", []]];
private _T = { _this call comspec_atak_native_fnc_tr };
private _esc = { params ["_t"]; ((_t regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"]) regexReplace [">", "&gt;"] };
private _modeLbl = { (createHashMapFromArray [["ORBIT", "orbite"], ["HUNT", "rechercher et tuer"], ["MANUAL", "terminal UAV"]]) getOrDefault [_this, "en attente"] };
private _nameOf = { params ["_v"]; private _n = _v getVariable ["COMSPEC_DroneName", ""]; if (_n isEqualTo "") then { _n = getText (configOf _v >> "displayName"); }; _n };
private _ammoTxt = {
    params ["_ws"];
    if ((count _ws) isEqualTo 0) exitWith { "aucune munition guidée" };
    (_ws apply { format ["%1 x%2", _x select 3, _x select 2] }) joinString ", "
};
private _status = {
    params ["_v", "_lk"];
    (_v getVariable ["COMSPEC_UcavSt", []]) params [["_sk", ""], ["_stx", ""]];
    private _mode = _v getVariable ["COMSPEC_UcavMode", ""];
    private _ws = ["weapons", _v] call comspec_atak_native_fnc_ucav;
    private _roe = ["proposer", "engagement libre"] select ((_v getVariable ["COMSPEC_UcavRoe", "PROPOSE"]) isEqualTo "FREE");
    private _out = format ["<t font='COMSPEC_BarlowBold' color='#5cc76b'>UCAV %1</t>  <t size='0.85' color='#c9d4cf'>%2%3</t><br/><t size='0.8' color='#8a9a93'>%4 m · %5 km/h · carburant %6 %% · %7 · %8</t><br/><t size='0.8' color='#8a9a93'>%9</t>",
        [[_v] call _nameOf] call _esc, _mode call _modeLbl, ["", " · " + _stx] select (_stx isNotEqualTo ""),
        round ((getPosATL _v) select 2), round (speed _v), round (100 * fuel _v),
        [format ["<t color='#e5483a'>hors liaison</t>"], format ["liaison %1/4", _lk param [1, 0]]] select (_lk param [0, false]),
        format ["règle : %1", _roe], [_ws] call _ammoTxt];
    // Étapes d'une frappe : faites en vert, en cours en ambre.
    private _steps = [["ENROUTE", "EN ROUTE"], ["INPOS", "EN POSITION"], ["LOCK", "VERROUILLÉ"], ["FIRE", "TIR"], ["IMPACT", "IMPACT"], ["BDA", "BDA À CONFIRMER"]];
    private _si = _steps findIf { (_x select 0) isEqualTo _sk };
    if (_si >= 0) then {
        private _parts = [];
        for "_i" from 0 to ((count _steps) - 1) do {
            private _ci = if (_i isEqualTo _si) then { 1 } else { [0, 2] select (_i < _si) };
            _parts pushBack format ["<t color='%1'%2>%3</t>", ["#55605b", "#f2ab33", "#5cc76b"] select _ci,
                ["", " font='COMSPEC_BarlowBold'"] select (_i isEqualTo _si), (_steps select _i) select 1];
        };
        _out = _out + "<br/><t size='0.8'>" + (_parts joinString " › ") + "</t>";
    };
    if (_sk in ["FAIL", "CANCEL"]) then { _out = _out + format ["<br/><t size='0.8' color='#e5483a'>%1</t>", _stx]; };
    _out
};
if (!isNull _ou) exitWith { [_ou, _olk] call _status };

disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _fs = _l get "fontSmall";
private _mini = _l getOrDefault ["mini", false];
private _land = (_l get "landscape") && {!_mini};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _u = missionNamespace getVariable ["COMSPEC_ATAK_Ucav", objNull];
private _A = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_ucav;", str _a, str _v] };
private _cf = _s getOrDefault ["ucavCf", ["", 0]];
private _cfOn = { params ["_k"]; (_cf select 0) isEqualTo _k && {diag_tickTime - (_cf select 1) < 5} };
private _strikeLbl = { params ["_k"]; ["FRAPPER", "CONFIRMER LA FRAPPE"] select (["S:" + _k] call _cfOn) };
private _km = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
uiNamespace setVariable ["COMSPEC_ATAK_UcavOsd", controlNull];
private _rows = [];

// Aucun UCAV commandé : ceux du camp, et la prise en main.
if (isNull _u || {!alive _u}) exitWith {
    _rows append [
        ["title", "UCAV armés"],
        ["text", "<t color='#8a9a93'>Drones armés de votre camp (Greyhawk, Fenghuang, Ababil, Sentinel ou drone armé d'un mod), partis d'une base : prenez-en la main depuis le téléphone, mettez-les en orbite, marquez des cibles et ils les frappent avec leurs propres armes guidées. Ni appairage à vos pieds, ni poste du servant.</t>"]
    ];
    private _why = ["can", player] call comspec_atak_native_fnc_ucav;
    if (_why isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#f2ab33'>%1.</t>", _why]]; };
    private _list = ["list"] call comspec_atak_native_fnc_ucav;
    private _me = getPlayerUID player;
    {
        private _v = _x;
        private _cu = (_v getVariable ["COMSPEC_UcavCtl", []]) param [0, ""];
        private _cn = (_v getVariable ["COMSPEC_UcavCtl", []]) param [1, ""];
        private _busy = _cu isNotEqualTo "" && {_cu isNotEqualTo _me} && {(allPlayers findIf { (getPlayerUID _x) isEqualTo _cu }) >= 0};
        private _lk = ["link", _v] call comspec_atak_native_fnc_ucav;
        private _op = (UAVControl _v) param [0, objNull];
        private _opOn = _op isEqualType objNull && {!isNull _op} && {alive _op};
        private _line = switch (true) do {
            case _busy: { format ["<t color='#f2ab33'>commandé par %1</t>", [_cn] call _esc] };
            case _opOn: { format ["<t color='#f2ab33'>piloté au terminal UAV par %1</t>", [name _op] call _esc] };
            case !(_lk select 0): { format ["<t color='#e5483a'>hors liaison : %1</t>", _lk select 3] };
            default { "<t color='#5cc76b'>libre : prêt à être pris en main</t>" };
        };
        _rows pushBack ["person", getText (configOf _v >> "picture"),
            format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2 · %3 m · carburant %4 %% · %5</t><br/><t size='0.8'>%6</t>",
                [[_v] call _nameOf] call _esc, [_v distance player] call _km, round ((getPosATL _v) select 2), round (100 * fuel _v), [["weapons", _v] call comspec_atak_native_fnc_ucav] call _ammoTxt, _line],
            [["PRENDRE LA MAIN", ["take", netId _v] call _A, true, _why isEqualTo "" && {!_busy} && {!_opOn} && {_lk select 0}, "Le téléphone commande l'UCAV : orbite, Rechercher et tuer, frappes"]]];
    } forEach _list;
    if ((count _list) isEqualTo 0) then { _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_drone.paa", "Aucun UCAV armé", "Aucun drone armé de votre camp dans la mission."]; };
    _rows pushBack ["buttons", [["ACTUALISER", { [{ ["DRONE"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }], ["PETITS DRONES", ["back"] call _A]]];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

private _lk = missionNamespace getVariable ["COMSPEC_ATAK_UcavLinkC", []];
if ((count _lk) < 4) then { _lk = ["link", _u] call comspec_atak_native_fnc_ucav; };
// Disposition : paysage, menus à gauche et image à droite ; portrait, image en haut et menus dessous.
private _vx = _pad;
private _vy = _pad;
private _vw = _bw - 2 * _pad;
private _menu = [];
if (_land) then {
    private _mw = _bw * 0.46;
    _vx = _mw + _pad;
    _vw = _bw - _vx - _pad;
};
private _vh = ((_vw * 9 / 16) * pixelH / pixelW) min (_bh * ([0.34, 0.6] select _land));
private _stH = _fs * 4.6;
private _visY = _vy + _vh + _pad * 0.5;
private _btnH = _fs * 1.4;
private _stY = _visY + _btnH + _pad * 0.5;
_menu = if (_land) then { [0, 0, _bw * 0.46, _bh] } else { [0, _stY + _stH, _bw, _bh - _stY - _stH] };

// Image de la nacelle (caméra de Vidéo Live, rendue dans comspec_livecam) : le servant IA regarde la cible pendant la frappe.
private _vis = _s getOrDefault ["ucavVis", 0];
private _feed = [_u, _vis] call comspec_atak_native_fnc_livecamUav;
private _fl = ["eval", _u] call comspec_atak_native_fnc_feedLink;
if (_feed isEqualTo true && {(_fl get "res") > 0}) then {
    private _res = ["r2t", _fl get "res"] call comspec_atak_native_fnc_deviceGuard;
    ["COMSPEC_RscPhone", [_vx, _vy, _vw, _vh], format ["#(argb,%1,%1,1)r2t(comspec_livecam,1.0)", _res]] call comspec_atak_native_fnc_pageCtrl;
    private _lo = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _vy + _pad / 4, _vw - _pad, _fs * 2.4]] call comspec_atak_native_fnc_pageCtrl;
    uiNamespace setVariable ["COMSPEC_ATAK_LivecamOsd", _lo];
    private _rt = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh / 2 - _fs * 0.9, _vw, _fs * 1.8]] call comspec_atak_native_fnc_pageCtrl;
    _rt ctrlSetStructuredText parseText "<t align='center' size='1.5' color='#e5483a'>+</t>";
} else {
    private _bg = ["COMSPEC_RscMapPanel", [_vx, _vy, _vw, _vh]] call comspec_atak_native_fnc_pageCtrl;
    _bg ctrlSetBackgroundColor [0, 0, 0, 0.9];
    private _nt = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh * 0.35, _vw, _fs * 3]] call comspec_atak_native_fnc_pageCtrl;
    _nt ctrlSetStructuredText parseText ((format ["<t align='center' font='COMSPEC_BarlowBold' color='#e0a33a'>PAS D'IMAGE</t><br/><t align='center' size='0.8' color='#8a9a93'>%1</t>",
        [_fl getOrDefault ["why", ""], "flux de la nacelle refusé"] select ((_fl getOrDefault ["why", ""]) isEqualTo "")]) call _T);
};
// Vision de la nacelle.
private _vbw = _vw / 3;
{
    _x params ["_lbl", "_n"];
    private _b = ["COMSPEC_RscButton", [_vx + _forEachIndex * _vbw, _visY, _vbw - _pad / 4, _btnH], _lbl] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight ((_fs * 0.74) max COMSPEC_ATAK_FontMin);
    if (_n isEqualTo _vis) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    [_b, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['ucavVis', %1]; ['tab', (uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) getOrDefault ['ucavTab', 'MISSION']] call comspec_atak_native_fnc_ucav;", _n]] call comspec_atak_native_fnc_clickNext;
} forEach [["JOUR", 0], ["VISION NOCTURNE", 1], ["THERMIQUE", 2]];
// État de l'UCAV et de la frappe en cours (rafraîchi chaque seconde).
private _osd = ["COMSPEC_RscStructuredText", [_vx, _stY, _vw, _stH]] call comspec_atak_native_fnc_pageCtrl;
_osd ctrlSetStructuredText parseText (([_u, _lk] call _status) call _T);
uiNamespace setVariable ["COMSPEC_ATAK_UcavOsd", _osd];

// Onglets.
private _tab = _s getOrDefault ["ucavTab", "MISSION"];
private _det = _u getVariable ["COMSPEC_UcavDet", []];
private _q = _u getVariable ["COMSPEC_UcavQ", []];
private _pend = _s getOrDefault ["ucavPend", []];
private _ws = ["weapons", _u] call comspec_atak_native_fnc_ucav;
(_u getVariable ["COMSPEC_UcavSt", []]) params [["_sk", ""], ["_stx", ""], "", ["_sid", ""]];
if !(_lk select 0) then { _rows pushBack ["text", format ["<t color='#e5483a'>Hors liaison : %1. L'UCAV poursuit sa mission seul ; aucun ordre ne part tant que la liaison n'est pas rétablie.</t>", _lk select 3]]; };
_rows pushBack ["tabs", [
    ["MISSION", ["tab", "MISSION"] call _A, _tab isEqualTo "MISSION"],
    ["CIBLES", ["tab", "TARGETS"] call _A, _tab isEqualTo "TARGETS", ({ (_x select 6) isEqualTo "PROPOSED" } count _det) + ([0, 1] select ((count _pend) >= 2))],
    ["FILE", ["tab", "QUEUE"] call _A, _tab isEqualTo "QUEUE", count _q],
    ["JOURNAL", ["tab", "LOG"] call _A, _tab isEqualTo "LOG"]
]];
switch (_tab) do {
    case "TARGETS": {
        // Arme : AUTO (l'UCAV choisit), puis chaque arme guidée avec ses coups restants ; trois par rangée.
        private _wsel = _s getOrDefault ["ucavWpn", ""];
        private _wi = [["AUTO", ["wpn", ""] call _A, _wsel isEqualTo "", "L'UCAV choisit : missile contre un véhicule, bombe contre l'infanterie, une pièce fixe ou un point"]]
            + (_ws apply { [format ["%1 x%2", toUpper (_x select 3), _x select 2], ["wpn", _x select 0] call _A, (_x select 0) == _wsel, format ["%1, guidage %2", ["missile", "bombe"] select ((_x select 4) isEqualTo "BOMB"), _x select 5]] });
        for "_c" from 0 to ((count _wi) - 1) step 3 do { _rows pushBack ["segment", ["", "Arme"] select (_c isEqualTo 0), _wi select [_c, 3]]; };
        if ((count _ws) isEqualTo 0) then { _rows pushBack ["text", "<t color='#f2ab33'>L'UCAV n'a plus de munition guidée : rendez-le pour qu'il soit réarmé.</t>"]; };
        // Cible désignée depuis la carte (clic droit > Frapper avec…).
        if ((count _pend) >= 2) then {
            _pend params [["_po", objNull], ["_pp", []], ["_pl", "cible"]];
            if (_po isEqualType objNull && {!isNull _po} && {alive _po}) then { _pp = getPosASL _po; };
            if ((count _pp) >= 2) then {
                private _fr = ["friend", [side group player, _pp, ["dc"] call comspec_atak_native_fnc_ucav, false]] call comspec_atak_native_fnc_ucav;
                _rows append [
                    ["section", "Cible désignée sur la carte", "Choisissez l'arme, puis FRAPPER deux fois"],
                    ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.85' color='#8a9a93'>%2 · à %3 de l'UCAV</t>%4", [_pl] call _esc, [_pp, 8] call comspec_atak_native_fnc_gridRef, [_u distance2D _pp] call _km,
                        ["", format ["<br/><t size='0.85' color='#e5483a'>DANGER CLOSE : allié à %1 m</t>", _fr]] select (_fr >= 0)]],
                    ["buttons", [[["PEND"] call _strikeLbl, ["strike", "PEND"] call _A, "red", (count _ws) > 0], ["CAMÉRA DESSUS", ["look", "PEND"] call _A], ["OUBLIER", ["pendClear"] call _A]]]
                ];
            };
        };
        // Détections de l'UCAV (Rechercher et tuer).
        private _stLbl = createHashMapFromArray [["PROPOSED", ["À VALIDER", "#f2ab33"]], ["AUTO", ["ENGAGÉE (LIBRE)", "#e5483a"]], ["QUEUED", ["EN FILE", "#e5483a"]], ["DONE", ["FRAPPÉE", "#8a9a93"]],
            ["IGNORED", ["ÉCARTÉE", "#8a9a93"]], ["DOWN", ["DÉTRUITE", "#5cc76b"]], ["UNID", ["NON IDENTIFIÉE", "#8a9a93"]], ["NEW", ["NOUVELLE", "#c9d4cf"]]];
        private _kindLbl = createHashMapFromArray [["INF", "infanterie"], ["VEH", "véhicule"], ["ARM", "blindé"], ["STATIC", "pièce fixe"]];
        _rows pushBack ["section", "Détections de l'UCAV", format ["%1 dans la zone · règle : %2", count _det, ["proposer chaque cible", "engagement libre"] select ((_u getVariable ["COMSPEC_UcavRoe", "PROPOSE"]) isEqualTo "FREE")]];
        if ((count _det) isEqualTo 0) then {
            _rows pushBack ["text", ["<t color='#8a9a93'>Aucune détection. Lancez Rechercher et tuer sur une zone (onglet MISSION) : les cibles vues par les capteurs de l'UCAV arrivent ici.</t>", "<t color='#8a9a93'>Aucune détection pour l'instant : l'UCAV patrouille la zone.</t>"] select ((_u getVariable ["COMSPEC_UcavMode", ""]) isEqualTo "HUNT")];
        };
        private _ord = _det apply { [["PROPOSED", "NEW", "UNID", "AUTO", "QUEUED", "DONE", "IGNORED", "DOWN"] find (_x select 6), _x select 0, _x] };
        _ord sort true;
        {
            (_x select 2) params ["_k", "_o", "_lbl", "_pos", "_ident", "_kind", "_state", "_fr"];
            (_stLbl getOrDefault [_state, [_state, "#c9d4cf"]]) params ["_sl", "_sc"];
            private _can = alive _o && {!(_state in ["QUEUED", "AUTO", "DOWN"])} && {(count _ws) > 0};
            _rows pushBack ["person", "\A3\ui_f\data\igui\cfg\simpletasks\types\target_ca.paa",
                format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='%2'>%3</t><br/><t size='0.8' color='#8a9a93'>%4 · à %5 de l'UCAV · %6%7</t>",
                    [_lbl] call _esc, _sc, _sl, _kindLbl getOrDefault [_kind, ""], [_u distance2D _pos] call _km, ["non identifiée", "ennemi identifié"] select _ident,
                    ["", format [" · <t color='#e5483a'>allié à %1 m</t>", _fr]] select (_fr >= 0)],
                [[["DET:" + _k] call _strikeLbl, ["strike", "DET:" + _k] call _A, "red", _can], ["CAMÉRA", ["look", "DET:" + _k] call _A, false, alive _o], ["ÉCARTER", ["ignore", _k] call _A, false, _state isEqualTo "PROPOSED"]]];
        } forEach (_ord select [0, 10]);
        // Cibles marquées (marquage suivi : caméra d'un drone, du téléphone ou Vidéo Live).
        private _locks = ["list"] call comspec_atak_native_fnc_lockAction;
        if ((count _locks) > 0) then {
            _rows pushBack ["section", "Cibles marquées", "Marquage suivi : caméra d'un drone, du téléphone ou Vidéo Live"];
            {
                _x params ["_k", "_e"];
                private _ep = _e getOrDefault ["pos", []];
                if ((count _ep) < 2) then { continue };
                private _trk = (_e getOrDefault ["state", ""]) isEqualTo "TRACK";
                _rows pushBack ["person", "\A3\ui_f\data\igui\cfg\simpletasks\types\target_ca.paa",
                    format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='%2'>%3</t><br/><t size='0.8' color='#8a9a93'>%4 · à %5 de l'UCAV</t>",
                        [_e getOrDefault ["name", _k]] call _esc, ["#8a9a93", "#5cc76b"] select _trk, ["PERDUE", "SUIVIE"] select _trk, [_ep, 8] call comspec_atak_native_fnc_gridRef, [_u distance2D _ep] call _km],
                    [[["LOCK:" + _k] call _strikeLbl, ["strike", "LOCK:" + _k] call _A, "red", (count _ws) > 0], ["CAMÉRA", ["look", "LOCK:" + _k] call _A]]];
            } forEach (_locks select [0, 6]);
        };
        _rows append [
            ["section", "Laser ami ou grille", "Le point allumé par votre groupe, ou le centre de la case saisie"],
            ["buttons", [[["LASER"] call _strikeLbl, ["strike", "LASER"] call _A, "red", (count _ws) > 0, "Frappe le point laser allumé par un membre de votre groupe (désignateur)"]]],
            ["edit", "ucavSGrid", "Grille de la cible (8 ou 10 chiffres)", _s getOrDefault ["ucavSGridVal", ""]],
            ["buttons", [[["GRID"] call _strikeLbl, ["strike", "GRID"] call _A, "red", (count _ws) > 0]]]
        ];
    };
    case "QUEUE": {
        _rows pushBack ["section", "File de frappes", "Frappées dans l'ordre, une munition par cible ; annulables tant que le tir n'est pas parti"];
        if ((count _q) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune frappe en attente.</t>"]; };
        {
            _x params ["_id", "_o", "_aim", ["_w", ""], ["_lbl", "cible"], ["_byN", ""], ["_auto", false]];
            private _cur = _sid isEqualTo _id;
            private _wl = if (_w isEqualTo "") then { "arme au choix de l'UCAV" } else { private _e = _ws select { (_x select 0) == _w }; if (_e isEqualTo []) then { _w } else { (_e select 0) select 3 } };
            private _gone = _cur && {_sk in ["FIRE", "IMPACT", "BDA"]};
            _rows pushBack ["person", "\A3\ui_f\data\igui\cfg\simpletasks\types\destroy_ca.paa",
                format ["<t font='COMSPEC_BarlowBold'>%1. %2</t>%3<br/><t size='0.8' color='#8a9a93'>%4 · %5 · %6</t>", _forEachIndex + 1, [_lbl] call _esc,
                    ["", format ["  <t size='0.8' color='#e5483a'>%1</t>", _stx]] select _cur, [_aim, 8] call comspec_atak_native_fnc_gridRef, _wl,
                    [format ["demandée par %1", [_byN] call _esc], "engagement libre"] select _auto],
                [[["ANNULER", "CONFIRMER L'ANNULATION"] select (["C:" + _id] call _cfOn), ["cancel", _id] call _A, "red", !_gone, ["Retire cette frappe de la file", "Le tir est parti : plus annulable"] select _gone]]];
        } forEach _q;
        if ((count _q) > 1) then { _rows pushBack ["buttons", [[["TOUT ANNULER", "CONFIRMER : TOUT ANNULER"] select (["C:ALL"] call _cfOn), ["cancel", "ALL"] call _A, "red"]]]; };
        _rows pushBack ["section", "Munitions", ""];
        if ((count _ws) isEqualTo 0) then { _rows pushBack ["text", "<t color='#f2ab33'>Plus de munition guidée.</t>"]; };
        { _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>%1 x%2</t>  <t size='0.8' color='#8a9a93'>%3, guidage %4%5</t>", [_x select 3] call _esc, _x select 2, ["missile", "bombe"] select ((_x select 4) isEqualTo "BOMB"), _x select 5,
            ["", format [", portée %1 à %2", [_x select 6] call _km, [_x select 7] call _km]] select ((_x select 4) isEqualTo "MISSILE")]]; } forEach _ws;
    };
    case "LOG": {
        private _b = missionNamespace getVariable ["COMSPEC_ATAK_UcavBda", []];
        if ((count _b) >= 7) then {
            _b params ["_type", "_res", "_wl", "_tl", "_imp", "_miss"];
            _rows append [
                ["section", "Dernière frappe", "Résultat proposé par le capteur de l'UCAV, à confirmer à vue"],
                ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.85' color='#8a9a93'>%2 · %3 · impact à %4 m du point visé · %5</t>", [_tl] call _esc, _res, [_wl] call _esc, _miss, [_imp, 8] call comspec_atak_native_fnc_gridRef]],
                ["buttons", [["COMPTE RENDU BDA", ["bda"] call _A, true, true, "Ouvre l'app Comptes rendus avec le BDA pré-rempli : vérifiez et envoyez"]]]
            ];
        };
        private _lg = missionNamespace getVariable ["COMSPEC_ATAK_UcavLog", []];
        _rows pushBack ["section", "Journal", format ["%1 événements", count _lg]];
        if ((count _lg) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Rien pour l'instant.</t>"]; } else {
            private _last = _lg select [((count _lg) - 25) max 0];
            reverse _last;
            _rows pushBack ["text", (_last apply { format ["<t size='0.8'><t color='#8a9a93'>%1</t>  <t color='%3'>%2</t></t>", _x select 0, [(_x select 1) call _T] call _esc, ["#c9d4cf", _x select 2] select ((_x select 2) isNotEqualTo "")] }) joinString "<br/>"];
        };
    };
    default {
        private _mode = _u getVariable ["COMSPEC_UcavMode", ""];
        private _roe = _u getVariable ["COMSPEC_UcavRoe", _s getOrDefault ["ucavRoe", "PROPOSE"]];
        private _alt = _s getOrDefault ["ucavAlt", 1000];
        private _rad = _s getOrDefault ["ucavRad", 1200];
        private _free = missionNamespace getVariable ["comspec_atak_native_ucav_free", true];
        (_u getVariable ["COMSPEC_UcavOrbit", []]) params [["_oc", []], ["_or", 0], ["_oa", 0]];
        _rows append [
            ["section", "Orbite", "L'UCAV tourne autour du point, à l'altitude et au rayon choisis"],
            ["text", if ((count _oc) >= 2) then { format ["<t color='#5cc76b'>En cours : %1 en %2, rayon %3 m, %4 m</t>", _mode call _modeLbl, [_oc, 8] call comspec_atak_native_fnc_gridRef, round _or, round _oa] } else { "<t color='#8a9a93'>Aucune orbite posée.</t>" }],
            ["segment", "Altitude", [500, 1000, 1500, 2500] apply { [format ["%1 m", _x], ["alt", _x] call _A, _alt isEqualTo _x] }, "Hauteur de vol au-dessus du sol : plus haut, l'UCAV est moins exposé et ses bombes portent plus loin"],
            ["segment", "Rayon", [800, 1200, 2000, 3000] apply { [format ["%1 m", _x], ["rad", _x] call _A, _rad isEqualTo _x] }, "Rayon de l'orbite, ou de la zone de Rechercher et tuer"],
            ["edit", "ucavGrid", "Point : grille à 8 ou 10 chiffres (vide : sur vous)", _s getOrDefault ["ucavGridVal", ""]],
            ["buttons", [["ORBITE SUR LE POINT", ["orbit"] call _A, true], ["CHOISIR SUR LA CARTE", ["pickMap", "ORBIT"] call _A]]],
            ["section", "Rechercher et tuer", "L'UCAV patrouille la zone ; ses capteurs détectent les cibles, qu'il propose ou engage"],
            ["segment", "Règle d'engagement", [["PROPOSER", ["roe", "PROPOSE"] call _A, _roe isEqualTo "PROPOSE", "Chaque cible détectée attend votre validation au téléphone"]]
                + ([[], [["ENGAGER LIBREMENT", ["roe", "FREE"] call _A, _roe isEqualTo "FREE", "Ennemis identifiés seulement, jamais près d'un allié ou d'un civil"]]] select _free)],
            ["text", format ["<t size='0.85' color='#8a9a93'>%1</t>", if (_roe isEqualTo "FREE") then {
                format ["Engager librement : l'UCAV frappe seul les ennemis identifiés, deux à la fois au plus. Une cible à moins de %1 m d'un allié ou d'un civil (distance de sécurité de l'appui aérien) vous est proposée au lieu d'être frappée.", ["dc"] call comspec_atak_native_fnc_ucav]
            } else { "Proposer : chaque cible détectée vous est signalée (notification, onglet CIBLES) ; rien ne part sans vos deux appuis." }]],
            ["buttons", [["RECHERCHER ET TUER SUR LE POINT", ["hunt"] call _A, true], ["CHOISIR SUR LA CARTE", ["pickMap", "HUNT"] call _A]]]
        ];
        if (!_free) then { _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>L'engagement libre est interdit sur ce serveur : les cibles sont toujours proposées.</t>"]; };
        _rows append [
            ["text", "<t size='0.8' color='#8a9a93'>Depuis la carte : clic droit > UCAV : orbite ici, UCAV : rechercher et tuer, ou Frapper avec… sur une cible marquée, un contact ou un marqueur.</t>"],
            ["section", "Main sur l'UCAV", format ["Vous le commandez depuis %1", [[player, true] call comspec_atak_native_fnc_unitCallsign] call _esc]],
            ["buttons", [["RÉAPPLIQUER LA MISSION", ["resume"] call _A, false, true, "Si l'UCAV ne répond plus : équipage remis d'aplomb, orbite et frappes en file renvoyées"],
                [["RENDRE L'UCAV", "CONFIRMER : RENDRE"] select (["release"] call _cfOn), ["release"] call _A, "red", true, "Il garde son orbite ; un autre joueur autorisé peut le prendre"]]],
            ["buttons", [["PETITS DRONES", ["back"] call _A]]]
        ];
    };
};
[_rows, _menu] call comspec_atak_native_fnc_formRender;
true
