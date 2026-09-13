/*
    Remplit la visionneuse dossier (mode dossier | feuille).
    [_fog, _mode] call comspec_sse_fnc_fillResultDialog
*/
params [
    ["_fog", createHashMap, [createHashMap]],
    ["_mode", "dossier", [""]]
];

private _display = uiNamespace getVariable ["COMSPEC_SSE_ResultDisplay", displayNull];
if (isNull _display) then { _display = findDisplay 93010; };
if (isNull _display) exitWith { false };

private _chrome = if (!isNil "comspec_sse_fnc_getDocumentChrome") then {
    ["get"] call comspec_sse_fnc_getDocumentChrome
} else {
    createHashMapFromArray [
        ["paper_style", "clean"],
        ["banner", "DIFFUSION RESTREINTE — EXPLOITATION TERRAIN"],
        ["title_person", "DOSSIER SSE"],
        ["title_docs", "DOSSIER DOCUMENTAIRE"],
        ["subtitle_dossier", "Compte rendu d’exploitation"],
        ["subtitle_feuille", "Feuille de consultation — lecture détaillée"],
        ["subtitle_docs", "Pièces saisies sur le terrain"],
        ["footer", "Ne constitue pas une preuve judiciaire — usage RP / renseignement uniquement."],
        ["quality_prefix", "Qualité d’exploitation"],
        ["btn_consult", "FEUILLE"],
        ["btn_transmit", "TRANSMETTRE"],
        ["btn_close", "FERMER"]
    ]
};
if (!isNil "comspec_sse_fnc_applyPaperStyle") then {
    [_display, _chrome getOrDefault ["paper_style", "clean"]] call comspec_sse_fnc_applyPaperStyle;
};

private _title = _fog getOrDefault ["title", "Exploitation SSE"];
private _uid = _fog getOrDefault ["uid", "?"];
private _q = _fog getOrDefault ["quality", 0];
private _ql = _fog getOrDefault ["qualityLabel", ""];
private _level = toLower (_fog getOrDefault ["level", _fog getOrDefault ["kind", ""]]);
private _docs = _fog getOrDefault ["docs", []];
private _lines = _fog getOrDefault ["lines", []];

private _isDocs = (_level find "doc") >= 0 || {(count _docs) > 0};

private _titleText = if (_isDocs) then {
    _chrome getOrDefault ["title_docs", "DOSSIER DOCUMENTAIRE"]
} else {
    private _tp = _chrome getOrDefault ["title_person", "DOSSIER SSE"];
    if ((toUpper _title) find "SSE" >= 0 || {_title isEqualTo "Exploitation SSE"}) then {
        _tp
    } else {
        toUpper _title
    };
};
(_display displayCtrl 93011) ctrlSetText _titleText;

(_display displayCtrl 93017) ctrlSetText (if (_mode == "feuille") then {
    _chrome getOrDefault ["subtitle_feuille", "Feuille de consultation — lecture détaillée"]
} else {
    if (_isDocs) then {
        _chrome getOrDefault ["subtitle_docs", "Pièces saisies sur le terrain"]
    } else {
        _chrome getOrDefault ["subtitle_dossier", "Compte rendu d’exploitation"]
    }
});
(_display displayCtrl 93016) ctrlSetText (_chrome getOrDefault ["banner", "DIFFUSION RESTREINTE — EXPLOITATION TERRAIN"]);

(_display displayCtrl 93013) ctrlSetText (_chrome getOrDefault ["btn_consult", "FEUILLE"]);
(_display displayCtrl 93014) ctrlSetText (_chrome getOrDefault ["btn_transmit", "TRANSMETTRE"]);
(_display displayCtrl 93015) ctrlSetText (_chrome getOrDefault ["btn_close", "FERMER"]);

private _ink = "#1f1a14";
private _muted = "#5a4e3c";
private _accent = "#6b3a2a";
private _rule = "#a89878";

private _html = "";

