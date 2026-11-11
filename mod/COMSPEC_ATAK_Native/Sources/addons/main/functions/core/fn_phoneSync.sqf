/*
    Synchroniser le téléphone avec le web (ICD-SSE-PHONE-001) : le contenu du téléphone du PERSONNAGE part vers les fichiers
    d'Athena par la commande SubmitIdCheck de COMSPEC Link (POST /api/sse/identity/checks, kind « phone_extraction »),
    sans nouvelle commande de DLL. Seulement des données de jeu : contacts, messages, notes, noms des photos, apps,
    positions, repères, numéro, IMEI et MAC du jeu. Jamais de chemin disque ni d'information du vrai PC.
    Rien ne part :
      - avant l'acceptation de la licence (comspec_overwatch_connect_fnc_licenceStatus), liaison coupée (uplinkOff),
        service Athena autre que « on » (fn_athenaMode), Athena pas encore connecté (COMSPEC_AthenaReady) ;
      - sans l'accord du joueur (réglage client comspec_atak_native_phone_sync, décoché par défaut, que la mission ne
        peut pas imposer) ;
      - quand la mission l'interdit (réglage comspec_atak_native_phone_sync_off, ou variable de mission
        COMSPEC_ATAK_PhoneSyncDisabled posée par un Zeus) ;
      - plus d'une fois par minute (anti-répétition), ou si COMSPEC Link ne connaît pas SubmitIdCheck.
    Téléphone saisi : seulement celui d'une IA, ouvert et lu (fn_pagePhones). Le téléphone d'un autre joueur ne part
    jamais avec le compte du lecteur. owner.ai_uid = variable publique comspec_sse_aiUid de l'IA (fn_phoneServer).
    Le retour s'affiche dans le téléphone (fn_notify) et dans le RPT (fn_log), jamais en message Arma.
    Params : [action]
      "own"       synchronise mon téléphone (menu ACE « Synchroniser avec le web »)
      "seized"    synchronise le téléphone d'IA saisi affiché (page Téléphones, menu ACE)
      "seizedOk"  -> true si le téléphone d'autrui affiché est celui d'une IA, ouvert et non effacé
      "gate"      -> "" si l'envoi est permis maintenant, sinon la raison (texte du téléphone)
*/
params [["_act", ""]];
private _say = {
    params ["_type", "_msg"];
    [_type, _msg, 6, 20] call comspec_atak_native_fnc_notify;
    ["INFO", "PHONE", "Synchro web : " + _msg] call comspec_atak_native_fnc_log;
};
// Téléphone d'IA saisi, ouvert et lu : [fiche, données] ; sinon [].
private _seized = {
    private _rec = ["activeRec"] call comspec_atak_native_fnc_phoneOwn;
    private _v = uiNamespace getVariable ["COMSPEC_ATAK_PhoneView", createHashMap];
    if (_rec isEqualTo [] || {(_v getOrDefault ["id", ""]) isNotEqualTo (_rec select 0)} || {(_v getOrDefault ["status", ""]) isNotEqualTo "OPEN"}) exitWith { [] };
    private _d = _v getOrDefault ["data", createHashMap];
    if !(_d isEqualType createHashMap) exitWith { [] };
    private _uid = _d getOrDefault ["ai_uid", ""];
    if ((_d getOrDefault ["wiped", false]) isEqualTo true || {!(_uid isEqualType "")} || {_uid isEqualTo ""} || {((_rec select 2) select [0, 3]) isNotEqualTo "AI_"}) exitWith { [] };
    [_rec, _d]
};
private _gate = {
    if (!hasInterface) exitWith { "Pas de téléphone sur cette machine." };
    if (isNil "comspec_overwatch_connect_fnc_licenceStatus" || {!(([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false])}) exitWith {
        "Acceptez d'abord la licence COMSPEC (menu principal) : rien n'est envoyé avant."
    };
    if (isNil "comspec_overwatch_connect_fnc_uplinkOff" || {[] call comspec_overwatch_connect_fnc_uplinkOff} || {([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on"}) exitWith {
        "Liaison Athena coupée (ATAK local) : rien n'est envoyé."
    };
    if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { "Athena n'est pas connecté : synchronisation impossible pour l'instant." };
    if ((missionNamespace getVariable ["comspec_atak_native_phone_sync_off", false]) || {(missionNamespace getVariable ["COMSPEC_ATAK_PhoneSyncDisabled", false]) isEqualTo true}) exitWith {
        "Synchronisation des téléphones interdite pendant cette mission."
    };
    if !(missionNamespace getVariable ["comspec_atak_native_phone_sync", false]) exitWith {
        "Synchronisation désactivée : cochez « Autoriser la synchronisation du téléphone avec Athena » (options CBA, ATAK · Téléphones personnels)."
    };
    if (missionNamespace getVariable ["COMSPEC_ATAK_PhoneSyncBusy", false]) exitWith { "Synchronisation déjà en cours." };
    private _wait = 60 - (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_PhoneSyncAt", -1000]));
    if (_wait > 0) exitWith { format ["Une synchronisation par minute au plus : réessayez dans %1 s.", ceil _wait] };
    ""
};

switch (_act) do {
    case "gate": { call _gate };
    case "seizedOk": { (call _seized) isNotEqualTo [] };
    case "own";
    case "seized": {
        private _why = call _gate;
        if (_why isNotEqualTo "") exitWith { ["WARNING", _why] call _say; false };
        private _src = [];
        if (_act isEqualTo "seized") then {
            _src = call _seized;
        } else {
            if (["wiped"] call comspec_atak_native_fnc_phoneSecurity) exitWith { _src = ["wiped"]; };
            if !([player, true] call comspec_atak_native_fnc_hasDevice) exitWith { _src = ["nophone"]; };
            _src = ["own"];
        };
        if (_src isEqualTo []) exitWith { ["WARNING", "Seul le téléphone ouvert d'une IA peut être synchronisé."] call _say; false };
        if (_src isEqualTo ["wiped"]) exitWith { ["WARNING", "Téléphone effacé : rien à synchroniser."] call _say; false };
        if (_src isEqualTo ["nophone"]) exitWith { ["WARNING", "Vous n'avez pas de téléphone à synchroniser."] call _say; false };
        // Anti-répétition posé dès le départ : un échec compte aussi (pas d'envoi en boucle).
        missionNamespace setVariable ["COMSPEC_ATAK_PhoneSyncAt", diag_tickTime];
        missionNamespace setVariable ["COMSPEC_ATAK_PhoneSyncBusy", true];
        ["INFO", "Synchronisation du téléphone avec le web…", 4, 10] call comspec_atak_native_fnc_notify;
        [_src, _say] spawn {
            params ["_src", "_say"];
            private _txt = {
                params ["_v", ["_max", 1000]];
                if !(_v isEqualType "") then { _v = str _v; };
                // Caractères de contrôle (sauf retour à la ligne) : un JSON invalide serait refusé par Athena.
                _v = trim (_v regexReplace ["[\x00-\x09\x0b-\x1f]", " "]);
                forceUnicode 0;
                _v select [0, _max]
            };
            private _two = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
            private _hm = { params ["_t"]; private _h = dayTime - ((time - _t) / 3600); [((_h mod 24) + 24) mod 24, "HH:MM"] call BIS_fnc_timeToString };
            private _clsLabel = { params ["_c"]; private _n = getText (configFile >> "CfgWeapons" >> _c >> "displayName"); [_n, _c] select (_n isEqualTo "") };
            private _clean = { params ["_s"]; _s regexReplace ["[^A-Za-z0-9_.:-]", "_"] };
            date params ["_yr", "_mo", "_dy", "_hr", "_mi"];
            private _gameTime = format ["%1-%2-%3 %4:%5", _yr, [_mo] call _two, [_dy] call _two, [_hr] call _two, [_mi] call _two];
            private _cats = createHashMapFromArray [["contacts", []], ["calls", []], ["messages", []], ["notes", []], ["photos", []], ["apps", []], ["positions", []], ["places", []]];
            private _device = createHashMap;
            private _owner = createHashMap;
            private _net = createHashMap;
            if ((_src select 0) isEqualTo "own") then {
                ([player, "norm"] call comspec_atak_native_fnc_phoneIdent) params [["_numN", ""], ["_imeiN", ""]];
                ([player] call comspec_atak_native_fnc_phoneIdent) params [["_num", ""], ["_imei", ""], ["_mac", ""]];
                private _me = getPlayerUID player;
                if (_me isEqualTo "") then { _me = netId player; };
                private _mine = (["records", player] call comspec_atak_native_fnc_phoneOwn) select { (_x select 2) isEqualTo _me };
                private _cls = if (_mine isNotEqualTo []) then { (_mine select 0) select 1 } else { "COMSPEC_ATAK_Phone" };
                // Identifiant stable du téléphone du jeu : son IMEI (change avec « Changer de téléphone », comme l'appareil).
                private _uid = ["ph.imei." + _imeiN, "ph.num." + _numN] select (_imeiN isEqualTo "");
                _device = createHashMapFromArray [["uid", [[_uid] call _clean, 64] call _txt], ["label", [format ["Téléphone de %1", name player], 190] call _txt],
                    ["model", [[_cls] call _clsLabel, 190] call _txt], ["number", [_num, 40] call _txt], ["imei", [_imei, 40] call _txt]];
                _owner = createHashMapFromArray [["kind", "player"], ["label", [name player, 190] call _txt]];
                _net = createHashMapFromArray [["carrier", ""], ["number", [_num, 40] call _txt], ["imei", [_imei, 40] call _txt], ["mac", [_mac, 40] call _txt], ["wifi", []]];
                // Contacts du carnet (app Numéros, fn_localCfg) : nom, numéro, note du personnage.
                private _cfg = ["get"] call comspec_atak_native_fnc_localCfg;
                private _ct = if (_cfg isEqualType createHashMap) then { _cfg getOrDefault ["contacts", []] } else { [] };
                if !(_ct isEqualType []) then { _ct = []; };
                _cats set ["contacts", ((_ct select { _x isEqualType createHashMap }) select [0, 100]) apply {
                    createHashMapFromArray [["name", [_x getOrDefault ["name", ""], 190] call _txt], ["number", [_x getOrDefault ["number", ""], 40] call _txt], ["note", [_x getOrDefault ["note", ""]] call _txt]]
                }];
                // Messages reçus et envoyés par ce téléphone (fil unifié, fn_messagesAll).
                _cats set ["messages", ((([""] call comspec_atak_native_fnc_messagesAll) select { _x isEqualType createHashMap }) select [0, 300]) apply {
                    createHashMapFromArray [["at", [_x getOrDefault ["time", ""], 32] call _txt], ["from", [_x getOrDefault ["author", ""], 90] call _txt], ["to", ""],
                        ["body", [_x getOrDefault ["body", ""]] call _txt], ["channel", [_x getOrDefault ["kind", ""], 40] call _txt], ["outgoing", (_x getOrDefault ["mine", false]) isEqualTo true]]
                }];
                // Bloc-notes : titre et texte (la date des notes vient de l'horloge du PC : pas envoyée).
                private _notes = profileNamespace getVariable ["COMSPEC_ATAK_Notes", []];
                if !(_notes isEqualType []) then { _notes = []; };
                _cats set ["notes", ((_notes select { _x isEqualType [] && {(count _x) >= 3} }) select [0, 100]) apply {
                    createHashMapFromArray [["at", ""], ["title", [_x select 1, 190] call _txt], ["body", [_x select 2] call _txt]]
                }];
                // Galerie : nom du fichier seul, jamais son chemin sur le disque.
                private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
                if (_lib isEqualTo []) then { _lib = ["list"] call comspec_atak_native_fnc_photoLibrary; };
                if !(_lib isEqualType []) then { _lib = []; };
                _cats set ["photos", ((_lib select { _x isEqualType [] && {(count _x) >= 2} }) select [0, 200]) apply {
                    private _f = ([_x select 1, 400] call _txt) splitString "\/";
                    createHashMapFromArray [["at", ""], ["file", [(_f param [(count _f) - 1, ""]), 190] call _txt], ["caption", ""], ["grid", ""]]
                }];
                // Apps affichées sur le téléphone (lanceur), avec la version du mod.
                private _ver = missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", ""];
                _cats set ["apps", ((([] call comspec_atak_native_fnc_appList) select { !((_x getOrDefault ["id", ""]) in ["Admin"]) && {[_x] call comspec_atak_native_fnc_appVisible} }) select [0, 80]) apply {
                    createHashMapFromArray [["name", [_x getOrDefault ["name", ""], 190] call _txt], ["version", [_ver, 40] call _txt]]
                }];
                // Positions : ma trace (fn_mapPrefs « trail », 200 points au plus) puis ma position actuelle.
                private _pts = missionNamespace getVariable ["COMSPEC_ATAK_SelfTrailPts", []];
                if !(_pts isEqualType []) then { _pts = []; };
                _pts = _pts select { _x isEqualType [] && {(count _x) >= 3} };
                private _step = ceil ((count _pts) / 200) max 1;
                private _pos = [];
                for "_i" from 0 to ((count _pts) - 1) step _step do {
                    private _p = _pts select _i;
                    _pos pushBack createHashMapFromArray [["at", [_p select 2] call _hm], ["grid", mapGridPosition [_p select 0, _p select 1]], ["note", "Trace"]];
                };
                _pos pushBack createHashMapFromArray [["at", [time] call _hm], ["grid", [player, 8] call comspec_atak_native_fnc_gridRef], ["note", "Position à la synchronisation"]];
                _cats set ["positions", _pos select [0, 201]];
                // Repères de la carte du téléphone, avec leur note.
                private _mnotes = missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap];
                if !(_mnotes isEqualType createHashMap) then { _mnotes = createHashMap; };
                private _places = [];
                {
                    private _p = _y getOrDefault ["position", []];
                    if (_p isEqualType [] && {(count _p) >= 2} && {(count _places) < 300}) then {
                        private _note = (_mnotes getOrDefault [_x, [""]]) param [0, ""];
                        _places pushBack createHashMapFromArray [["name", [_y getOrDefault ["text", ""], 190] call _txt], ["grid", mapGridPosition _p], ["note", [_note] call _txt]];
                    };
                } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["markers", createHashMap]);
                _cats set ["places", _places];
            } else {
                // Téléphone d'IA saisi : ce que le serveur a renvoyé à l'ouverture (fn_phoneServer « adopt »).
                (_src select 0) params ["", "_cls", "", "_name"];
                private _d = _src select 1;
                private _aiUid = [_d getOrDefault ["ai_uid", ""], 64] call _txt;
                private _num = [_d getOrDefault ["number", ""], 40] call _txt;
                private _imei = [_d getOrDefault ["imei", ""], 40] call _txt;
                _device = createHashMapFromArray [["uid", [["ai." + _aiUid] call _clean, 64] call _txt], ["label", [format ["Téléphone de %1", _name], 190] call _txt],
                    ["model", [[_cls] call _clsLabel, 190] call _txt], ["number", _num], ["imei", _imei]];
                _owner = createHashMapFromArray [["kind", "ai"], ["label", [_name, 190] call _txt], ["ai_uid", _aiUid]];
                _net = createHashMapFromArray [["carrier", ""], ["number", _num], ["imei", _imei], ["mac", ""], ["wifi", []]];
                private _arr = { params ["_k"]; private _v = _d getOrDefault [_k, []]; if (_v isEqualType []) then { (_v select { _x isEqualType [] }) select [0, 300] } else { [] } };
                _cats set ["messages", (["msgs"] call _arr) apply {
                    createHashMapFromArray [["at", [_x param [0, ""], 32] call _txt], ["from", [_x param [1, ""], 90] call _txt], ["to", ""], ["body", [_x param [2, ""]] call _txt], ["channel", "sms"]]
                }];
                _cats set ["notes", (["notes"] call _arr) apply { createHashMapFromArray [["at", ""], ["title", [_x param [0, ""], 190] call _txt], ["body", [_x param [1, ""]] call _txt]] }];
                _cats set ["places", (["marks"] call _arr) apply { createHashMapFromArray [["name", [_x param [1, ""], 190] call _txt], ["grid", [_x param [0, ""], 32] call _txt], ["note", ""]] }];
            };
            private _devUid = _device getOrDefault ["uid", ""];
            if ((count _devUid) < 3) exitWith {
                missionNamespace setVariable ["COMSPEC_ATAK_PhoneSyncBusy", false];
                ["WARNING", "Téléphone sans identifiant : synchronisation impossible."] call _say;
            };
            // COMSPEC Link trop ancien : SubmitIdCheck absent de GetAtakFeatures (commande locale, sans réseau). Gardé pour la partie.
            private _dll = missionNamespace getVariable ["COMSPEC_ATAK_PhoneSyncDll", ""];
            if (_dll isEqualTo "") then {
                private _f = (["COMSPECExtension", "GetAtakFeatures", []] call comspec_atak_native_fnc_extAwait) param [0, ""];
                if !(_f isEqualType "") then { _f = str _f; };
                if ((_f select [0, 3]) isEqualTo "OK|") then {
                    _dll = ["no", "yes"] select ("SubmitIdCheck" in (((_f splitString "|") param [2, ""]) splitString ","));
                    missionNamespace setVariable ["COMSPEC_ATAK_PhoneSyncDll", _dll];
                };
            };
            if (_dll isNotEqualTo "yes") exitWith {
                missionNamespace setVariable ["COMSPEC_ATAK_PhoneSyncBusy", false];
                ["WARNING", "COMSPEC Link ne permet pas encore la synchronisation du téléphone : mettez-le à jour."] call _say;
            };
            private _st = systemTimeUTC;
            private _ek = [[format ["phx.%1.%2%3%4%5%6%7", _devUid select [0, 40], _st select 0, [_st select 1] call _two, [_st select 2] call _two, [_st select 3] call _two, [_st select 4] call _two, [_st select 5] call _two]] call _clean, 72] call _txt;
            private _base = createHashMapFromArray [["kind", "phone_extraction"], ["consent", true], ["extraction_key", _ek], ["device", _device], ["owner", _owner],
                ["grid", [player, 8] call comspec_atak_native_fnc_gridRef], ["game_time", _gameTime]];
            private _op = missionNamespace getVariable ["comspec_sse_id_opKey", ""];
            if (_op isEqualType "" && {_op isNotEqualTo ""}) then { _base set ["op", [_op, 64] call _txt]; };
            // Parties : 300 éléments par catégorie au plus ; corps de 240 000 octets au plus (Athena : 256 Ko), soit 60 morceaux
            // de 4 000 caractères (la DLL accepte 64 arguments, Async et le ticket compris). Trop gros : parties deux fois plus petites.
            private _build = {
                params ["_ps"];
                private _n = 1;
                { _n = _n max (ceil ((count _y) / _ps)); } forEach _cats;
                private _out = [];
                for "_p" from 0 to (_n - 1) do {
                    private _dd = createHashMapFromArray [["network", _net]];
                    { _dd set [_x, _y select [_p * _ps, _ps]]; } forEach _cats;
                    private _b = +_base;
                    _b set ["data", _dd];
                    _b set ["part", [_p + 1, _n]];
                    _b set ["idempotency_key", format ["%1.%2", _ek, _p + 1]];
                    _out pushBack ([_b] call comspec_atak_native_fnc_json);
                };
                _out
            };
            private _ps = 300;
            private _jsons = [_ps] call _build;
            while { (_jsons findIf { (count _x) > 240000 }) >= 0 && {_ps > 10} } do {
                _ps = floor (_ps / 2);
                _jsons = [_ps] call _build;
            };
            private _items = 0;
            { _items = _items + count _y; } forEach _cats;
            if ((count _jsons) > 20 || {(_jsons findIf { (count _x) > 240000 }) >= 0}) exitWith {
                missionNamespace setVariable ["COMSPEC_ATAK_PhoneSyncBusy", false];
                ["WARNING", "Trop de données dans ce téléphone : synchronisation refusée."] call _say;
            };
            private _res = "";
            private _ref = "";
            {
                private _json = _x;
                private _chunks = [];
                forceUnicode 0;
                private _len = count _json;
                for "_i" from 0 to (_len - 1) step 4000 do {
                    forceUnicode 0;
                    _chunks pushBack (_json select [_i, 4000]);
                };
                private _r = (["COMSPECExtension", "SubmitIdCheck", _chunks, 45] call comspec_atak_native_fnc_extAwait) param [0, ""];
                if !(_r isEqualType "") then { _r = str _r; };
                ["INFO", "PHONE", format ["Synchro web : partie %1/%2 (%3 octets) -> %4", _forEachIndex + 1, count _jsons, count _json, _r select [0, 80]]] call comspec_atak_native_fnc_log;
                if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith { _res = _r; };
                private _p = _r splitString "|";
                _ref = _p param [2, _p param [1, ""]];
                _res = "OK";
            } forEach _jsons;
            missionNamespace setVariable ["COMSPEC_ATAK_PhoneSyncBusy", false];
            if (_res isEqualTo "OK") exitWith {
                ["SUCCESS", format ["Téléphone synchronisé avec le web : %1 élément(s), référence %2.", _items, _ref]] call _say;
            };
            private _code = (_res splitString "|") param [1, _res];
            private _msg = switch (_code) do {
                case "unlinked";
                case "no_tenant": { "Compte Athena non relié à ce jeu : rien n'a été enregistré." };
                case "consent_revoked": { "Vous avez retiré l'accord pour ce téléphone sur le site : rien n'a été enregistré." };
                case "extractions_disabled": { "Votre communauté a coupé la synchronisation des téléphones." };
                case "unauthorized";
                case "forbidden": { "Refusé par Athena : compte non relié, accord retiré sur le site ou synchronisation coupée par la communauté." };
                case "too_large": { "Trop de données pour Athena : synchronisation refusée." };
                case "rate_limited": { "Athena est occupé : réessayez dans une minute." };
                case "uplink_hold": { "Liaison Athena coupée : rien n'est envoyé." };
                case "timeout";
                case "unavailable": { "Athena ne répond pas : réessayez plus tard." };
                default { format ["Synchronisation refusée par Athena (%1).", _code] };
            };
            ["WARNING", _msg] call _say;
        };
        true
    };
    default { false };
};
