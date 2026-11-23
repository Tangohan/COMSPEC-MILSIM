/*
    Onglet CKIN de l'app JTAC (fn_pageJtac) : la fiche de check-in CAS à compléter, sur le modèle de la fiche blanche
    « 9 LINE CLOSE AIR SUPPORT BRIEF ». Sections repliables, dans l'ordre de la fiche : 1 en-tête et check-in,
    2 situation et game plan, 3 les 9 lignes, 4 remarques, 5 lecture en phonie ; puis les actions (préremplir,
    brouillon, effacer, copier, verser au compte rendu), l'envoi au pilote et l'historique des check-ins de la partie.
    Rien n'est lu dans le jeu ici hors de la liste des aéronefs alliés (déjà celle de l'onglet 9/5-LINE) : le
    préremplissage se fait au clic (fn_jtacCkin « prefill »).
    Renvoie les lignes du formulaire (fn_formRender).
*/
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
private _grey = "#8a9a93";
private _amber = "#f2ab33";
private _green = "#5cc76b";
private _cardBg = [0.045, 0.058, 0.052, 1];
private _mono = { params ["_t", ["_c", ""], ["_sz", 1], ["_bold", false]]; format ["<t font='%1' size='%2'%3>%4</t>", ["EtelkaMonospacePro", "EtelkaMonospaceProBold"] select _bold, _sz, ["", format [" color='%1'", _c]] select (_c isNotEqualTo ""), _t] };
private _ck = { params ["_a", ["_v", ""]]; compile format ["[%1, %2] call comspec_atak_native_fnc_jtacCkin;", str _a, str _v] };
private _fields = ["fields"] call comspec_atak_native_fnc_jtacCkin;
private _h = ["state"] call comspec_atak_native_fnc_jtacCkin;
private _val = { params ["_hv", "_kv"]; private _tv = _hv getOrDefault [_kv, ""]; if !(_tv isEqualType "") then { _tv = str _tv; }; trim _tv };
private _def = { params ["_k"]; _fields select { (_x select 0) isEqualTo _k } param [0, ["", "", "", "", "", ""]] };
// Phrase de séquence du FAC, comme sur la fiche blanche.
private _say = { params ["_t"]; ["text", format ["<t size='0.72' color='%1'>FAC</t><br/>%2", _grey, [_t, _amber, 0.9, true] call _mono], [0.07, 0.06, 0.03, 1]] };
private _rows = [];
private _hist = missionNamespace getVariable ["COMSPEC_ATAK_JtacCkinHist", []];

// ---------------------------------------------------------------- Check-in de l'historique, en lecture
private _view = _s getOrDefault ["ckView", ""];
private _e = _hist select { (_x get "ref") isEqualTo _view } param [0, createHashMap];
if (_view isNotEqualTo "" && {(count _e) > 0}) exitWith {
    private _hv = _e get "vals";
    _rows pushBack ["strip", format ["%1  %2", ["CKIN", _amber, 1, true] call _mono, [[_hv, "ckAc"] call _val, "", 0.9, true] call _mono],
        format ["%1  %2", [_e get "at", _grey, 0.85] call _mono, [_e getOrDefault ["how", ""], _grey, 0.75] call _mono]];
    {
        _x params ["_k", "_lbl"];
        private _t = [_hv, _k] call _val;
        if (_t isNotEqualTo "") then { _rows pushBack ["line", "", _lbl, _t, "auto", "", "", "mono"]; };
    } forEach _fields;
    _rows pushBack ["buttons", [
        ["RETOUR", ["histBack"] call _ck],
        ["DUPLIQUER POUR UN NOUVEAU CAS", ["histDup", _view] call _ck, true, true, "La fiche en cours est remplacée par celle-ci"]
    ]];
    _rows pushBack ["buttons", [
        ["COPIER EN TEXTE", ["histCopy", _view] call _ck, false, true, "Fiche lue en phonie, ligne par ligne, dans le presse-papiers"],
        ["VERSER AU COMPTE RENDU", ["histReport", _view] call _ck, false, true, "Compte rendu libre prérempli, fiche jointe"]
    ]];
    _rows
};

// ---------------------------------------------------------------- Fiche à compléter
private _open = _s getOrDefault ["ckOpen", ["HDR"]];
private _fill = { params ["_grp"]; private _f = _fields select { (_x select 4) in _grp }; format ["%1/%2", { ([_h, _x select 0] call _val) isNotEqualTo "" } count _f, count _f] };
private _edit = {
    params ["_k", ["_num", ""]];
    ([_k] call _def) params ["", "_lbl", "", "", "", "_hlp"];
    ["line", _num, _lbl, [_h, _k] call _val, "edit", _k, _hlp, "mono"]
};
private _draftAt = _s getOrDefault ["ckDraftAt", ""];
_rows pushBack ["strip", format ["%1  %2", ["CKIN", _amber, 1, true] call _mono, ["CHECK-IN CAS · 9 LINE", "", 0.85, true] call _mono],
    [[format ["brouillon %1", _draftAt], "nouvelle fiche"] select (_draftAt isEqualTo ""), _grey, 0.75] call _mono];
