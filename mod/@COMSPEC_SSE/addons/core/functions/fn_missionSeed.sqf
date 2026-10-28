/*
    Graine maîtresse de la mission (NUMBER, identique sur toutes les machines).
    [] call comspec_sse_fnc_missionSeed
    - Graine écrite par le mission maker / dataset (comspec_sse_missionSeed, STRING) : rejouable.
    - Sinon tirée par le serveur et diffusée (comspec_sse_missionSeedNum).
*/
private _authored = missionNamespace getVariable ["comspec_sse_missionSeed", ""];
if (_authored isEqualType 0) then { _authored = str _authored; };
if (_authored isEqualType "" && {_authored isNotEqualTo ""}) exitWith {
    // Graine écrite après le preInit (dataset / module Zeus) : on recalcule dès qu'elle change,
    // sinon la graine aléatoire tirée au preInit resterait en cache et le dataset ne serait pas rejouable.
    if (isNil "comspec_sse_missionSeedNum" || {(missionNamespace getVariable ["comspec_sse_missionSeedSrc", ""]) isNotEqualTo _authored}) then {
        comspec_sse_missionSeedNum = [0, _authored] call comspec_sse_fnc_hash;
        comspec_sse_missionSeedSrc = _authored;
        if (isServer) then { publicVariable "comspec_sse_missionSeedNum"; };
    };
    comspec_sse_missionSeedNum
};

if (!isNil "comspec_sse_missionSeedNum") exitWith { comspec_sse_missionSeedNum };

if (isServer) exitWith {
    comspec_sse_missionSeedNum = floor random 16777215;
    publicVariable "comspec_sse_missionSeedNum";
    comspec_sse_missionSeedNum
};

// Client arrivé avant la diffusion du serveur : graine neutre, non mémorisée.
0
