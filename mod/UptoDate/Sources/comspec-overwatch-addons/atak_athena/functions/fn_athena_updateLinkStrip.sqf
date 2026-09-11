/*
    Bandeau liaison compact sous la barre d’état cTab :
    OK/NOK · dernière sync · fiabilité · débit/perte
    Indicatif · nom · versions Overwatch / Athena

    Lecture seule — ne jamais appeler refreshLinkState ici
    (récursion updateStatusBadges → crash).
*/
if (!hasInterface) exitWith { false };

if (missionNamespace getVariable ["COMSPEC_LinkStripUpdating", false]) exitWith { false };
missionNamespace setVariable ["COMSPEC_LinkStripUpdating", true, false];

private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _disp) then {
    _disp = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
};
if (isNull _disp) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkStripUpdating", false, false];
    false
};

private _IDC = 99871;
private _ctrl = _disp displayCtrl _IDC;

private _bat = _disp displayCtrl 2;
if (isNull _bat) then { _bat = _disp displayCtrl 1; };
if (isNull _bat) exitWith {
    missionNamespace setVariable ["COMSPEC_LinkStripUpdating", false, false];
    false
};

(ctrlPosition _bat) params ["_bx", "_by", "_bw", "_bh"];
private _hdr = _disp displayCtrl 1;
private _hx = _bx;
private _hy = _by;
private _hw = _bw * 12;
private _hh = _bh;
if (!isNull _hdr) then {
    (ctrlPosition _hdr) params ["_x0", "_y0", "_w0", "_h0"];
    _hx = _x0;
    _hy = _y0;
    _hw = _w0;
    _hh = _h0;
};

// Plus compact : ~0,86 × hauteur barre d’état
private _sy = _hy + _hh;
private _sh = (_hh * 0.86) max 0.018;
private _inset = _hw * 0.018;
_hx = _hx + _inset;
_hw = (_hw - (_inset * 2)) max (_bw * 4);

if (isNull _ctrl) then {
    _ctrl = _disp ctrlCreate ["RscStructuredText", _IDC];
    if (isNull _ctrl) exitWith {
        missionNamespace setVariable ["COMSPEC_LinkStripUpdating", false, false];
        false
    };
};

_ctrl ctrlSetPosition [_hx, _sy, _hw, _sh];
_ctrl ctrlSetBackgroundColor [0.015, 0.03, 0.04, 0.78];
_ctrl ctrlCommit 0;

private _fncShort = {
    params ["_txt", ["_max", 14]];
    if (!(_txt isEqualType "")) then { _txt = str _txt; };
    _txt = trim _txt;
    if (_txt isEqualTo "") exitWith { "" };
    if ((count _txt) > _max) then { (_txt select [0, _max - 1]) + "…" } else { _txt };
};

private _fncAgo = {
    params ["_tick"];
    if (!(_tick isEqualType 0) || {_tick < 0}) exitWith { "—" };
    private _sec = round (diag_tickTime - _tick);
    if (_sec < 0) then { _sec = 0; };
    if (_sec < 60) exitWith { format ["%1s", _sec] };
    if (_sec < 3600) exitWith { format ["%1m", round (_sec / 60)] };
    format ["%1h", round (_sec / 3600)]
};

// --- État liaison ---
private _state = missionNamespace getVariable ["COMSPEC_LinkState", "offline"];
if (!(_state isEqualType "")) then { _state = "offline"; };
private _ready = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
if (!(_ready isEqualType true)) then { _ready = false; };
private _lastHealth = missionNamespace getVariable ["COMSPEC_LastHealthOk", -1];
if (!(_lastHealth isEqualType 0)) then { _lastHealth = -1; };
private _healthFresh = (_lastHealth >= 0) && {(diag_tickTime - _lastHealth) < 90};

private _ok = (_state isEqualTo "linked") && {_ready || _healthFresh};
private _degraded = _state isEqualTo "degraded";

private _pkt = [] call comspec_overwatch_connect_fnc_getPacketLossStats;
private _loss = _pkt getOrDefault ["packet_loss_percent", 0];
if (!(_loss isEqualType 0)) then { _loss = 0; };
private _sentWin = _pkt getOrDefault ["packets_sent_window", 0];
private _measDur = _pkt getOrDefault ["measurement_duration", 0];
private _posInterval = missionNamespace getVariable ["comspec_overwatch_position_interval", 3];
if (!(_posInterval isEqualType 0)) then { _posInterval = 3; };
_posInterval = (_posInterval max 1) min 60;

private _bitrateKbps = 0;
if (_measDur > 0.5 && {_sentWin > 0}) then {
    _bitrateKbps = ((_sentWin * 0.45) / _measDur) * 8;
};
if (_bitrateKbps < 0.05 && {_ok || _degraded}) then {
    _bitrateKbps = (0.45 / _posInterval) * 8;
};

private _okTxt = if (_ok) then { "OK" } else { if (_degraded) then { "OK*" } else { "NOK" } };
private _okColor = if (_ok) then {
    if (_loss > 12) then { "#ffd27a" } else { "#7dffb0" }
} else {
    if (_degraded) then { "#ffd27a" } else { "#ff8a7a" }
};

private _rateTxt = if (!_ok && {!_degraded}) then {
    "—"
} else {
    if (_bitrateKbps < 0.1) then {
        "—"
    } else {
        if (_bitrateKbps < 10) then {
            format ["%1k", (_bitrateKbps toFixed 1)]
        } else {
            format ["%1k", round _bitrateKbps]
        }
    }
};

