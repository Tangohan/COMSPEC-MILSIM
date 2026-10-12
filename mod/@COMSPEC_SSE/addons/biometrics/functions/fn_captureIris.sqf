params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "iris"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Scanner oculaire, SEEK II ou kit iris compatible requis.";
    false
};

[
    5,
    "Capture iris...",
    {
        params ["_target"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        if (missionNamespace getVariable ["comspec_sse_bio_enabled", true]) exitWith {
            if ([_target, player, "iris"] call comspec_sse_fnc_bioApplyCapture) then {
                private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
                _bio set ["irisId", format ["IR-%1", [[_target] call comspec_sse_fnc_getSeed, "ir", 8] call comspec_sse_fnc_idToken]];
                [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
            };
            if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
        };
        private _quality = [70, true, 1, 1] call comspec_sse_fnc_calcQuality;
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
        _bio set ["irisId", format ["IR-%1", [[_target] call comspec_sse_fnc_getSeed, "ir", 8] call comspec_sse_fnc_idToken]];
        _bio set ["irisQuality", _quality];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
        hint format ["Iris capturé — %1%%", _quality];
        if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
    },
    [_target]
] call comspec_sse_fnc_progressAction;
true
