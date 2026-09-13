/*
    Version du pack Overwatch lue dans le manifeste, pour Athena et le pied de fenêtre.
*/
private _v = getText (configFile >> "CfgPatches" >> "comspec_overwatch_connect" >> "versionStr");
if (_v isEqualTo "") then { "1.5.0" } else { _v };
