/*
    Retourne une chaîne "radioType|frequency|channel" pour cache / comparaison.
    TFAR: TFAR_fnc_activeSwRadio, TFAR_fnc_currentSwFrequency
    ACRE: acre_api_fnc_getCurrentRadioList
*/
params ["_unit"];
private _out = "N/A|N/A|N/A";

if (isClass (configFile >> "CfgPatches" >> "tfar_core")) then {
    private _radio = _unit call TFAR_fnc_activeSwRadio;
    if (!isNil "_radio" && {_radio != ""}) then {
        private _freq = _unit call TFAR_fnc_getCurrentSwFrequency;
        private _freqStr = if (!isNil "_freq") then { str _freq } else { "N/A" };
        _out = "TFAR|" + _freqStr + "|0";
    };
} else {
    if (isClass (configFile >> "CfgPatches" >> "acre_main")) then {
        if (!isNil "acre_api_fnc_getCurrentRadioList") then {
            private _radios = [] call acre_api_fnc_getCurrentRadioList;
            if (count _radios > 0) then {
                private _r = _radios select 0;
                private _freq = "N/A";
                private _ch = "0";
                if (!isNil "acre_api_fnc_getRadioChannel") then {
                    _ch = str ([_r] call acre_api_fnc_getRadioChannel);
                };
                if (!isNil "acre_api_fnc_getChannelData") then {
                    private _data = [_r, ([_r] call acre_api_fnc_getRadioChannel)] call acre_api_fnc_getChannelData;
                    if (!isNil "_data" && {count _data > 0}) then { _freq = str (_data select 0); };
                };
                _out = "ACRE|" + _freq + "|" + _ch;
            };
        };
    };
};

_out
