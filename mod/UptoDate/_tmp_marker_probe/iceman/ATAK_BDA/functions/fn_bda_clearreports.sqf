#include "..\script_component.hpp"

Iceman_ATAK_BDA_reports = [];
call Iceman_fnc_bda_updatePanel;

["BDA", "BDA reports cleared locally.", 3] call cTab_fnc_addNotification;
