/*
    Distances parcourues (app Distances) comptées par le serveur Arma pour chaque joueur, comme le temps de jeu
    (fn_playTimeServer) : rien ne dépend du téléphone du joueur (batterie, casse, réseau, plantage du client).
    Toutes les 3 s, la position de chaque joueur est comparée à la précédente et rangée selon son état :
      à pied (pas estimés d'après l'allure, pas de pas à la nage ni en rampant), véhicule terrestre, bateau,
      vol (hélicoptère, avion), saut (chute libre HALO et sous voile, sauts comptés au départ d'au moins 150 m).
    Heures de pilote : aux commandes (currentPilot) d'un aéronef moteur allumé, par classe d'appareil
    (Athena regroupe les classes par type : Chinook, Black Hawk…).
    Envoi à Athena par la DLL du serveur (Distance.Batch de COMSPEC Link 2.0.86) toutes les 10 min, au départ d'un joueur
    et en fin de mission. Ce qui n'est pas parti reste en mémoire serveur et repart au prochain envoi.
    Params : [mode : "tick" | "flush" (tout envoyer) | "leave" (envoyer un joueur), uid (pour "leave")]
    Mémoire (uid ->) : COMSPEC_ATAK_DistLast [unité, position ASL, en saut]
                       COMSPEC_ATAK_DistAcc  [indicatif, compteurs, pilote] à envoyer
                       COMSPEC_ATAK_DistSess [indicatif, compteurs, pilote] de cette mission, montré dans l'app
    compteurs : HashMap steps, foot_m, foot_s, land_m, land_s, sea_m, sea_s, air_m, air_s, halo_m, halo_s, halo_jumps
    pilote : HashMap classe -> [nom, heli|plane, secondes, mètres]
*/
params [["_mode", "tick"], ["_only", ""]];
if (!isServer) exitWith { false };
private _now = diag_tickTime;
private _last = missionNamespace getVariable ["COMSPEC_ATAK_DistLast", createHashMap];
private _acc = missionNamespace getVariable ["COMSPEC_ATAK_DistAcc", createHashMap];
private _sess = missionNamespace getVariable ["COMSPEC_ATAK_DistSess", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_DistLast", _last];
missionNamespace setVariable ["COMSPEC_ATAK_DistAcc", _acc];
missionNamespace setVariable ["COMSPEC_ATAK_DistSess", _sess];

if (_mode isEqualTo "tick") then {
    private _dt = (_now - (missionNamespace getVariable ["COMSPEC_ATAK_DistTick", _now])) min 10;
    missionNamespace setVariable ["COMSPEC_ATAK_DistTick", _now];
    if (_dt <= 0 || {!(missionNamespace getVariable ["comspec_atak_native_distance", true])}) exitWith {};
    // Vitesse au-delà de laquelle l'écart est un téléport (respawn, Zeus, changement de slot) : non compté.
    private _vmax = createHashMapFromArray [["foot", 14], ["land", 130], ["sea", 60], ["air", 900], ["halo", 120]];
    private _publish = (_now - (missionNamespace getVariable ["COMSPEC_ATAK_DistPub", -1e9])) >= 15;
    if (_publish) then { missionNamespace setVariable ["COMSPEC_ATAK_DistPub", _now]; };
    {
        private _unit = _x;
        private _uid = getPlayerUID _unit;
        if (_uid isEqualTo "" || {_unit isKindOf "HeadlessClient_F"}) then { continue };
        if (!alive _unit) then { _last deleteAt _uid; continue };
        private _veh = vehicle _unit;
        private _pos = getPosASL _unit;
        (_last getOrDefault [_uid, [objNull, [], false]]) params ["_pu", "_pp", "_wasJump"];
        private _vz = (velocity _unit) select 2;
        private _alt = (getPosATL _unit) select 2;
        private _free = _veh isEqualTo _unit && {!isTouchingGround _unit} && {_alt > 15} && {_vz < -8 || {"halofreefall" in toLower (animationState _unit)}};
        private _kind = switch (true) do {
            case (_veh isKindOf "ParachuteBase"): { "halo" };
            case (_veh isEqualTo _unit): { ["foot", "halo"] select _free };
            case (_veh isKindOf "Air"): { "air" };
            case (_veh isKindOf "Ship"): { "sea" };
            default { "land" };
        };
        private _jump = _kind isEqualTo "halo";
        _last set [_uid, [_unit, _pos, _jump]];
        if (_pu isNotEqualTo _unit || {_pp isEqualTo []}) then { continue };
        private _d = if (_jump) then { _pp vectorDistance _pos } else { _pp distance2D _pos };
        if (_d / _dt > (_vmax get _kind)) then { continue };
        private _call = [_unit] call comspec_atak_native_fnc_unitCallsign;
        private _swim = ((toLower (animationState _unit)) select [1, 3]) in ["swm", "sdv", "bsw", "bdv"];
        private _steps = 0;
        if (_kind isEqualTo "foot" && {!_swim} && {stance _unit isNotEqualTo "PRONE"} && {_d >= 0.3}) then {
            // Longueur de pas selon l'allure : ~0,75 m en marche, ~1,1 m au trot, jusqu'à 1,6 m au sprint.
            _steps = _d / (0.7 max (0.45 + 0.2 * (_d / _dt)) min 1.6);
        };
        private _pilot = [];
        if (_kind isEqualTo "air" && {currentPilot _veh isEqualTo _unit} && {isEngineOn _veh}) then {
            _pilot = [typeOf _veh, getText (configOf _veh >> "displayName"), ["plane", "heli"] select (_veh isKindOf "Helicopter")];
        };
        {
            (_x getOrDefault [_uid, ["", createHashMap, createHashMap]]) params ["", "_c", "_p"];
            private _add = { params ["_k", "_v"]; _c set [_k, (_c getOrDefault [_k, 0]) + _v]; };
            // À pied : seulement le temps en mouvement (marche, course, nage), pas l'attente sur place.
            if (_kind isNotEqualTo "foot" || {_d >= 0.3}) then {
                [format ["%1_m", _kind], _d] call _add;
                [format ["%1_s", _kind], _dt] call _add;
            };
            if (_steps > 0) then { ["steps", _steps] call _add; };
            if (_jump && {!_wasJump} && {_alt > 150 || {_veh isKindOf "ParachuteBase"}}) then { ["halo_jumps", 1] call _add; };
            if (_pilot isNotEqualTo []) then {
                _pilot params ["_cls", "_label", "_pk"];
                (_p getOrDefault [_cls, [_label, _pk, 0, 0]]) params ["", "", "_ps", "_pm"];
                _p set [_cls, [_label, _pk, _ps + _dt, _pm + _d]];
            };
            _x set [_uid, [_call, _c, _p]];
        } forEach [_acc, _sess];
        // Compteurs de cette mission envoyés au téléphone du joueur (app Distances) toutes les 15 s.
        if (_publish) then {
            (_sess get _uid) params ["", "_c", "_p"];
            _unit setVariable ["COMSPEC_ATAK_DistMe", [(keys _c) apply { [_x, _c get _x] }, (keys _p) apply { [_x] + (_p get _x) }], owner _unit];
        };
    } forEach allPlayers;
};

// Envoi : toutes les 10 min, ou tout de suite (départ d'un joueur, fin de mission).
private _sent0 = missionNamespace getVariable ["COMSPEC_ATAK_DistSent", -1e9];
if (_mode isEqualTo "tick" && {(_now - _sent0) < 600}) exitWith { true };
if (_mode isNotEqualTo "leave") then { missionNamespace setVariable ["COMSPEC_ATAK_DistSent", _now]; };
private _uids = if (_mode isEqualTo "leave") then { [_only] select { _x in _acc } } else { keys _acc };
if (_uids isEqualTo []) exitWith { true };
private _mk = missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2", missionName, worldName]];
private _ok = true;
private _r = "";
private _tried = false;
for "_i" from 0 to ((count _uids) - 1) step 25 do {
    private _players = [];
    private _sent = [];
    {
        private _uid = _x;
        (_acc get _uid) params ["_call", "_c", "_p"];
        private _row = createHashMapFromArray [["player_uid", _uid], ["call_sign", _call]];
        { _row set [_x, round _y]; } forEach _c;
        _row set ["pilot", (keys _p) apply {
            (_p get _x) params ["_label", "_pk", "_ps", "_pm"];
            createHashMapFromArray [["class", _x], ["label", _label], ["kind", _pk], ["seconds", round _ps], ["meters", round _pm]]
        }];
        _players pushBack _row;
        _sent pushBack _uid;
    } forEach (_uids select [_i, 25]);
    if (_players isNotEqualTo []) then {
        _tried = true;
        _r = "COMSPECExtension" callExtension ["Distance.Batch", [[createHashMapFromArray [["mission_key", _mk], ["players", _players]]] call comspec_atak_native_fnc_json]];
        if (_r isEqualType []) then { _r = _r param [0, ""]; };
        if ((_r select [0, 3]) isEqualTo "OK|") then {
            { _acc deleteAt _x; } forEach _sent;
        } else {
            _ok = false;
        };
    };
};
if (_tried) then {
    if (_ok) then { missionNamespace setVariable ["COMSPEC_ATAK_DistSrv", [true, serverTime], true]; };
    if (!_ok && {(missionNamespace getVariable ["COMSPEC_ATAK_DistSrv", [true, -1]]) select 0}) then {
        missionNamespace setVariable ["COMSPEC_ATAK_DistSrv", [false, (missionNamespace getVariable ["COMSPEC_ATAK_DistSrv", [true, -1]]) select 1], true];
        ["WARN", "DISTANCE", format ["Distance.Batch refusé (%1) : gardé en mémoire serveur, renvoyé au prochain envoi", [_r, "COMSPEC Link 2.0.86 requis"] select (_r isEqualTo "")]] call comspec_atak_native_fnc_log;
    };
};
true
