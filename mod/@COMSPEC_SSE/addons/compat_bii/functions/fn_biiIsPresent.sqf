/*
    True si le PBO BII Identifi est chargé.
*/
isClass (configFile >> "CfgPatches" >> "BII_Identifi")
    || {!isNil "BII_fnc_identifi_processScan"}
    || {isClass (configFile >> "CfgWeapons" >> "BII_Identifi_Device")}
