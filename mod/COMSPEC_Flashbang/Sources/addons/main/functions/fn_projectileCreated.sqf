/*
    comspec_fb_fnc_projectileCreated : filet de sécurité du lancer pour toute grenade du mod créée sur cette machine
    (événement de mission « ProjectileCreated », posé au postInit, indépendant des XEH de CBA).

    Normalement le XEH « fired » de CBA a déjà pris la grenade en charge (comspec_fb_handled) : lancer vanilla (G),
    IA, script (fire / forceWeaponFire), Zeus qui contrôle une unité, véhicule (passager), lancer avancé d'ACE (ACE
    rejoue l'événement au dégoupillage, haut / bas / roulé / lâcher au sol compris). Sinon (unité sans XEH, gestionnaires
    retirés par un autre mod, objet créé par ACE sans événement), la grenade est confiée ici à la même chaîne (fn_fired),
    avec son lanceur tiré de getShotParents, ou de l'unité dont la grenade active ACE est cet objet. Vérifié pendant
    1 s (getShotParents arrive parfois après la création, par le serveur). Aperçu en main du lancer avancé d'ACE (pas
    encore dégoupillé) : jamais pris en charge.
    [_projectile, _essai] call comspec_fb_fnc_projectileCreated
*/
params [["_projectile", objNull, [objNull]], ["_try", 0, [0]]];
if (isNull _projectile || {!local _projectile} || {_projectile getVariable ["comspec_fb_handled", false]}) exitWith {};
private _unit = (getShotParents _projectile) param [0, objNull];
private _how = "getShotParents";
private _preview = false;
if (isNull _unit) then {
    // Objet du lancer avancé d'ACE : l'unité qui le tient comme grenade active, une fois dégoupillée ; avant, c'est
    // l'aperçu en main (ACE le remplace par un nouvel objet au dégoupillage) : jamais pris en charge.
    private _locals = allUnits select { local _x };
    private _i = _locals findIf { (_x getVariable ["ace_advanced_throwing_activeThrowable", objNull]) isEqualTo _projectile };
    if (_i >= 0) then {
        if ((_locals select _i) getVariable ["ace_advanced_throwing_primed", false]) then {
            _unit = _locals select _i;
            _how = "grenade active ACE";
        } else {
            _preview = true;
        };
    };
};
if (_preview) exitWith {};
if (isNull _unit || {!(_unit isKindOf "CAManBase")}) exitWith {
    if (_try < 10) then {
        [comspec_fb_fnc_projectileCreated, [_projectile, _try + 1], 0.1] call CBA_fnc_waitAndExecute;
    } else {
        ["fired", format ["%1 créé sans lanceur connu (objet de script ou aperçu ACE) : non pris en charge", typeOf _projectile]] call comspec_fb_fnc_log;
    };
};
private _ammo = typeOf _projectile;
private _mag = (getArray (configFile >> "CfgWeapons" >> "Throw" >> "muzzles")) apply {
    getArray (configFile >> "CfgWeapons" >> "Throw" >> _x >> "magazines") param [0, ""]
} select { getText (configFile >> "CfgMagazines" >> _x >> "ammo") == _ammo } param [0, ""];
["fired", format ["%1 : aucun événement de tir reçu, prise en charge de secours (lanceur par %2, après %3 essai(s))", _ammo, _how, _try]] call comspec_fb_fnc_log;
[_unit, "Throw", "", "", _ammo, _mag, _projectile, _unit, "secours"] call comspec_fb_fnc_fired;
