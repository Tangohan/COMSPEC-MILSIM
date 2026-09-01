/*
    Recale la page Comptes-rendus IceMan dans la taille réelle du téléphone.
    IceMan pose les champs sur une page encore à 1×1, puis le menu se
    rétrécit : liste, Localiser / Effacer et le détail se marchent dessus.
    Après le calage, un seul formulaire (TIC, Eagle Down, etc.) reste visible.
*/
if (!hasInterface) exitWith {};

private _fncIsReports = {
    params ["_g"];
    if (isNull _g) exitWith { false };
    ((ctrlClassName _g) find "Iceman_ATAK_Reports") >= 0
};

private _fncAll = {
    params ["_g"];
    private _list = allControls _g;
    {
        if (ctrlType _x == 15) then {
            _list append (allControls _x);
        };
    } forEach +_list;
    _list
};

private _group = uiNamespace getVariable ["Iceman_ATAK_Alerts_group", controlNull];
if !([_group] call _fncIsReports) then {
    _group = controlNull;
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) then {
        _display = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
    };
    if (!isNull _display) then {
        private _apps = _display displayCtrl (17000 + 4650);
        if (!isNull _apps) then {
            {
                if ([_x] call _fncIsReports) exitWith {
                    _group = _x;
                    uiNamespace setVariable ["Iceman_ATAK_Alerts_group", _group];
                };
            } forEach (allControls _apps);
        };
    };
};

if (isNull _group || {!ctrlShown _group}) exitWith {};

["reports"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;

private _stolen = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _stolen && {((ctrlClassName _stolen) find "COMSPEC_ATAK_Athena") < 0}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_Athena_group", controlNull];
};

(ctrlPosition _group) params ["", "", "_w", "_h"];
if (_w < 0.03 || {_h < 0.07}) exitWith {};

private _srcW = _group getVariable ["COMSPEC_ReportsNativeW", -1];
private _srcH = _group getVariable ["COMSPEC_ReportsNativeH", -1];
private _ctrls = [_group] call _fncAll;

if (_srcW > 0.02 && {_srcH > 0.02}) then {
    private _sx = _w / _srcW;
    private _sy = _h / _srcH;
    if ((abs (_sx - 1) > 0.01) || {abs (_sy - 1) > 0.01}) then {
        {
            if !(_x getVariable ["IcemanReportsCtrl", false]) then { continue };
            private _np = _x getVariable ["COMSPEC_ReportsNativePos", []];
            if ((count _np) < 4) then { continue };
            _x ctrlSetPosition [
                (_np select 0) * _sx,
                (_np select 1) * _sy,
                (_np select 2) * _sx,
                (_np select 3) * _sy
            ];
            _x ctrlCommit 0;
        } forEach _ctrls;
    };
};

{
    private _idc = ctrlIDC _x;
    if (_idc in [5, 6, 10, 11]) then {
        _x ctrlShow false;
        _x ctrlEnable false;
    };
} forEach _ctrls;

private _tab = missionNamespace getVariable ["Iceman_ATAK_Reports_tab", "inbox"];
if !(_tab in ["inbox", "new"]) then { _tab = "inbox"; };
private _form = missionNamespace getVariable ["Iceman_ATAK_Reports_form", "TIC"];
if !(_form in ["TIC", "EAGLE_DOWN", "BDA", "FRAGO", "SALUTE"]) then { _form = "TIC"; };

private _pad = ((_w * 0.035) max 0.0035);
private _titleH = ((_h * 0.075) max 0.018);
private _tabH = ((_h * 0.072) max 0.016);
private _btnH = ((_h * 0.068) max 0.015);
private _gap = ((_h * 0.012) max 0.0025);
private _fullW = _w - (2 * _pad);
private _halfW = (_fullW - _pad) / 2;

private _fncSet = {
    params ["_grp", "_idc", "_rect"];
    private _c = _grp controlsGroupCtrl _idc;
    if (isNull _c) exitWith {};
    _c ctrlSetPosition _rect;
    _c ctrlCommit 0;
    _c
};

[_group, 9600, [0, 0, _w, _titleH]] call _fncSet;
private _tabY = _titleH + _gap;
[_group, 9601, [_pad, _tabY, _halfW, _tabH]] call _fncSet;
[_group, 9602, [_pad * 2 + _halfW, _tabY, _halfW, _tabH]] call _fncSet;

