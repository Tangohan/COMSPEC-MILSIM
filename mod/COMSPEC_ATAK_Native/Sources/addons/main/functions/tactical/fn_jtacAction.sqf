/*
    Actions de l'app JTAC (contrôle aérien avancé). Déroulé d'une mission CAS, à la façon de la doctrine OTAN :
      CHECK-IN (appareil choisi, fiche lue sur l'appareil) → BRIEF (9-LINE + remarques transmis)
      → READBACK (l'équipage relit lignes 4, 6 et restrictions ; le JTAC confirme) → CORRÉLATION (CONTACT, TALLY, SPOT)
      → IN (l'appareil annonce son passage en attaque) → AUTORISÉ (CLEARED HOT / CONTINUE DRY, ou ABORT)
      → OFF (dégagement) → BDA (bilan transmis).
    Le JTAC et l'équipage ont chacun l'état de la mission (même identifiant) ; leurs appels passent par l'événement
    CBA comspec_atak_native_cas : [genre ("brief" | "call" | "resp"), id, données, indicatif, unité émettrice].
    Appareil sans joueur (ou appels passés à la radio) : le JTAC note lui-même les appels du pilote (« radio »).

      "save"                          garde la saisie (avant un redessin)     "tick" : rafraîchit le contrôle en cours
      "tab", clé | "view", clé        onglet (SIT, BRIEF, CTRL, LOG) | vue JTAC ou ÉQUIPAGE
      "code", n | "lock" | "unlock"   code laser ACE, cible verrouillée (laser ou visée), cible effacée
      "pick", "tgt" | "ip"            cible ou IP pointés sur la carte ; "picked", position (retour de la carte)
      "air", netId | "type", n | "method", t | "ord", nom | "mark", m   appareil et plan d'attaque
      "brief"                         transmet le 9-LINE à l'équipage
      "call", code                    appel du JTAC (RBC, CORRECTION, LASER_ON, LASER_OFF, MARK, HOT, DRY, ABORT, REATTACK)
      "radio", code                   appel du pilote reçu à la radio (READBACK, CONTACT, TALLY, SPOT, VISUAL, NOJOY, IN, AWAY, OFF, UNABLE)
      "bda", résultat                 bilan transmis, mission terminée      "new" : nouvelle mission
      "pilot", code                   (équipage) appel envoyé au JTAC       "map" / "pmap" : carte centrée sur la cible
      "ipAuto"                        l'IP redevient proposé automatiquement (fn_jtacLines)
      "pickIn", "tgt" | "ip"          onglet CARTE, prochain appui sur la carte tactique = cible ou IP
      "mapPick", "tgt" | "ip"         (onglet CARTE) arme ou désarme la pose ; "mapSet", [genre, position] : position posée
      "center", "tgt" | "ip" | "me" | "air"   carte tactique centrée sur la cible, l'IP, le JTAC ou l'appareil
      "recv", [genre, id, données, indicatif, unité]   réception (XEH_postInitClient)
*/
params [["_act", ""], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Jtac", _s];
private _isActive = { ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "JTAC" };
// "clearing" : saisie vidée par l'action, à ne pas reprendre des champs encore affichés pendant le redessin.
private _render = { [{
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "JTAC") then { ["JTAC"] call comspec_atak_native_fnc_pageRender; };
    (uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap]) set ["clearing", false];
}] call CBA_fnc_execNextFrame; };
private _me = { private _c = [player] call comspec_atak_native_fnc_unitCallsign; if (_c isEqualTo "") then { groupId group player } else { _c } };
private _time = { [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString };
// Directions en anglais, comme les appels radio air-sol (IN FROM THE WEST).
private _card = { params ["_deg"]; ["NORTH", "NORTHEAST", "EAST", "SOUTHEAST", "SOUTH", "SOUTHWEST", "WEST", "NORTHWEST"] select ((round (_deg / 45)) mod 8) };
// Libellés radio (brièveté OTAN, en anglais comme sur le réseau air-sol).
private _say = createHashMapFromArray [
    ["BRIEF", "9-LINE"], ["RBC", "READBACK CORRECT"], ["CORRECTION", "CORRECTION, nouveau brief"],
    ["LASER_ON", "LASER ON"], ["LASER_OFF", "LASER OFF / TERMINATE"], ["MARK", "MARQUE EN PLACE"],
    ["HOT", "CLEARED HOT"], ["ENGAGE", "CLEARED TO ENGAGE"], ["DRY", "CONTINUE DRY"], ["ABORT", "ABORT ABORT ABORT"],
    ["REATTACK", "RE-ATTACK"], ["BDA", "BDA"],
    ["READBACK", "READBACK"], ["CONTACT", "CONTACT"], ["TALLY", "TALLY"], ["SPOT", "SPOT"], ["VISUAL", "VISUAL"],
    ["NOJOY", "NO JOY"], ["IN", "IN"], ["AWAY", "WEAPONS AWAY"], ["OFF", "OFF"], ["UNABLE", "UNABLE"]
];
// Avancement d'une mission sur un appel (même règle chez le JTAC et chez l'équipage).
private _apply = {
    params ["_m", "_code"];
    private _st = _m getOrDefault ["step", 0];
    switch (_code) do {
        case "BRIEF": { _m set ["step", 1]; _m set ["rbOk", false]; _m set ["corr", []]; _m set ["aborted", false]; _m set ["away", false]; };
        case "READBACK": { _m set ["step", _st max 1]; };
        case "RBC": { _m set ["step", _st max 2]; _m set ["rbOk", true]; };
        case "CORRECTION": { _m set ["step", 1]; _m set ["rbOk", false]; };
        case "CONTACT";
        case "TALLY";
        case "SPOT";
        case "VISUAL": {
            private _c = _m getOrDefault ["corr", []];
            _c pushBackUnique _code;
            _m set ["corr", _c];
            if (_code isNotEqualTo "VISUAL" && {_st >= 2}) then { _m set ["step", _st max 3]; };
        };
        case "IN": { if (_st >= 2) then { _m set ["step", 4]; _m set ["aborted", false]; }; };
        case "HOT";
        case "ENGAGE": { _m set ["step", 5]; _m set ["dry", false]; };
        case "DRY": { _m set ["step", 5]; _m set ["dry", true]; };
        case "ABORT": { _m set ["step", [_st, 3] select (_st > 3)]; _m set ["aborted", true]; };
        case "AWAY": { _m set ["away", true]; };
        case "OFF": { if (_st >= 4) then { _m set ["step", 6]; }; };
        case "REATTACK": { _m set ["step", 3]; _m set ["away", false]; _m set ["aborted", false]; };
        case "BDA": { _m set ["step", 7]; };
        case "LASER_ON": { _m set ["laser", true]; };
        case "LASER_OFF": { _m set ["laser", false]; };
        default {};
    };
};
private _log = {
    params ["_m", "_who", "_text"];
    private _l = _m getOrDefault ["log", []];
    _l = [[[] call _time, _who, _text]] + _l;
    _m set ["log", _l select [0, 60]];
};
// Envoi à des joueurs (débit simulé du téléphone).
private _emit = {
    params ["_to", "_payload"];
    _to = _to select { !isNull _x && {isPlayer _x} };
    if ((count _to) isEqualTo 0) exitWith { false };
    [{ params ["_to", "_payload"]; ["comspec_atak_native_cas", _payload, _to] call CBA_fnc_targetEvent; }, [_to, _payload], "JTAC", 1] call comspec_atak_native_fnc_netSend;
    true
};
private _save = {
    if (_s getOrDefault ["clearing", false]) exitWith {};
    private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
    {
        private _c = _form getOrDefault [_x, controlNull];
        // Proposition automatique laissée telle quelle : pas une saisie (elle suit la cible si celle-ci change).
        if (!isNull _c) then {
            private _v = [_x] call comspec_atak_native_fnc_formValue;
            if ((trim _v) isEqualTo (_s getOrDefault ["auto_" + _x, ""])) then { _v = ""; };
            _s set [_x, _v];
        };
    } forEach ["jIp", "j5", "j9", "jFah", "jThreat", "jDc", "jBda"];
};
// Rafraîchissement (XEH_postInitClient, 3 s) : seulement les vues sans saisie, mission en cours.
if (_act isEqualTo "tick") exitWith {
    if (isNull ([] call comspec_atak_native_fnc_display) || {!(call _isActive)}) exitWith { false };
    private _pilot = (count (uiNamespace getVariable ["COMSPEC_ATAK_JtacPilot", createHashMap])) > 0 && {(_s getOrDefault ["view", "JTAC"]) isEqualTo "PILOT"};
    private _st = (_s getOrDefault ["msn", createHashMap]) getOrDefault ["step", 0];
    if (_pilot || {(_s getOrDefault ["tab", "SIT"]) isEqualTo "CTRL" && {_st >= 1} && {_st < 6}}) then { ["JTAC"] call comspec_atak_native_fnc_pageRender; };
    true
};
if (_act isNotEqualTo "recv") then { call _save; };

switch (_act) do {
    case "save": {};
    case "tab": { _s set ["tab", _arg]; call _render; };
    case "view": { _s set ["view", _arg]; call _render; };
    case "code": { player setVariable ["ace_laser_code", _arg, true]; ["INFO", format ["Code laser %1", _arg], 2, 10] call comspec_atak_native_fnc_notify; call _render; };
    case "lock": {
        private _t = [] call comspec_atak_native_fnc_jtacTarget;
        if ((count _t) isEqualTo 0) exitWith { ["WARNING", "Aucune cible : visez un point à moins de 3 km", 3, 20] call comspec_atak_native_fnc_notify; };
        _s set ["target", _t select 0];
        _s set ["source", format ["%1 · %2", _t select 1, [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
        ["SUCCESS", format ["Cible verrouillée : %1", [_t select 0, 10] call comspec_atak_native_fnc_gridRef], 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "unlock": { _s set ["target", []]; call _render; };
    case "ipAuto": { _s set ["ipAuto", true]; _s set ["jIp", ""]; call _render; };
    case "pickIn": {
        _s set ["mapPick", _arg];
        _s set ["pickBack", _s getOrDefault ["tab", "SIT"]];
        _s set ["tab", "MAP"];
        call _render;
    };
    case "mapPick": {
        _s set ["mapPick", ["", _arg] select ((_s getOrDefault ["mapPick", ""]) isNotEqualTo _arg)];
        _s set ["pickBack", ""];
        call _render;
    };
    case "mapSet": {
        _arg params ["_kind", "_pos"];
        private _asl = [_pos select 0, _pos select 1, getTerrainHeightASL _pos];
        if (_kind isEqualTo "tgt") then {
            _s set ["target", _asl];
            _s set ["source", format ["carte · %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
        } else {
            _s set ["ip", _asl];
            _s set ["ipAuto", false];
            if ((_s getOrDefault ["jIp", ""]) isEqualTo "") then { _s set ["jIp", format ["IP %1", [_asl, 6] call comspec_atak_native_fnc_gridRef]]; };
        };
        _s set ["mapPick", ""];
        // Pose demandée depuis un autre onglet : on y revient.
        private _back = _s getOrDefault ["pickBack", ""];
        if (_back isNotEqualTo "") then { _s set ["tab", _back]; _s set ["pickBack", ""]; };
        ["SUCCESS", [format ["IP posé : %1", [_asl, 6] call comspec_atak_native_fnc_gridRef], format ["Cible posée : %1", [_asl, 10] call comspec_atak_native_fnc_gridRef]] select (_kind isEqualTo "tgt"), 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "center": {
        private _map = uiNamespace getVariable ["COMSPEC_ATAK_JtacMap", controlNull];
        if (isNull _map) exitWith {};
        private _m = _s getOrDefault ["msn", createHashMap];
        private _pos = switch (_arg) do {
            case "tgt": { ((_m getOrDefault ["brief", createHashMap]) getOrDefault ["target", _s getOrDefault ["target", []]]) };
            case "ip": { _s getOrDefault ["ip", []] };
            case "air": { private _a = _m getOrDefault ["air", _s getOrDefault ["air", objNull]]; [getPosASL _a, []] select (isNull _a) };
            default { getPosASL player };
        };
        if ((count _pos) < 2) exitWith {};
        _map ctrlMapAnimAdd [0.3, ctrlMapScale _map, [_pos select 0, _pos select 1]];
        ctrlMapAnimCommit _map;
    };
    case "pick": {
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _st set ["jtacPick", _arg];
        [{ ["MAP"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        ["INFO", ["Cliquez l'IP sur la carte", "Cliquez la cible sur la carte"] select (_arg isEqualTo "tgt"), 3, 40] call comspec_atak_native_fnc_notify;
    };
    case "picked": {
        _arg params ["_kind", "_pos"];
        private _asl = [_pos select 0, _pos select 1, getTerrainHeightASL _pos];
        if (_kind isEqualTo "tgt") then {
            _s set ["target", _asl];
            _s set ["source", format ["carte · %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
        } else {
            _s set ["ip", _asl];
            _s set ["ipAuto", false];
            if ((_s getOrDefault ["jIp", ""]) isEqualTo "") then { _s set ["jIp", format ["IP %1", [_asl, 6] call comspec_atak_native_fnc_gridRef]]; };
            _s set ["tab", "BRIEF"];
        };
        [{ ["JTAC"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    };
    case "air": {
        private _a = _arg call BIS_fnc_objectFromNetId;
        _s set ["air", _a];
        _s set ["airAuto", false];
        _s set ["ord", ""];
        // Nouvel appareil : la mission en cours (autre appareil) est close.
        private _m = _s getOrDefault ["msn", createHashMap];
        if ((count _m) > 0 && {(_m getOrDefault ["air", objNull]) isNotEqualTo _a}) then { _s set ["msn", createHashMap]; };
        call _render;
    };
    case "type": { _s set ["type", _arg]; call _render; };
    case "method": { _s set ["method", _arg]; call _render; };
    case "ord": { _s set ["ord", _arg]; call _render; };
    case "mark": { _s set ["mark", _arg]; call _render; };
    case "map": {
        private _p = _s getOrDefault ["target", []];
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.03] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;
    };
    case "brief": {
        private _air = _s getOrDefault ["air", objNull];
        private _b = [] call comspec_atak_native_fnc_jtacLines;
        if (isNull _air || {!alive _air}) exitWith { ["WARNING", "Choisissez d'abord un appareil (onglet SITUATION)", 3, 30] call comspec_atak_native_fnc_notify; };
        if ((_b getOrDefault ["missing", ""]) isNotEqualTo "") exitWith { ["WARNING", format ["Brief incomplet : %1", _b get "missing"], 4, 30] call comspec_atak_native_fnc_notify; };
        private _m = _s getOrDefault ["msn", createHashMap];
        // Même appareil : nouveau brief de la même mission (correction) ; sinon nouvelle mission.
        if ((count _m) isEqualTo 0 || {(_m getOrDefault ["step", 0]) >= 7}) then {
            _m = createHashMapFromArray [["id", format ["%1-%2", getPlayerUID player, round (diag_tickTime * 1000)]], ["air", _air], ["log", []]];
            _s set ["msn", _m];
        };
        _m set ["air", _air];
        _m set ["brief", _b];
        _m set ["readback", ""];
        [_m, "BRIEF"] call _apply;
        private _lines = _b get "lines";
        // IP et distance de risque en fin de liste : la carte tactique de l'équipage les dessine (un ancien client les ignore).
        private _data = [_lines, _b get "remarks", _b get "type", _b get "method", _b get "ord", _b get "code", _b get "target", _b get "fah", _b get "dc", _s getOrDefault ["ip", []], _b get "risk"];
        private _sent = [(crew _air), ["brief", _m get "id", _data, [] call _me, player]] call _emit;
        [_m, "JTAC", format ["9-LINE pour %1 : %2 | %3 | %4", groupId group _air, _lines select 3, _lines select 5, _lines select 6]] call _log;
        // Athena (pilotes et web) quand le téléphone est relié : même chemin que l'app CAS.
        if ([] call comspec_atak_native_fnc_bridge) then {
            private _f = [] call comspec_atak_native_fnc_firesState;
            { _f set [format ["n%1", _forEachIndex + 1], _x]; } forEach _lines;
            _f set ["nr", _b get "remarks"];
            ["send9"] spawn comspec_atak_native_fnc_firesAction; // attente de la DLL sans figer le jeu
        };
        [["INFO", "SUCCESS"] select _sent, [format ["9-LINE à %1 : équipage IA, transmettez-le à la radio", groupId group _air], format ["9-LINE transmis à %1, attente du readback", groupId group _air]] select _sent, 4, 40] call comspec_atak_native_fnc_notify;
        _s set ["tab", "CTRL"];
        call _render;
    };
    case "call": {
        private _m = _s getOrDefault ["msn", createHashMap];
        if ((count _m) isEqualTo 0) exitWith { ["WARNING", "Aucune mission : transmettez d'abord le 9-LINE", 3, 30] call comspec_atak_native_fnc_notify; };
        private _air = _m getOrDefault ["air", objNull];
        if (_arg isEqualTo "LASER_ON") then {
            private _t = [] call comspec_atak_native_fnc_jtacTarget;
            if ((count _t) isEqualTo 0 || {(_t select 1) isNotEqualTo "laser"}) then { ["WARNING", "Votre désignateur n'est pas allumé : LASER ON annoncé quand même", 4, 30] call comspec_atak_native_fnc_notify; };
        };
        private _code = _arg;
        if (_code isEqualTo "HOT" && {(_s getOrDefault ["type", 2]) isEqualTo 3}) then { _code = "ENGAGE"; };
        private _text = _say getOrDefault [_code, _code];
        if (_code isEqualTo "HOT") then { _text = format ["%1, %2", groupId group _air, _text]; };
        if (_code isEqualTo "LASER_ON") then { _text = format ["LASER ON, code %1", player getVariable ["ace_laser_code", 1111]]; };
        [_m, _code] call _apply;
        [_m, "JTAC", _text] call _log;
        if (_code isEqualTo "CORRECTION") exitWith { _s set ["tab", "BRIEF"]; call _render; };
        [(crew _air), ["call", _m get "id", [_code, _text], [] call _me, player]] call _emit;
        [["INFO", "WARNING"] select (_code isEqualTo "ABORT"), _text, 3, 40] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "radio": {
        // Appel du pilote entendu à la radio (appareil IA ou équipage sans téléphone).
        private _m = _s getOrDefault ["msn", createHashMap];
        if ((count _m) isEqualTo 0) exitWith {};
        private _air = _m getOrDefault ["air", objNull];
        private _text = _say getOrDefault [_arg, _arg];
        if (_arg isEqualTo "IN") then { _text = format ["IN FROM THE %1", [(_m getOrDefault ["brief", createHashMap]) getOrDefault ["target", getPosASL _air] getDir _air] call _card]; };
        if (_arg isEqualTo "READBACK") then { _m set ["readback", "(à la radio)"]; };
        if (_arg isEqualTo "IN") then { _m set ["inCheck", [_air, _m] call comspec_atak_native_fnc_jtacGeometry]; };
        [_m, _arg] call _apply;
        [_m, format ["%1 (radio)", groupId group _air], _text] call _log;
        call _render;
    };
    case "bda": {
        private _m = _s getOrDefault ["msn", createHashMap];
        if ((count _m) isEqualTo 0) exitWith {};
        private _air = _m getOrDefault ["air", objNull];
        private _txt = trim (_s getOrDefault ["jBda", ""]);
        private _text = format ["BDA : %1%2", _arg, ["", format [", %1", _txt]] select (_txt isNotEqualTo "")];
        [_m, "BDA"] call _apply;
        [_m, "JTAC", _text] call _log;
        [(crew _air), ["call", _m get "id", ["BDA", _text], [] call _me, player]] call _emit;
        _s set ["jBda", ""];
        _s set ["clearing", true];
        ["SUCCESS", _text, 3, 30] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "new": {
        _s set ["msn", createHashMap];
        { _s deleteAt _x; } forEach ["j5", "j9", "jFah", "jThreat", "jDc", "jBda", "ord", "jIp", "ip"];
        _s set ["ipAuto", true];
        _s set ["clearing", true];
        _s set ["tab", "SIT"];
        call _render;
    };
    case "pilot": {
        // Équipage : appel envoyé au JTAC qui a transmis le brief.
        private _p = uiNamespace getVariable ["COMSPEC_ATAK_JtacPilot", createHashMap];
        if ((count _p) isEqualTo 0) exitWith {};
        private _text = _say getOrDefault [_arg, _arg];
        private _b = _p getOrDefault ["data", []];
        _b params [["_lines", []], "", "", "", "", "", ["_tpos", []], ["_fah", ""]];
        if (_arg isEqualTo "READBACK") then { _text = format ["READBACK : %1, %2%3", _lines param [3, "-"], _lines param [5, "-"], ["", format [", FAH %1", _fah]] select (_fah isNotEqualTo "")]; };
        if (_arg isEqualTo "IN" && {(count _tpos) >= 2}) then { _text = format ["IN FROM THE %1, cap %2°", [_tpos getDir (vehicle player)] call _card, round getDir (vehicle player)]; };
        if (_arg isEqualTo "OFF" && {(count _tpos) >= 2}) then { _text = format ["OFF %1", [_tpos getDir (vehicle player)] call _card]; };
        [_p, _arg] call _apply;
        [_p, [] call _me, _text] call _log;
        [[_p getOrDefault ["jtac", objNull]], ["resp", _p get "id", [_arg, _text, vehicle player], [] call _me, player]] call _emit;
        call _render;
    };
    case "pmap": {
        private _b = (uiNamespace getVariable ["COMSPEC_ATAK_JtacPilot", createHashMap]) getOrDefault ["data", []];
        private _p = _b param [6, []];
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.03] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call CBA_fnc_execNextFrame;
    };
    case "recv": {
        _arg params [["_kind", ""], ["_id", ""], ["_data", []], ["_from", ""], ["_unit", objNull]];
        if (!hasInterface) exitWith {};
        switch (_kind) do {
            case "brief": {
                // Équipage : nouveau brief (ou correction du même).
                private _p = uiNamespace getVariable ["COMSPEC_ATAK_JtacPilot", createHashMap];
                if ((_p getOrDefault ["id", ""]) isNotEqualTo _id) then {
                    _p = createHashMapFromArray [["id", _id], ["log", []]];
                    uiNamespace setVariable ["COMSPEC_ATAK_JtacPilot", _p];
                };
                _p set ["jtac", _unit];
                _p set ["jtacName", _from];
                _p set ["data", _data];
                [_p, "BRIEF"] call _apply;
                [_p, _from, format ["9-LINE : %1 | %2", (_data select 0) param [3, ""], (_data select 0) param [5, ""]]] call _log;
                _s set ["view", "PILOT"];
                ["WARNING", format ["CAS : 9-LINE de %1 · cible %2", _from, (_data select 0) param [5, ""]], 10, 85] call comspec_atak_native_fnc_notify;
                [] call comspec_atak_native_fnc_vibrate;
                if (call _isActive) then { call _render; };
            };
            case "call": {
                private _p = uiNamespace getVariable ["COMSPEC_ATAK_JtacPilot", createHashMap];
                if ((_p getOrDefault ["id", ""]) isNotEqualTo _id) exitWith {};
                _data params [["_code", ""], ["_text", ""]];
                [_p, _code] call _apply;
                [_p, _from, _text] call _log;
                private _kindN = switch (_code) do { case "ABORT": { "ERROR" }; case "HOT"; case "ENGAGE": { "SUCCESS" }; default { "TACTICAL" } };
                [_kindN, format ["%1 : %2", _from, _text], [5, 10] select (_code in ["ABORT", "HOT", "ENGAGE"]), 85] call comspec_atak_native_fnc_notify;
                if (_code in ["ABORT", "HOT", "ENGAGE", "RBC"]) then { [] call comspec_atak_native_fnc_vibrate; };
                if (call _isActive) then { call _render; };
            };
            case "resp": {
                private _m = _s getOrDefault ["msn", createHashMap];
                if ((_m getOrDefault ["id", ""]) isNotEqualTo _id) exitWith {};
                _data params [["_code", ""], ["_text", ""], ["_veh", objNull]];
                if (_code isEqualTo "READBACK") then { _m set ["readback", _text]; };
                if (_code isEqualTo "IN") then { _m set ["inCheck", [_m getOrDefault ["air", _veh], _m] call comspec_atak_native_fnc_jtacGeometry]; };
                [_m, _code] call _apply;
                [_m, _from, _text] call _log;
                [["TACTICAL", "WARNING"] select (_code in ["UNABLE", "NOJOY"]), format ["%1 : %2", _from, _text], 6, 85] call comspec_atak_native_fnc_notify;
                if (_code in ["READBACK", "IN", "UNABLE"]) then { [] call comspec_atak_native_fnc_vibrate; };
                if (call _isActive) then { call _render; };
            };
        };
    };
};
true
