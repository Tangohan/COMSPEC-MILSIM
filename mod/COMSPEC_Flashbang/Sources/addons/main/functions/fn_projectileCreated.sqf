/*
    comspec_fb_fnc_projectileCreated : filet de sécurité du lancer, une image après la création d'une grenade du mod
    (événement de mission « ProjectileCreated », posé au postInit, indépendant des XEH de CBA).

    Normalement le XEH « fired » de CBA (vanilla, IA, lancer avancé d'ACE) a déjà pris la grenade en charge
    (comspec_fb_handled). S'il n'est pas passé (unité sans XEH, gestionnaires « Fired » retirés par un autre mod…),
    la grenade est confiée ici à la même chaîne (fn_fired), avec son lanceur tiré de getShotParents.
    Pas de lanceur (aperçu en main du lancer avancé d'ACE, objet créé par script) : rien n'est fait.
    [_projectile] call comspec_fb_fnc_projectileCreated
*/
params [["_projectile", objNull, [objNull]]];
if (isNull _projectile || {!local _projectile} || {_projectile getVariable ["comspec_fb_handled", false]}) exitWith {};
private _unit = (getShotParents _projectile) param [0, objNull];
if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith {};
private _ammo = typeOf _projectile;
private _mag = (getArray (configFile >> "CfgWeapons" >> "Throw" >> "muzzles")) apply {
    getArray (configFile >> "CfgWeapons" >> "Throw" >> _x >> "magazines") param [0, ""]
} select { getText (configFile >> "CfgMagazines" >> _x >> "ammo") == _ammo } param [0, ""];
[_unit, "Throw", "", "", _ammo, _mag, _projectile, _unit, "secours"] call comspec_fb_fnc_fired;
