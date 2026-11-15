/*
    comspec_fb_fnc_targets (serveur) : machines qui doivent jouer une détonation à _posASL.
    Serveur (IA locales), clients headless (IA), joueurs à moins de 2,5 km (le flash se voit de loin la nuit),
    joueurs morts ou spectateurs et Zeus (leur caméra peut être n'importe où), et propriétaire de chaque IA à moins de
    80 m (une IA du groupe d'un joueur éloigné, ou passée sur un autre poste, doit être touchée par SA machine : seule
    elle peut lui appliquer les effets). Identifiants « owner » sans doublon.
    [_posASL] call comspec_fb_fnc_targets -> [2, 5, 7, ...]
*/
params [["_posASL", [0, 0, 0], [[]], 3]];
private _owners = [2];
private _pos = ASLToAGL _posASL;
{
    if (_x isKindOf "HeadlessClient_F" || {!alive _x} || {(_x distance2D _pos) < 2500} || {!isNull (getAssignedCuratorLogic _x)}) then {
        _owners pushBackUnique (owner _x);
    };
} forEach allPlayers;
{ _owners pushBackUnique (owner _x) } forEach (entities "HeadlessClient_F");
// Propriétaires des IA proches : l'effet s'applique là où l'IA est locale (ni remoteExec ni liste blanche).
{
    { if (!isPlayer _x) then { _owners pushBackUnique (owner _x) } } forEach (if (_x isKindOf "CAManBase") then { [_x] } else { crew _x });
} forEach (_pos nearEntities [["CAManBase", "LandVehicle", "Air", "Ship", "StaticWeapon"], 80]);
_owners select { _x >= 2 }
