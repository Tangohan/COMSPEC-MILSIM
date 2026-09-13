/*
    Interprète la réponse OperatorRegister / OperatorSync.
    Params: [_ok, _status, _detail]
    Retour: HashMap
*/
params [
    ["_ok", false, [true]],
    ["_status", "", [""]],
    ["_detail", "", [""]]
];

private _out = createHashMap;
_out set ["ok", _ok];
_out set ["status", _status];
_out set ["pending", false];
_out set ["operator_linked", false];
_out set ["profile_id", 0];
_out set ["discrepancies", 0];
_out set ["update_required", false];

if (!_ok) exitWith { _out };

private _d = toLower (trim _detail);
if (_d in ["pending", "not_implemented", "success"]) then {
    _out set ["pending", _d isNotEqualTo "success"];
    if (_d isEqualTo "success") then { _out set ["operator_linked", true]; };
} else {
    private _pairs = _detail splitString ";";
    {
        private _kv = _x splitString "=";
        if ((count _kv) < 2) then { continue };
        private _k = toLower (trim (_kv select 0));
        private _v = trim (_kv select 1);
        switch (_k) do {
            case "linked": { _out set ["operator_linked", _v in ["1", "true"]]; };
            case "profile_id": { _out set ["profile_id", round (parseNumber _v)]; };
            case "discrepancies": { _out set ["discrepancies", round (parseNumber _v)]; };
            case "update_required": { _out set ["update_required", _v in ["1", "true"]]; };
            case "pending": { _out set ["pending", _v in ["1", "true"]]; };
        };
    } forEach _pairs;
};

if (!(_out get "pending")) then {
    missionNamespace setVariable ["COMSPEC_OperatorLinked", _out get "operator_linked", false];
    missionNamespace setVariable ["COMSPEC_OperatorProfileId", _out get "profile_id", false];
    missionNamespace setVariable ["COMSPEC_OperatorDiscrepancies", _out get "discrepancies", false];
    missionNamespace setVariable ["COMSPEC_OperatorUpdateRequired", _out get "update_required", false];
};
missionNamespace setVariable ["COMSPEC_OperatorLastSyncAt", diag_tickTime, false];
missionNamespace setVariable ["COMSPEC_OperatorPendingBackend", _out get "pending", false];

if (_out get "update_required") then {
    private _lastAnn = missionNamespace getVariable ["COMSPEC_OperatorUpdateAnnouncedAt", -1e9];
    if ((diag_tickTime - _lastAnn) > 600) then {
        missionNamespace setVariable ["COMSPEC_OperatorUpdateAnnouncedAt", diag_tickTime, false];
        ["Une mise à jour COMSPEC Overwatch est recommandée pour rester compatible avec le poste.", "link", "warn"]
            call comspec_overwatch_connect_fnc_announce;
    };
};

_out
