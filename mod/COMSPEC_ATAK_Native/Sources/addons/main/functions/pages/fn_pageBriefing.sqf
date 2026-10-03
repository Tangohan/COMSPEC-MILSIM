/*
    App Briefing, deux onglets :
    - DIAPOSITIVES : deck Google Slides partagé par le présentateur (synchronisé pour tous par Overwatch)
      ou diapositives publiées sur Athena ; sommaire cliquable, précédente / suivante, actualiser.
    - MISSION : nom, carte, date et heure, météo, puis les notes du briefing de la mission (journal Arma).
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
private _tw = (_gw - 2 * _pad) / 2;
[[_pad, _pad, _tw - _pad / 6, _rowH], "DIAPOSITIVES", { ["tab", "SLIDES"] call comspec_atak_native_fnc_briefingAction; }, _tab isEqualTo "SLIDES"] call _btn;
[[_pad + _tw, _pad, _tw - _pad / 6, _rowH], "MISSION", { ["tab", "MISSION"] call comspec_atak_native_fnc_briefingAction; }, _tab isEqualTo "MISSION"] call _btn;
private _y0 = _pad * 2 + _rowH;

if (_tab isEqualTo "MISSION") exitWith {
    private _h = { params ["_t"]; format ["<t color='#5cc76b' size='0.85' font='RobotoCondensedBold'>%1</t>", toUpper _t] };
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
        _t = _t regexReplace [" size=['""][0-9.]+['""]", ""];
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
        format ["<t font='RobotoCondensedBold' size='1.1'>%1</t>", _mission],
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
                _lines pushBack format ["<t font='RobotoCondensedBold'>%1</t>", _x param [1, ""]];
                _lines pushBack ([_x param [2, ""]] call _clean);
                _lines pushBack "";
                _count = _count + 1;
            } forEach _recs;
        };
    } forEach _subjects;
    if (_count isEqualTo 0) then { _lines pushBack (["La mission ne contient pas de notes de briefing."] call _dim); };
    private _text = ["COMSPEC_RscStructuredText", [_pad, _y0, _gw - 2 * _pad, _bh - _y0 - _pad]] call comspec_atak_native_fnc_pageCtrl;
    _text ctrlSetStructuredText parseText (_lines joinString "<br/>");
    _text ctrlSetPosition [_pad, _y0, _gw - 2 * _pad, (ctrlTextHeight _text) max (_bh - _y0 - _pad)];
    _text ctrlCommit 0;
    true
};

// DIAPOSITIVES
([] call comspec_atak_native_fnc_briefingSignature) params ["_src", "_idx", "_total", "_path", "_titles"];
if (_src isEqualTo "ATHENA" && {_total > 0}) then {
    // Image en cache local (téléchargée une fois par diapositive).
    private _cache = uiNamespace getVariable ["COMSPEC_ATAK_SlideCache", createHashMap];
    if !(_path in _cache) then {
        private _slide = (missionNamespace getVariable ["COMSPEC_BriefingSlides", []]) select _idx;
        _cache set [_path, [_slide] call comspec_overwatch_connect_fnc_downloadBriefingSlide];
        uiNamespace setVariable ["COMSPEC_ATAK_SlideCache", _cache];
    };
    _path = _cache get _path;
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
        _toc lbAdd format ["%1. %2", _forEachIndex + 1, _t];
    } forEach _titles;
    _toc lbSetCurSel _idx;
    _toc ctrlAddEventHandler ["LBSelChanged", { params ["", "_i"]; ["goto", _i] call comspec_atak_native_fnc_briefingAction; }];
};

private _bg = ["COMSPEC_RscPanel", [_vx, _vy, _vw, _vh]] call comspec_atak_native_fnc_pageCtrl;
_bg ctrlSetBackgroundColor [0, 0, 0, 1];
if (_total < 1 || {_path isEqualTo ""}) then {
    private _msg = switch (true) do {
        case (_total < 1 && {_bridge}): { "Aucune diapositive pour l'instant.<br/>Le présentateur partage un Google Slides depuis le tableau de briefing, ou publiez des diapositives sur Athena puis touchez ACTUALISER." };
        case (_total < 1): { "Aucun briefing reçu.<br/>Les diapositives Athena demandent Overwatch connect." };
        case (_src isEqualTo "GOOGLE"): { "Chargement de la diapositive…" };
        default { "Image indisponible (réseau ou cache).<br/>Touchez ACTUALISER pour réessayer." };
    };
    private _t = ["COMSPEC_RscStructuredText", [_vx + _pad, _vy + _vh * 0.38, _vw - 2 * _pad, _rowH * 3]] call comspec_atak_native_fnc_pageCtrl;
    _t ctrlSetStructuredText parseText format ["<t align='center' color='#8a9a93'>%1</t>", _msg];
} else {
    ["COMSPEC_RscSlide", [_vx, _vy, _vw, _vh], _path] call comspec_atak_native_fnc_pageCtrl;
};

// Titre, source et position
private _title = if (_total > 0) then { _titles param [_idx, ""] } else { "" };
private _srcLabel = createHashMapFromArray [["GOOGLE", "Google Slides · synchronisé"], ["ATHENA", "Athena"], ["LOCAL", "Briefing"]] getOrDefault [_src, ""];
private _info = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh + _pad / 2, _vw, _fs * 1.6]] call comspec_atak_native_fnc_pageCtrl;
_info ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold'>%1</t><t align='right' size='0.85' color='#8a9a93'>%2 · %3 / %4</t>", _title, _srcLabel, [_idx + 1, 0] select (_total < 1), _total];

// Notes de la diapositive (texte saisi sur Athena).
if (_notesH > 0) then {
    private _nt = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh + _pad / 2 + _fs * 1.6, _vw, _notesH]] call comspec_atak_native_fnc_pageCtrl;
    private _e = _detail;
    { _e = [_e, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]];
    _nt ctrlSetStructuredText parseText format ["<t size='0.85' color='#c9d4cf'>%1</t>", [_e, " ¶ ", "<br/>"] call CBA_fnc_replace];
};
// Bandeau de présentation : qui présente, présents, PRÉSENTER / SUIVRE.
if (_liveH > 0) then {
    private _ly = _bh - _btnH - _pad - _liveH;
    private _txt = switch (true) do {
        case (_iPresent): { private _a = ["attendees"] call comspec_atak_native_fnc_briefingLive; format ["<t color='#5cc76b' font='RobotoCondensedBold'>● VOUS PRÉSENTEZ</t>  <t size='0.85' color='#8a9a93'>%1 présent(s)%2</t>", count _a, ["", format [" : %1", ((_a select [0, 6]) apply { name _x }) joinString ", "]] select ((count _a) > 0)] };
        case ((count _live) > 0): { format ["<t color='#f2ab33' font='RobotoCondensedBold'>● %1 présente</t>  <t size='0.85' color='#8a9a93'>%2</t>", _live select 1, ["lecture libre", "vous suivez"] select _follow] };
        default { "<t size='0.85' color='#8a9a93'>Personne ne présente. PRÉSENTER fait suivre vos diapositives aux téléphones de votre camp.</t>" };
    };
    private _lt = ["COMSPEC_RscStructuredText", [_vx, _ly, _vw * 0.66, _liveH]] call comspec_atak_native_fnc_pageCtrl;
    _lt ctrlSetStructuredText parseText _txt;
    private _lw = _vw * 0.34 - _pad / 2;
    if ((count _live) > 0 && {!_iPresent}) then {
        [[_vx + _vw - _lw, _ly, _lw, _fs * 1.5], ["SUIVRE", "SUIVI ✓"] select _follow, { ["follow"] call comspec_atak_native_fnc_briefingAction; }, _follow] call _btn;
    } else {
        [[_vx + _vw - _lw, _ly, _lw, _fs * 1.5], ["PRÉSENTER", "ARRÊTER"] select _iPresent, { ["present"] call comspec_atak_native_fnc_briefingAction; }, _iPresent] call _btn;
    };
};

// Navigation
private _by = _bh - _btnH - _pad;
private _canRefresh = _bridge && {_src isNotEqualTo "GOOGLE"};
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
