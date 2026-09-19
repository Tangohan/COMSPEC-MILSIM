/*
    Photo Library : TRANSFÉRER / TOUT TRANSFÉRER dans la zone entre la liste et le nom.
    Ne dépend pas du wrap IceMan (souvent compileFinal) : un suivi périodique pose les boutons.
*/
if (!hasInterface) exitWith {};

private _fnc_displayOf = {
    params ["_ctrl"];
    if (isNull _ctrl) exitWith { displayNull };
    private _walk = _ctrl;
    for "_i" from 0 to 12 do {
        private _p = ctrlParent _walk;
        if (isNull _p) exitWith {};
        _walk = _p;
    };
    _walk
};

private _fnc_ensureButtons = {
    params ["_grp"];
    if (isNull _grp) exitWith { [controlNull, controlNull] };

    private _b1 = uiNamespace getVariable ["COMSPEC_PhotoXferBtn", controlNull];
    private _b2 = uiNamespace getVariable ["COMSPEC_PhotoXferAllBtn", controlNull];
    if (
        !isNull _b1
        && {!isNull _b2}
        && {ctrlParentControlsGroup _b1 isEqualTo _grp}
        && {ctrlShown _b1 || {ctrlShown _b2}}
    ) exitWith { [_b1, _b2] };

    private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _disp) then { _disp = uiNamespace getVariable ["cTab_Android_dsp", displayNull]; };
    if (isNull _disp) then { _disp = [_grp] call (missionNamespace getVariable ["COMSPEC_PhotoXferDisplayOf", {}]); };
    if (isNull _disp) exitWith { [controlNull, controlNull] };

    if (!isNull _b1) then { ctrlDelete _b1; };
    if (!isNull _b2) then { ctrlDelete _b2; };

    {
        if (!isNull _b1 && {!isNull _b2}) then { continue };
        private _cls = _x;
        if (isNull _b1) then { _b1 = _disp ctrlCreate [_cls, 9460, _grp]; };
        if (isNull _b2) then { _b2 = _disp ctrlCreate [_cls, 9461, _grp]; };
    } forEach ["RscButton", "RscButtonMenu", "BCE_RscButtonMenu"];

    if (!isNull _b1) then {
        _b1 ctrlSetText "TRANSFÉRER";
        _b1 ctrlSetTooltip "Envoyer la photo sélectionnée vers le poste, puis la retirer de cette bibliothèque";
        _b1 ctrlSetTextColor [1, 1, 1, 1];
        _b1 ctrlSetBackgroundColor [0.08, 0.42, 0.52, 1];
        _b1 ctrlSetEventHandler ["ButtonClick", "[false] call comspec_overwatch_atak_athena_fnc_athena_sendLibraryPhoto; true"];
        uiNamespace setVariable ["COMSPEC_PhotoXferBtn", _b1];
    };
    if (!isNull _b2) then {
        _b2 ctrlSetText "TOUT TRANSFÉRER";
        _b2 ctrlSetTooltip "Envoyer toutes vos photos locales vers le poste, puis les retirer de cette bibliothèque";
        _b2 ctrlSetTextColor [1, 1, 1, 1];
        _b2 ctrlSetBackgroundColor [0.08, 0.32, 0.40, 1];
        _b2 ctrlSetEventHandler ["ButtonClick", "[true] call comspec_overwatch_atak_athena_fnc_athena_sendLibraryPhoto; true"];
        uiNamespace setVariable ["COMSPEC_PhotoXferAllBtn", _b2];
    };
    [_b1, _b2]
};

