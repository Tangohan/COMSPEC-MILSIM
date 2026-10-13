/*
    Joint la photo du visage à une fiche personne déjà enregistrée sur Athena (file asynchrone
    de COMSPEC Link : la DLL attend le PNG hors du fil du jeu). Appelé à la transmission de la
    fiche (fn_ssePersonDialogSubmit) et dès la prise du cliché quand le sujet a déjà une fiche
    (fn_sseCaptureFacePhoto) : la photo remonte sans retransmettre la fiche.

    Params : [personId, stem, indicatif, posX, posY, posZ, carroyage, angle, sujet]
*/
params [
    ["_pid", ""], ["_stem", ""], ["_cs", ""],
    ["_px", "0"], ["_py", "0"], ["_pz", "0"], ["_gridRef", ""],
    ["_angle", "face"], ["_target", objNull]
];
if (_pid isEqualTo "" || {_stem isEqualTo ""}) exitWith { false };
["UploadSsePhoto", "attempt", format ["personne %1 stem=%2", _pid, _stem], nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
[_pid, _stem, _cs, _px, _py, _pz, _gridRef, _angle, _target] spawn {
    params ["_pid", "_stem", "_cs", "_px", "_py", "_pz", "_gridRef", "_angle", "_target"];
    private _stem0 = _stem;
    uiSleep 0.35;
    if (!isNil "comspec_overwatch_connect_fnc_extResult") then {
        private _staged = ["COMSPECExtension" callExtension ["StageCapture", [_stem]]] call comspec_overwatch_connect_fnc_extResult;
        if ((_staged isEqualType "") && {(count _staged) >= 4} && {(_staged select [0, 3]) isEqualTo "OK|"}) then {
            private _body = trim (_staged select [3, (count _staged) - 3]);
            if (_body isNotEqualTo "") then { _stem = _body; };
        };
    };
    private _shot = [
        "COMSPECExtension" callExtension ["UploadSsePhoto", [
            _pid,
            _stem,
            _cs,
            _angle,
            _px,
            _py,
            _pz,
            "Photo du visage",
            _gridRef
        ]]
    ] call comspec_overwatch_connect_fnc_extResult;
    private _parsed = [_shot] call comspec_overwatch_connect_fnc_parseAtakExtResponse;
    _parsed params ["_ok", "_status", "_detail"];
    private _u = toUpper (str _shot);
    if (!_ok && { ((_u find "OK|QUEUED") == 0) || {(_u find "OK|DUPLICATE") == 0} }) then { _ok = true; };
    if (!_ok) then {
        ["UploadSsePhoto", "fail", _shot, _shot, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        private _why = if ((toLower (str _shot)) find "not_connected" >= 0) then {
            "COMSPEC Link n'est pas connecté à Athena"
        } else { str _shot };
        [
            format ["La photo du visage n'a pas pu partir (%1) — refaites PHOTO DU VISAGE une fois la liaison revenue.", _why],
            "tactical",
            "warn"
        ] call comspec_overwatch_connect_fnc_announce;
    } else {
        ["UploadSsePhoto", "ok", format ["personne %1 · %2", _pid, _detail], nil, true, "system"] call comspec_overwatch_connect_fnc_logTransmission;
        // Cliché parti : il ne repartira pas à chaque réouverture du terminal sur ce sujet.
        if (!isNull _target) then { _target setVariable ["comspec_sse_facePhotoSentStem", _stem0]; };
    };
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoPending", false];
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoStem", ""];
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoAngle", "face"];
};
true
