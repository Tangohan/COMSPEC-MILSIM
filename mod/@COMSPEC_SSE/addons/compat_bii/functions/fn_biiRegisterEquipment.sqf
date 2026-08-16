/*
    Enregistre le BII-10 comme matériel SSE (SEEK / biométrie / terminal).
*/
if !([] call comspec_sse_fnc_biiIsPresent) exitWith { false };

["seek", ["BII_Identifi_Device"]] call comspec_sse_fnc_registerModClasses;
["fingerprint", ["BII_Identifi_Device"]] call comspec_sse_fnc_registerModClasses;
["face", ["BII_Identifi_Device"]] call comspec_sse_fnc_registerModClasses;
["dna", ["BII_Identifi_Device"]] call comspec_sse_fnc_registerModClasses;
["iris", ["BII_Identifi_Device"]] call comspec_sse_fnc_registerModClasses;
["terminal", ["BII_Identifi_Device"]] call comspec_sse_fnc_registerModClasses;
["camera", ["BII_Identifi_Device"]] call comspec_sse_fnc_registerModClasses;

true
