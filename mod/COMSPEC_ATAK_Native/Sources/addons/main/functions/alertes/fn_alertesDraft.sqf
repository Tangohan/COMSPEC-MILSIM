/*
    App « Avis et vigilance » : brouillon de l'assistant d'émission (ÉMETTRE) et du signalement (JE L'AI VU).
    Sept étapes courtes : TYPE, SUJET, NIVEAU, MOTIFS, ZONE, VALIDITÉ, ENVOI. Le brouillon est gardé à chaque geste (uiNamespace
    COMSPEC_ATAK_AlertesDraft et profileNamespace du même nom) : on peut quitter l'app, le téléphone ou la partie et reprendre.
    Envoi : fn_alertesSend (« create », identifiant client stable). Sans le droit d'émettre, ou pour un sujet inconnu, le site crée une
    PROPOSITION à valider par le bureau (ICD-SSE-ALERTES-001 § 3.4) : l'écran le dit avant l'envoi et après.
    Aucun toast, aucun hint, aucun systemChat : ce qui manque s'écrit sous l'étape (clé « hint »).

    Params : [action, argument]
      ["get"]                  le brouillon (HashMap)            ["steps"] noms des étapes
      ["set", [clé, valeur]]   pose un choix                      ["toggle", [liste, code]] coche un motif ou une mesure (6 au plus)
      ["step", n] / ["next"] / ["prev"]
      ["save"]                 relit les champs de saisie de l'étape affichée
      ["here"]                 zone = ma position (grille, lieu-dit le plus proche)
      ["mapArm"] / ["mapUse"]  pointer sur la carte : la carte s'ouvre, le dernier point touché devient la grille
      ["subjects", texte]      personnes et identités que le téléphone connaît déjà : [[nom, référence, origine]…]
      ["pick", [référence, nom, origine]] / ["unknown"]
      ["rank"]                 [clé du niveau, rang] déduits de la menace et de l'armement (règle du site, ICD § 3.1.1)
      ["missing"]              ce qui manque avant l'envoi (textes)   ["proposal"] true si l'envoi sera une proposition
      ["describe"]             description de l'inconnu, assemblée     ["body"] corps de l'opération « create »
      ["submit"]               envoie ; rend l'id de l'envoi ("" si refusé)
      ["sent", id]             envoi accepté par le site : brouillon effacé
      ["clear"]                efface le brouillon
      ["report", [clé, valeur]] / ["reportGet"] / ["reportOpen", [réf, position, grille]] / ["reportSend"]   signalement d'une observation
*/
params [["_act", "get"], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _steps = ["TYPE", "SUJET", "NIVEAU", "MOTIFS", "ZONE", "VALIDITÉ", "ENVOI"];
private _defaults = {
    createHashMapFromArray [["step", 0], ["doc", "vigilance"], ["type", ""], ["subjMode", "known"], ["subject", ""], ["subjectName", ""], ["subjectFrom", ""],
        ["sex", ""], ["height", ""], ["build", ""], ["clothes", ""], ["marks", ""], ["threat", "low"], ["armed", "unknown"], ["reasons", []], ["measures", []],
        ["text", ""], ["zone", ""], ["grid", ""], ["place", ""], ["days", 30], ["service", ""], ["delay", "now"], ["q", ""], ["qr", ""], ["qm", ""], ["hint", ""], ["armAt", -1], ["sentId", ""], ["codesTab", "reasons"]]
};
private _d = uiNamespace getVariable ["COMSPEC_ATAK_AlertesDraft", createHashMap];
if ((count _d) isEqualTo 0) then {
    _d = call _defaults;
    // Brouillon gardé sur le poste : repris tel quel (paires clé, valeur).
    private _saved = profileNamespace getVariable ["COMSPEC_ATAK_AlertesDraft", []];
    if (_saved isEqualType [] && {(_saved findIf { !(_x isEqualType []) || {(count _x) isNotEqualTo 2} || {!((_x select 0) isEqualType "")} }) < 0}) then {
        { if ((_x select 0) in _d && {(_x select 1) isEqualType (_d get (_x select 0))}) then { _d set [_x select 0, _x select 1]; }; } forEach _saved;
    };
    _d set ["armAt", -1];
    uiNamespace setVariable ["COMSPEC_ATAK_AlertesDraft", _d];
};
private _render = {
    [{
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "SSEALERT") then {
            ["SSEALERT"] call comspec_atak_native_fnc_pageRender;
        };
    }] call comspec_atak_native_fnc_nextFrame;
};
private _keep = { profileNamespace setVariable ["COMSPEC_ATAK_AlertesDraft", (keys _d) apply { [_x, _d get _x] }]; };
// Texte saisi : borné en caractères (jamais en octets : un accent n'est pas coupé en deux), sans retour chariot.
private _cut = {
    params ["_t", ["_max", 400]];
    if !(_t isEqualType "") exitWith { "" };
    trim (toString (((toArray _t) select { _x >= 32 || {_x isEqualTo 10} }) select [0, _max]))
};
// Champs de saisie présents selon l'étape affichée : relus avant tout redessin (sinon la frappe en cours serait perdue).
private _saveFields = {
    private _keys = switch (_d get "step") do {
        case 1: { ["q", "subject", "height", "clothes", "marks"] };
        case 3: { ["qr", "qm", "text"] };
        case 4: { ["zone", "grid", "place"] };
        default { [] };
    };
    // Sujet connu : seule la recherche est affichée ; sujet inconnu : la description.
    if ((_d get "step") isEqualTo 1) then { _keys = [["q", "subject"], ["height", "clothes", "marks"]] select ((_d get "subjMode") isEqualTo "unknown"); };
    { _d set [_x, [[format ["av_%1", _x], _d getOrDefault [_x, ""]] call comspec_atak_native_fnc_formValue, [400, 80] select (_x in ["q", "qr", "qm", "subject", "grid", "height"])] call _cut]; } forEach _keys;
    // Référence saisie à la main : elle tient lieu de nom tant qu'aucune personne n'est choisie.
    if ((_d get "subject") isNotEqualTo "" && {(_d get "subjectName") isEqualTo ""}) then { _d set ["subjectName", _d get "subject"]; _d set ["subjectFrom", "référence saisie"]; };
};
private _rank = {
    switch (true) do {
        case ((_d get "armed") isEqualTo "yes"): { ["danger", 4] };
        case ((_d get "threat") in ["high", "critical"]): { ["caution", 3] };
        case ((_d get "threat") isEqualTo "medium"): { ["reinforced", 2] };
        default { ["watch", 1] };
    }
};
private _describe = {
    private _bits = [];
    {
        _x params ["_label", "_key", ["_section", ""]];
        private _v = _d getOrDefault [_key, ""];
        if (_v isNotEqualTo "") then { _bits pushBack format ["%1 : %2", _label, _v]; };
    } forEach [["Sexe", "sex"], ["Taille", "height"], ["Corpulence", "build"], ["Tenue", "clothes"], ["Signes", "marks"]];
    private _name = _d get "subjectName";
    // Personne vue en jeu sans référence au registre : son nom ouvre la description.
    if ((_d get "subjMode") isEqualTo "known" && {(_d get "subject") isEqualTo ""} && {_name isNotEqualTo ""}) then {
        _bits = [format ["Personne connue du poste : %1 (%2)", _name, _d get "subjectFrom"]] + _bits;
    };
    [_bits joinString ". ", 400] call _cut
};
private _proposal = {
    private _rights = ["rights"] call comspec_atak_native_fnc_alertesAction;
    private _can = _rights param [[0, 1] select ((_d get "doc") isEqualTo "avis"), false];
    !_can || {(_d get "subject") isEqualTo ""}
};
private _missing = {
    private _m = [];
    if ((_d get "doc") isEqualTo "avis" && {(_d get "type") isEqualTo ""}) then { _m pushBack "le type d'avis (étape TYPE)"; };
    if ((_d get "subject") isEqualTo "" && {(count (call _describe)) < 10}) then { _m pushBack "le sujet : une personne connue, ou la description de l'inconnu (étape SUJET)"; };
    if ((_d get "reasons") isEqualTo [] && {(_d get "text") isEqualTo ""}) then { _m pushBack "au moins un motif ou une consigne (étape MOTIFS)"; };
    if ((_d get "zone") isEqualTo "" && {(_d get "grid") isEqualTo ""} && {(_d get "place") isEqualTo ""}) then { _m pushBack "la zone, le lieu ou la grille (étape ZONE)"; };
    _m
};
private _nearName = {
    private _loc = nearestLocations [getPos player, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 3000];
    if ((count _loc) > 0) then { text (_loc select 0) } else { "" }
};
// Signalement d'une observation (JE L'AI VU) : réf, position et heure prises d'office.
private _r = uiNamespace getVariable ["COMSPEC_ATAK_AlertesReport", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_AlertesReport", _r];

switch (_act) do {
    case "get": { _d };
    case "steps": { _steps };
    case "rank": { call _rank };
    case "describe": { call _describe };
    case "missing": { call _missing };
    case "proposal": { call _proposal };
    case "save": { call _saveFields; call _keep; true };
    case "set": {
        _arg params [["_k", ""], ["_v", ""]];
        call _saveFields;
        if (_k in _d) then { _d set [_k, _v]; };
        // Une fiche de vigilance n'a pas de type d'avis ; un type d'avis fait de l'alerte un avis.
        if (_k isEqualTo "doc" && {_v isEqualTo "vigilance"}) then { _d set ["type", ""]; };
        if (_k isEqualTo "type" && {_v isNotEqualTo ""}) then { _d set ["doc", "avis"]; };
        _d set ["hint", ""];
        call _keep;
        call _render;
        true
    };
    case "toggle": {
        _arg params [["_k", "reasons"], ["_code", ""]];
        call _saveFields;
        if !(_k in ["reasons", "measures"]) exitWith { false };
        private _l = _d get _k;
        if (_code in _l) then {
            _l deleteAt (_l find _code);
            _d set ["hint", ""];
        } else {
            if ((count _l) >= 6) exitWith { _d set ["hint", "Six codes au plus par liste."]; };
            _l pushBack _code;
            _d set ["hint", ""];
            // Codes récents en tête des listes (20 par liste, gardés sur le poste).
            private _rec = profileNamespace getVariable ["COMSPEC_ATAK_AlertesRecents", []];
            if !(_rec isEqualType []) then { _rec = []; };
            _rec = ([_code] + (_rec select { _x isNotEqualTo _code })) select [0, 40];
            profileNamespace setVariable ["COMSPEC_ATAK_AlertesRecents", _rec];
        };
        call _keep;
        call _render;
        true
    };
    case "step": {
        call _saveFields;
        if (_arg isEqualType 0) then { _d set ["step", ((floor _arg) max 0) min ((count _steps) - 1)]; };
        _d set ["hint", ""];
        call _keep;
        call _render;
        true
    };
    case "next": {
        call _saveFields;
        _d set ["step", ((_d get "step") + 1) min ((count _steps) - 1)];
        _d set ["hint", ""];
        call _keep;
        call _render;
        true
    };
    case "prev": {
        call _saveFields;
        if ((_d get "step") isEqualTo 0) exitWith { ["home"] call comspec_atak_native_fnc_alertesAction; true };
        _d set ["step", ((_d get "step") - 1) max 0];
        _d set ["hint", ""];
        call _keep;
        call _render;
        true
    };
    case "here": {
        call _saveFields;
        _d set ["grid", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef];
        private _n = call _nearName;
        if (_n isNotEqualTo "") then { _d set ["place", _n]; if ((_d get "zone") isEqualTo "") then { _d set ["zone", _n]; }; };
        _d set ["armAt", -1];
        call _keep;
        call _render;
        true
    };
    // Pointer sur la carte : la carte s'ouvre ; au retour, le dernier point touché depuis l'ouverture est proposé (« mapUse »).
    case "mapArm": {
        call _saveFields;
        _d set ["armAt", diag_tickTime];
        call _keep;
        _s set ["alertesStatus", ["Touchez le point sur la carte, puis revenez : la grille sera proposée.", diag_tickTime]];
        [{ ["MAP"] call comspec_atak_native_fnc_navigate; }] call comspec_atak_native_fnc_nextFrame;
        true
    };
    case "mapPoint": {
        private _at = _s getOrDefault ["mapLcAt", -1];
        private _w = _s getOrDefault ["mapLcW", []];
        if ((_d get "armAt") < 0 || {!(_at isEqualType 0)} || {_at < (_d get "armAt")} || {!(_w isEqualType [])} || {(count _w) < 2}) exitWith { [] };
        [_w select 0, _w select 1, 0]
    };
    case "mapUse": {
        private _p = ["mapPoint"] call comspec_atak_native_fnc_alertesDraft;
        if (_p isEqualTo []) exitWith { false };
        call _saveFields;
        _d set ["grid", [_p, 8] call comspec_atak_native_fnc_gridRef];
        private _loc = nearestLocations [_p, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 3000];
        if ((count _loc) > 0) then { _d set ["place", text (_loc select 0)]; };
        _d set ["armAt", -1];
        call _keep;
        call _render;
        true
    };

    // Ce que le téléphone connaît déjà : avis de l'app Recherchés (référence du site), contrôles d'identité récents, lectures SEEK II,
    // fiches du portail. Une entrée sans référence au registre part comme inconnu décrit (donc en proposition).
    case "subjects": {
        private _q = toLower (if (_arg isEqualType "") then { _arg } else { "" });
        private _outS = [];
        private _add = {
            params ["_name", "_ref", "_from"];
            if (!(_name isEqualType "") || {!(_ref isEqualType "")} || {_name isEqualTo ""}) exitWith {};
            if (_q isNotEqualTo "" && {((toLower _name) find _q) < 0} && {((toLower _ref) find _q) < 0}) exitWith {};
            if ((_outS findIf { (toLower (_x select 0)) isEqualTo (toLower _name) && {(_x select 1) isEqualTo _ref} }) < 0) then { _outS pushBack [_name, _ref, _from]; };
        };
        {
            if (_x isEqualType [] && {(count _x) >= 4}) then { [_x select 3, _x param [2, ""], "Recherchés"] call _add; };
        } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Wanted", [[]]]) param [0, []]);
        {
            if (_x isEqualType createHashMap) then {
                private _nm = _x getOrDefault ["subjName", ""];
                if (!(_nm isEqualType "") || {_nm isEqualTo ""}) then {
                    private _doc = _x getOrDefault ["doc", createHashMap];
                    if (_doc isEqualType createHashMap) then { _nm = trim format ["%1 %2", toUpper (_doc getOrDefault ["last", ""]), _doc getOrDefault ["first", ""]]; };
                };
                [_nm, "", "Contrôle ID"] call _add;
            };
        } forEach (missionNamespace getVariable ["comspec_sse_idHist", []]);
        {
            if (_x isEqualType createHashMap) then { [trim format ["%1 %2", toUpper (_x getOrDefault ["last", ""]), _x getOrDefault ["first", ""]], "", "Contrôle ID"] call _add; };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_IdChecks", []]);
        {
            if (_x isEqualType [] && {(count _x) >= 10} && {(_x select 0) isEqualType ""}) then { [_x select 0, "", "SEEK II"] call _add; };
        } forEach (missionNamespace getVariable ["COMSPEC_SeekQueryHistory", []]);
        {
            if ((_x getOrDefault ["name", ""]) isNotEqualTo "") then { [_x get "name", "", "Fiches"] call _add; };
        } forEach (["list"] call comspec_atak_native_fnc_fichesAction);
        _outS select [0, 12]
    };
    case "pick": {
        _arg params [["_ref", ""], ["_name", ""], ["_from", ""]];
        call _saveFields;
        _d set ["subjMode", "known"];
        _d set ["subject", _ref];
        _d set ["subjectName", _name];
        _d set ["subjectFrom", _from];
        _d set ["hint", ""];
        call _keep;
        call _render;
        true
    };
    case "unknown": {
        call _saveFields;
        _d set ["subjMode", "unknown"];
        _d set ["subject", ""];
        _d set ["subjectName", ""];
        _d set ["subjectFrom", ""];
        call _keep;
        call _render;
        true
    };

    // Corps de « create » (ICD § 3.4). « subject » : référence d'identité ou de personne ; sinon « unknown » : description.
    case "body": {
        private _b = createHashMapFromArray [
            ["doc", _d get "doc"], ["threat", _d get "threat"], ["armed", _d get "armed"],
            ["reasons", (_d get "reasons") joinString ","], ["measures", (_d get "measures") joinString ","],
            ["zone", [_d get "zone", 120] call _cut], ["grid", [_d get "grid", 20] call _cut], ["place", [_d get "place", 120] call _cut],
            ["days", _d get "days"], ["text", [_d get "text", 400] call _cut], ["service", _d get "service"], ["delay", _d get "delay"]
        ];
        if ((_d get "doc") isEqualTo "avis") then { _b set ["type", _d get "type"]; };
        if ((_d get "subject") isNotEqualTo "") then { _b set ["subject", [_d get "subject", 80] call _cut]; } else { _b set ["unknown", call _describe]; };
        _b
    };
    case "submit": {
        call _saveFields;
        call _keep;
        private _m = call _missing;
        if (_m isNotEqualTo []) exitWith {
            _d set ["hint", format ["Il manque : %1.", _m joinString " ; "]];
            call _render;
            ""
        };
        // Un envoi de ce brouillon déjà parti et pas encore arrivé : on le renvoie (même identifiant client), jamais un second.
        private _prev = ["get", _d getOrDefault ["sentId", ""]] call comspec_atak_native_fnc_alertesSend;
        if ((count _prev) > 0 && {(_prev getOrDefault ["web", ""]) isNotEqualTo "OK"}) exitWith {
            ["retry", _prev get "id"] call comspec_atak_native_fnc_alertesSend;
            _s set ["alertesLast", _prev get "id"];
            ["view", "sent"] call comspec_atak_native_fnc_alertesAction;
            _prev get "id"
        };
        private _name = [_d get "subjectName", "Sujet non identifié"] select ((_d get "subjectName") isEqualTo "");
        private _label = format ["%1 · %2", ["kindLabel", _d get "doc"] call comspec_atak_native_fnc_alertesAction, _name];
        private _id = ["new", ["create", ["body"] call comspec_atak_native_fnc_alertesDraft, "", _label, _d get "subjectName"]] call comspec_atak_native_fnc_alertesSend;
        _d set ["sentId", _id];
        call _keep;
        ["view", "sent"] call comspec_atak_native_fnc_alertesAction;
        _id
    };
    case "sent": {
        if ((_d getOrDefault ["sentId", ""]) isNotEqualTo _arg) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_AlertesDraft", createHashMap];
        profileNamespace setVariable ["COMSPEC_ATAK_AlertesDraft", []];
        true
    };
    case "clear": {
        uiNamespace setVariable ["COMSPEC_ATAK_AlertesDraft", createHashMap];
        profileNamespace setVariable ["COMSPEC_ATAK_AlertesDraft", []];
        call _render;
        true
    };

    // ---------------------------------------------------------------- Signalement d'une observation
    case "reportGet": { _r };
    // Ouvre le signalement de l'alerte : position et heure prises d'office (celles du joueur, ou celles du contrôle d'identité).
    case "reportOpen": {
        _arg params [["_ref", ""], ["_pos", []], ["_grid", ""]];
        if !(_pos isEqualType [] && {(count _pos) >= 2}) then { _pos = getPosASL player; };
        if (!(_grid isEqualType "") || {_grid isEqualTo ""}) then { _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef; };
        private _loc = nearestLocations [_pos, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 3000];
        systemTimeUTC params ["_y", "_mo", "_dd", "_h", "_mi"];
        private _p2 = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
        {
            _r set [_x select 0, _x select 1];
        } forEach [["ref", _ref], ["pos", [_pos select 0, _pos select 1]], ["grid", _grid], ["place", if ((count _loc) > 0) then { text (_loc select 0) } else { "" }],
            ["when", format ["%1-%2-%3 %4:%5", _y, [_mo] call _p2, [_dd] call _p2, [_h] call _p2, [_mi] call _p2]], ["clock", [dayTime, "HH:MM"] call BIS_fnc_timeToString],
            ["what", "seen"], ["sure", "medium"], ["dir", ""], ["vehicle", ""], ["text", ""], ["hint", ""]];
        _s set ["alertesOpen", _ref];
        _s set ["alertesView", "report"];
        if ((_s getOrDefault ["activePage", ""]) isEqualTo "SSEALERT") then { call _render; } else { ["SSEALERT"] call comspec_atak_native_fnc_navigate; };
        true
    };
    case "report": {
        _arg params [["_k", ""], ["_v", ""]];
        { _r set [_x, [[format ["av_r%1", _x], _r getOrDefault [_x, ""]] call comspec_atak_native_fnc_formValue, [600, 80] select (_x isEqualTo "vehicle")] call _cut]; } forEach ["text", "vehicle"];
        if (_k isNotEqualTo "") then { _r set [_k, _v]; };
        _r set ["hint", ""];
        call _render;
        true
    };
    case "reportSend": {
        { _r set [_x, [[format ["av_r%1", _x], _r getOrDefault [_x, ""]] call comspec_atak_native_fnc_formValue, [600, 80] select (_x isEqualTo "vehicle")] call _cut]; } forEach ["text", "vehicle"];
        private _ref = _r getOrDefault ["ref", ""];
        if (_ref isEqualTo "") exitWith { "" };
        // Texte facultatif pour le joueur ; le site en demande 3 caractères : « Vu » et le lieu partent d'office.
        private _text = _r getOrDefault ["text", ""];
        if ((count _text) < 3) then { _text = trim format ["Vu en %1 %2", _r getOrDefault ["grid", ""], _r getOrDefault ["place", ""]]; };
        private _b = createHashMapFromArray [["ref", _ref], ["what", _r getOrDefault ["what", "seen"]], ["when", _r getOrDefault ["when", ""]],
            ["place", _r getOrDefault ["place", ""]], ["grid", _r getOrDefault ["grid", ""]], ["pos", _r getOrDefault ["pos", []]], ["text", _text],
            ["dir", _r getOrDefault ["dir", ""]], ["vehicle", _r getOrDefault ["vehicle", ""]], ["sure", _r getOrDefault ["sure", "medium"]]];
        private _id = ["new", ["report", _b, _ref, format ["Signalement · %1", _ref], ""]] call comspec_atak_native_fnc_alertesSend;
        _r set ["text", ""];
        _r set ["vehicle", ""];
        ["view", "sent"] call comspec_atak_native_fnc_alertesAction;
        _id
    };
    default { false };
};
