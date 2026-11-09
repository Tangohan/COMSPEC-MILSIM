/*
    App « Bilan de l'année » : la rétrospective du site (heures de jeu, kilomètres, blessures, SSE, comptes rendus et, si la
    formule de la communauté le comprend, le bilan de l'unité) en écrans qui défilent, un par page, avec PRÉCÉDENT / SUIVANT.
    Données : fn_yearReview (GetYearReview de COMSPEC Link, après liaison Athena autorisée). Rien n'est envoyé au site.
    État : uiNamespace COMSPEC_ATAK_State, clé « yrIdx » (écran affiché).
    Params (module, fn_pageRender) : [page, [x, y, largeur, hauteur]] — appelée aussi sans paramètre.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _th = COMSPEC_ATAK_Th;
private _muted = "<t color='" + (_th get "hexTextMuted") + "'>%1</t>";
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; [[[_t, "&", "&amp;"] call CBA_fnc_replace, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace };
private _icon = "\z\comspec_atak_native\addons\main\data\app_reports.paa";
["load", false] call comspec_atak_native_fnc_yearReview;
private _store = ["store"] call comspec_atak_native_fnc_yearReview;
private _state = _store get "state";
private _rows = [];
private _msg = {
    params ["_title", "_text"];
    _rows pushBack ["hero", _icon, format ["<t size='1.3' font='COMSPEC_BarlowBold'>%1</t><br/>%2", [_title call comspec_atak_native_fnc_tr] call _esc, format [_muted, [_text call comspec_atak_native_fnc_tr] call _esc]]];
};
if (_state in ["IDLE", "LOADING"]) then {
    ["Bilan de l'année", "Chargement…"] call _msg;
    // Réponse attendue : la page se redessine toute seule tant qu'elle est ouverte.
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "YEARREVIEW") then { ["YEARREVIEW"] call comspec_atak_native_fnc_pageRender; }; }, [], 2] call comspec_atak_native_fnc_waitAndExec;
} else {
    if (_state isNotEqualTo "OK") then {
        switch (_state) do {
            case "NOLINK": { ["Bilan de l'année", "Reliez votre compte Athena (app Athena) pour voir votre bilan."] call _msg; };
            case "FORBID": { ["Bilan de l'année", "Votre communauté ne vous donne pas accès au bilan."] call _msg; };
            case "OFF": { ["Bilan de l'année", "COMSPEC Link n'est pas actif ou le service Athena est coupé."] call _msg; };
            case "DLL": { ["Bilan de l'année", "COMSPEC Link est trop ancienne pour le bilan de l'année : mettez-la à jour."] call _msg; };
            case "EMPTY": { ["Bilan de l'année", "Le bilan n'est pas encore publié. Il apparaît à la date choisie par l'administration du site."] call _msg; };
            default { ["Bilan de l'année", "Le site ne répond pas pour le moment."] call _msg; };
        };
        _rows pushBack ["buttons", [["RÉESSAYER", { ["load", true] call comspec_atak_native_fnc_yearReview; [{ ["YEARREVIEW"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; }, true]]];
    } else {
        private _slides = _store get "slides";
        private _n = count _slides;
        private _i = (((_s getOrDefault ["yrIdx", 0]) max 0) min (_n - 1));
        private _sl = _slides select _i;
        private _tone = _sl get "tone";
        private _hex = switch (_tone) do {
            case "olive": { _th get "hexOk" };
            case "gold": { _th get "hexWarn" };
            case "slate": { _th get "hexTextMuted" };
            default { _th get "hexInfo" };
        };
        private _t = "";
        if ((_sl get "kicker") isNotEqualTo "") then { _t = _t + format ["<t size='0.8' color='%1'>%2</t><br/>", _hex, [toUpper (_sl get "kicker")] call _esc]; };
        if ((_sl get "name") isNotEqualTo "") then { _t = _t + format ["<t size='1.1' font='COMSPEC_BarlowBold'>%1</t><br/>", [_sl get "name"] call _esc]; };
        if ((_sl get "big") isNotEqualTo "") then { _t = _t + format ["<t size='2' font='COMSPEC_BarlowBold' color='%1'>%2</t><br/>", _hex, [_sl get "big"] call _esc]; };
        if ((_sl get "big_label") isNotEqualTo "") then { _t = _t + format [_muted + "<br/>", [_sl get "big_label"] call _esc]; };
        if ((_sl get "title") isNotEqualTo "") then { _t = _t + format ["<t size='1.2' font='COMSPEC_BarlowBold'>%1</t>", [_sl get "title"] call _esc]; };
        _rows pushBack ["hero", _icon, _t];
        if ((_sl get "lead") isNotEqualTo "") then { _rows pushBack ["text", format [_muted, [_sl get "lead"] call _esc]]; };
        if ((_sl get "persona") isNotEqualTo "") then { _rows pushBack ["info", "Profil", _sl get "persona"]; };
        { _x params ["_k", "_v"]; _rows pushBack ["info", [_k] call _esc, [_v] call _esc]; } forEach (_sl get "stats");
        private _bars = _sl get "bars";
        if ((count _bars) > 0) then {
            _rows pushBack ["section", "Mois par mois", ""];
            private _bt = "";
            {
                _x params ["_m", "_pct"];
                private _on = ""; private _off = "";
                for "_k" from 1 to 14 do { if (_k <= round (_pct * 14 / 100)) then { _on = _on + "|"; } else { _off = _off + "|"; }; };
                _bt = _bt + format ["<t font='EtelkaMonospaceProBold'>%1 </t><t font='EtelkaMonospaceProBold' color='%2'>%3</t><t font='EtelkaMonospaceProBold' color='#2a3430'>%4</t><br/>", [_m] call _esc, _th get "hexOk", _on, _off];
            } forEach _bars;
            _rows pushBack ["text", _bt];
        };
        {
            _x params ["_title", "_list"];
            _rows pushBack ["section", [_title] call _esc, ""];
            { _x params ["_rank", "_who", "_val"]; _rows pushBack ["info", format ["%1. %2", _rank, [_who] call _esc], [_val] call _esc]; } forEach _list;
        } forEach (_sl get "boards");
        if (_store get "partial") then {
            _rows pushBack ["text", format [_muted, ("Bilan provisoire : l'année n'est pas terminée.") call comspec_atak_native_fnc_tr]];
        };
        private _go = { params ["_d"]; compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['yrIdx', %1]; [{ ['YEARREVIEW'] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;", _d] };
        _rows pushBack ["buttons", [
            ["‹ PRÉCÉDENT", [_i - 1] call _go, false, _i > 0],
            [format ["%1 / %2", _i + 1, _n], {}, false, false],
            ["SUIVANT ›", [_i + 1] call _go, true, _i < (_n - 1)]
        ]];
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
