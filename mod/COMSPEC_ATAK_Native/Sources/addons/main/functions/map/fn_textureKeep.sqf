/*
    Garde chargées les icônes PAA du téléphone (apps, outils carte, interface, logo ATAK).
    Les pages sont recréées à chaque rafraîchissement (pageClear puis ctrlCreate) : quand plus aucun contrôle ne
    référence une texture, Arma peut la décharger, et au rafraîchissement suivant l'icône restait vide le temps de la
    recharger (icônes qui « dépopent », logo ATAK de l'app Athena). Une image minuscule hors écran par texture, sur
    l'écran de mission (display 46, vivant toute la partie), garde une référence permanente.
    Sans paramètre. Appelé une fois au démarrage (XEH_postInitClient), sans effet s'il a déjà tourné sur ce display 46.
*/
disableSerialization;
private _d = findDisplay 46;
if (isNull _d) exitWith { false };
if ((_d getVariable ["COMSPEC_ATAK_TexKeep", 0]) > 0) exitWith { true };
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _tex = ("true" configClasses (configFile >> "COMSPEC_ATAK_Apps")) apply { getText (_x >> "icon") };
{ _tex pushBack (_dir + _x + ".paa"); } forEach [
    "logo_atak", "compass_ring", "compass_needle",
    "map_center", "map_clear", "map_compass", "map_distance", "map_flat", "map_follow", "map_grid", "map_height", "map_house",
    "map_labels", "map_los", "map_marker", "map_measure", "map_route", "map_tools", "map_zoomin", "map_zoomout",
    "nav_arrive", "nav_left", "nav_right", "nav_slight_left", "nav_slight_right", "nav_straight", "nav_uturn",
    "ui_apps", "ui_back", "ui_bell", "ui_camera", "ui_check", "ui_clip", "ui_close", "ui_collapse", "ui_comspec_link", "ui_disc",
    "ui_expand", "ui_folder", "ui_gallery", "ui_gps", "ui_home", "ui_link", "ui_minus", "ui_photocam", "ui_rotate", "ui_send",
    "ui_vibrate", "ui_weather", "ui_wind", "sig_0", "sig_1", "sig_2", "sig_3", "sig_4", "bat_10", "bat_25", "bat_50", "bat_75", "bat_100"
];
_tex = (_tex select { _x isNotEqualTo "" }) arrayIntersect (_tex select { _x isNotEqualTo "" });
{
    private _c = _d ctrlCreate ["RscPicture", -1];
    _c ctrlSetPosition [safeZoneX - 1, safeZoneY - 1, 0.001, 0.001];
    _c ctrlSetText _x;
    _c ctrlSetTextColor [1, 1, 1, 0.01];
    _c ctrlEnable false;
    _c ctrlCommit 0;
} forEach _tex;
_d setVariable ["COMSPEC_ATAK_TexKeep", count _tex];
true
