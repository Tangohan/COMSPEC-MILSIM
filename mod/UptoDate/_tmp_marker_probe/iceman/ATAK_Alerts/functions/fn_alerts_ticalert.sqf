#include "..\script_component.hpp"

params [["_isAlert", true]];

if (!isNil "Iceman_ATAK_Alerts_originalTicAlert") then {
    _this call Iceman_ATAK_Alerts_originalTicAlert;
};

if (_isAlert) then {
    ["TIC", format ["Troops in contact reported by %1.", name player], getPosASL player] call Iceman_fnc_alerts_send;
} else {
    ["TIC_CLEAR", format ["TIC cleared by %1.", name player], getPosASL player] call Iceman_fnc_alerts_send;
};

true
