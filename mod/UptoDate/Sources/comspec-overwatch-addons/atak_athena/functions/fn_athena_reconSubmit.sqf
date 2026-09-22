/*
    Envoie la note depuis l’app Reco du téléphone.
*/
if (!hasInterface) exitWith {};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Recon_group", controlNull];
if (isNull _group) exitWith {};

private _edit = _group controlsGroupCtrl 9782;
private _text = if (isNull _edit) then { "" } else { trim (ctrlText _edit) };
if ((count _text) > 140) then { _text = _text select [0, 140]; };

private _tag = "other";
private _combo = _group controlsGroupCtrl 9784;
if (!isNull _combo) then {
    private _idx = lbCurSel _combo;
    if (_idx >= 0) then { _tag = _combo lbData _idx; };
};
if (_tag isEqualTo "") then { _tag = "other"; };

private _conf = "vu_direct";
private _cCombo = _group controlsGroupCtrl 9785;
if (!isNull _cCombo) then {
    private _cIdx = lbCurSel _cCombo;
    if (_cIdx >= 0) then { _conf = _cCombo lbData _cIdx; };
};

private _pos = uiNamespace getVariable ["COMSPEC_ReconNote_Pos", []];
if (!(_pos isEqualType []) || {(count _pos) < 3}) then {
    _pos = [] call comspec_overwatch_connect_fnc_reconLookPos;
};

private _ok = [_text, _tag, _conf, _pos] call comspec_overwatch_connect_fnc_reconPushNote;
if (_ok) then {
    if (!isNull _edit) then { _edit ctrlSetText ""; };
    [] call comspec_overwatch_atak_athena_fnc_athena_updateRecon;
};
