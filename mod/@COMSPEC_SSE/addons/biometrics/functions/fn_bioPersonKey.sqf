/*
    Clé biométrique d'une personne : ce qui la rend « la même » d'un contrôle à l'autre.

    Priorité :
      1. identifiant fixé par le chef de mission (Eden / Zeus / script) —
         COMSPEC_SSE_BioId, ex. « HVT-ABU-HAMZA » : stable entre missions avec la
         même graine de campagne ;
      2. graine SSE du sujet (COMSPEC_SSE_Seed, posée par Eden ou dérivée du réseau).

    [_unit] call comspec_sse_fnc_bioPersonKey -> String ("" si objet nul)
*/
params [["_unit", objNull, [objNull]]];
if (isNull _unit) exitWith { "" };

private _id = _unit getVariable ["COMSPEC_SSE_BioId", ""];
if ((_id isEqualType "") && {(trim _id) isNotEqualTo ""}) exitWith { toUpper (trim _id) };

private _seed = _unit getVariable ["COMSPEC_SSE_Seed", 0];
if (!(_seed isEqualType 0) || {_seed <= 0}) then {
    if (!isNil "comspec_overwatch_connect_fnc_sseUnitSeed") then {
        _seed = [_unit] call comspec_overwatch_connect_fnc_sseUnitSeed;
    } else {
        // Même dérivation que le terminal Overwatch : identifiant réseau.
        private _k = netId _unit;
        if (!(_k isEqualType "") || {_k isEqualTo ""}) then { _k = str _unit; };
        private _h = 7;
        { _h = ((_h * 31) + _x) mod 1000003; } forEach (toArray _k);
        _seed = 100000 + (_h mod 900000);
        _unit setVariable ["COMSPEC_SSE_Seed", _seed, true];
    };
};
format ["P%1", round _seed]
