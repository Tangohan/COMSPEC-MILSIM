/*
    Détecte le mod Workshop F-PANO ECOTI (évite le double affichage).
*/
isClass (configFile >> "CfgPatches" >> "FPANO_ECOTI_PATCH")
    || { !isNil "FPANO_HUD_Active" }
    || { isClass (configFile >> "CfgPatches" >> "FPANO_ECOTI") }
