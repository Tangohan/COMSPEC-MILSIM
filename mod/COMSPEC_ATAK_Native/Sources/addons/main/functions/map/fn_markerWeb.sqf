/*
    Envoie un marqueur du téléphone au web (Athena) par COMSPEC Link, sans dépendre de ses EH MarkerCreated
    (non posés tant qu'Overwatch ne voit pas de terminal, ou muets pendant ses imports).
    Params : [nom, supprimé]. Sans Overwatch : rien (le marqueur reste dans le jeu).
    Débit simulé (fn_netSend) : sans signal, l'envoi attend le retour du réseau ; seul le dernier état du marqueur part.
*/
params [["_name", ""], ["_deleted", false]];
// Mode « réseau ATAK uniquement » : le marqueur part aussi aux téléphones du canal (fn_markerNet), avec ou sans Athena.
if (_name isNotEqualTo "") then {
    [{ ["touch", _this] call comspec_atak_native_fnc_markerNet; }, [_name, _deleted], [0.5, 0] select _deleted] call CBA_fnc_waitAndExecute;
};
if (_name isEqualTo "" || {isNil "comspec_overwatch_connect_fnc_syncMapMarker"}) exitWith { false };
// Laisse le temps au type, à la couleur et au texte d'être posés (le dernier appel global diffuse l'état).
[{
    params ["_name", "_deleted"];
    if (!_deleted && {(markerShape _name) isEqualTo ""}) exitWith {};
    // Débit simulé : parti après la latence, ou en file sans signal (la clé ne garde que le dernier état du marqueur).
    [{
        params ["_name", "_deleted"];
        if (!_deleted && {(markerShape _name) isEqualTo ""}) exitWith {};
        private _ok = [_name, _deleted, true] call comspec_overwatch_connect_fnc_syncMapMarker;
        ["INFO", "MARKERS", format ["Web %1 %2 : %3%4", ["envoi", "suppression"] select _deleted, _name, ["refusé", "OK"] select (_ok isEqualTo true),
            ["", format [" (%1)", missionNamespace getVariable ["COMSPEC_LastMarkerSyncSkip", ""]]] select (_ok isNotEqualTo true)]] call comspec_atak_native_fnc_log;
        // Trait ou repère non parti au site : le joueur le voit (sinon rien ne dit pourquoi il manque sur Athena).
        if (_deleted) exitWith {};
        private _why = missionNamespace getVariable ["COMSPEC_LastMarkerSyncSkip", ""];
        private _msg = switch (true) do {
            case (_ok isNotEqualTo true && {_why isEqualTo "sync_off"}): { "Non envoyé au site : synchro des marqueurs coupée (réglages serveur)" };
            case (_ok isNotEqualTo true): { format [("Non envoyé au site (%1)") call comspec_atak_native_fnc_tr, [_why, ("erreur") call comspec_atak_native_fnc_tr] select (_why isEqualTo "")] };
            case (_why in ["queued_not_ready", "queued_tx"]): { "Liaison Athena pas prête : envoi au site en attente" };
            default { "" };
        };
        if (_msg isNotEqualTo "") then { ["WARNING", _msg, 4, 20] call comspec_atak_native_fnc_notify; };
    }, [_name, _deleted], "Marqueur", 1, 1, "mk:" + _name] call comspec_atak_native_fnc_netSend;
}, [_name, _deleted], [0.5, 0] select _deleted] call CBA_fnc_waitAndExecute;
true
