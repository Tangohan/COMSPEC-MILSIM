/*
    Miroir des notifications du téléphone ATAK natif vers COMSPEC Athena (page « Mon téléphone ATAK »).
    Appelé toutes les ~10 s (fn_startSyncLoops). Lit l'historique du téléphone (uiNamespace COMSPEC_ATAK_State
    "notifHistory", tenu par comspec_atak_native_fnc_notify) et n'envoie que les nouvelles entrées :
    chaque entrée vue est marquée (clé "_ws" ajoutée à sa HashMap, ignorée par le téléphone).
    Envoi par le POST des charges (DLL SubmitExplosiveTimer, clé atak_notifs ; steam_uid ajouté par la DLL).
    Battement toutes les 60 s pour le compteur non lu, même sans nouvelle notification.
    Retour : nombre de notifications envoyées.
*/
if (!hasInterface || {isNull player}) exitWith { 0 };
if (isNil "comspec_atak_native_fnc_notify") exitWith { 0 };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { 0 };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { 0 };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { 0 };

private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _hist = _s getOrDefault ["notifHistory", []];
if (!(_hist isEqualType [])) exitWith { 0 };

// Première passe de la session : l'historique déjà présent (mission précédente, uiNamespace) n'est pas renvoyé.
private _first = isNil "COMSPEC_WebNotifMirrorInit";
COMSPEC_WebNotifMirrorInit = true;

private _new = [];
{
    if ((_x isEqualType createHashMap) && {!(_x getOrDefault ["_ws", false])}) then {
        _x set ["_ws", true];
        if (!_first) then {
            private _m = _x getOrDefault ["message", ""];
            if (!(_m isEqualType "")) then { _m = str _m; };
            // Texte brut : balises du texte structuré et retours ligne retirés.
            _m = ((_m regexReplace ["<[^>]*>", ""]) splitString (toString [10, 13, 9])) joinString " ";
            if ((count _m) > 300) then { _m = _m select [0, 300]; };
            if (_m isNotEqualTo "") then {
                _new pushBack createHashMapFromArray [["t", _x getOrDefault ["type", "INFO"]], ["m", _m], ["h", _x getOrDefault ["time", ""]]];
            };
        };
    };
} forEach _hist;

private _unread = _s getOrDefault ["notifUnread", 0];
private _lastBeat = missionNamespace getVariable ["COMSPEC_WebNotifBeatAt", -1e9];
if ((count _new) isEqualTo 0 && {diag_tickTime - _lastBeat < 60}) exitWith { 0 };
missionNamespace setVariable ["COMSPEC_WebNotifBeatAt", diag_tickTime];

// Au plus 15 par envoi (taille du JSON) : en rafale, seules les 15 plus récentes partent.
if ((count _new) > 15) then { _new = _new select [(count _new) - 15, 15]; };
private _json = [createHashMapFromArray [["atak_notifs", _new], ["atak_notif_unread", _unread]]] call comspec_overwatch_connect_fnc_hashMapToJson;
"COMSPECExtension" callExtension ["SubmitExplosiveTimer", [_json]];
count _new
