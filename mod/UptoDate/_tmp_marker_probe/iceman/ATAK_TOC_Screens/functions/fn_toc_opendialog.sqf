params [["_target", objNull], ["_player", player]];

if (isNull _target) exitWith {};

uiNamespace setVariable ["Iceman_TOC_target", _target];
createDialog "Iceman_TOC_ScreenDialog";
