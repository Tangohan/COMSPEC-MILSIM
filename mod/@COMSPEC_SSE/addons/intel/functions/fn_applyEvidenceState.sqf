params [["_seed", 0, [0]], ["_type", "OBJECT", [""]]];
private _states = ["INTACT", "DAMAGED", "WET", "BURNED", "BROKEN", "CONTAMINATED"];
private _w = [_seed, "estate"] call comspec_sse_fnc_hash;
private _st = _states select (_w mod (count _states));
if ((_w mod 100) < 55) then { _st = "INTACT"; };
private _penalty = switch (_st) do {
    case "INTACT": { 0 };
    case "DAMAGED"; case "WET": { 10 };
    case "BROKEN"; case "BURNED": { 25 };
    case "CONTAMINATED": { 15 };
    default { 5 };
};
createHashMapFromArray [
    ["state", _st],
    ["qualityPenalty", _penalty],
    ["note", format ["État physique : %1", _st]]
]
