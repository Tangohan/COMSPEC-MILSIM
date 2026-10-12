/*
    Portée de la liaison drone en mètres, sans le relief : réglage admin comspec_atak_native_drone_range (6000 m par
    défaut), ou la portée maximale du mod Mavic (mavic_setting_maxConnectionDistance) si elle est plus grande.
    Utilisé par fn_droneAction (_link) et fn_droneFleet (_linkOf).
*/
private _r = missionNamespace getVariable ["comspec_atak_native_drone_range", 6000];
if !(_r isEqualType 0) then { _r = 6000; };
private _m = missionNamespace getVariable ["mavic_setting_maxConnectionDistance", 0];
if (_m isEqualType 0) then { _r = _r max _m; };
_r
