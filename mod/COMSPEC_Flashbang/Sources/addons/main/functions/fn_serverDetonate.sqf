/*
    comspec_fb_fnc_serverDetonate : décide une fois pour toutes du nombre de détonations et de leurs instants
    (intervalle + écart aléatoire), puis envoie UN seul message ; chaque machine joue les N détonations à partir de ces
    instants (fn_timeline) : le nombre est le même partout. Appelable sur n'importe quelle machine :

      - serveur (solo, hébergé, dédié avec le mod) : engin au sol synchronisé (ThingX, poussé par les explosions,
        supprimable par Zeus), message aux seules machines concernées (fn_targets) ;
      - client, serveur AVEC le mod (comspec_fb_fnc_serverHasMod) : la demande est transmise au serveur (événement CBA) ;
      - client, serveur SANS le mod : le client fait autorité. Il tire les instants lui-même et diffuse le message à
        toutes les machines (événement CBA global : CBA suffit sur le serveur) ; chaque machine qui a le mod pose son
        propre engin local (même position, même vitesse) et joue les détonations. Une seule des trois voies sert pour
        une grenade : aucun doublon.

    [_ammo, _posASL, _vectorDirAndUp, _velocity, _thrower, _side, _nOverride, _lead, _tDet, _drillSound, _rate]
        call comspec_fb_fnc_serverDetonate
      _nOverride  : nombre de détonations imposé (Zeus, Eden ; -1 = celui de la grenade, réglage pour la DRILL)
      _lead       : délai avant la première détonation (s) ; Zeus / Eden : délai + mèche simulée
      _tDet       : heure absolue (comspec_fb_fnc_clock : serverTime en multijoueur) de la 1re détonation, fixée au lancer (fn_fired) ; -1 = maintenant
                    + _lead. Un message arrivé en retard détone au plus tôt, sans jamais sauter de détonation.
      _drillSound : son de la DRILL imposé par le module (clé de drillChoix[] dans CfgSons.hpp) ; "" = réglage d'addon
      _rate       : cadence imposée (0 Normal, 1 Rapide, 2 Hyper rapide, 3 Aléatoire) ; -1 = celle de la grenade (classes
                    _R : Hyper rapide) ou le réglage « Cadence des flashs »
*/
params [
    ["_ammo", "", [""]], ["_posASL", [0, 0, 0], [[]], 3], ["_vdu", [[0, 1, 0], [0, 0, 1]], [[]]],
    ["_vel", [0, 0, 0], [[]], 3], ["_thrower", objNull, [objNull]], ["_side", sideUnknown, [sideUnknown]],
    ["_nOverride", -1, [0]], ["_lead", 0, [0]], ["_tDet", -1, [0]], ["_drillSound", "", [""]], ["_rate", -1, [0]]
];
if (!isServer && {call comspec_fb_fnc_serverHasMod}) exitWith {
    ["serveur", format ["%1 : demande transmise au serveur (il a le mod)", _ammo]] call comspec_fb_fnc_log;
    ["comspec_fb_serverDetonate", _this] call CBA_fnc_serverEvent;
};
private _client = !isServer;
private _cfg = configFile >> "CfgAmmo" >> _ammo;
if !(isNumber (_cfg >> "comspec_fb_bangs")) exitWith {
    ["serveur", format ["munition inconnue : %1", _ammo]] call comspec_fb_fnc_log;
};
private _drill = getNumber (_cfg >> "comspec_fb_drill") == 1;
private _n = if (_drill) then { missionNamespace getVariable ["comspec_fb_drillPops", 1] } else { getNumber (_cfg >> "comspec_fb_bangs") };
if (_nOverride > 0) then { _n = _nOverride };
// 1 à 9, ou jusqu'à 50 (GANG BANG BF) si le réglage « Autoriser les grenades à plus de 9 flashs » est coché.
_n = ((round _n) max 1) min ([9, 50] select (missionNamespace getVariable ["comspec_fb_allowBig", true]));
private _devClass = getText (_cfg >> "comspec_fb_device");

