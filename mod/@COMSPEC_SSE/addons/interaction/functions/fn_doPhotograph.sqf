/*
    [_target, _player] call comspec_sse_fnc_doPhotograph
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "camera"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Appareil photo SSE (ou caméra / tablette cTab compatible) requis.";
};

private _time = missionNamespace getVariable ["comspec_sse_timePhoto", 3];
private _hasKit = [_player, "camera"] call comspec_sse_fnc_hasEquipment;

[
    _time,
    "Photographie SSE...",
    {
        params ["_target", "_player", "_hasKit"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        private _quality = [55, _hasKit, 1, 1] call comspec_sse_fnc_calcQuality;
        private _fog = [_target, "inspect", _quality] call comspec_sse_fnc_revealFog;

        private _photos = [_target, "photos"] call comspec_sse_fnc_getSection;
        if (isNil "_photos" || {!(_photos isEqualType [])}) then { _photos = []; };
        _photos pushBack (createHashMapFromArray [
            ["by", name _player],
            ["at", time],
            ["quality", _quality],
            ["pos", getPosATL _target]
        ]);
        [_target, "photos", _photos, true] call comspec_sse_fnc_setSection;

        hint format ["Photo prise — qualité %1%% (%2)", _quality, [_quality] call comspec_sse_fnc_qualityLabel];
        [
            _fog getOrDefault ["uid", "?"],
            "photo",
            typeOf _target,
            format ["Photo qualité %1%%", _quality],
            _quality,
            "LOCAL"
        ] call comspec_sse_fnc_addJournalEntry;
    },
    [_target, _player, _hasKit]
] call comspec_sse_fnc_progressAction;
