/*
    comspec_fb_fnc_fuseEnd : fin de la mèche (0,25 s avant la 1re détonation), sur la machine du lanceur.
    La grenade a disparu entre-temps (entrée dans un véhicule, supprimée par Zeus, ramassée par un script) :
    rien ne se passe, aucune détonation fantôme. Sinon position, orientation, vitesse et heure prévue de la 1re
    détonation (CBA_missionTime) partent vers fn_serverDetonate : serveur avec le mod (engin synchronisé), ou ce client
    lui-même si le serveur n'a pas le mod. Transport : événements CBA (pas de remoteExec).
*/
params ["_projectile", "_ammo", "_unit", "_side", ["_tDet", -1]];
if (isNull _projectile) exitWith {
    ["fuse", format ["%1 : grenade disparue avant la fin de la mèche, aucune détonation", _ammo]] call comspec_fb_fnc_log;
};
private _args = [_ammo, getPosASL _projectile, [vectorDir _projectile, vectorUp _projectile], velocity _projectile, _unit, _side, -1, 0, _tDet];
["fuse", format ["%1 : fin de mèche à %2, %3", _ammo, (getPosASL _projectile) apply { round _x }, switch (true) do {
    case isServer: { "ce poste est le serveur" };
    case (call comspec_fb_fnc_serverHasMod): { "envoi au serveur (il a le mod)" };
    default { "serveur sans le mod : ce client fait autorité" };
}]] call comspec_fb_fnc_log;
deleteVehicle _projectile;
_args call comspec_fb_fnc_serverDetonate;