private _fnc_decorate = {
    if (isNil "Iceman_fnc_photo_findControls") exitWith { false };
    private _controls = [] call Iceman_fnc_photo_findControls;
    if (!(_controls isEqualType createHashMap)) exitWith { false };

    private _grp = uiNamespace getVariable ["Iceman_ATAK_PhotoLibrary_group", controlNull];
    if (isNull _grp) exitWith { false };
    if !(ctrlShown _grp) exitWith { false };

    private _btns = [_grp] call (missionNamespace getVariable ["COMSPEC_PhotoLibraryEnsureBtns", {}]);
    if (!(_btns isEqualType []) || {(count _btns) < 2}) exitWith { false };
    _btns params ["_b1", "_b2"];

    private _expanded = missionNamespace getVariable ["Iceman_PhotoLibrary_expanded", false];
    private _preview = _controls getOrDefault ["9420", controlNull];
    private _list = _controls getOrDefault ["9410", controlNull];
    private _meta = _controls getOrDefault ["9421", controlNull];
    private _ref = _controls getOrDefault ["9440", controlNull];
    private _send = _controls getOrDefault ["9442", controlNull];

    private _px = 0;
    private _py = 0;
    private _pw = 0;
    private _ph = 0;
    if (!isNull _list && {!isNull _meta}) then {
        (ctrlPosition _list) params ["_lx", "_ly", "_lw", "_lh"];
        (ctrlPosition _meta) params ["_mx", "_my", "_mw", "_mh"];
        _px = _lx;
        _py = _ly + _lh + 0.004;
        _pw = _lw;
        _ph = (_my - _py - 0.004) max 0.04;
    };
    if (_pw < 0.02 && {!isNull _preview} && {!_expanded}) then {
        (ctrlPosition _preview) params ["_x", "_y", "_w", "_h"];
        _px = _x; _py = _y; _pw = _w; _ph = _h;
    };

    if (!isNull _preview && {!_expanded}) then {
        _preview ctrlShow false;
        _preview ctrlEnable false;
    };

    private _refH = 0.058;
    if (!isNull _ref) then { _refH = ((ctrlPosition _ref) select 3); };
    private _gap = _refH * 0.16;
    private _stackH = (_refH * 2) + _gap;
    if (_stackH > (_ph - 0.008)) then {
        _refH = ((_ph - _gap - 0.008) / 2) max 0.032;
        _stackH = (_refH * 2) + _gap;
    };
    private _y0 = _py + (((_ph - _stackH) / 2) max 0);

    private _haveCreate = !isNull _b1 && {!isNull _b2};
    {
        _x params ["_ctrl"];
        if (!isNull _ctrl) then {
            _ctrl ctrlShow (!_expanded && {_haveCreate});
            _ctrl ctrlEnable (!_expanded && {_haveCreate});
        };
    } forEach [[_b1], [_b2]];

    if (!_expanded && {_haveCreate} && {_pw > 0.02}) then {
        _b1 ctrlSetPosition [_px, _y0, _pw, _refH];
        _b1 ctrlCommit 0;
        _b2 ctrlSetPosition [_px, _y0 + _refH + _gap, _pw, _refH];
        _b2 ctrlCommit 0;
    };

    // Secours : si la création a échoué, le Send IceMan envoie vers le poste.
    if (!_haveCreate && {!isNull _send} && {!_expanded}) then {
        _send ctrlSetText "TRANSFÉRER";
        _send ctrlSetTooltip "Envoyer la photo sélectionnée vers le poste, puis la retirer de cette bibliothèque";
        _send ctrlSetEventHandler ["ButtonClick", "[false] call comspec_overwatch_atak_athena_fnc_athena_sendLibraryPhoto; true"];
    };
    true
};
missionNamespace setVariable ["COMSPEC_PhotoXferDisplayOf", _fnc_displayOf, false];
missionNamespace setVariable ["COMSPEC_PhotoLibraryEnsureBtns", _fnc_ensureButtons, false];
missionNamespace setVariable ["COMSPEC_PhotoLibraryDecorate", _fnc_decorate, false];

private _wrap = {
    if (!isNil "COMSPEC_PhotoLibraryAthenaWrapped") exitWith { true };
    if (isNil "Iceman_fnc_photo_refresh") exitWith { false };
    if (isFinal Iceman_fnc_photo_refresh) exitWith { false };

    COMSPEC_PhotoLibraryAthenaWrapped = true;
    missionNamespace setVariable ["COMSPEC_PhotoRefreshOrig", Iceman_fnc_photo_refresh];
    Iceman_fnc_photo_refresh = {
        private _orig = missionNamespace getVariable ["COMSPEC_PhotoRefreshOrig", {}];
        private _ok = [] call _orig;
        if (!(_ok isEqualType true)) then { _ok = true; };
        [] call (missionNamespace getVariable ["COMSPEC_PhotoLibraryDecorate", {}]);
        _ok
    };
    if (!isNil "Iceman_fnc_photo_onOpened" && {!isFinal Iceman_fnc_photo_onOpened}) then {
        missionNamespace setVariable ["COMSPEC_PhotoOnOpenedOrig", Iceman_fnc_photo_onOpened];
        Iceman_fnc_photo_onOpened = {
            private _orig = missionNamespace getVariable ["COMSPEC_PhotoOnOpenedOrig", {}];
            private _ret = _this call _orig;
            [] call (missionNamespace getVariable ["COMSPEC_PhotoLibraryDecorate", {}]);
            _ret
        };
    };
    if (!isNil "Iceman_fnc_photo_applyLayout" && {!isFinal Iceman_fnc_photo_applyLayout}) then {
        missionNamespace setVariable ["COMSPEC_PhotoApplyLayoutOrig", Iceman_fnc_photo_applyLayout];
        Iceman_fnc_photo_applyLayout = {
            private _orig = missionNamespace getVariable ["COMSPEC_PhotoApplyLayoutOrig", {}];
            private _ok = [] call _orig;
            if (!(_ok isEqualType true)) then { _ok = true; };
            [] call (missionNamespace getVariable ["COMSPEC_PhotoLibraryDecorate", {}]);
            _ok
        };
    };
    true
};

[] call _wrap;
{ [_wrap, [], _x] call CBA_fnc_waitAndExecute; } forEach [2, 6, 12, 20, 40];

if (isNil "COMSPEC_PhotoXferPfh") then {
    COMSPEC_PhotoXferPfh = [{
        private _grp = uiNamespace getVariable ["Iceman_ATAK_PhotoLibrary_group", controlNull];
        if (isNull _grp) exitWith {};
        if !(ctrlShown _grp) exitWith {};
        [] call (missionNamespace getVariable ["COMSPEC_PhotoLibraryDecorate", {}]);
    }, 0.35] call CBA_fnc_addPerFrameHandler;
};
