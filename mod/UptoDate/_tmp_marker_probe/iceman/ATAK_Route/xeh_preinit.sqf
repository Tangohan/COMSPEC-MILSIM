#include "script_component.hpp"

["Iceman_ATAK_Route_showMiniInfo", "CHECKBOX", ["Show Minimized Route Info", "Show distance, remaining distance, and ETA on the minimized ATAK screen."], ["Iceman ATAK", "Route"], true] call CBA_fnc_addSetting;
["Iceman_ATAK_Route_lineWidth", "SLIDER", ["Route Line Width", "Visual width of route lines on the ATAK map."], ["Iceman ATAK", "Route"], [1, 8, 3, 0]] call CBA_fnc_addSetting;
["Iceman_ATAK_Route_vehicleColor", "COLOR", ["Vehicle Route Color", "Color used for vehicle route lines."], ["Iceman ATAK", "Route"], [0, 0.95, 1, 1]] call CBA_fnc_addSetting;
["Iceman_ATAK_Route_footColor", "COLOR", ["Foot Route Color", "Color used for foot route lines."], ["Iceman ATAK", "Route"], [0.1, 0.8, 1, 0.95]] call CBA_fnc_addSetting;
["Iceman_ATAK_Route_turnColor", "COLOR", ["Vehicle Turn Color", "Color used for vehicle road corner highlights."], ["Iceman ATAK", "Route"], [1, 0.9, 0.05, 1]] call CBA_fnc_addSetting;
["Iceman_ATAK_Route_footMinSpeedKph", "SLIDER", ["Foot ETA Minimum Speed", "Minimum speed used for foot ETA when the player is stationary."], ["Iceman ATAK", "Route"], [1, 12, 4.5, 1]] call CBA_fnc_addSetting;
["Iceman_ATAK_Route_vehicleMinSpeedKph", "SLIDER", ["Vehicle ETA Minimum Speed", "Minimum speed used for vehicle ETA when the vehicle is stationary."], ["Iceman ATAK", "Route"], [1, 40, 5, 1]] call CBA_fnc_addSetting;
["Iceman_ATAK_Route_footNodeLimit", "SLIDER", ["Foot Route Search Budget", "Higher values can find better concealed routes but may take longer."], ["Iceman ATAK", "Route"], [500, 6000, 2800, 0]] call CBA_fnc_addSetting;
["Iceman_ATAK_Route_vehicleRoadCheckM", "SLIDER", ["Vehicle Road Check Spacing", "Spacing in meters for vehicle route road snapping checks."], ["Iceman ATAK", "Route"], [5, 25, 8, 0]] call CBA_fnc_addSetting;
