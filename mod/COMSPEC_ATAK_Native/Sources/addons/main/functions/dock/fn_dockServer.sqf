/*
    Socle de recharge CS-10 (objet COMSPEC_ATAK_ChargingDock, 10 emplacements), côté serveur : seul le serveur
    écrit l'état des emplacements, le recharge, le mémorise et l'affiche (fn_dockVisual).

    Emplacement : [] (libre) ou [UID du propriétaire, nom, objet téléphone, charge 0-100, état de l'appareil
    (paires de COMSPEC_ATAK_Device), couleur de coque, posé à (serverTime)].
    Mémoire : localNamespace COMSPEC_ATAK_DockDb, identifiant du socle -> [emplacements, pose, enregistré à (s UTC)],
    écrite dans le profil du serveur (profileNamespace COMSPEC_ATAK_DockDb_v1) : un téléphone laissé sur un socle
    se retrouve à la partie suivante, chargé du temps écoulé (réglage « La charge continue entre deux parties »).
    Pose : [] pour un socle de la mission (Eden, Zeus), [monde, posASL, vectorDir, vectorUp, UID] pour un socle
    posé avec le kit, recréé au lancement de la mission sur la même carte (réglage « Mémoriser les socles posés »).
    Identifiant : attribut Eden « Identifiant du socle » (le même d'une mission à l'autre), sinon carte + position.
    Kit socle (Item_COMSPEC_ATAK_DockKit) posé par Zeus en cours de partie : remplacé par le socle déplié ("zeusKit").
    Clients -> serveur : [action, arguments] remoteExecCall ["comspec_atak_native_fnc_dockServer", 2] (fn_dockClient),
    jugées sur le demandeur retrouvé par remoteExecutedOwner (fn_reqUnit), jamais sur une unité reçue :
      "put"    [socle, emplacement (-1 : premier libre), téléphone, charge, état de l'appareil, couleur de coque]
      "take"   [socle, emplacement]          "deploy" [posASL, direction]          "pack" [socle]
    Réponse au demandeur : ["result", [action, ok, message, données]] remoteExecCall vers fn_dockClient, qui n'accepte
    que le serveur.
    Lot mpauth3 (SEC-025) : plus d'événements CBA comspec_atak_native_dockPut, _dockTake, _dockDeploy, _dockPack ni
    comspec_atak_native_dockResult. L'unité venait de l'émetteur : tout client posait ou reprenait un téléphone au nom
    d'un autre joueur, posait sans fin des socles près de n'importe qui, gardés dans le profil du serveur et recréés à
    chaque partie, et faisait rendre un objet, une charge ou un état d'appareil au téléphone d'un autre. Bornes : 10
    socles du kit au plus par joueur (UID, toutes cartes), 300 socles au plus dans le profil (les plus récemment vus
    d'abord), écrit au plus toutes les 5 s ; socle, téléphone (catalogue fn_deviceCatalog), état de l'appareil et
    couleur de coque vérifiés. Identifiant et genre (kit ou mission) d'un socle : registre du serveur (localNamespace
    COMSPEC_ATAK_DockReg), plus ses variables publiques COMSPEC_ATAK_DockId et COMSPEC_ATAK_DockKit, que tout client
    peut écrire (elles restent écrites pour les menus des joueurs).

    Params : [action, arguments]
*/
params [["_act", "init"], ["_a", []]];
if (!isServer) exitWith {};
if (isRemoteExecutedJIP) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][DOCK] Demande « %1 » refusée (rejouée depuis la file JIP)", _act];
};
// Appel distant d'un client : ses quatre demandes seulement, pour le joueur de son poste.
private _remote = isMultiplayer && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2};
if (_remote && {!(_act in ["put", "take", "deploy", "pack"])}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][DOCK] Action « %1 » refusée à un appel distant (poste %2)", _act, remoteExecutedOwner];
};
private _req = [objNull, player] select hasInterface;
if (_remote) then { _req = [remoteExecutedOwner] call comspec_atak_native_fnc_reqUnit; };
if (_remote && {isNull _req}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][DOCK] Demande « %1 » refusée au poste %2 (demandeur introuvable)", _act, remoteExecutedOwner];
};
if !(_a isEqualType []) exitWith {};
private _db = localNamespace getVariable ["COMSPEC_ATAK_DockDb", createHashMap];
private _class = "COMSPEC_ATAK_ChargingDock";
// Bornes de la mémoire (lot mpauth3, SEC-025) : socles du kit par joueur (UID, toutes cartes), socles du profil.
private _kitMax = 10;
private _dbMax = 300;
// Secondes UTC (assez juste pour un écart entre deux parties).
private _utc = {
    systemTimeUTC params ["_y", "_mo", "_d", "_h", "_mi", "_s"];
    private _days = 365 * _y + floor ((_y - 1) / 4) + ([0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334] select (_mo - 1)) + _d;
    if (_mo > 2 && {_y % 4 == 0}) then { _days = _days + 1; };
    ((_days * 24 + _h) * 60 + _mi) * 60 + _s
};
// Réponse au demandeur : remoteExecCall vers fn_dockClient « result », qui n'accepte que le serveur. Joueur local au
// serveur (hôte d'une partie locale, solo) : appel à l'image suivante, hors de la pile de la demande en cours.
private _reply = {
    params ["_u", "_what", "_ok", "_msg", ["_data", []]];
    if (isNull _u) exitWith {};
    if (local _u) exitWith { [{ _this call comspec_atak_native_fnc_dockClient; }, ["result", [_what, _ok, _msg, _data]]] call CBA_fnc_execNextFrame; };
    ["result", [_what, _ok, _msg, _data]] remoteExecCall ["comspec_atak_native_fnc_dockClient", _u];
};
private _empty = { private _s = []; for "_i" from 1 to 10 do { _s pushBack []; }; _s };
private _rate = { (missionNamespace getVariable ["comspec_atak_native_dock_rate", 1.5]) max 0.1 };
// Registre du serveur [socle, identifiant, kit] (localNamespace) : identifiant et genre d'un socle y sont lus, et plus
// dans ses variables publiques COMSPEC_ATAK_DockId et COMSPEC_ATAK_DockKit (écrites ici pour les menus des joueurs).
private _register = {
    params ["_o", "_id", "_kit"];
    _o setVariable ["COMSPEC_ATAK_DockId", _id, true];
    if (_kit) then { _o setVariable ["COMSPEC_ATAK_DockKit", true, true]; };
    localNamespace setVariable ["COMSPEC_ATAK_DockReg", ((localNamespace getVariable ["COMSPEC_ATAK_DockReg", []]) select { !isNull (_x select 0) && {(_x select 0) isNotEqualTo _o} }) + [[_o, _id, _kit]]];
};
// Socle du kit (posé avec le kit, recréé au lancement, déplié par Zeus) : seul le registre du serveur le dit.
private _kitOf = {
    params ["_dock"];
    private _reg = localNamespace getVariable ["COMSPEC_ATAK_DockReg", []];
    private _i = _reg findIf { (_x select 0) isEqualTo _dock };
    _i >= 0 && {(_reg select _i) select 2}
};
// Identifiant du socle, posé une fois sur l'objet (il le garde s'il est déplacé par Zeus) ; fiche créée au besoin.
private _idOf = {
    params ["_dock"];
    private _reg = localNamespace getVariable ["COMSPEC_ATAK_DockReg", []];
    private _i = _reg findIf { (_x select 0) isEqualTo _dock };
    private _id = "";
    if (_i >= 0) then { _id = (_reg select _i) select 1; } else {
        // Première vue d'un socle de la mission (au lancement, ou posé ensuite par Zeus) : attribut Eden « Identifiant
        // du socle », sinon carte + position.
        _id = _dock getVariable ["COMSPEC_ATAK_DockId", ""];
        if (!(_id isEqualType "") || {_id isEqualTo ""} || {(count _id) > 100}) then {
            private _p = getPosASL _dock;
            _id = format ["%1|%2|%3|%4", worldName, round (_p select 0), round (_p select 1), round (_p select 2)];
        };
        [_dock, _id, false] call _register;
    };
    if !(_id in _db) then { _db set [_id, [call _empty, [], call _utc]]; };
    _id
};
// État public du socle (lu par les menus) et textures (téléphones, écrans, voyants).
private _publish = {
    params ["_dock", "_slots"];
    private _pub = _slots apply { if ((count _x) < 6) then { [] } else { [_x select 0, _x select 1, floor (_x select 3), _x select 5, _x select 2] } };
    if ((_dock getVariable ["COMSPEC_ATAK_DockSlots", []]) isNotEqualTo _pub) then {
        _dock setVariable ["COMSPEC_ATAK_DockSlots", _pub, true];
    };
    [_dock, _pub] call comspec_atak_native_fnc_dockVisual;
};
// Profil du serveur : socles utilisés, et socles du kit (place) si le réglage le permet ; 300 au plus, les plus
// récemment vus d'abord. Une demande de joueur écrit ce profil (saveProfileNamespace réécrit tout le profil) : une
// écriture toutes les 5 s au plus, la suivante est laissée au prochain « tick » (10 s) ; fin de partie : tout de suite.
private _save = {
    params [["_force", false]];
    if (!_force && {(serverTime - (localNamespace getVariable ["COMSPEC_ATAK_DockSaved", -1e9])) < 5}) exitWith { localNamespace setVariable ["COMSPEC_ATAK_DockLater", true]; };
    localNamespace setVariable ["COMSPEC_ATAK_DockLater", false];
    localNamespace setVariable ["COMSPEC_ATAK_DockSaved", serverTime];
    private _keep = missionNamespace getVariable ["comspec_atak_native_dock_persist", true];
    private _out = [];
    {
        private _rec = _y;
        private _used = ((_rec select 0) findIf { (count _x) > 0 }) >= 0;
        // Socles vides de la mission : rien à retenir. Socles du kit : gardés (place) si le réglage le permet.
        if (_used || {_keep && {(count (_rec select 1)) > 0}}) then { _out pushBack [_x, _rec select 0, _rec select 1, _rec select 2]; };
    } forEach _db;
    if ((count _out) > _dbMax) then { _out = ([_out, [], { _x select 3 }, "DESCEND"] call BIS_fnc_sortBy) select [0, _dbMax]; };
    profileNamespace setVariable ["COMSPEC_ATAK_DockDb_v1", _out];
    [] call comspec_atak_native_fnc_saveProfile;
};

