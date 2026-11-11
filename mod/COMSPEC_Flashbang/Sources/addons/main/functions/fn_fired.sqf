/*
    comspec_fb_fnc_fired : FiredBIS (XEH) de tous les fantassins, sur toutes les machines.

    Seule la machine où le lanceur est local compte la mèche (fn_fuseEnd) : c'est elle qui simule le vol de SA grenade.
    Les autres machines effacent leur copie locale du projectile à la fin de la mèche (si le moteur en a créé une),
    pour qu'il ne reste que l'engin synchronisé posé par le serveur. Bruit de cuillère joué localement à chacun.
*/
params ["_unit", "_weapon", "", "", "_ammo", "", "_projectile"];
if (_weapon != "Throw") exitWith {};
private _cfg = configFile >> "CfgAmmo" >> _ammo;
if !(isNumber (_cfg >> "comspec_fb_bangs")) exitWith {};

// Projectile parfois nul dans l'événement (bug moteur connu) : on reprend la grenade la plus proche du lanceur.
if (isNull _projectile) then {
    _projectile = nearestObject [_unit, _ammo];
};
private _fuse = ((missionNamespace getVariable ["comspec_fb_fuse", 1.5]) max 0.5) min 4;

if (hasInterface && {!isNull _projectile}) then {
    private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
    if ((_cam vectorDistance (eyePos _unit)) < 30) then {
        ["Levier", eyePos _unit, 0, false] call comspec_fb_fnc_playBang;
    };
};

if (local _unit) then {
    ["fired", format ["%1 lance %2 (mèche %3 s)", name _unit, _ammo, _fuse]] call comspec_fb_fnc_log;
    [comspec_fb_fnc_fuseEnd, [_projectile, _ammo, _unit, side group _unit], _fuse] call CBA_fnc_waitAndExecute;
} else {
    if (!isNull _projectile && {local _projectile}) then {
        [{
            if (!isNull _this) then { deleteVehicle _this };
        }, _projectile, _fuse + 0.05] call CBA_fnc_waitAndExecute;
    };
};
