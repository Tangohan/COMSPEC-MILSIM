params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "fingerprint"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Kit d'empreintes, SEEK II ou tablette terrain compatible requis.";
    false
};

private _time = missionNamespace getVariable ["comspec_sse_timeFingerprint", 8];
[
    _time,
    "Relevé d'empreintes...",
    {
        params ["_target", "_player"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        private _quality = [60, true, 1, 1] call comspec_sse_fnc_calcQuality;
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
        private _seed = [_target] call comspec_sse_fnc_getSeed;
        _bio set ["fingerprintId", format ["FP-%1", [_seed, "fp"] call comspec_sse_fnc_hash]];
        _bio set ["fingerprintQuality", _quality];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
        hint format ["Empreintes relevées — qualité %1%% (%2)", _quality, [_quality] call comspec_sse_fnc_qualityLabel];
        private _data = [_target] call comspec_sse_fnc_getData;
        private _uid = if (isNil "_data") then {"?"} else {[_data, "uid", "?"] call BIS_fnc_getFromPairs};
        [_uid, "fingerprint", typeOf _target, format ["FP Q%1%%", _quality], _quality, "LOCAL"] call comspec_sse_fnc_addJournalEntry;
        if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
    },
    [_target, _player]
] call comspec_sse_fnc_progressAction;
true
