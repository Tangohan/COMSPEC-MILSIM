/*
    Contrôle Zeus des téléphones ATAK, côté serveur (seul à écrire l'état). Appelé par un client avec
      [action, arguments] remoteExecCall ["comspec_atak_native_fnc_zeusServer", 2] (CfgRemoteExec).
      Accepté d'un Zeus (curateur assigné), de l'hôte, d'un admin connecté ou d'un
      gestionnaire désigné (fn_adminAllowed ; l'app Admin du téléphone passe par ici pour les effets par joueur).
      Le demandeur n'est JAMAIS celui qu'annonce la demande : il est retrouvé par remoteExecutedOwner (fn_reqUnit).
      Plus d'événement CBA comspec_atak_native_zeus : un événement CBA ne porte pas l'identité du poste émetteur.
    Actions :
      "set"     [joueurs, clé, valeur, durée s (0 = jusqu'à annulation)]   effet durable (fn_zeusFx) ; valeur 0 / false / "" = retiré
      "once"    [joueurs, type, arguments]   effet ponctuel joué chez le joueur (fn_zeusClient "once")
      "reset"   [joueurs]   retire tous les effets durables et répare le téléphone
      "jamArea" [position, rayon, force 1-100, durée s (0 = sans fin), camp épargné ("" = aucun)]   brouilleur de zone
      "jamClear" [position, rayon (0 = tous)]   retire les brouilleurs de zone Zeus
    Interne : "init" (postInit serveur), "tick" (toutes les 5 s : effets expirés, brouilleurs Zeus remis dans la liste
    publique si une écriture concurrente d'un client les a effacés), "respawn" (effets reportés sur le nouveau corps).
    Les effets durables restent jusqu'à l'annulation : réapparition et arrivée en cours de partie comprises (variable publique).
*/
if (!isServer) exitWith {};
params [["_act", ""], ["_args", []], ["_caller", objNull]];
// Demande venue d'un client : seul remoteExecutedOwner fait foi. L'unité annoncée dans la demande est écartée (un
// client y mettait l'unité d'un Zeus, d'un admin, ou n'importe quelle IA locale au serveur : l'ancienne clause
// « local _caller » était vraie pour tout objet du serveur). Un appel non distant ne peut venir que du code du
// serveur (postInit, boucle de 5 s, réapparition, script de mission) : il garde ce qu'il passe.
private _remote = isMultiplayer && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2};
if (_remote) then { _caller = [remoteExecutedOwner] call comspec_atak_native_fnc_reqUnit; };
// Hôte d'une partie locale : son propre appel n'est pas distant (son poste EST le serveur, remoteExecutedOwner y
// vaut 2 ou rien). Il reste le demandeur, sans quoi la réponse et le journal partiraient vers objNull.
if (!_remote && {isNull _caller} && {hasInterface}) then { _caller = player; };
// Actions internes : jamais acceptées d'un client (elles ne sont pas des demandes mais le fonctionnement du serveur).
if (_remote && {!(_act in ["set", "once", "reset", "jamArea", "jamClear"])}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][ZEUS] Action « %1 » refusée au poste %2 (réservée au serveur)", _act, remoteExecutedOwner];
    false
};
if (_remote && {isNull _caller}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][ZEUS] Demande « %1 » refusée au poste %2 (demandeur introuvable)", _act, remoteExecutedOwner];
    false
};
if (_remote && {!([_caller] call comspec_atak_native_fnc_adminAllowed)}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][ZEUS] Demande « %1 » refusée à %2 (ni Zeus ni admin)", _act, name _caller];
    ["comspec_atak_native_note", ["Refusé par le serveur : les effets Zeus sur les ATAK sont réservés à Zeus et aux admins."], _caller] call CBA_fnc_targetEvent;
    false
};
private _now = [time, serverTime] select isMultiplayer;
private _pub = {
    params ["_u", "_list"];
    _u setVariable ["COMSPEC_ATAK_ZeusFx", _list, true];
    // Réaction immédiate chez le joueur (notification, musique coupée, redessin), sans attendre sa boucle.
    ["comspec_atak_native_zeusFx", ["refresh", []], _u] call CBA_fnc_targetEvent;
};
private _players = {
    params ["_l"];
    private _out = [];
    { if (_x isEqualType objNull && {!isNull _x}) then {
        if (_x isKindOf "CAManBase") then { if (isPlayer _x) then { _out pushBackUnique _x; }; } else { { if (isPlayer _x) then { _out pushBackUnique _x; }; } forEach (crew _x); };
    }; } forEach _l;
    _out
};
switch (_act) do {
    case "init": {
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusJammersSrv", []];
        // Pas de gestionnaire d'événement CBA : les demandes des clients arrivent par remoteExecCall vers 2, seule
        // voie qui laisse le serveur reconnaître le poste émetteur (contrôle en tête de cette fonction).
        [{ ["tick"] call comspec_atak_native_fnc_zeusServer; }, 5] call CBA_fnc_addPerFrameHandler;
        addMissionEventHandler ["EntityRespawned", { params ["_new", "_old"]; ["respawn", [_new, _old]] call comspec_atak_native_fnc_zeusServer; }];
    };
    case "respawn": {
        _args params [["_new", objNull], ["_old", objNull]];
        if (isNull _new || {isNull _old}) exitWith {};
        private _l = _old getVariable ["COMSPEC_ATAK_ZeusFx", []];
        if (_l isNotEqualTo []) then { [_new, _l] call _pub; };
    };
    case "set": {
        _args params [["_units", []], ["_key", ""], ["_val", true], ["_dur", 0]];
        if !(_key in ["off", "lock", "mute", "jam", "heat", "drain"]) exitWith {};
        private _clear = (_val isEqualTo false) || {_val isEqualTo 0} || {_val isEqualTo ""};
        {
            private _l = (_x getVariable ["COMSPEC_ATAK_ZeusFx", []]) select { (_x param [0, ""]) isNotEqualTo _key };
            if (!_clear) then { _l pushBack [_key, _val, [-1, _now + _dur] select (_dur > 0)]; };
            [_x, _l] call _pub;
            diag_log format ["[COMSPEC ATAK NATIVE][INFO][ZEUS] %1 -> %2 : %3 = %4 (%5 s)", name _caller, name _x, _key, _val, _dur];
        } forEach ([_units] call _players);
    };
    case "once": {
        _args params [["_units", []], ["_kind", ""], ["_a", []]];
        {
            ["comspec_atak_native_zeusFx", ["once", [_kind, _a]], _x] call CBA_fnc_targetEvent;
            diag_log format ["[COMSPEC ATAK NATIVE][INFO][ZEUS] %1 -> %2 : %3 %4", name _caller, name _x, _kind, _a];
        } forEach ([_units] call _players);
    };
    case "reset": {
        _args params [["_units", []]];
        {
            [_x, []] call _pub;
            ["comspec_atak_native_zeusFx", ["once", ["repair", []]], _x] call CBA_fnc_targetEvent;
            diag_log format ["[COMSPEC ATAK NATIVE][INFO][ZEUS] %1 -> %2 : tout rétabli", name _caller, name _x];
        } forEach ([_args param [0, []]] call _players);
    };
    case "jamArea": {
        _args params [["_pos", [0, 0, 0]], ["_rad", 500], ["_str", 100], ["_dur", 0], ["_spare", ""]];
        private _n = (missionNamespace getVariable ["COMSPEC_ATAK_ZeusJamN", 0]) + 1;
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusJamN", _n];
        // Entrée de brouilleur ATAK : [position, rayon, uid, camp, fin, filtrage ami, force 0-1].
        private _e = [_pos, _rad max 10, format ["zeus:%1", _n], _spare, [1e9, _now + _dur] select (_dur > 0), _spare isNotEqualTo "", ((_str max 1) min 100) / 100];
        private _srv = missionNamespace getVariable ["COMSPEC_ATAK_ZeusJammersSrv", []];
        _srv pushBack _e;
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusJammersSrv", _srv];
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []];
        _list pushBack _e;
        missionNamespace setVariable ["COMSPEC_ATAK_Jammers", _list, true];
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][ZEUS] %1 : brouilleur de zone %2 m, %3 %%, %4 s", name _caller, _rad, _str, _dur];
    };
    case "jamClear": {
        _args params [["_pos", [0, 0, 0]], ["_rad", 0]];
        private _gone = (missionNamespace getVariable ["COMSPEC_ATAK_ZeusJammersSrv", []]) select { _rad <= 0 || {((_x select 0) distance2D _pos) <= _rad} };
        private _ids = _gone apply { _x select 2 };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusJammersSrv", (missionNamespace getVariable ["COMSPEC_ATAK_ZeusJammersSrv", []]) select { !((_x select 2) in _ids) }];
        missionNamespace setVariable ["COMSPEC_ATAK_Jammers", (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]) select { !((_x param [2, ""]) in _ids) }, true];
        diag_log format ["[COMSPEC ATAK NATIVE][INFO][ZEUS] %1 : %2 brouilleur(s) de zone retiré(s)", name _caller, count _ids];
    };
    case "tick": {
        // Effets expirés retirés (le client les ignore déjà ; ici pour garder la variable publique propre).
        {
            private _l = _x getVariable ["COMSPEC_ATAK_ZeusFx", []];
            if (_l isNotEqualTo []) then {
                private _k = _l select { ((_x param [2, -1]) < 0) || {(_x param [2, -1]) >= _now} };
                if ((count _k) isNotEqualTo (count _l)) then { [_x, _k] call _pub; };
            };
        } forEach allPlayers;
        private _srv = (missionNamespace getVariable ["COMSPEC_ATAK_ZeusJammersSrv", []]) select { (_x select 4) >= _now };
        missionNamespace setVariable ["COMSPEC_ATAK_ZeusJammersSrv", _srv];
        private _all = missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []];
        // Brouilleurs Zeus retirés (ou expirés) mais remis par l'écriture d'un client à liste périmée : enlevés à nouveau.
        private _srvIds = _srv apply { _x select 2 };
        private _list = _all select { private _id = _x param [2, ""]; !(_id isEqualType "") || {(_id find "zeus:") isNotEqualTo 0} || {_id in _srvIds} };
        private _have = _list apply { _x param [2, ""] };
        private _miss = _srv select { !((_x select 2) in _have) };
        if (_miss isNotEqualTo [] || {(count _list) isNotEqualTo (count _all)}) then { missionNamespace setVariable ["COMSPEC_ATAK_Jammers", _list + _miss, true]; };
    };
};
true