// Engin au sol synchronisé (serveur seulement : un serveur sans le mod ne connaît pas sa classe).
private _device = objNull;
if (!_client) then {
    _device = createVehicle [_devClass, ASLToAGL _posASL, [], 0, "CAN_COLLIDE"];
    _device setVectorDirAndUp _vdu;
    _device setPosASL _posASL;
    _device setVelocity _vel;
    { _x addCuratorEditableObjects [[_device], false] } forEach allCurators;
};

// Instants et forces des détonations, tirés une fois (fn_plan : irréguliers, 1er bang le plus fort).
if (_rate < 0 && {isNumber (_cfg >> "comspec_fb_rate")}) then { _rate = getNumber (_cfg >> "comspec_fb_rate") };
([_n, _rate] call comspec_fb_fnc_plan) params ["_offsets", "_gains", "_mode"];
private _modeName = ["normale", "rapide", "hyper rapide", "aléatoire"] select _mode;
private _meanDt = if (_n > 1) then { (selectMax _offsets) / (_n - 1) } else { 0 };
private _t = (selectMax _offsets) + 1;

comspec_fb_seq = comspec_fb_seq + 1;
// Identifiant unique entre machines : le client qui fait autorité y met son numéro réseau.
private _id = if (_client) then { format ["FBc%1_%2", clientOwner, comspec_fb_seq] } else { format ["FB%1", comspec_fb_seq] };
// Heure de la 1re détonation sur l'horloge commune : celle du lancer si elle est connue, sinon maintenant + délai.
// 0,1 s de marge au moins : le message arrive avant la première détonation sur les autres machines.
private _now = call comspec_fb_fnc_clock;
private _t0 = if (_tDet > 0) then { _tDet max (_now + 0.1) } else { _now + 0.15 + (_lead max 0) };
private _kind = ["live", "drill"] select _drill;
private _extra = [];
if (_drill) then {
    private _keys = getArray (configFile >> "COMSPEC_FB_Sons" >> "drillChoix");
    private _snd = if (_drillSound in _keys) then { _drillSound } else { missionNamespace getVariable ["comspec_fb_drillSound", "Pop"] };
    if !(_snd in _keys) then { _snd = "Pop" };
    _extra = [_snd, missionNamespace getVariable ["comspec_fb_drillVolume", 1]];
};
// Voie client : chaque machine pose son engin local à partir de cette description.
private _localDev = [[], [_devClass, _posASL, _vdu, _vel, _t + 40]] select _client;
private _msg = [_id, _kind, _device, ["", netId _device] select !_client, _posASL, _offsets, _t0, _side, _thrower, _extra, _localDev, _gains];
if (_client) then {
    ["client", format ["%1 %2 x%3 cadence %6 (intervalle moyen %7 s) instants=%4 1re dans %5 s : serveur sans le mod, ce client fait autorité (message à toutes les machines)",
        _id, _ammo, _n, _offsets apply { _x toFixed 2 }, (_t0 - _now) toFixed 2, _modeName, _meanDt toFixed 3]] call comspec_fb_fnc_log;
    ["comspec_fb_timeline", _msg] call CBA_fnc_globalEvent;
} else {
    private _owners = [_posASL] call comspec_fb_fnc_targets;
    comspec_fb_owners set [_id, _owners];
    ["serveur", format ["%1 %2 x%3 cadence %9 (intervalle moyen %10 s) engin=%4 instants=%5 1re dans %6 s machines=%7%8", _id, _ammo, _n, typeOf _device,
        _offsets apply { _x toFixed 2 }, (_t0 - _now) toFixed 2, _owners,
        [format [" son=%1", _extra], ""] select (_extra isEqualTo []), _modeName, _meanDt toFixed 3]] call comspec_fb_fnc_log;
    if (isMultiplayer) then {
        { ["comspec_fb_timeline", _msg, _x] call CBA_fnc_ownerEvent } forEach _owners;
    } else {
        _msg call comspec_fb_fnc_timeline;
    };
    // Fin : l'engin vide reste 40 s au sol puis disparaît ; on oublie la liste des machines.
    [{
        params ["_device", "_id"];
        if (!isNull _device) then { deleteVehicle _device };
        comspec_fb_owners deleteAt _id;
    }, [_device, _id], (_t0 - _now) + _t + 40] call CBA_fnc_waitAndExecute;
};
