/*
    Serveur : positions des IA suivies, même hors bulle d’un client.
    Le relais Athena lit COMSPEC_AllyTrackSnapshots.
*/
if (!isServer) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ProxyTrackServerHooked", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_ProxyTrackServerHooked", true, false];

[{
    private _ids = missionNamespace getVariable ["COMSPEC_AllyTrackNetIds", []];
    if (!(_ids isEqualType [])) then { _ids = []; };
    private _snaps = [];
    {
        if (!(_x isEqualType "")) then { continue };
        private _obj = objectFromNetId _x;
        if (isNull _obj || {!alive _obj}) then { continue };
        private _pos = getPosWorld _obj;
        if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) then { continue };
        private _asl = (getPosASL _obj) select 2;
        _snaps pushBack [_x, _pos select 0, _pos select 1, getDir _obj, _asl];
    } forEach _ids;
    missionNamespace setVariable ["COMSPEC_AllyTrackSnapshots", _snaps, true];
}, 4, []] call CBA_fnc_addPerFrameHandler;
