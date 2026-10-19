/*
    App « SSE terrain » du téléphone ATAK (page SSEFIELD, déclarée dans COMSPEC_ATAK_Apps).
    Tout le travail SSE hors gestes physiques passe ici, au lieu des menus ACE :
      CIBLE    : entité visée (personne ou objet), état, niveau d'exploitation, scellé, photos ;
                 approfondir, documents, SEEK, chronologie, DL-7, transmission, fiche FRS
      SITE     : avancement de la doctrine SSE autour du joueur (photos, fouille, biométrie,
                 numérique, scellés, transmission) et feu vert d'exfiltration
      SCELLÉS  : pièces que je détiens, registre
      JOURNAL  : derniers gestes du joueur, campagne en cours
    Params (fn_pageRender de l'ATAK) : [page, rectangle]. Actions : comspec_sse_fnc_fieldAction.
*/
params [["_page", "SSEFIELD"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if ((count _rect) isEqualTo 4) then { _bw = _rect select 2; _bh = _rect select 3; };

private _st = uiNamespace getVariable ["comspec_sse_field", createHashMap];
uiNamespace setVariable ["comspec_sse_field", _st];
private _tab = _st getOrDefault ["tab", "target"];
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _tr = { _this call comspec_sse_fnc_tr };
private _act = { params ["_a", ["_arg", ""]]; compile format ["['%1', %2] call comspec_sse_fnc_fieldAction;", _a, str _arg] };
private _icon = "\z\comspec_atak_native\addons\main\data\app_sse.paa";
private _rows = [];

// Bandeau : dossier actif, carroyage, heure.
private _case = [] call comspec_sse_fnc_getCaseReference;
_rows pushBack ["strip",
    format ["<t font='EtelkaMonospaceProBold'>%1</t>", [[_case] call _esc, "SANS DOSSIER" call _tr] select (_case isEqualTo "")],
    format ["<t font='EtelkaMonospacePro'>%1 · %2</t>", mapGridPosition player, [dayTime, "HH:MM"] call BIS_fnc_timeToString],
    [0.071, 0.09, 0.114, 1]];
_rows pushBack ["segment", "", [
    ["CIBLE" call _tr, ["tab", "target"] call _act, _tab isEqualTo "target"],
    ["SITE" call _tr, ["tab", "site"] call _act, _tab isEqualTo "site"],
    ["SCELLÉS" call _tr, ["tab", "custody"] call _act, _tab isEqualTo "custody"],
    ["JOURNAL" call _tr, ["tab", "journal"] call _act, _tab isEqualTo "journal"]
]];

switch (_tab) do {
    case "site": {
        private _s = [player, 50] call comspec_sse_fnc_fieldSite;
        if ((_s get "total") isEqualTo 0) exitWith {
            _rows pushBack ["empty", _icon, "Aucun élément SSE à moins de 50 m" call _tr, "Approchez-vous du site à exploiter." call _tr];
        };
        private _want = (_s get "steps") select { (_x select 3) > 0 };
        _rows pushBack ["section", "Avancement du site" call _tr, format ["%1 élément(s) à moins de 50 m" call _tr, _s get "total"]];
        // Frise : fait (tout), en cours (partiel), à faire (rien).
        _rows pushBack ["steps", _want apply {
            _x params ["", "_label", "_done", "_n"];
            [_label call _tr, ["todo", ["now", "done"] select (_done >= _n)] select (_done > 0)]
        }];
        {
            _x params ["", "_label", "_done", "_n"];
            _rows pushBack ["info", _label call _tr, format ["%1 / %2", _done, _n]];
        } forEach _want;
        private _pct = str (round ((_s get "ratio") * 100));
        _rows pushBack ["banner",
            [format ["<t color='#f2ab33'>%1</t>", format ["EXFILTRATION : SSE INCOMPLET (%1 %%)" call _tr, _pct]],
             format ["<t color='#34d399'>%1</t>", format ["EXFILTRATION : FEU VERT (%1 %%)" call _tr, _pct]]] select (_s get "go"),
            [[0.18, 0.12, 0.03, 1], [0.04, 0.16, 0.1, 1]] select (_s get "go")];
        _rows pushBack ["buttons", [
            ["Tableau des liens" call _tr, ["chart"] call _act],
            ["Triage du site" call _tr, ["triage"] call _act]
        ]];
    };
    case "custody": {
        private _held = ["held", [player]] call comspec_sse_fnc_custody;
        private _all = missionNamespace getVariable ["comspec_sse_custody", createHashMap];
        _rows pushBack ["section", "Pièces que je détiens" call _tr, format ["%1 pièce(s)" call _tr, count _held]];
        if (_held isEqualTo []) then {
            _rows pushBack ["text", format ["<t color='#8b98a7'>%1</t>", "Aucune pièce sous votre responsabilité." call _tr]];
        };
        {
            private _p = _all getOrDefault [_x, createHashMap];
            _rows pushBack ["info",
                format ["%1 · %2", [_x] call _esc, [_p getOrDefault ["label", ""]] call _esc],
                format ["%1 %2", [_p getOrDefault ["seal", "-"]] call _esc, [(_p getOrDefault ["state", ""]) call _tr] call _esc]];
        } forEach _held;
        _rows pushBack ["buttons", [["Registre des scellés" call _tr, ["registry"] call _act, true]]];
        _rows pushBack ["text", format ["<t size='0.85' color='#8b98a7'>%1</t>", "Remise en main propre : interaction sur l'équipier (Remettre mes pièces scellées)." call _tr]];
    };
    case "journal": {
        private _j = [] call comspec_sse_fnc_getJournal;
        _rows pushBack ["section", "Journal SSE" call _tr, format ["%1 entrée(s)" call _tr, count _j]];
        // reverse agit sur place : copie des 10 dernières entrées, la plus récente en tête.
        private _last = _j select [((count _j) - 10) max 0, 10];
        reverse _last;
        {
            _rows pushBack ["info",
                format ["%1 · %2", _x getOrDefault ["clock", "?"], [(_x getOrDefault ["type", "?"]) call _tr] call _esc],
                [_x getOrDefault ["summary", _x getOrDefault ["id", ""]]] call _esc];
        } forEach _last;
        if (!isNil "comspec_sse_fnc_campaignInfo") then {
            _rows pushBack ["section", "Campagne" call _tr, ""];
            _rows pushBack ["text", ([] call comspec_sse_fnc_campaignInfo) call _tr];
        };
    };
    default {
        private _t = [] call comspec_sse_fnc_fieldTarget;
        if (isNull _t) exitWith {
            _rows pushBack ["empty", _icon, "Aucune cible" call _tr, "Regardez une personne ou un objet du site (6 m au plus)." call _tr];
        };
        private _data = [_t] call comspec_sse_fnc_getData;
        private _type = [_data, "type", "OBJECT"] call comspec_sse_fnc_getPair;
        private _isMan = _t isKindOf "CAManBase";
        private _id = [_t, "identity"] call comspec_sse_fnc_getSection;
        private _name = if (!isNil "_id" && {_id isEqualType createHashMap}) then { _id getOrDefault ["name", ""] } else { "" };
        if (_name isEqualTo "") then { _name = [getText (configOf _t >> "displayName"), name _t] select _isMan; };
        private _lvl = [_t] call comspec_sse_fnc_getExploitationLevel;
        private _state = [_data, "state", "UNTOUCHED"] call comspec_sse_fnc_getPair;
        private _pid = ["piece", [_t]] call comspec_sse_fnc_custody;
        private _piece = (missionNamespace getVariable ["comspec_sse_custody", createHashMap]) getOrDefault [_pid, createHashMap];
        private _photos = [_t, "photos"] call comspec_sse_fnc_getSection;
        private _nPh = if (!isNil "_photos" && {_photos isEqualType []}) then { count _photos } else { 0 };

        _rows pushBack ["person", _icon,
            format ["<t size='1.1' font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8b98a7'>%2 · %3 · %4 m</t>",
                [_name] call _esc, _type call _tr, _state call _tr, round (player distance _t)], [], [0.29, 0.64, 1, 1]];
        _rows pushBack ["info", "Exploitation" call _tr, _lvl call _tr];
        _rows pushBack ["info", "Pièce / scellé" call _tr, [format ["%1 %2", _pid, _piece getOrDefault ["seal", ""]], "non saisie" call _tr] select (_pid isEqualTo "")];
        _rows pushBack ["info", "Photos de preuve" call _tr, [str _nPh, "aucune (app Photo)" call _tr] select (_nPh isEqualTo 0)];

        private _btns = [
            ["Photo de preuve" call _tr, ["photo"] call _act, _nPh isEqualTo 0],
            ["Approfondir" call _tr, ["exploit"] call _act, true],
            ["Documents" call _tr, ["docs"] call _act],
            ["Chronologie" call _tr, ["timeline"] call _act]
        ];
        if (_isMan) then {
            _btns pushBack ["SEEK II", ["seek"] call _act];
        };
        if (!isNil { _t getVariable "COMSPEC_ATAK_Data" }) then {
            _btns pushBack ["Brancher le DL-7" call _tr, ["dl7"] call _act, true];
        };
        _rows pushBack ["buttons", _btns];
        _rows pushBack ["buttons", [
            ["Transmettre à Athena" call _tr, ["transmit"] call _act],
            ["Fiche FRS" call _tr, ["frs"] call _act],
            ["Marquer exploité" call _tr, ["exploited"] call _act, false, _state isNotEqualTo "EXPLOITED"]
        ]];

        // Résultat du dernier geste sur cette cible (approfondir, chronologie).
        private _res = _st getOrDefault ["result", []];
        if ((_res param [0, objNull]) isEqualTo _t) then {
            _rows pushBack ["section", (_res param [1, ""]) call _tr, ""];
            { _rows pushBack ["text", format ["<t size='0.9'>%1</t>", [_x] call _esc]]; } forEach (_res param [2, []]);
        };
    };
};

[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