private _errTxt = format ["%1%%", round _loss];
private _errColor = switch (true) do {
    case (_loss >= 25): { "#ff8a7a" };
    case (_loss >= 10): { "#ffd27a" };
    default { "#a8b8c8" };
};

// Fiabilité : 100 − perte, pénalisée si hors liaison / santé ancienne / latence haute
private _reliab = ((100 - _loss) max 0) min 100;
if (!_ok && {!_degraded}) then { _reliab = 0; };
if (_degraded) then { _reliab = _reliab min 72; };
if (_lastHealth >= 0 && {(diag_tickTime - _lastHealth) > 45}) then {
    _reliab = _reliab min 55;
};
private _ms = missionNamespace getVariable ["COMSPEC_LastLatencyMs", -1];
if ((_ms isEqualType 0) && {_ms >= 0}) then {
    if (_ms >= 400) then { _reliab = _reliab min 50; };
    if (_ms >= 800) then { _reliab = _reliab min 30; };
};
_reliab = round _reliab;
private _reliabColor = switch (true) do {
    case (_reliab >= 85): { "#7dffb0" };
    case (_reliab >= 60): { "#ffd27a" };
    default { "#ff8a7a" };
};

private _lastSync = missionNamespace getVariable ["COMSPEC_LastPositionSync", -1];
private _syncTxt = [_lastSync] call _fncAgo;
if ((_ms isEqualType 0) && {_ms >= 0} && {_lastSync >= 0}) then {
    _syncTxt = format ["%1/%2ms", _syncTxt, round _ms];
};

// Identité
private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [true] call comspec_overwatch_connect_fnc_getCallsign;
};
if (!(_cs isEqualType "")) then { _cs = ""; };
private _name = missionNamespace getVariable ["comspec_profile_name", ""];
if (!(_name isEqualType "")) then { _name = ""; };
_name = trim _name;
if (_name isEqualTo "" && {!isNull player}) then { _name = name player; };
_cs = [_cs, 12] call _fncShort;
_name = [_name, 16] call _fncShort;
private _who = if (_cs isNotEqualTo "" && {_name isNotEqualTo ""} && {(toLower _cs) isNotEqualTo (toLower _name)}) then {
    format ["%1 · %2", _cs, _name]
} else {
    if (_cs isNotEqualTo "") then { _cs } else { if (_name isNotEqualTo "") then { _name } else { "—" } }
};

// Versions (cache liaison une fois)
private _owV = "";
if (!isNil "comspec_overwatch_connect_fnc_getModVersion") then {
    _owV = [] call comspec_overwatch_connect_fnc_getModVersion;
};
if (!(_owV isEqualType "") || {_owV isEqualTo ""}) then {
    _owV = getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr");
};
private _atakV = getText (configFile >> "CfgPatches" >> "comspec_overwatch_atak_athena" >> "versionStr");
if (_atakV isEqualTo "") then { _atakV = "—"; };

private _extV = missionNamespace getVariable ["COMSPEC_ExtensionVersionCached", ""];
if (!(_extV isEqualType "")) then { _extV = ""; };
if (_extV isEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_extResult"}) then {
    private _extRaw = ["COMSPECExtension" callExtension ["GetExtensionVersion", []]] call comspec_overwatch_connect_fnc_extResult;
    if ((_extRaw isEqualType "") && {_extRaw isNotEqualTo ""}) then {
        private _extParts = _extRaw splitString "|";
        if ((count _extParts) >= 2 && {(_extParts select 0) isEqualTo "OK"}) then {
            private _bits = (_extParts select 1) splitString " ";
            if ((count _bits) >= 2) then { _extV = _bits select 1; } else { _extV = _extParts select 1; };
        };
        _extV = trim _extV;
        if (_extV isNotEqualTo "") then {
            missionNamespace setVariable ["COMSPEC_ExtensionVersionCached", _extV, false];
        };
    };
};
if (_extV isEqualTo "") then { _extV = "—"; };

private _sep = "<t color='#4a5a68'> · </t>";
private _html = format [
    "<t align='center' size='0.45' shadow='1'><t color='%1'>%2</t>%3<t color='#9eb0c0'>sync %4</t>%3<t color='%5'>fiab. %6%%</t>%3<t color='#b8d4e8'>%7</t>%3<t color='%8'>perte %9</t><br/><t color='#d0dce8' size='0.90'>%10</t>%3<t color='#7a90a4'>OW %11</t>%3<t color='#7a90a4'>ATAK %12</t>%3<t color='#7a90a4'>liaison %13</t></t>",
    _okColor,
    _okTxt,
    _sep,
    _syncTxt,
    _reliabColor,
    _reliab,
    _rateTxt,
    _errColor,
    _errTxt,
    _who,
    _owV,
    _atakV,
    _extV
];
_ctrl ctrlSetStructuredText parseText _html;
_ctrl ctrlShow true;

private _sig = _disp displayCtrl 3;
if (!isNull _sig) then {
    if (_ok) then {
        _sig ctrlSetTextColor [0.55, 0.95, 0.72, 1];
    } else {
        if (_degraded) then {
            _sig ctrlSetTextColor [1, 0.82, 0.48, 1];
        } else {
            _sig ctrlSetTextColor [1, 0.55, 0.48, 1];
        };
    };
};

missionNamespace setVariable ["COMSPEC_LinkStripUpdating", false, false];
true
