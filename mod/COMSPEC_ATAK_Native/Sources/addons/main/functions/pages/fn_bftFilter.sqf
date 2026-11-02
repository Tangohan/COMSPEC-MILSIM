/*
    Filtres du BFT (app BFT > FILTRES), appliqués aux pistes amies partout : liste et ORBAT du BFT, carte
    (fn_localDataRefresh retire les pistes masquées du magasin « units » que dessine fn_mapOnDraw).
    Params : [piste (HashMap du magasin « units ») ou unité (objet)]
    Renvoie true si la piste reste affichée. Moi-même, les pistes non amies : toujours true.
    Réglages profil :
      COMSPEC_ATAK_HideAllyAi      masquer les IA (réglage existant de la carte, aussi appliqué à la source)
      COMSPEC_ATAK_BftHideAir      masquer les aériens (pistes « air » et équipages à bord d'un aéronef)
      COMSPEC_ATAK_BftHideVeh      masquer les véhicules (pistes véhicule / blindé ; l'équipage reste dans son groupe)
      COMSPEC_ATAK_BftHideLost     masquer les pistes anciennes ou perdues
      COMSPEC_ATAK_BftHideOffline  masquer les pistes hors ligne
      COMSPEC_ATAK_BftMaxDist      distance max en m (0 : tout)
    « Seulement mon groupe / mon unité » : réglage existant COMSPEC_ATAK_AllyFilter (GROUP / ORBAT), appliqué à la source.
*/
params [["_u", objNull]];
private _e = createHashMap;
private _obj = objNull;
if (_u isEqualType objNull) then { _obj = _u; } else { if (_u isEqualType createHashMap) then { _e = _u; _obj = _e getOrDefault ["object", objNull]; }; };
if (_obj isEqualTo player || {_e getOrDefault ["self", false]}) exitWith { true };
if (!isNull _obj && {(side group _obj) isNotEqualTo (side group player)}) exitWith { true };
if ((_e getOrDefault ["affiliation", "friend"]) isNotEqualTo "friend") exitWith { true };
private _hAi = profileNamespace getVariable ["COMSPEC_ATAK_HideAllyAi", false];
private _hAir = profileNamespace getVariable ["COMSPEC_ATAK_BftHideAir", false];
private _hVeh = profileNamespace getVariable ["COMSPEC_ATAK_BftHideVeh", false];
private _hLost = profileNamespace getVariable ["COMSPEC_ATAK_BftHideLost", false];
private _hOff = profileNamespace getVariable ["COMSPEC_ATAK_BftHideOffline", false];
private _max = profileNamespace getVariable ["COMSPEC_ATAK_BftMaxDist", 0];
if !(_max isEqualType 0) then { _max = 0; };
// Aucun filtre actif (cas courant) : appelé pour chaque piste à chaque rafraîchissement, sortie sans calculer le type.
if (!_hAi && {!_hAir} && {!_hVeh} && {!_hLost} && {!_hOff} && {_max <= 0}) exitWith { true };
private _type = _e getOrDefault ["type", ""];
if (_type isEqualTo "") then {
    _type = switch (true) do {
        case (isNull _obj): { "infantry" };
        case (_obj isKindOf "CAManBase"): { "infantry" };
        case (_obj isKindOf "Air"): { "air" };
        case (_obj isKindOf "Tank"): { "armor" };
        default { "vehicle" };
    };
};
private _fr = _e getOrDefault ["freshness", "LIVE"];
if (_hAi && {!isNull _obj} && {!isPlayer _obj}) exitWith { false };
if (_hAir && {_type isEqualTo "air" || {!isNull _obj && {(vehicle _obj) isKindOf "Air"}}}) exitWith { false };
if (_hVeh && {_type in ["vehicle", "armor"]}) exitWith { false };
if (_hLost && {_fr in ["STALE", "LOST"]}) exitWith { false };
if (_hOff && {_fr isEqualTo "OFFLINE"}) exitWith { false };
private _pos = if ((count _e) > 0) then { _e getOrDefault ["position", [0, 0, 0]] } else { getPosASL _obj };
if (_max > 0 && {(player distance2D _pos) > _max}) exitWith { false };
true
