params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "dna"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Kit ADN SSE (ou SEEK II / kit médical ACE compatible) requis.";
    false
};

[
    12,
    "Prélèvement ADN...",
    {
        params ["_target"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        private _quality = [55, true, 1, 1] call comspec_sse_fnc_calcQuality;
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
        _bio set ["dnaId", format ["DNA-%1", [[_target] call comspec_sse_fnc_getSeed, "dna", 8] call comspec_sse_fnc_idToken]];
        _bio set ["dnaQuality", _quality];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
        hint format ["Prélèvement ADN — qualité %1%%", _quality];
        if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
    },
    [_target]
] call comspec_sse_fnc_progressAction;
true
