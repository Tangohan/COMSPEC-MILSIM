/*
    Viseur du visage sur l'écran du SEEK (page PHOTO).

    Une caméra rendue dans une texture (r2t « comspecseekface ») suit la tête du sujet
    avec le cadrage de fn_sseFaceFraming : l'opérateur voit ce qui sera photographié.
    Après le cliché, la caméra est coupée : l'écran garde la dernière image.

    Params : [action, argument]
      "start"   lance le viseur (sujet de la fiche, sinon la personne visée)
      "stop"    coupe le viseur (changement de page, fermeture du terminal)
      "reset"   coupe et oublie le cliché affiché (nouvelle fiche)
      "freeze"  fige l'image après un cliché        "resume"  relance le direct
      "angle"   face → trois quarts → profil        "zoom" ±1 : x1 / x1.4 / x2 / x2.5
      "frame"   appelé à chaque image par le viseur
*/
params [["_act", "start", [""]], ["_arg", 0]];
if (!hasInterface) exitWith { false };

private _st = uiNamespace getVariable ["COMSPEC_SseFace", createHashMap];
uiNamespace setVariable ["COMSPEC_SseFace", _st];
private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
private _zooms = [1, 1.4, 2, 2.5];
private _angles = ["face", "trois_quarts", "profil"];

private _target = {
    private _t = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
    if (isNull _t) then {
        private _c = cursorObject;
        if (!isNull _c && {_c isKindOf "CAManBase"} && {_c != player} && {(player distance _c) < 6}) then { _t = _c; };
    };
    _t
};

private _kill = {
    private _pfh = _st getOrDefault ["pfh", -1];
    if (_pfh >= 0) then { [_pfh] call CBA_fnc_removePerFrameHandler; };
    _st set ["pfh", -1];
    private _cam = _st getOrDefault ["cam", objNull];
    if (!isNull _cam) then {
        _cam cameraEffect ["Terminate", "BACK", "comspecseekface"];
        camDestroy _cam;
    };
    _st set ["cam", objNull];
    // Le double du studio n'a plus lieu d'être sans viseur.
    ["close"] call comspec_overwatch_connect_fnc_sseFaceStudio;
};

private _paint = {
    if (isNull _disp) exitWith {};
    private _frozen = _st getOrDefault ["frozen", false];
    private _live = !isNull (_st getOrDefault ["cam", objNull]);
    (_disp displayCtrl 9575) ctrlShow ((_live || _frozen) && {(uiNamespace getVariable ["COMSPEC_SsePerson_Page", 0]) isEqualTo 5});
    (_disp displayCtrl 9574) ctrlShow (!(_live || _frozen) && {(uiNamespace getVariable ["COMSPEC_SsePerson_Page", 0]) isEqualTo 5});
    (_disp displayCtrl 9578) ctrlSetText (switch (_st getOrDefault ["angle", "face"]) do {
        case "trois_quarts": { "3/4" };
        case "profil": { "PROFIL" };
        default { "FACE" };
    });
    (_disp displayCtrl 9515) ctrlSetText (["PRENDRE LE CLICHÉ", "REPRENDRE"] select _frozen);
};

switch (_act) do {
    case "start": {
        if (isNull _disp) exitWith {};
        if (_st getOrDefault ["frozen", false]) exitWith { call _paint; [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels; };
        if (!isNull (_st getOrDefault ["cam", objNull])) exitWith { call _paint; };
        private _t = call _target;
        _st set ["target", _t];
        if (isNull _t) exitWith { call _paint; [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels; };
        private _fr = [_t, _st getOrDefault ["angle", "face"], _st getOrDefault ["zoom", 1]] call comspec_overwatch_connect_fnc_sseFaceFraming;
        if (_fr isEqualTo []) exitWith { call _paint; };
        _fr params ["_pos", "_look", "_fov"];
        private _cam = "camera" camCreate (ASLToAGL _pos);
        _cam setPosASL _pos;
        _cam camSetTarget (ASLToAGL _look);
        _cam camSetFov _fov;
        _cam cameraEffect ["Internal", "BACK", "comspecseekface"];
        _cam camCommit 0;
        _st set ["cam", _cam];
        _st set ["pfh", [{ ["frame"] call comspec_overwatch_connect_fnc_sseFaceViewfinder; }, 0] call CBA_fnc_addPerFrameHandler];
        call _paint;
    };
    case "frame": {
        private _cam = _st getOrDefault ["cam", objNull];
        private _t = _st getOrDefault ["target", objNull];
        if (isNull _disp || {isNull _cam} || {isNull _t} || {(uiNamespace getVariable ["COMSPEC_SsePerson_Page", 0]) isNotEqualTo 5}) exitWith {
            call _kill;
            call _paint;
        };
        private _fr = [_t, _st getOrDefault ["angle", "face"], _st getOrDefault ["zoom", 1]] call comspec_overwatch_connect_fnc_sseFaceFraming;
        if (_fr isEqualTo []) exitWith {};
        _fr params ["_pos", "_look", "_fov", "_info"];
        _cam setPosASL _pos;
        _cam camSetTarget (ASLToAGL _look);
        _cam camSetFov _fov;
        _cam camCommit 0;
        _st set ["info", _info];
        if (diag_tickTime >= (_st getOrDefault ["nextInfo", 0])) then {
            _st set ["nextInfo", diag_tickTime + 0.3];
            [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
        };
    };
    case "stop": { call _kill; call _paint; };
    case "reset": {
        call _kill;
        { _st set _x; } forEach [["frozen", false], ["target", objNull], ["info", createHashMap], ["angle", "face"], ["zoom", 1]];
    };
    case "freeze": {
        call _kill;
        _st set ["frozen", true];
        call _paint;
    };
    case "resume": {
        _st set ["frozen", false];
        ["start"] call comspec_overwatch_connect_fnc_sseFaceViewfinder;
    };
    case "angle": {
        private _i = _angles find (_st getOrDefault ["angle", "face"]);
        _st set ["angle", _angles select ((_i + 1) mod (count _angles))];
        if (_st getOrDefault ["frozen", false]) then { ["resume"] call comspec_overwatch_connect_fnc_sseFaceViewfinder; } else { call _paint; };
        [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
    };
    case "zoom": {
        private _i = _zooms find (_st getOrDefault ["zoom", 1]);
        if (_i < 0) then { _i = 0; };
        _i = ((_i + ([-1, 1] select (_arg > 0))) max 0) min ((count _zooms) - 1);
        _st set ["zoom", _zooms select _i];
        if (_st getOrDefault ["frozen", false]) then { ["resume"] call comspec_overwatch_connect_fnc_sseFaceViewfinder; };
        [] call comspec_overwatch_connect_fnc_ssePersonRefreshPanels;
    };
};
true
