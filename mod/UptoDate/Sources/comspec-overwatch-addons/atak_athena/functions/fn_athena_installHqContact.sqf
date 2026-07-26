/*
    Contact permanent « HQ » dans la liste destinataires Messages ATAK / cTab.
    Idempotent : peut être rappelé après le chargement tardif de cTab.
*/
if (!hasInterface) exitWith {};

private _injectIntoCtrl = {
    params ["_ctrl"];
    if (isNull _ctrl) exitWith {};

    private _rows = [];
    for "_i" from 0 to ((lbSize _ctrl) - 1) do {
        private _data = _ctrl lbData _i;
        if (_data isEqualTo "COMSPEC_HQ") then { continue };
        _rows pushBack [
            _ctrl lbText _i,
            _data,
            _ctrl lbColor _i
        ];
    };

    lbClear _ctrl;
    private _hqIdx = _ctrl lbAdd "HQ";
    _ctrl lbSetData [_hqIdx, "COMSPEC_HQ"];
    _ctrl lbSetTooltip [_hqIdx, "Poste de commandement Athena — message vers le TOC web"];
    _ctrl lbSetColor [_hqIdx, [0.45, 0.88, 0.68, 1]];

    {
        _x params ["_txt", "_data", "_col"];
        private _idx = _ctrl lbAdd _txt;
        _ctrl lbSetData [_idx, _data];
        if ((count _col) >= 4) then { _ctrl lbSetColor [_idx, _col]; };
    } forEach _rows;
};

private _injectFromOpen = {
    if (isNil "cTabIfOpen") exitWith {};
    if (!((cTabIfOpen select 0) isEqualType 0)) exitWith {};
    private _dispName = cTabIfOpen select 1;
    if (!(_dispName isEqualType "")) exitWith {};
    private _display = uiNamespace getVariable [_dispName, displayNull];
    if (isNull _display) exitWith {};
    private _ctrl = _display displayCtrl 15010; // IDC_CTAB_MSG_RECIPIENTS
    if (isNull _ctrl) exitWith {};
    [_ctrl] call (missionNamespace getVariable ["COMSPEC_Athena_HqInjectCtrl", {}]);
};

missionNamespace setVariable ["COMSPEC_Athena_HqInjectCtrl", _injectIntoCtrl, false];
missionNamespace setVariable ["COMSPEC_Athena_HqInjectOpen", _injectFromOpen, false];

// --- EDITTED / legacy cTab (player_init) ---
if (!isNil "cTab_msg_gui_load" && {!(missionNamespace getVariable ["COMSPEC_Athena_HqWrapped_guiLoad", false])}) then {
    private _oldLoad = cTab_msg_gui_load;
    cTab_msg_gui_load = {
        private _r = call _oldLoad;
        [] call (missionNamespace getVariable ["COMSPEC_Athena_HqInjectOpen", {}]);
        _r
    };
    missionNamespace setVariable ["COMSPEC_Athena_HqWrapped_guiLoad", true, false];
};

if (!isNil "cTab_msg_Send" && {!(missionNamespace getVariable ["COMSPEC_Athena_HqWrapped_msgSend", false])}) then {
    private _oldSend = cTab_msg_Send;
    cTab_msg_Send = {
        disableSerialization;
        private _display = uiNamespace getVariable [(cTabIfOpen select 1), displayNull];
        if (isNull _display) exitWith { false };
        private _plrLBctrl = _display displayCtrl 15010;
        private _msgBodyctrl = _display displayCtrl 14000;
        private _msgBody = if (!isNull _msgBodyctrl) then { ctrlText _msgBodyctrl } else { "" };
        if (_msgBody isEqualTo "") exitWith { false };

        private _indices = lbSelection _plrLBctrl;
        if (_indices isEqualTo []) exitWith { false };

        private _hqSelected = false;
        private _playerIndices = [];
        {
            private _data = _plrLBctrl lbData _x;
            if (_data isEqualTo "COMSPEC_HQ") then {
                _hqSelected = true;
            } else {
                _playerIndices pushBack _x;
            };
        } forEach _indices;

        private _hqOk = false;
        if (_hqSelected) then {
            _hqOk = [_msgBody] call comspec_overwatch_atak_athena_fnc_athena_sendHqMessage;
        };

        private _playerOk = false;
        if ((count _playerIndices) > 0) then {
            _plrLBctrl lbSetCurSel -1;
            { _plrLBctrl lbSetSelected [_x, true] } forEach _playerIndices;
            _playerOk = call _oldSend;
        } else {
            if (_hqOk) then {
                if (!isNull _msgBodyctrl) then { _msgBodyctrl ctrlSetText ""; };
                _plrLBctrl lbSetCurSel -1;
                if (!isNil "cTab_fnc_addNotification") then {
                    ["MSG", "Message transmis à HQ", 3] call cTab_fnc_addNotification;
                };
                playSound "cTab_mailSent";
                if (!isNil "cTabIfOpen" && {[cTabIfOpen select 1, "mode"] call cTab_fnc_getSettings == "MESSAGE"}) then {
                    call cTab_msg_gui_load;
                };
            };
        };

        _hqOk || _playerOk
    };
    missionNamespace setVariable ["COMSPEC_Athena_HqWrapped_msgSend", true, false];
};

