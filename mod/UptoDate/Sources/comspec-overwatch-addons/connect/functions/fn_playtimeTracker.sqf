if (!hasInterface) exitWith {};

private _accum = 0;
private _lastTick = diag_tickTime;
private _ctx = "";

private _classify = {
    if (is3DEN || {is3DENPreview}) exitWith {"editor"};
    if (!isMultiplayer) exitWith {""};
    if (!isNull curatorCamera || {!isNull findDisplay 312}) exitWith {"zeus"};
    "server"
};

private _flush = {
    params ["_secs", "_flushCtx"];
    if (_secs < 1) exitWith {};
    if (_flushCtx isEqualTo "") exitWith {};
    private _uid = if (isNull player) then {""} else {getPlayerUID player};
    if (_uid isEqualTo "__SERVER__" || {_uid isEqualTo "_SP_PLAYER_"}) exitWith {};
    private _callsign = if (isNull player) then {""} else {name player};
    private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
    "COMSPECExtension" callExtension ["ReportPlaytime", [_uid, str _secs, _callsign, _tenantId, _flushCtx]];
};

while { true } do {
    sleep 5;
    private _enabled = missionNamespace getVariable ["comspec_overwatch_enabled", true];
    private _ptOn = missionNamespace getVariable ["comspec_overwatch_playtime_enabled", true];
    private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];

    private _now = diag_tickTime;
    private _dt = _now - _lastTick;
    _lastTick = _now;
    if (_dt > 120) then { _dt = 120 };

    private _newCtx = [] call _classify;
    private _inEditor = _newCtx isEqualTo "editor";
    private _paused = !_inEditor && {!isNull findDisplay 49};

    if (_ctx isNotEqualTo "" && {_newCtx isNotEqualTo _ctx}) then {
        private _left = floor _accum;
        if (_left >= 1 && {_enabled} && {_ptOn} && {!(_url isEqualTo "")}) then {
            [_left, _ctx] call _flush;
        };
        _accum = 0;
    };

    if (_enabled && _ptOn && {!(_url isEqualTo "")} && {!_paused} && {_newCtx isNotEqualTo ""} && {_inEditor || {!isNull player}}) then {
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
};
