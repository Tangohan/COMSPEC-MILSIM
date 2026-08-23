params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "face"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Appareil photo, SEEK II ou tablette / caméra compatible requis.";
    false
};

[
    3,
    "Photo faciale...",
    {
        params ["_target"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
        _bio set ["facePhoto", true];
        _bio set ["faceQuality", 75];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
        if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };

        if (!isNil "comspec_overwatch_connect_fnc_sseCaptureFacePhoto") then {
            [_target] call comspec_overwatch_connect_fnc_sseCaptureFacePhoto;
        } else {
            hint "Photo faciale enregistrée.";
        };
    },
    [_target]
] call comspec_sse_fnc_progressAction;
true
