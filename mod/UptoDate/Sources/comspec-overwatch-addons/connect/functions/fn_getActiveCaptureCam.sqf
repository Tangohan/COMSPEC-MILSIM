/*
    Caméra réellement regardée (overlay ATAK, casque, tourelle, TGP), pas le soldat.

    Retour: [cam, host, rtt, kind]
    kind: "" | "phone" | "hcam" | "hcam_pip" | "uav_pip" | "tgp"
*/
if (!hasInterface) exitWith { [objNull, objNull, "", ""] };

private _stored = localNamespace getVariable ["COMSPEC_OverlayCaptureCam", objNull];
if (!(_stored isEqualType objNull)) then { _stored = objNull; };

private _phoneOn = !isNull (uiNamespace getVariable ["BCE_PhoneCAM_View", displayNull]);
private _hcamOn = !isNull (uiNamespace getVariable ["BCE_HCAM_View", displayNull]);

if (!_phoneOn && {!_hcamOn} && {!isNull _stored}) then {
    localNamespace setVariable ["COMSPEC_OverlayCaptureCam", objNull];
    localNamespace setVariable ["COMSPEC_OverlayCaptureKind", ""];
    _stored = objNull;
};

if ((_phoneOn || {_hcamOn}) && {!isNull _stored}) exitWith {
    private _host = attachedTo _stored;
    if (isNull _host) then { _host = cameraOn; };
    private _kind = localNamespace getVariable ["COMSPEC_OverlayCaptureKind", ""];
    if (!(_kind isEqualType "") || {_kind isEqualTo ""}) then {
        _kind = ["phone", "hcam"] select _hcamOn;
    };
    [_stored, _host, "", _kind]
};

if (_phoneOn || {_hcamOn}) exitWith {
    private _host = objNull;
    private _kind = "phone";
    if (_hcamOn && {!isNil "cTabHcams"} && {cTabHcams isEqualType []} && {(count cTabHcams) > 1}) then {
        _host = cTabHcams param [1, objNull];
        _kind = "hcam";
    };
    if (isNull _host) then { _host = focusOn; };
    if (isNull _host) then { _host = player; };
    private _found = objNull;
    {
        if (!isNull _x && {attachedTo _x isEqualTo _host}) exitWith { _found = _x; };
    } forEach (allMissionObjects "camera");
    [_found, _host, "", _kind]
};

private _tgp = missionNamespace getVariable ["TGP_View_Camera", []];
if ((_tgp isEqualType []) && {(count _tgp) > 0}) exitWith {
    private _cam = _tgp select 0;
    if (!(_cam isEqualType objNull) || {isNull _cam}) then {
        [objNull, objNull, "", ""]
    } else {
        [_cam, cameraOn, "", "tgp"]
    }
};

if (!isNil "cTabHcams" && {cTabHcams isEqualType []} && {(count cTabHcams) > 0}) exitWith {
    private _cam = cTabHcams param [0, objNull];
    private _host = cTabHcams param [1, objNull];
    if (!(_cam isEqualType objNull) || {isNull _cam}) then {
        [objNull, objNull, "", ""]
    } else {
        [_cam, _host, "rendertarget9", "hcam_pip"]
    }
};

if (!isNil "cTabUAVcams" && {cTabUAVcams isEqualType []} && {(count cTabUAVcams) > 0}) exitWith {
    private _entry = cTabUAVcams select 0;
    if (!(_entry isEqualType []) || {(count _entry) < 2}) then {
        [objNull, objNull, "", ""]
    } else {
        private _cam = _entry param [1, objNull];
        private _veh = _entry param [0, objNull];
        if (!(_cam isEqualType objNull) || {isNull _cam}) then {
            [objNull, objNull, "", ""]
        } else {
            [_cam, _veh, "rendertarget9", "uav_pip"]
        }
    }
};

[objNull, objNull, "", ""]
