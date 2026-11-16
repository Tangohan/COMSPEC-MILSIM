/*
    CKIN : fiche de check-in CAS de l'app JTAC (« 9 LINE CLOSE AIR SUPPORT BRIEF », doctrine publique JP 3-09.3), à
    compléter au check-in de l'appareil puis à lire au pilote, lui envoyer ou verser au compte rendu.
    Fiche : en-tête (indicatif de l'appareil, indicatif du FAC), check-in (7 champs), situation et game plan, 9 lignes
    (dont décalage gauche / droite, type de marquage et code laser), remarques (rester au-dessus / au-dessous, zones
    interdites, FAH, ligne laser-cible, menaces, ligne canon-cible active, TOT / TTT ou PUSH ASAP), informations
    complémentaires. Rien n'est calculé par image : le préremplissage ne lit le jeu qu'au clic.
    État : uiNamespace COMSPEC_ATAK_Jtac « ckin » (valeurs), « ckOpen » (sections dépliées), « ckAir » (appareil),
           « ckView » (check-in de l'historique ouvert en lecture) ;
           brouillon : profileNamespace COMSPEC_ATAK_JtacCkinDraft [[clé, valeur]...], écrit par fn_saveProfile (différé) ;
           historique de la partie : missionNamespace COMSPEC_ATAK_JtacCkinHist (le plus récent d'abord, 20 au plus).
    Envoi : fn_airBrief « send » au format "C" (espace BRIEFS AIR du pilote, alerte, accusés REÇU, LU, WILCO, UNABLE) ;
    compte rendu : fn_reportAction « prefill » du compte rendu libre (OTHER), fiche jointe en données structurées
    (form = cas_ckin, ckin_<clé>) : le poste TOC d'Athena en tire la fiche imprimable (PDF).
    Params : [action, argument]
      "fields" → [[clé, libellé, clé Athena, libellé radio, genre, aide]...]   "labels" → libellés lus par le pilote
      "state" → valeurs (brouillon du profil au premier appel)   "save" : garde la saisie affichée
      "text", valeurs → fiche en texte « lu en phonie », ligne par ligne    "sd", valeurs → données structurées
      "fold", section | "pickAir", netId | "set", [clé, valeur] | "push" | "prefill" | "draft" | "clear" | "copy"
      "send" | "report" | "histOpen", ref | "histBack" | "histDup", ref | "histDel", ref | "histCopy", ref | "histReport", ref
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Jtac", _s];
private _draftKey = "COMSPEC_ATAK_JtacCkinDraft";
private _histKey = "COMSPEC_ATAK_JtacCkinHist";
// Champs de la fiche, dans l'ordre de la fiche blanche : clé, libellé, clé Athena (ckin_…), libellé radio, genre, aide.
private _fields = [
    ["ckAc", "Indicatif de l'appareil", "ac", "AIRCRAFT CALLSIGN", "hdr", "TIGER 6"],
    ["ckFac", "Indicatif FAC", "fac", "FAC CALLSIGN", "hdr", "SABRE 1"],
    ["ckPilot", "Indicatif pilote / n° de mission", "pilot", "PILOT CALLSIGN / MISSION #", "ckin", "TIGER 6 // 9988"],
    ["ckAcft", "Nombre et type d'appareils", "acft", "NUMBER AND TYPE OF AIRCRAFT", "ckin", "1 × A-10C"],
    ["ckPos", "Position et altitude", "pos", "POSITION AND ALTITUDE", "ckin", "grille, altitude"],
    ["ckOrd", "Armement", "ord", "ORDNANCE", "ckin", "GBU-12, GAU-8"],
    ["ckTos", "Temps sur zone", "tos", "TIME ON STATION", "ckin", "45 min"],
    ["ckAbort", "Code d'annulation", "abort", "ABORT CODE", "ckin", "mot convenu"],
    ["ckAdd", "Remarques complémentaires", "add", "ADDITIONAL REMARKS", "ckin", ""],
    ["ckSit", "Situation amie, ennemie et game plan", "sit", "FRIENDLY, ENEMY SITUATION AND GAME PLAN", "sit", "amis, ennemi, type de contrôle, méthode"],
    ["ck1", "IP / BP", "l1", "IP / BP", "nine", "point initial (voilure fixe) ou de combat (hélico)"],
    ["ck2", "Cap", "l2", "HEADING", "nine", "degrés magnétiques, IP / BP vers cible"],
    ["ck2o", "Décalage", "l2o", "OFFSET", "nine", "gauche ou droite"],
    ["ck3", "Distance", "l3", "DISTANCE", "nine", "IP vers cible en NM (voilure fixe), BP en m (hélico)"],
    ["ck4", "Altitude de la cible", "l4", "TARGET ELEVATION", "nine", "pieds MSL (mètres × 3,3)"],
    ["ck5", "Description de la cible", "l5", "TARGET DESCRIPTION", "nine", "nombre, nature, protection"],
    ["ck6", "Position de la cible", "l6", "TARGET LOCATION", "nine", "grille"],
    ["ck7", "Type de marquage", "l7", "TYPE MARK", "nine", "laser, IR, WP, éclairant"],
    ["ck7c", "Code laser", "l7c", "LASER CODE", "nine", "1688"],
    ["ck8", "Amis", "l8", "FRIENDLIES", "nine", "direction et distance depuis la cible, en m"],
    ["ck9", "Dégagement", "l9", "EGRESS", "nine", "direction et point de sortie"],
    ["ckRem", "Remarques, rester au-dessus ou au-dessous, zones interdites", "rem", "REMARKS (STAY ABOVE / BELOW, NO FLY AREAS)", "rem", ""],
    ["ckFah", "Axe final d'attaque", "fah", "FINAL ATTACK HEADING", "rem", "degrés magnétiques"],
    ["ckLtl", "Ligne laser-cible", "ltl", "LASER TARGET LINE", "rem", "degrés magnétiques"],
    ["ckThr", "Menaces", "thr", "THREATS", "rem", "nature, direction et distance depuis la cible, neutralisation"],
    ["ckGtl", "Ligne canon-cible active", "gtl", "ACTIVE GUN TARGET LINE", "rem", "appuis feux en cours"],
    ["ckTot", "TOT / TTT", "tot", "TOT / TTT", "rem", "heure ou délai sur la cible"],
    ["ckPush", "PUSH ASAP", "push", "PUSH ASAP", "rem", ""],
    ["ckAmp", "Informations complémentaires (talk-on)", "amp", "AMPLIFYING INFORMATION", "rem", "guidage vers la cible, du général au détail"]
];
// Champs à saisie (lus dans le formulaire affiché) ; décalage, marquage et PUSH ASAP sont des choix.
private _textKeys = (_fields apply { _x select 0 }) - ["ck2o", "ck7", "ckPush"];
_textKeys pushBack "ckTo";
private _render = { [{
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "JTAC") then { ["JTAC"] call comspec_atak_native_fnc_pageRender; };
    (uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap]) set ["ckClearing", false];
}] call comspec_atak_native_fnc_nextFrame; };
private _state = {
    private _v = _s getOrDefault ["ckin", nil];
    if (isNil "_v") then {
        _v = createHashMap;
        private _d = profileNamespace getVariable [_draftKey, []];
        if (_d isEqualType []) then { { if (_x isEqualType [] && {(count _x) isEqualTo 2}) then { _v set [_x select 0, _x select 1]; }; } forEach _d; };
        _s set ["ckin", _v];
        _s set ["ckDraftAt", ["", profileNamespace getVariable ["COMSPEC_ATAK_JtacCkinDraftAt", ""]] select ((count _v) > 0)];
    };
    _v
};
private _val = { params ["_hv", "_kv"]; private _tv = _hv getOrDefault [_kv, ""]; if !(_tv isEqualType "") then { _tv = str _tv; }; trim _tv };
// Texte « lu en phonie » : la fiche dans l'ordre de la fiche blanche, phrases de séquence du FAC comprises.
private _text = {
    params ["_h"];
    private _o = [];
    private _v = { params ["_k"]; private _t = [_h, _k] call _val; [_t, "____"] select (_t isEqualTo "") };
    private _opt = { params ["_k"]; [_h, _k] call _val };
    _o pushBack "9 LINE CLOSE AIR SUPPORT BRIEF";
    _o pushBack format ["""%1, THIS IS %2, STANDING BY FOR AIRCRAFT CHECK-IN""", toUpper (["ckAc"] call _v), toUpper (["ckFac"] call _v)];
    { _x params ["_k", "", "", "_r", "_g"]; if (_g isEqualTo "ckin") then { _o pushBack format ["%1: %2", _r, [_k] call _v]; }; } forEach _fields;
    private _sit = ["ckSit"] call _opt;
    if (_sit isNotEqualTo "") then { _o pushBack format ["SITUATION / GAME PLAN: %1", _sit]; };
    _o pushBack """STAND BY FOR 9 LINE, I AM NOT A JTAC""";
    private _off = ["ck2o"] call _opt;
    _o pushBack format ["1. IP / BP: %1", ["ck1"] call _v];
    _o pushBack format ["2. HEADING: %1%2", ["ck2"] call _v, ["", format [", OFFSET %1", [_off, "LEFT", "RIGHT"] select ((["GAUCHE", "DROITE"] find _off) + 1)]] select (_off isNotEqualTo "")];
    _o pushBack format ["3. DISTANCE: %1", ["ck3"] call _v];
    _o pushBack format ["4. TARGET ELEVATION: %1", ["ck4"] call _v];
    _o pushBack format ["5. TARGET DESCRIPTION: %1", ["ck5"] call _v];
    _o pushBack format ["6. TARGET LOCATION: %1", ["ck6"] call _v];
    private _code = ["ck7c"] call _opt;
    _o pushBack format ["7. TYPE MARK: %1%2", ["ck7"] call _v, ["", format [", LASER CODE %1", _code]] select (_code isNotEqualTo "")];
    _o pushBack format ["8. FRIENDLIES: %1", ["ck8"] call _v];
    _o pushBack format ["9. EGRESS: %1", ["ck9"] call _v];
    _o pushBack """SAY WHEN READY TO COPY REMARKS""";
    {
        _x params ["_k", "_r"];
        private _t = [_k] call _opt;
        if (_t isNotEqualTo "") then { _o pushBack format ["%1: %2", _r, _t]; };
    } forEach [["ckRem", "REMARKS"], ["ckFah", "FINAL ATTACK HEADING"], ["ckLtl", "LASER TARGET LINE"], ["ckThr", "THREATS"], ["ckGtl", "ACTIVE GUN TARGET LINE"], ["ckTot", "TOT / TTT"]];
    if ((["ckPush"] call _opt) isNotEqualTo "") then { _o pushBack "PUSH ASAP"; };
    _o pushBack """SAY WHEN READY FOR AMPLIFYING INFORMATION""";
    private _amp = ["ckAmp"] call _opt;
    if (_amp isNotEqualTo "") then { _o pushBack _amp; };
    _o
};
// Données structurées du compte rendu (Athena, fiche imprimable) : form = cas_ckin, une clé ckin_<clé> par champ rempli.
private _sd = {
    params ["_h"];
    private _o = createHashMapFromArray [["form", "cas_ckin"]];
    {
        _x params ["_k", "", "_ak"];
        private _t = [_h, _k] call _val;
        if (_t isNotEqualTo "") then { _o set ["ckin_" + _ak, (((_t splitString "|") joinString "/") splitString toString [13, 10]) joinString " / "]; };
    } forEach _fields;
    _o
};
private _save = {
    if (_s getOrDefault ["ckClearing", false]) exitWith {};
    if ((_s getOrDefault ["ckView", ""]) isNotEqualTo "") exitWith {};
    private _h = call _state;
    { private _t = [_x, "§"] call comspec_atak_native_fnc_formValue; if (_t isNotEqualTo "§") then { _h set [_x, _t]; }; } forEach _textKeys;
};
private _keepDraft = {
    private _h = call _state;
    private _at = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
    profileNamespace setVariable [_draftKey, (keys _h) apply { [_x, _h get _x] }];
    profileNamespace setVariable ["COMSPEC_ATAK_JtacCkinDraftAt", _at];
    _s set ["ckDraftAt", _at];
    // Écriture regroupée à un moment calme (hors tir, téléphone rangé) : aucun gel en plein combat.
    [] call comspec_atak_native_fnc_saveProfile;
};
private _hist = { missionNamespace getVariable [_histKey, []] };
private _histGet = { params ["_ref"]; (call _hist) select { (_x get "ref") isEqualTo _ref } param [0, createHashMap] };
private _histAdd = {
    params ["_h", "_how"];
    private _l = call _hist;
    private _e = createHashMapFromArray [["ref", format ["CK-%1", round (diag_tickTime * 1000)]], ["at", [dayTime, "HH:MM"] call BIS_fnc_timeToString],
        ["ac", [_h, "ckAc"] call _val], ["vals", +_h], ["how", _how]];
    missionNamespace setVariable [_histKey, ([_e] + _l) select [0, 20]];
    _e
};
// Copie : presse-papiers d'Arma, puis celui de Windows par la DLL du mod (copyToClipboard est sans effet en multijoueur).
private _clip = {
    params ["_t"];
    copyToClipboard _t;
    private _b = ["ClipBegin"] call comspec_atak_native_fnc_extensionCall;
    if (_b isEqualType "" && {(_b select [0, 2]) isEqualTo "OK"}) then {
        forceUnicode 0;
        for "_i" from 0 to ((count _t) - 1) step 2000 do {
            forceUnicode 0;
            ["ClipAppend", [_t select [_i, 2000]]] call comspec_atak_native_fnc_extensionCall;
        };
        ["ClipCommit"] call comspec_atak_native_fnc_extensionCall;
    };
};
// Compte rendu libre prérempli (app Comptes rendus) : objet, texte lu en phonie, grille de la cible, fiche jointe.
private _toReport = {
    params ["_h"];
    private _g = (([_h, "ck6"] call _val) splitString " ,.-") joinString "";
    private _v = createHashMapFromArray [
        ["title", format ["CAS CHECK-IN · %1", [[_h, "ckAc"] call _val, "?"] select (([_h, "ckAc"] call _val) isEqualTo "")]],
        ["text", ([_h] call _text) joinString endl],
        ["annex", [_h] call _sd]
    ];
    if (_g regexMatch "^[0-9]{6,10}$" && {((count _g) mod 2) isEqualTo 0}) then { _v set ["grid", _g]; };
    ["prefill", "OTHER", _v] call comspec_atak_native_fnc_reportAction;
    ["REPORTS"] call comspec_atak_native_fnc_navigate;
};
// Appareil : indicatif, nombre et type, position et altitude, armement, carburant (les champs de l'appareil sont remplacés).
private _fromAir = {
    params ["_air", "_h", ["_force", true]];
    if (isNull _air) exitWith {};
    private _put = { params ["_k", "_t"]; if (_force || {([_h, _k] call _val) isEqualTo ""}) then { _h set [_k, _t]; }; };
    private _ci = [_air] call comspec_atak_native_fnc_jtacAir;
    private _cs = ["airCs", _air] call comspec_atak_native_fnc_airBrief;
    private _flight = [];
    { private _v = vehicle _x; if (_v isKindOf "Air" && {alive _v}) then { _flight pushBackUnique _v; }; } forEach units group (effectiveCommander _air);
    if ((count _flight) isEqualTo 0) then { _flight = [_air]; };
    ["ckAc", _cs] call _put;
    ["ckPilot", _cs] call _put;
    ["ckAcft", format ["%1 × %2", count _flight, _ci getOrDefault ["type", ""]]] call _put;
    ["ckPos", format ["%1 · %2 ft MSL · %3° %4 NM", [_air, 6] call comspec_atak_native_fnc_gridRef, _ci getOrDefault ["alt", 0], [round (player getDir _air), 3] call CBA_fnc_formatNumber, ((player distance2D _air) / 1852) toFixed 1]] call _put;
    private _ords = (_ci getOrDefault ["ord", []]) apply { format ["%1 × %2", _x select 1, _x select 0] };
    ["ckOrd", [_ords joinString ", ", "aucun armement air-sol"] select ((count _ords) isEqualTo 0)] call _put;
    ["ckTos", format ["carburant %1 %2", _ci getOrDefault ["fuel", 0], "%"]] call _put;
};

switch (_act) do {
    case "fields": { _fields };
    case "labels": { (_fields apply { _x select 1 }) };
    case "state": { call _state };
    case "save": { call _save; true };
    case "text": { [_arg] call _text };
    case "sd": { [_arg] call _sd };
    case "fold": {
        call _save;
        private _o = _s getOrDefault ["ckOpen", ["HDR"]];
        if (_arg in _o) then { _o = _o - [_arg]; } else { _o pushBack _arg; };
        _s set ["ckOpen", _o];
        call _render;
    };
    case "pickAir": {
        call _save;
        private _a = _arg call BIS_fnc_objectFromNetId;
        if (isNull _a) exitWith {};
        _s set ["ckAir", _arg];
        [_a, call _state] call _fromAir;
        _s set ["tab", "CKIN"];
        _s set ["ckView", ""];
        _s set ["ckClearing", true];
        call _render;
    };
    case "set": {
        call _save;
        _arg params ["_k", "_t"];
        private _h = call _state;
        // Un second appui sur le choix déjà pris l'enlève.
        _h set [_k, ["", _t] select (([_h, _k] call _val) isNotEqualTo _t)];
        call _render;
    };
    case "push": {
        call _save;
        private _h = call _state;
        _h set ["ckPush", ["1", ""] select (([_h, "ckPush"] call _val) isNotEqualTo "")];
        call _render;
    };
    case "prefill": {
        // Complète les champs vides avec ce que le jeu sait : indicatifs, appareil, cible et 9 lignes de l'app, amis, laser.
        call _save;
        private _h = call _state;
        private _put = { params ["_k", "_t"]; if (([_h, _k] call _val) isEqualTo "" && {!(_t in ["", "-"])}) then { _h set [_k, _t]; }; };
        ["ckFac", ["jtacCs"] call comspec_atak_native_fnc_airBrief] call _put;
        private _air = (_s getOrDefault ["ckAir", ""]) call BIS_fnc_objectFromNetId;
        if (isNull _air) then { _air = _s getOrDefault ["air", objNull]; };
        if (!isNull _air && {alive _air}) then { [_air, _h, false] call _fromAir; };
        private _b = [] call comspec_atak_native_fnc_jtacLines;
        private _ln = _b getOrDefault ["lines", []];
        { [_x select 0, _ln param [_x select 1, ""]] call _put; } forEach [["ck1", 0], ["ck2", 1], ["ck3", 2], ["ck4", 3], ["ck5", 4], ["ck6", 5], ["ck9", 8]];
        private _tg = _b getOrDefault ["target", []];
        if ((count _tg) >= 2) then {
            // Amis : le plus proche (direction et distance depuis la cible), puis le nombre d'amis à moins d'un kilomètre.
            private _side = side group player;
            private _n = { alive _x && {side group _x isEqualTo _side} && {!((vehicle _x) isKindOf "Air")} && {(_x distance2D _tg) < 1000} } count allUnits;
            private _l8 = _ln param [7, ""];
            ["ck8", [_l8, format ["%1 (%2 ami(s) à moins de 1 km)", _l8, _n]] select (_n > 1 && {!(_l8 in ["", "-"])})] call _put;
        };
        private _mark = _s getOrDefault ["mark", "LASER"];
        ["ck7", (createHashMapFromArray [["LASER", "LASER"], ["IR", "IR"], ["FUMI", "FUMIGÈNE"], ["AUCUN", "AUCUN"]]) getOrDefault [_mark, ""]] call _put;
        if (_mark isEqualTo "LASER") then { ["ck7c", str (_b getOrDefault ["code", 1111])] call _put; };
        ["ckFah", _b getOrDefault ["fah", ""]] call _put;
        ["ckLtl", _b getOrDefault ["ltl", ""]] call _put;
        private _thr = trim (_s getOrDefault ["jThreat", ""]);
        if (_thr isEqualTo "") then { _thr = _s getOrDefault ["auto_jThreat", ""]; };
        ["ckThr", _thr] call _put;
        // Game plan : type de contrôle, méthode, armement ; ennemi : ligne 5 ; amis : ligne 8.
        private _ord = _b getOrDefault ["ord", ""];
        ["ckSit", format ["Amis : %1. Ennemi : %2. Game plan : TYPE %3, %4%5.", [_h, "ck8"] call _val, [_h, "ck5"] call _val, _b getOrDefault ["type", 2], _b getOrDefault ["method", "BOT"], ["", ", " + _ord] select (_ord isNotEqualTo "")]] call _put;
        if ((count _tg) < 2) then { ["INFO", "Pas de cible verrouillée, 9 lignes à compléter à la main", 4, 20] call comspec_atak_native_fnc_notify; };
        _s set ["ckClearing", true];
        call _render;
    };
    case "draft": {
        call _save;
        call _keepDraft;
        ["SUCCESS", "Brouillon du check-in enregistré", 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "clear": {
        _s set ["ckin", createHashMap];
        _s set ["ckAir", ""];
        _s set ["ckDraftAt", ""];
        profileNamespace setVariable [_draftKey, []];
        [] call comspec_atak_native_fnc_saveProfile;
        _s set ["ckClearing", true];
        call _render;
    };
    case "copy": {
        call _save;
        [([call _state] call _text) joinString endl] call _clip;
        ["SUCCESS", "Check-in copié en texte (Ctrl+V)", 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "send": {
        call _save;
        private _h = call _state;
        if (({ ([_h, _x select 0] call _val) isNotEqualTo "" } count (_fields select { (_x select 4) in ["ckin", "nine"] })) isEqualTo 0) exitWith {
            ["WARNING", "Fiche vide, remplissez au moins le check-in ou une ligne", 3, 30] call comspec_atak_native_fnc_notify;
        };
        // Destinataires : l'appareil de la fiche, puis les indicatifs saisis (aéronef ou joueur du camp de même indicatif).
        private _dest = [];
        private _a = (_s getOrDefault ["ckAir", ""]) call BIS_fnc_objectFromNetId;
        if (!isNull _a && {alive _a}) then { _dest pushBack [["airCs", _a] call comspec_atak_native_fnc_airBrief, _a]; };
        private _known = ["airs", false] call comspec_atak_native_fnc_airBrief;
        private _side = side group player;
        {
            private _t = toUpper (trim _x);
            if (_t isNotEqualTo "" && {(_dest findIf { (toUpper (_x select 0)) isEqualTo _t }) < 0}) then {
                private _ki = _known findIf { (toUpper (_x select 1)) isEqualTo _t };
                private _who = if (_ki >= 0) then { (_known select _ki) select 0 } else {
                    (allPlayers select { alive _x && {side group _x isEqualTo _side} && {(toUpper ([_x] call comspec_atak_native_fnc_unitCallsign)) isEqualTo _t} }) param [0, objNull]
                };
                _dest pushBack [_t, _who];
            };
        } forEach (([_h, "ckTo"] call _val) splitString ",;");
        // Le pilote lit la fiche champ par champ (libellés « labels »), la situation en remarques.
        private _lines = _fields apply { [_h, _x select 0] call _val };
        private _ref = ["send", ["C", _lines, [_h, "ckSit"] call _val, _dest, "", true]] call comspec_atak_native_fnc_airBrief;
        if (_ref isEqualTo "") exitWith {};
        [_h, (_dest apply { _x select 0 }) joinString ", "] call _histAdd;
        call _keepDraft;
        call _render;
    };
    case "report": {
        call _save;
        private _h = call _state;
        [_h, "C.R."] call _histAdd;
        [_h] call _toReport;
    };
    case "histOpen": { call _save; _s set ["ckView", _arg]; call _render; };
    case "histBack": { _s set ["ckView", ""]; _s set ["ckClearing", true]; call _render; };
    case "histDup": {
        // Nouveau CAS repris d'un check-in de la partie : la fiche en cours est remplacée (son brouillon reste au profil).
        private _e = [_arg] call _histGet;
        if ((count _e) isEqualTo 0) exitWith {};
        _s set ["ckin", +(_e get "vals")];
        _s set ["ckView", ""];
        _s set ["ckClearing", true];
        ["INFO", "Check-in repris, vérifiez la cible et l'heure", 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "histDel": {
        missionNamespace setVariable [_histKey, (call _hist) select { (_x get "ref") isNotEqualTo _arg }];
        if ((_s getOrDefault ["ckView", ""]) isEqualTo _arg) then { _s set ["ckView", ""]; };
        call _render;
    };
    case "histCopy": {
        private _e = [_arg] call _histGet;
        if ((count _e) isEqualTo 0) exitWith {};
        [([_e get "vals"] call _text) joinString endl] call _clip;
        ["SUCCESS", "Check-in copié en texte (Ctrl+V)", 3, 20] call comspec_atak_native_fnc_notify;
    };
    case "histReport": {
        private _e = [_arg] call _histGet;
        if ((count _e) isEqualTo 0) exitWith {};
        [_e get "vals"] call _toReport;
    };
};
