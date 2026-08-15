private _state = call Iceman_fnc_roip_getState;
private _radios = call Iceman_fnc_roip_getRadios;
_state set ["lastRadios", _radios];

private _mpu5 = "";
if !(isNil "acre_api_fnc_getRadioByType") then {
    private _candidate = ["ACRE_MPU5"] call acre_api_fnc_getRadioByType;
    if (!(isNil "_candidate") && {_candidate isEqualType ""}) then {_mpu5 = _candidate};
};
if (_mpu5 == "") exitWith {
    ["ROIP", "MPU-5 not detected.", 2] call Iceman_fnc_roip_notify;
    false
};
if (_radios isEqualTo []) exitWith {
    ["ROIP", "No powered PRC-152 or PRC-117F is physically available.", 3] call Iceman_fnc_roip_notify;
    false
};

private _selectedId = _state getOrDefault ["selectedRadioId", ""];
private _radioIndex = _radios findIf {(_x # 0) == _selectedId};
if (_radioIndex < 0) then {
    _radioIndex = ((_state getOrDefault ["radioSelection", 0]) max 0) min ((count _radios) - 1);
};
private _record = _radios # _radioIndex;
if !(_record # 4) exitWith {
    ["ROIP", "The selected legacy radio is powered off.", 2] call Iceman_fnc_roip_notify;
    false
};

private _tg = (_state getOrDefault ["tgSelection", 1]) max 1 min 16;
private _wrState = if !(isNil "Iceman_fnc_wr_getState") then {call Iceman_fnc_wr_getState} else {createHashMap};
private _bank = _wrState getOrDefault ["frequency", player getVariable ["Iceman_WR_frequency", "32.0"]];
private _bankNumber = if (_bank isEqualType 0) then {_bank} else {parseNumber _bank};

private _conflict = objNull;
{
    if !(_x isEqualTo player) then {
        private _link = _x getVariable ["Iceman_ROIP_link", []];
        if (_x getVariable ["Iceman_ROIP_active", false] && {_link isEqualType []} && {(count _link) >= 13}) then {
            private _otherBank = _link # 2;
            private _otherBankNumber = if (_otherBank isEqualType 0) then {_otherBank} else {parseNumber _otherBank};
            if (abs (_otherBankNumber - _bankNumber) <= 0.001 && {(_link # 3) == _tg}) exitWith {_conflict = _x};
        };
    };
} forEach allPlayers;
if (!isNull _conflict) exitWith {
    ["ROIP", format ["TG%1 is already linked by %2.", _tg, name _conflict], 3] call Iceman_fnc_roip_notify;
    false
};

_state set ["radioSelection", _radioIndex];
_state set ["selectedRadioId", _record # 0];
_state set ["connectedRadioId", _record # 0];
_state set ["connectedTalkgroup", _tg];
_state set ["lastPublishedSignature", ""];
_state set ["appliedSignature", "__CONNECT__"];
profileNamespace setVariable ["Iceman_ROIP_lastTG", _tg];
saveProfileNamespace;

call Iceman_fnc_roip_tick;

private _radioName = ["PRC-117F", "PRC-152"] select ((_record # 1) == "ACRE_PRC152");
["ROIP", format ["%1 CH%2 linked to TG%3.", _radioName, _record # 2, _tg], 3] call Iceman_fnc_roip_notify;
true
