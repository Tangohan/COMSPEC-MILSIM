private _ref = missionNamespace getVariable ["comspec_sse_caseReference", ""];
if (_ref isEqualTo "") then {
    _ref = missionNamespace getVariable ["comspec_sse_caseRef", ""];
};
if (_ref isEqualTo "") then {
    _ref = missionNamespace getVariable ["COMSPEC_SSE_ActiveCase", ""];
    if (!(_ref isEqualType "")) then { _ref = ""; };
};
_ref
