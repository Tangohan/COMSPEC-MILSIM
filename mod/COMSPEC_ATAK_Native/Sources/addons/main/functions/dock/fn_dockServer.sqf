/*
    Socle de recharge CS-10 (objet COMSPEC_ATAK_ChargingDock, 10 emplacements), côté serveur : seul le serveur
    écrit l'état des emplacements, le recharge, le mémorise et l'affiche (fn_dockVisual).

    Emplacement : [] (libre) ou [UID du propriétaire, nom, objet téléphone, charge 0-100, état de l'appareil
    (paires de COMSPEC_ATAK_Device), couleur de coque, posé à (serverTime)].
    Mémoire : missionNamespace COMSPEC_ATAK_DockDb, identifiant du socle -> [emplacements, pose, enregistré à (s UTC)],
    écrite dans le profil du serveur (profileNamespace COMSPEC_ATAK_DockDb_v1) : un téléphone laissé sur un socle
    se retrouve à la partie suivante, chargé du temps écoulé (réglage « La charge continue entre deux parties »).
    Pose : [] pour un socle de la mission (Eden, Zeus), [monde, posASL, vectorDir, vectorUp, UID] pour un socle
    posé avec le kit, recréé au lancement de la mission sur la même carte (réglage « Mémoriser les socles posés »).
    Identifiant : attribut Eden « Identifiant du socle » (le même d'une mission à l'autre), sinon carte + position.
    Kit socle (Item_COMSPEC_ATAK_DockKit) posé par Zeus en cours de partie : remplacé par le socle déplié ("zeusKit").
    Clients -> serveur (CBA) : comspec_atak_native_dockPut, _dockTake, _dockDeploy, _dockPack ;
    réponse au client : comspec_atak_native_dockResult [action, ok, message, données].

    Params : [action, arguments]
*/
params [["_act", "init"], ["_a", []]];
if (!isServer) exitWith {};
private _db = missionNamespace getVariable ["COMSPEC_ATAK_DockDb", createHashMap];
private _class = "COMSPEC_ATAK_ChargingDock";
// Secondes UTC (assez juste pour un écart entre deux parties).
private _utc = {
    systemTimeUTC params ["_y", "_mo", "_d", "_h", "_mi", "_s"];
    private _days = 365 * _y + floor ((_y - 1) / 4) + ([0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334] select (_mo - 1)) + _d;
    if (_mo > 2 && {_y % 4 == 0}) then { _days = _days + 1; };
    ((_days * 24 + _h) * 60 + _mi) * 60 + _s
};
private _reply = {
    params ["_unit", "_what", "_ok", "_msg", ["_data", []]];
    if (isNull _unit) exitWith {};
    ["comspec_atak_native_dockResult", [_what, _ok, _msg, _data], _unit] call CBA_fnc_targetEvent;
};
private _empty = { private _s = []; for "_i" from 1 to 10 do { _s pushBack []; }; _s };
private _rate = { (missionNamespace getVariable ["comspec_atak_native_dock_rate", 1.5]) max 0.1 };

