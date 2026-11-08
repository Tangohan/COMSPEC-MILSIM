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
(_display displayCtrl 93011) ctrlSetText ((_titleText) call comspec_sse_fnc_tr);

(_display displayCtrl 93017) ctrlSetText (((if (_mode == "feuille") then {
    _chrome getOrDefault ["subtitle_feuille", "Feuille de consultation — lecture détaillée"]
} else {
    if (_isDocs) then {
        _chrome getOrDefault ["subtitle_docs", "Pièces saisies sur le terrain"]
    } else {
        _chrome getOrDefault ["subtitle_dossier", "Compte rendu d’exploitation"]
    }
})) call comspec_sse_fnc_tr);
(_display displayCtrl 93016) ctrlSetText (((_chrome getOrDefault ["banner", "DIFFUSION RESTREINTE — EXPLOITATION TERRAIN"])) call comspec_sse_fnc_tr);

(_display displayCtrl 93013) ctrlSetText (((_chrome getOrDefault ["btn_consult", "FEUILLE"])) call comspec_sse_fnc_tr);
(_display displayCtrl 93014) ctrlSetText (((_chrome getOrDefault ["btn_transmit", "TRANSMETTRE"])) call comspec_sse_fnc_tr);
(_display displayCtrl 93015) ctrlSetText (((_chrome getOrDefault ["btn_close", "FERMER"])) call comspec_sse_fnc_tr);

