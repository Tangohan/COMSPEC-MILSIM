/*
    comspec_fb_fnc_logOn : vrai si le journal de diagnostic écrit (réglage actif sur cette machine ou test en cours).
    call comspec_fb_fnc_logOn -> bool
*/
(missionNamespace getVariable ["comspec_fb_debug", false]) || {!isNil "comspec_fb_capture"}
