/*
    Réglages du téléphone ATAK du joueur, envoyés depuis COMSPEC Athena (page « Mon téléphone ATAK »).
    Params : [id, cmd, réf, args (HashMap)]
      "prefs" : mute = types coupés séparés par "," (INFO, SUCCESS, WARNING, ERROR, MESSAGE, TACTICAL),
                silent = 0|1, banners = 0|1, toast = secondes (0 = durée choisie par chaque notification) ;
      "read"  : tout marquer lu (compteur du téléphone remis à zéro).
    Variables profileNamespace (lues par comspec_atak_native_fnc_notify du téléphone) :
      COMSPEC_ATAK_Notifications (BOOL, existant)   bandeaux de notification affichés ;
      COMSPEC_ATAK_Silent        (BOOL, existant)   mode discrétion, aucun son ;
      COMSPEC_ATAK_NotifMuted    (ARRAY de STRING)  types sans bandeau ni son (toujours gardés dans l'historique) ;
      COMSPEC_ATAK_NotifToastSec (NUMBER, 0 à 30)   durée imposée des bandeaux, 0 = celle de l'appelant.
    Retour : true (la commande visait ce joueur).
*/
params [["_id", ""], ["_cmd", ""], ["_ref", ""], ["_a", createHashMap]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
switch (_cmd) do {
    case "prefs": {
        private _types = ["INFO", "SUCCESS", "WARNING", "ERROR", "MESSAGE", "TACTICAL"];
        private _muted = ((_a getOrDefault ["mute", ""]) splitString ",") apply { toUpper _x } select { _x in _types };
        profileNamespace setVariable ["COMSPEC_ATAK_NotifMuted", _muted];
        profileNamespace setVariable ["COMSPEC_ATAK_Silent", (_a getOrDefault ["silent", "0"]) isEqualTo "1"];
        profileNamespace setVariable ["COMSPEC_ATAK_Notifications", (_a getOrDefault ["banners", "1"]) isEqualTo "1"];
        profileNamespace setVariable ["COMSPEC_ATAK_NotifToastSec", 0 max (parseNumber (_a getOrDefault ["toast", "0"])) min 30];
        saveProfileNamespace;
        if (!isNil "comspec_atak_native_fnc_notify") then { ["INFO", "Préférences de notifications mises à jour depuis Athena", 3, 20] call comspec_atak_native_fnc_notify; };
        [_id, "done", "Préférences appliquées au téléphone"] call comspec_overwatch_connect_fnc_webCmdAck;
    };
    case "read": {
        _s set ["notifUnread", 0];
        [_id, "done", "Notifications marquées lues sur le téléphone"] call comspec_overwatch_connect_fnc_webCmdAck;
    };
    default { [_id, "failed", "Réglage inconnu du téléphone"] call comspec_overwatch_connect_fnc_webCmdAck; };
};
true
