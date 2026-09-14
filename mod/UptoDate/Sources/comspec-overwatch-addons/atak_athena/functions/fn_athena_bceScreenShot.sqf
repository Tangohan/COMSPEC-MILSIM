/*
    Cliché BCE avec un dossier disque réel.

    L’extension de capture du pack annonce parfois un dossier collé
    (« Arma 3 » + « !Workshop » + nom du mod, sans séparateurs). Discord
    ouvre ce chemin et échoue, alors que la photo est bien enregistrée.
    On passe un dossier existant, puis on répare le chemin renvoyé.
*/
params [["_fileName", ""]];

if (missionNamespace getVariable ["COMSPEC_BCE_ScreenShotInHelper", false]) exitWith { [] };

private _orig = missionNamespace getVariable ["COMSPEC_BCE_screenShotOrig", nil];
if (isNil "_orig") then {
    if (isNil "BCE_fnc_screenShot") exitWith { [] };
    _orig = BCE_fnc_screenShot;
};
if (!(_orig isEqualType {})) exitWith { [] };

private _repair = {
    params ["_p"];
    if (!(_p isEqualType "") || {_p isEqualTo ""}) exitWith { _p };
    private _out = _p;
    private _i = _out find "Arma 3!Workshop";
    if (_i < 0) then { _i = _out find "Arma 3!workshop"; };
    if (_i >= 0) then {
        _out = (_out select [0, _i + 6]) + "\\" + (_out select [_i + 6, count _out]);
    };
    private _j = _out find "!Workshop@";
    if (_j < 0) then { _j = _out find "!workshop@"; };
    if (_j >= 0) then {
        _out = (_out select [0, _j + 9]) + "\\" + (_out select [_j + 9, count _out]);
    };
    _out
};

private _custom = "";
if (!isNil "BCE_PicFilePath_edit" && {BCE_PicFilePath_edit isEqualType ""}) then {
    _custom = BCE_PicFilePath_edit;
};
if (
    (_custom find "Arma 3!Workshop") >= 0
    || {(_custom find "Arma 3!workshop") >= 0}
    || {(_custom find "!Workshop@") >= 0}
    || {(_custom find "!workshop@") >= 0}
) then {
    _custom = "";
};

private _dir = "";
if (_fileName isEqualTo "" && {_custom isEqualTo ""}) then {
    if (!isNil "comspec_overwatch_connect_fnc_extResult") then {
        private _raw = ["COMSPECExtension" callExtension ["GetBceScreenshotDir", []]] call comspec_overwatch_connect_fnc_extResult;
        if (_raw isEqualType "" && {(_raw select [0, 3]) isEqualTo "OK|"}) then {
            _dir = trim (_raw select [3, (count _raw) - 3]);
        };
    };
};

private _stem = _fileName;
if (_stem isEqualTo "" && {_dir isNotEqualTo ""} && {(_dir find ":") >= 0}) then {
    private _time = systemTime apply { (["", "0"] select (_x < 10)) + (str _x) };
    _time resize 6;
    private _base = _dir;
    while { (count _base) > 0 && {(_base select [(count _base) - 1, 1]) isEqualTo "\\"} } do {
        _base = _base select [0, (count _base) - 1];
    };
    _stem = format ["%1\\%2", _base, _time joinString "_"];
};

missionNamespace setVariable ["COMSPEC_BCE_ScreenShotInHelper", true, false];
private _screenshot = if (_stem isEqualTo "") then {
    [] call _orig
} else {
    [_stem] call _orig
};
missionNamespace setVariable ["COMSPEC_BCE_ScreenShotInHelper", false, false];
if (!(_screenshot isEqualType []) || {_screenshot isEqualTo []}) exitWith { _screenshot };

private _ret = _screenshot select 0;
private _file = if ((count _screenshot) > 1) then { _screenshot select 1 } else { "" };
if (_ret isEqualType "") then { _ret = [_ret] call _repair; };
if (_file isEqualType "") then { _file = [_file] call _repair; };

private _leaf = _file;
if (_leaf isEqualType "" && {_leaf isNotEqualTo ""}) then {
    if ((_leaf find "\\") >= 0 || {(_leaf find "/") >= 0}) then {
        private _segs = _leaf splitString "\/";
        _leaf = _segs select ((count _segs) - 1);
    };
};

private _dirOut = _ret;
if (_dirOut isEqualType "" && {_dirOut isNotEqualTo ""}) then {
    private _low = toLower _dirOut;
    if ((_low find ".jpg") >= 0 || {(_low find ".jpeg") >= 0} || {(_low find ".png") >= 0}) then {
        private _segs = _dirOut splitString "\/";
        if ((count _segs) > 1) then {
            if (_leaf isEqualTo "") then { _leaf = _segs select ((count _segs) - 1); };
            _segs deleteAt ((count _segs) - 1);
            _dirOut = _segs joinString "\\";
        };
    };
};

if (_dirOut isEqualType "" && {_leaf isEqualType ""}) then {
    [_dirOut, _leaf]
} else {
    _screenshot
};
