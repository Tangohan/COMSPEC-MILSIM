/*
    Centre Zeus : relevé à la demande de ce que les variables publiques ne disent pas (fn_zeusCentre).
    Trajet : Zeus -> serveur (droits, fn_adminAllowed) -> joueurs -> serveur -> Zeus. Un joueur ne connaît jamais le demandeur
    et ne répond qu'au serveur ; seul le serveur envoie les fiches, à des unités dont il vient de vérifier les droits.
    Un client qui imite la demande au nom d'un Zeus ne reçoit donc rien. Aucun message, note, code, numéro ni IMEI dans la fiche.
    Coût : rien tant qu'aucun Centre n'est ouvert (aucune boucle, ni côté serveur ni côté joueur). Vue ouverte : une demande
    toutes les 5 s, un relevé toutes les 4 s au plus quel que soit le nombre de Zeus, un envoi groupé par Zeus ; un joueur
    ne répond que si sa fiche a changé, et au moins toutes les 30 s.
    Actions :
      Zeus    : "request" [toutes les fiches ?] | "data" [fiches] (événement comspec_atak_native_zeusStateData) | "denied"
                "srvRequest" (onglet SERVEUR ouvert, toutes les 5 s) | "srvData" [état] (comspec_atak_native_zeusSrvData)
      Serveur : "init" (postInit) | "scan" [demandeur, toutes] | "row" [unité, fiche] | "flush" | "srvScan" [demandeur]
      Joueur  : "poll" (événement comspec_atak_native_zeusStatePoll)
    Fiche : [uid, ATAK porté, écran (0 rangé, 1 porté, 2 en main), dégâts %, batterie %, état Athena (fn_athenaStatus « key »),
             session ouverte, version du mod, version de COMSPEC Link, usage caméra ("" | "drone" | "livecam" | "photo"),
             en émission radio, secondes de blocage du code, secondes depuis la PANIQUE (-1 : aucune récente),
             pseudo du compte Athena relié (4.2.2, "" avant)]
    État du serveur (onglet SERVEUR, 4.2.2) : paires [clé, valeur] lues sur le serveur à la demande, jamais en continu ;
    FPS, joueurs et IA, versions des mods chargés, liaison Athena du serveur, paquet d'identité SSE, maillage, ordres IA,
    dernières erreurs du journal COMSPEC. Aucun jeton, clé, adresse IP ni UID Steam : les suites de 17 chiffres du journal
    sont masquées.
*/
params [["_act", ""], ["_args", []]];
switch (_act) do {
    case "request": {
        ["comspec_atak_native_zeusState", [player, _args param [0, false]]] call CBA_fnc_serverEvent;
    };
    case "srvRequest": {
        ["comspec_atak_native_zeusSrv", [player]] call CBA_fnc_serverEvent;
    };
    case "denied": {
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateDenied", true];
    };
    case "data": {
        private _m = missionNamespace getVariable ["COMSPEC_ATAK_ZeusRows", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusRows", _m];
        { _m set [_x param [0, ""], [_x, diag_tickTime]]; } forEach _args;
    };
    case "srvData": {
        if !(_args isEqualType []) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusSrv", [createHashMapFromArray _args, diag_tickTime]];
        if (!isNil "comspec_atak_native_fnc_zeusCentre") then { ["server"] call comspec_atak_native_fnc_zeusCentre; };
    };
    case "init": {
        if (!isServer) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateMap", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateWatch", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateDirty", []];
        ["comspec_atak_native_zeusState", {
            params [["_caller", objNull], ["_full", false]];
            if !(_caller isEqualType objNull) exitWith {};
            if !([_caller] call comspec_atak_native_fnc_adminAllowed) exitWith {
                diag_log format ["[COMSPEC ATAK NATIVE][WARN][ZEUS] Relevé refusé (ni Zeus ni admin) : %1", name _caller];
                ["comspec_atak_native_zeusStateDenied", [], _caller] call CBA_fnc_targetEvent;
            };
            ["scan", [_caller, _full isEqualTo true]] call comspec_atak_native_fnc_zeusState;
        }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_zeusStateRow", { ["row", _this] call comspec_atak_native_fnc_zeusState; }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_zeusSrv", {
            params [["_caller", objNull]];
            if (!(_caller isEqualType objNull) || {isNull _caller}) exitWith {};
            if !([_caller] call comspec_atak_native_fnc_adminAllowed) exitWith {
                ["comspec_atak_native_zeusStateDenied", [], _caller] call CBA_fnc_targetEvent;
            };
            ["srvScan", [_caller]] call comspec_atak_native_fnc_zeusState;
        }] call CBA_fnc_addEventHandler;
    };
    case "scan": {
        _args params [["_caller", objNull], ["_full", false]];
        private _now = diag_tickTime;
        private _watch = missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateWatch", createHashMap];
        // Nouveau Zeus, ou Zeus dont les fiches ont vieilli : toutes les fiches connues au prochain envoi.
        private _id = netId _caller;
        private _isNew = !(_id in _watch);
        _watch set [_id, [_caller, _now, _isNew || {_full} || {(_watch get _id) param [2, false]}]];
        if (_now - (missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateAt", -100]) < 4) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateAt", _now];
        private _to = allPlayers select { !(_x isKindOf "HeadlessClient_F") };
        if (_to isNotEqualTo []) then { ["comspec_atak_native_zeusStatePoll", [], _to] call CBA_fnc_targetEvent; };
        [{ ["flush"] call comspec_atak_native_fnc_zeusState; }, [], 1.5] call comspec_atak_native_fnc_waitAndExec;
    };
    case "row": {
        if (!isServer) exitWith {};
        _args params [["_u", objNull], ["_row", []]];
        // Variables envoyées par un client : forme vérifiée, jamais diffusées à d'autres que les Zeus.
        if (!(_u isEqualType objNull) || {isNull _u} || {!isPlayer _u} || {!(_row isEqualType [])} || {!((count _row) in [13, 14])}) exitWith {};
        _row params [["_uid", ""], ["_has", false], ["_open", 0], ["_dmg", 0], ["_bat", 0], ["_key", ""], ["_log", false], ["_build", ""], ["_link", ""], ["_cam", ""], ["_tx", false], ["_pin", 0], ["_panic", -1], ["_acct", ""]];
        if (_uid isNotEqualTo (getPlayerUID _u)) exitWith {};
        private _nums = [_open, _dmg, _bat, _pin, _panic];
        private _strs = [_key, _build, _link, _cam, _acct];
        if ((_nums findIf { !(_x isEqualType 0) || {!finite _x} }) >= 0 || {(_strs findIf { !(_x isEqualType "") || {(count _x) > 40} }) >= 0} || {!(_has isEqualType true)} || {!(_log isEqualType true)} || {!(_tx isEqualType true)}) exitWith {};
        if ((count _row) isEqualTo 13) then { _row pushBack ""; };
        (missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateMap", createHashMap]) set [_uid, _row];
        (missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateDirty", []]) pushBackUnique _uid;
    };
    case "flush": {
        if (!isServer) exitWith {};
        private _map = missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateMap", createHashMap];
        private _uids = allPlayers apply { getPlayerUID _x };
        // Joueurs partis : fiche oubliée.
        { if !(_x in _uids) then { _map deleteAt _x; }; } forEach (keys _map);
        private _dirty = (missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateDirty", []]) select { _x in _map };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateDirty", []];
        private _watch = missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateWatch", createHashMap];
        private _now = diag_tickTime;
        {
            _y params [["_u", objNull], ["_at", 0], ["_full", false]];
            // Vue fermée, joueur parti ou droits perdus : plus rien ne part vers lui.
            if (isNull _u || {_now - _at > 12} || {!([_u] call comspec_atak_native_fnc_adminAllowed)}) then { _watch deleteAt _x; continue; };
            private _rows = if (_full) then { values _map } else { _dirty apply { _map get _x } };
            if (_full) then { _watch set [_x, [_u, _at, false]]; };
            if (_rows isNotEqualTo []) then { ["comspec_atak_native_zeusStateData", [_rows], _u] call CBA_fnc_targetEvent; };
        } forEach (+_watch);
    };
    case "srvScan": {
        if (!isServer) exitWith {};
        _args params [["_caller", objNull]];
        private _now = diag_tickTime;
        // Une lecture toutes les 3 s au plus par Zeus : l'état est léger, mais rien ne doit tourner plus vite que l'onglet.
        if (_now - (_caller getVariable ["COMSPEC_ATAK_ZeusSrvAt", -100]) < 3) exitWith {};
        _caller setVariable ["COMSPEC_ATAK_ZeusSrvAt", _now];
        private _str = { params ["_k", ["_d", ""]]; private _v = missionNamespace getVariable [_k, _d]; if (_v isEqualType "") then { _v select [0, 60] } else { str _v } };
        private _ver = { params ["_c"]; private _v = getText (configFile >> "CfgPatches" >> _c >> "versionStr"); [_v, "—"] select (_v isEqualTo "") };
        private _hc = allPlayers select { _x isKindOf "HeadlessClient_F" };
        private _players = count allPlayers - count _hc;
        private _ai = { !isPlayer _x && {!(_x isKindOf "HeadlessClient_F")} } count allUnits;
        // Versions chargées sur le serveur. Link : la DLL du serveur si elle répond (dédié avec COMSPEC Link), sinon « — ».
        private _link = if (isClass (configFile >> "CfgPatches" >> "comspec_overwatch_connect")) then { [] call comspec_atak_native_fnc_extVersion } else { "" };
        private _mods = [
            ["ATAK", ["COMSPEC_ATAK_NativeBuild", "?"] call _str],
            ["Link attendu", ["COMSPEC_ATAK_LinkExpected", "?"] call _str],
            ["Link (DLL serveur)", [_link, "—"] select (_link isEqualTo "")],
            ["Overwatch", ["comspec_overwatch_connect"] call _ver],
            ["SSE", ["comspec_sse_main"] call _ver],
            ["CBA", ["cba_main"] call _ver], ["ACE", ["ace_main"] call _ver], ["ZEN", ["zen_main"] call _ver], ["ACRE", ["acre_main"] call _ver]
        ] select { (_x select 1) isNotEqualTo "—" };
        // Liaison Athena du serveur (variables d'Overwatch connect), sans jeton ni adresse.
        private _okAt = missionNamespace getVariable ["COMSPEC_LastHealthOk", -1];
        private _okAge = if (_okAt isEqualType 0 && {_okAt > 0}) then { round (_now - _okAt) } else { -1 };
        private _uplinkOff = !isNil "comspec_overwatch_connect_fnc_uplinkOff" && {([] call comspec_overwatch_connect_fnc_uplinkOff) isEqualTo true};
        private _queue = missionNamespace getVariable ["COMSPEC_ServerQueue", createHashMap];
        private _athena = [
            ["mode", [["on", "off"] select (missionNamespace getVariable ["comspec_atak_native_local_only", false]), "absent"] select !(isClass (configFile >> "CfgPatches" >> "comspec_overwatch_connect"))],
            ["auth", ["comspec_overwatch_auth_state", "—"] call _str], ["link", ["COMSPEC_LinkState", "—"] call _str],
            ["detail", (["COMSPEC_LinkDetail", ""] call _str) regexReplace ["[0-9]{17}", "UID"]],
            ["ready", (missionNamespace getVariable ["COMSPEC_AthenaReady", false]) isEqualTo true], ["okAge", _okAge],
            ["uplinkOff", _uplinkOff], ["hub", (missionNamespace getVariable ["COMSPEC_ServerHubActive", false]) isEqualTo true],
            ["queue", if (_queue isEqualType createHashMap) then { count _queue } else { -1 }],
            ["err", (["COMSPEC_LastExtError", ""] call _str) regexReplace ["[0-9]{17}", "UID"]]
        ];
        // Paquet d'identité SSE (addon identity) : reçu ou non, date, volumes.
        private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
        if !(_reg isEqualType createHashMap) then { _reg = createHashMap; };
        private _identity = if (isNil "comspec_sse_fnc_idAthenaPack") then { [] } else {
            [
                ["dll", (["has"] call comspec_sse_fnc_idAthenaPack) isEqualTo true],
                ["packAt", (str (_reg getOrDefault ["packAt", ""])) select [0, 40]], ["packTs", (["comspec_sse_idPackTs", ""] call _str) select [0, 40]],
                ["tries", missionNamespace getVariable ["comspec_sse_idPackTries", 0]],
                ["docs", count (_reg getOrDefault ["athena", createHashMap])], ["countries", count (_reg getOrDefault ["countries", createHashMap])],
                ["population", count (_reg getOrDefault ["population", createHashMap])], ["watch", count (_reg getOrDefault ["watch", []])]
            ]
        };
        // Maillage : graphe de référence du serveur, par camp.
        private _ref = missionNamespace getVariable ["COMSPEC_ATAK_MeshRef", []];
        private _mesh = [["on", (missionNamespace getVariable ["comspec_atak_native_mpu_real", false]) isEqualTo true], ["seq", _ref param [0, -1]],
            ["sides", (_ref param [1, []]) apply { [str (_x param [0, ""]), (count (_x param [1, []])) / 5, (count (_x param [2, []])) / 3, _x param [3, 0]] }]];
        // Ordres aux groupes IA gardés pour les arrivants : nombre par état.
        private _ord = createHashMap;
        {
            if (_y isEqualType createHashMap) then {
                {
                    private _st = if (_y isEqualType createHashMap) then { _y getOrDefault ["state", "?"] } else { "?" };
                    if !(_st isEqualType "") then { _st = str _st; };
                    _ord set [_st, (_ord getOrDefault [_st, 0]) + 1];
                } forEach _y;
            };
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_SrvAiOrd", createHashMap]);
        // Dernières alertes du journal COMSPEC du serveur (fn_log) : 12 au plus, UID masqués, messages tronqués.
        private _log = (missionNamespace getVariable ["COMSPEC_ATAK_Log", []]) select { (_x select 1) in ["ERROR", "WARN", "WARNING"] };
        _log = (_log select [(count _log - 12) max 0, 12]) apply { [round (_now - (_x select 0)), _x select 1, _x select 2, (str (_x select 3) select [0, 160]) regexReplace ["[0-9]{17}", "UID"]] };
        private _watch = missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateWatch", createHashMap];
        private _out = [
            ["uptime", round time], ["fps", round diag_fps], ["fpsMin", round diag_fpsMin], ["dedicated", isDedicated],
            ["atakVersion", ["COMSPEC_ATAK_NativeVersion", ""] call _str],
            ["players", _players], ["hc", count _hc], ["ai", _ai], ["groups", count allGroups], ["vehicles", count vehicles], ["dead", count allDead],
            ["mods", _mods], ["athena", _athena], ["identity", _identity], ["mesh", _mesh],
            ["orders", (keys _ord) apply { [_x, _ord get _x] }], ["zeusWatch", count _watch], ["zeusRows", count (missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateMap", createHashMap])],
            ["log", _log], ["mission", missionName], ["world", worldName]
        ];
        ["comspec_atak_native_zeusSrvData", [_out], _caller] call CBA_fnc_targetEvent;
    };
    case "poll": {
        if (!hasInterface || {isNull player}) exitWith {};
        private _st = [] call comspec_atak_native_fnc_athenaStatus;
        private _d = [] call comspec_atak_native_fnc_display;
        private _open = 0;
        if (!isNull _d) then { _open = [1, 2] select ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false]); };
        private _cam = "";
        private _isObj = { params ["_k"]; private _o = missionNamespace getVariable [_k, objNull]; (_o isEqualType objNull) && {!isNull _o} };
        if (["COMSPEC_ATAK_DroneCam"] call _isObj) then { _cam = "drone"; };
        if (["COMSPEC_ATAK_LivecamCam"] call _isObj) then { _cam = "livecam"; };
        if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) then { _cam = "photo"; };
        private _tx = ((missionNamespace getVariable ["COMSPEC_ATAK_VoiceTx", []]) isNotEqualTo []) || {((["radios"] call comspec_atak_native_fnc_mpu5) findIf { _x param [8, false] }) >= 0};
        private _panicAt = missionNamespace getVariable ["COMSPEC_ATAK_PanicAt", -1e6];
        private _str = { params ["_k"]; private _v = missionNamespace getVariable [_k, ""]; if (_v isEqualType "") then { _v select [0, 40] } else { "" } };
        // Compte Athena relié : indicatif Overwatch, sinon pseudo du profil ; rien sans session ouverte.
        private _acct = "";
        if ((_st getOrDefault ["logged", false]) isEqualTo true) then {
            _acct = ["comspec_profile_name"] call _str;
            if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
                private _g = [] call comspec_overwatch_connect_fnc_getCallsign;
                if (!isNil "_g" && {_g isEqualType ""} && {!((trim _g) in ["", "—", "Operateur"])}) then { _acct = (trim _g) select [0, 40]; };
            };
        };
        private _row = [
            getPlayerUID player, [player, true] call comspec_atak_native_fnc_hasDevice, _open,
            round ((([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["damage", 0]) * 100),
            round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]),
            _st getOrDefault ["key", ""], (_st getOrDefault ["logged", false]) isEqualTo true,
            ["COMSPEC_ATAK_NativeBuild"] call _str, ["COMSPEC_ExtensionVersion"] call _str,
            _cam, _tx,
            ceil (((missionNamespace getVariable ["COMSPEC_ATAK_PinBlock", -1]) - diag_tickTime) max 0),
            [-1, round (diag_tickTime - _panicAt)] select (diag_tickTime - _panicAt < 600),
            _acct
        ];
        // Inchangée : rien n'est envoyé, sauf au bout de 30 s (le Zeus sait ainsi que le joueur répond encore).
        // Le temps depuis la PANIQUE et le blocage du code avancent seuls : hors comparaison.
        private _sig = str (_row select [0, 11]) + str (_row select 12 >= 0) + str (_row select 11 > 0) + _acct;
        (uiNamespace getVariable ["COMSPEC_ATAK_ZeusStateSent", ["", -100]]) params ["_old", "_at"];
        if (_sig isEqualTo _old && {diag_tickTime - _at < 30}) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusStateSent", [_sig, diag_tickTime]];
        ["comspec_atak_native_zeusStateRow", [player, _row]] call CBA_fnc_serverEvent;
    };
};
