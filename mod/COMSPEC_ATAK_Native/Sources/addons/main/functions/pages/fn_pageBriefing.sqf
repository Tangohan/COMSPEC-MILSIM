/*
    App Briefing, deux onglets :
    - DIAPOSITIVES : deck Google Slides partagé par le présentateur (synchronisé pour tous par Overwatch)
      ou diapositives publiées sur Athena ; sommaire cliquable, précédente / suivante, actualiser.
    - MISSION : nom, carte, date et heure, météo, puis les notes du briefing de la mission (journal Arma).
    - Engrenage (à droite des onglets) : lien Google Slides, source des diapositives, actualisation automatique
      (fn_briefingSettings, actions fn_briefingCfg).
    La page se redessine seule quand la diapositive change (voir le PFH de XEH_postInitClient).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _rowH = _font * 1.5;
private _gw = _bw - 0.012;
private _land = _l get "landscape";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _tab = _s getOrDefault ["briefTab", "SLIDES"];
private _bridge = [] call comspec_atak_native_fnc_bridge;

private _btn = {
    params ["_rect", "_label", "_code", ["_active", false]];
    private _b = ["COMSPEC_RscButton", _rect, _label] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight _fs;
    if (_active) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", _code];
    _b
};

// Onglets
// Quatrième onglet : la carte de briefing (app Plan), à part de la carte qui suit le joueur.
// Engrenage carré au bout de la rangée : paramètres du briefing (liens des présentations).
private _gearW = _rowH / (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
// Cinquième onglet ÉCRANS seulement quand un écran COMSPEC est à portée (fn_screenCast).
private _screens = ["list"] call comspec_atak_native_fnc_screenCast;
if ((count _screens) isEqualTo 0 && {_tab isEqualTo "SCREENS"}) then { _tab = "SLIDES"; _s set ["briefTab", _tab]; };
private _nTabs = [4, 5] select ((count _screens) > 0);
private _tw = (_gw - 2 * _pad - _gearW - _pad / 2) / _nTabs;
private _qn = count ((uiNamespace getVariable ["COMSPEC_ATAK_BriefQ", createHashMap]) getOrDefault ["list", []]);
[[_pad, _pad, _tw - _pad / 6, _rowH], ["DIAPOSITIVES", "DIAPOS"] select (_nTabs > 4), { ["tab", "SLIDES"] call comspec_atak_native_fnc_briefingAction; }, _tab isEqualTo "SLIDES"] call _btn;
[[_pad + _tw, _pad, _tw - _pad / 6, _rowH], ["QUESTIONS", format ["QUESTIONS (%1)", _qn]] select (_qn > 0), { ["tab", "QUESTIONS"] call comspec_atak_native_fnc_briefingAction; }, _tab isEqualTo "QUESTIONS"] call _btn;
[[_pad + 2 * _tw, _pad, _tw - _pad / 6, _rowH], "MISSION", { ["tab", "MISSION"] call comspec_atak_native_fnc_briefingAction; }, _tab isEqualTo "MISSION"] call _btn;
[[_pad + 3 * _tw, _pad, _tw - _pad / 6, _rowH], "CARTE", { [{ ["PLAN"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame; }] call _btn;
if (_nTabs > 4) then {
    [[_pad + 4 * _tw, _pad, _tw - _pad / 6, _rowH], format ["ÉCRANS (%1)", count _screens], { ["tab", "SCREENS"] call comspec_atak_native_fnc_briefingAction; }, _tab isEqualTo "SCREENS"] call _btn;
};
private _gx = _gw - _pad - _gearW;
private _gearOn = _tab isEqualTo "SETTINGS";
private _gbg = ["COMSPEC_RscText", [_gx, _pad, _gearW, _rowH]] call comspec_atak_native_fnc_pageCtrl;
_gbg ctrlSetBackgroundColor ([[0.05, 0.12, 0.16, 1], [0.36, 0.78, 0.42, 0.95]] select _gearOn);
private _gi = ["COMSPEC_RscPicture", [_gx + _gearW * 0.18, _pad + _rowH * 0.18, _gearW * 0.64, _rowH * 0.64], "\z\comspec_atak_native\addons\main\data\ui_gear.paa"] call comspec_atak_native_fnc_pageCtrl;
_gi ctrlSetTextColor ([[0.92, 0.96, 0.93, 1], [0.03, 0.05, 0.04, 1]] select _gearOn);
private _gb = ["COMSPEC_RscButtonOverlay", [_gx, _pad, _gearW, _rowH]] call comspec_atak_native_fnc_pageCtrl;
_gb ctrlSetTooltip (("Paramètres du briefing : lien Google Slides, source des diapositives") call comspec_atak_native_fnc_tr);
_gb ctrlAddEventHandler ["ButtonClick", { [{ [["open", "close"] select (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["briefTab", "SLIDES"]) isEqualTo "SETTINGS")] call comspec_atak_native_fnc_briefingCfg; }] call CBA_fnc_execNextFrame; }];
private _y0 = _pad * 2 + _rowH;

if (_tab isEqualTo "SETTINGS") exitWith {
    [[0, _y0, _bw, _bh - _y0]] call comspec_atak_native_fnc_briefingSettings;
    true
};

// Écrans COMSPEC à portée : diffuser ma diapositive en cours, la faire défiler, arrêter (fn_screenCast).
if (_tab isEqualTo "SCREENS") exitWith {
    ([] call comspec_atak_native_fnc_briefingSignature) params ["_src", "_idx", "_total"];
    private _rows = [
        ["section", "Écrans à portée", "Diapositives de ce téléphone sur les écrans du jeu"],
        ["text", "<t size='0.8' color='#8a9a93'>DIFFUSER affiche votre diapositive en cours sur l'écran ; quand vous tournez les pages dans DIAPOSITIVES, l'écran suit. Tout le monde peut aussi la faire défiler à l'écran avec ACE. Chaque joueur voit l'image s'il a COMSPEC Link.</t>"]
    ];
    if (_total < 1) then { _rows pushBack ["text", "<t color='#f2ab33'>Aucune diapositive chargée : ouvrez d'abord une présentation dans DIAPOSITIVES.</t>"]; };
    if ((count _screens) > 1 && {_total > 0}) then { _rows pushBack ["buttons", [["DIFFUSER SUR TOUS", { ["castAll"] call comspec_atak_native_fnc_screenCast; [{ ["BRIEFING"] call comspec_atak_native_fnc_pageRender; }, [], 0.3] call CBA_fnc_waitAndExecute; }, true]]]; };
    {
        private _o = _x;
        private _cfg = _o getVariable ["COMSPEC_ATAK_Screen", []];
        private _c = _o getVariable ["COMSPEC_ATAK_ScreenCast", []];
        private _id = netId _o;
        private _state = switch (true) do {
            case ((count _c) < 5): { "<t color='#8a9a93'>libre</t>" };
            case ((_c select 0) isEqualTo player): { format ["<t color='#5cc76b'>● vous diffusez · %1</t>", [format ["%1 / %2", (_c select 4) + 1, count (_c select 3)], "Google Slides"] select ((_c select 2) isEqualTo "GOOGLE")] };
            default { format ["<t color='#f2ab33'>● %1 diffuse · %2</t>", _c select 1, [format ["%1 / %2", (_c select 4) + 1, count (_c select 3)], "Google Slides"] select ((_c select 2) isEqualTo "GOOGLE")] };
        };
        _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='#8a9a93'>%2 m</t><br/>%3", _cfg param [0, "Écran"], round (player distance _o), _state]];
        private _b = [];
        if ((count _c) >= 5) then {
            _b append [
                ["«", compile format ["['step', [objectFromNetId '%1', -1]] call comspec_atak_native_fnc_screenCast; [{ ['BRIEFING'] call comspec_atak_native_fnc_pageRender; }, [], 0.3] call CBA_fnc_waitAndExecute;", _id]],
                ["»", compile format ["['step', [objectFromNetId '%1', 1]] call comspec_atak_native_fnc_screenCast; [{ ['BRIEFING'] call comspec_atak_native_fnc_pageRender; }, [], 0.3] call CBA_fnc_waitAndExecute;", _id]],
                ["ARRÊTER", compile format ["['stop', objectFromNetId '%1'] call comspec_atak_native_fnc_screenCast; [{ ['BRIEFING'] call comspec_atak_native_fnc_pageRender; }, [], 0.3] call CBA_fnc_waitAndExecute;", _id]]
            ];
        };
        if (_total > 0 && {(_c param [0, objNull]) isNotEqualTo player}) then {
            _b pushBack [["DIFFUSER", "REPRENDRE"] select ((count _c) >= 5), compile format ["['cast', objectFromNetId '%1'] call comspec_atak_native_fnc_screenCast; [{ ['BRIEFING'] call comspec_atak_native_fnc_pageRender; }, [], 0.3] call CBA_fnc_waitAndExecute;", _id], true];
        };
        if ((count _b) > 0) then { _rows pushBack ["buttons", _b]; };
    } forEach _screens;
    [_rows, [0, _y0, _bw, _bh - _y0]] call comspec_atak_native_fnc_formRender;
    true
};

// Questions sur la diapositive en cours : gardées sur Athena (commentaires de la diapositive), le présentateur est prévenu.
if (_tab isEqualTo "QUESTIONS") exitWith {
    ([] call comspec_atak_native_fnc_briefingSignature) params ["_src", "_idx", "_total"];
    private _slide = if (_src isEqualTo "ATHENA" && {_total > 0}) then { (missionNamespace getVariable ["COMSPEC_BriefingSlides", []]) select _idx } else { [] };
    private _rows = [];
    if ((count _slide) isEqualTo 0) then {
        _rows pushBack ["text", "<t color='#8a9a93'>Les questions portent sur une diapositive Athena. Ouvrez l'onglet DIAPOSITIVES et touchez ACTUALISER.</t>"];
    } else {
        private _q = uiNamespace getVariable ["COMSPEC_ATAK_BriefQ", createHashMap];
        if ((_q getOrDefault ["slide", -1]) isNotEqualTo (_slide select 0)) then {
            [{ ["questions", _this] call comspec_atak_native_fnc_briefingAction; }, _slide select 0] call CBA_fnc_execNextFrame;
        };
        private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
        _rows append [
            ["section", format ["Diapositive %1 : %2", _idx + 1, _slide select 1], "Questions gardées sur Athena avec la diapositive"]
        ];
        private _list = _q getOrDefault ["list", []];
        if ((_q getOrDefault ["err", ""]) isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='#e0a040'>%1</t>", _q get "err"]]; };
        if ((count _list) isEqualTo 0 && {(_q getOrDefault ["err", ""]) isEqualTo ""}) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune question sur cette diapositive.</t>"]; };
        { _x params ["", "_author", "_when", "_body"]; _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' color='#9be3a5'>%1</t> <t size='0.75' color='#8a9a93'>%2</t><br/>%3", [_author] call _esc, (_when splitString " ") param [1, _when], [_body] call _esc]]; } forEach _list;
        _rows append [
            ["memo", "briefQ", "Votre question", uiNamespace getVariable ["COMSPEC_ATAK_BriefQDraft", ""]],
            ["buttons", [["ENVOYER", { ["ask"] call comspec_atak_native_fnc_briefingAction; }, true], ["ACTUALISER", { ["questions", -1] call comspec_atak_native_fnc_briefingAction; }]]]
        ];
    };
    [_rows, [0, _y0, _bw, _bh - _y0]] call comspec_atak_native_fnc_formRender;
    // Brouillon de la question tenu à jour à chaque frappe : tout redessin (PFH de signature, changement de
    // diapositive, arrivée d'un participant, actualisation) le retrouve au lieu de l'effacer.
    private _qc = (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault ["briefQ", controlNull];
    if (!isNull _qc) then {
        _qc ctrlAddEventHandler ["KeyUp", { params ["_c"]; uiNamespace setVariable ["COMSPEC_ATAK_BriefQDraft", ctrlText _c]; false }];
    };
    true
};

if (_tab isEqualTo "MISSION") exitWith {
    private _h = { params ["_t"]; format ["<t color='#5cc76b' size='0.85' font='COMSPEC_BarlowBold'>%1</t>", toUpper _t] };
    private _dim = { params ["_t"]; format ["<t color='#8a9a93' size='0.85'>%1</t>", _t] };
    // Texte du journal Arma → texte structuré lisible par parseText.
    private _clean = {
        params ["_t"];
        _t = _t regexReplace ["<marker[^>]*>", "<t color='#5cc76b'>"];
        _t = _t regexReplace ["</marker>", "</t>"];
        _t = _t regexReplace ["<execute[^>]*>", "<t color='#5cc76b'>"];
        _t = _t regexReplace ["</execute(Close)?>", "</t>"];
        _t = _t regexReplace ["<font", "<t"];
        _t = _t regexReplace ["</font>", "</t>"];
        _t = _t regexReplace [" face=", " font="];
        // Tailles du journal : en pixels dans le journal Arma (size='40'), en multiple de la police dans un texte
        // structuré, d'où un « 0.8% » géant (barre de charge d'un mod de gilet). Toutes retirées, quelle que soit
        // l'écriture (guillemets ou non, espaces, majuscules, « px »), ainsi que width / height des images.
        _t = _t regexReplace ["\s[sS][iI][zZ][eE]\s*=\s*(['""][^'""]*['""]|[^\s/>]+)", " "];
        _t = _t regexReplace ["\s([wW][iI][dD][tT][hH]|[hH][eE][iI][gG][hH][tT])\s*=\s*(['""][^'""]*['""]|[^\s/>]+)", " "];
        // Barre de remplissage en « | » répétés devant un pourcentage : on garde la valeur, en taille normale.
        _t = _t regexReplace ["\|{2,}\s*(?=[0-9])", ""];
        _t
    };
    private _date = date;
    private _world = getText (configFile >> "CfgWorlds" >> worldName >> "description");
    private _mission = briefingName;
    if (_mission isEqualTo "") then { _mission = missionName; };
    private _sky = switch (true) do { case (rain > 0.5): { "pluie forte" }; case (rain > 0.1): { "pluie" }; case (overcast > 0.7): { "couvert" }; case (overcast > 0.35): { "nuageux" }; default { "dégagé" } };
    private _windDir = round (((wind select 0) atan2 (wind select 1)) + 360) mod 360;
    private _lines = [
        ["Situation"] call _h,
        format ["<t font='COMSPEC_BarlowBold' size='1.1'>%1</t>", _mission],
        format ["%1 · %2", [_world, worldName] select (_world isEqualTo ""), ["Opération en cours", format ["%1 joueur(s)", count allPlayers]] select isMultiplayer] call _dim,
        format ["Date  %1/%2/%3   Heure  %4", (_date select 2) toFixed 0, (_date select 1) toFixed 0, (_date select 0) toFixed 0, [daytime, "HH:MM"] call BIS_fnc_timeToString],
        format ["Météo  %1 · brouillard %2 %% · vent %3 m/s du %4°", _sky, round (fog * 100), round (vectorMagnitude wind * 10) / 10, (_windDir + 180) mod 360],
        format ["Ma position  %1", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef],
        ""
    ];
    // Notes du briefing : sujet « Diary » d'abord, puis les sujets propres à la mission.
    private _skip = ["units", "players", "statistics", "log", "tasks", "diary"];
    private _subjects = [["Diary", "Briefing"]];
    {
        _x params [["_id", ""], ["_name", ""]];
        if (!((toLower _id) in _skip) && {!((toLower _id) select [0, 3] in ["cba", "ace", "bis"])}) then { _subjects pushBack [_id, [_name, _id] select (_name isEqualTo "")]; };
    } forEach (allDiarySubjects player);
    private _count = 0;
    {
        _x params ["_id", "_name"];
        private _recs = +(player allDiaryRecords _id);
        reverse _recs;
        if ((count _recs) > 0) then {
            _lines pushBack ([_name] call _h);
            {
                _lines pushBack format ["<t font='COMSPEC_BarlowBold'>%1</t>", _x param [1, ""]];
                _lines pushBack ([_x param [2, ""]] call _clean);
                _lines pushBack "";
                _count = _count + 1;
            } forEach _recs;
        };
    } forEach _subjects;
    if (_count isEqualTo 0) then { _lines pushBack (["La mission ne contient pas de notes de briefing."] call _dim); };
    private _text = ["COMSPEC_RscStructuredText", [_pad, _y0, _gw - 2 * _pad, _bh - _y0 - _pad]] call comspec_atak_native_fnc_pageCtrl;
    _text ctrlSetStructuredText parseText (((_lines joinString "<br/>")) call comspec_atak_native_fnc_tr);
    _text ctrlSetPosition [_pad, _y0, _gw - 2 * _pad, (ctrlTextHeight _text) max (_bh - _y0 - _pad)];
    _text ctrlCommit 0;
    true
};

// DIAPOSITIVES
([] call comspec_atak_native_fnc_briefingSignature) params ["_src", "_idx", "_total", "_path", "_titles"];
// Source « Google Slides » de l'engrenage : le lien enregistré est chargé une fois à l'ouverture.
private _pref = profileNamespace getVariable ["COMSPEC_ATAK_BriefSource", "AUTO"];
private _gUrl = profileNamespace getVariable ["COMSPEC_ATAK_BriefGoogleUrl", ""];
if (_pref isEqualTo "GOOGLE" && {_bridge} && {_gUrl isNotEqualTo ""} && {_src isNotEqualTo "GOOGLE"} && {(uiNamespace getVariable ["COMSPEC_ATAK_BriefAutoTried", ""]) isNotEqualTo _gUrl}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_BriefAutoTried", _gUrl];
    [{ ["load", [_this, false]] call comspec_atak_native_fnc_briefingCfg; }, _gUrl] call CBA_fnc_execNextFrame;
};
// Raison montrée dans la zone d'image quand la diapositive Athena ne peut pas s'afficher (vide : image affichée).
private _slideWhy = "";
private _slideLoading = false;
if (_src isEqualTo "ATHENA" && {_total > 0}) then {
    // Image en cache local (téléchargée une fois par diapositive) : [chemin, raison].
    private _cache = uiNamespace getVariable ["COMSPEC_ATAK_SlideCache", createHashMap];
    // Téléchargement hors du dessin de la page (spawn + fn_extAwait) : callExtension direct figeait le jeu le temps
    // de la requête HTTP vers Athena ; « Chargement de la diapositive… » en attendant, puis la page est redessinée.
    private _loading = uiNamespace getVariable ["COMSPEC_ATAK_SlideLoading", createHashMap];
    uiNamespace setVariable ["COMSPEC_ATAK_SlideLoading", _loading];
    if (!(_path in _cache) && {!(_path in _loading)}) then {
        _loading set [_path, true];
        private _slide = (missionNamespace getVariable ["COMSPEC_BriefingSlides", []]) select _idx;
        [_path, _slide, _loading] spawn {
            params ["_path", "_slide", "_loading"];
            private _url = _slide param [3, ""];
            // Fichier local propre à l'adresse de l'image : Arma garde en mémoire la texture d'un chemin déjà lu, une image
            // renvoyée sur Athena (nouvelle adresse) ou une première lecture ratée restaient sinon affichées jusqu'au redémarrage.
            private _raw = [(["COMSPECExtension", "DownloadBriefingSlideImage", [_url, format ["%1_%2", _slide param [0, 0], hashValue _url]]] call comspec_atak_native_fnc_extAwait)] call comspec_overwatch_connect_fnc_extResult;
            private _dl = _raw splitString "|";
            private _p = if ((_dl param [0, ""]) isEqualTo "OK") then { ((_dl param [1, ""]) splitString (toString [92])) joinString "/" } else { "" };
            private _lp = toLower _p;
            private _ext = _lp select [((count _lp) - 4) max 0];
            private _why = switch (true) do {
                case (_url isEqualTo ""): { "Athena n'a envoyé aucune image pour cette diapositive." };
                case (_p isEqualTo ""): { format ["Téléchargement de l'image impossible (%1).", _dl param [1, "réponse vide de COMSPEC Link"]] };
                // Arma n'affiche que JPEG et PAA : un PNG laissait la zone noire (copie JPEG du site absente).
                case !(_ext in [".jpg", "jpeg", ".paa"]): { format ["Image reçue au format %1, illisible par Arma (JPEG attendu).<br/>Le site Athena n'a pas pu en faire une copie JPEG : renvoyez la diapositive en JPG.", _ext] };
                default { "" };
            };
            // Taille lue par Arma : 0 x 0 = fichier illisible par le jeu (chemin refusé ou image abîmée), à joindre à un signalement.
            private _tex = if (_why isEqualTo "") then { getTextureInfo _p } else { [] };
            ["INFO", "BRIEFING", format ["Diapositive %1 : adresse %2, fichier %3, texture %4, %5", _slide param [0, 0], _url, _p, _tex, ["image affichée", _why] select (_why isNotEqualTo "")]] call comspec_atak_native_fnc_log;
            // Cache relu : ACTUALISER a pu le remplacer pendant le téléchargement.
            private _cache = uiNamespace getVariable ["COMSPEC_ATAK_SlideCache", createHashMap];
            _cache set [_path, [[_p, ""] select (_why isNotEqualTo ""), _why]];
            uiNamespace setVariable ["COMSPEC_ATAK_SlideCache", _cache];
            _loading deleteAt _path;
            [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "BRIEFING") then { ["BRIEFING"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
        };
    };
    _slideLoading = !(_path in _cache);
    (_cache getOrDefault [_path, ["", ""]]) params [["_p", ""], ["_why", ""]];
    _path = _p;
    _slideWhy = _why;
};

private _btnH = _rowH;
// Présentation en direct (Athena) : bandeau présentateur / présents et notes de la diapositive.
private _live = if (_src isEqualTo "ATHENA") then { ["get"] call comspec_atak_native_fnc_briefingLive } else { [] };
private _iPresent = (_live param [0, objNull]) isEqualTo player;
private _follow = _s getOrDefault ["briefFollow", true];
private _detail = if (_src isEqualTo "ATHENA" && {_total > 0}) then { ((missionNamespace getVariable ["COMSPEC_BriefingSlides", []]) select _idx) param [4, ""] } else { "" };
private _liveH = [0, _fs * 1.5 + _pad / 2] select (_src isEqualTo "ATHENA" && {_total > 0});
private _notesH = [0, _fs * 3.4] select (_detail isNotEqualTo "");
private _foot = _btnH + _fs * 1.6 + _pad * 2 + _liveH + _notesH;
private _listW = [0, _gw * 0.28] select (_land && {_total > 1});
private _vx = _pad + ([0, _listW + _pad] select (_listW > 0));
private _vy = _y0 + ([0, _rowH + _pad] select (!_land && {_total > 1}));
private _vw = _gw - _vx - _pad;
private _vh = _bh - _vy - _foot;

// Sommaire : liste à gauche en horizontal, liste déroulante en vertical.
if (_total > 1) then {
    private _toc = if (_land) then {
        ["COMSPEC_RscListBox", [_pad, _y0, _listW, _bh - _y0 - _pad]] call comspec_atak_native_fnc_pageCtrl
    } else {
        ["COMSPEC_RscCombo", [_pad, _y0, _gw - 2 * _pad, _rowH]] call comspec_atak_native_fnc_pageCtrl
    };
    _toc ctrlSetFontHeight _fs;
    {
        private _t = _x;
        if (_t isEqualTo "") then { _t = format ["Diapositive %1", _forEachIndex + 1]; };
        _toc lbAdd ((format ["%1. %2", _forEachIndex + 1, _t]) call comspec_atak_native_fnc_tr);
    } forEach _titles;
    _toc lbSetCurSel _idx;
    _toc ctrlAddEventHandler ["LBSelChanged", { params ["", "_i"]; ["goto", _i] call comspec_atak_native_fnc_briefingAction; }];
};

private _bg = ["COMSPEC_RscPanel", [_vx, _vy, _vw, _vh]] call comspec_atak_native_fnc_pageCtrl;
_bg ctrlSetBackgroundColor [0, 0, 0, 1];
if (_total < 1 || {_path isEqualTo ""}) then {
    private _msg = switch (true) do {
        case (_total < 1 && {_bridge} && {_pref isEqualTo "GOOGLE"}): { ["Aucune présentation Google enregistrée.<br/>Touchez l'engrenage en haut à droite pour coller le lien Google Slides.", "Chargement de la présentation Google…<br/>En cas d'échec, l'engrenage en haut à droite explique pourquoi."] select (_gUrl isNotEqualTo "") };
        case (_total < 1 && {_bridge}): { "Aucune diapositive pour l'instant.<br/>Le présentateur partage un Google Slides depuis le tableau de briefing, ou publiez des diapositives sur Athena puis touchez ACTUALISER.<br/>Votre propre lien Google Slides : touchez l'engrenage en haut à droite." };
        case (_total < 1): { "Aucun briefing reçu.<br/>Les diapositives Athena demandent COMSPEC Link." };
        case (_src isEqualTo "GOOGLE" || {_slideLoading}): { "Chargement de la diapositive…" };
        case (_slideWhy isNotEqualTo ""): { _slideWhy + "<br/>Touchez ACTUALISER pour réessayer." };
        default { "Image indisponible (réseau ou cache).<br/>Touchez ACTUALISER pour réessayer." };
    };
    private _t = ["COMSPEC_RscStructuredText", [_vx + _pad, _vy + _vh * 0.38, _vw - 2 * _pad, _rowH * 3]] call comspec_atak_native_fnc_pageCtrl;
    _t ctrlSetStructuredText parseText ((format ["<t align='center' color='#8a9a93'>%1</t>", _msg]) call comspec_atak_native_fnc_tr);
} else {
    ["COMSPEC_RscSlide", [_vx, _vy, _vw, _vh], _path] call comspec_atak_native_fnc_pageCtrl;
};

// Titre, source et position
private _title = if (_total > 0) then { _titles param [_idx, ""] } else { "" };
private _srcLabel = createHashMapFromArray [["GOOGLE", "Google Slides · synchronisé"], ["ATHENA", "Athena"], ["LOCAL", "Briefing"]] getOrDefault [_src, ""];
// Opération Athena rattachée à la diapositive (vide : commune à toutes les opérations).
private _op = if (_src isEqualTo "ATHENA" && {_total > 0}) then { ((missionNamespace getVariable ["COMSPEC_BriefingSlides", []]) select _idx) param [5, ""] } else { "" };
if (_op isNotEqualTo "") then {
    { _op = [_op, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]];
    _srcLabel = format ["<t color='#7fb6e6'>Op. %1</t>", _op];
};
private _info = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh + _pad / 2, _vw, _fs * 1.6]] call comspec_atak_native_fnc_pageCtrl;
_info ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold'>%1</t><t align='right' size='0.85' color='#8a9a93'>%2 · %3 / %4</t>", _title, _srcLabel, [_idx + 1, 0] select (_total < 1), _total]) call comspec_atak_native_fnc_tr);

// Notes de la diapositive (texte saisi sur Athena).
if (_notesH > 0) then {
    private _nt = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh + _pad / 2 + _fs * 1.6, _vw, _notesH]] call comspec_atak_native_fnc_pageCtrl;
    private _e = _detail;
    { _e = [_e, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]];
    _nt ctrlSetStructuredText parseText ((format ["<t size='0.85' color='#c9d4cf'>%1</t>", [_e, " ¶ ", "<br/>"] call CBA_fnc_replace]) call comspec_atak_native_fnc_tr);
};
// Bandeau de présentation : qui présente, présents, PRÉSENTER / SUIVRE.
if (_liveH > 0) then {
    private _ly = _bh - _btnH - _pad - _liveH;
    private _txt = switch (true) do {
        case (_iPresent): { private _a = ["attendees"] call comspec_atak_native_fnc_briefingLive; format ["<t color='#5cc76b' font='COMSPEC_BarlowBold'>● VOUS PRÉSENTEZ</t>  <t size='0.85' color='#8a9a93'>%1 présent(s)%2</t>", count _a, ["", format [" : %1", ((_a select [0, 6]) apply { name _x }) joinString ", "]] select ((count _a) > 0)] };
        case ((count _live) > 0): { format ["<t color='#f2ab33' font='COMSPEC_BarlowBold'>● %1 présente</t>  <t size='0.85' color='#8a9a93'>%2</t>", _live select 1, ["lecture libre", "vous suivez"] select _follow] };
        default { "<t size='0.85' color='#8a9a93'>Personne ne présente. PRÉSENTER fait suivre vos diapositives aux téléphones de votre camp.</t>" };
    };
    private _lt = ["COMSPEC_RscStructuredText", [_vx, _ly, _vw * 0.66, _liveH]] call comspec_atak_native_fnc_pageCtrl;
    _lt ctrlSetStructuredText parseText ((_txt) call comspec_atak_native_fnc_tr);
    private _lw = _vw * 0.34 - _pad / 2;
    if ((count _live) > 0 && {!_iPresent}) then {
        [[_vx + _vw - _lw, _ly, _lw, _fs * 1.5], ["SUIVRE", "SUIVI ✓"] select _follow, { ["follow"] call comspec_atak_native_fnc_briefingAction; }, _follow] call _btn;
    } else {
        [[_vx + _vw - _lw, _ly, _lw, _fs * 1.5], ["PRÉSENTER", "ARRÊTER"] select _iPresent, { ["present"] call comspec_atak_native_fnc_briefingAction; }, _iPresent] call _btn;
    };
};

// Navigation
private _by = _bh - _btnH - _pad;
private _canRefresh = _bridge && {_src isNotEqualTo "GOOGLE" || {_pref isEqualTo "GOOGLE"}};
private _n = [2, 3] select _canRefresh;
private _w = (_vw - (_n - 1) * _pad / 2) / _n;
private _bx = _vx;
[[_bx, _by, _w, _btnH], "« PRÉCÉDENTE", { ["step", -1] call comspec_atak_native_fnc_briefingAction; }] call _btn;
_bx = _bx + _w + _pad / 2;
if (_canRefresh) then {
    [[_bx, _by, _w, _btnH], "ACTUALISER", { ["refresh"] call comspec_atak_native_fnc_briefingAction; }] call _btn;
    _bx = _bx + _w + _pad / 2;
};
[[_bx, _by, _w, _btnH], "SUIVANTE »", { ["step", 1] call comspec_atak_native_fnc_briefingAction; }, true] call _btn;
true
