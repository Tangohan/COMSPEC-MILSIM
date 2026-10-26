/*
    Serveur de l'extraction de données. Appelée par un client avec
    [action, objet, objNull, argument] remoteExecCall ["comspec_atak_native_fnc_dataServer", 2] (CfgRemoteExec) :
    le joueur n'est PAS lu dans la demande, il est retrouvé par remoteExecutedOwner (fn_reqUnit). Plus d'événement
    CBA comspec_atak_native_dataSrv : il ne portait pas l'identité du poste émetteur et n'écartait que « code ».
    Params : [action, objet, joueur (ignoré pour un appel distant), argument]
      "reg"      : ajoute le support à COMSPEC_ATAK_DataNodes (variable publique : app Extraction, actions ACE)
      "connect"  : un joueur branche son câble (Zeus prévenu, alarme « au branchement »)
      "fail"     : mauvais mot de passe ; au bout des essais permis : verrouillage, effacement ou alarme
      "unlock"   : accès obtenu (mot de passe ou forçage) : compteur d'essais remis à zéro
      "cracked"  : accès forcé
      "done"     : téléchargement terminé (argument : [noms des fichiers, tout le support]) : code de la mission,
                   événement global comspec_atak_native_dataDownloaded [objet, joueur, fichiers], usage unique
      "rip"      : câble arraché pendant le transfert
      "reset"    : Zeus remet le support en état (essais, verrou, effacement)
      "remove"   : Zeus retire les données du support
      "code"     : code de fin (argument : texte, vide = retiré), gardé au serveur seul (localNamespace, jamais diffusé).
                   Accepté d'un appel local au serveur (fn_dataSetup) ou d'un remoteExec d'un Zeus / administrateur
                   (contrôle propre à cette action, plus bas).
    Zeus : chaque curateur reçoit un message (comspec_atak_native_dataNote).
*/
if (!isServer) exitWith { false };
params [["_act", ""], ["_obj", objNull], ["_who", objNull], ["_arg", []]];
if (isNull _obj) exitWith { false };
// Demande venue d'un client : seul remoteExecutedOwner fait foi. Un appel non distant ne peut venir que du code du
// serveur (modules Eden, fn_dataSetup au démarrage).
private _remote = isMultiplayer && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2};
if (_remote) then { _who = [remoteExecutedOwner] call comspec_atak_native_fnc_reqUnit; };
// Actions qui supposent qu'on manipule le support : le demandeur doit être à portée de son câble, plus une marge (la
// position d'une unité distante est connue avec du retard au serveur ; le client, lui, coupe à la longueur exacte,
// fn_dataAction.sqf:55 et :145). Sans ce contrôle un client déclarait un support téléchargé sans y toucher
// (« done » exécute le code de fin gardé par le serveur et lève comspec_atak_native_dataDownloaded), forçait le
// verrou (« cracked »), remettait le compteur d'essais à zéro (« unlock »), ou faisait verrouiller / effacer le
// support à distance en enchaînant les « fail ».
if (_remote && {_act in ["connect", "fail", "unlock", "cracked", "rip", "done"]}
    && {isNull _who || {!alive _who} || {(_who distance _obj) > (((missionNamespace getVariable ["comspec_atak_native_data_length", 3]) max 3) + 20)}}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][DATA] « %1 » refusé au poste %2 (demandeur absent ou hors de portée du support)", _act, remoteExecutedOwner];
    false
};
// « reset » et « remove » sont des actions de Zeus (module « Remettre / retirer les données »), sauf un joueur qui
// efface les données de son propre téléphone exposé (fn_phoneSecurity : l'objet visé est le joueur lui-même).
if (_remote && {_act in ["reset", "remove"]} && {_obj isNotEqualTo _who}
    && {isNull _who || {!([_who] call comspec_atak_native_fnc_adminAllowed)}}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][DATA] « %1 » refusé à %2 (ni Zeus ni admin)", _act, name _who];
    ["comspec_atak_native_note", ["Refusé par le serveur : remettre en état ou effacer un support de données est réservé à Zeus et aux admins."], _who] call CBA_fnc_targetEvent;
    false
};
private _cfg = createHashMapFromArray (_obj getVariable ["COMSPEC_ATAK_Data", []]);
private _label = format ["%1%2", _cfg getOrDefault ["name", "Support"], ["", format [" (%1)", mapGridPosition _obj]] select (_act isNotEqualTo "reg")];
private _whoName = if (isNull _who) then { "?" } else { name _who };
private _note = {
    params ["_msg"];
    { private _u = getAssignedCuratorUnit _x; if (!isNull _u) then { ["comspec_atak_native_dataNote", [_msg], _u] call CBA_fnc_targetEvent; }; } forEach allCurators;
};
private _alarm = {
    params ["_why"];
    playSound3D ["A3\Sounds_F\sfx\alarm_independent.wss", _obj, false, getPosASL _obj, 3, 1, 250];
    if (!isNull _who) then {
        private _side = side group _who;
        private _groups = [];
        {
            if (alive _x && {!isPlayer _x} && {[side group _x, _side] call BIS_fnc_sideIsEnemy}) then { _groups pushBackUnique group _x; };
        } forEach (_obj nearEntities ["CAManBase", 400]);
        { [_x, [_who, 3.5]] remoteExec ["reveal", groupOwner _x]; } forEach _groups;
    };
    [format ["ALARME %1 : %2 (%3)", _label, _why, _whoName]] call _note;
};
switch (_act) do {
    case "reg": {
        private _old = missionNamespace getVariable ["COMSPEC_ATAK_DataNodes", []];
        private _nodes = _old select { !isNull _x };
        _nodes pushBackUnique _obj;
        // Liste rediffusée à tous (et JIP) seulement si elle change : un téléphone exposé se réenregistre à chaque chute.
        if (_nodes isNotEqualTo _old) then { missionNamespace setVariable ["COMSPEC_ATAK_DataNodes", _nodes, true]; };
    };
    case "connect": {
        [format ["%1 branche son ATAK sur %2", _whoName, _label]] call _note;
        if ((_cfg getOrDefault ["alarm", "none"]) isEqualTo "connect") then { ["branchement"] call _alarm; };
    };
    case "fail": {
        private _tries = (_obj getVariable ["COMSPEC_ATAK_DataTries", 0]) + 1;
        _obj setVariable ["COMSPEC_ATAK_DataTries", _tries, true];
        private _max = _cfg getOrDefault ["attempts", 3];
        [format ["%1 : mauvais mot de passe (%2%3) par %4", _label, _tries, ["", format ["/%1", _max]] select (_max > 0), _whoName]] call _note;
        if ((_cfg getOrDefault ["alarm", "none"]) isEqualTo "fail") then { ["mauvais mot de passe"] call _alarm; };
        if (_max > 0 && {_tries >= _max}) then {
            switch (_cfg getOrDefault ["onFail", "lock"]) do {
                case "lock": { _obj setVariable ["COMSPEC_ATAK_DataLocked", true, true]; [format ["%1 verrouillé", _label]] call _note; };
                case "wipe": { _obj setVariable ["COMSPEC_ATAK_DataWiped", true, true]; [format ["%1 : données effacées (trop d'essais)", _label]] call _note; };
                case "alarm": { ["trop d'essais"] call _alarm; _obj setVariable ["COMSPEC_ATAK_DataTries", 0, true]; };
                default {};
            };
        };
    };
    case "unlock": { _obj setVariable ["COMSPEC_ATAK_DataTries", 0, true]; };
    case "cracked": {
        _obj setVariable ["COMSPEC_ATAK_DataTries", 0, true];
        _obj setVariable ["COMSPEC_ATAK_DataLocked", false, true];
        [format ["%1 : accès forcé par %2", _label, _whoName]] call _note;
    };
    case "rip": { [format ["%1 : câble arraché pendant le transfert (%2)", _label, _whoName]] call _note; };
    case "done": {
        // Données retirées par Zeus pendant le transfert : rien n'a été téléchargé.
        if (isNil { _obj getVariable "COMSPEC_ATAK_Data" }) exitWith {};
        _arg params [["_names", []], ["_full", false]];
        private _taken = _obj getVariable ["COMSPEC_ATAK_DataTaken", []];
        _taken pushBackUnique _whoName;
        _obj setVariable ["COMSPEC_ATAK_DataTaken", _taken, true];
        [format ["%1 a téléchargé %2 fichier(s) de %3", _whoName, count _names, _label]] call _note;
        if ((_cfg getOrDefault ["alarm", "none"]) isEqualTo "download") then { ["téléchargement"] call _alarm; };
        if (_full && {_cfg getOrDefault ["oneShot", false]}) then { _obj setVariable ["COMSPEC_ATAK_DataWiped", true, true]; };
        // Code lu au serveur seul : jamais dans COMSPEC_ATAK_Data, que tout client peut réécrire.
        private _code = (((localNamespace getVariable ["COMSPEC_ATAK_DataCodes", []]) select { (_x select 0) isEqualTo _obj }) param [0, [objNull, ""]]) select 1;
        if (_code isEqualType "" && {(trim _code) isNotEqualTo ""}) then {
            [_obj, _who, _names] call compile _code;
        };
        ["comspec_atak_native_dataDownloaded", [_obj, _who, _names]] call CBA_fnc_globalEvent;
    };
    case "reset": {
        _obj setVariable ["COMSPEC_ATAK_DataTries", 0, true];
        _obj setVariable ["COMSPEC_ATAK_DataLocked", false, true];
        _obj setVariable ["COMSPEC_ATAK_DataWiped", false, true];
        _obj setVariable ["COMSPEC_ATAK_DataTaken", [], true];
    };
    case "code": {
        // Contrôle côté serveur (comme fn_netConfig) : appel local, hôte, administrateur connecté ou Zeus.
        private _owner = remoteExecutedOwner;
        private _ok = !isRemoteExecuted || {_owner in [2, clientOwner]} || {(admin _owner) > 0}
            || {(allPlayers findIf { owner _x isEqualTo _owner && {!isNull (getAssignedCuratorLogic _x)} }) > -1};
        if (!_ok) exitWith { ["WARNING", "DATA", format ["Code de fin refusé pour %1 (client %2 sans droits)", _label, _owner]] call comspec_atak_native_fnc_log; };
        private _codes = (localNamespace getVariable ["COMSPEC_ATAK_DataCodes", []]) select { !isNull (_x select 0) && {(_x select 0) isNotEqualTo _obj} };
        if (_arg isEqualType "" && {(trim _arg) isNotEqualTo ""}) then { _codes pushBack [_obj, _arg]; };
        localNamespace setVariable ["COMSPEC_ATAK_DataCodes", _codes];
    };
    case "remove": {
        _obj setVariable ["COMSPEC_ATAK_Data", nil, true];
        localNamespace setVariable ["COMSPEC_ATAK_DataCodes", (localNamespace getVariable ["COMSPEC_ATAK_DataCodes", []]) select { !isNull (_x select 0) && {(_x select 0) isNotEqualTo _obj} }];
        missionNamespace setVariable ["COMSPEC_ATAK_DataNodes", (missionNamespace getVariable ["COMSPEC_ATAK_DataNodes", []]) select { !isNull _x && {_x isNotEqualTo _obj} }, true];
    };
};
true