if (_isDocs && {(count _docs) > 0}) then {
    private _n = count _docs;
    _html = _html + format [
        "<t color='%1' size='0.85'>Réf. dossier</t><t color='%2' size='0.85'>  %3</t><br/>" +
        "<t color='%1' size='0.85'>Pièces</t><t color='%2' size='0.85'>  %4 document(s)</t><br/><br/>",
        _muted, _ink, _uid, _n
    ];

    {
        private _i = _forEachIndex + 1;
        if (_x isEqualType createHashMap) then {
            private _dt = _x getOrDefault ["title", format ["Pièce %1", _i]];
            private _sum = _x getOrDefault ["summary", ""];
            private _grid = _x getOrDefault ["grid", ""];
            private _cw = _x getOrDefault ["codeword", ""];
            private _duid = _x getOrDefault ["uid", ""];

            _html = _html + format [
                "<t color='%1' size='0.7'>────────────────────────────────</t><br/>" +
                "<t color='%2' size='0.95' font='PuristaMedium'>PIÈCE %3 — %4</t><br/>",
                _rule, _accent, _i, _dt
            ];
            if (_duid != "" && {(_duid find "e+") < 0} && {(_duid find "e-") < 0}) then {
                _html = _html + format ["<t color='%1' size='0.75'>%2</t><br/>", _muted, _duid];
            };
            if (_mode == "feuille" || {_sum != "" && {_q >= 55}}) then {
                if (_sum != "") then {
                    _html = _html + format ["<t color='%1' size='0.88'>%2</t><br/>", _ink, _sum];
                };
            };
            if (_grid != "" && {_q >= 70 || {_mode == "feuille"}}) then {
                _html = _html + format ["<t color='%1' size='0.8'>Grille</t><t color='%2' size='0.8'>  %3</t><br/>", _muted, _ink, _grid];
            };
            if (_cw != "" && {_q >= 80 || {_mode == "feuille"}}) then {
                _html = _html + format ["<t color='%1' size='0.8'>Mot de code</t><t color='%2' size='0.8'>  %3</t><br/>", _muted, _ink, _cw];
            };
            _html = _html + "<br/>";
        };
    } forEach _docs;
} else {
    private _type = _fog getOrDefault ["type", ""];
    if (_type != "") then {
        _html = _html + format [
            "<t color='%1' size='0.8'>Nature</t><br/><t color='%2' size='0.95'>%3</t><br/><br/>",
            _muted, _ink, _type
        ];
    };
    _html = _html + format [
        "<t color='%1' size='0.8'>Identification</t><br/><t color='%2' size='0.9'>%3</t><br/><br/>",
        _muted, _ink, _uid
    ];
    if ((count _lines) > 0) then {
        _html = _html + format ["<t color='%1' size='0.8'>Extraits</t><br/>", _muted];
        {
            private _line = if (_x isEqualType "") then { _x } else { str _x };
            if ((toLower _line) find "documents sse" < 0) then {
                _html = _html + format ["<t color='%1' size='0.85'>• %2</t><br/>", _ink, _line];
            };
        } forEach _lines;
    };
};

(_display displayCtrl 93012) ctrlSetStructuredText parseText _html;

private _qLabel = if (_ql != "") then { _ql } else {
    if (_q >= 80) then {"Bonne"} else { if (_q >= 55) then {"Correcte"} else {"Partielle"} };
};
private _qPrefix = _chrome getOrDefault ["quality_prefix", "Qualité d’exploitation"];
private _footer = _chrome getOrDefault ["footer", "Ne constitue pas une preuve judiciaire — usage RP / renseignement uniquement."];
(_display displayCtrl 93018) ctrlSetStructuredText parseText format [
    "<t color='%1' size='0.72' align='left'>%2 : %3 %% — %4</t><br/>" +
    "<t color='%1' size='0.68' align='left'>%5</t>",
    _muted, _qPrefix, _q, _qLabel, _footer
];

true
