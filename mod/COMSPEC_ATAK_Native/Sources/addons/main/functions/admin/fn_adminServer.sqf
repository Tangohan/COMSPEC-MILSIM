/*
    App Admin de l'ATAK, côté serveur (seul juge des droits, fn_adminAllowed). Appelée par un client avec
    [action, arguments] remoteExecCall ["comspec_atak_native_fnc_adminServer", 2] (CfgRemoteExec). Le demandeur n'est
    JAMAIS celui qu'annonce la demande : il est retrouvé par remoteExecutedOwner (fn_reqUnit). Plus d'événement CBA
    comspec_atak_native_admin : un événement CBA ne porte pas l'identité du poste émetteur.
    Rien ne tourne en continu : le serveur ne travaille que quand un admin a l'app ouverte (une demande toutes les 10 s au plus).
    Actions :
      "scan"     état de la partie : chaque joueur renvoie la fiche de son ATAK directement au demandeur
                 (fn_adminClient "report"), le serveur renvoie le debug de l'IA (téléphones ramassés, porteurs, traque)
      "capture"  [id, "validate" | "dismiss" | "stopHunt" | "recover"]   téléphone aux mains de l'IA :
                 valider ou rejeter la compromission, arrêter la traque de ses groupes, reprendre le téléphone au porteur
      "hunt"     [actif]   traque des joueurs par l'IA qui a un téléphone (réglage comspec_atak_native_capture_hunt), pour la partie
      "apps"     [id, "required" | "forbidden" | "free"]   App Store : app obligatoire, interdite ou laissée au choix
                 des joueurs pour la partie (fn_appInstall, variable publique COMSPEC_ATAK_AppPolicy) ; ["", "reset"] : tout libérer
    Réponse au demandeur : comspec_atak_native_adminData [genre, données] ("debug" | "result" | "denied").
*/
if (!isServer) exitWith {};
params [["_act", ""], ["_args", []], ["_caller", objNull]];
// Demande venue d'un client : seul remoteExecutedOwner fait foi. L'unité annoncée dans la demande est écartée (un
// client y mettait l'unité d'un Zeus ou d'un admin connecté pour obtenir le relevé de la partie, valider une
// compromission -- donc faire révoquer un certificat chez Athena -- ou reprendre un téléphone).
// Un appel non distant ne peut venir que du code du serveur (postInit) : il garde ce qu'il passe. Quand la demande vient
// d'un client, ce que cette fonction appelle elle-même (ré-appel « scan », fn_captureServer, gestionnaire CBA d'une
// unité locale) tourne dans la même pile et y voit le même appel distant (voir « recover »).
private _remote = isMultiplayer && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2};
if (_remote) then { _caller = [remoteExecutedOwner] call comspec_atak_native_fnc_reqUnit; };
// Hôte d'une partie locale : son propre appel n'est pas distant (son poste EST le serveur, remoteExecutedOwner y
// vaut 2 ou rien). Il reste le demandeur, sans quoi la réponse et le journal partiraient vers objNull.
if (!_remote && {isNull _caller} && {hasInterface}) then { _caller = player; };
// Actions ouvertes aux clients (app Admin), le droit étant jugé juste après sur le demandeur. « apps » (App Store,
// regroupement 2.5.0) y est : sans elle, tout admin qui n'est pas l'hôte était refusé (contre-expertise v4, B5).
if (_remote && {!(_act in ["scan", "capture", "hunt", "apps"])}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][ADMIN] Action « %1 » refusée au poste %2 (réservée au serveur)", _act, remoteExecutedOwner];
    false
};
if (_remote && {isNull _caller}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][ADMIN] Demande « %1 » refusée au poste %2 (demandeur introuvable)", _act, remoteExecutedOwner];
    false
};
if (_remote && {!([_caller] call comspec_atak_native_fnc_adminAllowed)}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][ADMIN] Demande « %1 » refusée à %2 (ni Zeus ni admin)", _act, name _caller];
    ["comspec_atak_native_adminData", ["denied", []], _caller] call CBA_fnc_targetEvent;
    false
};
private _now = [time, serverTime] select isMultiplayer;
private _reply = { params ["_kind", "_data"]; ["comspec_atak_native_adminData", [_kind, _data], _caller] call CBA_fnc_targetEvent; };
switch (_act) do {
    case "init": {
        // Pas de gestionnaire d'événement CBA : les demandes des clients arrivent par remoteExecCall vers 2, seule
        // voie qui laisse le serveur reconnaître le poste émetteur (contrôle en tête de cette fonction).
    };
    case "scan": {
        // Une demande toutes les 4 s au plus par admin.
        private _last = _caller getVariable ["COMSPEC_ATAK_AdminScanAt", -100];
        if ((_now - _last) < 4) exitWith {};
        _caller setVariable ["COMSPEC_ATAK_AdminScanAt", _now];
        private _to = allPlayers select { !(_x isKindOf "HeadlessClient_F") };
        // remoteExecCall (et non l'événement CBA comspec_atak_native_adminReport, que tout client pouvait lever) :
        // le joueur qui reçoit la demande voit alors qu'elle vient du serveur (remoteExecutedOwner == 2) et n'y répond
        // que dans ce cas. Le serveur reste seul juge du droit du demandeur (contrôle en tête de cette fonction).
        if (_to isNotEqualTo []) then { ["report", [_caller]] remoteExecCall ["comspec_atak_native_fnc_adminClient", _to]; };

        private _side = { private _s = side group _this; [str _s, "?"] select (isNull group _this) };
        private _caps = [];
        {
            private _c = _y;
            private _t = _c get "tag";
            private _ai = _c get "carrier";
            private _alive = !isNull _ai && {alive _ai};
            private _status = switch (true) do {
                case ((_c get "state") isEqualTo "validated"): { "cut" };
                case (_c get "wiped"): { "wiped" };
                case (isNull _ai): { "recovered" };
                case (!_alive): { "dead" };
                case (_now < (_c get "exploitAt")): { format ["crack:%1", round ((_c get "exploitAt") - _now)] };
                default { "exploit" };
            };
            private _hunters = (_c getOrDefault ["hunters", []]) select { !isNull _x && {(units _x) isNotEqualTo []} };
            _caps pushBack [
                _c get "id", _t param [2, ""], _t param [4, ""], _t param [3, ""], _t param [1, ""], _c get "state", _c get "by", _c get "athena",
                _c get "grid", _c get "hour", _status,
                [["", typeOf _ai] select _alive, ["", getText (configOf _ai >> "displayName")] select _alive, ["", _ai call _side] select _alive,
                    ["", groupId group _ai] select _alive, [[], getPosATL _ai] select _alive],
                _c get "lastGrid", round (_now - (_c getOrDefault ["lastRun", _now])),
                _hunters apply { [groupId _x, count units _x, (_x getVariable ["COMSPEC_ATAK_HuntPos", []]), str side _x] },
                _c getOrDefault ["targets", 0], _c get "pos", _t param [0, ""]
            ];
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap]);
        _caps sort false;
        // IA en route pour ramasser un téléphone : [type, camp, distance, position de l'IA, propriétaire, position du téléphone]
        private _going = [];
        {
            _y params [["_ai", objNull], ["_t0", 0]];
            private _o = objectFromNetId _x;
            if (isNull _ai || {!alive _ai} || {isNull _o}) then { continue };
            private _tag = _o getVariable ["COMSPEC_ATAK_PhoneTag", []];
            if (_tag isEqualTo []) then { _tag = (_o getVariable ["COMSPEC_ATAK_PhoneOwner", objNull]) getVariable ["COMSPEC_ATAK_PhoneTag", []]; };
            _going pushBack [getText (configOf _ai >> "displayName"), _ai call _side, round (_ai distance _o), getPosATL _ai, _tag param [2, "?"], getPosATL _o, round (_now - _t0)];
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_CapGo", createHashMap]);
        ["debug", [
            [missionNamespace getVariable ["comspec_atak_native_capture_enabled", true], missionNamespace getVariable ["comspec_atak_native_capture_radius", 100],
                missionNamespace getVariable ["comspec_atak_native_capture_hunt", true], missionNamespace getVariable ["comspec_atak_native_capture_hunt_radius", 3000],
                missionNamespace getVariable ["comspec_atak_native_capture_crack", 120]],
            _caps select [0, 20], _going select [0, 20], round _now
        ]] call _reply;
    };
    case "capture": {
        _args params [["_id", ""], ["_op", ""]];
        private _c = (missionNamespace getVariable ["COMSPEC_ATAK_Captures", createHashMap]) getOrDefault [_id, createHashMap];
        if ((count _c) isEqualTo 0) exitWith { ["result", [false, "Capture introuvable."]] call _reply; };
        private _who = [_caller] call comspec_atak_native_fnc_unitCallsign;
        switch (_op) do {
            case "validate";
            case "dismiss": {
                // Le gestionnaire valide aussi pour un camp qui n'est pas le sien (droit vérifié ci-dessus).
                _caller setVariable ["COMSPEC_ATAK_AdminOk", true];
                [_op, [_id, _caller]] call comspec_atak_native_fnc_captureServer;
                _caller setVariable ["COMSPEC_ATAK_AdminOk", nil];
                ["result", [true, ["Compromission rejetée.", "Compromission validée : le téléphone est coupé du réseau."] select (_op isEqualTo "validate")]] call _reply;
            };
            case "stopHunt": {
                {
                    if (!isNull _x) then {
                        _x setVariable ["COMSPEC_ATAK_NoHunt", true, true];
                        _x setVariable ["COMSPEC_ATAK_HuntPos", nil];
                        [_x] call CBA_fnc_clearWaypoints;
                    };
                } forEach ((_c getOrDefault ["hunters", []]) + [group (_c get "carrier")]);
                _c set ["hunters", []];
                ["result", [true, "Traque arrêtée pour les groupes de cette capture."]] call _reply;
            };
            case "recover": {
                private _ai = _c get "carrier";
                if (!isNull _ai) then {
                    private _cat = [] call comspec_atak_native_fnc_deviceCatalog;
                    {
                        if ((toLower _x) in _cat) then {
                            // IA locale au serveur : retrait fait ici (même code que la branche « local » de
                            // fn_captureServer). Par l'événement, CBA appelle le gestionnaire dans cette même pile, que
                            // fn_captureServer prend pour un appel du client admin : « local » y était refusée et l'IA
                            // gardait le téléphone, alors que l'app annonçait le contraire. IA locale à une autre
                            // machine (client sans interface) : l'événement part par le réseau, hors de cette pile.
                            if (local _ai) then {
                                if (_x in (assignedItems _ai)) then { _ai unlinkItem _x; } else { _ai removeItem _x; };
                            } else {
                                ["comspec_atak_native_capLocal", ["strip", _ai, _x], _ai] call CBA_fnc_targetEvent;
                            };
                        };
                    } forEach ((assignedItems _ai) + (items _ai));
                    _ai setVariable ["COMSPEC_ATAK_CapOf", nil, true];
                    _ai setVariable ["COMSPEC_ATAK_Ident", nil, true];
                };
                _c set ["carrier", objNull];
                _c set ["by", _who];
                ["result", [true, "Téléphone repris à l'IA : plus d'exploitation."]] call _reply;
            };
        };
        ["INFO", "ADMIN", format ["%1 : %2 par %3 (app Admin)", _id, _op, _who]] call comspec_atak_native_fnc_log;
        _caller setVariable ["COMSPEC_ATAK_AdminScanAt", -100];
        ["scan", [], _caller] call comspec_atak_native_fnc_adminServer;
    };
    case "hunt": {
        _args params [["_on", true]];
        missionNamespace setVariable ["comspec_atak_native_capture_hunt", _on, true];
        ["INFO", "ADMIN", format ["Traque par l'IA %1 par %2 (app Admin)", ["coupée", "rétablie"] select _on, name _caller]] call comspec_atak_native_fnc_log;
        ["result", [true, ["Traque par l'IA coupée pour la partie.", "Traque par l'IA rétablie."] select _on]] call _reply;
    };
    case "apps": {
        _args params [["_id", ""], ["_rule", "free"]];
        if (_rule isEqualTo "reset") exitWith {
            ["policy", [[], []]] call comspec_atak_native_fnc_appInstall;
            ["INFO", "ADMIN", format ["App Store : règles de la mission levées par %1 (app Admin)", name _caller]] call comspec_atak_native_fnc_log;
            ["result", [true, "Toutes les apps sont de nouveau au choix des joueurs (règles du serveur et d'Athena gardées)."]] call _reply;
        };
        if (!isClass (configFile >> "COMSPEC_ATAK_Apps" >> _id) || {!(_rule in ["required", "forbidden", "free"])}) exitWith {
            ["result", [false, "App ou règle inconnue."]] call _reply;
        };
        ["set", [_id, _rule]] call comspec_atak_native_fnc_appInstall;
        ["INFO", "ADMIN", format ["App Store : %1 %2 par %3 (app Admin)", _id, _rule, name _caller]] call comspec_atak_native_fnc_log;
        ["result", [true, format ["%1 : %2 pour la partie.", getText (configFile >> "COMSPEC_ATAK_Apps" >> _id >> "name"),
            (createHashMapFromArray [["required", "obligatoire"], ["forbidden", "interdite"], ["free", "au choix des joueurs"]]) get _rule]]] call _reply;
    };
};
true
