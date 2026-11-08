/*
    Nœuds simulés du réseau maillé (mode développeur, fn_devMesh) : de simples objets sans simulation, créés par le serveur,
    qui publient un enregistrement de nœud comme un joueur. On teste ainsi un graphe de 60 nœuds seul, sans 60 joueurs et
    sans le coût de 60 IA, qui fausserait la mesure du constructeur.
    Variables : COMSPEC_ATAK_MeshSimNodes (publique, liste d'objets), sur chaque objet COMSPEC_ATAK_MeshSim (camp) et
    COMSPEC_ATAK_MpuNode. Un relais simulé est un vrai relais de mission (COMSPEC_AtakRelays), retiré par "clear".
      "units"    [camp] -> nœuds simulés de ce camp ; [] sans le mode développeur (toujours appelé par le maillage : une seule
                 lecture de réglage dans ce cas)
      "request"  [action, arguments] (client) : envoie la demande au serveur, qui vérifie le réglage et les droits
      "init"     (serveur) : enregistre l'événement comspec_atak_native_devMeshSim
      Serveur, après vérification, avec l'appelant en 3e paramètre :
      "spawn"    [disposition ("chain" | "grid" | "random"), nombre, écart m, portée m, avec relais au bout]
      "scenario" ["chain" | "grid30" | "grid60" | "partition" | "jammer" | "move"]
      "kill"     [index] : le nœud s'éteint (enregistrement vide) ; "clear" : tout retirer
      "moveTick" : déplacement scripté, toutes les 2 s tant que le mouvement est actif
*/
params [["_act", ""], ["_arg", []], ["_caller", objNull]];
if (_act isEqualTo "units") exitWith {
    if !((missionNamespace getVariable ["comspec_atak_native_mesh_dev", false]) isEqualTo true) exitWith { [] };
    private _side = _arg param [0, ""];
    (missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []]) select { !isNull _x && {(_x getVariable ["COMSPEC_ATAK_MeshSim", ""]) isEqualTo _side} }
};
if (_act isEqualTo "request") exitWith {
    if !(["allowed"] call comspec_atak_native_fnc_devMesh) exitWith { false };
    ["comspec_atak_native_devMeshSim", [player, _arg param [0, ""], _arg param [1, []]]] call CBA_fnc_serverEvent;
    true
};
if (!isServer) exitWith { false };
private _maxNodes = 60;
private _lift = 1.5;
private _ground = {
    params ["_px", "_py", "_h"];
    [_px, _py, (getTerrainHeightASL [_px, _py]) + _h]
};
private _clear = {
    {
        if (!isNull _x) then { deleteVehicle _x; };
    } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []]);
    missionNamespace setVariable ["COMSPEC_ATAK_MeshSimNodes", [], true];
    private _rel = missionNamespace getVariable ["COMSPEC_ATAK_MeshSimRelays", []];
    if (_rel isNotEqualTo []) then {
        missionNamespace setVariable ["COMSPEC_AtakRelays", (missionNamespace getVariable ["COMSPEC_AtakRelays", []]) - _rel, true];
        { if (!isNull _x) then { deleteVehicle _x; }; } forEach _rel;
        missionNamespace setVariable ["COMSPEC_ATAK_MeshSimRelays", []];
    };
    missionNamespace setVariable ["COMSPEC_ATAK_MeshSimMove", false];
};
private _spawn = {
    params ["_who", "_layout", "_count", "_gap", "_range", "_withRelay", "_groundFn"];
    private _n = ((round _count) max 1) min 60;
    private _side = str side group _who;
    (["node", _who] call comspec_atak_native_fnc_meshRead) params [["_key", "C1"]];
    private _org = getPosASL _who;
    private _dir = getDir _who;
    private _side2 = ceil sqrt _n;
    private _list = missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []];
    private _made = [];
    for "_i" from 1 to _n do {
        private _dx = 0;
        private _dy = 0;
        switch (_layout) do {
            case "grid": { _dx = ((_i - 1) mod _side2) * _gap; _dy = (floor ((_i - 1) / _side2) + 1) * _gap; };
            case "random": { _dx = (random (2 * _gap)) - _gap; _dy = (random (2 * _gap)) - _gap; };
            default { _dy = _i * _gap; };
        };
        private _px = (_org select 0) + _dx * (cos _dir) + _dy * (sin _dir);
        private _py = (_org select 1) - _dx * (sin _dir) + _dy * (cos _dir);
        private _pos = [_px, _py, 1.5] call _groundFn;
        private _o = createVehicle ["Sign_Sphere25cm_F", ASLToATL _pos, [], 0, "CAN_COLLIDE"];
        _o enableSimulationGlobal false;
        _o allowDamage false;
        _o setPosASL _pos;
        _o setVariable ["COMSPEC_ATAK_MeshSim", _side, true];
        _o setVariable ["COMSPEC_ATAK_MpuNode", [_key, round _range, _o], true];
        _o setVariable ["COMSPEC_ATAK_CsCustom", format ["SIM-%1", (count _list) + _i], true];
        _made pushBack _o;
    };
    if (_withRelay) then {
        private _last = _made deleteAt ((count _made) - 1);
        _last setVariable ["COMSPEC_ATAK_MpuNode", [], true];
        _last setVariable ["COMSPEC_ATAK_MeshSim", "", true];
        _last setVariable ["COMSPEC_AtakRelayRange", round _range, true];
        _last setVariable ["COMSPEC_AtakRelayName", "Relais simulé", true];
        _last setVariable ["COMSPEC_AtakRelaySide", _side, true];
        _last setVariable ["COMSPEC_AtakRelay", true, true];
        private _rel = missionNamespace getVariable ["COMSPEC_ATAK_MeshSimRelays", []];
        _rel pushBack _last;
        missionNamespace setVariable ["COMSPEC_ATAK_MeshSimRelays", _rel];
        private _all = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
        _all pushBack _last;
        missionNamespace setVariable ["COMSPEC_AtakRelays", _all, true];
    };
    missionNamespace setVariable ["COMSPEC_ATAK_MeshSimNodes", _list + _made, true];
    diag_log format ["[COMSPEC ATAK NATIVE][MESH] Nœuds simulés : %1 créés (%2, écart %3 m, portée %4 m, canal %5, camp %6)", count _made, _layout, _gap, _range, _key, _side];
    count _made
};
switch (_act) do {
    case "init": {
        ["comspec_atak_native_devMeshSim", {
            params [["_who", objNull], ["_a", ""], ["_xa", []]];
            if (isNull _who || {!((missionNamespace getVariable ["comspec_atak_native_mesh_dev", false]) isEqualTo true)}) exitWith {};
            if !([_who] call comspec_atak_native_fnc_adminAllowed) exitWith {
                diag_log format ["[COMSPEC ATAK NATIVE][WARN][MESH] Demande d'outil de développement refusée : %1 %2", name _who, _a];
            };
            if !(_a in ["spawn", "scenario", "kill", "clear"]) exitWith {};
            [_a, _xa, _who] call comspec_atak_native_fnc_devMeshSim;
        }] call CBA_fnc_addEventHandler;
    };
    case "spawn": {
        _arg params [["_layout", "chain"], ["_count", 4], ["_gap", 250], ["_range", 300], ["_withRelay", false]];
        private _room = _maxNodes - count (missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []]);
        if (_room <= 0) exitWith { 0 };
        [_caller, _layout, _count min _room, (_gap max 5) min 5000, (_range max 50) min 20000, _withRelay, _ground] call _spawn
    };
    case "scenario": {
        switch (_arg param [0, ""]) do {
            // A (joueur) - B - C - D (relais) : chaque maillon n'atteint que ses voisins, portée 300 m, écart 250 m.
            case "chain": { call _clear; ["spawn", ["chain", 3, 250, 300, true], _caller] call comspec_atak_native_fnc_devMeshSim; };
            case "grid30": { call _clear; ["spawn", ["grid", 30, 200, 600, false], _caller] call comspec_atak_native_fnc_devMeshSim; };
            case "grid60": { call _clear; ["spawn", ["grid", 60, 200, 600, false], _caller] call comspec_atak_native_fnc_devMeshSim; };
            // Le nœud du milieu tombe : le graphe se coupe en deux.
            case "partition": {
                private _l = missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []];
                if (_l isNotEqualTo []) then { ["kill", [floor ((count _l) / 2)], _caller] call comspec_atak_native_fnc_devMeshSim; };
            };
            case "jammer": { ["add", [(getPosASL _caller) vectorAdd [150, 0, 0], 300, 100, 120, "", "devmesh"]] call comspec_atak_native_fnc_jammer; };
            case "move": {
                private _on = !(missionNamespace getVariable ["COMSPEC_ATAK_MeshSimMove", false]);
                missionNamespace setVariable ["COMSPEC_ATAK_MeshSimMove", _on];
                if (_on) then { ["moveTick"] call comspec_atak_native_fnc_devMeshSim; };
            };
        };
    };
    case "kill": {
        private _o = (missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []]) param [_arg param [0, -1], objNull];
        if (!isNull _o) then { _o setVariable ["COMSPEC_ATAK_MpuNode", [], true]; };
    };
    case "clear": { call _clear; };
    case "moveTick": {
        if !(missionNamespace getVariable ["COMSPEC_ATAK_MeshSimMove", false]) exitWith {};
        {
            if (isNull _x) then { continue };
            private _d = _x getVariable ["COMSPEC_ATAK_MeshSimDir", random 360];
            _d = _d + (random 40) - 20;
            _x setVariable ["COMSPEC_ATAK_MeshSimDir", _d];
            private _p = getPosASL _x;
            _x setPosASL ([(_p select 0) + 8 * (sin _d), (_p select 1) + 8 * (cos _d), _lift] call _ground);
        } forEach (missionNamespace getVariable ["COMSPEC_ATAK_MeshSimNodes", []]);
        [{ ["moveTick"] call comspec_atak_native_fnc_devMeshSim; }, [], 2] call comspec_atak_native_fnc_waitAndExec;
    };
};
