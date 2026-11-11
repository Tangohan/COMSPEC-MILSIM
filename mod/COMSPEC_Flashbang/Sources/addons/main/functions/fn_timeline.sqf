/*
    comspec_fb_fnc_timeline : reçu UNE fois par machine (serveur, headless, clients proches) pour une grenade.
    Programme exactement une exécution de fn_burst par instant reçu, alignée sur l'horloge commune
    comspec_fb_fnc_clock (serverTime en multijoueur : même instant sur toutes les machines, quel que soit le réseau).
    Message arrivé en retard : les détonations passées depuis plus de 0,3 s sont sautées (jamais rejouées en rafale),
    les suivantes partent à leur heure. Plusieurs détonations dues pendant une image lente partent dans la même image
    (aucune perte : CBA exécute toutes les tâches échues).
    Lance aussi, si activée, l'analyse intérieur / extérieur du point d'explosion, étalée sur quelques images.

    [_id, _kind, _device, _netId, _posASL, _offsets, _t0, _side, _thrower, _extra, _localDev, _gains] call comspec_fb_fnc_timeline
      _kind     : "live" (grenade), "drill" (entraînement), "area" (effet de zone sans grenade, _extra = [rayon, force, fx])
      _localDev : voie client (serveur sans le mod) : [classe, posASL, [dir, up], vitesse, durée de vie] de l'engin que
                  chaque machine pose elle-même (createVehicleLocal) ; [] = engin synchronisé posé par le serveur
      _gains    : force relative de chaque détonation (fn_plan : 1er bang 1, les suivants 0,65 à 1) ; [] = toutes à 1
*/
params [
    ["_id", "", [""]], ["_kind", "live", [""]], ["_device", objNull, [objNull]], ["_netId", "", [""]],
    ["_posASL", [0, 0, 0], [[]], 3], ["_offsets", [], [[]]], ["_t0", 0, [0]], ["_side", sideUnknown, [sideUnknown]],
    ["_thrower", objNull, [objNull]], ["_extra", [], [[]]], ["_localDev", [], [[]]], ["_gains", [], [[]]]
];
if (_id isEqualTo "" || {_offsets isEqualTo []} || {_id in comspec_fb_events}) exitWith {};
private _now = call comspec_fb_fnc_clock;
// Voie client : message diffusé à toutes les machines ; un joueur à plus de 3 km sans IA locale près de la grenade
// n'a rien à jouer.
if (_localDev isNotEqualTo [] && {hasInterface} && {!isServer}
    && {((AGLToASL (positionCameraToWorld [0, 0, 0])) vectorDistance _posASL) > 3000}
    && {(((ASLToAGL _posASL) nearEntities [["CAManBase"], 60]) findIf { local _x }) < 0}) exitWith {};

if (_localDev isNotEqualTo []) then {
    _localDev params [["_class", "", [""]], ["_p", _posASL, [[]], 3], ["_vdu", [[0, 1, 0], [0, 0, 1]], [[]]], ["_vel", [0, 0, 0], [[]], 3], ["_life", 45, [0]]];
    if (isClass (configFile >> "CfgVehicles" >> _class)) then {
        _device = _class createVehicleLocal (ASLToAGL _p);
        _device setVectorDirAndUp _vdu;
        _device setPosASL _p;
        _device setVelocity _vel;
        [{ if (!isNull _this) then { deleteVehicle _this } }, _device, ((_t0 - _now) max 0) + _life] call CBA_fnc_waitAndExecute;
    };
};

private _ctx = createHashMapFromArray [
    ["id", _id], ["kind", _kind], ["device", _device], ["netId", _netId], ["resolved", !isNull _device],
    ["pos", _posASL], ["side", _side], ["thrower", _thrower], ["extra", _extra], ["n", count _offsets],
    ["done", 0], ["cancelled", false], ["env", createHashMap], ["lastBang", -1], ["gains", _gains],
    ["t0", _t0], ["offsets", _offsets], ["lastLight", -1]
];
comspec_fb_events set [_id, _ctx];

if (_kind != "area" && {missionNamespace getVariable ["comspec_fb_indoorDetection", true]}) then {
    [_ctx] call comspec_fb_fnc_envProbe;
};

private _skipped = 0;
{
    private _wait = _t0 + _x - _now;
    if (_wait < -0.3) then {
        _skipped = _skipped + 1;
    } else {
        [comspec_fb_fnc_burst, [_id, _forEachIndex], _wait max 0] call CBA_fnc_waitAndExecute;
    };
} forEach _offsets;
if (_skipped > 0) then {
    ["timeline", format ["%1 : message reçu %2 s après la 1re détonation, %3 détonation(s) déjà passée(s) sautée(s)", _id,
        (_now - _t0) toFixed 2, _skipped]] call comspec_fb_fnc_log;
};

// Contexte oublié 3 s après la dernière détonation.
[{ comspec_fb_events deleteAt _this }, _id, ((_t0 + selectMax _offsets - _now) max 0) + 3] call CBA_fnc_waitAndExecute;
["timeline", format ["%1 %2 x%3 dans %4 s%5", _id, _kind, count _offsets, (_t0 - _now) toFixed 2,
    ["", " (engin local : serveur sans le mod)"] select (_localDev isNotEqualTo [])]] call comspec_fb_fnc_log;
