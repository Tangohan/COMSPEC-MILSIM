/*
    Guerre électronique : effets du brouillage sur le joueur local, recalculés au plus une fois par seconde.
    Brouilleurs : missionNamespace COMSPEC_ATAK_Jammers = [[objet ou position, rayon, uid, camp, fin, filtrage ami]...]
    (les entrées de mission plus courtes [position, rayon] restent valables, sans fin ni filtrage).
    Renvoie [erreur GPS en mètres, BFT dégradé, décalage de la position affichée [dx, dy, 0], intensité 0-1].
*/
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_EwFx", []];
if ((count _cache) isEqualTo 2 && {diag_tickTime - (_cache select 0) < 1}) exitWith { _cache select 1 };
private _now = [time, serverTime] select isMultiplayer;
private _mySide = str side group player;
private _k = 0;
{
    _x params [["_pos", [0, 0, 0]], ["_rad", 500], ["_uid", ""], ["_side", ""], ["_until", 1e9], ["_exempt", false]];
    if (_until < _now) then { continue; };
    if (_exempt && {_side isEqualTo _mySide}) then { continue; };
    if (_pos isEqualType objNull) then { if (isNull _pos) then { continue; }; _pos = getPosATL _pos; };
    private _d = player distance2D _pos;
    if (_d < _rad) then { _k = _k max (1 - _d / (_rad max 1)); };
} forEach (missionNamespace getVariable ["COMSPEC_ATAK_Jammers", []]);
private _err = [0, round (25 + 175 * _k)] select (_k > 0);
// Puce GPS du téléphone abîmée (fn_deviceDamage) : erreur de 15 à 100 m, 400 m si elle est hors service.
private _gpsHw = ((missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap]) getOrDefault ["gps", 0]) * ([0, 1] select (missionNamespace getVariable ["comspec_atak_native_damage_sim", true]));
if (_gpsHw > 0) then { _err = _err + round ([15 + 85 * _gpsHw, 400] select (_gpsHw >= 1)); };
// Dérive lente de la position affichée : le cap de l'erreur tourne de quelques degrés à chaque calcul.
private _drift = uiNamespace getVariable ["COMSPEC_ATAK_EwDrift", random 360];
_drift = _drift + (random 50) - 25;
uiNamespace setVariable ["COMSPEC_ATAK_EwDrift", _drift];
private _off = [(sin _drift) * _err * 0.7, (cos _drift) * _err * 0.7, 0];
private _r = [_err, _k > 0.25, _off, _k];
uiNamespace setVariable ["COMSPEC_ATAK_EwFx", [diag_tickTime, _r]];
_r
