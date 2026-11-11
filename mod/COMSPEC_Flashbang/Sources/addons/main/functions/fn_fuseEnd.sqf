/*
    comspec_fb_fnc_fuseEnd : fin de la mèche, sur la machine du lanceur.
    La grenade a disparu entre-temps (entrée dans un véhicule, supprimée par Zeus, ramassée par un script) :
    rien ne se passe, aucune détonation fantôme. Sinon on transmet position, orientation et vitesse au serveur,
    qui pose l'engin synchronisé et lance les N détonations.
*/
params ["_projectile", "_ammo", "_unit", "_side"];
if (isNull _projectile) exitWith {
    ["fuse", format ["%1 : grenade supprimée avant la fin de la mèche, aucune détonation", _ammo]] call comspec_fb_fnc_log;
};
private _args = [_ammo, getPosASL _projectile, [vectorDir _projectile, vectorUp _projectile], velocity _projectile, _unit, _side];
deleteVehicle _projectile;
if (isServer) then {
    _args call comspec_fb_fnc_serverDetonate;
} else {
    _args remoteExecCall ["comspec_fb_fnc_serverDetonate", 2];
};
