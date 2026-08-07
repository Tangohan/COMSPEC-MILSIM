params [
    ["_entity", objNull, [objNull]],
    ["_player", player, [objNull]]
];
private _sec = [_entity, "sections"] call comspec_sse_fnc_getSection;
if (isNil "_sec" || {!(_sec isEqualType createHashMap)}) exitWith { false };
private _caches = _sec getOrDefault ["hiddenCaches", []];
if !(_caches isEqualType []) exitWith { false };
private _revealed = false;
{
    if (_x isEqualType createHashMap && {!(_x getOrDefault ["revealed", false])}) then {
        private _tool = _x getOrDefault ["tool", ""];
        private _ok = _tool == "" || {[_player, "evidence_bag"] call comspec_sse_fnc_hasEquipment};
        if (_ok) then {
            _x set ["revealed", true];
            _revealed = true;
            hint format ["Cache révélée : %1 — %2", _x getOrDefault ["label", "?"], _x getOrDefault ["content", ""]];
            ["SSE_IntelDiscovered", [_entity, _x getOrDefault ["id", ""], _x]] call comspec_sse_fnc_emitEvent;
        };
    };
} forEach _caches;
if (_revealed) then {
    _sec set ["hiddenCaches", _caches];
    [_entity, "sections", _sec, true] call comspec_sse_fnc_setSection;
};
_revealed
