/*
    Serveur de l'extraction de données (événement CBA comspec_atak_native_dataSrv, branché dans XEH_preInit).
    Params : [action, objet, joueur, argument]
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
      "code"     : code de fin d'un module Zeus (remoteExecCall d'un curateur seulement), gardé sur le serveur
    Zeus : chaque curateur reçoit un message (comspec_atak_native_dataNote).
*/
if (!isServer) exitWith { false };
params [["_act", ""], ["_obj", objNull], ["_who", objNull], ["_arg", []]];
if (isNull _obj) exitWith { false };
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
        _arg params [["_names", []], ["_full", false]];
        private _taken = _obj getVariable ["COMSPEC_ATAK_DataTaken", []];
        _taken pushBackUnique _whoName;
        _obj setVariable ["COMSPEC_ATAK_DataTaken", _taken, true];
        [format ["%1 a téléchargé %2 fichier(s) de %3", _whoName, count _names, _label]] call _note;
        if ((_cfg getOrDefault ["alarm", "none"]) isEqualTo "download") then { ["téléchargement"] call _alarm; };
        if (_full && {_cfg getOrDefault ["oneShot", false]}) then { _obj setVariable ["COMSPEC_ATAK_DataWiped", true, true]; };
        // Code tenu par le serveur seul (fn_dataSetup) : jamais lu dans COMSPEC_ATAK_Data, que tout client peut réécrire.
        private _code = _obj getVariable ["COMSPEC_ATAK_DataCode", ""];
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
        // Code de fin posé par un module Zeus : accepté seulement d'un curateur (émetteur du remoteExec, pas un argument).
        private _owner = remoteExecutedOwner;
        private _ok = isRemoteExecuted && {(hasInterface && {_owner isEqualTo clientOwner}) || {(allCurators findIf { private _u = getAssignedCuratorUnit _x; !isNull _u && {(owner _u) isEqualTo _owner} }) >= 0}};
        if (_ok && {_arg isEqualType ""}) then { _obj setVariable ["COMSPEC_ATAK_DataCode", _arg]; };
    };
    case "remove": {
        _obj setVariable ["COMSPEC_ATAK_Data", nil, true];
        _obj setVariable ["COMSPEC_ATAK_DataCode", nil];
        missionNamespace setVariable ["COMSPEC_ATAK_DataNodes", (missionNamespace getVariable ["COMSPEC_ATAK_DataNodes", []]) select { !isNull _x && {_x isNotEqualTo _obj} }, true];
    };
};
true
