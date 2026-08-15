params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["Iceman_ATAK_Weather_group", _group];
private _token = diag_tickTime;
uiNamespace setVariable ["Iceman_ATAK_Weather_token", _token];
call Iceman_fnc_weather_updatePanel;

[_token] spawn {
    params ["_token"];
    while {(uiNamespace getVariable ["Iceman_ATAK_Weather_token", -1]) == _token} do {
        uiSleep 5;
        private _group = uiNamespace getVariable ["Iceman_ATAK_Weather_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {};
        call Iceman_fnc_weather_updatePanel;
    };
};
