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
    private _sig = [];
    private _alive = [];
    {
        if (!(_x isEqualType "")) then { continue };
        private _obj = objectFromNetId _x;
        if (isNull _obj || {!alive _obj}) then { continue };
        _alive pushBack _x;
        private _pos = getPosWorld _obj;
        if ((abs (_pos select 0) < 1) && { abs (_pos select 1) < 1 }) then { continue };
        private _asl = (getPosASL _obj) select 2;
        _snaps pushBack [_x, _pos select 0, _pos select 1, getDir _obj, _asl];
        _sig pushBack [_x, round (_pos select 0), round (_pos select 1), round (getDir _obj)];
    } forEach _ids;
    // Purge des netIds d'IA supprimées / mortes : la liste grossissait sans fin et était rescannée toutes les 4 s.
    if ((count _alive) != (count _ids)) then {
        missionNamespace setVariable ["COMSPEC_AllyTrackNetIds", _alive, true];
    };
    // Diffusion seulement si une position (au mètre) / un cap a changé : sinon le tableau complet
    // partait toutes les 4 s vers tous les clients, même avec des IA immobiles.
    if (_sig isEqualTo (missionNamespace getVariable ["COMSPEC_AllyTrackSnapSig", []])) exitWith {};
    missionNamespace setVariable ["COMSPEC_AllyTrackSnapSig", _sig, false];
    missionNamespace setVariable ["COMSPEC_AllyTrackSnapshots", _snaps, true];
}, 4, []] call CBA_fnc_addPerFrameHandler;
