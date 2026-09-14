/*
    Remplace BCE_fnc_ATAK_TakePicture.

    Le cliché reste celui de BCE (extension de capture du pack). C’est ce
    cliché qui part aussi vers Discord. Ne pas changer la caméra ni prendre
    un second screenshot Arma autour de cet appel : ça coupe l’envoi Discord.
    Le dossier annoncé à Discord est le dossier Screenshot réel du pack.
*/
private _display = uiNamespace getVariable ["BCE_PhoneCAM_View", displayNull];
if (isNull _display) then {
    _display = uiNamespace getVariable ["BCE_HCAM_View", displayNull];
};
if (isNull _display) exitWith {};

private _grid = _display displayCtrl 55;
if (!isNull _grid) then {
    _grid ctrlSetBackgroundColor [0, 0, 0, 0.3];
    if (!isNil "BCE_fnc_POS2Grid") then {
        _grid ctrlSetText format ["GRID :%1", [getPosVisual player, 10] call BCE_fnc_POS2Grid];
    };
};

private _ctrls = (allControls _display) apply {
    if (50 > ctrlIDC _x) then {
        _x ctrlShow false;
        _x
    } else {
        controlNull
    };
};

[{
    params ["_ctrls", "_grid"];

    private _screenshot = [];
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_bceScreenShot") then {
        _screenshot = [] call comspec_overwatch_atak_athena_fnc_athena_bceScreenShot;
    } else {
        if (!isNil "BCE_fnc_screenShot") then {
            _screenshot = [] call BCE_fnc_screenShot;
        };
    };
    if (!(_screenshot isEqualType [])) then { _screenshot = []; };

    {
        if (isNull _x) then { continue };
        _x ctrlShow true;
    } forEach _ctrls;

    if (!isNull _grid) then {
        _grid ctrlSetBackgroundColor [0, 0, 0, 0];
        _grid ctrlSetText "";
    };

    if (_screenshot isEqualTo []) exitWith {};

    playSound3D ["\z\BCE\addons\Core\sound\CameraShutter.wss", player, false, getPosASL player, 3, 1, 15];

    ["bce_took_screenshot", _screenshot] call CBA_fnc_localEvent;
}, [_ctrls, _grid], 0.2] call CBA_fnc_waitAndExecute;
