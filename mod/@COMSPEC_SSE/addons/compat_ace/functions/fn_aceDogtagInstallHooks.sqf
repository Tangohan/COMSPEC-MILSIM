/*
    Installe les wraps ACE dogtags → SSE (sans modifier le PBO ACE).
*/
if (missionNamespace getVariable ["comspec_sse_aceDogtagHooksInstalled", false]) exitWith { true };
if !([] call comspec_sse_fnc_aceDogtagIsPresent) exitWith { false };

// Lecture plaque : forcer les données ACE depuis l’identité SSE
if (!isNil "ace_dogtags_fnc_getDogtagData") then {
    private _oldGet = ace_dogtags_fnc_getDogtagData;
    missionNamespace setVariable ["comspec_sse_ace_oldGetDogtagData", _oldGet];
    ace_dogtags_fnc_getDogtagData = {
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
    };
};

// Vérification plaque : ACE affiche + dossier SSE alimenté
if (!isNil "ace_dogtags_fnc_checkDogtag") then {
    private _oldCheck = ace_dogtags_fnc_checkDogtag;
    missionNamespace setVariable ["comspec_sse_ace_oldCheckDogtag", _oldCheck];
    ace_dogtags_fnc_checkDogtag = {
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
    };
};

missionNamespace setVariable ["comspec_sse_aceDogtagHooksInstalled", true];
["Hooks ACE dogtags installés."] call comspec_sse_fnc_log;
true
