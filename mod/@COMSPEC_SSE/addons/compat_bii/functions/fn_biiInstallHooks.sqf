/*
    Passerelle BII → SSE.

    IMPORTANT : les fonctions CfgFunctions / BII sont compileFinal.
    Toute tentative d'override (missionNamespace setVariable / assignation)
    loggue "Attempt to override final function" et peut provoquer un
    STATUS_STACK_OVERFLOW (RPT 2026-08-16 17:47). On ne wrap plus rien.

    - Fusion identité : injectée dans fn_ensureGenerated.sqf
    - Scans / preuves BII : pont par polling de l'état BII (sans toucher aux fnc)
*/
if (missionNamespace getVariable ["comspec_sse_biiHooksInstalled", false]) exitWith { true };
if !([] call comspec_sse_fnc_biiIsPresent) exitWith { false };

missionNamespace setVariable ["comspec_sse_biiHooksInstalled", true];

if (isNil "BII_fnc_identifi_getState") exitWith {
    ["BII getState absent - passerelle partielle (ensureGenerated seulement).", "WARNING"] call comspec_sse_fnc_log;
    true
};

if (missionNamespace getVariable ["comspec_sse_biiPollInstalled", false]) exitWith { true };
missionNamespace setVariable ["comspec_sse_biiPollInstalled", true];

missionNamespace setVariable ["comspec_sse_fnc_biiPollOnce", {
    if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith {};
    if (isNil "BII_fnc_identifi_getState") exitWith {};

    private _state = call BII_fnc_identifi_getState;
    if !(_state isEqualType createHashMap) exitWith {};

    private _sel = _state getOrDefault ["selectedId", ""];
    private _target = _state getOrDefault ["lastTarget", objNull];
    private _lastEv = _state getOrDefault ["lastEvidence", []];
    private _prevSel = missionNamespace getVariable ["comspec_sse_biiLastBridgedId", ""];
    private _prevEvHash = missionNamespace getVariable ["comspec_sse_biiLastEvHash", ""];

    if (
        _sel isNotEqualTo ""
        && {_sel isNotEqualTo _prevSel}
        && {!isNull _target}
        && {alive _target}
    ) then {
        missionNamespace setVariable ["comspec_sse_biiLastBridgedId", _sel];
        private _record = [];
        if (!isNil "BII_fnc_identifi_findRecord") then {
            private _local = profileNamespace getVariable ["BII_Identifi_localDB", []];
            private _global = missionNamespace getVariable ["BII_Identifi_globalDB", []];
            ([_sel, _local] call BII_fnc_identifi_findRecord) params ["", "_localRecord"];
            if (_localRecord isEqualType [] && {_localRecord isNotEqualTo []}) then {
                _record = _localRecord;
            } else {
                ([_sel, _global] call BII_fnc_identifi_findRecord) params ["", "_globalRecord"];
                if (_globalRecord isEqualType [] && {_globalRecord isNotEqualTo []}) then {
                    _record = _globalRecord;
                };
            };
        };
        if (_record isNotEqualTo []) then {
            [_target, _record] call comspec_sse_fnc_biiImportScan;
        } else {
            [_target] call comspec_sse_fnc_biiImportEntityVars;
        };
        ["BII→SSE: scan/selection importee."] call comspec_sse_fnc_log;
    };

    if (_lastEv isEqualType [] && {_lastEv isNotEqualTo []}) then {
        private _evHash = str (_lastEv select [0, (count _lastEv) min 4]);
        if (_evHash isNotEqualTo _prevEvHash && {!isNull _target}) then {
            missionNamespace setVariable ["comspec_sse_biiLastEvHash", _evHash];
            [_target] call comspec_sse_fnc_biiImportEntityVars;
            [_target, _lastEv] call comspec_sse_fnc_biiImportEvidenceEntry;
            ["BII→SSE: preuve importee."] call comspec_sse_fnc_log;
        };
    };
}];

missionNamespace setVariable ["comspec_sse_fnc_biiPollTick", {
    if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith {};
    call (missionNamespace getVariable ["comspec_sse_fnc_biiPollOnce", {}]);
    [
        { [] call (missionNamespace getVariable ["comspec_sse_fnc_biiPollTick", {}]); },
        [],
        2
    ] call CBA_fnc_waitAndExecute;
}];

[] call (missionNamespace getVariable ["comspec_sse_fnc_biiPollTick", {}]);
["Passerelle BII: poll actif (pas d'override compileFinal)."] call comspec_sse_fnc_log;
true