switch (_act) do {
    case "init": {
        missionNamespace setVariable ["COMSPEC_ATAK_DockDb", _db];
        private _now = call _utc;
        private _offline = missionNamespace getVariable ["comspec_atak_native_dock_offline", true];
        {
            _x params [["_id", ""], ["_slots", []], ["_place", []], ["_at", _now]];
            if (_id isEqualTo "" || {(count _slots) != 10}) then { continue };
            // Charge pendant l'absence du serveur.
            if (_offline) then {
                private _gain = (call _rate) * ((_now - _at) max 0) / 60;
                { if ((count _x) > 3) then { _x set [3, ((_x select 3) + _gain) min 100]; }; } forEach _slots;
            };
            _db set [_id, [_slots, _place, _now]];
        } forEach (profileNamespace getVariable ["COMSPEC_ATAK_DockDb_v1", []]);
        // Socles posés avec le kit sur cette carte : recréés à leur place.
        if (missionNamespace getVariable ["comspec_atak_native_dock_persist", true]) then {
            {
                private _rec = _db get _x;
                (_rec select 1) params [["_world", ""], ["_pos", []], ["_dir", [0, 1, 0]], ["_up", [0, 0, 1]]];
                if (_world isEqualTo worldName && {(count _pos) == 3}) then {
                    private _o = createVehicle [_class, ASLToATL _pos, [], 0, "CAN_COLLIDE"];
                    _o setVectorDirAndUp [_dir, _up];
                    _o setPosASL _pos;
                    _o setVariable ["COMSPEC_ATAK_DockId", _x, true];
                    _o setVariable ["COMSPEC_ATAK_DockKit", true, true];
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
        ["comspec_atak_native_dockPut", { ["put", _this] call comspec_atak_native_fnc_dockServer; }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_dockTake", { ["take", _this] call comspec_atak_native_fnc_dockServer; }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_dockDeploy", { ["deploy", _this] call comspec_atak_native_fnc_dockServer; }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_dockPack", { ["pack", _this] call comspec_atak_native_fnc_dockServer; }] call CBA_fnc_addEventHandler;
        [{ ["tick"] call comspec_atak_native_fnc_dockServer; }, 10] call CBA_fnc_addPerFrameHandler;
        addMissionEventHandler ["MPEnded", { ["save"] call comspec_atak_native_fnc_dockServer; }];
        addMissionEventHandler ["Ended", { ["save"] call comspec_atak_native_fnc_dockServer; }];
        ["INFO", "DOCK", format ["Socles de recharge en mémoire : %1", count _db]] call comspec_atak_native_fnc_log;
    };
    // Identifiant du socle, posé une fois sur l'objet (il le garde s'il est déplacé par Zeus).
    case "id": {
        _a params ["_dock"];
        private _id = _dock getVariable ["COMSPEC_ATAK_DockId", ""];
        if (_id isEqualTo "") then {
            private _p = getPosASL _dock;
            _id = format ["%1|%2|%3|%4", worldName, round (_p select 0), round (_p select 1), round (_p select 2)];
            _dock setVariable ["COMSPEC_ATAK_DockId", _id, true];
        };
        if !(_id in _db) then { _db set [_id, [call _empty, [], call _utc]]; };
        _id
    };
    case "tick": {
        private _now = serverTime;
        private _dt = (_now - (missionNamespace getVariable ["COMSPEC_ATAK_DockTick", _now])) min 60;
        missionNamespace setVariable ["COMSPEC_ATAK_DockTick", _now];
        private _gain = (call _rate) * _dt / 60;
        private _utcNow = call _utc;
        // Liste des socles : allMissionObjects (parcourt tous les objets de la mission) une fois par minute
        // seulement ; les socles posés avec le kit y sont ajoutés dès leur création (« deploy »).
        private _docks = missionNamespace getVariable ["COMSPEC_ATAK_DockObjs", []];
        if (_now - (missionNamespace getVariable ["COMSPEC_ATAK_DockScan", -1e9]) > 60) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DockScan", _now];
            _docks = allMissionObjects _class;
        };
        _docks = _docks select { !isNull _x };
        missionNamespace setVariable ["COMSPEC_ATAK_DockObjs", _docks];
        // Sauvegarde utile seulement si une charge ou une place a changé.
        private _dirty = false;
        {
            private _dock = _x;
            private _id = ["id", [_dock]] call comspec_atak_native_fnc_dockServer;
            private _rec = _db get _id;
            private _slots = _rec select 0;
            { if ((count _x) > 3 && {(_x select 3) < 100}) then { _x set [3, ((_x select 3) + _gain) min 100]; _dirty = true; }; } forEach _slots;
            // Socle du kit déplacé par Zeus : nouvelle place mémorisée.
            if (_dock getVariable ["COMSPEC_ATAK_DockKit", false]) then {
                private _pl = _rec select 1;
                if ((count _pl) > 1 && {((_pl select 1) distance (getPosASL _dock)) > 0.05}) then {
                    _rec set [1, [worldName, getPosASL _dock, vectorDir _dock, vectorUp _dock, _pl param [4, ""]]];
                    _dirty = true;
                };
            };
            _rec set [2, _utcNow];
            ["publish", [_dock, _slots]] call comspec_atak_native_fnc_dockServer;
        } forEach _docks;
        // Sauvegarde du profil serveur (saveProfileNamespace réécrit tout le profil : à-coup sur le serveur) toutes les
        // 5 min au plus, et seulement si un téléphone charge ou un socle a bougé (sinon à chaque dépôt, retrait et en fin de partie).
        if (_dirty && {_now - (missionNamespace getVariable ["COMSPEC_ATAK_DockSaved", -1e9]) > 300}) then { ["save"] call comspec_atak_native_fnc_dockServer; };
    };
    // État public du socle (lu par les menus) et textures (téléphones, écrans, voyants).
    case "publish": {
        _a params ["_dock", "_slots"];
        private _pub = _slots apply { if ((count _x) < 6) then { [] } else { [_x select 0, _x select 1, floor (_x select 3), _x select 5, _x select 2] } };
        if ((_dock getVariable ["COMSPEC_ATAK_DockSlots", []]) isNotEqualTo _pub) then {
            _dock setVariable ["COMSPEC_ATAK_DockSlots", _pub, true];
        };
        [_dock, _pub] call comspec_atak_native_fnc_dockVisual;
    };
    case "save": {
        missionNamespace setVariable ["COMSPEC_ATAK_DockSaved", serverTime];
        private _keep = missionNamespace getVariable ["comspec_atak_native_dock_persist", true];
        private _out = [];
        {
            private _rec = _y;
            private _used = ((_rec select 0) findIf { (count _x) > 0 }) >= 0;
            // Socles vides de la mission : rien à retenir. Socles du kit : gardés (place) si le réglage le permet.
            if (_used || {_keep && {(count (_rec select 1)) > 0}}) then { _out pushBack [_x, _rec select 0, _rec select 1, _rec select 2]; };
        } forEach _db;
        profileNamespace setVariable ["COMSPEC_ATAK_DockDb_v1", _out];
        saveProfileNamespace;
    };
    case "put": {
        _a params [["_dock", objNull], ["_unit", objNull], ["_slot", -1], ["_item", ""], ["_level", 100], ["_device", []], ["_tint", "coyote"]];
        if (isNull _unit || {_item isEqualTo ""}) exitWith {};
        // Socle replié ou supprimé (Zeus) avant l'arrivée de la demande : le téléphone, déjà retiré côté joueur, lui est rendu.
        if (isNull _dock) exitWith { [_unit, "put", false, "Socle replié", [_item]] call _reply; };
        private _id = ["id", [_dock]] call comspec_atak_native_fnc_dockServer;
        private _slots = (_db get _id) select 0;
        if (_slot < 0) then { _slot = _slots findIf { (count _x) == 0 }; };
        if (_slot < 0 || {_slot > 9} || {(count (_slots select _slot)) > 0}) exitWith {
            [_unit, "put", false, "Emplacement occupé ou socle plein", [_item]] call _reply;
        };
        if ((_unit distance _dock) > 6) exitWith { [_unit, "put", false, "Trop loin du socle", [_item]] call _reply; };
        _slots set [_slot, [getPlayerUID _unit, name _unit, _item, (_level max 0) min 100, _device, _tint, serverTime]];
        ["publish", [_dock, _slots]] call comspec_atak_native_fnc_dockServer;
        ["save"] call comspec_atak_native_fnc_dockServer;
        [_unit, "put", true, format ["Téléphone posé sur l'emplacement %1 (%2 %%)", _slot + 1, floor _level], [_slot]] call _reply;
    };
    case "take": {
        _a params [["_dock", objNull], ["_unit", objNull], ["_slot", -1]];
        if (isNull _dock || {isNull _unit}) exitWith {};
        private _id = ["id", [_dock]] call comspec_atak_native_fnc_dockServer;
        private _slots = (_db get _id) select 0;
        if (_slot < 0 || {_slot > 9} || {(count (_slots select _slot)) == 0}) exitWith { [_unit, "take", false, "Emplacement vide"] call _reply; };
        private _s = _slots select _slot;
        private _mine = (_s select 0) isEqualTo getPlayerUID _unit;
        private _staff = (admin (owner _unit)) > 0 || {!isNull (getAssignedCuratorLogic _unit)};
        if (!_mine && {!_staff} && {!(missionNamespace getVariable ["comspec_atak_native_dock_anyone", false])}) exitWith {
            [_unit, "take", false, format ["Ce téléphone appartient à %1", _s select 1]] call _reply;
        };
        if ((_unit distance _dock) > 6) exitWith { [_unit, "take", false, "Trop loin du socle"] call _reply; };
        _slots set [_slot, []];
        ["publish", [_dock, _slots]] call comspec_atak_native_fnc_dockServer;
        ["save"] call comspec_atak_native_fnc_dockServer;
        [_unit, "take", true, format ["Téléphone repris (emplacement %1, %2 %%)%3", _slot + 1, floor (_s select 3), ["", format [" · appartient à %1", _s select 1]] select !_mine],
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
        _o setVariable ["COMSPEC_ATAK_DockId", _id, true];
        _o setVariable ["COMSPEC_ATAK_DockKit", true, true];
        // Pose vide : un socle posé par Zeus n'est pas recréé aux parties suivantes (il appartient à cette partie).
        _db set [_id, [call _empty, [], call _utc]];
        (missionNamespace getVariable ["COMSPEC_ATAK_DockObjs", []]) pushBack _o;
        // Le socle reste éditable par les Zeus (déplacer, supprimer).
        { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
        ["publish", [_o, (_db get _id) select 0]] call comspec_atak_native_fnc_dockServer;
        ["save"] call comspec_atak_native_fnc_dockServer;
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][DOCK] Kit socle posé par Zeus : socle déplié %1", _id];
    };
    case "deploy": {
        _a params [["_unit", objNull], ["_pos", []], ["_dir", 0]];
        if (isNull _unit || {(count _pos) != 3} || {((getPosASL _unit) distance _pos) > 4}) exitWith { [_unit, "deploy", false, "Place invalide"] call _reply; };
        private _o = createVehicle [_class, ASLToATL _pos, [], 0, "CAN_COLLIDE"];
        _o setDir _dir;
        _o setPosASL _pos;
        private _id = format ["kit|%1|%2|%3", getPlayerUID _unit, (call _utc), floor random 1e5];
        _o setVariable ["COMSPEC_ATAK_DockId", _id, true];
        _o setVariable ["COMSPEC_ATAK_DockKit", true, true];
        _db set [_id, [call _empty, [worldName, getPosASL _o, vectorDir _o, vectorUp _o, getPlayerUID _unit], call _utc]];
        (missionNamespace getVariable ["COMSPEC_ATAK_DockObjs", []]) pushBack _o;
        ["publish", [_o, (_db get _id) select 0]] call comspec_atak_native_fnc_dockServer;
        ["save"] call comspec_atak_native_fnc_dockServer;
        [_unit, "deploy", true, "Socle de recharge posé"] call _reply;
    };
    case "pack": {
        _a params [["_dock", objNull], ["_unit", objNull]];
        if (isNull _dock || {isNull _unit}) exitWith {};
        private _id = ["id", [_dock]] call comspec_atak_native_fnc_dockServer;
        if !(_dock getVariable ["COMSPEC_ATAK_DockKit", false]) exitWith { [_unit, "pack", false, "Ce socle fait partie de la mission"] call _reply; };
        if ((((_db get _id) select 0) findIf { (count _x) > 0 }) >= 0) exitWith { [_unit, "pack", false, "Retirez d'abord les téléphones"] call _reply; };
        if ((_unit distance _dock) > 6) exitWith { [_unit, "pack", false, "Trop loin du socle"] call _reply; };
        _db deleteAt _id;
        deleteVehicle _dock;
        ["save"] call comspec_atak_native_fnc_dockServer;
        [_unit, "pack", true, "Socle replié"] call _reply;
    };
    default {};
};
