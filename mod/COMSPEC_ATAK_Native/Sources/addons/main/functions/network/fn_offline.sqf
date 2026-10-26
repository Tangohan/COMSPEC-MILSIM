/*
    Fonctionnement hors connexion du téléphone. Le signal vient de fn_linkQuality (relais, relief, brouilleurs…) :
    cette fonction ne calcule aucun état réseau, elle dit seulement quoi faire quand le téléphone ne reçoit plus.
      - sans signal (ou mode avion, batterie vide, pas de téléphone : même règle que fn_p2pReachable), le téléphone
        reste sur ses dernières données : fil Athena, boîte d'alertes, ordres et unités figés à l'heure de la perte ;
      - comptes rendus, MEDEVAC, alertes, ordres, appels JTAC, itinéraires reçus pendant ce temps sont retenus
        (l'émetteur les a bien envoyés, ils attendent au relais) et arrivent tous au retour du signal ;
      - au retour : synchro immédiate (Athena, ordres, alertes) et bilan de ce qui est arrivé ;
      - SMS, messages, ordres et alertes sont gardés dans le profil du joueur pour la mission en cours : après un
        crash ou une reconnexion au serveur, ils restent consultables même sans réseau.
    Les envois sans signal partent déjà en file (fn_netSend, vidée par la boucle de XEH_postInitClient).
    Réglages serveur : comspec_atak_native_offline_hold (retenir ce qui arrive hors réseau),
    comspec_atak_native_offline_save (garder les données entre deux connexions au serveur).
    Params : [action, arguments]
      ["online"]            le téléphone reçoit-il ? (booléen)
      ["tick"]              toutes les 2 s (XEH_postInitClient) : perte, retour, sauvegarde, restauration
      ["hold", [év, args]]  true si l'événement CBA est retenu jusqu'au retour du signal
      ["var", [nom, déf]]   variable de COMSPEC Link vue par le téléphone : figée hors réseau, sinon la valeur vive
                            (ou celle sauvegardée tant que la liaison n'a encore rien reçu)
      ["state"]             [hors ligne, heure de la dernière synchro, secondes hors ligne, nombre de données retenues]
    État : missionNamespace COMSPEC_ATAK_Offline (HashMap).
*/
params [["_act", "state"], ["_arg", []]];
private _st = missionNamespace getVariable ["COMSPEC_ATAK_Offline", createHashMap];
if ((count _st) isEqualTo 0) then {
    _st = createHashMapFromArray [["off", false], ["since", 0], ["sync", ""], ["snap", createHashMap], ["held", []], ["saved", createHashMap], ["restored", ""], ["saveAt", diag_tickTime + 20], ["saveN", 0]];
    missionNamespace setVariable ["COMSPEC_ATAK_Offline", _st];
};
private _hhmm = { [dayTime, "HH:MM"] call BIS_fnc_timeToString };
// Variables publiées par COMSPEC Link que le téléphone fige hors réseau.
private _frozen = ["COMSPEC_Comms_Messages", "COMSPEC_Athena_AlertInbox", "COMSPEC_Orders", "COMSPEC_IntelStore"];
// HashMap -> tableau de paires (profil) ; les objets du jeu ne sont pas gardés.
private _flat = { params ["_h"]; (keys _h) select { !((_h get _x) isEqualType objNull) } apply { [_x, _h get _x] } };

