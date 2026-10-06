/*
    App Médical, pour les médecins et chefs d'équipe :
    - alertes médicales Athena (inconscient, arrêt cardiaque, bilans) avec triage : à secourir, en cours, traité, KIA ;
    - suivi des troupes du camp (état ACE : stable, blessé, critique, inconscient, arrêt), les plus graves en premier ;
    - LOCALISER centre la carte sur le blessé.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _bridge = [] call comspec_atak_native_fnc_bridge;
private _canTriage = _bridge && {!isNil "comspec_overwatch_connect_fnc_canTriageMedical"} && {[] call comspec_overwatch_connect_fnc_canTriageMedical};
private _sel = _s getOrDefault ["medSel", ""];
private _tab = _s getOrDefault ["medTab", "ALERTS"];
private _alertsAll = (missionNamespace getVariable ["COMSPEC_MedicalAlerts", []]) select { _x isEqualType createHashMap };
private _open = { (_x getOrDefault ["triage_status", "a_secourir"]) in ["a_secourir", "en_cours"] } count _alertsAll;
// Onglets : une seule chose à l'écran à la fois.
private _tabBtn = { params ["_t", "_k"]; [_t, compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['medTab', '%1']; [{ ['MEDICAL'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _k], _tab isEqualTo _k] };
// Identité médicale d'un patient : groupe sanguin (plaque ACE, variable ACE / KAT, profil COMSPEC) et poids (profil).
// Renvoie [texte groupe, texte poids] ; "non renseigné" quand rien n'est connu.
private _medIdent = {
    params [["_u", objNull], ["_alert", createHashMap]];
    private _bt = "";
    private _str = { params ["_v"]; if (isNil "_v") exitWith { "" }; if (_v isEqualType "") exitWith { trim _v }; if (_v isEqualType 0) exitWith { str _v }; "" };
    if (!isNull _u) then {
        if (!isNil "ace_dogtags_fnc_getDogtagData") then {
            private _d = [_u] call ace_dogtags_fnc_getDogtagData;
            if (_d isEqualType [] && {(count _d) >= 3}) then { _bt = [_d select 2] call _str; };
        };
        if (_bt isEqualTo "") then {
            private _i = _u getVariable ["ace_medical_bloodType", -1];
            if (_i isEqualType 0 && {_i >= 0} && {_i <= 7}) then { _bt = ["O-", "O+", "A-", "A+", "B-", "B+", "AB-", "AB+"] select _i; };
        };
        { if (_bt isEqualTo "") then { _bt = [_u getVariable [_x, ""]] call _str; }; } forEach ["KAT_circulation_bloodType", "COMSPEC_BloodType", "comspec_blood_type", "COMSPEC_ProfileBloodType"];
        if (_bt isEqualTo "" && {_u isEqualTo player} && {!isNil "comspec_overwatch_connect_fnc_getBloodType"}) then { _bt = [[] call comspec_overwatch_connect_fnc_getBloodType] call _str; };
    };
    if (_bt isEqualTo "") then { _bt = [_alert getOrDefault ["blood_type", ""]] call _str; };
    private _w = "";
    if (!isNull _u) then {
        { if (_w isEqualTo "") then {
            private _v = _u getVariable [_x, nil];
            if (!isNil "_v") then { if (_v isEqualType "") then { _v = parseNumber _v; }; if (_v isEqualType 0 && {_v > 0}) then { _w = format ["%1 kg", round _v]; }; };
        }; } forEach ["COMSPEC_WeightKg", "COMSPEC_weight_kg", "comspec_weight_kg", "COMSPEC_ProfileWeight"];
    };
    if (_w isEqualTo "") then {
        private _v = _alert getOrDefault ["weight_kg", 0];
        if (_v isEqualType "") then { _v = parseNumber _v; };
        if (_v isEqualType 0 && {_v > 0}) then { _w = format ["%1 kg", round _v]; };
    };
    private _nr = "<t color='#8a9a93'>non renseigné</t>";
    [[format ["<t font='RobotoCondensedBold' color='#e5483a'>%1</t>", _bt], _nr] select (_bt isEqualTo ""), [format ["<t font='RobotoCondensedBold'>%1</t>", _w], _nr] select (_w isEqualTo "")]
};
// Ligne d'identité médicale réduite aux données affichées : [libellé, valeur] ("" si rien).
private _identRow = {
    params ["_bt", "_w", "_ctx"];
    private _lb = []; private _vl = [];
    if (["bloodtype", _ctx] call comspec_atak_native_fnc_medShow) then { _lb pushBack "Groupe sanguin"; _vl pushBack _bt; };
    if (["weight", _ctx] call comspec_atak_native_fnc_medShow) then { _lb pushBack "Poids"; _vl pushBack _w; };
    [_lb joinString " · ", _vl joinString " · "]
};
private _rows = [["segment", "", [
    [["ALERTES", format ["ALERTES (%1)", _open]] select (_open > 0), "ALERTS"] call _tabBtn,
    ["TROUPES", "TROOPS"] call _tabBtn,
    ["MEDEVAC", "MEDEVAC"] call _tabBtn
]], ["gap"]];
if (_tab isEqualTo "ALERTS") then {

// Alertes Athena
_rows pushBack ["section", "Alertes médicales", ["Liaison Athena requise", ["Lecture seule : triage réservé aux médecins et chefs d'équipe", "Touchez une alerte pour la trier"] select _canTriage] select _bridge];
private _alerts = _alertsAll;
// Alertes médicales masquées (Réglages > Réalisme) : liste retirée.
private _showA = ["ctx", "alerts"] call comspec_atak_native_fnc_medShow;
if (!_showA) then { _alerts = []; _rows pushBack ["text", "<t color='#8a9a93'>Alertes médicales masquées (Réglages > Réalisme).</t>"]; };
if (_showA && {(count _alerts) isEqualTo 0}) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucune alerte active.</t>"]; };
private _showState = ["state", "alerts"] call comspec_atak_native_fnc_medShow;
{
    private _id = str (_x getOrDefault ["id", ""]);
    private _kind = toLower (_x getOrDefault ["kind", ""]);
    private _status = _x getOrDefault ["triage_status", "a_secourir"];
    private _col = switch (_status) do { case "en_cours": { "#f2ab33" }; case "traite": { "#5cc76b" }; case "kia": { "#8a9a93" }; default { "#e5483a" }; };
    private _kindLabel = if (!_showState && {_kind isNotEqualTo "kia"}) then { "ALERTE MÉDICALE" } else { switch (_kind) do { case "cardiac_arrest": { "ARRÊT CARDIAQUE" }; case "unconscious": { "INCONSCIENT" }; case "kia": { "KIA" }; case "wia_report": { "BILAN" }; default { "ASSISTANCE" }; } };
    private _who = _x getOrDefault ["call_sign", ""];
    if (_who isEqualTo "") then { _who = _x getOrDefault ["label", "?"]; };
    _rows pushBack ["buttons", [[format ["%1 · %2 · %3 · %4", _kindLabel, _who, _x getOrDefault ["grid", "—"], toUpper (_x getOrDefault ["triage_label", _status])],
        compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['medSel', ['%1', ''] select (((uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) getOrDefault ['medSel', '']) isEqualTo '%1')]; [{ ['MEDICAL'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _id], _id isEqualTo _sel]]];
    _rows pushBack ["text", format ["<t size='0.75' color='%1'>● %2</t><t size='0.75' color='#8a9a93'>  %3</t>", _col, _x getOrDefault ["triage_label", _status], _x getOrDefault ["created_at", ""]]];
    if (_id isEqualTo _sel) then {
        private _grid = _x getOrDefault ["grid", ""];
        // Patient de l'alerte : retrouvé en jeu par son identifiant Steam ou son indicatif, sinon données de l'alerte.
        private _uid = format ["%1", _x getOrDefault ["steam_id", _x getOrDefault ["uid", ""]]];
        private _pu = (allPlayers select { (getPlayerUID _x) isEqualTo _uid || {([_x, true] call comspec_atak_native_fnc_unitCallsign) isEqualTo _who} }) param [0, objNull];
        ([_pu, _x] call _medIdent) params ["_btT", "_wT"];
        // Données masquées (Réglages > Réalisme, fn_medShow) : la ligne ne garde que ce qui est affiché.
        ([_btT, _wT, "alerts"] call _identRow) params ["_idL", "_idV"];
        if (_idL isNotEqualTo "") then { _rows pushBack ["info", _idL, _idV]; };
        private _b = [[ "LOCALISER", compile format ["['locate', '%1'] call comspec_atak_native_fnc_medicalAction;", _grid]]];
        if (_canTriage) then {
            _b append [
                ["EN COURS", compile format ["['triage', 'en_cours', '%1'] call comspec_atak_native_fnc_medicalAction;", _id]],
                ["TRAITÉ", compile format ["['triage', 'traite', '%1'] call comspec_atak_native_fnc_medicalAction;", _id], true],
                ["KIA", compile format ["['triage', 'kia', '%1'] call comspec_atak_native_fnc_medicalAction;", _id]]
            ];
        };
        _rows pushBack ["buttons", _b];
    };
} forEach _alerts;

};

// Demande MEDEVAC 9-line (COMSPEC Link → Athena, repère LZ sur la carte)
if (_tab isEqualTo "MEDEVAC") then {
private _m = uiNamespace getVariable ["COMSPEC_ATAK_Medevac", createHashMap];
private _mv = { params ["_k", "_d"]; _m getOrDefault [_k, _d] };
private _seg = {
    params ["_label", "_key", "_def", "_opts"];
    private _cur = [_key, _def] call _mv;
    ["segment", _label, _opts apply { [_x select 0, compile format ["['set', '%1', '%2'] call comspec_atak_native_fnc_medicalAction;", _key, _x select 1], (_x select 1) isEqualTo _cur] }]
};
_rows append [
    ["section", "Demande MEDEVAC", "9-line envoyé au camp (et au poste web avec Overwatch), LZ marquée sur la carte"],
    ["buttons", [["REMPLIR AUTO (blessés à 50 m)", { ["auto"] call comspec_atak_native_fnc_medicalAction; }]]],
    ["Priorité", "prio", "URGENT", [["URGENT", "URGENT"], ["PRIORITAIRE", "PRIORITY"], ["ROUTINE", "ROUTINE"]]] call _seg,
    ["Blessés urgents (T1)", "mT1", "1", [["0", "0"], ["1", "1"], ["2", "2"], ["3", "3"], ["4+", "4"]]] call _seg,
    ["Blessés prioritaires (T2)", "mT2", "0", [["0", "0"], ["1", "1"], ["2", "2"], ["3", "3"], ["4+", "4"]]] call _seg,
    ["Blessés différés (T3)", "mT3", "0", [["0", "0"], ["1", "1"], ["2", "2"], ["3", "3"], ["4+", "4"]]] call _seg,
    ["info", "Couchés / assis", format ["%1 sur brancard · %2 valides", ["litter", 0] call _mv, ["amb", 0] call _mv]],
    ["Équipement spécial", "equip", "NONE", [["AUCUN", "NONE"], ["TREUIL", "HOIST"], ["EXTRACTION", "EXTRACTION"], ["RESPIRATEUR", "VENTILATOR"]]] call _seg,
    ["Sécurité de la LZ", "sec", "NO_ENEMY", [["PAS D'ENNEMI", "NO_ENEMY"], ["POSSIBLE", "POSSIBLE_ENEMY"], ["ENNEMI", "ENEMY_IN_AREA"], ["ESCORTE", "ARMED_ESCORT"]]] call _seg,
    ["Marquage", "mark", "SMOKE", [["FUMÉE", "SMOKE"], ["PANNEAU", "PANEL"], ["PYRO", "PYRO"], ["AUCUN", "NONE"]]] call _seg,
    ["Couleur", "col", "GREEN", [["VERT", "GREEN"], ["ROUGE", "RED"], ["JAUNE", "YELLOW"], ["VIOLET", "PURPLE"]]] call _seg,
    ["edit", "mLz", "Grille de la LZ", ["mLz", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef] call _mv],
    ["edit", "mRem", "Remarques", ["mRem", ""] call _mv],
    ["buttons", [["LZ À MA POSITION", { ["lzHere"] call comspec_atak_native_fnc_medicalAction; }], ["LZ SUR LA CARTE", { ["pick"] call comspec_atak_native_fnc_medicalAction; }]]],
    ["buttons", [["DEMANDER LE MEDEVAC", { ["medevac"] call comspec_atak_native_fnc_medicalAction; }, true]]]
];
// Suivi des demandes du camp : étapes ● ○, les receveurs font avancer, le demandeur peut annuler.
private _steps = ["DEMANDÉE", "ACCEPTÉE", "EN VOL", "SUR ZONE", "TERMINÉE"];
private _reqs = values (missionNamespace getVariable ["COMSPEC_ATAK_MedevacReqs", createHashMap]);
_reqs = _reqs apply { [[0, 1] select ((_x get "status") >= 4), _x get "id", _x] };
_reqs sort true;
_rows pushBack ["section", "Suivi des MEDEVAC", ["Aucune demande en cours", format ["%1 demande(s)", count _reqs]] select ((count _reqs) > 0)];
{
    private _r = _x select 2;
    private _st = _r get "status";
    private _mine = (_r get "uid") isEqualTo getPlayerUID player;
    private _dots = (_steps apply { ["○", "●"] select ((_steps find _x) <= _st) }) joinString " ";
    private _stTxt = if (_r getOrDefault ["cancelled", false]) then { "<t color='#8a9a93'>ANNULÉE</t>" } else { format ["<t color='%1'>%2</t>", ["#f2ab33", "#5cc76b"] select (_st >= 4), _steps select _st] };
    _rows pushBack ["text", format ["<t font='RobotoCondensedBold'>%1 · %2</t>  <t size='0.8' color='#8a9a93'>%3 · LZ %4</t><br/><t size='0.8'>T1 %5 · T2 %6 · T3 %7 · brancards %8 · %9 · %10</t><br/>%11  %12%13",
        _r get "prio", _r get "from", _r get "time", _r get "grid", _r get "t1", _r get "t2", _r get "t3", _r get "litter", _r get "sec", _r get "mark",
        _dots, _stTxt, ["", format ["  <t size='0.75' color='#8a9a93'>par %1</t>", _r get "by"]] select ((_r get "by") isNotEqualTo "")]];
    private _b = [["LZ", compile format ["['locateReq', '%1'] call comspec_atak_native_fnc_medicalAction;", _r get "id"]]];
    if (_st < 4) then {
        if (!_mine) then { _b pushBack [_steps select (_st + 1), compile format ["['status', '%1', '%2'] call comspec_atak_native_fnc_medicalAction;", _r get "id", _st + 1], true]; };
        if (_mine) then { _b pushBack ["ANNULER", compile format ["['status', '%1', '-1'] call comspec_atak_native_fnc_medicalAction;", _r get "id"]]; };
    };
    _rows pushBack ["buttons", _b];
} forEach _reqs;

};

// Suivi des troupes (état ACE lu localement)
// Suivi des troupes masqué (Réglages > Réalisme, fn_medShow) : ni liste ni moniteur.
private _showT = ["ctx", "medical"] call comspec_atak_native_fnc_medShow;
if (_tab isEqualTo "TROOPS" && {!_showT}) then {
    _rows pushBack ["text", "<t color='#8a9a93'>Suivi médical des troupes masqué (Réglages > Réalisme).</t>"];
};
if (_tab isEqualTo "TROOPS" && {_showT}) then {
private _vis = createHashMapFromArray (["state", "blood", "hr"] apply { [_x, [_x, "medical"] call comspec_atak_native_fnc_medShow] });
_rows pushBack ["section", "Suivi des troupes", ["Camp allié", "Camp allié, les plus graves en premier"] select (_vis get "state")];
private _rank = createHashMapFromArray [["cardiac_arrest", 0], ["unconscious", 1], ["critical", 2], ["wounded", 3], ["stable", 4]];
private _list = [];
{
    private _st = if (isNil "comspec_overwatch_connect_fnc_getMedicalState") then { ["stable", 100, 0, 80] } else { ([_x] call comspec_overwatch_connect_fnc_getMedicalState) splitString "|" };
    private _h = _st param [0, "stable"];
    if (!alive _x) then { _h = "kia"; };
    _list pushBack [[[_x, true] call comspec_atak_native_fnc_unitCallsign, _rank getOrDefault [_h, 5]] select (_vis get "state"), _x, _h, _st param [1, "100"], _st param [3, "80"]];
} forEach (allPlayers select { side group _x isEqualTo side group player });
_list sort true;
// Blessé suivi au moniteur : choisi dans la liste, sinon le plus grave (moi s'il n'y a personne).
private _mon = objectFromNetId (_s getOrDefault ["medMon", ""]);
if (isNull _mon) then { _mon = (_list param [0, [0, player]]) select 1; };
uiNamespace setVariable ["COMSPEC_ATAK_MedMonitor", _mon];
// Fiche du patient suivi au moniteur : groupe sanguin et poids.
([_mon] call _medIdent) params ["_btM", "_wM"];
([_btM, _wM, "medical"] call _identRow) params ["_idL", "_idV"];
_rows pushBack ["info", format ["Patient : %1", [_mon, true] call comspec_atak_native_fnc_unitCallsign], [_idV, ""] select (_idL isEqualTo "")];
{
    _x params ["", "_u", "_h", "_blood", "_hr"];
    private _lab = createHashMapFromArray [["cardiac_arrest", ["ARRÊT", "#e5483a"]], ["unconscious", ["INCONSCIENT", "#e5483a"]], ["critical", ["CRITIQUE", "#f2ab33"]], ["wounded", ["BLESSÉ", "#e8b84a"]], ["kia", ["KIA", "#8a9a93"]]] getOrDefault [_h, ["STABLE", "#5cc76b"]];
    if (!(_vis get "state") && {_h isNotEqualTo "kia"}) then { _lab = ["—", "#8a9a93"]; };
    private _vit = [];
    if (_vis get "blood") then { _vit pushBack format ["sang %1 %%", _blood]; };
    if (_vis get "hr") then { _vit pushBack format ["pouls %1", _hr]; };
    _vit append [format ["%1 m", round (player distance _u)], [getPosASL _u, 6] call comspec_atak_native_fnc_gridRef];
    _rows pushBack ["text", format ["<t color='%1' font='RobotoCondensedBold'>%2</t>  %3  <t size='0.8' color='#8a9a93'>%4</t>",
        _lab select 1, _lab select 0, [_u, true] call comspec_atak_native_fnc_unitCallsign, _vit joinString " · "]];
    _rows pushBack ["buttons", [[["MONITEUR", "● SUIVI"] select (_u isEqualTo _mon), compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['medMon', %1]; [{ ['MEDICAL'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", str netId _u], _u isEqualTo _mon]]];
} forEach _list;
if ((count _list) isEqualTo 0) then { _rows pushBack ["text", "<t color='#8a9a93'>Aucun joueur allié.</t>"]; };
};
if (_tab isEqualTo "TROOPS" && {_showT}) then {
    private _vh = _bh * 0.4;
    [[0, 0, _bw, _vh], uiNamespace getVariable ["COMSPEC_ATAK_MedMonitor", player]] call comspec_atak_native_fnc_vizEcg;
    [_rows, [0, _vh, _bw, _bh - _vh]] call comspec_atak_native_fnc_formRender;
} else {
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
};
true
