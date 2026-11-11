/*
    comspec_fb_fnc_fuseEnd : fin de la mèche (0,25 s avant la 1re détonation), sur la machine du lanceur.
    La grenade a disparu entre-temps (entrée dans un véhicule, supprimée par Zeus, ramassée par un script) :
    rien ne se passe, aucune détonation fantôme. Sinon on transmet position, orientation, vitesse et l'heure prévue de
    la 1re détonation (CBA_missionTime) au serveur, qui pose l'engin synchronisé et lance les N détonations.
    Transport : événement CBA (pas de remoteExec, donc indépendant des listes blanches et filtres de la mission).
*/
params ["_projectile", "_ammo", "_unit", "_side", ["_tDet", -1]];
if (isNull _projectile) exitWith {
    ["fuse", format ["%1 : grenade disparue avant la fin de la mèche, aucune détonation", _ammo]] call comspec_fb_fnc_log;
};
private _args = [_ammo, getPosASL _projectile, [vectorDir _projectile, vectorUp _projectile], velocity _projectile, _unit, _side, -1, 0, _tDet];
["fuse", format ["%1 : fin de mèche à %2, envoi au serveur (%3)", _ammo, (getPosASL _projectile) apply { round _x }, ["distant", "local"] select isServer]] call comspec_fb_fnc_log;
deleteVehicle _projectile;
["comspec_fb_serverDetonate", _args] call CBA_fnc_serverEvent;
