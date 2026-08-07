/*
    Identification simulée (watchlist / bases).
    [_target, _player] call comspec_sse_fnc_identifySubject
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

[
    8,
    "Interrogation bases d'identité...",
    {
        params ["_target"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        private _seed = [_target] call comspec_sse_fnc_getSeed;
        private _identity = [_target, "identity"] call comspec_sse_fnc_getSection;
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };

        private _roll = ([_seed, "idmatch"] call comspec_sse_fnc_hash) mod 100;
        private _verdict = if (_roll < 45) then {
            "INCONNU des bases"
        } else {
            if (_roll < 75) then {
                "SIGNALÉ — correspondance partielle"
            } else {
                "RECHERCHÉ — correspondance confirmée"
            }
        };

        private _recordRef = format ["SSE-WL-%1", [_seed, "wl"] call comspec_sse_fnc_hash];
        _bio set ["matchHint", _verdict];
        _bio set ["watchlistRef", _recordRef];
        _bio set ["matchConfidence", _roll];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;

        private _name = if (!isNil "_identity" && {_identity isEqualType createHashMap}) then {
            _identity getOrDefault ["name", "Sujet"]
        } else { "Sujet" };

        private _lines = [
            format ["Sujet : %1", _name],
            format ["Verdict : %1", _verdict],
            format ["Réf. : %1", _recordRef],
            format ["Confiance simulée : %1%%", _roll]
        ];
        hint (_lines joinString endl);

        if (!isNil "comspec_sse_fnc_showResult") then {
            [createHashMapFromArray [
                ["title", "SEEK II — IDENTITY QUERY"],
                ["uid", _recordRef],
                ["type", "IDENTITY"],
                ["lines", _lines],
                ["quality", _roll],
                ["qualityLabel", [_roll] call comspec_sse_fnc_qualityLabel]
            ]] call comspec_sse_fnc_showResult;
        };
    },
    [_target]
] call comspec_sse_fnc_progressAction;
true
