/*
    Les tenues de la communauté chargées dans le panneau Tenues Athena ne sont plus « nouvelles » : appelée à la
    fermeture du panneau ou de l'arsenal. Identifiants posés par arsenalOverlayRefresh (COMSPEC_ArsenalSeenPending).
*/
private _ids = missionNamespace getVariable ["COMSPEC_ArsenalSeenPending", []];
if !(_ids isEqualType []) exitWith {};
if (_ids isEqualTo []) exitWith {};
missionNamespace setVariable ["COMSPEC_ArsenalSeenPending", [], false];
private _seen = profileNamespace getVariable ["COMSPEC_ArsenalSeenIds", []];
if !(_seen isEqualType []) then { _seen = []; };
private _all = _seen + (_ids - _seen);
// Borne : on garde les plus récents.
if ((count _all) > 2000) then { _all = _all select [(count _all) - 2000]; };
profileNamespace setVariable ["COMSPEC_ArsenalSeenIds", _all];
saveProfileNamespace;
