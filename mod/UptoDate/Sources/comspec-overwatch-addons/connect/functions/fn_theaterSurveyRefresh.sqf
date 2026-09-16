/*
    Met à jour durée, compteurs, secteur et date du dernier relevé.
*/
disableSerialization;
private _disp = uiNamespace getVariable ["COMSPEC_TheaterSurvey_Display", displayNull];
if (isNull _disp) exitWith {};

private _busy = missionNamespace getVariable ["COMSPEC_TheaterSampling", false]
    || {missionNamespace getVariable ["COMSPEC_GeoSampling", false]};
private _phase = missionNamespace getVariable ["COMSPEC_TheaterPhase", "idle"];
private _started = missionNamespace getVariable ["COMSPEC_TheaterStartedAt", -1];
private _buildings = missionNamespace getVariable ["COMSPEC_TheaterBuildings", 0];
private _forests = missionNamespace getVariable ["COMSPEC_TheaterForests", 0];
private _terrain = missionNamespace getVariable ["COMSPEC_TheaterTerrain", 0];
private _places = missionNamespace getVariable ["COMSPEC_TheaterPlaces", 0];
private _roads = missionNamespace getVariable ["COMSPEC_TheaterRoads", 0];
private _current = missionNamespace getVariable ["COMSPEC_TheaterCurrent", "En attente"];
private _done = missionNamespace getVariable ["COMSPEC_TheaterDone", 0];
private _total = missionNamespace getVariable ["COMSPEC_TheaterTotal", 0];

private _countKey = format ["COMSPEC_TheaterSurveyCounts_%1", worldName];
private _saved = profileNamespace getVariable [_countKey, []];
if ((_saved isEqualType []) && {(count _saved) >= 3}) then {
    if (_buildings < 1) then { _buildings = _saved select 0; };
    if (_forests < 1) then { _forests = _saved select 1; };
    if (_terrain < 1) then { _terrain = _saved select 2; };
};
if ((_saved isEqualType []) && {(count _saved) >= 5}) then {
    if (_places < 1) then { _places = _saved select 3; };
    if (_roads < 1) then { _roads = _saved select 4; };
};
private _mapId = missionNamespace getVariable ["COMSPEC_MapId", 1];
if (!(_mapId isEqualType 0) || {_mapId < 1}) then { _mapId = 1; };
private _geoKey = format ["COMSPEC_GeoDone_%1_%2", worldName, _mapId];
private _geoSaved = profileNamespace getVariable [_geoKey, []];
if ((_geoSaved isEqualType []) && {(count _geoSaved) >= 2}) then {
    if (_places < 1) then { _places = _geoSaved select 0; };
    if (_roads < 1) then { _roads = _geoSaved select 1; };
};

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
    "<t size='0.64' color='#e8f4f0'>Bâtiments %1 · Forêts %2 · Relief %3<br/>Villes %4 · Routes %5</t>",
    _buildings,
    _forests,
    _terrain,
    _places,
    _roads
];

(_disp displayCtrl 1103) ctrlSetStructuredText parseText format [
    "<t size='0.68' color='#c8ddd6'>%1</t>",
    _current
];

private _pct = 0;
if (_total > 0) then { _pct = (_done / _total) min 1; };
if (_phase isEqualTo "geo") then {
    if (_pct < 0.92) then { _pct = 0.92; };
};
if (_phase isEqualTo "done") then { _pct = 1; };
private _bar = _disp displayCtrl 1110;
if (!isNull _bar) then {
    private _maxW = 0.255 * safezoneW;
    private _x0 = 0.715 * safezoneW + safezoneX;
    private _y0 = 0.472 * safezoneH + safezoneY;
    _bar ctrlSetPosition [_x0, _y0, (_maxW * (0.02 max _pct)), 0.012 * safezoneH];
    _bar ctrlCommit 0;
};

private _progTxt = "";
if (_phase isEqualTo "geo") then {
    _progTxt = format ["Villes et routes — %1 lieux, %2 routes", _places, _roads];
} else {
    if (_total > 0) then {
        _progTxt = format ["%1 / %2 secteurs — %3 %", _done, _total, round (_pct * 100)];
    };
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
        _btn ctrlSetTooltip "Parcourt tout le théâtre et transmet bâtiments, forêts, relief, villes et routes au poste.";
    };
};

private _tx = missionNamespace getVariable ["COMSPEC_TheaterVerifyText", ""];
if (!(_tx isEqualType "") || {_tx isEqualTo ""}) then {
    _tx = "Pas encore vérifié. La comparaison avec le poste se lance à la fin du relevé.";
};
(_disp displayCtrl 1108) ctrlSetStructuredText parseText format [
    "<t size='0.56' color='#c8ddd6'>%1</t>",
    _tx
];

private _vBusy = missionNamespace getVariable ["COMSPEC_TheaterVerifyBusy", false];
private _btnV = _disp displayCtrl 1111;
if (!isNull _btnV) then {
    if (_busy || {_vBusy}) then {
        _btnV ctrlEnable false;
        _btnV ctrlSetTooltip "Attendez la fin du relevé ou de la vérification.";
    } else {
        _btnV ctrlEnable true;
        _btnV ctrlSetTooltip "Compare le relevé local avec ce qui est arrivé au poste, sans rien renvoyer.";
    };
};

private _btnR = _disp displayCtrl 1112;
if (!isNull _btnR) then {
    private _mode = missionNamespace getVariable ["COMSPEC_TheaterResendMode", ""];
    private _canResend = (_mode isEqualType "") && {_mode isNotEqualTo ""};
    if (_busy || {_vBusy} || {!_canResend}) then {
        _btnR ctrlEnable false;
        if (_canResend) then {
            _btnR ctrlSetTooltip "Attendez la fin du relevé ou de la vérification.";
        } else {
            _btnR ctrlSetTooltip "Lancez d’abord une vérification d’intégrité. Le bouton s’active s’il manque des données.";
        };
    } else {
        _btnR ctrlEnable true;
        _btnR ctrlSetTooltip "Renvoie uniquement ce que la vérification a trouvé manquant au poste.";
    };
};
