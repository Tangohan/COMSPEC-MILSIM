/*
    True si le joueur local est dans le rayon d'un relais encore intact.
    
    Version refactorée : utilise config réalisme centralisée pour fallback
*/
private _unit = missionNamespace getVariable ["COMSPEC_PlayerUnit", player];
if (isNull _unit) then { _unit = player; };
if (isNull _unit) exitWith { false };

// Récupérer range par défaut depuis config centralisée
private _defaultRange = 2000;
// Réglage réalisme seulement s'il vient d'une fonction de la config du jeu (CfgFunctions, finale) : realism_config
// n'est pas construit, et sans cette garde ce nom ne désignerait pas une fonction finale.
if (isClass (configFile >> "CfgFunctions" >> "ATHENA") && {!isNil "ATHENA_fnc_getRealismParam"}) then {
    _defaultRange = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;
};

private _list = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
private _ok = false;
{
    if (isNull _x) then { continue };
    if (!alive _x) then { continue };
    if (!(_x getVariable ["COMSPEC_AtakRelay", false])) then { continue };
    
    // Lire range du relais (ou fallback config)
    private _range = _x getVariable ["COMSPEC_AtakRelayRange", _defaultRange];
    if (!(_range isEqualType 0)) then { _range = _defaultRange; };
    
    // Vérifier distance
    if ((_unit distance2D _x) <= _range) then { _ok = true };
} forEach _list;

_ok
