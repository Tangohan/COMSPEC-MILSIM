/*
    Capture biométrique complète séquentielle.
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

private _hasSeek = [_player, "seek"] call comspec_sse_fnc_hasEquipment;
if (!_hasSeek) then {
    private _hasKits =
        ([_player, "fingerprint"] call comspec_sse_fnc_hasEquipment)
        && {[_player, "dna"] call comspec_sse_fnc_hasEquipment}
        && {[_player, "face"] call comspec_sse_fnc_hasEquipment};
    if (!_hasKits) exitWith {
        hint "SEEK II / tablette compatible, ou kits empreintes + ADN + photo requis.";
        false
    };
};

[
    25,
    "SEEK II — capture complète...",
    {
        params ["_target", "_player"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
        private _seed = [_target] call comspec_sse_fnc_getSeed;

        _bio set ["fingerprintId", format ["FP-%1", [_seed, "fp"] call comspec_sse_fnc_hash]];
        _bio set ["fingerprintQuality", 85];
        _bio set ["irisId", format ["IR-%1", [_seed, "ir"] call comspec_sse_fnc_hash]];
        _bio set ["irisQuality", 82];
        _bio set ["facePhoto", true];
        _bio set ["faceQuality", 80];
        _bio set ["dnaId", format ["DNA-%1", [_seed, "dna"] call comspec_sse_fnc_hash]];
        _bio set ["dnaQuality", 70];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;

        private _status = [_target, "sectionStatus"] call comspec_sse_fnc_getSection;
        if (!isNil "_status" && {_status isEqualType createHashMap}) then {
            _status set ["biometrics", "complete"];
            [_target, "sectionStatus", _status, true] call comspec_sse_fnc_setSection;
        };

        ["setstate", _target, ["PARTIALLY_EXPLOITED"]] call comspec_sse_fnc_requestServerOp;
        hint "Capture biométrique complète terminée.";
        private _data = [_target] call comspec_sse_fnc_getData;
        private _uid = if (isNil "_data") then {"?"} else {[_data, "uid", "?"] call BIS_fnc_getFromPairs};
        [_uid, "biometrics", "capture_all", "FP+IRIS+FACE+DNA", 80, "LOCAL"] call comspec_sse_fnc_addJournalEntry;
    },
    [_target, _player]
] call comspec_sse_fnc_progressAction;
true
