/*
    Briefs air : 9-LINE et 5-LINE envoyés aux pilotes par un JTAC (app JTAC en jeu ou page Athena /9-line), lus dans
    l'espace BRIEFS AIR de l'app JTAC du pilote, avec accusé (REÇU, LU, WILCO, UNABLE) renvoyé à l'émetteur.
      - en jeu : événement CBA comspec_atak_native_airBrief [genre ("brief" | "ack"), ref, données, indicatif, unité],
        envoyé par fn_netSend (liaison réaliste : file hors réseau, priorité JTAC / accusé) ;
      - Athena (COMSPEC Link, sans nouvelle fonction DLL) : SubmitNineLine (POST /api/cas : brief et destinataires),
        GetCASForCallsign ["~air|nom|nom...", carte] (boîte du pilote, tableau SQF), SendCASState [ref, "STATUT:indicatif"].
    Un brief est identifié par sa ref (choisie par le jeu, reprise par Athena en mission_id ; brief rédigé sur le web :
    son numéro Athena) : reçu deux fois (jeu puis Athena), il n'apparaît qu'une fois.
    Boîte du pilote : missionNamespace COMSPEC_ATAK_AirInbox (le plus récent d'abord) ; envois du JTAC (suivi) :
    COMSPEC_ATAK_AirSent. Rien n'est calculé par image : "tick" (toutes les 4 s, XEH_postInitClient) décide du relevé de la
    boîte Athena : toutes les 15 s à bord d'un aéronef, toutes les 45 s pour un pilote à pied (rôle pilote, sorti d'un
    aéronef depuis moins de 30 min, ou app JTAC ouverte), et tout de suite (5 s d'écart au moins) au réveil du téléphone,
    à l'ouverture de BRIEFS AIR ou au retour de la liaison. Indicatifs comparés par leur clé ("key" : casse, accents,
    espaces, tirets ignorés : « TIGER 6 » = « Tiger-6 » = « TIGER6 »), même règle qu'Athena (AirBriefService::callsignKey).
    Liaison de bord ("airLink") : à un poste d'équipage d'un aéronef (tablette de vol, fn_vehicleTerminal "air"), le brief
    air-sol passe par la liaison de données de l'appareil : ni retenu hors réseau (relais hors de portée en vol), ni la
    boîte Athena suspendue.
    Params : [action, argument]
      "send", [format ("9" | "5"), lignes, remarques, destinataires [[indicatif, véhicule ou unité]...], ref, libre]  (JTAC)
      "track", [ref, format, résumé, [indicatifs]]   suivi d'un envoi fait ailleurs (mission CAS de l'app JTAC)
      "recv", [genre, ref, données, indicatif, unité]   réception de l'événement CBA
      "store", [ref, id Athena, format, émetteur, unité, heure, lignes, remarques, destinataire, source, statut]
      "poll", forcé (bool)       boîte Athena du pilote (forcé : relance 3 s après, la DLL rend le relevé précédent)
      "tick"                     toutes les 4 s : relève la boîte quand il le faut (voir plus haut)
      "names" → noms interrogés (indicatif de l'appareil, dernier indicatif d'appareil, indicatif Athena, indicatif ATAK,
                indicatif du groupe, nom du joueur ; 8 au plus, sans doublon de clé)
      "key", texte → clé de comparaison d'un indicatif ; "airLink" → liaison de bord disponible
      "why" → "" si la boîte peut être relevée, sinon la raison ; "diag" → ligne de diagnostic de BRIEFS AIR
      "ack", [ref, statut]       (pilote) WILCO | UNABLE | READ ; "del", ref ; "readAll" ; "delAll"
      "jtacCs" → indicatif JTAC (profil, sinon indicatif ATAK) ; "setJtacCs", texte
      "airCs", véhicule → indicatif de l'aéronef ; "setAirCs", texte (aéronef du joueur, partagé et remonté à Athena)
      "airs", joueurs uniquement → [[véhicule, indicatif, noms des joueurs à bord, distance]...] aéronefs alliés avec équipage
      "unread" → nombre de briefs non lus ; "label", statut → libellé affiché (ENVOYÉ, REÇU, LU, WILCO, UNABLE)
*/
params [["_act", ""], ["_arg", []]];
private _inboxKey = "COMSPEC_ATAK_AirInbox";
private _sentKey = "COMSPEC_ATAK_AirSent";
private _hhmm = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
private _rank = { params ["_st"]; (createHashMapFromArray [["SENT", 0], ["RECEIVED", 1], ["READ", 2], ["WILCO", 3], ["UNABLE", 3], ["DONE", 4]]) getOrDefault [toUpper _st, 0] };
// Texte sans « | » (séparateur des appels DLL) ni retour à la ligne.
private _clean = { params ["_t", ["_max", 60]]; if !(_t isEqualType "") then { _t = str _t; }; private _o = ((trim ((_t splitString "|") joinString "/")) splitString (toString [10, 13])) joinString " "; _o select [0, _max] };
private _airCs = {
    params ["_v"];
    if (isNull _v) exitWith { "" };
    private _take = { params ["_k"]; private _c = _v getVariable [_k, ""]; if !(_c isEqualType "") then { _c = str _c; }; trim _c };
    private _c = ["COMSPEC_ATAK_AirCs"] call _take;
    if (_c isEqualTo "") then { _c = ["COMSPEC_Callsign"] call _take; };
    if (_c isEqualTo "") then { _c = ["COMSPEC_GpsCallsign"] call _take; };
    if (_c isEqualTo "" || {(toLower _c) in ["unknown", "inconnu", "<null>", "any"]}) then { _c = trim (groupId group (effectiveCommander _v)); };
    _c
};
// Indicatif sous lequel je reçois : celui de mon aéronef, sinon le mien.
private _myLabel = {
    private _v = vehicle player;
    if (_v isNotEqualTo player && {_v isKindOf "Air"}) exitWith { [_v] call _airCs };
    [player] call comspec_atak_native_fnc_unitCallsign
};
// Clé d'un indicatif : minuscules, accents ramenés, lettres et chiffres seulement (AirBriefService::callsignKey d'Athena).
private _key = {
    params [["_t", ""]];
    if !(_t isEqualType "") then { _t = str _t; };
    private _acc = missionNamespace getVariable "COMSPEC_ATAK_CsKeyAcc";
    if (isNil "_acc") then {
        _acc = createHashMapFromArray [[224, 97], [225, 97], [226, 97], [228, 97], [231, 99], [232, 101], [233, 101], [234, 101], [235, 101],
            [236, 105], [237, 105], [238, 105], [239, 105], [241, 110], [242, 111], [243, 111], [244, 111], [246, 111], [249, 117], [250, 117], [251, 117], [252, 117]];
        missionNamespace setVariable ["COMSPEC_ATAK_CsKeyAcc", _acc];
    };
    toString (((toArray (toLower _t)) apply { _acc getOrDefault [_x, _x] }) select { (_x >= 48 && {_x <= 57}) || {_x >= 97 && {_x <= 122}} })
};
// Rôles génériques du jeu : jamais interrogés comme indicatif.
private _generic = ["operateur", "pilote", "pilot", "unknown", "inconnu", "any", "null", "jtac"];
// Liaison de bord : poste d'équipage d'un aéronef (tablette de vol).
private _airLink = { ((vehicle player) isKindOf "Air") && {([player] call comspec_atak_native_fnc_vehicleTerminal) isEqualTo "air"} };
// Raison pour laquelle la boîte Athena ne peut pas être relevée ("" : elle peut l'être).
private _whyNot = {
    if (!([player] call comspec_atak_native_fnc_hasDevice) && {!(call _airLink)}) exitWith { "pas de téléphone" };
    if !([] call comspec_atak_native_fnc_bridge) exitWith { "COMSPEC Overwatch absent" };
    if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { "Athena non relié" };
    if (!(call _airLink) && {!(["online"] call comspec_atak_native_fnc_offline)}) exitWith { "hors réseau" };
    ""
};
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "JTAC") then { ["JTAC"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
private _fmtName = { params ["_f"]; (createHashMapFromArray [["5", "5-LINE"], ["M", "MEDEVAC"]]) getOrDefault [_f, "9-LINE"] };
// Accusé du pilote : à l'émetteur en jeu (s'il est encore là) et à Athena, par la liaison du téléphone.
private _ackOut = {
    params ["_b", "_status"];
    private _ref = _b getOrDefault ["ref", ""];
    if (_ref isEqualTo "") exitWith {};
    private _to = _b getOrDefault ["to", ""];
    if (_to isEqualTo "") then { _to = call _myLabel; };
    private _unit = _b getOrDefault ["unit", objNull];
    private _ath = [] call comspec_atak_native_fnc_bridge;
    [{
        params ["_unit", "_ref", "_status", "_to", "_ath"];
        if (!isNull _unit && {isPlayer _unit} && {_unit isNotEqualTo player}) then { ["comspec_atak_native_airBrief", ["ack", _ref, [_status, _to], _to, player], _unit] call CBA_fnc_targetEvent; };
        if (_ath && {missionNamespace getVariable ["COMSPEC_AthenaReady", false]}) then { (["COMSPECExtension", "SendCASState", [_ref, format ["%1:%2", _status, _to]]] call comspec_overwatch_connect_fnc_extDirect); };
    }, [_unit, _ref, _status, _to, _ath], format ["Accusé brief air %1", _status], 0.2] call comspec_atak_native_fnc_netSend;
};

switch (_act) do {
    case "jtacCs": {
        private _c = profileNamespace getVariable ["COMSPEC_ATAK_JtacCs", ""];
        if !(_c isEqualType "") then { _c = ""; };
        _c = trim _c;
        if (_c isEqualTo "") then { _c = [player] call comspec_atak_native_fnc_unitCallsign; };
        if (_c isEqualTo "") then { _c = "JTAC"; };
        _c
    };
    case "setJtacCs": {
        private _c = toUpper ([_arg, 24] call _clean);
        profileNamespace setVariable ["COMSPEC_ATAK_JtacCs", _c];
        [] call comspec_atak_native_fnc_saveProfile;
        ["SUCCESS", format ["Indicatif JTAC : %1", ["jtacCs"] call comspec_atak_native_fnc_airBrief], 3, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "airCs": { [_arg] call _airCs };
    case "setAirCs": {
        private _v = vehicle player;
        if (_v isEqualTo player || {!(_v isKindOf "Air")}) exitWith { ["WARNING", "Montez à bord d'un aéronef pour lui donner un indicatif", 3, 20] call comspec_atak_native_fnc_notify; false };
        private _c = toUpper ([_arg, 24] call _clean);
        if (_c isEqualTo "") then {
            _v setVariable ["COMSPEC_ATAK_AirCs", nil, true];
        } else {
            // Partagé (tous les joueurs) et repris par fn_reportCrewedAirAssets : Athena et le web affichent le même.
            _v setVariable ["COMSPEC_ATAK_AirCs", _c, true];
            _v setVariable ["COMSPEC_Callsign", _c, true];
            profileNamespace setVariable ["COMSPEC_ATAK_AirCsLast", _c];
            [] call comspec_atak_native_fnc_saveProfile;
        };
        ["SUCCESS", format ["Indicatif de l'appareil : %1", [_v] call _airCs], 3, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "airs": {
        private _side = side group player;
        private _l = vehicles select { _x isKindOf "Air" && {alive _x} && {(count crew _x) > 0} && {side group (effectiveCommander _x) isEqualTo _side} && {!(_x isKindOf "UAV_01_base_F")} && {(_x distance2D player) < 40000} };
        if (_arg isEqualTo true) then { _l = _l select { ((crew _x) findIf { isPlayer _x }) >= 0 }; };
        _l = _l apply { [_x distance2D player, _x] };
        _l sort true;
        _l apply { _x params ["_d", "_v"]; [_v, [_v] call _airCs, ((crew _v) select { isPlayer _x }) apply { name _x }, _d] }
    };
    case "unread": { { !(_x getOrDefault ["read", false]) } count (missionNamespace getVariable [_inboxKey, []]) };
    case "label": { (createHashMapFromArray [["SENT", "ENVOYÉ"], ["RECEIVED", "REÇU"], ["READ", "LU"], ["WILCO", "WILCO"], ["UNABLE", "UNABLE"], ["DONE", "CLÔTURÉ"]]) getOrDefault [toUpper _arg, toUpper _arg] };

    case "track": {
        _arg params [["_ref", ""], ["_fmt", "9"], ["_sum", ""], ["_labels", []]];
        private _sent = missionNamespace getVariable [_sentKey, []];
        private _i = _sent findIf { (_x get "ref") isEqualTo _ref };
        private _e = if (_i >= 0) then { _sent deleteAt _i } else { createHashMapFromArray [["ref", _ref], ["dest", createHashMap]] };
        _e set ["fmt", _fmt];
        _e set ["at", [] call _hhmm];
        _e set ["sum", _sum];
        private _d = _e get "dest";
        { _d set [_x, "SENT"]; } forEach _labels;
        _sent = [_e] + _sent;
        missionNamespace setVariable [_sentKey, _sent select [0, 10]];
        _ref
    };
    case "send": {
        _arg params [["_fmt", "9"], ["_lines", []], ["_rem", ""], ["_dest", []], ["_ref", ""], ["_free", false]];
        // Id sûr ([A-Za-z0-9-]) : un nombre >= 1e6 passé à format donne « 6.35867e+06 », rejeté en 404 par Athena.
        if (_ref isEqualTo "") then { _ref = format ["CAS-%1-%2", (getPlayerUID player) select [9], (round (diag_tickTime * 1000)) toFixed 0]; };
        private _from = ["jtacCs"] call comspec_atak_native_fnc_airBrief;
        private _labels = [];
        private _units = [];
        {
            _x params [["_lbl", ""], ["_who", objNull]];
            _lbl = [_lbl, 40] call _clean;
            if (_lbl isNotEqualTo "") then {
                _labels pushBackUnique _lbl;
                // Véhicule : les joueurs à bord ; unité à pied : elle-même (crew d'une unité la renvoie).
                if (!isNull _who) then { { if (isPlayer _x && {_x isNotEqualTo player}) then { _units pushBack [_x, _lbl]; }; } forEach (crew _who); };
            };
        } forEach _dest;
        if ((count _labels) isEqualTo 0) exitWith { ["WARNING", "Choisissez au moins un destinataire (appareil ou indicatif)", 3, 30] call comspec_atak_native_fnc_notify; "" };
        private _sum = format ["%1 | %2", _lines param [[5, 2] select (_fmt isEqualTo "5"), "-"], _lines param [[4, 3] select (_fmt isEqualTo "5"), "-"]];
        ["track", [_ref, _fmt, _sum, _labels]] call comspec_atak_native_fnc_airBrief;
        {
            _x params ["_u", "_lbl"];
            [{ params ["_u", "_p"]; ["comspec_atak_native_airBrief", _p, _u] call CBA_fnc_targetEvent; }, [_u, ["brief", _ref, [_fmt, _lines, _rem, _lbl], _from, player]], format ["JTAC %1 pour %2", [_fmt] call _fmtName, _lbl], 1] call comspec_atak_native_fnc_netSend;
        } forEach _units;
        // Athena (page /9-line, pilotes reliés hors de portée du jeu) : même ref, destinataires par indicatif.
        if ([] call comspec_atak_native_fnc_bridge) then {
            private _esc = { params ["_t"]; [[[_t, "\", "\\"] call CBA_fnc_replace, """", "\"""] call CBA_fnc_replace, toString [10], " "] call CBA_fnc_replace };
            private _mapId = missionNamespace getVariable ["COMSPEC_MapId", missionNamespace getVariable ["comspec_overwatch_map_id", 1]];
            private _lj = [];
            { _lj pushBack format ['"line%1":"%2"', _forEachIndex + 1, [_x] call _esc]; } forEach _lines;
            private _json = format ['{"mapId":%1,"author":"%2","jtac":"%2","mission_kind":"%3","format":"%4","free":%5,"mission_id":"%6","remarks":"%7","lines":{%8},"recipients":[%9]}',
                _mapId, [_from] call _esc, ["CAS", "5LINE"] select (_fmt isEqualTo "5"), ["9line", "5line"] select (_fmt isEqualTo "5"), ["false", "true"] select _free, _ref, [_rem] call _esc,
                _lj joinString ",",
                (_labels apply { format ['"%1"', [_x] call _esc] }) joinString ","];
            [{ _this spawn { params ["_json"]; (["COMSPECExtension", "SubmitNineLine", [_json]] call comspec_atak_native_fnc_extAwait); }; }, [_json], format ["JTAC %1 vers Athena", [_fmt] call _fmtName], 1] call comspec_atak_native_fnc_netSend;
        };
        ["SUCCESS", format ["%1 envoyé à %2", [_fmt] call _fmtName, _labels joinString ", "], 4, 40] call comspec_atak_native_fnc_notify;
        _ref
    };

    case "recv": {
        _arg params [["_kind", ""], ["_ref", ""], ["_data", []], ["_from", ""], ["_unit", objNull]];
        if (!hasInterface) exitWith {};
        switch (_kind) do {
            case "brief": {
                _data params [["_fmt", "9"], ["_lines", []], ["_rem", ""], ["_to", ""]];
                ["store", [_ref, 0, _fmt, _from, _unit, [] call _hhmm, _lines, _rem, _to, "game", ""]] call comspec_atak_native_fnc_airBrief;
            };
            case "ack": {
                _data params [["_st", ""], ["_to", ""]];
                private _sent = missionNamespace getVariable [_sentKey, []];
                private _e = _sent select { (_x get "ref") isEqualTo _ref } param [0, createHashMap];
                if ((count _e) isEqualTo 0) exitWith {};
                private _d = _e get "dest";
                if (([_st] call _rank) < ([_d getOrDefault [_to, "SENT"]] call _rank)) exitWith {};
                _d set [_to, toUpper _st];
                // Mission clôturée par le pilote : demande FIRES court terminée, son marqueur est retiré (fn_firesQuick).
                if ((toUpper _st) isEqualTo "DONE") then { ["closed", _ref] call comspec_atak_native_fnc_firesQuick; };
                if ((toUpper _st) in ["WILCO", "UNABLE", "DONE"]) then {
                    [["TACTICAL", "WARNING"] select ((toUpper _st) isEqualTo "UNABLE"), format ["%1 : %2 (%3 de %4)", _to, toUpper _st, [_e get "fmt"] call _fmtName, _e get "at"], 6, 85, "JTAC"] call comspec_atak_native_fnc_notify;
                    [] call comspec_atak_native_fnc_vibrate;
                };
                // Relais vers Athena (page /9-line du JTAC) : le pilote n'est pas forcément relié lui-même.
                if ([] call comspec_atak_native_fnc_bridge && {missionNamespace getVariable ["COMSPEC_AthenaReady", false]}) then {
                    (["COMSPECExtension", "SendCASState", [_ref, format ["%1:%2", toUpper _st, _to]]] call comspec_overwatch_connect_fnc_extDirect);
                };
                call _render;
            };
            // Demande FIRES court close par son auteur : plus rien à faire pour le pilote.
            case "close": {
                private _b = (missionNamespace getVariable [_inboxKey, []]) select { (_x get "ref") isEqualTo _ref } param [0, createHashMap];
                if ((count _b) isEqualTo 0) exitWith {};
                _b set ["ack", "DONE"];
                _b set ["read", true];
                ["INFO", format ["Demande de %1 close", _from], 4, 40] call comspec_atak_native_fnc_notify;
                call _render;
            };
        };
    };
    case "store": {
        _arg params [["_ref", ""], ["_aid", 0], ["_fmt", "9"], ["_from", ""], ["_unit", objNull], ["_at", ""], ["_lines", []], ["_rem", ""], ["_to", ""], ["_src", "game"], ["_status", ""]];
        if (_ref isEqualTo "" && {_aid > 0}) then { _ref = str _aid; };
        if (_ref isEqualTo "") exitWith { false };
        if (_ref in (missionNamespace getVariable ["COMSPEC_ATAK_AirGone", []])) exitWith { false };
        private _in = missionNamespace getVariable [_inboxKey, []];
        private _i = _in findIf { (_x get "ref") isEqualTo _ref };
        if (_i >= 0) exitWith {
            // Déjà reçu (jeu puis Athena) : on complète ; nouveau brief du même JTAC (correction) : relu.
            private _b = _in select _i;
            if (_aid > 0) then { _b set ["aid", _aid]; };
            if (!isNull _unit) then { _b set ["unit", _unit]; };
            if (_src isEqualTo "game" && {(_b get "lines") isNotEqualTo _lines}) then {
                _b set ["lines", _lines];
                _b set ["rem", _rem];
                _b set ["read", false];
                _b set ["ack", ""];
                _b set ["at", _at];
                ["WARNING", format ["%1 corrigé par %2", [_fmt] call _fmtName, _from], 8, 85, "JTAC"] call comspec_atak_native_fnc_notify;
                [] call comspec_atak_native_fnc_vibrate;
                call _render;
            };
            false
        };
        private _st = toUpper _status;
        private _b = createHashMapFromArray [["ref", _ref], ["aid", _aid], ["fmt", _fmt], ["from", _from], ["unit", _unit], ["at", _at],
            ["lines", _lines], ["rem", _rem], ["to", _to], ["src", _src], ["read", ([_st] call _rank) >= 2], ["ack", ["", _st] select (_st in ["WILCO", "UNABLE", "DONE"])]];
        _in = [_b] + _in;
        missionNamespace setVariable [_inboxKey, _in select [0, 20]];
        // Déjà lu ailleurs (reconnexion) : pas de nouvelle alerte.
        if (([_st] call _rank) >= 2) exitWith { call _render; true };
        // L'app JTAC s'ouvrira sur ce brief (notification cliquable, ou prochaine ouverture du téléphone).
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_Jtac", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_Jtac", _s];
        private _onJtac = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "JTAC";
        if (!_onJtac || {(_s getOrDefault ["view", ""]) isEqualTo "AIR"}) then { _s set ["view", "AIR"]; _s set ["airOpen", _ref]; };
        // Mode pilote actif à bord : l'alerte ouvre l'écran pilote, où la mission s'accepte sans menu.
        // Une seule alerte par brief nouveau (ref déjà rangée : rien, sauf correction) : format, émetteur, cible et IP.
        private _what = (switch (_fmt) do {
            case "5": { [_lines param [2, ""], _lines param [3, ""]] };
            case "M": { [_lines param [0, ""]] };
            default { [_lines param [5, ""], format ["IP %1", _lines param [0, ""]]] };
        }) select { !((trim _x) in ["", "-", "IP", "IP -"]) };
        private _head = (createHashMapFromArray [["5", "5 LIGNES reçues de %1"], ["M", "MEDEVAC reçu de %1"]]) getOrDefault [_fmt, "9 LIGNES reçues de %1"];
        ["WARNING", [format [_head, _from], format [_head + " — %2", _from, _what joinString " · "]] select (_what isNotEqualTo []), 10, 85, ["JTAC", "PILOT"] select ((["on"] call comspec_atak_native_fnc_pilotMode) && {["crew"] call comspec_atak_native_fnc_pilotMode})] call comspec_atak_native_fnc_notify;
        [] call comspec_atak_native_fnc_vibrate;
        // Téléphone rangé (en vol) : le vibreur ne joue pas, on entend quand même l'arrivée.
        if (isNull ([] call comspec_atak_native_fnc_display) && {!(profileNamespace getVariable ["COMSPEC_ATAK_Silent", false])}) then {
            if (!isNil "comspec_overwatch_connect_fnc_playAtakVibrate") then { [0.7] call comspec_overwatch_connect_fnc_playAtakVibrate; } else { playSound "ClickSoft"; };
        };
        if (([_st] call _rank) < 1) then { [_b, "RECEIVED"] call _ackOut; };
        call _render;
        true
    };
    case "ack": {
        _arg params [["_ref", ""], ["_st", ""]];
        private _b = (missionNamespace getVariable [_inboxKey, []]) select { (_x get "ref") isEqualTo _ref } param [0, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { false };
        _st = toUpper _st;
        if (_st isEqualTo "READ") exitWith {
            if (_b getOrDefault ["read", false]) exitWith { false };
            _b set ["read", true];
            [_b, "READ"] call _ackOut;
            call _render;
            true
        };
        _b set ["read", true];
        _b set ["ack", _st];
        [_b, _st] call _ackOut;
        [["SUCCESS", "WARNING"] select (_st isEqualTo "UNABLE"), format ["%1 transmis à %2", _st, _b get "from"], 3, 30] call comspec_atak_native_fnc_notify;
        call _render;
        true
    };
    case "readAll": {
        { if !(_x getOrDefault ["read", false]) then { _x set ["read", true]; [_x, "READ"] call _ackOut; }; } forEach (missionNamespace getVariable [_inboxKey, []]);
        call _render;
    };
    case "del": {
        private _in = missionNamespace getVariable [_inboxKey, []];
        private _gone = missionNamespace getVariable ["COMSPEC_ATAK_AirGone", []];
        _gone pushBackUnique _arg;
        missionNamespace setVariable ["COMSPEC_ATAK_AirGone", _gone select [((count _gone) - 60) max 0]];
        missionNamespace setVariable [_inboxKey, _in select { (_x get "ref") isNotEqualTo _arg }];
        call _render;
    };
    case "delAll": {
        private _gone = missionNamespace getVariable ["COMSPEC_ATAK_AirGone", []];
        { _gone pushBackUnique (_x get "ref"); } forEach (missionNamespace getVariable [_inboxKey, []]);
        missionNamespace setVariable ["COMSPEC_ATAK_AirGone", _gone select [((count _gone) - 60) max 0]];
        missionNamespace setVariable [_inboxKey, []];
        call _render;
    };

    case "key": { [_arg] call _key };
    case "airLink": { call _airLink };
    case "why": { call _whyNot };
    case "names": {
        private _v = vehicle player;
        private _inAir = _v isNotEqualTo player && {_v isKindOf "Air"};
        private _l = [];
        private _ks = [];
        {
            private _n = [_x, 40] call _clean;
            private _k = [_n] call _key;
            if (_k isNotEqualTo "" && {!(_k in _generic)} && {!(_k in _ks)}) then { _ks pushBack _k; _l pushBack _n; };
        } forEach [
            ["", [_v] call _airCs] select _inAir,
            profileNamespace getVariable ["COMSPEC_ATAK_AirCsLast", ""],
            ["", [true] call comspec_overwatch_connect_fnc_getCallsign] select (!isNil "comspec_overwatch_connect_fnc_getCallsign"),
            [player] call comspec_atak_native_fnc_unitCallsign,
            groupId group player,
            name player
        ];
        _l select [0, 8]
    };
    case "tick": {
        if (!hasInterface || {isNull player} || {!alive player}) exitWith {};
        private _v = vehicle player;
        private _inAir = _v isNotEqualTo player && {_v isKindOf "Air"};
        if (_inAir) then { missionNamespace setVariable ["COMSPEC_ATAK_AirSeenAt", diag_tickTime]; };
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _open = !isNull ([] call comspec_atak_native_fnc_display);
        private _onApp = _open && {(_st getOrDefault ["activePage", ""]) in ["JTAC", "PILOT"]};
        // Pilote : à bord, rôle pilote, sorti d'un aéronef depuis moins de 30 min, ou app JTAC ouverte.
        // Rôle relu au plus une fois par minute (détection du lanceur, fn_appSpaces).
        private _role = missionNamespace getVariable ["COMSPEC_ATAK_AirRole", [-1e9, false]];
        if ((diag_tickTime - (_role select 0)) > 60) then {
            _role = [diag_tickTime, (["kindOf", player] call comspec_atak_native_fnc_appSpaces) isEqualTo "pilot"];
            missionNamespace setVariable ["COMSPEC_ATAK_AirRole", _role];
        };
        private _want = _inAir || {_onApp} || {_role select 1} || {(diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_AirSeenAt", -1e9])) < 1800};
        if (!_want) exitWith {};
        private _ok = (call _whyNot) isEqualTo "";
        (missionNamespace getVariable ["COMSPEC_ATAK_AirTickWas", [true, true, true]]) params ["_wasOpen", "_wasApp", "_wasOk"];
        missionNamespace setVariable ["COMSPEC_ATAK_AirTickWas", [_open, _onApp, _ok]];
        if (!_ok) exitWith {};
        private _gap = diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_AirPollLast", -1e9]);
        // Réveil du téléphone, ouverture de l'app ou retour de la liaison : relevé tout de suite.
        if (((_open && {!_wasOpen}) || {_onApp && {!_wasApp}} || {!_wasOk}) && {_gap >= 5}) exitWith { ["poll", true] call comspec_atak_native_fnc_airBrief; };
        if (_gap >= ([45, 15] select _inAir)) then { ["poll"] call comspec_atak_native_fnc_airBrief; };
    };
    case "diag": {
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_AirPollDiag", createHashMap];
        private _names = _d getOrDefault ["names", []];
        if (_names isEqualTo []) then { _names = ["names"] call comspec_atak_native_fnc_airBrief; };
        private _at = _d getOrDefault ["at", -1];
        private _why = call _whyNot;
        private _err = _d getOrDefault ["err", ""];
        private _pend = { !(_x getOrDefault ["read", false]) } count (missionNamespace getVariable [_inboxKey, []]);
        [
            format ["Boîte interrogée pour : %1", [_names joinString ", ", "aucun indicatif"] select (_names isEqualTo [])],
            ["dernier relevé : jamais", format ["dernier relevé il y a %1 s", round (diag_tickTime - _at)]] select (_at >= 0),
            format ["%1 brief(s) en attente", _pend],
            ["liaison OK", format ["liaison KO (%1)", [_why, _err] select (_why isEqualTo "")]] select (_why isNotEqualTo "" || {_err isNotEqualTo ""})
        ] joinString " · "
    };
    case "poll": {
        if (!hasInterface || {isNull player} || {!alive player}) exitWith { false };
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_AirPollDiag", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_AirPollDiag", _d];
        private _why = call _whyNot;
        if (_why isNotEqualTo "") exitWith { false };
        // Un seul relevé en vol (réponse lente : on n'en relance pas un autre avant 45 s).
        if ((diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_AirPollAt", -1e9])) < 45) exitWith { false };
        private _names = ["names"] call comspec_atak_native_fnc_airBrief;
        _d set ["names", _names];
        if ((count _names) isEqualTo 0) exitWith { false };
        private _mapId = str (missionNamespace getVariable ["COMSPEC_MapId", missionNamespace getVariable ["comspec_overwatch_map_id", 1]]);
        missionNamespace setVariable ["COMSPEC_ATAK_AirPollAt", diag_tickTime];
        missionNamespace setVariable ["COMSPEC_ATAK_AirPollLast", diag_tickTime];
        [format ["~air|%1", _names joinString "|"], _mapId, _arg isEqualTo true, _d] spawn {
            params ["_q", "_mapId", "_force", "_d"];
            private _r = ["COMSPECExtension", "GetCASForCallsign", [_q, _mapId]] call comspec_atak_native_fnc_extAwait;
            missionNamespace setVariable ["COMSPEC_ATAK_AirPollAt", -1e9];
            // La DLL rend le relevé précédent et rafraîchit en tâche de fond : relevé forcé (réveil, ouverture) relancé 3 s après.
            if (_force) then { [{ missionNamespace setVariable ["COMSPEC_ATAK_AirPollLast", -1e9]; ["poll"] call comspec_atak_native_fnc_airBrief; }, [], 3] call comspec_atak_native_fnc_waitAndExec; };
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if !(_r isEqualType "") then { _r = ""; };
            _d set ["at", diag_tickTime];
            if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith { _d set ["err", [(_r splitString "|") param [1, "sans réponse"], "sans réponse"] select (_r isEqualTo "")]; };
            _d set ["err", ""];
            private _p = trim (_r select [3]);
            if ((_p select [0, 2]) isNotEqualTo "[[") exitWith {};
            private _arr = parseSimpleArray _p;
            if !(_arr isEqualType []) exitWith {};
            isNil {
                {
                    if (_x isEqualType [] && {(count _x) >= 6}) then {
                        _x params [["_ref", ""], ["_aid", 0], ["_kind", "9line"], ["_from", ""], ["_at", ""], ["_lines", []], ["_rem", ""], ["_st", ""], ["_to", ""]];
                        if !(_aid isEqualType 0) then { _aid = 0; };
                        private _fmt = switch (toLower _kind) do { case "5line": { "5" }; case "medevac": { "M" }; default { "9" }; };
                        ["store", [_ref, _aid, _fmt, _from, objNull, _at, _lines, _rem, _to, "athena", _st]] call comspec_atak_native_fnc_airBrief;
                    };
                } forEach _arr;
            };
        };
    };
};
