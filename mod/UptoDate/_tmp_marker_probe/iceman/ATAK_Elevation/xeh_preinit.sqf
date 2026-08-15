#include "script_component.hpp"

["Iceman_ATAK_Elevation_defaultHeightFt", "SLIDER", ["Default View Shed AGL", "Default observer height above ground in feet."], ["Iceman ATAK", "Elevation"], [1, 30, 6, 0]] call CBA_fnc_addSetting;
["Iceman_ATAK_Elevation_defaultRadiusM", "SLIDER", ["Default View Shed Radius", "Default view shed radius in meters."], ["Iceman ATAK", "Elevation"], [100, 3000, 500, 0]] call CBA_fnc_addSetting;
["Iceman_ATAK_Elevation_defaultHeatmapSizeM", "SLIDER", ["Default Heatmap Size", "Default heatmap square size in meters."], ["Iceman ATAK", "Elevation"], [250, 5000, 1000, 0]] call CBA_fnc_addSetting;
["Iceman_ATAK_Elevation_defaultSampleM", "SLIDER", ["Default Heatmap Sample", "Default heatmap sample spacing in meters. Lower values are more detailed and slower."], ["Iceman ATAK", "Elevation"], [25, 250, 80, 0]] call CBA_fnc_addSetting;
["Iceman_ATAK_Elevation_visibleColor", "COLOR", ["View Shed Visible Color", "Overlay color for visible terrain."], ["Iceman ATAK", "Elevation"], [0.05, 1, 0.12, 0.42]] call CBA_fnc_addSetting;
["Iceman_ATAK_Elevation_deadspaceColor", "COLOR", ["View Shed Deadspace Color", "Overlay color for blocked terrain/deadspace."], ["Iceman ATAK", "Elevation"], [1, 0.05, 0.02, 0.35]] call CBA_fnc_addSetting;
