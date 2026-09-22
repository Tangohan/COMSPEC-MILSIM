/*
    Panneau des couches : bouton « Couches » toujours visible sur la carte du téléphone.
    Cases locales — ne change pas les modules Zeus. IDC 88619–88639.
*/
params ["_disp", "_mapCtrl", "_vis"];
if (isNull _disp) exitWith {};
if (!(_vis isEqualType []) || {(count _vis) < 4}) exitWith {};
_vis params ["_vx", "_vy", "_vw", "_vh"];
if (!(_vw isEqualType 0) || {!(_vh isEqualType 0)}) exitWith {};
if (_vw <= 0 || {_vh <= 0}) exitWith {};

private _fncEnsure = {
    params ["_d", "_idc", "_class"];
    if (isNull _d) exitWith { controlNull };
    private _c = _d displayCtrl _idc;
    if (isNull _c) then { _c = _d ctrlCreate [_class, _idc]; };
    _c
};

private _layers = missionNamespace getVariable ["COMSPEC_MapLayers", createHashMap];
if (!(_layers isEqualType createHashMap)) then { _layers = createHashMap; };

private _labels = [
    ["enemy_ai", "Contacts ennemis"],
    ["units", "Alliés suivis"],
    ["relays", "Relais"],
    ["network_zones", "Zones réseau"],
    ["sigint", "Rapports SIGINT"],
    ["recon_notes", "Notes reco"],
    ["vehicles", "Véhicules"],
    ["objectives", "Objectifs"],
    ["player_markers", "Marqueurs"],
    ["athena", "Athena"],
    ["intel", "Intel"],
    ["photos", "Photos"],
    ["jtac", "JTAC"],
    ["cas", "CAS"],
    ["logistics", "Logistique"]
];

private _show = (missionNamespace getVariable ["COMSPEC_MapActiveTool", ""]) isEqualTo "layers";
private _btnW = (_vw * 0.078) max 0.042;
private _btnH = (_vh * 0.046) max 0.022;
private _bx = _vx + _vw - _btnW - (_vw * 0.012);
private _by = _vy + (_vh * 0.12);

private _toggle = [_disp, 88619, "RscButton"] call _fncEnsure;
if (!isNull _toggle) then {
    _toggle ctrlSetPosition [_bx, _by, _btnW, _btnH];
    _toggle ctrlSetText "Couches";
    _toggle ctrlSetFont "RobotoCondensedBold";
    _toggle ctrlSetFontHeight (_btnH * 0.42);
    _toggle ctrlSetBackgroundColor (if (_show) then { [0.18, 0.42, 0.52, 1] } else { [0.12, 0.12, 0.12, 0.94] });
    _toggle ctrlSetTextColor [0.95, 0.96, 0.97, 1];
    _toggle ctrlShow true;
    _toggle ctrlEnable true;
    _toggle ctrlCommit 0;
    if (isNil {_toggle getVariable "COMSPEC_LayerToggleWired"}) then {
        _toggle setVariable ["COMSPEC_LayerToggleWired", true];
        _toggle ctrlAddEventHandler ["ButtonClick", {
            ["layers"] call comspec_overwatch_atak_athena_fnc_setActiveTool;
        }];
    };
};

private _nLab = count _labels;
private _pw = (_vw * 0.34) min 0.22;
private _ph = ((_vh * 0.58) min (0.028 * (_nLab + 2))) max 0.08;
private _px = _vx + _vw - _pw - (_vw * 0.012);
private _py = _by + _btnH + 0.006;

private _panel = [_disp, 88620, "RscStructuredText"] call _fncEnsure;
if (isNull _panel) exitWith {};
_panel ctrlSetPosition [_px, _py, _pw, _ph];
_panel ctrlSetBackgroundColor [0.06, 0.06, 0.06, 0.92];
private _html = "<t font='RobotoCondensedBold' size='0.58' color='#5EC7F2'>Couches</t><br/>";
{
    _x params ["_key", "_lab"];
    private _on = _layers getOrDefault [_key, true];
    _html = _html + format [
        "<t size='0.52' color='%1'>[%2]</t> <t size='0.52' color='#E8F0F4'>%3</t><br/>",
        ["#8aa0b4", "#7CFF9A"] select _on,
        [" ", "x"] select _on,
        _lab
    ];
} forEach _labels;
_panel ctrlSetStructuredText parseText _html;
_panel ctrlEnable false;
_panel ctrlShow _show;
_panel ctrlCommit 0;

private _n = 0;
{
    _x params ["_key", "_lab"];
    private _idc = 88621 + _n;
    private _b = [_disp, _idc, "RscButton"] call _fncEnsure;
    if (isNull _b) then { continue };
    private _rowH = (_ph / (_nLab + 1.4)) max 0.002;
    _b ctrlSetPosition [_px + 0.004, _py + (_rowH * (_n + 1.15)), _pw - 0.008, _rowH * 0.88];
    _b ctrlSetText "";
    _b ctrlSetBackgroundColor [0, 0, 0, 0.05];
    _b ctrlShow _show;
    _b ctrlEnable _show;
    _b ctrlCommit 0;
    if (isNil {_b getVariable "COMSPEC_LayerWired"}) then {
        _b setVariable ["COMSPEC_LayerWired", true];
        _b setVariable ["COMSPEC_LayerKey", _key];
        _b ctrlAddEventHandler ["ButtonClick", {
            params ["_ctrl"];
            private _k = _ctrl getVariable ["COMSPEC_LayerKey", ""];
            private _ly = missionNamespace getVariable ["COMSPEC_MapLayers", createHashMap];
            if (!(_ly isEqualType createHashMap)) then { _ly = createHashMap; };
            _ly set [_k, !(_ly getOrDefault [_k, true])];
            missionNamespace setVariable ["COMSPEC_MapLayers", _ly, false];
            profileNamespace setVariable ["COMSPEC_MapLayersPairs", _ly toArray];
            saveProfileNamespace;
            [] call comspec_overwatch_atak_athena_fnc_applyMapLayers;
        }];
    } else {
        _b setVariable ["COMSPEC_LayerKey", _key];
    };
    _n = _n + 1;
} forEach _labels;
