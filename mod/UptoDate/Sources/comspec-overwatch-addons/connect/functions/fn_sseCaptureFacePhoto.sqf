/*
    Capture une photo de visage pour la fiche SEEK.
    Cache le terminal, cadre la tête de la cible, cliche, restaure l’interface.
    La jointure vers Athena se fait à la transmission (UploadSsePhoto en file).

    Params optionnels: [target]
*/
params [["_target", objNull, [objNull]]];
if (!hasInterface) exitWith { false };

if (isNull _target) then {
    _target = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
};
if (isNull _target) then {
    private _cursor = cursorObject;
    if (!isNull _cursor && {_cursor isKindOf "CAManBase"} && {_cursor != player}) then {
        _target = _cursor;
    };
};

if (missionNamespace getVariable ["COMSPEC_SsePerson_PhotoBusy", false]) exitWith {
    ["Capture du visage déjà en cours.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    false
};

missionNamespace setVariable ["COMSPEC_SsePerson_PhotoBusy", true, false];

private _stem = format [
    "COMSPEC_SSE_Face_%1_%2",
    (floor diag_tickTime) toFixed 0,
    (floor random 99999) toFixed 0
];
private _png = _stem + ".png";
uiNamespace setVariable ["COMSPEC_SsePerson_PhotoPending", true];
uiNamespace setVariable ["COMSPEC_SsePerson_PhotoStem", _png];
uiNamespace setVariable ["COMSPEC_SsePerson_PhotoTakenAt", diag_tickTime];
if (!isNull _target) then {
    _target setVariable ["comspec_sse_facePhotoStem", _png, false];
};

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (!isNull _disp) then {
    private _lcd = _disp displayCtrl 9525;
    if (!isNull _lcd) then {
        _lcd ctrlSetStructuredText parseText "<t size='0.38' color='#e0c56e' align='center'>CAPTURE EN COURS</t>";
    };
};

[_target, _png] spawn {
    params ["_target", "_png"];

    private _hidden = [];
    private _cam = objNull;
    private _prevView = cameraView;
    private _fnc_restore = {
        { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;
        showHUD true;
        if (!isNull _cam) then {
            _cam cameraEffect ["Terminate", "BACK"];
            camDestroy _cam;
        };
        if (_prevView isEqualType "" && {_prevView isNotEqualTo ""}) then {
            if (!isNull player) then { player switchCamera _prevView; };
        };
    };

    if (!isNil "ace_interact_menu_fnc_hideMenu") then {
        [] call ace_interact_menu_fnc_hideMenu;
    };

    private _displays = [];
    private _seek = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
    if (isNull _seek) then { _seek = findDisplay 9991; };
    if (!isNull _seek) then { _displays pushBack _seek; };
    private _ctab = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _ctab) then { _ctab = uiNamespace getVariable ["cTab_Tablet_dlg", displayNull]; };
    if (!isNull _ctab) then { _displays pushBackUnique _ctab; };
    {
        {
            if (ctrlShown _x) then {
                _hidden pushBack _x;
                _x ctrlShow false;
            };
        } forEach (allControls _x);
    } forEach _displays;
    showHUD false;

    if (!isNull player) then {
        player switchCamera "INTERNAL";
    };

    if (!isNull _target && {(_target isKindOf "CAManBase")}) then {
        private _sel = _target selectionPosition "head";
        if (_sel isEqualTo [0, 0, 0]) then { _sel = _target selectionPosition "pilot"; };
        private _headAGL = _target modelToWorldVisual _sel;
        if (_headAGL isEqualTo [0, 0, 0]) then {
            _headAGL = ASLToAGL ((getPosASL _target) vectorAdd [0, 0, 0.4]);
        };
        private _headASL = AGLToASL _headAGL;
        private _eyeASL = if (!isNull player) then { eyePos player } else { _headASL vectorAdd [0, -0.8, 0.1] };
        private _delta = _headASL vectorDiff _eyeASL;
        private _dist = vectorMagnitude _delta;
        private _dir = [0, 1, 0];
        if (_dist < 0.2) then {
            _dir = _target vectorModelToWorld [0, 1, 0];
            private _m = vectorMagnitude _dir;
            if (_m > 0.01) then { _dir = _dir vectorMultiply (1 / _m); } else { _dir = [0, 1, 0]; };
        } else {
            _dir = _delta vectorMultiply (1 / _dist);
        };
        private _camASL = _headASL vectorAdd (_dir vectorMultiply -0.62);
        _camASL set [2, (_headASL select 2) + 0.04];

        _cam = "camera" camCreate (ASLToAGL _camASL);
        if (!isNull _cam) then {
            _cam setPosASL _camASL;
            _cam camSetTarget _headAGL;
            _cam camSetFov 0.42;
            _cam cameraEffect ["Internal", "BACK"];
            _cam camCommit 0;
        };
    };

    uiSleep 0.18;

    private _shotOk = screenshot _png;

    call _fnc_restore;
    missionNamespace setVariable ["COMSPEC_SsePerson_PhotoBusy", false, false];

    if (_shotOk isEqualType true && {!_shotOk}) exitWith {
        uiNamespace setVariable ["COMSPEC_SsePerson_PhotoPending", false];
        uiNamespace setVariable ["COMSPEC_SsePerson_PhotoStem", ""];
        if (!isNull _target) then {
            _target setVariable ["comspec_sse_facePhotoStem", nil, false];
        };
        [
            "Capture refusée par le jeu — passez la qualité HDR au moins sur Moyen, puis reprenez la photo du visage.",
            "tactical",
            "warn"
        ] call comspec_overwatch_connect_fnc_announce;
        private _d = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
        if (!isNull _d && {!isNil "comspec_overwatch_connect_fnc_ssePersonRefreshPanels"}) then {
            [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
        };
    };

    if (!isNil "comspec_overwatch_connect_fnc_ssePersonRefreshPanels") then {
        [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
    };

    [
        "Photo du visage capturée — elle sera jointe à la transmission de la fiche.",
        "tactical",
        "info"
    ] call comspec_overwatch_connect_fnc_announce;

    // Recopie hors fil jeu dans Documents\Arma 3 - COMSPEC\Captures (même dossier que le téléphone).
    [_png] spawn {
        params ["_hint"];
        uiSleep 2.2;
        if (isNil "comspec_overwatch_connect_fnc_extResult") exitWith {};
        ["COMSPECExtension" callExtension ["StageCapture", [_hint]]] call comspec_overwatch_connect_fnc_extResult;
    };
};

true
