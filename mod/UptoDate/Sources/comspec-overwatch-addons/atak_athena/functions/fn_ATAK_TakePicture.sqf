/*
    Remplace BCE_fnc_ATAK_TakePicture.

    Avant le cliché : la caméra overlay / casque / tourelle devient la vue scène,
    sinon l’image envoyée au poste est celle du soldat.
*/
private _display = uiNamespace getVariable ["BCE_PhoneCAM_View", displayNull];
if (isNull _display) then {
    _display = uiNamespace getVariable ["BCE_HCAM_View", displayNull];
};
if (isNull _display) exitWith {};

private _grid = _display displayCtrl 55;
_grid ctrlSetBackgroundColor [0, 0, 0, 0.3];
_grid ctrlSetText format ["GRID :%1", [getPosVisual player, 10] call BCE_fnc_POS2Grid];

private _ctrls = (allControls _display) apply {
    if (50 > ctrlIDC _x) then {
        _x ctrlShow false;
        _x
    } else {
        controlNull
    };
};

if (!isNil "comspec_overwatch_connect_fnc_promoteCaptureCam") then {
    [false] call comspec_overwatch_connect_fnc_promoteCaptureCam;
};

[{
    params ["_ctrls", "_grid"];

    private _restore = [];
    if (!isNil "comspec_overwatch_connect_fnc_promoteCaptureCam") then {
        _restore = [true] call comspec_overwatch_connect_fnc_promoteCaptureCam;
        if (!(_restore isEqualType [])) then { _restore = []; };
    };

    private _screenshot = [] call BCE_fnc_screenShot;

    if ((count _restore) >= 3 && {!isNil "comspec_overwatch_connect_fnc_restoreCaptureCam"}) then {
        _restore call comspec_overwatch_connect_fnc_restoreCaptureCam;
    };

    {
        if (isNull _x) then { continue };
        _x ctrlShow true;
    } forEach _ctrls;

    _grid ctrlSetBackgroundColor [0, 0, 0, 0];
    _grid ctrlSetText "";

    if (_screenshot isEqualTo []) exitWith {};

    playSound3D ["\z\BCE\addons\Core\sound\CameraShutter.wss", player, false, getPosASL player, 3, 1, 15];

    ["bce_took_screenshot", _screenshot] call CBA_fnc_localEvent;
}, [_ctrls, _grid], 0.2] call CBA_fnc_waitAndExecute;
