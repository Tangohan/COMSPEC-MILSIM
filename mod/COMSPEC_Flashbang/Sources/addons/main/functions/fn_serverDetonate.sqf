/*
    comspec_fb_fnc_serverDetonate (serveur) : pose l'engin et décide une fois pour toutes du nombre de détonations et de
    leurs instants (intervalle + écart aléatoire), puis envoie UN seul message aux machines concernées (fn_targets).
    Chaque machine joue alors les N détonations à partir de ces instants (fn_timeline) : le nombre est le même partout.

    [_ammo, _posASL, _vectorDirAndUp, _velocity, _thrower, _side, _nOverride, _lead] call comspec_fb_fnc_serverDetonate
      _nOverride : nombre de détonations imposé (Zeus, Eden ; -1 = celui de la grenade, réglage pour la DRILL)
      _lead      : délai supplémentaire avant la première détonation (s) ; Zeus / Eden : mèche simulée
*/
params [
    ["_ammo", "", [""]], ["_posASL", [0, 0, 0], [[]], 3], ["_vdu", [[0, 1, 0], [0, 0, 1]], [[]]],
    ["_vel", [0, 0, 0], [[]], 3], ["_thrower", objNull, [objNull]], ["_side", sideUnknown, [sideUnknown]],
    ["_nOverride", -1, [0]], ["_lead", 0, [0]]
];
if (!isServer) exitWith {};
private _cfg = configFile >> "CfgAmmo" >> _ammo;
if !(isNumber (_cfg >> "comspec_fb_bangs")) exitWith {
    ["serveur", format ["munition inconnue : %1", _ammo]] call comspec_fb_fnc_log;
};
private _drill = getNumber (_cfg >> "comspec_fb_drill") == 1;
private _n = if (_drill) then { missionNamespace getVariable ["comspec_fb_drillPops", 1] } else { getNumber (_cfg >> "comspec_fb_bangs") };
if (_nOverride > 0) then { _n = _nOverride };
_n = ((round _n) max 1) min 9;

// Engin au sol : objet synchronisé (ThingX), poussé par les explosions, supprimable par Zeus.
private _device = createVehicle [getText (_cfg >> "comspec_fb_device"), ASLToAGL _posASL, [], 0, "CAN_COLLIDE"];
_device setVectorDirAndUp _vdu;
_device setPosASL _posASL;
_device setVelocity _vel;
{ _x addCuratorEditableObjects [[_device], false] } forEach allCurators;

// Instants des détonations : 0, puis intervalle réglé +/- écart aléatoire (jamais moins de 0,12 s).
private _interval = missionNamespace getVariable ["comspec_fb_interval", 0.5];
private _jitter = missionNamespace getVariable ["comspec_fb_jitter", 0.12];
private _offsets = [];
private _t = 0;
for "_i" from 1 to _n do {
    _offsets pushBack _t;
    _t = _t + ((_interval + (random 2 - 1) * _jitter) max 0.12);
};

comspec_fb_seq = comspec_fb_seq + 1;
private _id = format ["FB%1", comspec_fb_seq];
// 0,15 s d'avance : le message arrive avant la première détonation sur les clients (horloge commune CBA_missionTime).
private _t0 = CBA_missionTime + 0.15 + (_lead max 0);
private _kind = ["live", "drill"] select _drill;
private _msg = [_id, _kind, _device, netId _device, _posASL, _offsets, _t0, _side, _thrower, []];
private _owners = [_posASL] call comspec_fb_fnc_targets;
comspec_fb_owners set [_id, _owners];
["serveur", format ["%1 %2 x%3 instants=%4 machines=%5", _id, _ammo, _n, _offsets apply { _x toFixed 2 }, _owners]] call comspec_fb_fnc_log;
if (isMultiplayer) then {
    _msg remoteExecCall ["comspec_fb_fnc_timeline", _owners];
} else {
    _msg call comspec_fb_fnc_timeline;
};

// Fin : l'engin vide reste 40 s au sol puis disparaît ; on oublie la liste des machines.
[{
    params ["_device", "_id"];
    if (!isNull _device) then { deleteVehicle _device };
    comspec_fb_owners deleteAt _id;
}, [_device, _id], (_t0 - CBA_missionTime) + _t + 40] call CBA_fnc_waitAndExecute;
