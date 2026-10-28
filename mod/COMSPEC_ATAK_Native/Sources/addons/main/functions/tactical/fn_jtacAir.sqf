/*
    Fiche de check-in d'un appareil (ce que l'équipage annonce au JTAC en arrivant en station), lue sur l'appareil :
    indicatif, type, équipage, armement restant, carburant, altitude, vitesse, nacelle de désignation.
    Params : [appareil]. Renvoie un hashmap :
      callsign, type, players (équipage joueur), ai (équipage tout IA), alt (ft MSL), agl (ft sol), kts,
      fuel (0-100), tgp (nacelle / caméra pilote), ord : [[libellé, nombre, distance de risque (m), magazine]...]
    Distance de risque : ordre de grandeur des tables « danger close » (probabilité d'atteinte 0,1 %) par famille
    de munition, faute de table par arme dans Arma : bombe lourde 500 m, bombe 425 m, roquettes 160 m,
    missile 100 m, canon 100 m.
*/
params [["_air", objNull]];
private _h = createHashMap;
if (isNull _air) exitWith { _h };
private _cfgM = configFile >> "CfgMagazines";
private _cfgA = configFile >> "CfgAmmo";
private _risk = {
    params ["_ammo"];
    private _sim = toLower getText (_cfgA >> _ammo >> "simulation");
    private _rng = getNumber (_cfgA >> _ammo >> "indirectHitRange");
    switch (true) do {
        case (_sim isEqualTo "shotbomb" || {_ammo isKindOf ["BombCore", _cfgA]}): { [425, 500] select (_rng >= 15) };
        case (_sim isEqualTo "shotrocket" || {_ammo isKindOf ["RocketCore", _cfgA]}): { 160 };
        case (_sim isEqualTo "shotmissile" || {_ammo isKindOf ["MissileCore", _cfgA]}): { 100 };
        case (_sim in ["shotbullet", "shotshell"]): { 100 };
        default { 0 };
    };
};
private _ord = createHashMap;
{
    _x params ["_mag", "", "_cnt"];
    private _ammo = getText (_cfgM >> _mag >> "ammo");
    private _r = [_ammo] call _risk;
    // Contre-mesures, fumigènes, désignateur : pas des armes air-sol.
    if (_r > 0 && {_cnt > 0}) then {
        private _name = getText (_cfgM >> _mag >> "displayNameShort");
        if (_name isEqualTo "") then { _name = getText (_cfgM >> _mag >> "displayName"); };
        if (_name isEqualTo "") then { _name = _mag; };
        private _cur = _ord getOrDefault [_name, [_name, 0, _r, _mag]];
        _cur set [1, (_cur select 1) + _cnt];
        _ord set [_name, _cur];
    };
} forEach (magazinesAllTurrets _air);
private _list = values _ord;
_list sort false; // libellé décroissant : stable d'un rendu à l'autre
private _crew = (crew _air) select { alive _x };
private _players = _crew select { isPlayer _x };
private _cs = groupId group _air;
_h set ["callsign", _cs];
_h set ["type", getText (configOf _air >> "displayName")];
_h set ["players", _players];
_h set ["ai", (count _players) isEqualTo 0];
_h set ["alt", round (((getPosASL _air) select 2) * 3.28084)];
_h set ["agl", round (((getPosATL _air) select 2) * 3.28084)];
_h set ["kts", round ((speed _air) * 0.539957)];
_h set ["fuel", round ((fuel _air) * 100)];
_h set ["tgp", isClass (configOf _air >> "pilotCamera") || {((weapons _air) + (_air weaponsTurret [0])) findIf { "laserdesignator" in toLower _x } >= 0}];
_h set ["ord", _list];
_h
