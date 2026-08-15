params [["_target", objNull]];

if (isNull _target) exitWith {[] call Iceman_fnc_toc_normalizeSettings};

[_target getVariable ["Iceman_TOC_settings", []]] call Iceman_fnc_toc_normalizeSettings
