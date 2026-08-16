/*
    [_target, _player] call comspec_sse_fnc_doPhotograph
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "camera"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Appareil photo SSE, S7 Android (cTab) ou caméra / tablette compatible requis.";
};

private _time = missionNamespace getVariable ["comspec_sse_timePhoto", 3];
private _hasKit = [_player, "camera"] call comspec_sse_fnc_hasEquipment;

[
    _time,
    "Photographie SSE...",
    {
        params ["_target", "_player", "_hasKit"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        private _damage = damage _target;
        private _condition = (1 - (_damage min 0.9));
        private _quality = [55, _hasKit, 1, _condition, 1] call comspec_sse_fnc_calcQuality;
        private _fog = [_target, "inspect", _quality] call comspec_sse_fnc_revealFog;

        private _pos = getPosATL _target;
        private _heading = round (getDir _player);
        private _missionId = missionNamespace getVariable ["comspec_sse_missionId", "OP"];
        private _uid = _fog getOrDefault ["uid", ""];
        private _room = "SITE";
        private _siteRef = "";
        private _data = [_target] call comspec_sse_fnc_getData;
        if (!isNil "_data" && {_data isEqualType []}) then {
            private _cluster = [_data, "cluster", createHashMap] call comspec_sse_fnc_getPair;
            if (_cluster isEqualType createHashMap) then {
                _siteRef = _cluster getOrDefault ["siteRef", ""];
                _room = _cluster getOrDefault ["room", _cluster getOrDefault ["area", "SITE"]];
            };
        };

        private _photoType = if (_target isKindOf "CAManBase") then { "FACE" } else { "EVIDENCE" };
        private _photoMeta = createHashMapFromArray [
            ["by", name _player],
            ["at", time],
            ["dtg", systemTimeUTC],
            ["quality", _quality],
            ["quality_label", [_quality] call comspec_sse_fnc_qualityLabel],
            ["pos", _pos],
            ["heading", _heading],
            ["photo_type", _photoType],
            ["mission_id", _missionId],
            ["site_ref", _siteRef],
            ["room", _room],
            ["target_ref", _uid],
            ["target_type", typeOf _target]
        ];

        private _photos = [_target, "photos"] call comspec_sse_fnc_getSection;
        if (isNil "_photos" || {!(_photos isEqualType [])}) then { _photos = []; };
        _photos pushBack _photoMeta;
        [_target, "photos", _photos, true] call comspec_sse_fnc_setSection;

        hint format ["Photo prise — qualité %1%% (%2)", _quality, [_quality] call comspec_sse_fnc_qualityLabel];
        [
            _uid,
            "photo",
            typeOf _target,
            format ["Photo %1 qualité %2%%", _photoType, _quality],
            _quality,
            "LOCAL"
        ] call comspec_sse_fnc_addJournalEntry;

        private _photoEnv = createHashMapFromArray [
            ["event_type", "PHOTOGRAPHED"],
            ["source_system", "ACE"],
            ["entity_type", if (_target isKindOf "CAManBase") then { "PERSON" } else { "OBJECT" }],
            ["summary", format ["Photographie SSE — %1 — qualité %2%%", _photoType, _quality]],
            ["identity_tier", "DECLARED"],
            ["source_reliability", "C"],
            ["info_credibility", 3],
            ["author_label", name _player],
            ["payload", createHashMapFromArray [
                ["quality", _quality],
                ["photo_type", _photoType],
                ["heading", _heading],
                ["mission_id", _missionId],
                ["site_ref", _siteRef],
                ["room", _room],
                ["target_type", typeOf _target],
                ["uid", _uid],
                ["pos", _pos]
            ]]
        ];
        ["COMSPEC_SSE_PHOTO_TAKEN", _photoEnv, false] call comspec_sse_fnc_raiseSseEvent;
    },
    [_target, _player, _hasKit]
] call comspec_sse_fnc_progressAction;
