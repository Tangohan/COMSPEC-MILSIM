/*
    Recale la page Comptes-rendus IceMan dans la taille réelle du téléphone.
    Inbox : titre → onglets → liste → Localiser/Effacer → détail (sans chevauchement).
    Nouveau : champs espacés + Envoyer/Effacer collés en bas (hors barre Retour).
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

private _ctrls = [_group] call _fncAll;

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

private _pad = ((_w * 0.04) max 0.004);
private _titleH = ((_h * 0.085) max 0.02);
private _tabH = ((_h * 0.078) max 0.018);
private _btnH = ((_h * 0.075) max 0.017);
private _gap = ((_h * 0.016) max 0.0035);
private _fullW = _w - (2 * _pad);
private _halfW = (_fullW - _gap) / 2;

private _fncSet = {
    params ["_grp", "_idc", "_rect"];
    private _c = _grp controlsGroupCtrl _idc;
    if (isNull _c) exitWith { controlNull };
    _c ctrlSetPosition _rect;
    _c ctrlCommit 0;
    _c
};

private _fncOpaqueEdit = {
    params ["_c"];
    if (isNull _c) exitWith {};
    // Évite le fond carte / texte jaune qui traverse les champs.
    _c ctrlSetBackgroundColor [0.07, 0.07, 0.08, 1];
};

[_group, 9600, [0, 0, _w, _titleH]] call _fncSet;
private _tabY = _titleH + _gap;
[_group, 9601, [_pad, _tabY, _halfW, _tabH]] call _fncSet;
[_group, 9602, [_pad + _halfW + _gap, _tabY, _halfW, _tabH]] call _fncSet;

private _contentTop = _tabY + _tabH + _gap;
private _footerReserve = _btnH + _gap + _pad;

private _fncFormOfIdc = {
    params ["_idc"];
    if (_idc >= 9650 && {_idc <= 9657}) exitWith { "TIC" };
    if (_idc >= 9540 && {_idc <= 9551}) exitWith { "SALUTE" };
    if (_idc >= 9660 && {_idc <= 9686}) exitWith { "EAGLE_DOWN" };
    if (_idc >= 9630 && {_idc <= 9643}) exitWith { "FRAGO" };
    if (_idc >= 9700 && {_idc <= 9729}) exitWith { "BDA" };
    ""
};

// --- Visibilité onglets ---
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

if (_tab isEqualTo "inbox") then {
    private _y = _contentTop;
    private _remain = (_h - _y - _pad) max 0.1;
    // Liste ~42 %, boutons, détail le reste — boutons jamais sous le détail.
    private _listH = (_remain * 0.42) max 0.06;
    private _detH = (_remain - _listH - _btnH - (2 * _gap)) max 0.06;

    [_group, 9610, [_pad, _y, _fullW, _listH]] call _fncSet;
    _y = _y + _listH + _gap;
    [_group, 9611, [_pad, _y, _halfW, _btnH]] call _fncSet;
    [_group, 9612, [_pad + _halfW + _gap, _y, _halfW, _btnH]] call _fncSet;
    _y = _y + _btnH + _gap;
    private _dg = [_group, 9613, [_pad, _y, _fullW, _detH]] call _fncSet;

    private _det = _group controlsGroupCtrl 9615;
    if (isNull _det && {!isNull _dg}) then {
        _det = _dg controlsGroupCtrl 9615;
    };
    if (!isNull _det) then {
        // Hauteur bornée au groupe parent (évite le panneau qui mange Localiser / Effacer).
        private _innerH = (_detH * 0.96) max 0.04;
        _det ctrlSetPosition [0, 0, _fullW * 0.97, _innerH];
        _det ctrlCommit 0;
        private _list = _group controlsGroupCtrl 9610;
        if (!isNull _list && {lbSize _list == 0}) then {
            _det ctrlSetStructuredText parseText "<t size='0.82' color='#c8d0d8'>Aucun compte rendu pour le moment.</t>";
        } else {
            private _txt = ctrlText _det;
            if (_txt isEqualTo "No reports received." || {(toLower _txt) find "no reports" >= 0}) then {
                _det ctrlSetStructuredText parseText "<t size='0.82' color='#c8d0d8'>Aucun compte rendu pour le moment.</t>";
            };
        };
    };
} else {
    // --- Onglet Nouveau : type + champs du formulaire actif + boutons bas ---
    private _y = _contentTop;
    private _labelW = _fullW * 0.34;
    private _inputW = _fullW - _labelW - _gap;
    private _rowH = ((_h * 0.048) max 0.015) min 0.026;

    // Masquer le sous-titre formulaire (gagne une ligne, évite le double titre).
    private _ft = _group controlsGroupCtrl 9623;
    if (!isNull _ft) then {
        _ft ctrlShow false;
        _ft ctrlEnable false;
    };

    private _typeL = _group controlsGroupCtrl 9614;
    private _typeC = _group controlsGroupCtrl 9620;
    if (!isNull _typeL) then {
        _typeL ctrlSetPosition [_pad, _y, _labelW, _rowH];
        _typeL ctrlSetStructuredText parseText "<t size='0.7'>Type</t>";
        _typeL ctrlCommit 0;
    };
    if (!isNull _typeC) then {
        _typeC ctrlSetPosition [_pad + _labelW + _gap, _y, _inputW, _rowH];
        [_typeC] call _fncOpaqueEdit;
        _typeC ctrlCommit 0;
    };
    _y = _y + _rowH + (_gap * 0.7);

    private _send = _group controlsGroupCtrl 9621;
    private _clr = _group controlsGroupCtrl 9622;
    private _btnY = _h - _pad - _btnH;
    if (!isNull _send) then {
        _send ctrlSetPosition [_pad, _btnY, _halfW, _btnH];
        _send ctrlCommit 0;
    };
    if (!isNull _clr) then {
        _clr ctrlSetPosition [_pad + _halfW + _gap, _btnY, _halfW, _btnH];
        _clr ctrlCommit 0;
    };

    private _fieldBottom = _btnY - _gap;
    private _avail = (_fieldBottom - _y) max 0.05;

    // Collecter les lignes visibles du formulaire (label pairé + champ, ou section seule).
    private _rows = [];
    private _seen = [];
    private _candidates = [];
    {
        private _idc = ctrlIDC _x;
        if !(_x getVariable ["IcemanReportsCtrl", false]) then { continue };
        if !(ctrlShown _x) then { continue };
        if (_idc in [9600, 9601, 9602, 9614, 9620, 9621, 9622, 9623]) then { continue };
        private _want = [_idc] call _fncFormOfIdc;
        private _formTag = _x getVariable ["IcemanReportsForm", ""];
        if (_formTag isEqualTo "commonForm") then { continue };
        if (_want isNotEqualTo _form && {_formTag isNotEqualTo _form}) then { continue };
        _candidates pushBack [_idc, _x];
    } forEach _ctrls;
    _candidates sort true;

    {
        _x params ["_idc", "_ctrl"];
        if (_idc in _seen) then { continue };

        if ((_idc % 2) == 0) then {
            private _sib = _group controlsGroupCtrl (_idc + 1);
            if (!isNull _sib && {ctrlShown _sib}) then {
                _rows pushBack [_ctrl, _sib];
                _seen append [_idc, _idc + 1];
            } else {
                _rows pushBack [_ctrl, controlNull];
                _seen pushBack _idc;
            };
        } else {
            _rows pushBack [controlNull, _ctrl];
            _seen pushBack _idc;
        };
    } forEach _candidates;

    private _n = count _rows;
    if (_n > 0) then {
        private _step = (_avail / _n) max (_rowH * 0.85);
        _step = _step min (_rowH * 1.35);
        // Si trop de lignes, on serre un peu mais on garde une marge.
        if ((_n * _step) > _avail) then {
            _step = (_avail / _n) max 0.012;
        };
        {
            _x params ["_lab", "_inp"];
            private _ry = _y + (_forEachIndex * _step);
            private _rh = (_step * 0.88) max 0.011;
            if (!isNull _lab && {isNull _inp}) then {
                _lab ctrlSetPosition [_pad, _ry, _fullW, _rh];
                _lab ctrlCommit 0;
            } else {
                if (!isNull _lab) then {
                    _lab ctrlSetPosition [_pad, _ry, _labelW, _rh];
                    _lab ctrlCommit 0;
                };
                if (!isNull _inp) then {
                    private _ix = if (isNull _lab) then { _pad } else { _pad + _labelW + _gap };
                    private _iw = if (isNull _lab) then { _fullW } else { _inputW };
                    _inp ctrlSetPosition [_ix, _ry, _iw, _rh];
                    [_inp] call _fncOpaqueEdit;
                    _inp ctrlCommit 0;
                };
            };
        } forEach _rows;
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
