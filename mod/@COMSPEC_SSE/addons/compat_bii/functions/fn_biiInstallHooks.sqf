/*
    Installe les wraps BII → SSE (sans modifier le PBO Workshop).
*/
if (missionNamespace getVariable ["comspec_sse_biiHooksInstalled", false]) exitWith { true };
if !([] call comspec_sse_fnc_biiIsPresent) exitWith { false };

// Scan biométrique BII → import SSE
if (!isNil "BII_fnc_identifi_processScan") then {
    private _oldScan = BII_fnc_identifi_processScan;
    missionNamespace setVariable ["comspec_sse_bii_oldProcessScan", _oldScan];
    BII_fnc_identifi_processScan = {
        private _old = missionNamespace getVariable ["comspec_sse_bii_oldProcessScan", {}];
        private _record = _this call _old;
        if (
            (_record isEqualType [])
            && {_record isNotEqualTo []}
            && {missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]}
        ) then {
            private _target = _this param [0, objNull];
            if (!isNull _target) then {
                [_target, _record] call comspec_sse_fnc_biiImportScan;
            };
        };
        _record
    };
};

// Collecte preuve BII → chaîne de custody SSE (via identité sélectionnée)
if (!isNil "BII_fnc_identifi_collectEvidence") then {
    private _oldEv = BII_fnc_identifi_collectEvidence;
    missionNamespace setVariable ["comspec_sse_bii_oldCollectEvidence", _oldEv];
    BII_fnc_identifi_collectEvidence = {
        private _old = missionNamespace getVariable ["comspec_sse_bii_oldCollectEvidence", {}];
        private _ok = _this call _old;
        if (
            _ok
            && {missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]}
            && {!isNil "BII_fnc_identifi_getState"}
        ) then {
            private _state = call BII_fnc_identifi_getState;
            private _sel = if (_state isEqualType createHashMap) then {
                _state getOrDefault ["selectedId", ""]
            } else { "" };
            private _lastTarget = if (_state isEqualType createHashMap) then {
                _state getOrDefault ["lastTarget", objNull]
            } else { objNull };
            private _lastEv = if (_state isEqualType createHashMap) then {
                _state getOrDefault ["lastEvidence", []]
            } else { [] };

            if (!isNull _lastTarget && {_lastEv isEqualType []} && {_lastEv isNotEqualTo []}) then {
                [_lastTarget] call comspec_sse_fnc_biiImportEntityVars;
                [_lastTarget, _lastEv] call comspec_sse_fnc_biiImportEvidenceEntry;
            };

            private _obj = _this param [0, objNull];
            if (!isNull _obj) then {
                [_obj] call comspec_sse_fnc_biiImportObject;
            };
            // silence unused
            _sel
        };
        _ok
    };
};

// Module identity BII → import immédiat SSE
if (!isNil "BII_fnc_identifi_moduleIdentity") then {
    private _oldMod = BII_fnc_identifi_moduleIdentity;
    missionNamespace setVariable ["comspec_sse_bii_oldModuleIdentity", _oldMod];
    BII_fnc_identifi_moduleIdentity = {
        private _old = missionNamespace getVariable ["comspec_sse_bii_oldModuleIdentity", {}];
        private _ok = _this call _old;
        if (_ok && {missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]}) then {
            private _logic = _this param [0, objNull];
            private _units = _this param [1, []];
            private _targets = (_units + synchronizedObjects _logic) select {_x isKindOf "CAManBase"};
            { [_x] call comspec_sse_fnc_biiImportEntityVars; } forEach _targets;
        };
        _ok
    };
};

// Module evidence BII → makeSearchable SSE
if (!isNil "BII_fnc_identifi_moduleEvidence") then {
    private _oldModEv = BII_fnc_identifi_moduleEvidence;
    missionNamespace setVariable ["comspec_sse_bii_oldModuleEvidence", _oldModEv];
    BII_fnc_identifi_moduleEvidence = {
        private _old = missionNamespace getVariable ["comspec_sse_bii_oldModuleEvidence", {}];
        private _ok = _this call _old;
        if (_ok && {missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]}) then {
            private _logic = _this param [0, objNull];
            private _units = _this param [1, []];
            private _objects = (_units + synchronizedObjects _logic) select {!(_x isKindOf "CAManBase")};
            { [_x] call comspec_sse_fnc_biiImportObject; } forEach _objects;
        };
        _ok
    };
};

// Génération SSE → fusion BII après gen, puis export variables BII
if (!isNil "comspec_sse_fnc_ensureGenerated") then {
    private _oldEns = comspec_sse_fnc_ensureGenerated;
    missionNamespace setVariable ["comspec_sse_bii_oldEnsureGenerated", _oldEns];
    comspec_sse_fnc_ensureGenerated = {
        private _old = missionNamespace getVariable ["comspec_sse_bii_oldEnsureGenerated", {}];
        private _entity = _this param [0, objNull];
        private _r = _this call _old;
        if (!isNull _entity && {missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]}) then {
            // BII authored gagne sur le narratif généré
            [_entity] call comspec_sse_fnc_biiImportEntityVars;
            if (missionNamespace getVariable ["comspec_sse_biiExportToBii", true]) then {
                [_entity] call comspec_sse_fnc_biiExportEntityVars;
            };
        };
        _r
    };
};

missionNamespace setVariable ["comspec_sse_biiHooksInstalled", true];
true
