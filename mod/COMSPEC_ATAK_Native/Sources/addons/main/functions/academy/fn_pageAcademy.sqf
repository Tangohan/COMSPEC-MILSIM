/*
    App Academy : mes cours en cours, choix d'une leçon, lecture page par page, diffusion sur les écrans (fn_academy).
*/
disableSerialization;
["ensure"] call comspec_atak_native_fnc_academy;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_Academy", createHashMap];
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _icon = "\z\comspec_atak_native\addons\main\data\app_notes.paa";
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _txt = { params ["_t"]; [[_t] call _esc, "\n", "<br/>"] call CBA_fnc_replace };
private _stages = ["Cours", "Démonstration", "Cas guidé", "Cas sans aide", "Évaluation", "Débriefing", "Validation"];
private _view = _st getOrDefault ["view", "LIST"];
private _busy = _st getOrDefault ["busy", false];
private _err = _st getOrDefault ["err", ""];
private _staff = _st getOrDefault ["staff", false];
private _rows = [];
private _sub = {
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["ACADEMY", { ["list"] call comspec_atak_native_fnc_academy; }]];
};

if (_view isEqualTo "LESSON" && {(count (_st getOrDefault ["lesson", []])) >= 9}) exitWith {
    call _sub;
    private _ls = _st get "lesson";
    _ls params ["_ref", "_key", "_ch", "_course", "_chTitle", "_chN", "_pages", "_stage", "_slides"];
    private _i = ((_st getOrDefault ["idx", 0]) max 0) min (((count _pages) - 1) max 0);
    (_pages param [_i, ["", 0, "", ""]]) params ["", "_pStage", "_pTitle", "_pBody"];
    _rows pushBack ["strip", format ["<t font='COMSPEC_BarlowBold'>%1</t>", [_course] call _esc], format ["Leçon %1 / %2 · page %3 / %4", _ch + 1, _chN, _i + 1, count _pages], [0.07, 0.12, 0.10, 1]];
    _rows pushBack ["text", format ["<t size='1.2' font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#e0b040'>%2</t>", [_pTitle] call _esc, _stages param [_pStage, ""]]];
    _rows pushBack ["text", format ["<t size='0.95' color='#e8eee6'>%1</t>", [_pBody] call _txt], [0.05, 0.085, 0.075, 1]];
    _rows pushBack ["buttons", [
        ["« Précédente", { ["step", -1] call comspec_atak_native_fnc_academy; }, false, _i > 0],
        ["Suivante »", { ["step", 1] call comspec_atak_native_fnc_academy; }, true, _i < ((count _pages) - 1)]
    ]];
    if (_stage in [0, 1]) then {
        _rows pushBack ["text", format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", format ["Votre étape sur Academy : %1. Lisez-la jusqu'à sa dernière page : elle sera notée comme vue sur le site.", _stages select _stage]]];
    } else {
        if (_stage >= 2) then { _rows pushBack ["text", format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%1</t>", format ["Votre étape sur Academy : %1. Les exercices et l'évaluation se font sur le site.", _stages param [_stage, ""]]]]; };
    };
    { _rows pushBack ["buttons", [[format ["Diapositives : %1", _x select 0], compile format ["['slides', %1] call comspec_atak_native_fnc_academy;", str (_x select 1)], false, true, "Ouvre les diapositives du cours dans l'app Briefing (que vous pouvez ensuite diffuser)"]]]; } forEach _slides;
    if (_staff) then {
        private _screens = ["screens"] call comspec_atak_native_fnc_academy;
        _rows pushBack ["section", "Écrans à portée", "Montrer la leçon aux joueurs proches"];
        if ((count _screens) isEqualTo 0) then {
            _rows pushBack ["text", "<t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Aucun écran à portée. Approchez-vous d'un écran COMSPEC ou d'un écran TOC (posé dans l'éditeur, par Zeus ou avec le module Écran).</t>"];
        };
        {
            private _o = _x;
            private _c = _o getVariable ["COMSPEC_ATAK_ScreenCast", []];
            private _mine = (count _c) >= 6 && {(_c select 0) isEqualTo player} && {(_c select 5) isEqualTo _ref};
            private _name = ((_o getVariable ["COMSPEC_ATAK_Screen", []]) param [0, ""]);
            if (_name isEqualTo "") then { _name = ((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, getText (configOf _o >> "displayName")]); };
            private _state = switch (true) do {
                case _mine: { format ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>● cette leçon est à l'écran · page %1</t>", (_c select 4) + 1] };
                case ((count _c) >= 5): { format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>● %1 diffuse</t>", [_c select 1] call _esc] };
                default { "<t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>libre</t>" };
            };
            private _id = netId _o;
            _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>%2 m</t><br/><t size='0.85'>%3</t>", [_name] call _esc, round (player distance _o), _state], [
                [["DIFFUSER", "ARRÊTER"] select _mine, compile format ["['%1', objectFromNetId '%2'] call comspec_atak_native_fnc_academy;", ["cast", "stop"] select _mine, _id], !_mine]
            ]];
        } forEach _screens;
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

if (_view isEqualTo "PICK" && {(count (_st getOrDefault ["pick", []])) >= 3}) exitWith {
    call _sub;
    (_st get "pick") params ["_key", "_title", "_n"];
    _rows pushBack ["section", _title, "Choisissez la leçon à ouvrir"];
    private _b = [];
    for "_i" from 0 to ((_n min 20) - 1) do {
        _b pushBack [format ["Leçon %1", _i + 1], compile format ["['open', [%1, %2]] call comspec_atak_native_fnc_academy;", str _key, _i], false, !_busy];
        if ((count _b) isEqualTo 4) then { _rows pushBack ["buttons", _b]; _b = []; };
    };
    if ((count _b) > 0) then { _rows pushBack ["buttons", _b]; };
    if (_busy) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>Chargement de la leçon…</t>"]; };
    if (_err isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", _err]]; };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};

(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) deleteAt "backSub";
private _mine = _st getOrDefault ["courses", []];
_rows pushBack ["hero", _icon, "<t size='1.35' font='COMSPEC_BarlowBold'>Academy</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextMuted") + "'>Vos cours COMSPEC Academy, à lire en jeu</t>"];
if (_busy) then { _rows pushBack ["text", "<t color='" + (COMSPEC_ATAK_Th get "hexInfo") + "'>Chargement…</t>"]; };
if (_err isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", _err]]; };
_rows pushBack ["section", "Mes cours en cours", ""];
if ((count _mine) isEqualTo 0 && {!_busy} && {_err isEqualTo ""}) then {
    _rows pushBack ["empty", _icon, "Aucun cours suivi", "Inscrivez-vous à un cours sur le site Academy : il apparaîtra ici."];
};
{
    _x params ["_key", "_title", "_ch", "_n", "_stage", "_done", "_chTitle"];
    private _line = if (_done) then { "Cours terminé" } else { format ["Leçon %1 sur %2 · %3 · %4", _ch + 1, _n, [_chTitle] call _esc, _stages param [_stage, ""]] };
    _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2</t>", [_title] call _esc, _line], [
        [["Ouvrir", "Revoir"] select _done, [
            compile format ["['open', [%1, %2]] call comspec_atak_native_fnc_academy;", str _key, _ch],
            compile format ["['pick', [%1, %2, %3]] call comspec_atak_native_fnc_academy;", str _key, str _title, _n]
        ] select _done, true, !_busy]
    ]];
} forEach _mine;
if (_staff) then {
    _rows pushBack ["section", "Cours de la communauté", "Encadrement Formation : ouvrir une leçon pour la présenter"];
    {
        _x params ["_key", "_title", "_n", "_pub"];
        _rows pushBack ["person", _icon, format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2</t>", [_title] call _esc, format ["%1 leçon%2%3", _n, ["", "s"] select (_n > 1), ["  · brouillon", ""] select _pub]], [
            ["Choisir", compile format ["['pick', [%1, %2, %3]] call comspec_atak_native_fnc_academy;", str _key, str _title, _n], false, !_busy]
        ]];
    } forEach (_st getOrDefault ["all", []]);
};
_rows pushBack ["buttons", [["ACTUALISER", { ["load"] call comspec_atak_native_fnc_academy; }, false, !_busy]]];
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
