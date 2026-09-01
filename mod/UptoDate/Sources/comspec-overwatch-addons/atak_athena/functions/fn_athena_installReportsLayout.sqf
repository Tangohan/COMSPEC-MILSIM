/*
    Recale IceMan Reports après ouverture / rafraîchissement / barre du bas.
    IceMan peut arriver après nous : on retente le branchement.
*/
if (!hasInterface) exitWith {};

private _snapshot = {
    private _group = uiNamespace getVariable ["Iceman_ATAK_Alerts_group", controlNull];
    if (isNull _group) exitWith {};
    if (((ctrlClassName _group) find "Iceman_ATAK_Reports") < 0) exitWith {};
    (ctrlPosition _group) params ["", "", "_w", "_h"];
    _group setVariable ["COMSPEC_ReportsNativeW", _w];
    _group setVariable ["COMSPEC_ReportsNativeH", _h];
    private _list = allControls _group;
    {
        if (ctrlType _x == 15) then {
            _list append (allControls _x);
        };
    } forEach +_list;
    {
        if (_x getVariable ["IcemanReportsCtrl", false]) then {
            _x setVariable ["COMSPEC_ReportsNativePos", ctrlPosition _x];
        };
    } forEach _list;
};

private _scheduleFix = {
    [] call comspec_overwatch_atak_athena_fnc_athena_fixReportsLayout;
    {
        [{ [] call comspec_overwatch_atak_athena_fnc_athena_fixReportsLayout; }, [], _x] call CBA_fnc_waitAndExecute;
    } forEach [0.05, 0.25, 0.8, 1.4];
};

missionNamespace setVariable ["COMSPEC_ReportsSnapshot", _snapshot];
missionNamespace setVariable ["COMSPEC_ReportsScheduleFix", _scheduleFix];

if (isNil "Iceman_fnc_alerts_onOpened" && {isNil "Iceman_fnc_alerts_updatePanel"}) exitWith {};

if (!(missionNamespace getVariable ["COMSPEC_ReportsOpenedWrapped", false]) && {!isNil "Iceman_fnc_alerts_onOpened"}) then {
    missionNamespace setVariable ["COMSPEC_Prev_Iceman_alerts_onOpened", Iceman_fnc_alerts_onOpened];
    Iceman_fnc_alerts_onOpened = {
        private _r = _this call (missionNamespace getVariable ["COMSPEC_Prev_Iceman_alerts_onOpened", {}]);
        [] call (missionNamespace getVariable ["COMSPEC_ReportsSnapshot", {}]);
        ["reports"] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
        private _ag = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
        if (!isNull _ag && {((ctrlClassName _ag) find "COMSPEC_ATAK_Athena") < 0}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_Athena_group", controlNull];
        };
        [] call (missionNamespace getVariable ["COMSPEC_ReportsScheduleFix", {}]);
        _r
    };
    missionNamespace setVariable ["COMSPEC_ReportsOpenedWrapped", true];
};

if (!(missionNamespace getVariable ["COMSPEC_ReportsUpdateWrapped", false]) && {!isNil "Iceman_fnc_alerts_updatePanel"}) then {
    missionNamespace setVariable ["COMSPEC_Prev_Iceman_alerts_updatePanel", Iceman_fnc_alerts_updatePanel];
    Iceman_fnc_alerts_updatePanel = {
        private _r = _this call (missionNamespace getVariable ["COMSPEC_Prev_Iceman_alerts_updatePanel", {}]);
        private _group = uiNamespace getVariable ["Iceman_ATAK_Alerts_group", controlNull];
        if (!isNull _group && {(_group getVariable ["COMSPEC_ReportsNativeW", -1]) > 0}) then {
            [] call comspec_overwatch_atak_athena_fnc_athena_fixReportsLayout;
        };
        _r
    };
    missionNamespace setVariable ["COMSPEC_ReportsUpdateWrapped", true];
};

if (!(missionNamespace getVariable ["COMSPEC_ReportsButtonsWrapped", false]) && {!isNil "Iceman_fnc_alerts_initButtons"}) then {
    missionNamespace setVariable ["COMSPEC_Prev_Iceman_alerts_initButtons", Iceman_fnc_alerts_initButtons];
    Iceman_fnc_alerts_initButtons = {
        private _r = _this call (missionNamespace getVariable ["COMSPEC_Prev_Iceman_alerts_initButtons", {}]);
        if (!(_this isEqualType [])) exitWith { _r };
        if ((count _this) < 1) exitWith { _r };
        private _ctrlBnts = _this select 0;
        if (!(_ctrlBnts isEqualType []) || {(count _ctrlBnts) < 3}) exitWith { _r };
        _ctrlBnts params ["_bnt_back", "_bnt_Ent", "_bnt_third"];
        private _slot = 0;
        if ((count _this) > 1) then {
            private _ctrlPOS = _this select 1;
            if ((_ctrlPOS isEqualType []) && {(count _ctrlPOS) > 2}) then {
                _slot = _ctrlPOS select 2;
            };
        };
        if (!isNull _bnt_back) then {
            private _bar = ctrlParentControlsGroup _bnt_back;
            if (isNull _bar) then { _bar = ctrlParent _bnt_back; };
            if (!isNull _bar) then {
                private _bw = (ctrlPosition _bar) select 2;
                if (_bw > 0.04) then { _slot = _bw / 3; };
            };
            _bnt_back ctrlSetText "Retour";
            if (_slot > 0.01) then {
                _bnt_back ctrlSetPositionX 0;
                _bnt_back ctrlSetPositionW _slot;
                _bnt_back ctrlCommit 0;
            };
        };
        if (!isNull _bnt_Ent) then {
            _bnt_Ent ctrlSetText "Localiser";
            if (_slot > 0.01) then {
                _bnt_Ent ctrlSetPositionX _slot;
                _bnt_Ent ctrlSetPositionW _slot;
                _bnt_Ent ctrlCommit 0;
            };
        };
        if (!isNull _bnt_third) then {
            _bnt_third ctrlSetText "Effacer";
            if (_slot > 0.01) then {
                _bnt_third ctrlSetPositionX (_slot * 2);
                _bnt_third ctrlSetPositionW _slot;
                _bnt_third ctrlCommit 0;
            };
        };
        _r
    };
    missionNamespace setVariable ["COMSPEC_ReportsButtonsWrapped", true];
};
