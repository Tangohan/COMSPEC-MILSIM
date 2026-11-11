/*
    comspec_fb_fnc_fired : tir « Fired » (XEH CBA sur CAManBase) de tous les fantassins, sur toutes les machines.
    Couvre le lancer vanilla (G) et le lancer avancé ACE (ACE rejoue les XEH « fired » au dégoupillage) ; les unités
    sans XEH dans leur config sont prises par la boucle de secours de CBA. Filet de sécurité : si ce gestionnaire
    n'a pas été appelé pour une grenade du mod, fn_projectileCreated l'appelle une image plus tard (_via = "secours").

    Seule la machine où le lanceur est local compte la mèche (fn_fuseEnd) : c'est elle qui simule le vol de SA grenade.
    Mèche : réglage « Mèche » pour les variantes standard (comspec_fb_fuse = -1 dans CfgAmmo), mèche fixe de la
    variante pour les grenades à délai (3 s, 8 s), plus l'irrégularité. L'heure de la 1re détonation est fixée ICI, sur
    l'horloge commune CBA_missionTime (lancer + mèche) ; la machine du lanceur passe la main au serveur 0,25 s avant,
    pour que le message arrive partout à temps (pas de dérive due au réseau).
    Les autres machines effacent leur copie locale du projectile après la mèche (si le moteur en a créé une), pour qu'il
    ne reste que l'engin synchronisé posé par le serveur. Bruit de cuillère joué localement à chacun.
*/
params ["_unit", "_weapon", "", "", "_ammo", "_magazine", "_projectile", "", ["_via", "", [""]]];
if (_weapon != "Throw") exitWith {};
private _cfg = configFile >> "CfgAmmo" >> _ammo;
if !(isNumber (_cfg >> "comspec_fb_bangs")) exitWith {};

// Projectile parfois nul dans l'événement (bug moteur connu) : on reprend la grenade la plus proche du lanceur.
if (isNull _projectile) then {
    _projectile = nearestObject [_unit, _ammo];
};
// Une seule prise en charge par grenade : XEH « fired » d'abord, sinon le filet ProjectileCreated (fn_projectileCreated).
if (!isNull _projectile) then {
    if (_projectile getVariable ["comspec_fb_handled", false]) exitWith { _via = "deja" };
    _projectile setVariable ["comspec_fb_handled", true];
};
if (_via == "deja") exitWith {};
private _fixed = getNumber (_cfg >> "comspec_fb_fuse");
private _fuse = if (_fixed > 0) then {
    private _j = missionNamespace getVariable ["comspec_fb_jitter", 0.12];
    (_fixed + (random 2 - 1) * _j) max 0.5
} else {
    ((missionNamespace getVariable ["comspec_fb_fuse", 1.5]) max 0.5) min 4
};

if (hasInterface && {!isNull _projectile}) then {
    private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
    if ((_cam vectorDistance (eyePos _unit)) < 30) then {
        ["Levier", eyePos _unit, 0, false] call comspec_fb_fnc_playBang;
    };
};

if (local _unit) then {
    private _tDet = CBA_missionTime + _fuse;
    ["fired", format ["%1 lance %2 (munition %3), chemin %4, projectile %5, mèche %6 s (%7), 1re détonation prévue à t=%8",
        name _unit, _magazine, _ammo,
        switch (true) do {
            case (_via == "secours"): { "secours ProjectileCreated (XEH fired non reçu)" };
            case (_unit getVariable ["ace_advanced_throwing_primed", false]): { "ACE lancer avancé" };
            default { "vanilla" };
        },
        [typeOf _projectile, "NUL"] select (isNull _projectile), _fuse toFixed 2,
        ["réglage", "fixe"] select (_fixed > 0), _tDet toFixed 2]] call comspec_fb_fnc_log;
    [comspec_fb_fnc_fuseEnd, [_projectile, _ammo, _unit, side group _unit, _tDet], (_fuse - 0.25) max 0.05] call CBA_fnc_waitAndExecute;
} else {
    if (!isNull _projectile && {local _projectile}) then {
        [{
            if (!isNull _this) then { deleteVehicle _this };
        }, _projectile, _fuse + 0.35] call CBA_fnc_waitAndExecute;
    };
};
