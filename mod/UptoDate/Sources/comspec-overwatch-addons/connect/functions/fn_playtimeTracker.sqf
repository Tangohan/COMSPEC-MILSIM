if (!hasInterface) exitWith {};

/*
    Cumul mission → portail (serveur / Zeus / éditeur).
    N’envoie que lorsque la liaison Athena est prête (comme le téléphone ATAK).
    Resynch / Zeus / bouton Athena peuvent forcer une remontée immédiate.
*/
private _accum = missionNamespace getVariable ["COMSPEC_PlaytimeAccum", 0];
if (!(_accum isEqualType 0)) then { _accum = 0; };
private _lastTick = diag_tickTime;
private _ctx = missionNamespace getVariable ["COMSPEC_PlaytimeCtx", ""];
if (!(_ctx isEqualType "")) then { _ctx = ""; };

private _classify = {
    if (is3DEN || {is3DENPreview}) exitWith {"editor"};
    if (!isNull curatorCamera || {!isNull findDisplay 312}) exitWith {"zeus"};
    // Solo (hors éditeur) et multijoueur : même seau « serveur ».
    "server"
};

private _flush = {
    params ["_secs", "_flushCtx"];
    if (_secs < 1) exitWith {};
    if (_flushCtx isEqualTo "") exitWith {};
    if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
    private _uid = if (isNull player) then {""} else {getPlayerUID player};
    // __SERVER__ uniquement : en solo (_SP_PLAYER_) la liaison reprend le Steam de session.
    if (_uid isEqualTo "__SERVER__") exitWith {};
    private _callsign = "";
    if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
        _callsign = [true] call comspec_overwatch_connect_fnc_getCallsign;
    };
    if (!(_callsign isEqualType "")) then { _callsign = ""; };
    if (_callsign isEqualTo "") then {
        _callsign = if (isNull player) then {""} else {name player};
    };
    private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
    if (!(_tenantId isEqualType "")) then { _tenantId = ""; };
    "COMSPECExtension" callExtension ["ReportPlaytime", [_uid, str _secs, _callsign, _tenantId, _flushCtx]];
    missionNamespace setVariable ["COMSPEC_LastPlaytimeSent", diag_tickTime, false];
};

missionNamespace setVariable ["COMSPEC_PlaytimeLastTick", _lastTick, false];
missionNamespace setVariable ["COMSPEC_PlaytimeAccum", _accum, false];
missionNamespace setVariable ["COMSPEC_PlaytimeCtx", _ctx, false];

while { true } do {
    sleep 5;
    private _enabled = missionNamespace getVariable ["comspec_overwatch_enabled", true];
    private _ptOn = missionNamespace getVariable ["comspec_overwatch_playtime_enabled", true];
    private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
    if (!(_ready isEqualType true)) then { _ready = false; };

    private _now = diag_tickTime;
    private _dt = _now - _lastTick;
    _lastTick = _now;
    missionNamespace setVariable ["COMSPEC_PlaytimeLastTick", _now, false];
    if (_dt > 120) then { _dt = 120 };

    // Remontée forcée (Resynch / Zeus / Athena) : laisser fn_forcePlaytimeReport gérer.
    if (missionNamespace getVariable ["COMSPEC_PlaytimeForceFlush", false]) then {
        missionNamespace setVariable ["COMSPEC_PlaytimeForceFlush", false, false];
        if (!isNil "comspec_overwatch_connect_fnc_forcePlaytimeReport") then {
            [] call comspec_overwatch_connect_fnc_forcePlaytimeReport;
            _accum = missionNamespace getVariable ["COMSPEC_PlaytimeAccum", 0];
            if (!(_accum isEqualType 0)) then { _accum = 0; };
            _ctx = missionNamespace getVariable ["COMSPEC_PlaytimeCtx", _ctx];
            if (!(_ctx isEqualType "")) then { _ctx = ""; };
            continue;
        };
    };

    private _newCtx = [] call _classify;
    private _inEditor = _newCtx isEqualTo "editor";
    private _paused = !_inEditor && {!isNull findDisplay 49};

    if (_ctx isNotEqualTo "" && {_newCtx isNotEqualTo _ctx}) then {
        private _left = floor _accum;
        if (_left >= 1 && {_enabled} && {_ptOn} && {_ready}) then {
            [_left, _ctx] call _flush;
        };
        _accum = 0;
    };

    if (_enabled && _ptOn && {_ready} && {!_paused} && {_newCtx isNotEqualTo ""} && {_inEditor || {!isNull player}}) then {
        _ctx = _newCtx;
        _accum = _accum + _dt;

        private _reportEveryMin = missionNamespace getVariable ["comspec_overwatch_playtime_report_interval", 5];
        private _reportEvery = ((_reportEveryMin max 2) * 60);
        if (_accum >= _reportEvery) then {
            private _secs = floor _accum;
            if (_secs >= 1) then {
                _accum = _accum - _secs;
                [_secs, _ctx] call _flush;
            };
        };
    };

    missionNamespace setVariable ["COMSPEC_PlaytimeAccum", _accum, false];
    missionNamespace setVariable ["COMSPEC_PlaytimeCtx", _ctx, false];
};
