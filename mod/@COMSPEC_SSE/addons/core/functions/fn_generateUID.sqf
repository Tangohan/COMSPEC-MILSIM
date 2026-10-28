/*
    Génère un UID SSE unique.
    [_prefix, _seed] call comspec_sse_fnc_generateUID
    Format : PREFIX-AA-MMMM-NNNNNN
      MMMM   : jeton de mission (graine maîtresse) → pas de collision d'une opération à l'autre (clés Athena)
      NNNNNN : dérivé de la graine de l'entité si fournie (identique sur toutes les machines),
               sinon compteur serveur, ou compteur client préfixé par clientOwner.
*/
params [
    ["_prefix", "SSE", [""]],
    ["_seed", -1, [0]]
];

private _year = (date select 0) mod 100;
private _yPad = str _year;
if (count _yPad < 2) then { _yPad = "0" + _yPad; };

private _mission = [[] call comspec_sse_fnc_missionSeed, "mission"] call comspec_sse_fnc_idToken;
_mission = _mission select [count _mission - 4];

private _tail = if (_seed >= 0) then {
    [_seed, "uid"] call comspec_sse_fnc_idToken
} else {
    if (isNil "comspec_sse_uidCounter") then { comspec_sse_uidCounter = 0; };
    comspec_sse_uidCounter = comspec_sse_uidCounter + 1;
    private _n = if (isServer) then { comspec_sse_uidCounter } else { (clientOwner mod 100) * 10000 + (comspec_sse_uidCounter mod 10000) };
    private _pad = _n toFixed 0;
    while {count _pad < 6} do { _pad = "0" + _pad; };
    _pad
};
_tail = _tail select [count _tail - 6];

format ["%1-%2-%3-%4", _prefix, _yPad, _mission, _tail]
