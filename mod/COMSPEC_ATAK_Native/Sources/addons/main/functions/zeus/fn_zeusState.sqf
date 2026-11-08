/*
    Centre Zeus : relevé à la demande de ce que les variables publiques ne disent pas (fn_zeusCentre).
    Trajet : Zeus -> serveur (droits, fn_adminAllowed) -> joueurs -> serveur -> Zeus. Un joueur ne connaît jamais le demandeur
    et ne répond qu'au serveur ; seul le serveur envoie les fiches, à des unités dont il vient de vérifier les droits.
    Un client qui imite la demande au nom d'un Zeus ne reçoit donc rien. Aucun message, note, code, numéro ni IMEI dans la fiche.
    Coût : rien tant qu'aucun Centre n'est ouvert (aucune boucle, ni côté serveur ni côté joueur). Vue ouverte : une demande
    toutes les 5 s, un relevé toutes les 4 s au plus quel que soit le nombre de Zeus, un envoi groupé par Zeus ; un joueur
    ne répond que si sa fiche a changé, et au moins toutes les 30 s.
    Actions :
      Zeus    : "request" | "data" [fiches] (événement comspec_atak_native_zeusStateData) | "denied"
      Serveur : "init" (postInit) | "scan" [demandeur] | "row" [unité, fiche] | "flush"
      Joueur  : "poll" (événement comspec_atak_native_zeusStatePoll)
    Fiche : [uid, ATAK porté, écran (0 rangé, 1 porté, 2 en main), dégâts %, batterie %, état Athena (fn_athenaStatus « key »),
             session ouverte, version du mod, version de COMSPEC Link, usage caméra ("" | "drone" | "livecam" | "photo"),
             en émission radio, secondes de blocage du code, secondes depuis la PANIQUE (-1 : aucune récente)]
*/
params [["_act", ""], ["_args", []]];
switch (_act) do {
    case "request": {
        ["comspec_atak_native_zeusState", [player]] call CBA_fnc_serverEvent;
    };
    case "denied": {
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateDenied", true];
    };
    case "data": {
        private _m = missionNamespace getVariable ["COMSPEC_ATAK_ZeusRows", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusRows", _m];
        { _m set [_x param [0, ""], [_x, diag_tickTime]]; } forEach _args;
    };
    case "init": {
        if (!isServer) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateMap", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateWatch", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusStateDirty", []];
        ["comspec_atak_native_zeusState", {
            params [["_caller", objNull]];
            if !(_caller isEqualType objNull) exitWith {};
            if !([_caller] call comspec_atak_native_fnc_adminAllowed) exitWith {
                diag_log format ["[COMSPEC ATAK NATIVE][WARN][ZEUS] Relevé refusé (ni Zeus ni admin) : %1", name _caller];
                ["comspec_atak_native_zeusStateDenied", [], _caller] call CBA_fnc_targetEvent;
            };
            ["scan", [_caller]] call comspec_atak_native_fnc_zeusState;
        }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_zeusStateRow", { ["row", _this] call comspec_atak_native_fnc_zeusState; }] call CBA_fnc_addEventHandler;
    };
    case "scan": {
        _args params [["_caller", objNull]];
        private _now = diag_tickTime;
        private _watch = missionNamespace getVariable ["COMSPEC_ATAK_ZeusStateWatch", createHashMap];
        // Nouveau Zeus : toutes les fiches connues au prochain envoi.
        private _id = netId _caller;
        private _isNew = !(_id in _watch);
        _watch set [_id, [_caller, _now, _isNew || {(_watch get _id) param [2, false]}]];
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
        if (!(_u isEqualType objNull) || {isNull _u} || {!isPlayer _u} || {!(_row isEqualType [])} || {(count _row) isNotEqualTo 13}) exitWith {};
        _row params [["_uid", ""], ["_has", false], ["_open", 0], ["_dmg", 0], ["_bat", 0], ["_key", ""], ["_log", false], ["_build", ""], ["_link", ""], ["_cam", ""], ["_tx", false], ["_pin", 0], ["_panic", -1]];
        if (_uid isNotEqualTo (getPlayerUID _u)) exitWith {};
        private _nums = [_open, _dmg, _bat, _pin, _panic];
        private _strs = [_key, _build, _link, _cam];
        if ((_nums findIf { !(_x isEqualType 0) || {!finite _x} }) >= 0 || {(_strs findIf { !(_x isEqualType "") || {(count _x) > 40} }) >= 0} || {!(_has isEqualType true)} || {!(_log isEqualType true)} || {!(_tx isEqualType true)}) exitWith {};
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
        private _row = [
            getPlayerUID player, [player, true] call comspec_atak_native_fnc_hasDevice, _open,
            round ((([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["damage", 0]) * 100),
            round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]),
            _st getOrDefault ["key", ""], _st getOrDefault ["logged", false],
            ["COMSPEC_ATAK_NativeBuild"] call _str, ["COMSPEC_ExtensionVersion"] call _str,
            _cam, _tx,
            ceil (((missionNamespace getVariable ["COMSPEC_ATAK_PinBlock", -1]) - diag_tickTime) max 0),
            [-1, round (diag_tickTime - _panicAt)] select (diag_tickTime - _panicAt < 600)
        ];
        // Inchangée : rien n'est envoyé, sauf au bout de 30 s (le Zeus sait ainsi que le joueur répond encore).
        // Le temps depuis la PANIQUE et le blocage du code avancent seuls : hors comparaison.
        private _sig = str (_row select [0, 11]) + str (_row select 12 >= 0) + str (_row select 11 > 0);
        (uiNamespace getVariable ["COMSPEC_ATAK_ZeusStateSent", ["", -100]]) params ["_old", "_at"];
        if (_sig isEqualTo _old && {diag_tickTime - _at < 30}) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_ZeusStateSent", [_sig, diag_tickTime]];
        ["comspec_atak_native_zeusStateRow", [player, _row]] call CBA_fnc_serverEvent;
    };
};
