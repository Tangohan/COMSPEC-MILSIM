/*
    App Admin de l'ATAK, côté client : fiches des joueurs de la partie, debug de l'IA, parc Athena de la communauté.
    Deux droits séparés :
      - en jeu (Zeus, hôte, admin connecté, gestionnaire désigné : fn_adminAllowed, vérifié par le serveur) : ATAK des
        joueurs de la partie, effets Zeus par joueur (fn_zeusServer), téléphones ramassés par l'IA et traque (fn_adminServer) ;
      - Athena (gestionnaire de la communauté ou administrateur de la plateforme sur le site, vérifié par le site) : parc
        ATAK de la communauté, comme /back-office/atak/parc-atak (COMSPEC Link « GameAdmin.Fleet » / « GameAdmin.Action »).
    Mémoire (uiNamespace) : COMSPEC_ATAK_AdminPlayers (uid -> [fiche, reçue à]), COMSPEC_ATAK_AdminDebug, COMSPEC_ATAK_AdminFleet
      [reçu à, droit, total, fiches, erreur, recherche, début], COMSPEC_ATAK_AdminRole (droit Athena connu : "platform" |
      "manager" | "none" | "").
    Params : [action, arguments]
      "report" [demandeur]  ma fiche, envoyée directement au demandeur (événement du serveur, seulement quand un admin regarde) :
                [uid, nom, indicatif, camp, groupe, ATAK porté, batterie %, état de l'appareil, clé Athena, état Athena,
                 version du mod, version de Link, effets Zeus [[clé, valeur]...], numéro, IMEI, terminal Athena, SOS, balise BFT,
                 position, compromission (fn_compromiseView), réseau]
      "data" [genre, données]   réponse du serveur ou d'un joueur ("player" | "debug" | "result" | "denied")
      "scan"                    demande l'état de la partie (au plus toutes les 4 s côté serveur)
      "fleet" [recherche, début] parc Athena (spawn)        "probe"   droit Athena, sans afficher (spawn)
      "site" [action, HashMap]  action Athena sur un ATAK du parc (spawn)
      "zeus" [uid, clé, valeur] effet Zeus durable sur l'ATAK d'un joueur ; clé "reset" : tout rétablir ; "once" : [uid, "once", [type, args]]
      "server" [action, args]   action serveur (fn_adminServer : "capture", "hunt")
      "visible"                 l'app est-elle montrée dans le lanceur ?
*/
params [["_act", ""], ["_arg", []]];
// Appel distant : seul le serveur (poste 2) entre ici. Un autre poste client est refusé.
// Avant, l'événement CBA comspec_atak_native_adminReport était enregistré chez tout le monde (XEH_preInit) et
// « report » répondait à n'importe quel demandeur annoncé dans le message : un client qui levait l'événement vers
// tous les joueurs obtenait UID Steam, camp, groupe, étiquette de téléphone et position exacte de toute la partie,
// répétable à volonté. Le droit ne peut pas être jugé ici : un admin #login n'est reconnaissable que du serveur
// (fn_adminAllowed.sqf:14). Le client ne juge donc pas le droit, il exige seulement que la demande vienne du serveur.
// Les autres actions (scan, zeus, server, fleet, site) agissent au nom du joueur local : elles n'ont pas non plus à
// être déclenchables par un autre poste. Un appel rejoué depuis la file JIP est refusé lui aussi : aucun ordre du
// serveur n'emprunte cette file, et la valeur de remoteExecutedOwner pendant le rejeu chez un joueur qui arrive n'est
// pas établie (client d'origine, 0 ou 2). isRemoteExecutedJIP est lu en premier, seul : le refus ne dépend pas de ce
// que isRemoteExecuted vaut pendant un rejeu.
if (isRemoteExecutedJIP || {isRemoteExecuted && {remoteExecutedOwner isNotEqualTo 2}}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][ADMIN] Appel « %1 » refusé : il ne vient pas du serveur ou il est rejoué depuis la file JIP (poste %2, file JIP : %3)", _act, remoteExecutedOwner, isRemoteExecutedJIP];
};
private _render = {
    if (uiNamespace getVariable ["COMSPEC_ATAK_AdminRenderQ", false]) exitWith {};
    uiNamespace setVariable ["COMSPEC_ATAK_AdminRenderQ", true];
    [{
        uiNamespace setVariable ["COMSPEC_ATAK_AdminRenderQ", false];
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        // Message imposé en cours de saisie : pas de redessin (il effacerait le texte).
        if ((_st getOrDefault ["activePage", ""]) isEqualTo "ADMIN" && {(_st getOrDefault ["admMsgFor", 0]) isEqualTo 0}) then { ["ADMIN"] call comspec_atak_native_fnc_pageRender; };
    }, [], 0.3] call CBA_fnc_waitAndExecute;
};
private _ext = {
    params ["_cmd", "_payload"];
    private _r = ["COMSPECExtension", _cmd, [[_payload] call comspec_atak_native_fnc_json]] call comspec_atak_native_fnc_extAwait;
    private _out = _r param [0, ""];
    if !(_out isEqualType "") then { _out = str _out; };
    _out
};
switch (_act) do {
    case "report": {
        _arg params [["_to", objNull]];
        if (!hasInterface || {isNull player} || {isNull _to}) exitWith {};
        private _st = [] call comspec_atak_native_fnc_athenaStatus;
        private _hasAtak = [player, true] call comspec_atak_native_fnc_hasDevice;
        private _tag = player getVariable ["COMSPEC_ATAK_PhoneTag", []];
        private _cv = ["get"] call comspec_atak_native_fnc_compromiseView;
        private _row = [
            getPlayerUID player, name player, [player] call comspec_atak_native_fnc_unitCallsign, str side group player, groupId group player,
            _hasAtak, round (missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]), ([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["state", "OK"],
            _st getOrDefault ["key", ""], _st getOrDefault ["title", ""],
            missionNamespace getVariable ["COMSPEC_ATAK_NativeBuild", ""], missionNamespace getVariable ["COMSPEC_ExtensionVersion", ""],
            (player getVariable ["COMSPEC_ATAK_ZeusFx", []]) apply { [_x param [0, ""], _x param [1, ""]] },
            _tag param [3, ""], _tag param [4, ""], _tag param [5, ""],
            player getVariable ["COMSPEC_ATAK_SimSos", false], player getVariable ["COMSPEC_ATAK_Beacon", false],
            getPosATL player, _cv getOrDefault ["state", ""],
            (player getVariable ["COMSPEC_ATAK_NetUp", []]) param [0, -1]
        ];
        ["comspec_atak_native_adminData", ["player", _row], _to] call CBA_fnc_targetEvent;
    };
    case "data": {
        _arg params [["_kind", ""], ["_d", []]];
        switch (_kind) do {
            case "player": {
                private _m = uiNamespace getVariable ["COMSPEC_ATAK_AdminPlayers", createHashMap];
                uiNamespace setVariable ["COMSPEC_ATAK_AdminPlayers", _m];
                _m set [_d param [0, ""], [_d, diag_tickTime]];
            };
            case "debug": { uiNamespace setVariable ["COMSPEC_ATAK_AdminDebug", [_d, diag_tickTime]]; };
            case "result": {
                _d params [["_ok", false], ["_msg", ""]];
                [["WARNING", "SUCCESS"] select _ok, _msg, 5, 40, "ADMIN"] call comspec_atak_native_fnc_notify;
            };
            case "denied": {
                uiNamespace setVariable ["COMSPEC_ATAK_AdminDenied", diag_tickTime];
                ["WARNING", "Le serveur refuse : il faut être Zeus, admin du serveur ou gestionnaire désigné.", 6, 40, "ADMIN"] call comspec_atak_native_fnc_notify;
            };
        };
        call _render;
    };
    case "scan": {
        if !([player] call comspec_atak_native_fnc_adminAllowed) exitWith {};
        // Joueurs partis : retirés au bout de 30 s sans réponse.
        private _m = uiNamespace getVariable ["COMSPEC_ATAK_AdminPlayers", createHashMap];
        { if ((diag_tickTime - (_y select 1)) > 30) then { _m deleteAt _x; }; } forEach (+_m);
        // remoteExecCall : le serveur retrouve le demandeur par remoteExecutedOwner (fn_adminServer).
        ["scan", []] remoteExecCall ["comspec_atak_native_fnc_adminServer", 2];
    };
    case "probe";
    case "fleet": {
        _arg params [["_q", ""], ["_off", 0]];
        if (([] call comspec_atak_native_fnc_athenaMode) isEqualTo "off") exitWith {};
        if (uiNamespace getVariable ["COMSPEC_ATAK_AdminFleetBusy", false]) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_AdminFleetBusy", true];
        [_act, _q, _off, _ext, _render] spawn {
            params ["_act", "_q", "_off", "_ext", "_render"];
            private _out = ["GameAdmin.Fleet", createHashMapFromArray [["q", _q], ["offset", _off]]] call _ext;
            uiNamespace setVariable ["COMSPEC_ATAK_AdminFleetBusy", false];
            private _ok = (_out select [0, 3]) isEqualTo "OK|";
            private _lines = (_out select [3]) splitString toString [10];
            private _head = (_lines param [0, ""]) splitString toString [9];
            private _role = ["", _head param [0, "none"]] select _ok;
            // Session absente ou DLL sans « GameAdmin » : droit inconnu, l'app le dit.
            if (_ok || {(_out find "session_required") >= 0}) then { uiNamespace setVariable ["COMSPEC_ATAK_AdminRole", [_role, "none"] select (_role isEqualTo "")]; };
            if (_act isEqualTo "probe") exitWith {};
            // COMSPEC Link écrit « - » pour un champ vide (splitString avalerait la colonne).
            private _rows = (_lines select [1]) apply { (_x splitString toString [9]) apply { ["", _x] select (_x isNotEqualTo "-") } };
            uiNamespace setVariable ["COMSPEC_ATAK_AdminFleet", [diag_tickTime, _role, parseNumber (_head param [1, "0"]), _rows,
                ["", _out select [4]] select !_ok, _q, _off]];
            call _render;
        };
    };
    case "site": {
        _arg params [["_a", ""], ["_p", createHashMap]];
        _p set ["action", _a];
        [_p, _ext] spawn {
            params ["_p", "_ext"];
            private _out = ["GameAdmin.Action", _p] call _ext;
            private _ok = (_out select [0, 3]) isEqualTo "OK|";
            private _msg = _out select [[4, 3] select _ok];
            // Réponse vide : COMSPEC Link trop ancien (sans « GameAdmin »).
            if (_msg isEqualTo "") then { _msg = ["Action refusée : mettez à jour COMSPEC Link.", "Fait."] select _ok; };
            [["WARNING", "SUCCESS"] select _ok, _msg, 6, 40, "ADMIN"] call comspec_atak_native_fnc_notify;
            private _f = uiNamespace getVariable ["COMSPEC_ATAK_AdminFleet", []];
            ["fleet", [_f param [5, ""], _f param [6, 0]]] call comspec_atak_native_fnc_adminClient;
        };
    };
    case "zeus": {
        _arg params [["_uid", ""], ["_k", ""], ["_v", true]];
        private _i = allPlayers findIf { (getPlayerUID _x) isEqualTo _uid };
        if (_i < 0) exitWith { ["WARNING", "Joueur introuvable (parti ?).", 4, 40, "ADMIN"] call comspec_atak_native_fnc_notify; };
        private _u = allPlayers select _i;
        switch (_k) do {
            // remoteExecCall : le serveur retrouve le demandeur par remoteExecutedOwner (fn_zeusServer).
            case "reset": { ["reset", [[_u]]] remoteExecCall ["comspec_atak_native_fnc_zeusServer", 2]; };
            case "once": { ["once", [[_u], _v param [0, ""], _v param [1, []]]] remoteExecCall ["comspec_atak_native_fnc_zeusServer", 2]; };
            default { ["set", [[_u], _k, _v, 0]] remoteExecCall ["comspec_atak_native_fnc_zeusServer", 2]; };
        };
        ["INFO", format ["%1 : demande envoyée au serveur", name _u], 3, 30, "ADMIN"] call comspec_atak_native_fnc_notify;
        // Effet visible au prochain relevé (variable publique du joueur).
        [{ ["scan"] call comspec_atak_native_fnc_adminClient; }, [], 1.5] call CBA_fnc_waitAndExecute;
    };
    case "server": {
        _arg params [["_a", ""], ["_xa", []]];
        [_a, _xa] remoteExecCall ["comspec_atak_native_fnc_adminServer", 2];
    };
    case "visible": {
        // Montrée au Zeus, à l'admin du serveur, au gestionnaire désigné et au gestionnaire Athena (droit déjà connu).
        [player] call comspec_atak_native_fnc_adminAllowed || {(uiNamespace getVariable ["COMSPEC_ATAK_AdminRole", ""]) in ["manager", "platform"]}
    };
};
