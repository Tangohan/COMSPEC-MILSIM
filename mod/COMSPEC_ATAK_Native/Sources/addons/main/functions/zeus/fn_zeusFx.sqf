/*
    Effet Zeus en cours sur le téléphone d'un joueur (fn_zeusServer). Params : [clé, défaut, unité (player)]
    Variable d'unité publique COMSPEC_ATAK_ZeusFx = [[clé, valeur, fin (serverTime, -1 = jusqu'à annulation)]...],
    écrite par le serveur seul. Clés : "off" (désactivé), "lock" (verrouillé, message), "mute" (haut-parleurs coupés),
    "jam" (brouillage 1-100), "heat" (surchauffe 1-3), "drain" (décharge forcée, % / min).
*/
params [["_key", ""], ["_def", false], ["_unit", player]];
if (isNull _unit) exitWith { _def };
private _list = _unit getVariable ["COMSPEC_ATAK_ZeusFx", []];
if (_list isEqualTo []) exitWith { _def };
private _now = [time, serverTime] select isMultiplayer;
private _i = _list findIf { (_x param [0, ""]) isEqualTo _key };
if (_i < 0) exitWith { _def };
(_list select _i) params ["", ["_v", _def], ["_until", -1]];
if (_until >= 0 && {_until < _now}) exitWith { _def };
_v
