params [["_seed", 0, [0]], ["_type", "OBJECT", [""]]];
private _states = ["OPEN", "LOCKED", "ENCRYPTED", "PARTIAL_ACCESS", "WIPED"];
private _roll = [_seed, "access"] call comspec_sse_fnc_hash;
private _st = "OPEN";
if (_type in ["PHONE", "SMARTPHONE", "COMPUTER", "LAPTOP", "PERSON"]) then {
    private _r = _roll mod 100;
    if (_r < 15) then { _st = "ENCRYPTED"; };
    if (_r >= 15 && {_r < 35}) then { _st = "LOCKED"; };
    if (_r >= 35 && {_r < 45}) then { _st = "PARTIAL_ACCESS"; };
    if (_r >= 95) then { _st = "WIPED"; };
};
// Anti-exploitation narratif
private _trap = (_roll mod 100) < 8;
createHashMapFromArray [
    ["state", _st],
    ["batteryDead", (_roll mod 100) < 12],
    ["driveRemoved", (_roll mod 100) > 92],
    ["passwordProtected", _st in ["LOCKED", "ENCRYPTED"]],
    ["wipeArmed", _trap],
    ["note", if (_trap) then {"Indicateurs d'effacement rapide / piège narratif"} else {""}]
]
