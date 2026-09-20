/*
    A3TI / fusion thermique Workshop déjà chargé : Overwatch ne simule pas le calque chaleur.
*/
isClass (configFile >> "CfgPatches" >> "A3TI")
    || { isClass (configFile >> "CfgPatches" >> "A3TI_main") }
    || { isClass (configFile >> "CfgPatches" >> "A3TI_Fusion") }
    || { !isNil "A3TI_Enabled" }