// Écran selon la nature de la preuve : un support numérique ne s'ouvre pas comme un dossier papier ; un sac, comme un contenu.
private _natureRaw = toUpper (_fog getOrDefault ["type", ""]);
// Support numérique : écran sombre « exploitation numérique », jamais la feuille de papier. Tout ce qui passe par
// fn_openDigitalPanel (téléphone, ordinateur, clé USB, disque, carte mémoire, tablette) est marqué « screen » ; la liste des
// types couvre les appels directs. Avant, SMARTPHONE, HARDDRIVE ou DEVICE (types réels du générateur) ouvraient la feuille beige.
private _isDigital = ((_fog getOrDefault ["screen", ""]) isEqualTo "digital") || {_natureRaw in ["PHONE", "SMARTPHONE", "FEATUREPHONE", "COMPUTER", "LAPTOP", "DESKTOP", "MEDIA", "TABLET", "DRIVE", "HARDDRIVE", "HDD", "SSD", "USB", "SDCARD", "MEMORYCARD", "DEVICE", "DEV"]};
private _isContent = _natureRaw in ["CONTAINER", "BAG", "LUGGAGE", "VALUABLES"];
private _ink = "#1f1a14";
private _muted = "#5a4e3c";
private _accent = "#6b3a2a";
private _rule = "#a89878";
if (_isDigital) then {
    _ink = "#e6efe9"; _muted = "#9fb0a8"; _accent = "#5cc76b"; _rule = "#22302a";
    (_display displayCtrl 93019) ctrlSetBackgroundColor [0.039, 0.059, 0.051, 1];
    (_display displayCtrl 93022) ctrlSetBackgroundColor [0.055, 0.082, 0.071, 1];
    (_display displayCtrl 93023) ctrlSetBackgroundColor [0.055, 0.082, 0.071, 1];
    { (_display displayCtrl _x) ctrlShow false; } forEach [93024, 93025, 93026];
    (_display displayCtrl 93021) ctrlSetBackgroundColor [0.09, 0.157, 0.122, 1];
    (_display displayCtrl 93011) ctrlSetTextColor [0.914, 0.941, 0.925, 1];
    (_display displayCtrl 93017) ctrlSetTextColor [0.624, 0.69, 0.659, 1];
    (_display displayCtrl 93011) ctrlSetText ("SUPPORT NUMÉRIQUE" call comspec_sse_fnc_tr);
    (_display displayCtrl 93016) ctrlSetText ("EXPLOITATION NUMÉRIQUE" call comspec_sse_fnc_tr);
    (_display displayCtrl 93017) ctrlSetText ((["Fichiers et traces trouvés", "Lecture détaillée du support"] select (_mode == "feuille")) call comspec_sse_fnc_tr);
    (_display displayCtrl 93013) ctrlSetText ("DÉTAIL" call comspec_sse_fnc_tr);
};
if (_isContent) then {
    (_display displayCtrl 93011) ctrlSetText ("CONTENU DE L'OBJET" call comspec_sse_fnc_tr);
    (_display displayCtrl 93017) ctrlSetText ("Ce qui a été trouvé à l'intérieur" call comspec_sse_fnc_tr);
    (_display displayCtrl 93013) ctrlSetText ("DÉTAIL" call comspec_sse_fnc_tr);
};
// Texte humain à l'écran seulement : les rapports et l'API gardent leurs codes.
private _human = {
    params ["_l"];
    { _l = [_l, _x select 0, (_x select 1) call comspec_sse_fnc_tr] call CBA_fnc_replace; } forEach [
        ["[OBSERVED] ", "Observé : "], ["[EXTRACTED] ", "Extrait : "], ["[PROBABLE] ", "Probable : "], ["[HYPOTHESIS] ", "Hypothèse : "],
        ["EXPLOIT_NOW", "à exploiter maintenant"], ["COLLECT", "à collecter"], ["DOCUMENT_ONLY", "à consigner"], ["LOW_VALUE", "peu d'intérêt"], ["UNKNOWN", "non évalué"]
    ];
    private _vc = _l regexFind ["\\(V(\\d+) C(\\d+)\\)"];
    if (_vc isNotEqualTo []) then {
        private _m = _vc select 0;
        private _word = { private _n = parseNumber _this; (["faible", "moyen", "élevé"] select ((floor (_n / 34)) min 2)) call comspec_sse_fnc_tr };
        _l = (_l select [0, (_m select 0) select 1]) + format ["(%1 %2, %3 %4)", "intérêt" call comspec_sse_fnc_tr, ((_m select 1) select 0) call _word, "confiance" call comspec_sse_fnc_tr, ((_m select 2) select 0) call _word];
    };
    _l regexReplace ["\\(valeur (\\d+)\\)", "(intérêt $1 %)"]
};

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
            _muted, _ink, ((createHashMapFromArray [["PHONE", "Téléphone"], ["SMARTPHONE", "Téléphone"], ["FEATUREPHONE", "Téléphone"], ["LAPTOP", "Ordinateur portable"], ["USB", "Clé USB"], ["SDCARD", "Carte mémoire"], ["HARDDRIVE", "Disque dur"], ["DEVICE", "Appareil numérique"], ["COMPUTER", "Ordinateur"], ["MEDIA", "Support de stockage (clé USB, disque, carte mémoire)"], ["TABLET", "Tablette"], ["RADIO", "Radio"], ["DOCUMENT", "Document"], ["WEAPON", "Arme"], ["CONTAINER", "Contenant"], ["VEHICLE", "Véhicule"], ["BUILDING", "Bâtiment"], ["OBJECT", "Objet"], ["PERSON", "Personne"]]) getOrDefault [toUpper _type, _type]) call comspec_sse_fnc_tr
        ];
    };
    _html = _html + format [
        "<t color='%1' size='0.8'>Identification</t><br/><t color='%2' size='0.9'>%3</t><br/><br/>",
        _muted, _ink, [_uid, "Non identifié" call comspec_sse_fnc_tr] select (_uid isEqualTo "?")
    ];
    if (_isDigital) then {
        // Le relevé le dit (fn_revealDigitalFog) : « chiffré », sans « déchiffré ».
        private _low = toLower (_lines joinString " ");
        private _enc = (_low find "chiffr") >= 0 && {(_low find "déchiffr") < 0};
        _html = _html + format ["<t color='%1' size='0.8'>État</t><br/><t color='%2' size='0.95'>%3</t><br/><br/>", _muted, ["#e6efe9", "#f2c94c"] select _enc,
            (["Lisible", "Chiffré : à déchiffrer"] select _enc) call comspec_sse_fnc_tr];
    };
    if ((count _lines) > 0) then {
        _html = _html + format ["<t color='%1' size='0.8'>%2</t><br/>", _muted, ((["Extraits", "Fichiers et extraits"] select _isDigital) call comspec_sse_fnc_tr)];
        {
            private _line = if (_x isEqualType "") then { _x } else { str _x };
            // Valeur inconnue (« Véhicule : ? ») : rien à dire, rien à afficher.
            if ((toLower _line) find "documents sse" < 0 && {(_line select [(count _line) - 3]) isNotEqualTo " : ?"} && {(_line select [(count _line) - 2]) isNotEqualTo ":?"}) then {
                _html = _html + format ["<t color='%1' size='0.85'>• %2</t><br/>", _ink, [_line] call _human];
            };
        } forEach _lines;
        if (_isDigital && {(missionNamespace getVariable ["comspec_sse_lastResultEntity", objNull]) getVariable ["comspec_sse_domex_enabled", false]}) then {
            _html = _html + format ["<br/><t color='%1' size='0.85'>%2</t><br/>", _accent, "Exploitation complète : Terminal SSE, onglet DOMEX." call comspec_sse_fnc_tr];
        };
    };
};

(_display displayCtrl 93012) ctrlSetStructuredText parseText ((_html) call comspec_sse_fnc_tr);

private _qLabel = if (_ql != "") then { _ql } else {
    if (_q >= 80) then {"Bonne"} else { if (_q >= 55) then {"Correcte"} else {"Partielle"} };
};
private _qPrefix = _chrome getOrDefault ["quality_prefix", "Qualité d’exploitation"];
private _footer = _chrome getOrDefault ["footer", "Ne constitue pas une preuve judiciaire — usage RP / renseignement uniquement."];
(_display displayCtrl 93018) ctrlSetStructuredText parseText ((format [
    "<t color='%1' size='0.72' align='left'>%2 : %3 %% — %4</t><br/>" +
    "<t color='%1' size='0.68' align='left'>%5</t>",
    _muted, _qPrefix, _q, _qLabel, _footer
]) call comspec_sse_fnc_tr);

true
