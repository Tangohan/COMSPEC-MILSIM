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

private _title = _fog getOrDefault ["title", "Exploitation SSE"];
private _uid = _fog getOrDefault ["uid", "?"];
private _q = _fog getOrDefault ["quality", 0];
private _ql = _fog getOrDefault ["qualityLabel", ""];
private _level = toLower (_fog getOrDefault ["level", _fog getOrDefault ["kind", ""]]);
private _docs = _fog getOrDefault ["docs", []];
private _lines = _fog getOrDefault ["lines", []];

private _isDocs = (_level find "doc") >= 0 || {(count _docs) > 0};

(_display displayCtrl 93011) ctrlSetText (if (_isDocs) then {"DOSSIER DOCUMENTAIRE"} else {toUpper _title});
(_display displayCtrl 93017) ctrlSetText (if (_mode == "feuille") then {
    "Feuille de consultation — lecture détaillée"
} else {
    if (_isDocs) then {"Pièces saisies sur le terrain"} else {"Compte rendu d’exploitation"}
});
(_display displayCtrl 93016) ctrlSetText "DIFFUSION RESTREINTE — EXPLOITATION TERRAIN";

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
            // Éviter de réafficher le titre générique en tête de liste.
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
(_display displayCtrl 93018) ctrlSetStructuredText parseText format [
    "<t color='%1' size='0.72' align='left'>Qualité d’exploitation : %2 %% — %3</t><br/>" +
    "<t color='%1' size='0.68' align='left'>Ne constitue pas une preuve judiciaire — usage RP / renseignement uniquement.</t>",
    _muted, _q, _qLabel
];

true
