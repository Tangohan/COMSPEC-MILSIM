/*
    Capture une photo de visage pour la fiche SEEK (terminal SEEK ou app SSE de l'ATAK).

    Cadrage portrait de fn_sseFaceFraming (face, trois quarts ou profil, choisi dans le
    viseur du SEEK) : la caméra se place devant le visage, l'interface est masquée,
    le jeu prend une capture d'écran, puis tout est restauré. Le site recadre la capture
    en portrait 3:4 (angle transmis « face@portrait,q=88 »).
    La jointure vers Athena se fait à la transmission (UploadSsePhoto en file).

    Arma ne sait enregistrer une image que par `screenshot` (plein écran, qualité HDR
    au moins Moyen) : il n'existe pas d'export d'une texture r2t.

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

private _face = uiNamespace getVariable ["COMSPEC_SseFace", createHashMap];
// Viseur figé sur un cliché : le bouton REPRENDRE relance le direct avant un nouveau cliché.
if (_face getOrDefault ["frozen", false]) exitWith {
    ["resume"] call comspec_overwatch_connect_fnc_sseFaceViewfinder;
    false
};

if (isNull _target) exitWith {
    ["Aucun sujet : regardez la personne à photographier.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};
if (!isNull player && {(player distance _target) > 4}) exitWith {
    ["Approchez-vous du sujet (4 m au plus) pour photographier son visage.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

private _angle = _face getOrDefault ["angle", "face"];
private _frame = [_target, _angle, _face getOrDefault ["zoom", 1]] call comspec_overwatch_connect_fnc_sseFaceFraming;
if (_frame isEqualTo []) exitWith { false };
// Visage trop endommagé (moteur biométrique) : pas de cliché de comparaison.
if (((_frame select 3) getOrDefault ["qualite", 99]) <= 0 && {!isNil "comspec_sse_fnc_bioCapture"}) exitWith {
    private _why = ((_frame select 3) getOrDefault ["note", ""]);
    [format ["Photo du visage impossible : %1", [_why, "visage trop endommagé"] select (_why isEqualTo "")], "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

missionNamespace setVariable ["COMSPEC_SsePerson_PhotoBusy", true, false];

private _stem = format [
    "COMSPEC_SSE_Face_%1_%2",
    (floor diag_tickTime) toFixed 0,
    (floor random 99999) toFixed 0
];
private _png = _stem + ".png";

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (!isNull _disp) then {
    private _lcd = _disp displayCtrl 9525;
    if (!isNull _lcd) then {
        _lcd ctrlSetStructuredText parseText "<t size='0.52' color='#e0c56e' align='center'>CAPTURE EN COURS</t>";
    };
};

[_target, _png, _frame, _angle] spawn {
    params ["_target", "_png", "_frame", "_angle"];
    _frame params ["_pos", "_look", "_fov", "_info"];

    private _hidden = [];
    private _cam = objNull;
    private _flash = objNull;
    private _prevView = cameraView;
    private _fnc_restore = {
        { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;
        showHUD true;
        if (!isNull _flash) then { deleteVehicle _flash; };
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

    // Tout ce qui pourrait recouvrir la vue : SEEK, téléphone cTab, ATAK natif.
    private _displays = [];
    private _seek = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
    if (isNull _seek) then { _seek = findDisplay 9991; };
    if (!isNull _seek) then { _displays pushBack _seek; };
    private _ctab = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _ctab) then { _ctab = uiNamespace getVariable ["cTab_Tablet_dlg", displayNull]; };
    if (!isNull _ctab) then { _displays pushBackUnique _ctab; };
    if (!isNil "comspec_atak_native_fnc_display") then {
        private _atak = [] call comspec_atak_native_fnc_display;
        if (!isNull _atak) then { _displays pushBackUnique _atak; };
    };
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

    _cam = "camera" camCreate (ASLToAGL _pos);
    if (!isNull _cam) then {
        _cam setPosASL _pos;
        _cam camSetTarget (ASLToAGL _look);
        _cam camSetFov _fov;
        _cam cameraEffect ["Internal", "BACK"];
        _cam camCommit 0;
    };
    // De nuit, le SEEK déclenche son flash.
    if (_info getOrDefault ["nuit", false]) then {
        _flash = "#lightpoint" createVehicleLocal (ASLToAGL _pos);
        _flash setPosASL _pos;
        _flash setLightColor [1, 0.97, 0.92];
        _flash setLightAmbient [0.6, 0.58, 0.55];
        _flash setLightBrightness 0.9;
        _flash setLightAttenuation [0.2, 0, 4, 0];
    };

    uiSleep 0.18;

    private _shotOk = screenshot _png;

    call _fnc_restore;
    missionNamespace setVariable ["COMSPEC_SsePerson_PhotoBusy", false, false];
    if (isNull (uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull])) then {
        ["close"] call comspec_overwatch_connect_fnc_sseFaceStudio;
    };

    if (_shotOk isEqualType true && {!_shotOk}) exitWith {
        [
            "Capture refusée par le jeu — passez la qualité HDR au moins sur Moyen, puis reprenez la photo du visage.",
            "tactical",
            "warn"
        ] call comspec_overwatch_connect_fnc_announce;
        if (!isNil "comspec_overwatch_connect_fnc_ssePersonRefreshPanels") then {
            [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
        };
    };

    // Le cliché remplace le précédent seulement s'il a réussi.
    private _q = _info getOrDefault ["qualite", 0];
    private _st = systemTime;
    private _clock = format ["%1:%2", [str (_st select 3), "0" + str (_st select 3)] select ((_st select 3) < 10), [str (_st select 4), "0" + str (_st select 4)] select ((_st select 4) < 10)];
    private _meta = [_angle, _q, _clock, _info getOrDefault ["note", ""]];
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoPending", true];
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoStem", _png];
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoTakenAt", diag_tickTime];
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoAngle", format ["%1@portrait,q=%2", _angle, _q]];
    uiNamespace setVariable ["COMSPEC_SsePerson_PhotoMeta", _meta];
    if (!isNull _target) then {
        _target setVariable ["comspec_sse_facePhotoStem", _png, false];
        _target setVariable ["comspec_sse_facePhotoMeta", _meta, false];
    };

    // Le viseur garde l'image du cliché.
    if (!isNull (uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull])) then {
        ["freeze"] call comspec_overwatch_connect_fnc_sseFaceViewfinder;
    };
    if (!isNil "comspec_overwatch_connect_fnc_ssePersonRefreshPanels") then {
        [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
    };

    [
        format ["Photo du visage capturée (qualité %1) — elle sera jointe à la transmission de la fiche.", _q],
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