switch (_act) do {
    case "init": {
        localNamespace setVariable ["COMSPEC_ATAK_DockDb", _db];
        private _now = call _utc;
        private _offline = missionNamespace getVariable ["comspec_atak_native_dock_offline", true];
        private _rows = profileNamespace getVariable ["COMSPEC_ATAK_DockDb_v1", []];
        if !(_rows isEqualType []) then { _rows = []; };
        // Mémoire bornée (lot mpauth3, SEC-025) : les 300 socles vus le plus récemment. Une mémoire plus grosse, ou mal
        // formée, vient d'avant cette borne : relue avec prudence.
        if ((count _rows) > _dbMax) then {
            _rows = ([_rows select { _x isEqualType [] && {(_x param [3, ""]) isEqualType 0} }, [], { _x select 3 }, "DESCEND"] call BIS_fnc_sortBy) select [0, _dbMax];
        };
        {
            _x params [["_id", ""], ["_slots", []], ["_place", []], ["_at", _now]];
            if (!(_id isEqualType "") || {_id isEqualTo ""} || {(count _id) > 100} || {!(_slots isEqualType [])} || {(count _slots) != 10} || {!(_place isEqualType [])} || {!(_at isEqualType 0)}) then { continue };
            // Emplacement mal formé : vidé.
            _slots = _slots apply { [[], _x] select (_x isEqualType [] && {_x isEqualTypeArray ["", "", "", 0, [], "", 0]}) };
            // Charge pendant l'absence du serveur.
            if (_offline) then {
                private _gain = (call _rate) * ((_now - _at) max 0) / 60;
                { if ((count _x) > 3) then { _x set [3, ((_x select 3) + _gain) min 100]; }; } forEach _slots;
            };
            _db set [_id, [_slots, _place, _now]];
        } forEach _rows;
        // Socles posés avec le kit sur cette carte : recréés à leur place.
        if (missionNamespace getVariable ["comspec_atak_native_dock_persist", true]) then {
            {
                private _rec = _db get _x;
                (_rec select 1) params [["_world", ""], ["_pos", []], ["_dir", [0, 1, 0]], ["_up", [0, 0, 1]]];
                if (_world isEqualTo worldName && {_pos isEqualType []} && {_pos isEqualTypeArray [0, 0, 0]}) then {
                    private _o = createVehicle [_class, ASLToATL _pos, [], 0, "CAN_COLLIDE"];
                    _o setVectorDirAndUp [_dir, _up];
                    _o setPosASL _pos;
                    [_o, _x, true] call _register;
                };
            } forEach (keys _db);
        };
        // Kit socle posé en cours de partie (Zeus) : la valise pliée devient le socle déplié, rangeable comme un socle
        // posé avec le kit. Un kit placé dans l'éditeur (time = 0) reste une valise à ramasser.
        ["Item_COMSPEC_ATAK_DockKit", "init", {
            params ["_h"];
            if (time <= 0 || {is3DEN}) exitWith {};
            [{
                params ["_h"];
                if (isNull _h) exitWith {};
                ["zeusKit", [_h]] call comspec_atak_native_fnc_dockServer;
            }, [_h]] call CBA_fnc_execNextFrame;
        }, false, [], false] call CBA_fnc_addClassEventHandler;
        // Filet si le porte-objet n'a pas les événements étendus CBA : création d'entité côté serveur.
        addMissionEventHandler ["EntityCreated", {
            params ["_h"];
            if (time <= 0 || {!(_h isKindOf "Item_COMSPEC_ATAK_DockKit")}) exitWith {};
            [{
                params ["_h"];
                if (isNull _h) exitWith {};
                ["zeusKit", [_h]] call comspec_atak_native_fnc_dockServer;
            }, [_h]] call CBA_fnc_execNextFrame;
        }];
        // Demandes des joueurs : remoteExecCall vers ce serveur (fn_dockClient), plus d'événements CBA (lot mpauth3,
        // SEC-025). Socles de la mission : identifiant lu une fois, au lancement, avant qu'un client puisse l'écrire.
        { [_x] call _idOf; } forEach (allMissionObjects _class);
        [{ ["tick"] call comspec_atak_native_fnc_dockServer; }, 10] call CBA_fnc_addPerFrameHandler;
        addMissionEventHandler ["MPEnded", { ["save"] call comspec_atak_native_fnc_dockServer; }];
        addMissionEventHandler ["Ended", { ["save"] call comspec_atak_native_fnc_dockServer; }];
        ["INFO", "DOCK", format ["Socles de recharge en mémoire : %1", count _db]] call comspec_atak_native_fnc_log;
    };
    // Identifiant du socle (fiche créée au besoin), pour un script du serveur.
    case "id": {
        _a params ["_dock"];
        [_dock] call _idOf
    };
    case "tick": {
        private _now = serverTime;
        private _dt = (_now - (localNamespace getVariable ["COMSPEC_ATAK_DockTick", _now])) min 60;
        localNamespace setVariable ["COMSPEC_ATAK_DockTick", _now];
        private _gain = (call _rate) * _dt / 60;
        private _utcNow = call _utc;
        // Liste des socles : allMissionObjects (parcourt tous les objets de la mission) une fois par minute
        // seulement ; les socles posés avec le kit y sont ajoutés dès leur création (« deploy »).
        private _docks = localNamespace getVariable ["COMSPEC_ATAK_DockObjs", []];
        if (_now - (localNamespace getVariable ["COMSPEC_ATAK_DockScan", -1e9]) > 60) then {
            localNamespace setVariable ["COMSPEC_ATAK_DockScan", _now];
            _docks = allMissionObjects _class;
        };
        _docks = _docks select { !isNull _x };
        localNamespace setVariable ["COMSPEC_ATAK_DockObjs", _docks];
        // Sauvegarde utile seulement si une charge ou une place a changé.
        private _dirty = false;
        {
            private _dock = _x;
            private _id = [_dock] call _idOf;
            private _rec = _db get _id;
            private _slots = _rec select 0;
            { if ((count _x) > 3 && {(_x select 3) < 100}) then { _x set [3, ((_x select 3) + _gain) min 100]; _dirty = true; }; } forEach _slots;
            // Socle du kit déplacé par Zeus : nouvelle place mémorisée.
            if ([_dock] call _kitOf) then {
                private _pl = _rec select 1;
                if ((count _pl) > 1 && {((_pl select 1) distance (getPosASL _dock)) > 0.05}) then {
                    _rec set [1, [worldName, getPosASL _dock, vectorDir _dock, vectorUp _dock, _pl param [4, ""]]];
                    _dirty = true;
                };
            };
            _rec set [2, _utcNow];
            [_dock, _slots] call _publish;
        } forEach _docks;
        // Sauvegarde du profil serveur (saveProfileNamespace réécrit tout le profil : à-coup sur le serveur) toutes les
        // 5 min au plus, et seulement si un téléphone charge ou un socle a bougé (sinon à chaque dépôt, retrait et en fin de partie).
        // Écriture d'une demande remise à plus tard (_save, 5 s au plus entre deux écritures) : faite ici.
        if ((localNamespace getVariable ["COMSPEC_ATAK_DockLater", false]) || {_dirty && {_now - (localNamespace getVariable ["COMSPEC_ATAK_DockSaved", -1e9]) > 300}}) then { [false] call _save; };
    };
    // État public du socle (_publish) et sauvegarde du profil (_save, fin de partie : tout de suite), pour un script du serveur.
    case "publish": {
        _a params ["_dock", "_slots"];
        [_dock, _slots] call _publish;
    };
    case "save": { [true] call _save; };
    case "put": {
        _a params [["_dock", objNull], ["_slot", -1], ["_item", ""], ["_level", 100], ["_device", []], ["_tint", "coyote"]];
        if (isNull _req || {!(_item isEqualType "")} || {_item isEqualTo ""}) exitWith {};
        // Socle replié ou supprimé (Zeus) avant l'arrivée de la demande : le téléphone, déjà retiré côté joueur, lui est
        // rendu. Toute demande refusée le lui rend de même.
        if (!(_dock isEqualType objNull) || {isNull _dock} || {!(_dock isKindOf _class)}) exitWith { [_req, "put", false, "Socle replié", [_item]] call _reply; };
        // Seul un terminal du catalogue (fn_deviceCatalog, réglages du serveur) se pose sur le socle.
        if !((toLower _item) in ([] call comspec_atak_native_fnc_deviceCatalog)) exitWith { [_req, "put", false, "Pas de téléphone sur vous", [_item]] call _reply; };
        if (!(_slot isEqualType 0) || {!(_level isEqualType 0)}) exitWith { [_req, "put", false, "Emplacement occupé ou socle plein", [_item]] call _reply; };
        // État de l'appareil borné (paires de COMSPEC_ATAK_Device : usure, taches… quelques milliers de caractères au plus),
        // sinon oublié ; couleur de coque connue, sinon coyote.
        if (!(_device isEqualType []) || {(count _device) > 64} || {(count str _device) > 8000}) then { _device = []; };
        if !(_tint in ["coyote", "od", "black"]) then { _tint = "coyote"; };
        private _id = [_dock] call _idOf;
        private _slots = (_db get _id) select 0;
        _slot = floor _slot;
        if (_slot < 0) then { _slot = _slots findIf { (count _x) == 0 }; };
        if (_slot < 0 || {_slot > 9} || {(count (_slots select _slot)) > 0}) exitWith {
            [_req, "put", false, "Emplacement occupé ou socle plein", [_item]] call _reply;
        };
        if ((_req distance _dock) > 6) exitWith { [_req, "put", false, "Trop loin du socle", [_item]] call _reply; };
        _slots set [_slot, [getPlayerUID _req, name _req, _item, (_level max 0) min 100, _device, _tint, serverTime]];
        [_dock, _slots] call _publish;
        [false] call _save;
        [_req, "put", true, format ["Téléphone posé sur l'emplacement %1 (%2 %%)", _slot + 1, floor _level], [_slot]] call _reply;
    };
    case "take": {
        _a params [["_dock", objNull], ["_slot", -1]];
        if (isNull _req || {!(_dock isEqualType objNull)} || {isNull _dock} || {!(_dock isKindOf _class)} || {!(_slot isEqualType 0)}) exitWith {};
        private _id = [_dock] call _idOf;
        private _slots = (_db get _id) select 0;
        _slot = floor _slot;
        if (_slot < 0 || {_slot > 9} || {(count (_slots select _slot)) == 0}) exitWith { [_req, "take", false, "Emplacement vide"] call _reply; };
        private _s = _slots select _slot;
        private _mine = (_s select 0) isEqualTo getPlayerUID _req;
        private _staff = (admin (owner _req)) > 0 || {!isNull (getAssignedCuratorLogic _req)};
        if (!_mine && {!_staff} && {!(missionNamespace getVariable ["comspec_atak_native_dock_anyone", false])}) exitWith {
            [_req, "take", false, format ["Ce téléphone appartient à %1", _s select 1]] call _reply;
        };
        if ((_req distance _dock) > 6) exitWith { [_req, "take", false, "Trop loin du socle"] call _reply; };
        _slots set [_slot, []];
        [_dock, _slots] call _publish;
        [false] call _save;
        [_req, "take", true, format ["Téléphone repris (emplacement %1, %2 %%)%3", _slot + 1, floor (_s select 3), ["", format [" · appartient à %1", _s select 1]] select !_mine],
            [_s select 2, _s select 3, _s select 4, _mine]] call _reply;
    };
    case "zeusKit": {
        _a params [["_h", objNull]];
        if (isNull _h) exitWith {};
        private _pos = getPosASL _h;
        private _dir = getDir _h;
        deleteVehicle _h;
        private _o = createVehicle [_class, ASLToATL _pos, [], 0, "CAN_COLLIDE"];
        _o setDir _dir;
        _o setPosASL _pos;
        _o setVectorUp (surfaceNormal (getPosATL _o));
        private _id = format ["kit|zeus|%1|%2", (call _utc), floor random 1e5];
        [_o, _id, true] call _register;
        // Pose vide : un socle posé par Zeus n'est pas recréé aux parties suivantes (il appartient à cette partie).
        _db set [_id, [call _empty, [], call _utc]];
        (localNamespace getVariable ["COMSPEC_ATAK_DockObjs", []]) pushBack _o;
        // Le socle reste éditable par les Zeus (déplacer, supprimer).
        { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
        [_o, (_db get _id) select 0] call _publish;
        [false] call _save;
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][DOCK] Kit socle posé par Zeus : socle déplié %1", _id];
    };
    case "deploy": {
        _a params [["_pos", []], ["_dir", 0]];
        if (isNull _req || {!(_pos isEqualType [])} || {!(_pos isEqualTypeArray [0, 0, 0])} || {!(_dir isEqualType 0)} || {((getPosASL _req) distance _pos) > 4}) exitWith { [_req, "deploy", false, "Place invalide"] call _reply; };
        private _uid = getPlayerUID _req;
        // Socles du kit à son nom, toutes cartes : 10 au plus, chacun gardé dans le profil du serveur et recréé à chaque
        // partie sur sa carte (le kit lui est rendu).
        if (({ private _pl = _x select 1; (count _pl) > 4 && {(_pl select 4) isEqualTo _uid} } count (values _db)) >= _kitMax) exitWith {
            [_req, "deploy", false, format ["Déjà %1 socles posés à votre nom sur ce serveur. Repliez-en un d'abord.", _kitMax]] call _reply;
        };
        private _o = createVehicle [_class, ASLToATL _pos, [], 0, "CAN_COLLIDE"];
        _o setDir _dir;
        _o setPosASL _pos;
        private _id = format ["kit|%1|%2|%3", _uid, (call _utc), floor random 1e5];
        [_o, _id, true] call _register;
        _db set [_id, [call _empty, [worldName, getPosASL _o, vectorDir _o, vectorUp _o, _uid], call _utc]];
        (localNamespace getVariable ["COMSPEC_ATAK_DockObjs", []]) pushBack _o;
        [_o, (_db get _id) select 0] call _publish;
        [false] call _save;
        [_req, "deploy", true, "Socle de recharge posé"] call _reply;
    };
    case "pack": {
        _a params [["_dock", objNull]];
        if (isNull _req || {!(_dock isEqualType objNull)} || {isNull _dock} || {!(_dock isKindOf _class)}) exitWith {};
        private _id = [_dock] call _idOf;
        if !([_dock] call _kitOf) exitWith { [_req, "pack", false, "Ce socle fait partie de la mission"] call _reply; };
        if ((((_db get _id) select 0) findIf { (count _x) > 0 }) >= 0) exitWith { [_req, "pack", false, "Retirez d'abord les téléphones"] call _reply; };
        if ((_req distance _dock) > 6) exitWith { [_req, "pack", false, "Trop loin du socle"] call _reply; };
        _db deleteAt _id;
        deleteVehicle _dock;
        [false] call _save;
        [_req, "pack", true, "Socle replié"] call _reply;
    };
    default {};
};
