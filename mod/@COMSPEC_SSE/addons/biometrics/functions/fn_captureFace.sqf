params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "face"] call comspec_sse_fnc_hasEquipment) exitWith {
    ["Appareil photo, SEEK II ou tablette / caméra compatible requis.", "WARNING"] call comspec_sse_fnc_notify;
    false
};

[player, _target, "visage", "start"] call comspec_sse_fnc_bioAnimate;
[
    3,
    "Photo faciale...",
    {
        params ["_target"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        if (missionNamespace getVariable ["comspec_sse_bio_enabled", true]) exitWith {
            if ([_target, player, "visage"] call comspec_sse_fnc_bioApplyCapture) then {
                if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
                if (!isNil "comspec_overwatch_connect_fnc_sseCaptureFacePhoto") then {
                    [_target] call comspec_overwatch_connect_fnc_sseCaptureFacePhoto;
                };
            };
        };
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
        _bio set ["facePhoto", true];
        _bio set ["faceQuality", 75];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
        if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };

        if (!isNil "comspec_overwatch_connect_fnc_sseCaptureFacePhoto") then {
            [_target] call comspec_overwatch_connect_fnc_sseCaptureFacePhoto;
        } else {
            ["Photo faciale enregistrée.", "SUCCESS"] call comspec_sse_fnc_notify;
        };
    },
    [_target]
] call comspec_sse_fnc_progressAction;
true
