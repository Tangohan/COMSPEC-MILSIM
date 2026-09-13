/*
    Graine SSE stable d'une entité.

    Toute donnée fictive dérivée d'un PNJ — résultat de requête, indice de confiance,
    référence de dossier biométrique — doit être reconstruite à l'identique à chaque
    interrogation. Un « random » à la requête donnerait une réponse différente à chaque
    ouverture du terminal, ce qui ruine l'exploitation.

    Priorité :
      1. graine posée par le chef de mission (Eden / Zeus) — COMSPEC_SSE_Seed
      2. graine dérivée de l'identifiant réseau, stable pour la session
      3. repli sur le nom de l'unité

    Params: [_unit]
    Returns: Number — graine positive
*/
params [["_unit", objNull, [objNull]]];

if (isNull _unit) exitWith { 0 };

private _seed = _unit getVariable ["COMSPEC_SSE_Seed", 0];
if (_seed isEqualType 0 && { _seed > 0 }) exitWith { _seed };

private _key = netId _unit;
if (!(_key isEqualType "") || { _key isEqualTo "" }) then { _key = name _unit; };
if (!(_key isEqualType "") || { _key isEqualTo "" }) then { _key = str _unit; };

// Hachage positionnel simple : deux chaînes proches donnent des graines éloignées.
private _h = 7;
{
    _h = ((_h * 31) + _x) mod 1000003;
} forEach toArray _key;

_seed = 100000 + (_h mod 900000);
_unit setVariable ["COMSPEC_SSE_Seed", _seed, true];

_seed
