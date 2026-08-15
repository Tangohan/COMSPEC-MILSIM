params [["_linkId", ""]];

if (_linkId == "") exitWith {false};
private _links = missionNamespace getVariable ["Iceman_ROIP_activeLinks", []];

(_links findIf {
    private _link = _x # 0;
    _link isEqualType [] && {(count _link) >= 2} && {(_link # 1) == _linkId}
}) >= 0
