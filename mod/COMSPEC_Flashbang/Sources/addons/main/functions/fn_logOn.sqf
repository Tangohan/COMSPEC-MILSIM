/*
    comspec_fb_fnc_logOn : le journal de diagnostic écrit-il ? (réglage « Journal de diagnostic (RPT) », actif par
    défaut ; lignes de détail seulement avec « Journal détaillé » ; tout pendant un test comspec_fb_fnc_debugTest).
    [_detail] call comspec_fb_fnc_logOn -> bool      (_detail : ligne de détail, false par défaut)
*/
params [["_detail", false, [false]]];
if (!isNil "comspec_fb_capture") exitWith { true };
(missionNamespace getVariable ["comspec_fb_debug", true]) && {!_detail || {missionNamespace getVariable ["comspec_fb_debugVerbose", false]}}
