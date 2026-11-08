params [["_entity",createHashMap]]; private _aff=_entity getOrDefault ["affiliation","unknown"]; private _type=_entity getOrDefault ["type","infantry"];
// Couleur de l'affiliation (OTAN / APP-6) : table de la carte (fn_mapColors), teinte adaptée au fond et au mode nuit.
private _pal=[] call comspec_atak_native_fnc_mapColors; private _color=+(_pal getOrDefault [["unknown",_aff] select (_aff in ["friend","hostile","neutral"]),_pal get "unknown"]);
// À bord d'un engin (fn_localDataRefresh « vtype ») : icône de véhicule, d'avion ou de char.
private _vt = _entity getOrDefault ["vtype", ""]; if (_vt isNotEqualTo "") then { _type = _vt; };
private _icon=switch _type do {case "air":{"\A3\ui_f\data\map\vehicleicons\iconAir_ca.paa"};case "armor":{"\A3\ui_f\data\map\vehicleicons\iconTank_ca.paa"};case "vehicle":{"\A3\ui_f\data\map\vehicleicons\iconCar_ca.paa"};case "medical":{"\A3\ui_f\data\map\mapcontrol\hospital_ca.paa"};case "objective":{"\A3\ui_f\data\map\markers\military\objective_CA.paa"};default {"\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa"};}; [_icon,_color]