// --- cTab moderne (messaging component) ---
if (!isNil "ctab_messaging_fnc_fillRecipientList" && {!(missionNamespace getVariable ["COMSPEC_Athena_HqWrapped_fill", false])}) then {
    private _oldFill = ctab_messaging_fnc_fillRecipientList;
    ctab_messaging_fnc_fillRecipientList = {
        params ["_control"];
        [_control] call _oldFill;
        [_control] call (missionNamespace getVariable ["COMSPEC_Athena_HqInjectCtrl", {}]);
    };
    missionNamespace setVariable ["COMSPEC_Athena_HqWrapped_fill", true, false];
};

if (!isNil "ctab_messaging_fnc_getSelectedRecipients" && {!(missionNamespace getVariable ["COMSPEC_Athena_HqWrapped_getSel", false])}) then {
    private _oldGet = ctab_messaging_fnc_getSelectedRecipients;
    ctab_messaging_fnc_getSelectedRecipients = {
        params ["_control"];
        private _selectedData = (lbSelection _control) apply { _control lbData _x };
        missionNamespace setVariable ["COMSPEC_MsgHqSelected", "COMSPEC_HQ" in _selectedData, false];
        [_control] call _oldGet
    };
    missionNamespace setVariable ["COMSPEC_Athena_HqWrapped_getSel", true, false];
};

if (!isNil "ctab_fnc_msg_Send" && {!(missionNamespace getVariable ["COMSPEC_Athena_HqWrapped_modernSend", false])}) then {
    private _oldModernSend = ctab_fnc_msg_Send;
    ctab_fnc_msg_Send = {
        disableSerialization;
        private _display = uiNamespace getVariable [(cTabIfOpen select 1), displayNull];
        private _msgBodyctrl = _display displayCtrl 14000;
        private _plrLBctrl = _display displayCtrl 15010;
        private _msgBody = if (!isNull _msgBodyctrl) then { ctrlText _msgBodyctrl } else { "" };
        private _hq = false;
        if (!isNull _plrLBctrl) then {
            private _selData = (lbSelection _plrLBctrl) apply { _plrLBctrl lbData _x };
            _hq = "COMSPEC_HQ" in _selData;
        };
        if (!_hq) then { _hq = missionNamespace getVariable ["COMSPEC_MsgHqSelected", false]; };

        private _hqOk = false;
        if (_hq && {_msgBody isNotEqualTo ""}) then {
            _hqOk = [_msgBody] call comspec_overwatch_atak_athena_fnc_athena_sendHqMessage;
        };

        private _r = call _oldModernSend;
        if (_hqOk && {!_r}) then {
            if (!isNull _msgBodyctrl) then { _msgBodyctrl ctrlSetText ""; };
            true
        } else {
            _r || _hqOk
        }
    };
    missionNamespace setVariable ["COMSPEC_Athena_HqWrapped_modernSend", true, false];
};

// Filet PFH une seule fois
if (!(missionNamespace getVariable ["COMSPEC_Athena_HqPfh", false])) then {
    missionNamespace setVariable ["COMSPEC_Athena_HqPfh", true, false];
    [{
        if (isNil "cTabIfOpen") exitWith {};
        private _mode = "";
        if (!isNil "cTab_fnc_getSettings") then {
            _mode = [cTabIfOpen select 1, "mode"] call cTab_fnc_getSettings;
        };
        if (_mode isNotEqualTo "MESSAGE") exitWith {};
        [] call (missionNamespace getVariable ["COMSPEC_Athena_HqInjectOpen", {}]);
    }, 2, []] call CBA_fnc_addPerFrameHandler;
};

missionNamespace setVariable ["COMSPEC_Athena_HqContactInstalled", true, false];
