/*
    App Comptes rendus : tous les C.R. au même endroit.
      NOUVEAU : choix du type par famille (combat et renseignement, feux, santé, logistique, menaces, ordres),
                puis le formulaire complet du type (catalogue fn_reportTypes) ;
      ENVOYÉS : mes C.R., avec leur arrivée sur Athena (et RENVOYER s'ils n'y sont pas arrivés) ;
      REÇUS   : les C.R. du camp ou de mon groupe, les plus récents d'abord.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_RepUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_RepUi", _ui];
private _vals = _ui getOrDefault ["vals", createHashMap];
private _tab = _ui getOrDefault ["tab", "NEW"];
private _types = [] call comspec_atak_native_fnc_reportTypes;
private _list = values (missionNamespace getVariable ["COMSPEC_ATAK_Reports", createHashMap]);
private _uid = getPlayerUID player;
private _mine = _list select { (_x getOrDefault ["uid", ""]) isEqualTo _uid };
private _recv = _list select { (_x getOrDefault ["uid", ""]) isNotEqualTo _uid };
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_k", ""], ["_v", ""]]; compile format ["[%1, %2, %3] call comspec_atak_native_fnc_reportAction;", str _a, str _k, str _v] };
private _pages = ([] call comspec_atak_native_fnc_appList) apply { _x get "page" };

private _rows = [["segment", "", [
    ["NOUVEAU", ["tab", "NEW"] call _act, _tab isEqualTo "NEW"],
    [format ["ENVOYÉS (%1)", count _mine], ["tab", "SENT"] call _act, _tab isEqualTo "SENT"],
    [format ["REÇUS (%1)", count _recv], ["tab", "RECV"] call _act, _tab isEqualTo "RECV"]
]]];

switch (_tab) do {
    case "NEW": {
        private _code = _ui getOrDefault ["type", ""];
        private _ti = _types findIf { (_x select 0) isEqualTo _code };
        if (_ti < 0) then {
            // Liste des types, par famille ; deux par ligne.
            private _fam = "";
            private _pair = [];
            private _flush = { if ((count _pair) > 0) then { _rows pushBack ["buttons", +_pair]; _pair = []; }; };
            {
                _x params ["_c", "_label", "_family", "_web", "", "_fields"];
                // Raccourci vers une app masquée par la communauté : on ne le propose pas.
                if (_web isNotEqualTo "" || {(_fields select 0) in _pages}) then {
                    if (_family isNotEqualTo _fam) then {
                        call _flush;
                        _fam = _family;
                        _rows pushBack ["section", _family, ""];
                    };
                    _pair pushBack [[_label, _label + "  ›"] select (_web isEqualTo ""), ["type", _c] call _act, _web isNotEqualTo ""];
                    if ((count _pair) isEqualTo 2) then { call _flush; };
                };
            } forEach _types;
            call _flush;
            _rows pushBack ["text", "<t size='0.8' color='#8a9a93'>Les types suivis de › ouvrent l'app qui les gère déjà (Feux, JTAC, MEDEVAC du Médical, Logistique). Les autres s'envoient d'ici : au camp ou à votre groupe en jeu, et sur Athena quand le téléphone est connecté.</t>"];
        } else {
            (_types select _ti) params ["", "_label", "_family", "", "_help", "_fields"];
            _rows pushBack ["section", _label, _family];
            _rows pushBack ["text", format ["<t size='0.85' color='#c9d4cf'>%1</t>", _help]];
            _rows pushBack ["buttons", [["‹ TOUS LES TYPES", ["back"] call _act]]];
            date params ["_yy", "_mo", "_dd", "_hh", "_mi"];
            private _dtg = format ["%1%2%3 %4 %5", [_dd, 2] call CBA_fnc_formatNumber, [_hh, 2] call CBA_fnc_formatNumber, [_mi, 2] call CBA_fnc_formatNumber,
                ["JAN", "FÉV", "MAR", "AVR", "MAI", "JUN", "JUL", "AOÛ", "SEP", "OCT", "NOV", "DÉC"] select ((_mo - 1) max 0 min 11), _yy mod 100];
            private _here = [getPosASL player, 8] call comspec_atak_native_fnc_gridRef;
            {
                _x params ["_k", "_kind", "_flabel", ["_farg", ""], ["_fhelp", ""]];
                switch (_kind) do {
                    case "edit": { _rows pushBack ["edit", "rp_" + _k, _flabel, _vals getOrDefault [_k, _farg]]; };
                    case "num": { _rows pushBack ["edit", "rp_" + _k, _flabel, _vals getOrDefault [_k, _farg]]; };
                    case "dtg": { _rows pushBack ["edit", "rp_" + _k, _flabel, _vals getOrDefault [_k, _dtg]]; };
                    case "memo": { _rows pushBack ["memo", "rp_" + _k, _flabel, _vals getOrDefault [_k, ""], _farg]; };
                    case "grid": {
                        _rows pushBack ["edit", "rp_" + _k, _flabel, _vals getOrDefault [_k, _here]];
                        _rows pushBack ["buttons", [["MA POSITION", ["here", _k] call _act], ["SUR LA CARTE", ["pick", _k] call _act]]];
                    };
                    case "seg": {
                        private _cur = _vals getOrDefault [_k, _farg select 0];
                        _rows pushBack ["segment", _flabel, _farg apply { [_x, ["set", _k, _x] call _act, _x isEqualTo _cur] }, _fhelp];
                    };
                    case "combo": { _rows pushBack ["combo", "rp_" + _k, _flabel, _farg apply { [_x, _x] }, _vals getOrDefault [_k, _farg select 0]]; };
                };
            } forEach _fields;
            _rows pushBack ["buttons", [["TRANSMETTRE LE COMPTE RENDU", ["send"] call _act, true]]];
        };
    };
    default {
        private _sel = [_recv, _mine] select (_tab isEqualTo "SENT");
        _sel = _sel apply { [_x getOrDefault ["time", ""], _x getOrDefault ["id", ""], _x] };
        _sel sort false;
        private _open = _ui getOrDefault ["open", ""];
        if ((count _sel) isEqualTo 0) then {
            _rows pushBack ["text", format ["<t color='#8a9a93'>%1</t>", ["Aucun compte rendu reçu du camp pour l'instant.", "Vous n'avez encore envoyé aucun compte rendu."] select (_tab isEqualTo "SENT")]];
        };
        {
            private _r = _x select 2;
            private _id = _r getOrDefault ["id", ""];
            private _prio = _r getOrDefault ["prio", "ROUTINE"];
            private _col = switch (_prio) do { case "FLASH": { "#e5483a" }; case "IMMÉDIAT": { "#f08a3c" }; case "PRIORITAIRE": { "#e8c547" }; default { "#5cc76b" } };
            private _web = _r getOrDefault ["web", ""];
            private _webTxt = switch (_web) do {
                case "OK": { "<t color='#5cc76b'>Athena ✓</t>" };
                case "HORS LIGNE": { "<t color='#8a9a93'>Athena : hors ligne</t>" };
                case "ÉCHEC": { "<t color='#e5483a'>Athena : non reçu</t>" };
                default { "" };
            };
            private _meta = [_r getOrDefault ["by", "?"], _r getOrDefault ["time", ""], _r getOrDefault ["grid", ""]];
            if ((_r getOrDefault ["dest", "CAMP"]) isNotEqualTo "CAMP") then { _meta pushBack toLower (_r getOrDefault ["dest", ""]); };
            _rows pushBack ["text", format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t font='RobotoCondensedBold'>%3</t>  <t color='#8a9a93'>%4</t>%5<br/><t size='0.9'>%6</t>",
                _col, _prio, [_r getOrDefault ["label", "C.R."]] call _esc, [_meta joinString " · "] call _esc,
                ["", "  " + _webTxt] select (_tab isEqualTo "SENT" && {_webTxt isNotEqualTo ""}), [_r getOrDefault ["summary", ""]] call _esc]];
            if (_open isEqualTo _id) then {
                _rows pushBack ["text", ((_r getOrDefault ["lines", []]) apply {
                    format ["<t size='0.85' color='#8a9a93'>%1</t><br/><t size='0.95'>%2</t>", [_x select 1] call _esc, [_x select 2] call _esc]
                }) joinString "<br/>"];
            };
            private _btns = [[["DÉTAILS", "REPLIER"] select (_open isEqualTo _id), ["open", _id] call _act], ["CARTE", ["map", _id] call _act]];
            if (_tab isEqualTo "SENT" && {_web in ["ÉCHEC", "HORS LIGNE"]}) then { _btns pushBack ["RENVOYER", ["web", _id] call _act]; };
            _btns pushBack ["RETIRER", ["del", _id] call _act];
            _rows pushBack ["buttons", _btns];
        } forEach _sel;
    };
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
