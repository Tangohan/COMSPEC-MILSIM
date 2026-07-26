/*
    Parse le retour extension ATAK (FormatAtakExtArray : ["STATUS","detail"] ou OK|detail).
    Params: [_raw] — retour brut callExtension (array Arma 2.18+ ou string)
    Retour: [_ok, _status, _detail]
*/
params [["_raw", ""]];

private _text = [_raw] call comspec_overwatch_connect_fnc_extResult;
private _status = "";
private _detail = "";

if (_text isEqualType "" && {(_text select [0, 1]) isEqualTo "["}) then {
    private _parsed = parseSimpleArray _text;
    if ((_parsed isEqualType []) && {(count _parsed) > 0}) then {
        private _s0 = _parsed select 0;
        _status = if (_s0 isEqualType "") then { _s0 } else { str _s0 };
        if ((count _parsed) > 1) then {
            private _s1 = _parsed select 1;
            _detail = if (_s1 isEqualType "") then { _s1 } else { str _s1 };
        };
    };
} else {
    if (_text isEqualType "" && {_text != ""}) then {
        private _parts = _text splitString "|";
        _status = if ((count _parts) > 0) then { _parts select 0 } else { "" };
        _detail = if ((count _parts) > 1) then { (_parts select [1, (count _parts) - 1]) joinString "|" } else { _text };
    };
};

private _ok = (toUpper _status) isEqualTo "OK";
[_ok, _status, _detail]
