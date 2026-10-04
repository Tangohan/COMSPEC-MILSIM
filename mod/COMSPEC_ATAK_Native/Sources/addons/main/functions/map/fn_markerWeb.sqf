/*
    Envoie un marqueur du téléphone au web (Athena) par COMSPEC Link, sans dépendre de ses EH MarkerCreated
    (non posés tant qu'Overwatch ne voit pas de terminal, ou muets pendant ses imports).
    Params : [nom, supprimé]. Sans Overwatch : rien (le marqueur reste dans le jeu).
*/
params [["_name", ""], ["_deleted", false]];
if (_name isEqualTo "" || {isNil "comspec_overwatch_connect_fnc_syncMapMarker"}) exitWith { false };
// Laisse le temps au type, à la couleur et au texte d'être posés (le dernier appel global diffuse l'état).
[{
    params ["_name", "_deleted"];
    if (!_deleted && {(markerShape _name) isEqualTo ""}) exitWith {};
    private _ok = [_name, _deleted, true] call comspec_overwatch_connect_fnc_syncMapMarker;
    ["INFO", "MARKERS", format ["Web %1 %2 : %3%4", ["envoi", "suppression"] select _deleted, _name, ["refusé", "OK"] select (_ok isEqualTo true),
        ["", format [" (%1)", missionNamespace getVariable ["COMSPEC_LastMarkerSyncSkip", ""]]] select (_ok isNotEqualTo true)]] call comspec_atak_native_fnc_log;
}, [_name, _deleted], [0.5, 0] select _deleted] call CBA_fnc_waitAndExecute;
true
