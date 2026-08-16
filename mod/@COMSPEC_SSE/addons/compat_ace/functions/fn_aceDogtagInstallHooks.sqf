/*
    Installe les wraps ACE dogtags → SSE (sans modifier le PBO ACE).
    Ne jamais écraser une fonction compileFinal (risque STACK_OVERFLOW).
*/
if (missionNamespace getVariable ["comspec_sse_aceDogtagHooksInstalled", false]) exitWith { true };
if !([] call comspec_sse_fnc_aceDogtagIsPresent) exitWith { false };

private _tryWrap = {
    params ["_varName", "_newCode", "_oldStore"];
    if (isNil _varName) exitWith { false };
    private _old = missionNamespace getVariable [_varName, nil];
    if (isNil "_old" || {!(_old isEqualType {})}) exitWith { false };
    if (!isNil "isFinal" && {isFinal _old}) exitWith {
        [format ["ACE dogtag hook skip %1 (compileFinal)", _varName], "WARNING"] call comspec_sse_fnc_log;
        false
    };
    try {
        missionNamespace setVariable [_oldStore, _old];
        missionNamespace setVariable [_varName, _newCode];
        true
    } catch {
        private _err = if (!isNil "_exception") then { str _exception } else { "unknown" };
        [format ["ACE dogtag hook skip %1: %2", _varName, _err], "WARNING"] call comspec_sse_fnc_log;
        false
    }
};

// Lecture plaque : forcer les données ACE depuis l’identité SSE
[
    "ace_dogtags_fnc_getDogtagData",
    {
        params ["_target"];

        private _sseTag = [];
        if (
            missionNamespace getVariable ["comspec_sse_aceDogtagBridgeEnabled", true]
            && {!isNull _target}
            && {_target isKindOf "CAManBase"}
        ) then {
            private _wantSse = (_target getVariable ["comspec_sse_enabled", false])
                || {!isNil {[_target] call comspec_sse_fnc_getData}};
            if (_wantSse) then {
                if (!isNil "comspec_sse_fnc_ensureGenerated") then {
                    [_target] call comspec_sse_fnc_ensureGenerated;
                };
                _sseTag = [_target] call comspec_sse_fnc_aceDogtagFromSse;
            };
        };

        if (_sseTag isNotEqualTo []) exitWith {
            _target setVariable ["ace_dogtags_dogtagData", _sseTag, true];
            _sseTag
        };

        private _old = missionNamespace getVariable ["comspec_sse_ace_oldGetDogtagData", {}];
        _this call _old
    },
    "comspec_sse_ace_oldGetDogtagData"
] call _tryWrap;

// Vérification plaque : ACE affiche + dossier SSE alimenté
[
    "ace_dogtags_fnc_checkDogtag",
    {
        params ["_player", "_target"];

        if (
            missionNamespace getVariable ["comspec_sse_aceDogtagBridgeEnabled", true]
            && {!isNull _target}
        ) then {
            private _wantSse = (_target getVariable ["comspec_sse_enabled", false])
                || {!isNil {[_target] call comspec_sse_fnc_getData}};
            if (_wantSse) then {
                if (!isNil "comspec_sse_fnc_ensureGenerated") then {
                    [_target] call comspec_sse_fnc_ensureGenerated;
                };
                [_target] call comspec_sse_fnc_aceDogtagSync;
            };
        };

        private _old = missionNamespace getVariable ["comspec_sse_ace_oldCheckDogtag", {}];
        private _ret = _this call _old;

        if (
            missionNamespace getVariable ["comspec_sse_aceDogtagBridgeEnabled", true]
            && {!isNull _target}
        ) then {
            [{
                _this call comspec_sse_fnc_aceDogtagOnCheck;
            }, [_player, _target], 1.2] call CBA_fnc_waitAndExecute;
        };

        _ret
    },
    "comspec_sse_ace_oldCheckDogtag"
] call _tryWrap;

missionNamespace setVariable ["comspec_sse_aceDogtagHooksInstalled", true];
["Hooks ACE dogtags installés (skip compileFinal)."] call comspec_sse_fnc_log;
true
