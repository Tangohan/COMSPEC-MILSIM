/*
    Version affichable du mod Overwatch (CfgPatches versionStr, repli 1.1.0).
*/
private _v = getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr");
if (_v isEqualTo "") then {
    _v = getText (configFile >> "CfgPatches" >> "comspec_overwatch_main" >> "versionStr");
};
if (_v isEqualTo "") then { _v = "1.1.0"; };
_v
