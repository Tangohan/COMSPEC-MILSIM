/*
    App « Contrôle ID » du téléphone ATAK (page IDCHECK, déclarée dans COMSPEC_ATAK_Apps du SSE).
      CONTRÔLE    document en cours : identité imprimée, étapes N0 visuel · N1 MRZ · N2 puce · N3 base, constats,
                  bandeau de verdict ; boutons de lecture (barre de progression ACE), Transmettre, Joindre à une FRM,
                  Photographier (photo du document jointe à la FRM), SALUTE et SPOTREP pré-remplis ;
                  ATAK 3.0.2 et plus : Partager (Bluetooth), NFC (dos à dos), Sur mon téléphone (QR, page /m/controle)
      HISTORIQUE  50 derniers contrôles du joueur (local) ; un clic rouvre la fiche
      BOLO        liste à surveiller diffusée par le commandement (Zeus « BOLO », « Désigner HVT ») : noms, alias,
                  papiers connus
    Lot 4 et 5 : N4 · comparaison du visage (biométrie, réglage), réponses du PNJ aux questions de légende,
    « Confirmer le PID » quand une HVT ou un recherché est identifié (chasse HVT de l'ATAK : FIX).
    Params (fn_pageRender de l'ATAK) : [page, rectangle]. Actions : comspec_sse_fnc_idAction.
*/
params [["_page", "IDCHECK"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if ((count _rect) isEqualTo 4) then { _bw = _rect select 2; _bh = _rect select 3; };
private _st = uiNamespace getVariable ["comspec_sse_idApp", createHashMap];
uiNamespace setVariable ["comspec_sse_idApp", _st];
private _tab = _st getOrDefault ["tab", "check"];
private _tr = { _this call comspec_sse_fnc_tr };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_arg", ""]]; compile format ["['%1', %2] call comspec_sse_fnc_idAction;", _a, str _arg] };
private _icon = "\z\comspec_sse\addons\identity\data\app_id.paa";
private _verdictInfo = {
    switch (_this) do {
        case "ras": { ["RAS : DOCUMENT CONFORME", "#34d399", [0.04, 0.16, 0.1, 1], [0.2, 0.83, 0.6, 1]] };
        case "doute": { ["DOUTE : À APPROFONDIR", "#f2ab33", [0.18, 0.12, 0.03, 1], [0.95, 0.67, 0.2, 1]] };
        case "expire": { ["DOCUMENT EXPIRÉ", "#f2ab33", [0.18, 0.12, 0.03, 1], [0.95, 0.67, 0.2, 1]] };
        case "inconnu": { ["NUMÉRO INCONNU : FAUX PROBABLE", "#ff5a5a", [0.22, 0.05, 0.05, 1], [1, 0.35, 0.35, 1]] };
        case "faux": { ["FAUX DOCUMENT", "#ff5a5a", [0.22, 0.05, 0.05, 1], [1, 0.35, 0.35, 1]] };
        case "vole": { ["DOCUMENT VOLÉ", "#ff5a5a", [0.22, 0.05, 0.05, 1], [1, 0.35, 0.35, 1]] };
        case "revoque": { ["DOCUMENT RÉVOQUÉ", "#ff5a5a", [0.22, 0.05, 0.05, 1], [1, 0.35, 0.35, 1]] };
        case "signale": { ["PERSONNE SOUS SURVEILLANCE", "#f2ab33", [0.18, 0.12, 0.03, 1], [0.95, 0.67, 0.2, 1]] };
        case "recherche": { ["PERSONNE RECHERCHÉE", "#ff5a5a", [0.3, 0.02, 0.02, 1], [1, 0.2, 0.2, 1]] };
        case "hvt": { ["HVT : CIBLE PRIORITAIRE", "#ff5a5a", [0.3, 0.02, 0.02, 1], [1, 0.2, 0.2, 1]] };
        default { [toUpper _this, "#8b98a7", [0.07, 0.09, 0.11, 1], [0.55, 0.6, 0.65, 1]] };
    }
};
private _rows = [];
private _cur = ["current"] call comspec_sse_fnc_idHistory;
private _case = if (isNil "comspec_sse_fnc_getCaseReference") then { "" } else { [] call comspec_sse_fnc_getCaseReference };
_rows pushBack ["strip",
    format ["<t font='EtelkaMonospaceProBold'>%1</t>", [[_case] call _esc, "SANS DOSSIER" call _tr] select (_case isEqualTo "")],
    format ["<t font='EtelkaMonospacePro'>%1 · %2</t>", mapGridPosition player, [dayTime, "HH:MM"] call BIS_fnc_timeToString],
    [0.071, 0.09, 0.114, 1]];
_rows pushBack ["segment", "", [
    ["CONTRÔLE" call _tr, ["tab", "check"] call _act, _tab isEqualTo "check"],
    [format ["%1 (%2)", "HISTORIQUE" call _tr, count (["list"] call comspec_sse_fnc_idHistory)], ["tab", "history"] call _act, _tab isEqualTo "history"],
    [format ["BOLO (%1)", count (["mine"] call comspec_sse_fnc_idHvt)], ["tab", "bolo"] call _act, _tab isEqualTo "bolo"]
]];
// BOLO et HVT aussi dans l'app Recherchés (liste déjà chargée).
if ((missionNamespace getVariable ["comspec_sse_idBolos", []]) isNotEqualTo []) then { ["wantedSync"] call comspec_sse_fnc_idHvt; };

if (_tab isEqualTo "bolo") exitWith {
    private _b = ["mine"] call comspec_sse_fnc_idHvt;
    if (_b isEqualTo []) then { _rows pushBack ["empty", _icon, "Aucun BOLO" call _tr, "Les avis à surveiller diffusés par le commandement s'affichent ici." call _tr]; };
    {
        _x params ["_ref", "_l", "_f", "_al", "_lvl", "_docs", "_notes", ["_kind", "BOLO"], ["_at", ""]];
        _rows pushBack ["notif", _icon, [[0.95, 0.67, 0.2, 1], [1, 0.2, 0.2, 1]] select (_lvl in ["recherche", "hvt"]),
            trim format ["%1 %2%3", toUpper _l, _f, ["", format [" « %1 »", _al]] select (_al isNotEqualTo "")], _at,
            format ["%1 · %2 · %3%4", _ref, _kind, (["sous surveillance", "recherché", "HVT"] select ((["surveillance", "recherche", "hvt"] find _lvl) max 0)) call _tr,
                ["", format [" · %1 %2", "papiers" call _tr, _docs joinString ", "]] select (_docs isNotEqualTo [])], {}, {}];
        if (_notes isNotEqualTo "") then { _rows pushBack ["text", format ["<t size='0.85' color='#8b98a7'>%1</t>", [_notes] call _esc]]; };
    } forEach _b;
    _rows pushBack ["text", format ["<t size='0.8' color='#8b98a7'>%1</t>", ("Un BOLO est reconnu à la base (N3 : nom, numéro) et au visage (N4)." call _tr) call _esc]];
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
};

if (_tab isEqualTo "history") then {
    private _h = +(["list"] call comspec_sse_fnc_idHistory);
    if (_h isEqualTo []) exitWith { _rows pushBack ["empty", _icon, "Aucun contrôle" call _tr, "Les contrôles faits pendant la mission s'affichent ici." call _tr]; };
    private _n = count _h;
    for "_i" from (_n - 1) to 0 step -1 do {
        private _r = _h select _i;
        private _d = _r getOrDefault ["doc", createHashMap];
        ((_r getOrDefault ["verdict", "ras"]) call _verdictInfo) params ["_vt", "", "", "_rgba"];
        // Carte « notif » : titre et texte échappés et traduits par formRender.
        _rows pushBack ["notif", _icon, _rgba,
            format ["%1 %2", toUpper (_d getOrDefault ["last", "?"]), _d getOrDefault ["first", ""]],
            _r getOrDefault ["clock", ""],
            format ["%1 · %2 · %3%4", _vt call _tr, _d getOrDefault ["country", ""], _d getOrDefault ["number", ""], ["", " · " + ("transmis" call _tr)] select (_r getOrDefault ["sent", false])],
            ["select", _i] call _act, {}];
    };
} else {
    if ((count _cur) isEqualTo 0) exitWith {
        _rows pushBack ["empty", _icon, "Aucun contrôle en cours" call _tr, "Menu ACE sur une personne : Contrôle d'identité, Demander les papiers, puis Contrôler l'identité." call _tr];
    };
    private _d = _cur get "doc";
    private _lv = _cur get "levels";
    private _typeTxt = (["Carte d'identité", "Passeport", "Permis de conduire"] select ((["id_card", "passport", "driving_license"] find (_d getOrDefault ["type", ""])) max 0)) call _tr;
    ((_cur get "verdict") call _verdictInfo) params ["_vt", "_vc", "_vbg"];
    _rows pushBack ["person", "\z\comspec_sse\addons\identity\data\silhouette_co.paa",
        format ["<t size='1.1' font='COMSPEC_BarlowBold'>%1 %2</t><br/><t size='0.8' color='#8b98a7'>%3 · %4 · %5</t>",
            [toUpper (_d getOrDefault ["last", ""])] call _esc, [_d getOrDefault ["first", ""]] call _esc, _typeTxt, _d getOrDefault ["country", ""], _d getOrDefault ["number", ""]],
        [["Voir" call _tr, ["view"] call _act]], [1, 1, 1, 1]];
    _rows pushBack ["info", "Né(e) le" call _tr, format ["%1 · %2", _d getOrDefault ["dob", ""], [_d getOrDefault ["pob", ""]] call _esc]];
    _rows pushBack ["info", "Expire le" call _tr, _d getOrDefault ["expires", ""]];
    if ((_cur getOrDefault ["subjName", ""]) isNotEqualTo "") then { _rows pushBack ["info", "Contrôlé" call _tr, [_cur get "subjName"] call _esc]; };
    if ((_cur getOrDefault ["photo", ""]) isNotEqualTo "") then { _rows pushBack ["info", "Photo du document" call _tr, format ["%1 · %2", _cur getOrDefault ["photoClock", ""], "jointe à la FRM" call _tr]]; };
    private _stateOf = {
        private _r = _lv getOrDefault [_this, createHashMap];
        if ((count _r) isEqualTo 0) exitWith { "todo" };
        switch (_r getOrDefault ["state", "ok"]) do { case "ok": { "done" }; case "doubt": { "now" }; case "na": { "abort" }; default { "hot" }; }
    };
    private _bio = missionNamespace getVariable ["comspec_sse_id_bio", true];
    private _steps = [["N0 VISUEL" call _tr, 0 call _stateOf], ["N1 MRZ" call _tr, 1 call _stateOf], ["N2 PUCE" call _tr, 2 call _stateOf], ["N3 BASE" call _tr, 3 call _stateOf]];
    if (_bio) then { _steps pushBack ["N4 VISAGE" call _tr, 4 call _stateOf]; };
    _rows pushBack ["steps", _steps];
    _rows pushBack ["banner", format ["<t color='%1'>%2</t>", _vc, _vt call _tr], _vbg];
    {
        _x params ["_lvl", "_name"];
        private _r = _lv getOrDefault [_lvl, createHashMap];
        if ((count _r) > 0) then {
            _rows pushBack ["section", _name call _tr, ""];
            { _rows pushBack ["text", format ["<t size='0.9'>%1</t>", [_x call _tr] call _esc]]; } forEach (_r getOrDefault ["lines", []]);
        };
    } forEach [[0, "N0 · Examen visuel"], [1, "N1 · Bande MRZ"], [2, "N2 · Puce NFC"], [3, "N3 · Base"], [4, "N4 · Visage (biométrie)"]];
    // Réponses de la personne aux questions de légende (menu ACE › Poser une question).
    private _talk = _cur getOrDefault ["talk", []];
    if (_talk isNotEqualTo []) then {
        _rows pushBack ["section", "Questions de légende" call _tr, ""];
        { _x params ["", "_t", ["_h", ""]]; _rows pushBack ["text", format ["<t size='0.9'>« %1 »%2</t>", [_t call _tr] call _esc, ["", format [" <t color='#f2ab33'>[%1]</t>", [_h call _tr] call _esc]] select (_h isNotEqualTo "")]]; } forEach _talk;
    };
    private _hasMrz = (_d getOrDefault ["mrz", []]) isNotEqualTo [];
    private _hasChip = _d getOrDefault ["chipLogo", false];
    _rows pushBack ["buttons", [
        ["Lire la MRZ (N1)" call _tr, ["level", 1] call _act, !(1 in _lv), _hasMrz],
        ["Lire la puce (N2)" call _tr, ["level", 2] call _act, !(2 in _lv) && {1 in _lv || {!_hasMrz}}, _hasChip],
        ["Interroger la base (N3)" call _tr, ["level", 3] call _act, !(3 in _lv)]
    ]];
    private _subjOk = ((_cur getOrDefault ["subj", objNull]) isEqualType objNull) && {!isNull (_cur getOrDefault ["subj", objNull])} && {(_cur get "subj") isNotEqualTo player};
    private _pidRow = [];
    if (_bio) then { _pidRow pushBack ["Comparer le visage (N4)" call _tr, ["level", 4] call _act, !(4 in _lv), _subjOk, "Photo du visage comparée à la base biométrique et à la photo du document (téléphone ou SEEK)."]; };
    if ((_cur get "verdict") in ["hvt", "recherche"] && {_subjOk}) then {
        _pidRow pushBack [((["Confirmer le PID", "PID confirmé"] select (_cur getOrDefault ["pid", false])) call _tr), ["pid"] call _act, true, !(_cur getOrDefault ["pid", false]),
            "Identification positive : tuyaux confirmés à votre groupe (chasse HVT), Zeus et Athena prévenus."];
    };
    if (_pidRow isNotEqualTo []) then { _rows pushBack ["buttons", _pidRow]; };
    private _dll = ["has"] call comspec_sse_fnc_idAthenaPack;
    _rows pushBack ["buttons", [
        [((["Transmettre à Athena", "Transmis"] select (_cur getOrDefault ["sent", false])) call _tr), ["transmit"] call _act, false, !(_cur getOrDefault ["sent", false]),
            ["Envoi au portail Athena (file d'attente hors liaison).", "Sans liaison Athena : mis en file d'attente."] select !_dll],
        ["Joindre à une FRM" call _tr, ["frm"] call _act]
    ]];
    _rows pushBack ["buttons", [
        ["Photographier" call _tr, ["photo"] call _act, false, true, "Photo du document jointe à la fiche FRM (pièces jointes de l'app FRS)."],
        ["SALUTE" call _tr, ["salute"] call _act, false, true, "Compte rendu SALUTE pré-rempli avec ce contrôle."],
        ["SPOTREP" call _tr, ["spotrep"] call _act, false, true, "Compte rendu SPOTREP pré-rempli avec ce contrôle."]
    ]];
    if ([3, 0, 2] call comspec_sse_fnc_idAtakAtLeast) then {
        _rows pushBack ["buttons", [
            ["Partager" call _tr, ["share"] call _act, false, true, "Fiche du contrôle envoyée en Bluetooth à un équipier appairé."],
            ["NFC" call _tr, ["nfc"] call _act, false, true, "Fiche du contrôle tendue au téléphone le plus proche (2 m), sans appairage."],
            ["Sur mon téléphone" call _tr, ["mobile"] call _act, false, true, "QR code : vos contrôles de la partie sur votre vrai téléphone."]
        ]];
    };
    _rows pushBack ["text", format ["<t size='0.8' color='#8b98a7'>%1</t>", ("SIMULATION : documents et pays fictifs." call _tr) call _esc]];
};

[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
