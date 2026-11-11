/*
    comspec_fb_fnc_log : trace RPT (diag_log) uniquement si le réglage « Journal de débogage » est actif. Jamais d'affichage.
    [_etape, _message] call comspec_fb_fnc_log
*/
params [["_tag", "", [""]], ["_msg", "", [""]]];
if !(missionNamespace getVariable ["comspec_fb_debug", false]) exitWith {};
diag_log text format ["COMSPEC_FB|%1|%2|t=%3", _tag, _msg, CBA_missionTime toFixed 2];
