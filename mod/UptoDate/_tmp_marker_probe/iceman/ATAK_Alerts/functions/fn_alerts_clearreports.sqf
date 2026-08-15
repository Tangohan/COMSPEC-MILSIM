#include "..\script_component.hpp"

Iceman_ATAK_Reports_reports = [];
Iceman_ATAK_Alerts_reports = [];
Iceman_ATAK_BDA_reports = [];
Iceman_ATAK_Reports_selected = -1;
call Iceman_fnc_alerts_updatePanel;

["REPORTS", "Reports cleared locally.", 3] call cTab_fnc_addNotification;
