/*
    Identification réelle via le poste Athena (registre + listes de surveillance).
    [_target, _player] call comspec_sse_fnc_identifySubject

    Aucune correspondance n’est inventée : si le poste est muet ou hors liaison,
    le terminal l’affiche clairement.
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

[
    8,
    "Interrogation du poste d'identité...",
    {
        params ["_target"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        if (!isNil "comspec_sse_fnc_uiSetRecord") then {
            [_target] call comspec_sse_fnc_uiSetRecord;
        };
        missionNamespace setVariable ["comspec_sse_lastResultEntity", _target];

        private _identity = [_target, "identity"] call comspec_sse_fnc_getSection;
        if (isNil "_identity" || {!(_identity isEqualType createHashMap)}) then {
            _identity = createHashMap;
        };
        private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
        if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };

        private _first = trim (_target getVariable ["COMSPEC_SSE_FirstName", ""]);
        private _last = trim (_target getVariable ["COMSPEC_SSE_LastName", ""]);
        private _alias = trim (_identity getOrDefault ["alias", ""]);
        if (_alias isEqualTo "") then {
            _alias = trim (_target getVariable ["COMSPEC_SSE_Alias", ""]);
        };
        if (_first isEqualTo "") then {
            _first = trim (_identity getOrDefault ["firstName", ""]);
        };
        if (_last isEqualTo "") then {
            _last = trim (_identity getOrDefault ["lastName", ""]);
        };
        if (_last isEqualTo "") then {
            _last = trim (_identity getOrDefault ["name", ""]);
        };

        private _name = trim (format ["%1 %2", _first, _last]);
        if (_name isEqualTo "") then {
            _name = trim (_identity getOrDefault ["name", ""]);
        };
        if (_name isEqualTo "" && {_target isKindOf "CAManBase"}) then {
            private _unitName = name _target;
            if (_unitName isNotEqualTo "" && {(_unitName find "Error:") < 0}) then {
                _name = _unitName;
            };
        };
        if (_name isEqualTo "") then { _name = "Sujet"; };

        private _verdict = "Poste injoignable";
        private _recordRef = "";
        private _score = 0;
        private _note = "Aucune interrogation inventée — reliez le terminal au poste, puis relancez QUERY.";
        private _okQuery = false;

        if (_first isEqualTo "" && {_last isEqualTo ""} && {_alias isEqualTo ""}) then {
            private _parts = _name splitString " ";
            if ((count _parts) >= 2) then {
                _first = _parts select 0;
                _last = (_parts select [1, (count _parts) - 1]) joinString " ";
            } else {
                _last = _name;
            };
        };

        if (!isNil "comspec_sse_fnc_extensionCall") then {
            private _raw = ["QuerySseIdentity", [_first, _last, _alias, _name]] call comspec_sse_fnc_extensionCall;
            if (!(_raw isEqualType "")) then { _raw = str _raw; };
            private _u = toUpper _raw;
            if ((_raw find "OK|") == 0) then {
                _okQuery = true;
                private _payload = _raw select [3];
                private _cols = _payload splitString toString [9];
                private _found = (count _cols) > 0 && {(_cols select 0) isEqualTo "1"};
                _verdict = if ((count _cols) > 1) then { _cols select 1 } else { "Réponse incomplète" };
                _score = if ((count _cols) > 2) then { parseNumber (_cols select 2) } else { 0 };
                private _remoteName = if ((count _cols) > 3) then { trim (_cols select 3) } else { "" };
                if (_remoteName isNotEqualTo "") then { _name = _remoteName; };
                private _remoteAlias = if ((count _cols) > 4) then { trim (_cols select 4) } else { "" };
                if (_remoteAlias isNotEqualTo "") then { _alias = _remoteAlias; };
                _recordRef = if ((count _cols) > 5) then { trim (_cols select 5) } else { "" };
                _note = if ((count _cols) > 6) then { trim (_cols select 6) } else { "" };
                if (!_found && {_verdict isEqualTo ""}) then {
                    _verdict = "Aucune correspondance sur le poste";
                };
            } else {
                if ((_u find "ERR|UNAUTHORIZED") >= 0 || {(_u find "ERR|NO_TENANT") >= 0}) then {
                    _note = "Liaison Athena manquante ou communauté non identifiée.";
                } else {
                    if ((_u find "ERR|UNAVAILABLE") >= 0 || {(_u find "ERR|HTTP_") >= 0}) then {
                        _note = "Le poste n’a pas répondu. Réessayez après resynchronisation.";
                    };
                };
            };
        };

        _bio set ["matchHint", _verdict];
        _bio set ["watchlistRef", _recordRef];
        _bio set ["matchConfidence", _score];
        _bio set ["matchNote", _note];
        _bio set ["matchLive", _okQuery];
        [_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;

        private _lines = [
            format ["Sujet : %1", _name]
        ];
        if (_alias isNotEqualTo "") then {
            _lines pushBack format ["Alias : %1", _alias];
        };
        _lines pushBack format ["Verdict : %1", _verdict];
        if (_recordRef isNotEqualTo "") then {
            _lines pushBack format ["Réf. : %1", _recordRef];
        };
        if (_okQuery) then {
            if (_score > 0) then {
                _lines pushBack format ["Confiance : %1%%", _score];
            };
        } else {
            _lines pushBack "Source : poste injoignable (rien n’a été inventé)";
        };
        if (_note isNotEqualTo "") then {
            _lines pushBack _note;
        };
        hint (_lines joinString endl);

        if (!isNil "comspec_sse_fnc_showResult") then {
            [createHashMapFromArray [
                ["title", "SEEK II — IDENTITY QUERY"],
                ["uid", [_recordRef, "QUERY"] select (_recordRef isEqualTo "")],
                ["type", "IDENTITY"],
                ["lines", _lines],
                ["quality", _score],
                ["qualityLabel", [_score] call comspec_sse_fnc_qualityLabel]
            ]] call comspec_sse_fnc_showResult;
        };
    },
    [_target]
] call comspec_sse_fnc_progressAction;
true
