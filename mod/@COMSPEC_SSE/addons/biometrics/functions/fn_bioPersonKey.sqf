/*
    Clé biométrique d'une personne : ce qui la rend « la même » d'un contrôle à l'autre.

    La clé est attribuée par le serveur (fn_bioRegistry) et publiée sur l'unité
    (COMSPEC_SSE_BioKey) : une identité = une clé, jamais deux personnages pour la
    même biométrie. Sur le serveur, une unité sans clé en reçoit une immédiatement.

    Repli côté joueur si la clé n'est pas encore arrivée (quelques images après
    l'apparition d'une unité) : identifiant fixé, sinon graine SSE de l'unité.

    [_unit] call comspec_sse_fnc_bioPersonKey -> String ("" si objet nul)
*/
params [["_unit", objNull, [objNull]]];
if (isNull _unit) exitWith { "" };

private _have = _unit getVariable ["COMSPEC_SSE_BioKey", ""];
if (_have isEqualType "" && {_have isNotEqualTo ""}) exitWith { _have };
if (isServer && {_unit isKindOf "CAManBase"}) exitWith { ["assign", _unit] call comspec_sse_fnc_bioRegistry };

private _id = _unit getVariable ["COMSPEC_SSE_BioId", ""];
if ((_id isEqualType "") && {(trim _id) isNotEqualTo ""}) exitWith { toUpper (trim _id) };

private _seed = _unit getVariable ["COMSPEC_SSE_Seed", 0];
if (!(_seed isEqualType 0) || {_seed <= 0}) then {
    if (!isNil "comspec_overwatch_connect_fnc_sseUnitSeed") then {
        _seed = [_unit] call comspec_overwatch_connect_fnc_sseUnitSeed;
    } else {
        private _k = netId _unit;
        if (!(_k isEqualType "") || {_k isEqualTo ""}) then { _k = str _unit; };
        private _h = 7;
        { _h = ((_h * 31) + _x) mod 1000003; } forEach (toArray _k);
        _seed = 100000 + (_h mod 900000);
    };
};
format ["P%1", round _seed]
