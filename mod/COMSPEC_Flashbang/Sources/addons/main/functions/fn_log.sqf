/*
    comspec_fb_fnc_log : ligne de diagnostic RPT « [COMSPEC FB] étape | message | t= », écrite seulement si le réglage
    « Journal de diagnostic (RPT) » est actif sur CETTE machine, ou pendant un test comspec_fb_fnc_debugTest (les lignes
    sont aussi gardées pour son rapport). Jamais d'affichage à l'écran. Réglage décoché : sortie immédiate.
    Les appelants dont le message coûte à construire testent d'abord comspec_fb_fnc_logOn.
    [_etape, _message] call comspec_fb_fnc_log
*/
params [["_tag", "", [""]], ["_msg", "", [""]]];
if !(call comspec_fb_fnc_logOn) exitWith {};
private _line = format ["[COMSPEC FB] %1 | %2 | t=%3", _tag, _msg, CBA_missionTime toFixed 2];
diag_log text _line;
if (!isNil "comspec_fb_capture") then { comspec_fb_capture pushBack _line };
