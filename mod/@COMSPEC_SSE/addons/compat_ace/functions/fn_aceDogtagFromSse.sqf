/*
    Construit les données plaque ACE [nom, code, groupe sanguin] depuis l’identité SSE.
    [_target] call comspec_sse_fnc_aceDogtagFromSse
    Retourne [] si pas de SSE utilisable.
*/
params [
    ["_target", objNull, [objNull]]
];

if (isNull _target) exitWith { [] };
if !(_target isKindOf "CAManBase") exitWith { [] };

private _data = [_target] call comspec_sse_fnc_getData;
if (isNil "_data") exitWith { [] };

private _identity = [_target, "identity"] call comspec_sse_fnc_getSection;
if (isNil "_identity" || {!(_identity isEqualType createHashMap)}) exitWith { [] };

private _name = _identity getOrDefault ["name", ""];
if (!(_name isEqualType "") || {_name isEqualTo ""}) exitWith { [] };

private _code = _identity getOrDefault ["idCode", ""];
if (!(_code isEqualType "") || {_code isEqualTo ""}) then {
    private _uid = [_data, "uid", ""] call comspec_sse_fnc_getPair;
    if (_uid isEqualType "" && {_uid isNotEqualTo ""}) then {
        // Format proche d’un SSN ACE (xxx-xx-xxxx) à partir de l’UID SSE
        private _h = [0, format ["%1|dogtag", _uid]] call comspec_sse_fnc_hash;
        private _a = (_h mod 900) + 100;
        private _b = ((_h / 1000) mod 90) + 10;
        private _c = ((_h / 100000) mod 9000) + 1000;
        _code = format ["%1-%2-%3", floor _a, floor _b, floor _c];
    } else {
        private _h = [0, format ["%1|%2", netId _target, _name]] call comspec_sse_fnc_hash;
        _code = format ["%1-%2-%3", 100 + (_h mod 800), 10 + ((_h / 10) mod 80), 1000 + ((_h / 100) mod 8000)];
    };
};

private _blood = _identity getOrDefault ["bloodType", ""];
if (!(_blood isEqualType "") || {_blood isEqualTo ""}) then {
    private _types = ["O POS", "O NEG", "A POS", "A NEG", "B POS", "B NEG", "AB POS", "AB NEG"];
    private _h = [0, format ["blood|%1", _name]] call comspec_sse_fnc_hash;
    _blood = _types select (_h mod (count _types));
};

[_name, _code, _blood]
