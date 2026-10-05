/*
    Catalogue des équipes de feu : couleurs, icônes et rôles. Renvoie un HashMap :
      colors : [[clé, libellé, "#RRGGBB", [r,g,b,a], couleur d'équipe Arma]...]
               Les cinq couleurs d'Arma (blanc, rouge, vert, bleu, jaune) sont aussi appliquées à l'équipe du jeu
               (assignTeam : barre d'escouade, carte d'Arma, ACE) ; les autres restent propres au téléphone.
      icons  : [[clé, libellé, texture]...]
      roles  : [[clé, libellé, abrégé, texture]...]  CDE = chef d'équipe (un seul par équipe).
*/
private _c = missionNamespace getVariable "COMSPEC_ATAK_FtCatalog";
if (!isNil "_c") exitWith { _c };
private _hex = {
    params ["_h"];
    private _d = "0123456789ABCDEF";
    private _v = toUpper (_h select [1, 6]);
    private _b = { params ["_i"]; ((_d find (_v select [_i, 1])) * 16 + (_d find (_v select [_i + 1, 1]))) / 255 };
    [[0] call _b, [2] call _b, [4] call _b, 1]
};
private _colors = [
    ["RED", "Rouge", "#E5483A", "RED"], ["BLUE", "Bleu", "#3B8BEB", "BLUE"], ["GREEN", "Vert", "#4CC261", "GREEN"],
    ["YELLOW", "Jaune", "#E8C547", "YELLOW"], ["WHITE", "Blanc", "#E6ECE8", "MAIN"], ["ORANGE", "Orange", "#F08A2C", "MAIN"],
    ["PURPLE", "Violet", "#9A6BE0", "MAIN"], ["CYAN", "Cyan", "#3FC6D6", "MAIN"], ["PINK", "Rose", "#E66BB0", "MAIN"], ["OLIVE", "Olive", "#9AA64A", "MAIN"]
] apply { _x params ["_k", "_l", "_h", "_t"]; [_k, _l, _h, [_h] call _hex, _t] };
private _m = "\A3\ui_f\data\map\markers\military\";
private _n = "\A3\ui_f\data\map\markers\nato\";
private _icons = [
    ["INF", "Infanterie", _n + "b_inf.paa"], ["RECON", "Reconnaissance", _n + "b_recon.paa"], ["MED", "Médical", _n + "b_med.paa"],
    ["SUPPORT", "Appui", _n + "b_support.paa"], ["MORTAR", "Mortier", _n + "b_mortar.paa"], ["UAV", "Drone", _n + "b_uav.paa"],
    ["AIR", "Aérien", _n + "b_air.paa"], ["MECH", "Mécanisé", _n + "b_mech_inf.paa"], ["HQ", "Commandement", _n + "b_hq.paa"],
    ["DOT", "Point", _m + "dot_CA.paa"], ["TRIANGLE", "Triangle", _m + "triangle_CA.paa"], ["BOX", "Carré", _m + "box_CA.paa"],
    ["CIRCLE", "Cercle", _m + "circle_CA.paa"], ["FLAG", "Drapeau", _m + "flag_CA.paa"], ["ARROW", "Flèche", _m + "arrow_CA.paa"],
    ["OBJ", "Objectif", _m + "objective_CA.paa"], ["WARN", "Attention", _m + "warning_CA.paa"], ["JOIN", "Jonction", _m + "join_CA.paa"]
];
private _roles = [
    ["CDE", "Chef d'équipe", "CDE", _n + "b_hq.paa"],
    ["FUS", "Fusilier", "FUS", "\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa"],
    ["GRE", "Grenadier", "GRE", "\A3\ui_f\data\map\vehicleicons\iconManLeader_ca.paa"],
    ["FM", "Fusilier-mitrailleur", "FM", "\A3\ui_f\data\map\vehicleicons\iconManMG_ca.paa"],
    ["TE", "Tireur d'élite", "TE", "\A3\ui_f\data\map\vehicleicons\iconManRecon_ca.paa"],
    ["AT", "Antichar", "AT", "\A3\ui_f\data\map\vehicleicons\iconManAT_ca.paa"],
    ["MED", "Auxiliaire sanitaire", "SAN", "\A3\ui_f\data\map\vehicleicons\iconManMedic_ca.paa"],
    ["RAD", "Opérateur radio", "RAD", "\A3\ui_f\data\map\vehicleicons\iconManCommander_ca.paa"],
    ["ENG", "Sapeur", "SAP", "\A3\ui_f\data\map\vehicleicons\iconManEngineer_ca.paa"],
    ["EOD", "Démineur (EOD)", "EOD", "\A3\ui_f\data\map\vehicleicons\iconManExplosive_ca.paa"],
    ["JTAC", "JTAC", "JTAC", _n + "b_art.paa"],
    ["DRN", "Télépilote drone", "DRN", _n + "b_uav.paa"],
    ["PIL", "Pilote", "PIL", "\A3\ui_f\data\map\vehicleicons\iconAir_ca.paa"],
    ["CPL", "Copilote / mitrailleur de bord", "CPL", "\A3\ui_f\data\map\vehicleicons\iconAir_ca.paa"],
    ["DRV", "Conducteur", "CON", "\A3\ui_f\data\map\vehicleicons\iconCar_ca.paa"]
];
_c = createHashMapFromArray [["colors", _colors], ["icons", _icons], ["roles", _roles]];
missionNamespace setVariable ["COMSPEC_ATAK_FtCatalog", _c];
_c
