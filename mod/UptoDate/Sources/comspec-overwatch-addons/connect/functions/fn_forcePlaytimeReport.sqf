/*
    Envoie immédiatement le temps de mission déjà cumulé vers le portail.
    Utilisé par Resynch, Zeus (groupe BFT) et le bouton Athena.
*/
params [["_silent", false, [true]]];

if (!hasInterface) exitWith { false };

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_playtime_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {
    if (!_silent) then {
        ["Liaison Athena coupée — impossible de remonter le temps de mission.", "link", "warn", true]
            call comspec_overwatch_connect_fnc_announce;
    };
    false
};

private _cooldown = 8;
private _now = diag_tickTime;
private _last = missionNamespace getVariable ["COMSPEC_ForcePlaytimeAt", -1e9];
private _remain = ceil (_cooldown - (_now - _last));
if (_remain > 0) exitWith {
    if (!_silent) then {
        [format ["Patientez %1 s avant une nouvelle remontée du temps.", _remain], "link", "info", true]
            call comspec_overwatch_connect_fnc_announce;
    };
    false
};

private _classify = {
    if (is3DEN || {is3DENPreview}) exitWith {"editor"};
    if (!isNull curatorCamera || {!isNull findDisplay 312}) exitWith {"zeus"};
    "server"
};

private _ctx = missionNamespace getVariable ["COMSPEC_PlaytimeCtx", ""];
if (!(_ctx isEqualType "")) then { _ctx = ""; };
_ctx = trim _ctx;
if (_ctx isEqualTo "") then { _ctx = [] call _classify; };

private _accum = missionNamespace getVariable ["COMSPEC_PlaytimeAccum", 0];
if (!(_accum isEqualType 0)) then { _accum = 0; };

private _lastTick = missionNamespace getVariable ["COMSPEC_PlaytimeLastTick", _now];
if (!(_lastTick isEqualType 0)) then { _lastTick = _now; };
private _dt = (_now - _lastTick) max 0;
if (_dt > 120) then { _dt = 120; };
private _paused = !(_ctx isEqualTo "editor") && {!isNull findDisplay 49};
if (!_paused && {_ctx isNotEqualTo ""}) then {
    _accum = _accum + _dt;
};

private _secs = floor _accum;
if (_secs < 1) then {
    _secs = 1;
};

private _uid = if (isNull player) then { "" } else { getPlayerUID player };
if (_uid isEqualTo "__SERVER__") exitWith { false };

private _callsign = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _callsign = [true] call comspec_overwatch_connect_fnc_getCallsign;
};
if (!(_callsign isEqualType "")) then { _callsign = ""; };
if (_callsign isEqualTo "") then {
    _callsign = if (isNull player) then { "" } else { name player };
};
private _tenantId = missionNamespace getVariable ["comspec_overwatch_tenant_id", ""];
if (!(_tenantId isEqualType "")) then { _tenantId = ""; };

"COMSPECExtension" callExtension ["ReportPlaytime", [_uid, str _secs, _callsign, _tenantId, _ctx]];

missionNamespace setVariable ["COMSPEC_ForcePlaytimeAt", _now, false];
missionNamespace setVariable ["COMSPEC_LastPlaytimeSent", _now, false];
missionNamespace setVariable ["COMSPEC_PlaytimeAccum", 0, false];
missionNamespace setVariable ["COMSPEC_PlaytimeLastTick", _now, false];
missionNamespace setVariable ["COMSPEC_PlaytimeCtx", _ctx, false];
missionNamespace setVariable ["COMSPEC_PlaytimeForceFlush", false, false];

if (!_silent) then {
    [format ["Temps de mission remonté (%1 s — %2).", _secs, _ctx], "link", "info", true]
        call comspec_overwatch_connect_fnc_announce;
};

true
