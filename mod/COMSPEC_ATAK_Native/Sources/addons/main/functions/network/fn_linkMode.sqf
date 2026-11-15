/*
    Mode de liaison du téléphone pour toute la mission : la SEULE source de vérité, lue par fn_relayNet ("only",
    "required", "gateOn") et donc par fn_linkQuality, fn_offline, fn_netSend et toutes les apps.
      "sat"   : liaison SATELLITE. Aucun relais n'est exigé : la liaison existe partout, les relais présents ne font
                qu'améliorer le débit. Aucune app ne se dit « hors réseau » faute de relais.
      "relay" : liaison PAR RELAIS. Le téléphone dépend des relais (portée, relief, état) ; relais détruit, éteint ou
                hors de portée : plus de réseau, SANS repli sur le satellite.
    Réglage serveur comspec_atak_native_link_mode (premier réglage de la liste COMSPEC, imposé à tous) :
      "auto"  (défaut) : DÉTECTION des relais posés dans la mission (Eden ou Zeus) ; un choix explicite « réseau
                uniquement par les relais » (réglage comspec_atak_native_relay_only, règle du site, case du module ou
                du Zeus, mode réaliste d'Athena : rien de coché par défaut) y vaut « relais obligatoires » ;
      "sat"   : satellite, toujours relié ;
      "relay" : relais obligatoires, même si la mission n'en a aucun.
    Règle de détection ("decide"), décidée par le serveur seul puis publiée à tous (COMSPEC_ATAK_LinkModeAuto) :
      0 relais posé                         -> satellite ;
      1 relais ou plus, même détruit         -> relais (un relais détruit existe toujours, il ne transmet plus) ;
      dernier relais supprimé (Zeus)         -> retour au satellite.
    Relais comptés ("placed") : ceux de la mission (COMSPEC_AtakRelays : pylônes et antennes COMSPEC, module « Poser un
    relais » en Eden ou en Zeus, véhicule relais du module, antennes du jeu converties, relais Overwatch), tous camps
    confondus, vivants ou détruits. Ne comptent pas : ce qu'un joueur déploie lui-même en cours de partie (antennes
    transportables, antenne MRH, passerelle de véhicule, radios MPU5) : poser une antenne ne doit pas couper le réseau
    des autres.
    Params : [action, arguments]
      ["get"]            -> "sat" | "relay" (réglage, puis détection publiée ; "sat" tant que rien n'est publié)
      ["decide", [n]]    -> "sat" | "relay" pour n relais posés (sans effet, testable)
      ["placed"]         -> relais posés encore présents dans la mission (objets)
      ["reason"]         -> pourquoi aucun relais n'est reçu : « Relais détruit », « Relais hors service », « Aucun relais à portée »
      ["why"]            -> raison réelle de l'absence de réseau du téléphone, "" s'il a du réseau
      ["serverInit"], ["serverTick"] (serveur, 2 s), ["deleted", [objet]], ["forget", [id]], ["curatorHook"] (joueur, 5 s)
    État du serveur : missionNamespace COMSPEC_ATAK_LinkModeSeen (id -> [objet, détruit, supprimé par un Zeus]).
*/
params [["_act", "get"], ["_arg", []]];
switch (_act) do {
    case "get": {
        private _set = missionNamespace getVariable ["comspec_atak_native_link_mode", "auto"];
        private _only = ["relayOnly"] call comspec_atak_native_fnc_netCond;
        if (isNil "_only") then { _only = false; };
        private _mode = switch (true) do {
            case (_set isEqualTo "sat"): { "sat" };
            case (_set isEqualTo "relay"): { "relay" };
            // Choix explicite déjà fait ailleurs (rien de coché par défaut) : réglage « Réseau uniquement par les relais »,
            // règle du site ou du menu Échap, case du module ou du Zeus, mode réaliste d'Athena. Il vaut « Relais obligatoires ».
            case (_only isEqualTo true
                || {(missionNamespace getVariable ["COMSPEC_ATAK_RelayOnly", false]) isEqualTo true}
                || {(missionNamespace getVariable ["COMSPEC_LinkViaRelays", false]) isEqualTo true}): { "relay" };
            default { ["sat", "relay"] select ((missionNamespace getVariable ["COMSPEC_ATAK_LinkModeAuto", "sat"]) isEqualTo "relay") };
        };
        // Changement de mode : liaison, meilleur relais et règle du maillage recalculés tout de suite.
        if (hasInterface && {_mode isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_LinkModeWas", ""])}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_LinkModeWas", _mode];
            uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
            uiNamespace setVariable ["COMSPEC_ATAK_RelayBest", []];
            uiNamespace setVariable ["COMSPEC_ATAK_GateCache", []];
        };
        _mode
    };
    case "decide": {
        // 0 relais posé : satellite. Au moins un, même détruit : relais. Plus aucun (dernier supprimé) : satellite.
        private _n = _arg param [0, 0];
        if !(_n isEqualType 0) then { _n = 0; };
        ["sat", "relay"] select (_n > 0)
    };
    case "placed": {
        private _out = [];
        {
            if (!(_x isEqualType objNull) || {isNull _x}) then { continue };
            // Retiré du réseau alors qu'il est intact (fn_relayConvert "remove") : ce n'est plus un relais.
            if (alive _x && {!(_x getVariable ["COMSPEC_AtakRelay", false])}) then { continue };
            // Déployé par un joueur (fn_antennaKit, antenne MRH) : n'entre pas dans la détection.
            if ((_x getVariable ["COMSPEC_AtakRelayKit", ""]) isNotEqualTo "") then { continue };
            if (((toLower typeOf _x) find "mrh_") isEqualTo 0) then { continue };
            _out pushBackUnique _x;
        } forEach (missionNamespace getVariable ["COMSPEC_AtakRelays", []]);
        _out
    };
    case "reason": {
        private _all = (["list"] call comspec_atak_native_fnc_relayNet) select { (_x select 3) isNotEqualTo "mpu5" };
        if (_all isEqualTo [] || {(_all findIf { _x select 6 }) >= 0}) exitWith { "Aucun relais à portée" };
        ["Relais hors service", "Relais détruit"] select ((_all findIf { (_x select 7) isEqualTo "détruit" }) >= 0)
    };
    case "why": {
        private _q = [] call comspec_atak_native_fnc_linkQuality;
        if (!(_q getOrDefault ["sim", false]) || {(_q getOrDefault ["bars", 0]) > 0}) exitWith { "" };
        private _f = _q getOrDefault ["factors", []];
        private _i = _f findIf { (_x param [1, ""]) in ["aucun réseau", "aucun signal"] };
        if (_i >= 0) exitWith { (_f select _i) select 0 };
        _q getOrDefault ["label", "Aucun signal"]
    };
    case "serverInit": {
        if (!isServer) exitWith {};
        ["comspec_atak_native_linkModeForget", { ["forget", _this] call comspec_atak_native_fnc_linkMode; }] call CBA_fnc_addEventHandler;
    };
    case "deleted": {
        // Événement « Deleted » d'un relais suivi (serveur) : on note s'il était détruit au moment de disparaître.
        private _o = _arg param [0, objNull];
        private _seen = missionNamespace getVariable ["COMSPEC_ATAK_LinkModeSeen", createHashMap];
        private _id = _o getVariable ["COMSPEC_ATAK_LinkModeId", ""];
        private _e = _seen getOrDefault [_id, []];
        if (_e isNotEqualTo []) then { _seen set [_id, [_o, !alive _o, _e param [2, false]]]; };
    };
    case "forget": {
        // Relais supprimé par un Zeus (même détruit) : il ne compte plus.
        if (!isServer) exitWith {};
        private _id = _arg param [0, ""];
        private _seen = missionNamespace getVariable ["COMSPEC_ATAK_LinkModeSeen", createHashMap];
        private _e = _seen getOrDefault [_id, []];
        if (_id isEqualType "" && {_e isNotEqualTo []}) then { _seen set [_id, [_e select 0, _e select 1, true]]; };
    };
    case "serverTick": {
        if (!isServer) exitWith {};
        private _seen = missionNamespace getVariable "COMSPEC_ATAK_LinkModeSeen";
        if (isNil "_seen") then { _seen = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_LinkModeSeen", _seen]; };
        private _now = ["placed"] call comspec_atak_native_fnc_linkMode;
        {
            private _id = _x getVariable ["COMSPEC_ATAK_LinkModeId", ""];
            if (_id isEqualTo "") then {
                private _n = (missionNamespace getVariable ["COMSPEC_ATAK_LinkModeSeq", 0]) + 1;
                missionNamespace setVariable ["COMSPEC_ATAK_LinkModeSeq", _n];
                _id = format ["lm%1", _n];
                _x setVariable ["COMSPEC_ATAK_LinkModeId", _id, true];
                _x addEventHandler ["Deleted", { ["deleted", [_this select 0]] call comspec_atak_native_fnc_linkMode; }];
            };
            _seen set [_id, [_x, !alive _x, (_seen getOrDefault [_id, []]) param [2, false]]];
        } forEach _now;
        {
            _y params ["_o", "_dead", ["_zeus", false]];
            if (isNull _o) then {
                // Supprimé intact, ou supprimé par un Zeus : ne compte plus. Détruit puis nettoyé (épave effacée) : compte toujours.
                if (!_dead || {_zeus}) then { _seen deleteAt _x; };
            } else {
                // Sorti de la liste des relais : intact, ce n'est plus un relais ; détruit, il existe toujours.
                if !(_o in _now) then { if (alive _o) then { _seen deleteAt _x; } else { _seen set [_x, [_o, true, _zeus]]; }; };
            };
        } forEach +_seen;
        private _want = ["decide", [count _seen]] call comspec_atak_native_fnc_linkMode;
        private _pub = missionNamespace getVariable ["COMSPEC_ATAK_LinkModeAuto", ""];
        if (_want isEqualTo _pub) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_LinkModePending", ["", 0]]; };
        // Anti-rafale : le nouveau mode doit tenir 3 s (pylônes d'Eden enregistrés un par un, relais déplacé, objet recréé).
        (missionNamespace getVariable ["COMSPEC_ATAK_LinkModePending", ["", 0]]) params [["_pm", ""], ["_pt", 0]];
        if (_pm isNotEqualTo _want) exitWith { missionNamespace setVariable ["COMSPEC_ATAK_LinkModePending", [_want, diag_tickTime]]; };
        if (diag_tickTime - _pt < 3) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_LinkModePending", ["", 0]];
        // Publié à tous, arrivées en cours de partie comprises : le même mode pour tout le monde.
        missionNamespace setVariable ["COMSPEC_ATAK_LinkModeAuto", _want, true];
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][LIAISON] Mode de liaison détecté : %1 (%2 relais posé(s))", _want, count _seen];
    };
    case "curatorHook": {
        // Zeus : un relais supprimé depuis l'interface Zeus est signalé au serveur, même à l'état d'épave.
        if (!hasInterface || {isNull player}) exitWith {};
        private _c = getAssignedCuratorLogic player;
        if (isNull _c || {_c getVariable ["COMSPEC_ATAK_LinkModeEh", false]}) exitWith {};
        _c setVariable ["COMSPEC_ATAK_LinkModeEh", true];
        _c addEventHandler ["CuratorObjectDeleted", {
            params ["", ["_e", objNull]];
            if (isNull _e) exitWith {};
            private _id = _e getVariable ["COMSPEC_ATAK_LinkModeId", ""];
            if (_id isNotEqualTo "") then { ["comspec_atak_native_linkModeForget", [_id]] call CBA_fnc_serverEvent; };
        }];
    };
};
