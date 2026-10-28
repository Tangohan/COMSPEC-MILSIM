params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "dna"] call comspec_sse_fnc_hasEquipment) exitWith {
    ["Kit ADN SSE (ou SEEK II / kit médical ACE compatible) requis.", "WARNING"] call comspec_sse_fnc_notify;
    false
};

[player, _target, "adn", "start"] call comspec_sse_fnc_bioAnimate;
[
    12,
    "Prélèvement ADN...",
    {
        params ["_target"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        if (missionNamespace getVariable ["comspec_sse_bio_enabled", true]) exitWith {
            if ([_target, player, "adn"] call comspec_sse_fnc_bioApplyCapture) then {
                private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
                _bio set ["dnaId", format ["DNA-%1", [[_target] call comspec_sse_fnc_getSeed, "dna", 8] call comspec_sse_fnc_idToken]];
                [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
                // L'écouvillon part au laboratoire : résultat différé.
                ["dna", createHashMapFromArray [
                    ["unit", _target], ["key", [_target] call comspec_sse_fnc_bioPersonKey],
                    ["dna", _bio getOrDefault ["dnaQuality", 0]], ["dnaRef", _bio getOrDefault ["dnaRef", ""]]
                ], { ["Échantillon ADN expédié au laboratoire : résultat à venir.", "INFO"] call comspec_sse_fnc_notify; }] call comspec_sse_fnc_bioRequest;
            };
            if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
        };
        private _quality = [55, true, 1, 1, 1, player, _target, "bio"] call comspec_sse_fnc_calcQuality;
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
        _bio set ["dnaId", format ["DNA-%1", [[_target] call comspec_sse_fnc_getSeed, "dna", 8] call comspec_sse_fnc_idToken]];
        _bio set ["dnaQuality", _quality];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
        [format ["Prélèvement ADN — qualité %1%%", _quality], "INFO"] call comspec_sse_fnc_notify;
        if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
    },
    [_target]
] call comspec_sse_fnc_progressAction;
true
