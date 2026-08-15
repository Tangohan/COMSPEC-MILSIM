private _display = uiNamespace getVariable ["Iceman_TOC_display", displayNull];
private _target = uiNamespace getVariable ["Iceman_TOC_target", objNull];
if (isNull _display || {isNull _target}) exitWith {};

private _feedList = _display displayCtrl 94101;
private _idx = lbCurSel _feedList;
if (_idx < 0) exitWith {
    ["Pick a feed first."] call Iceman_fnc_toc_setStatus;
};

private _feed = call compile (_feedList lbData _idx);
private _settings = call Iceman_fnc_toc_readDialog;

_target setVariable ["Iceman_TOC_feed", _feed, true];
_target setVariable ["Iceman_TOC_settings", _settings, true];

[ _target, _feed, _settings ] call Iceman_fnc_toc_syncStreamGlobal;
["Stream applied to this screen."] call Iceman_fnc_toc_setStatus;
