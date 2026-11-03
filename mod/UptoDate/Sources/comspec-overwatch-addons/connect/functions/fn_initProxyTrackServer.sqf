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
    private _sigCoarse = [];
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
        _sigCoarse pushBack [_x, round ((_pos select 0) / 10), round ((_pos select 1) / 10), round ((getDir _obj) / 15)];
    } forEach _ids;
    // Purge des netIds d'IA supprimées / mortes : la liste grossissait sans fin et était rescannée toutes les 4 s.
    if ((count _alive) != (count _ids)) then {
        missionNamespace setVariable ["COMSPEC_AllyTrackNetIds", _alive, true];
    };
    // Diffusion seulement si une position (au mètre) / un cap a changé : sinon le tableau complet
    // partait toutes les 4 s vers tous les clients, même avec des IA immobiles.
    // Seul le relais (client élu) lit ce tableau, mais il part public vers tous : seuil ~10 m / 15° pour
    // diffuser vite, et petits écarts (au mètre) seulement toutes les 20 s, pour ne pas inonder le réseau.
    // Élection avant les sorties : un nouveau relais (mort, déconnexion, UID plus petit) reçoit le tableau même sans mouvement.
    private _linked = allPlayers select { alive _x && {_x getVariable ["COMSPEC_AtakReporterLinked", false]} && {(getPlayerUID _x) isNotEqualTo ""} };
    // Plus petit UID : sort natif (BIS_fnc_sortBy toutes les 4 s avant).
    private _uids = _linked apply { getPlayerUID _x };
    private _min = +_uids; _min sort true;
    private _b = if (_linked isEqualTo []) then {objNull} else {_linked select (_uids find (_min select 0))};
    private _to = if (isNull _b) then {""} else {getPlayerUID _b};
    private _sameTo = _to isEqualTo (missionNamespace getVariable ["COMSPEC_AllyTrackSnapTo", ""]);
    if (_sameTo && {_sig isEqualTo (missionNamespace getVariable ["COMSPEC_AllyTrackSnapSig", []])}) exitWith {};
    private _coarseSame = _sigCoarse isEqualTo (missionNamespace getVariable ["COMSPEC_AllyTrackSnapSigCoarse", []]);
    if (_sameTo && _coarseSame && {(time - (missionNamespace getVariable ["COMSPEC_AllyTrackSnapAt", -1e9])) < 20}) exitWith {};
    missionNamespace setVariable ["COMSPEC_AllyTrackSnapSig", _sig, false];
    missionNamespace setVariable ["COMSPEC_AllyTrackSnapSigCoarse", _sigCoarse, false];
    missionNamespace setVariable ["COMSPEC_AllyTrackSnapAt", time, false];
    // Seul le relais lit ce tableau : envoyé à lui seul (même élection que _isBridge, fn_initGpsBeacons), plus en public.
    missionNamespace setVariable ["COMSPEC_AllyTrackSnapshots", _snaps, false];
    missionNamespace setVariable ["COMSPEC_AllyTrackSnapTo", _to, false];
    if (!isNull _b && {!local _b}) then { (owner _b) publicVariableClient "COMSPEC_AllyTrackSnapshots"; };
}, 4, []] call CBA_fnc_addPerFrameHandler;