if (_tab isEqualTo "inbox") then {
    private _y = _tabY + _tabH + _gap;
    private _remain = (_h - _y - _pad) max 0.08;
    private _listH = (_remain * 0.36) max 0.05;
    private _detH = (_remain - _listH - _btnH - (2 * _gap)) max 0.05;

    [_group, 9610, [_pad, _y, _fullW, _listH]] call _fncSet;
    _y = _y + _listH + _gap;
    [_group, 9611, [_pad, _y, _halfW, _btnH]] call _fncSet;
    [_group, 9612, [_pad * 2 + _halfW, _y, _halfW, _btnH]] call _fncSet;
    _y = _y + _btnH + _gap;
    [_group, 9613, [_pad, _y, _fullW, _detH]] call _fncSet;

    private _det = _group controlsGroupCtrl 9615;
    if (isNull _det) then {
        private _dg = _group controlsGroupCtrl 9613;
        if (!isNull _dg) then {
            _det = _dg controlsGroupCtrl 9615;
        };
    };
    if (!isNull _det) then {
        private _need = ((ctrlTextHeight _det) + 0.02) max (_detH * 0.92);
        _det ctrlSetPosition [0, 0, _fullW * 0.96, _need];
        _det ctrlCommit 0;
    };

    private _list = _group controlsGroupCtrl 9610;
    if (!isNull _list && {!isNull _det} && {lbSize _list == 0}) then {
        _det ctrlSetStructuredText parseText "<t size='0.78'>Aucun compte rendu pour le moment.</t>";
    };
};

private _title = _group controlsGroupCtrl 9600;
if (!isNull _title) then {
    _title ctrlSetStructuredText parseText "<t align='center' size='0.95'>Comptes-rendus</t>";
};

private _fncLabel = {
    params ["_grp", "_idc", "_txt"];
    private _c = _grp controlsGroupCtrl _idc;
    if (!isNull _c) then {
        _c ctrlSetText _txt;
    };
};
[_group, 9601, "Reçus"] call _fncLabel;
[_group, 9602, "Nouveau"] call _fncLabel;
[_group, 9611, "Localiser"] call _fncLabel;
[_group, 9612, "Effacer"] call _fncLabel;
[_group, 9621, "Envoyer"] call _fncLabel;
[_group, 9622, "Effacer"] call _fncLabel;

private _fncFormOfIdc = {
    params ["_idc"];
    if (_idc >= 9650 && {_idc <= 9657}) exitWith { "TIC" };
    if (_idc >= 9540 && {_idc <= 9551}) exitWith { "SALUTE" };
    if (_idc >= 9660 && {_idc <= 9686}) exitWith { "EAGLE_DOWN" };
    if (_idc >= 9630 && {_idc <= 9643}) exitWith { "FRAGO" };
    if (_idc >= 9700 && {_idc <= 9729}) exitWith { "BDA" };
    ""
};

{
    private _idc = ctrlIDC _x;
    private _section = _x getVariable ["IcemanReportsSection", ""];
    private _formTag = _x getVariable ["IcemanReportsForm", ""];
    if (_section isEqualTo "common") then { continue };
    if (_idc in [9600, 9601, 9602]) then { continue };

    if (_tab isEqualTo "inbox") then {
        private _showInbox = _idc in [9610, 9611, 9612, 9613, 9615] || {_section isEqualTo "inbox"};
        if (_section isEqualTo "new" || {_idc in [9614, 9620, 9621, 9622, 9623]} || {([_idc] call _fncFormOfIdc) isNotEqualTo ""}) then {
            _showInbox = false;
        };
        if (_section isNotEqualTo "" || {_idc >= 9540}) then {
            _x ctrlShow _showInbox;
            _x ctrlEnable _showInbox;
        };
    } else {
        private _showNew = false;
        if (_idc in [9614, 9620, 9621, 9622, 9623] || {_formTag isEqualTo "commonForm"}) then {
            _showNew = true;
        } else {
            private _want = if (_formTag isNotEqualTo "" && {_formTag isNotEqualTo "commonForm"}) then {
                _formTag
            } else {
                [_idc] call _fncFormOfIdc
            };
            _showNew = (_want isEqualTo _form) && {_want isNotEqualTo ""};
        };
        if (_idc in [9610, 9611, 9612, 9613, 9615] || {_section isEqualTo "inbox"}) then {
            _showNew = false;
        };
        if (_section isNotEqualTo "" || {_idc >= 9540}) then {
            _x ctrlShow _showNew;
            _x ctrlEnable _showNew;
        };
    };
} forEach _ctrls;