switch (toLower _act) do {
    case "online": {
        if !(["offlineHold"] call comspec_atak_native_fnc_netCond) exitWith { true };
        // Terminal de bord d'un véhicule (sans téléphone sur soi) : seul le signal compte.
        if (!([player] call comspec_atak_native_fnc_hasDevice) && {([player] call comspec_atak_native_fnc_vehicleTerminal) isNotEqualTo ""}) exitWith {
            private _q = [] call comspec_atak_native_fnc_linkQuality;
            !((_q getOrDefault ["sim", false]) && {(_q getOrDefault ["bars", 4]) isEqualTo 0})
        };
        [] call comspec_atak_native_fnc_p2pReachable
    };
    case "state": {
        [_st get "off", _st get "sync", [0, diag_tickTime - (_st get "since")] select (_st get "off"), count (_st get "held")]
    };
    case "var": {
        _arg params [["_name", ""], ["_def", []]];
        private _snap = _st get "snap";
        if ((_st get "off") && {_name in _snap}) exitWith { _snap get _name };
        private _live = missionNamespace getVariable [_name, _def];
        if (_live isEqualTo [] && {_name in (_st get "saved")}) exitWith { (_st get "saved") get _name };
        _live
    };
    case "hold": {
        _arg params [["_ev", ""], ["_args", []]];
        if (missionNamespace getVariable ["COMSPEC_ATAK_NetReplay", false]) exitWith { false };
        if (["online"] call comspec_atak_native_fnc_offline) exitWith { false };
        private _held = _st get "held";
        _held pushBack [_ev, _args];
        // Borne : sans réseau très longtemps, les plus anciennes données retenues sont perdues au-delà de 150.
        while { (count _held) > 150 } do { _held deleteAt 0; };
        true
    };
    case "tick": {
        if (!hasInterface || {isNull player}) exitWith {};
        private _on = ["online"] call comspec_atak_native_fnc_offline;
        private _off = _st get "off";
        private _bridge = [] call comspec_atak_native_fnc_bridge;
        switch (true) do {
            // Perte du signal : on fige ce que le téléphone affichait.
            case (!_off && {!_on}): {
                private _snap = createHashMap;
                { _snap set [_x, +(["var", [_x, []]] call comspec_atak_native_fnc_offline)]; } forEach _frozen;
                _st set ["snap", _snap];
                _st set ["off", true];
                _st set ["since", diag_tickTime];
                if ((_st get "sync") isEqualTo "") then { _st set ["sync", call _hhmm]; };
                ["INFO", "OFFLINE", format ["Hors connexion : données figées à %1", _st get "sync"]] call comspec_atak_native_fnc_log;
                // Téléphone éteint ou absent : rien à dire, il ne s'affiche pas.
                if ([player] call comspec_atak_native_fnc_hasDevice && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) > 0}) then {
                    ["WARNING", format ["Hors connexion : cartes, messages et ordres restent consultables (données de %1), envois en attente du réseau", _st get "sync"], 6, 45, "NETWORK"] call comspec_atak_native_fnc_notify;
                };
            };
            // Retour du signal : ce qui attendait arrive, synchro tout de suite au lieu d'attendre la boucle.
            case (_off && {_on}): {
                private _snap = _st get "snap";
                private _gone = diag_tickTime - (_st get "since");
                _st set ["off", false];
                _st set ["snap", createHashMap];
                private _newMsg = 0 max ((count (missionNamespace getVariable ["COMSPEC_Comms_Messages", []])) - (count (_snap getOrDefault ["COMSPEC_Comms_Messages", []])));
                private _newOrd = 0 max ((count (missionNamespace getVariable ["COMSPEC_Orders", []])) - (count (_snap getOrDefault ["COMSPEC_Orders", []])));
                private _newAlr = 0 max ((count (missionNamespace getVariable ["COMSPEC_Athena_AlertInbox", []])) - (count (_snap getOrDefault ["COMSPEC_Athena_AlertInbox", []])));
                private _held = _st get "held";
                _st set ["held", []];
                missionNamespace setVariable ["COMSPEC_ATAK_NetReplay", true];
                { _x params ["_ev", "_args"]; [_ev, _args] call CBA_fnc_localEvent; } forEach _held;
                missionNamespace setVariable ["COMSPEC_ATAK_NetReplay", false];
                // Débit recalculé et données relues maintenant.
                uiNamespace setVariable ["COMSPEC_ATAK_LinkQ", []];
                [] call comspec_atak_native_fnc_importLegacyData;
                [] call comspec_atak_native_fnc_remoteSync;
                if (_bridge && {!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])} && {!isNil "comspec_overwatch_connect_fnc_pollAuth"}) then { [] call comspec_overwatch_connect_fnc_pollAuth; };
                private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                _s set ["lastRemote", 0];
                _s set ["chatChannelsPoll", -100];
                _st set ["sync", call _hhmm];
                ["INFO", "OFFLINE", format ["Réseau revenu après %1 s : %2 message(s), %3 ordre(s), %4 alerte(s), %5 donnée(s) retenue(s)", round _gone, _newMsg, _newOrd, _newAlr, count _held]] call comspec_atak_native_fnc_log;
                private _bits = [];
                if (_newMsg > 0) then { _bits pushBack format ["%1 message(s)", _newMsg]; };
                if (_newOrd > 0) then { _bits pushBack format ["%1 ordre(s)", _newOrd]; };
                if (_newAlr > 0) then { _bits pushBack format ["%1 alerte(s)", _newAlr]; };
                if ((count _held) > 0) then { _bits pushBack format ["%1 compte(s) rendu(s) et appel(s)", count _held]; };
                if ([player] call comspec_atak_native_fnc_hasDevice && {_gone > 10}) then {
                    ["SUCCESS", format ["Synchro après %1 hors connexion%2", [format ["%1 s", round _gone], format ["%1 min", round (_gone / 60)]] select (_gone >= 90),
                        ["", " : " + (_bits joinString ", ") + " reçu(s)"] select ((count _bits) > 0)], 5, 40, "NETWORK"] call comspec_atak_native_fnc_notify;
                };
                if (!isNull ([] call comspec_atak_native_fnc_display)) then {
                    private _page = _s getOrDefault ["activePage", ""];
                    if (_page in ["CHAT", "TASK", "ALERTS", "NETWORK", "REPORTS", "MEDICAL"]) then { [{ [_this] call comspec_atak_native_fnc_pageRender; }, _page] call CBA_fnc_execNextFrame; };
                };
            };
            case (_on): { _st set ["sync", call _hhmm]; };
            default {};
        };

        // Sauvegarde dans le profil, pour cette mission (clé posée par le serveur à son lancement).
        private _key = missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", ""];
        if (_key isEqualTo "" || {!(missionNamespace getVariable ["comspec_atak_native_offline_save", true])}) exitWith {};
        if ((_st get "restored") isNotEqualTo _key) then {
            _st set ["restored", _key];
            private _sv = profileNamespace getVariable ["COMSPEC_ATAK_OfflineSave", []];
            if !(_sv isEqualType [] && {(_sv param [0, ""]) isEqualTo _key}) exitWith {};
            _sv params ["", ["_at", ""], ["_p2p", []], ["_msgs", []], ["_inbox", []], ["_tasks", []], ["_alerts", []]];
            private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
            private _n = 0;
            if ((_data getOrDefault ["p2p", []]) isEqualTo [] && {(count _p2p) > 0}) then {
                _data set ["p2p", _p2p apply { createHashMapFromArray _x }];
                _n = _n + count _p2p;
            };
            if ((_data getOrDefault ["inbox", []]) isEqualTo [] && {(count _inbox) > 0}) then { _data set ["inbox", _inbox apply { createHashMapFromArray _x }]; };
            if ((count _tasks) > 0) then { _data set ["savedTasks", createHashMapFromArray (_tasks apply { [_x select 0, createHashMapFromArray (_x select 1)] })]; };
            private _saved = createHashMap;
            if ((count _msgs) > 0) then { _saved set ["COMSPEC_Comms_Messages", _msgs]; _n = _n + count _msgs; };
            if ((count _alerts) > 0) then { _saved set ["COMSPEC_Athena_AlertInbox", _alerts]; };
            _st set ["saved", _saved];
            ["INFO", "OFFLINE", format ["Données du téléphone reprises (sauvegarde de %1) : %2 SMS, %3 messages, %4 ordres", _at, count _p2p, count _msgs, count _tasks]] call comspec_atak_native_fnc_log;
            if (_n > 0) then { ["INFO", format ["Données du téléphone récupérées (enregistrées à %1) : consultables même sans réseau", _at], 5, 30, "NETWORK"] call comspec_atak_native_fnc_notify; };
        };
        if (diag_tickTime < (_st get "saveAt")) exitWith {};
        _st set ["saveAt", diag_tickTime + 30];
        private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
        private _p2p = (_data getOrDefault ["p2p", []]) select [((count (_data getOrDefault ["p2p", []])) - 100) max 0, 100];
        private _msgs = ["var", ["COMSPEC_Comms_Messages", []]] call comspec_atak_native_fnc_offline;
        if !(_msgs isEqualType []) then { _msgs = []; };
        _msgs = _msgs select [((count _msgs) - 150) max 0, 150];
        private _alerts = ["var", ["COMSPEC_Athena_AlertInbox", []]] call comspec_atak_native_fnc_offline;
        if !(_alerts isEqualType []) then { _alerts = []; };
        _alerts = (_alerts select [((count _alerts) - 50) max 0, 50]) select { _x isEqualType [] };
        private _tasks = [] call comspec_atak_native_fnc_tasksAll;
        profileNamespace setVariable ["COMSPEC_ATAK_OfflineSave", [
            _key, call _hhmm,
            _p2p apply { [_x] call _flat },
            _msgs,
            ((_data getOrDefault ["inbox", []]) select { _x isEqualType createHashMap }) apply { [_x] call _flat },
            (keys _tasks) select { (_tasks get _x) isEqualType createHashMap } apply { [_x, [_tasks get _x] call _flat] },
            _alerts
        ]];
        // Écrit sur le disque toutes les 2 min seulement (survit à un crash du jeu sans saccade à chaque sauvegarde).
        private _n = (_st getOrDefault ["saveN", 0]) + 1;
        _st set ["saveN", _n];
        if ((_n mod 4) isEqualTo 1) then { [] call comspec_atak_native_fnc_saveProfile; };
    };
};
