#include "..\script_component.hpp"

params [["_tab", "inbox"]];

Iceman_ATAK_Reports_tab = _tab;
call Iceman_fnc_alerts_updatePanel;