// En-tête de la fiche, rempli au fil de la saisie.
private _blank = { params ["_t"]; [_t, "______"] select (_t isEqualTo "") };
_rows pushBack ["text", format ["<t size='0.72' color='%1'>FAC</t><br/>%2", _grey,
    [format ["« %1, this is %2, standing by for aircraft check-in »", [[_h, "ckAc"] call _val] call _blank, [[_h, "ckFac"] call _val] call _blank], "#ffffff", 0.9, true] call _mono], _cardBg];

// 1 · En-tête et check-in.
private _isOpen = "HDR" in _open;
_rows pushBack ["fold", "1 · En-tête et check-in", [["hdr", "ckin"]] call _fill, _isOpen, ["fold", "HDR"] call _ck];
if (_isOpen) then {
    // Appareil : un aéronef allié avec équipage (indicatif, type, position, armement, carburant repris), ou à la main.
    private _airs = ["airs", false] call comspec_atak_native_fnc_airBrief;
    private _sel = _s getOrDefault ["ckAir", ""];
    if ((count _airs) isEqualTo 0) then {
        _rows pushBack ["text", format ["<t size='0.85' color='%1'>Aucun aéronef allié en vol. Saisissez l'indicatif et le check-in annoncés par le pilote.</t>", _grey]];
    };
    {
        _x params ["_v", "_cs", "_pl", "_d"];
        private _id = _v call BIS_fnc_netId;
        private _on = _id isEqualTo _sel;
        _rows pushBack ["person", getText (configOf _v >> "picture"), format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t>  <t size='0.82' color='%3'>%4 · %5 NM</t><br/>%6",
            ["#c9d4cf", _green] select _on, _cs, _grey, getText (configOf _v >> "displayName"), (_d / 1852) toFixed 1,
            [[_pl joinString ", ", "équipage IA"] select ((count _pl) isEqualTo 0), "#c9d4cf", 0.72] call _mono],
            [[["PRENDRE", "PRIS"] select _on, ["pickAir", _id] call _ck, _on, true, "Indicatif, type, position, armement et carburant repris dans la fiche"]]];
    } forEach (_airs select [0, 6]);
    _rows pushBack (["ckAc"] call _edit);
    _rows pushBack (["ckFac"] call _edit);
    _rows pushBack ["section", "Check-in de l'appareil", "Annoncé par le pilote"];
    { _rows pushBack ([_x] call _edit); } forEach ["ckPilot", "ckAcft", "ckPos", "ckOrd", "ckTos", "ckAbort"];
    _rows pushBack ["segment", "", ["IRON MAN", "BLACKJACK", "WILDCAT", "THUNDER"] apply { [_x, ["set", ["ckAbort", _x]] call _ck, ([_h, "ckAbort"] call _val) isEqualTo _x, "Code d'annulation convenu avec le pilote"] }];
    _rows pushBack (["ckAdd"] call _edit);
};

// 2 · Situation et game plan.
_isOpen = "SIT" in _open;
_rows pushBack ["fold", "2 · Situation et game plan", [["sit"]] call _fill, _isOpen, ["fold", "SIT"] call _ck];
if (_isOpen) then {
    _rows pushBack ["text", format ["<t size='0.85' color='%1'>Donnez au pilote la situation amie, ennemie et le game plan.</t>", _grey]];
    _rows pushBack ["memo", "ckSit", ([("ckSit")] call _def) select 1, [_h, "ckSit"] call _val, 3];
};

// 3 · Les 9 lignes.
_isOpen = "NINE" in _open;
_rows pushBack ["fold", "3 · 9 lignes", [["nine"]] call _fill, _isOpen, ["fold", "NINE"] call _ck];
if (_isOpen) then {
    _rows pushBack (["« Stand by for 9 line, I am not a JTAC »"] call _say);
    _rows pushBack (["ck1", "1"] call _edit);
    _rows pushBack (["ck2", "2"] call _edit);
    private _off = [_h, "ck2o"] call _val;
    _rows pushBack ["lineseg", "2", "Décalage", ["GAUCHE", "DROITE"] apply { [_x, ["set", ["ck2o", _x]] call _ck, _off isEqualTo _x] }, "Offset gauche ou droite, nouvel appui pour l'enlever"];
    _rows pushBack (["ck3", "3"] call _edit);
    _rows pushBack (["ck4", "4"] call _edit);
    _rows pushBack (["ck5", "5"] call _edit);
    _rows pushBack (["ck6", "6"] call _edit);
    private _mk = [_h, "ck7"] call _val;
    _rows pushBack ["lineseg", "7", "Type de marquage", ["LASER", "IR", "WP", "ÉCLAIRANT", "FUMIGÈNE", "AUCUN"] apply { [_x, ["set", ["ck7", _x]] call _ck, _mk isEqualTo _x] }, "WP, éclairant, laser ou pointeur IR"];
    _rows pushBack (["ck7c", "7"] call _edit);
    _rows pushBack (["ck8", "8"] call _edit);
    _rows pushBack (["ck9", "9"] call _edit);
};

