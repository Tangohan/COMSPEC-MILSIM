/*
    Overlay développeur : FPS UI, âge PLI, nb contrôles, markers, DLL.
    Visible seulement si COMSPEC_MapDebug = true (CBA / mission).
*/
params ["_disp", "_mapCtrl", "_vis"];
if (!(missionNamespace getVariable ["COMSPEC_MapDebug", false])) exitWith {
    private _c = _disp displayCtrl 88540;
    if (!isNull _c) then { _c ctrlShow false };
};
_vis params ["_vx", "_vy", "_vw", "_vh"];
private _c = _disp displayCtrl 88540;
if (isNull _c) then { _c = _disp ctrlCreate ["RscStructuredText", 88540]; };
private _nCtrl = 0;
{
    _x params ["_a", "_b"];
    for "_i" from _a to _b do {
        if (!isNull (_disp displayCtrl _i)) then { _nCtrl = _nCtrl + 1 };
    };
} forEach [[88540, 88559], [88600, 88650], [88700, 88700], [88800, 88815], [88900, 88924]];
private _age = time - (player getVariable ["COMSPEC_PliAt", time]);
private _dll = missionNamespace getVariable ["COMSPEC_MapDebugDll", ""];
if ((time - (missionNamespace getVariable ["COMSPEC_MapDebugDllAt", -1e9])) > 5) then {
    _dll = "COMSPECExtension" callExtension "Ping";
    missionNamespace setVariable ["COMSPEC_MapDebugDll", _dll, false];
    missionNamespace setVariable ["COMSPEC_MapDebugDllAt", time, false];
};
private _html = format [
    "<t font='EtelkaMonospacePro' size='0.48' color='#7CFF9A'>" +
    "FPS %1  PLI %2s  CTRL %3  MK %4  READY %5  %6</t>",
    round diag_fps,
    round _age,
    _nCtrl,
    count allMapMarkers,
    missionNamespace getVariable ["COMSPEC_AthenaReady", false],
    _dll
];
_c ctrlSetPosition [_vx + (_vw * 0.012), _vy + _vh - (_vh * 0.045), (_vw * 0.5) min 0.32, (_vh * 0.04) max 0.018];
_c ctrlSetBackgroundColor [0, 0, 0, 0.55];
_c ctrlSetStructuredText parseText _html;
_c ctrlEnable false;
_c ctrlShow true;
_c ctrlCommit 0;
