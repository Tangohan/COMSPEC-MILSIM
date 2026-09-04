if (!hasInterface) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_ATAK_CameraShotBusy", false]) exitWith { false };

if (!(missionNamespace getVariable ["COMSPEC_ATAK_CameraOpen", false])) then
{
    [] call COMSPEC_fnc_cameraOpen;
};

missionNamespace setVariable ["COMSPEC_ATAK_CameraShotBusy", true, false];

[] spawn
{
    uiSleep 0.28;

    private _hideChrome = {
        private _disp = findDisplay 88510;
        if (!isNull _disp) then
        {
            { (_disp displayCtrl _x) ctrlShow false; } forEach [1201, 1202, 1203, 1210, 1214];
        };
    };

    private _showChrome = {
        private _disp = findDisplay 88510;
        if (!isNull _disp) then
        {
            { (_disp displayCtrl _x) ctrlShow true; } forEach [1201, 1202, 1203, 1210, 1214];
        };
    };

    call _hideChrome;
    uiSleep 0.12;

    private _stem = format ["COMSPEC_%1_%2", (floor diag_tickTime) toFixed 0, (floor random 99999) toFixed 0];
    private _png = _stem + ".png";
    private _path = _png;
    private _usedBce = false;

    if (!isNil "BCE_fnc_screenShot") then
    {
        private _bce = [_stem] call BCE_fnc_screenShot;
        if ((_bce isEqualType []) && {(count _bce) >= 1}) then
        {
            private _full = _bce select 0;
            if ((_full isEqualType "") && {_full isNotEqualTo ""}) then
            {
                _path = _full;
                _usedBce = true;
            };
        };
    };

    if (!_usedBce) then
    {
        screenshot _png;
        uiSleep 0.85;
    };

    call _showChrome;

    private _snap = [] call COMSPEC_fnc_playerSnapshot;
    private _author = _snap getOrDefault ["callsign", name player];
    if (_author isEqualTo "") then { _author = name player; };
    private _pos = getPosASL player;
    private _grid = mapGridPosition player;
    private _dir = getDir player;
    private _sideStr = switch (side player) do
    {
        case east: { "EAST" };
        case independent: { "GUER" };
        case civilian: { "CIV" };
        default { "WEST" };
    };

    private _staged = ["StageCapture", [_path]] call COMSPEC_fnc_extensionCall;
    if ((_staged find "OK|") isEqualTo 0) then
    {
        _path = trim (_staged select [3, (count _staged) - 3]);
    }
    else
    {
        if (!_usedBce) then
        {
            uiSleep 0.9;
            _staged = ["StageCapture", [_png]] call COMSPEC_fnc_extensionCall;
            if ((_staged find "OK|") isEqualTo 0) then
            {
                _path = trim (_staged select [3, (count _staged) - 3]);
            };
        };
    };

    private _queued = ["NotifyNewPhoto", [
        _path,
        _author,
        str (_pos select 0),
        str (_pos select 1),
        str (_pos select 2),
        _grid,
        str _dir,
        str (_pos select 2),
        "Cliché ATAK",
        name player,
        _sideStr,
        missionName,
        "PHONE",
        str (floor time),
        "",
        "",
        "0"
    ]] call COMSPEC_fnc_extensionCall;

    private _txt = toUpper (trim _queued);
    private _msg = "Le poste n’a pas reçu le cliché. Réessayez.";
    private _level = "ERR";

    if ((_txt find "OK|QUEUED") isEqualTo 0 || {_txt isEqualTo "OK"} || {(_txt find "OK|IGNORED") isEqualTo 0}) then
    {
        _msg = "Cliché envoyé au poste.";
        _level = "OK";
    }
    else
    {
        if ((_txt find "NOT_CONNECTED") >= 0) then
        {
            _msg = "Connectez-vous au poste pour envoyer le cliché.";
            _level = "WARN";
        }
        else
        {
            if ((_txt find "OK|DUPLICATE") isEqualTo 0) then
            {
                _msg = "Ce cliché est déjà parti.";
                _level = "INFO";
            };
        };
    };

    hint _msg;
    missionNamespace setVariable ["COMSPEC_ATAK_PendingToast", [_msg, _level], false];
    missionNamespace setVariable ["COMSPEC_ATAK_CameraShotBusy", false, false];
};

true
