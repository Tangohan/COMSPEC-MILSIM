params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "fingerprint"] call comspec_sse_fnc_hasEquipment) exitWith {
    ["Kit d'empreintes, SEEK II ou tablette terrain compatible requis.", "WARNING"] call comspec_sse_fnc_notify;
    false
};

private _time = missionNamespace getVariable ["comspec_sse_timeFingerprint", 8];
[player, _target, "empreintes", "start"] call comspec_sse_fnc_bioAnimate;
[
    _time,
    "Relevé d'empreintes...",
    {
        params ["_target", "_player"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        if (missionNamespace getVariable ["comspec_sse_bio_enabled", true]) exitWith {
            if ([_target, _player, "empreintes"] call comspec_sse_fnc_bioApplyCapture) then {
                private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
                private _seed = [_target] call comspec_sse_fnc_getSeed;
                _bio set ["fingerprintId", format ["FP-%1", [_seed, "fp", 8] call comspec_sse_fnc_idToken]];
                [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
                private _q = _bio getOrDefault ["fingerprintQuality", 0];
                private _data = [_target] call comspec_sse_fnc_getData;
                private _uid = if (isNil "_data") then {"?"} else {[_data, "uid", "?"] call BIS_fnc_getFromPairs};
                [_uid, "fingerprint", typeOf _target, format ["FP Q%1%%", _q], _q, "LOCAL"] call comspec_sse_fnc_addJournalEntry;
            };
            if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
        };
        private _quality = [60, true, 1, 1, 1, _player, _target, "bio"] call comspec_sse_fnc_calcQuality;
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
        private _seed = [_target] call comspec_sse_fnc_getSeed;
        _bio set ["fingerprintId", format ["FP-%1", [_seed, "fp", 8] call comspec_sse_fnc_idToken]];
        _bio set ["fingerprintQuality", _quality];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;
        [format ["Empreintes relevées — qualité %1%% (%2)", _quality, [_quality] call comspec_sse_fnc_qualityLabel], "INFO"] call comspec_sse_fnc_notify;
        private _data = [_target] call comspec_sse_fnc_getData;
        private _uid = if (isNil "_data") then {"?"} else {[_data, "uid", "?"] call BIS_fnc_getFromPairs};
        [_uid, "fingerprint", typeOf _target, format ["FP Q%1%%", _quality], _quality, "LOCAL"] call comspec_sse_fnc_addJournalEntry;
        if (!isNull (findDisplay 93100)) then { [] call comspec_sse_fnc_seekOnLoad; };
    },
    [_target, _player]
] call comspec_sse_fnc_progressAction;
true
