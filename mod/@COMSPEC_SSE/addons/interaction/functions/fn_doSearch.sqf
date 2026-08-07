/*
    [_target, _player, _full] call comspec_sse_fnc_doSearch
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]],
    ["_full", true, [true]]
];

private _time = if (_full) then {
    missionNamespace getVariable ["comspec_sse_timeSearchFull", 15]
} else {
    missionNamespace getVariable ["comspec_sse_timeSearchQuick", 5]
};

[
    _time,
    if (_full) then {"Fouille SSE complète..."} else {"Fouille SSE rapide..."},
    {
        params ["_target", "_player", "_full"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        ["setstate", _target, ["SEARCHED"]] call comspec_sse_fnc_requestServerOp;

        private _quality = [if (_full) then {70} else {45}, true, if (_full) then {1.2} else {0.7}, 1] call comspec_sse_fnc_calcQuality;
        private _skill = if (!isNil "comspec_sse_fnc_getOperatorSkill") then { [] call comspec_sse_fnc_getOperatorSkill } else { 1 };
        _quality = (_quality + (_skill * 5)) min 95;

        private _fog = [_target, "search", _quality] call comspec_sse_fnc_revealFog;
        private _lines = _fog getOrDefault ["lines", []];

        // Niveaux successifs Tactical → …
        if (!isNil "comspec_sse_fnc_advanceExploitation") then {
            private _adv = [_target, _player] call comspec_sse_fnc_advanceExploitation;
            { _lines pushBack _x } forEach (_adv getOrDefault ["lines", []]);
            _fog set ["exploitationLevel", _adv getOrDefault ["level", ""]];
        };

        if (missionNamespace getVariable ["comspec_sse_autoTriage", true] && {!isNil "comspec_sse_fnc_triageSite"}) then {
            private _tri = [_target, 25] call comspec_sse_fnc_triageSite;
            if (count _tri > 0) then {
                private _top = _tri select 0;
                _lines pushBack format ["Triage : %1 (valeur %2)", _top getOrDefault ["triage", "?"], _top getOrDefault ["INTEL_VALUE", 0]];
            };
        };

        hint ((_fog getOrDefault ["title", "SSE"]) + endl + (_lines joinString endl));

        [
            _fog getOrDefault ["uid", "?"],
            "search",
            typeOf _target,
            _lines joinString " | ",
            _quality,
            "LOCAL"
        ] call comspec_sse_fnc_addJournalEntry;

        if (!isNil "comspec_sse_fnc_showResult") then {
            _fog set ["lines", _lines];
            [_fog] call comspec_sse_fnc_showResult;
        };
    },
    [_target, _player, _full]
] call comspec_sse_fnc_progressAction;