// 4 · Remarques.
_isOpen = "REM" in _open;
_rows pushBack ["fold", "4 · Remarques", [["rem"]] call _fill, _isOpen, ["fold", "REM"] call _ck];
if (_isOpen) then {
    _rows pushBack (["« Say when ready to copy remarks »"] call _say);
    _rows pushBack ["memo", "ckRem", ([("ckRem")] call _def) select 1, [_h, "ckRem"] call _val, 2];
    { _rows pushBack ([_x] call _edit); } forEach ["ckFah", "ckLtl", "ckThr", "ckGtl", "ckTot"];
    _rows pushBack ["switch", "PUSH ASAP", ([_h, "ckPush"] call _val) isNotEqualTo "", ["push"] call _ck, "Au lieu d'un TOT ou TTT, l'appareil attaque dès que possible"];
    _rows pushBack (["« Say when ready for amplifying information »"] call _say);
    _rows pushBack ["memo", "ckAmp", ([("ckAmp")] call _def) select 1, [_h, "ckAmp"] call _val, 2];
};

// 5 · Lecture en phonie : la fiche telle qu'on la lit au pilote.
_isOpen = "TXT" in _open;
_rows pushBack ["fold", "5 · Lecture en phonie", "ligne par ligne", _isOpen, ["fold", "TXT"] call _ck];
if (_isOpen) then {
    _rows pushBack ["text", ((["text", _h] call comspec_atak_native_fnc_jtacCkin) apply { [_x, "#e6ece9", 0.8] call _mono }) joinString "<br/>", _cardBg];
};

// Actions de la fiche.
_rows pushBack ["buttons", [
    ["PRÉ-REMPLIR DEPUIS LE JEU", ["prefill"] call _ck, false, true, "Complète les champs vides avec les indicatifs, l'appareil, la cible et les 9 lignes de l'app, les amis et le laser"],
    ["ENREGISTRER LE BROUILLON", ["draft"] call _ck, false, true, "Gardé dans votre profil, repris à la prochaine partie"],
    ["EFFACER", ["clear"] call _ck, "red", true, "Fiche vide (le brouillon du profil est effacé)"]
]];
_rows pushBack ["buttons", [
    ["COPIER EN TEXTE", ["copy"] call _ck, false, true, "Fiche lue en phonie, ligne par ligne, dans le presse-papiers"],
    ["VERSER AU COMPTE RENDU", ["report"] call _ck, false, true, "Compte rendu libre prérempli dans l'app Comptes rendus, fiche jointe (PDF sur le poste TOC)"]
]];

// Envoi : l'appareil pris dans la fiche, et les indicatifs saisis.
_rows pushBack ["section", "Envoyer au pilote", "La fiche arrive dans l'espace BRIEFS AIR de l'équipage ; son WILCO ou UNABLE revient ici"];
private _a = (_s getOrDefault ["ckAir", ""]) call BIS_fnc_objectFromNetId;
_rows pushBack ["line", "", "Appareil de la fiche", [["airCs", _a] call comspec_atak_native_fnc_airBrief, "aucun (PRENDRE dans la section 1)"] select (isNull _a), "auto", "", "", "mono"];
_rows pushBack ["line", "", "Autres destinataires", [_h, "ckTo"] call _val, "edit", "ckTo", "indicatifs séparés par des virgules", "mono"];
_rows pushBack ["big", [["ENVOYER AU PILOTE / À L'ÉQUIPAGE", ["send"] call _ck, "green", true, "Alerte et fiche en lecture dans le téléphone de l'équipage"]]];

// Historique des check-ins de la partie.
_isOpen = "HIST" in _open;
_rows pushBack ["fold", "Historique de la partie", format ["%1 check-in(s)", count _hist], _isOpen, ["fold", "HIST"] call _ck];
if (_isOpen) then {
    if ((count _hist) isEqualTo 0) then {
        _rows pushBack ["text", format ["<t size='0.85' color='%1'>Les check-ins envoyés ou versés au compte rendu pendant la partie arrivent ici.</t>", _grey]];
    };
    {
        private _r = _x get "ref";
        private _hv = _x get "vals";
        _rows pushBack ["notif", "\z\comspec_atak_native\addons\main\data\app_jtac.paa", [0.95, 0.67, 0.2, 1], format ["CKIN · %1", [[_hv, "ckAc"] call _val] call _blank],
            format ["%1 · %2", _x get "at", _x getOrDefault ["how", ""]], format ["%1 · %2", [[_hv, "ck6"] call _val] call _blank, [[_hv, "ck5"] call _val] call _blank],
            ["histOpen", _r] call _ck, ["histDel", _r] call _ck, "Ouvrir en lecture"];
    } forEach _hist;
};
_rows
