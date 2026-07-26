/*
    Reçoit l'état Google Briefing synchronisé (local + JIP).
    Params: [_command, _url, _index, _revision]
*/
params [
    ["_command", "", [""]],
    ["_url", "", [""]],
    ["_index", 0, [0]],
    ["_revision", 0, [0]]
];

if (!hasInterface) exitWith {};

private _lastRev = missionNamespace getVariable ["COMSPEC_GoogleBriefingAppliedRev", -1];
if (_revision > 0 && {_revision <= _lastRev}) exitWith {};
if (_revision > 0) then {
    missionNamespace setVariable ["COMSPEC_GoogleBriefingAppliedRev", _revision];
};

private _fnc_skipIfOwnPending = {
    params ["_u", "_i"];
    private _pendingUrl = missionNamespace getVariable ["COMSPEC_GoogleBriefingUrl", ""];
    private _pendingReq = missionNamespace getVariable ["COMSPEC_GoogleBriefingRequestId", ""];
    private _pendingIdx = missionNamespace getVariable ["COMSPEC_GoogleBriefingPendingIndex", -999];
    (_u isEqualTo _pendingUrl) && {_i isEqualTo _pendingIdx} && {_pendingReq isNotEqualTo ""}
};

switch (_command) do {
    case "end": {
        missionNamespace setVariable ["COMSPEC_GoogleBriefingActive", false];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingUrl", ""];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingPresentationId", ""];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingTotal", 0];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingIndex", 0];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingPath", ""];
        missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", ""];
    };
    case "step": {
        if (_url isEqualTo "") exitWith {};
        if ([_url, _index] call _fnc_skipIfOwnPending) exitWith {};

        private _presentationId = missionNamespace getVariable ["COMSPEC_GoogleBriefingPresentationId", ""];
        private _knownUrl = missionNamespace getVariable ["COMSPEC_GoogleBriefingUrl", ""];
        if (_presentationId isNotEqualTo "" && {_knownUrl isEqualTo _url}) then {
            missionNamespace setVariable ["COMSPEC_GoogleBriefingPendingIndex", floor _index];
            "COMSPECExtension" callExtension ["CancelGoogleDeck", []];
            private _requestId = format ["gslide_%1_%2", clientOwner, floor (diag_tickTime * 1000)];
            missionNamespace setVariable ["COMSPEC_GoogleBriefingRequestId", _requestId];
            private _raw = ["COMSPECExtension" callExtension [
                "LoadGoogleSlide",
                [_presentationId, str (floor _index), _requestId]
            ]] call comspec_overwatch_connect_fnc_extResult;
            private _parsed = parseSimpleArray _raw;
            if ((_parsed param [0, ""]) isNotEqualTo "accepted") then {
                [_url, _index, false] call comspec_overwatch_connect_fnc_loadGoogleBriefing;
            };
        } else {
            [_url, _index, false] call comspec_overwatch_connect_fnc_loadGoogleBriefing;
        };
    };
    case "show": {
        if (_url isEqualTo "") exitWith {};
        if ([_url, _index] call _fnc_skipIfOwnPending) exitWith {};
        [_url, _index, false] call comspec_overwatch_connect_fnc_loadGoogleBriefing;
    };
    default {};
};
