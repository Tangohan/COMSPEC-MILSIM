if (!hasInterface) exitWith {false};

// Overwatch Connect envoie déjà le temps de mission : ne pas doubler le cumul.
if (isClass (configFile >> "CfgPatches" >> "comspec_overwatch_connect")) exitWith {false};

if (missionNamespace getVariable ["COMSPEC_ATAK_PlaytimeStarted", false]) exitWith {true};
missionNamespace setVariable ["COMSPEC_ATAK_PlaytimeStarted", true, false];

[] spawn {
    private _accum = 0;
    private _lastTick = diag_tickTime;
    private _ctx = "";

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
        if (!(missionNamespace getVariable ["COMSPEC_ATAK_AthenaReady", false])) exitWith {};
        private _uid = if (isNull player) then {""} else {getPlayerUID player};
        // __SERVER__ uniquement : en solo la liaison reprend le Steam de session.
        if (_uid isEqualTo "__SERVER__") exitWith {};
        private _callsign = ["callsign", ""] call COMSPEC_fnc_getState;
        if (_callsign isEqualTo "") then {
            _callsign = if (isNull player) then {""} else {name player};
        };
        private _tenantId = missionNamespace getVariable ["COMSPEC_ATAK_tenant_id", ""];
        ["ReportPlaytime", [_uid, str _secs, _callsign, _tenantId, _flushCtx]] call COMSPEC_fnc_extensionCall;
        missionNamespace setVariable ["COMSPEC_LastPlaytimeSent", diag_tickTime, false];
    };

    while {true} do {
        uiSleep 5;
        private _now = diag_tickTime;
        private _dt = _now - _lastTick;
        _lastTick = _now;
        if (_dt > 120) then {_dt = 120;};

        private _enabled = missionNamespace getVariable ["COMSPEC_ATAK_playtime_enabled", true];
        private _newCtx = [] call _classify;
        private _inEditor = _newCtx isEqualTo "editor";
        private _paused = !_inEditor && {!isNull findDisplay 49};
        private _mode = ["networkMode", "NONE"] call COMSPEC_fnc_getState;
        private _ready = missionNamespace getVariable ["COMSPEC_ATAK_AthenaReady", false];
        // Éditeur : même suivi si le terminal est déjà relié, même hors mission.
        private _linked = _ready && {(_mode isEqualTo "ATHENA") || {_inEditor}};

        if (_ctx isNotEqualTo "" && {_newCtx isNotEqualTo _ctx}) then {
            private _left = floor _accum;
            if (_left >= 1 && {_enabled} && {_ready}) then {
                [_left, _ctx] call _flush;
            };
            _accum = 0;
        };

        if (
            _enabled
            && {!_paused}
            && {_linked}
            && {_newCtx isNotEqualTo ""}
            && {_inEditor || {!isNull player}}
        ) then {
            _ctx = _newCtx;
            _accum = _accum + _dt;
            if (_accum >= 300) then {
                private _secs = floor _accum;
                if (_secs >= 1) then {
                    _accum = _accum - _secs;
                    [_secs, _ctx] call _flush;
                };
            };
        };
    };
};

true
