/*
    true si ace_dogtags est chargé.
*/
isClass (configFile >> "CfgPatches" >> "ace_dogtags")
    && {!isNil "ace_dogtags_fnc_getDogtagData"}
    && {!isNil "ace_dogtags_fnc_checkDogtag"}
