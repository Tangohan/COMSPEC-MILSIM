/*
    URL du portail Athena (réglage / profil / défaut). Pas d’identifiant de communauté.
*/
private _cleanSecret = {
    params [["_s", ""]];
    if (!(_s isEqualType "")) then { _s = format ["%1", _s]; };
    _s = trim _s;
    _s
};
private _url = [missionNamespace getVariable ["comspec_overwatch_api_url", ""]] call _cleanSecret;
if ((count _url) < 12) then {
    _url = [profileNamespace getVariable ["comspec_overwatch_saved_api_url", ""]] call _cleanSecret;
};
if ((count _url) < 12) then {
    _url = "https://athena.ttrd.fr/public";
};
_url
