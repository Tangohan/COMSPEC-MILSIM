/*
    Envoie l’ordre / FRAGO depuis la mini-fenêtre contextuelle.
*/
if (!hasInterface) exitWith {};
if !([] call comspec_overwatch_connect_fnc_canIssueOrder) exitWith {
    ["Seul le chef d’unité peut émettre cet ordre.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _disp = uiNamespace getVariable ["COMSPEC_OrderCompose_Display", displayNull];
if (isNull _disp) exitWith {};

private _kindIdx = lbCurSel (_disp displayCtrl 9502);
private _prioIdx = lbCurSel (_disp displayCtrl 9503);
private _tgtIdx = lbCurSel (_disp displayCtrl 9504);
if (_kindIdx < 0 || {_prioIdx < 0} || {_tgtIdx < 0}) exitWith {
    ["Choisissez le type, la priorité et le destinataire.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _kind = (_disp displayCtrl 9502) lbData _kindIdx;
private _prio = (_disp displayCtrl 9503) lbData _prioIdx;
private _tgtData = (_disp displayCtrl 9504) lbData _tgtIdx;
private _tgtParts = _tgtData splitString "|";
private _targetType = if ((count _tgtParts) >= 1) then { _tgtParts select 0 } else { "group" };
private _targetRef = if ((count _tgtParts) >= 2) then { _tgtParts select 1 } else { "" };
private _targetLabel = if ((count _tgtParts) >= 3) then { _tgtParts select 2 } else { _targetRef };
if (_targetLabel isEqualTo "") then { _targetLabel = _targetRef; };

private _isFrago = (_kind isEqualTo "FRAGO");
private _payload = "";
private _orderType = _kind;

if (_isFrago) then {
    _orderType = "FRAGO";
    private _sit = trim (ctrlText (_disp displayCtrl 9521));
    private _mis = trim (ctrlText (_disp displayCtrl 9523));
    private _exe = trim (ctrlText (_disp displayCtrl 9525));
    private _sup = trim (ctrlText (_disp displayCtrl 9527));
    private _cmd = trim (ctrlText (_disp displayCtrl 9529));
    private _parts = [];
    if (_sit isNotEqualTo "") then { _parts pushBack format ["Situation: %1", _sit]; };
    if (_mis isNotEqualTo "") then { _parts pushBack format ["Mission: %1", _mis]; };
    if (_exe isNotEqualTo "") then { _parts pushBack format ["Exécution: %1", _exe]; };
    if (_sup isNotEqualTo "") then { _parts pushBack format ["Soutien: %1", _sup]; };
    if (_cmd isNotEqualTo "") then { _parts pushBack format ["Commandement: %1", _cmd]; };
    if ((count _parts) < 1) then {
        _payload = "";
    } else {
        _payload = _parts joinString " — ";
    };
} else {
    _payload = trim (ctrlText (_disp displayCtrl 9511));
};

if (_isFrago && {_payload isEqualTo ""}) exitWith {
    ["Renseignez au moins une rubrique du FRAGO.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

private _target = if (_targetType isEqualTo "all") then { "" } else { _targetLabel };
if (_target isEqualTo "" && {_targetType isNotEqualTo "all"}) then {
    _target = groupId (group player);
    if (_target isEqualTo "") then { _target = name player; };
};

[_orderType, _target, _payload, _prio, "", _targetType] call comspec_overwatch_connect_fnc_issueOrder;

if (_isFrago) then {
    private _grid = mapGridPosition player;
    private _cs = "";
    if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
        _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    };
    if (_cs isEqualTo "") then { _cs = name player; };
    private _alertBody = format ["FRAGO — %1 · grille %2 — %3", _cs, _grid, _payload];
    ["FRAGO", _alertBody, getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert;
};

private _label = if (_isFrago) then { "Ordre fragmentaire" } else {
    switch (_orderType) do {
        case "MOVE": { "Déplacement" };
        case "HOLD": { "Maintien" };
        case "RECON": { "Reconnaissance" };
        case "CAS": { "Appui aérien" };
        case "QRF": { "Renfort" };
        default { "Ordre" };
    }
};

[format ["%1 envoyé.", _label], "order", "info"] call comspec_overwatch_connect_fnc_announce;
closeDialog 0;
