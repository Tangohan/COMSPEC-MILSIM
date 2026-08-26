/*
    Met à jour durée, compteurs, secteur et date du dernier relevé.
*/
disableSerialization;
private _disp = uiNamespace getVariable ["COMSPEC_TheaterSurvey_Display", displayNull];
if (isNull _disp) exitWith {};

private _busy = missionNamespace getVariable ["COMSPEC_TheaterSampling", false];
private _phase = missionNamespace getVariable ["COMSPEC_TheaterPhase", "idle"];
private _started = missionNamespace getVariable ["COMSPEC_TheaterStartedAt", -1];
private _buildings = missionNamespace getVariable ["COMSPEC_TheaterBuildings", 0];
private _forests = missionNamespace getVariable ["COMSPEC_TheaterForests", 0];
private _terrain = missionNamespace getVariable ["COMSPEC_TheaterTerrain", 0];
private _current = missionNamespace getVariable ["COMSPEC_TheaterCurrent", "En attente"];
private _done = missionNamespace getVariable ["COMSPEC_TheaterDone", 0];
private _total = missionNamespace getVariable ["COMSPEC_TheaterTotal", 0];

private _durTxt = "—";
if (_started >= 0) then {
    private _sec = 0 max (round (diag_tickTime - _started));
    if (_phase isEqualTo "done" || {_phase isEqualTo "abort"}) then {
        private _ended = missionNamespace getVariable ["COMSPEC_TheaterEndedAt", _started];
        _sec = 0 max (round (_ended - _started));
    };
    private _m = floor (_sec / 60);
    private _s = _sec mod 60;
    _durTxt = if (_m < 1) then {
        format ["%1 s", _s]
    } else {
        format ["%1 min %2 s", _m, _s]
    };
};

(_disp displayCtrl 1101) ctrlSetStructuredText parseText format [
    "<t size='0.85' color='#e8f4f0'>%1</t>",
    _durTxt
];

(_disp displayCtrl 1102) ctrlSetStructuredText parseText format [
    "<t size='0.70' color='#e8f4f0'>Bâtiments %1<br/>Forêts %2 · Relief %3</t>",
    _buildings,
    _forests,
    _terrain
];

(_disp displayCtrl 1103) ctrlSetStructuredText parseText format [
    "<t size='0.68' color='#c8ddd6'>%1</t>",
    _current
];

private _pct = 0;
if (_total > 0) then { _pct = (_done / _total) min 1; };
private _bar = _disp displayCtrl 1110;
if (!isNull _bar) then {
    private _maxW = 0.255 * safezoneW;
    private _x0 = 0.715 * safezoneW + safezoneX;
    private _y0 = 0.472 * safezoneH + safezoneY;
    _bar ctrlSetPosition [_x0, _y0, (_maxW * (0.02 max _pct)), 0.012 * safezoneH];
    _bar ctrlCommit 0;
};

private _progTxt = "";
if (_total > 0) then {
    _progTxt = format ["%1 / %2 secteurs — %3 %", _done, _total, round (_pct * 100)];
};
(_disp displayCtrl 1105) ctrlSetStructuredText parseText format [
    "<t size='0.55' color='#8aa0b4'>%1</t>",
    _progTxt
];

private _lastKey = format ["COMSPEC_TheaterSurveyLast_%1", worldName];
private _last = profileNamespace getVariable [_lastKey, ""];
if (!(_last isEqualType "") || {_last isEqualTo ""}) then {
    _last = missionNamespace getVariable ["COMSPEC_TheaterLastText", ""];
};
if (!(_last isEqualType "") || {_last isEqualTo ""}) then {
    _last = "Aucun relevé enregistré pour cette carte";
};
(_disp displayCtrl 1104) ctrlSetStructuredText parseText format [
    "<t size='0.62' color='#c8ddd6'>%1</t>",
    _last
];

private _btn = _disp displayCtrl 1106;
if (!isNull _btn) then {
    if (_busy) then {
        _btn ctrlSetText "Interrompre";
        _btn ctrlSetTooltip "Arrête le relevé en cours. Les données déjà transmises restent au poste.";
    } else {
        _btn ctrlSetText "Lancer le relevé";
        _btn ctrlSetTooltip "Parcourt tout le théâtre et transmet bâtiments, forêts et relief au poste.";
    };
};
