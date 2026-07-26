if (!hasInterface) exitWith {};

private _accum = 0;
private _lastTick = diag_tickTime;

while { true } do {
    sleep 5;
    private _enabled = missionNamespace getVariable ["comspec_overwatch_enabled", true];
    private _ptOn = missionNamespace getVariable ["comspec_overwatch_playtime_enabled", true];
    private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];

    // Pause menu (display 49) — ne pas utiliser missionPaused (variable inexistante).
    private _paused = !isNull findDisplay 49;
    if (_enabled && _ptOn && {!(_url isEqualTo "")} && {!_paused}) then {
        private _now = diag_tickTime;
        private _dt = _now - _lastTick;
        _lastTick = _now;
        if (_dt > 120) then { _dt = 120 };

        _accum = _accum + _dt;

        private _reportEveryMin = missionNamespace getVariable ["comspec_overwatch_playtime_report_interval", 5];
        private _reportEvery = ((_reportEveryMin max 2) * 60);
        if (_accum >= _reportEvery) then {
            private _uid = getPlayerUID player;
            if (_uid isEqualTo "" || {_uid isEqualTo "__SERVER__"} || {_uid isEqualTo "_SP_PLAYER_"}) then {
                _accum = 0;
            } else {
                private _secs = floor _accum;
                if (_secs >= 1) then {
                    _accum = _accum - _secs;
                    private _callsign = name player;
                    private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
                    "COMSPECExtension" callExtension ["ReportPlaytime", [_uid, str _secs, _callsign, _tenantId]];
                };
            };
        };
    };
};
