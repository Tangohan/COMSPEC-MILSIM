params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "seek"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Terminal SEEK II (ou tablette compatible) requis.";
    false
};

[
    5,
    "Capture iris...",
    {
        params ["_target"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        private _quality = [70, true, 1, 1] call comspec_sse_fnc_calcQuality;
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
        _bio set ["irisId", format ["IR-%1", [[_target] call comspec_sse_fnc_getSeed, "ir"] call comspec_sse_fnc_hash]];
        _bio set ["irisQuality", _quality];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
        hint format ["Iris capturé — %1%%", _quality];
        if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
    },
    [_target]
] call comspec_sse_fnc_progressAction;
true
