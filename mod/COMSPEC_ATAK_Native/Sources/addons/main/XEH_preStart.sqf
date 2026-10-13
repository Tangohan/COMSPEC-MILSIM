// Lancement du jeu (menu principal, une fois par session) : parcours de config faits ici plutôt qu'à chaque
// arrivée en mission, gardés dans uiNamespace (fn_deviceScan, bouton COMSPEC de l'arsenal ACE).
private _t0 = diag_tickTime;
[["android", "atak", "smartphone"]] call compile preprocessFileLineNumbers "z\comspec_atak_native\addons\main\functions\core\fn_deviceScan.sqf";
uiNamespace setVariable ["COMSPEC_ATAK_ArsenalItems",
    ("getNumber (_x >> 'scope') >= 2 && {((toLower configName _x) find 'comspec_') isEqualTo 0}" configClasses (configFile >> "CfgWeapons")) apply { configName _x }];
diag_log format ["[COMSPEC ATAK NATIVE][PERF] preStart : parcours de config en %1 ms", round ((diag_tickTime - _t0) * 1000)];
