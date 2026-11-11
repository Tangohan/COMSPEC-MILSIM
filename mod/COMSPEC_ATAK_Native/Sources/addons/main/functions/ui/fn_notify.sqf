/*
    Notification du téléphone : toast quelques secondes, gardée dans le centre de notifications (60 dernières).
    Params : [type (INFO, SUCCESS, WARNING, ERROR, MESSAGE, TACTICAL), texte, durée en s, priorité, page de l'app à ouvrir au clic,
              mode ("" normal | "vibrate" vibreur seul)]
    Mode « vibrate » (3.0.2, alerte discrète : contrôle d'identité, approche furtive…) : la coque vibre sans aucun son
    (fn_vibrate [true]), aucun toast, rien sur l'écran de verrouillage ni sur la montre ; la notification est seulement
    rangée au centre de notifications (marquée « discreet ») et la cloche passe en non lue. Exemple :
    ["MESSAGE", "Contrôle : papiers signalés", 0, 10, "IDCHECK", "vibrate"] call comspec_atak_native_fnc_notify
    Centre : un texte identique déjà présent remonte en tête avec un compteur (« ×3 ») au lieu d'être empilé ; chaque
    entrée garde un identifiant (fermeture une par une) et l'instant de réception (« il y a 3 min »).
    File des toasts (état « notifications ») : un seul affiché à la fois (fn_notificationsRender) ; la durée ne court
    qu'à partir de son affichage, et un toast qui attend depuis plus de 30 s est abandonné (il reste dans le centre).
*/
params [["_type","INFO"],["_message",""],["_duration",5],["_priority",10],["_app",""],["_mode",""]];
private _discreet = (toLower _mode) in ["vibrate", "vibreur", "silent"];
if (_message isEqualTo "" || {missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false]}) exitWith {false};
// TOC sans téléphone (4.10.0) : téléphone rangé ou absent, le bandeau ne sera pas dessiné. Le retour va là où le joueur
// regarde : ligne d'état du PC du poste, ligne du Zeus, boîte du TOC (fn_tocInbox « feedback »). Jamais d'alerte du jeu.
if (hasInterface && {!_discreet} && {isNull ([] call comspec_atak_native_fnc_display)}) then { ["feedback", [_type, _message, _app]] call comspec_atak_native_fnc_tocInbox; };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _hist = _s getOrDefault ["notifHistory", []];
private _hid = (_s getOrDefault ["notifHistSeq", 0]) + 1;
_s set ["notifHistSeq", _hid];
private _same = _hist findIf { (_x getOrDefault ["message", ""]) isEqualTo _message && {(_x getOrDefault ["type", ""]) isEqualTo (toUpper _type)} };
private _times = 1;
if (_same >= 0) then { _times = ((_hist select _same) getOrDefault ["count", 1]) + 1; _hist deleteAt _same; };
_hist pushBack createHashMapFromArray [["id", _hid], ["type", toUpper _type], ["message", _message], ["app", toUpper _app], ["count", _times],
    ["time", (["now"] call comspec_atak_native_fnc_clockFmt)], ["tick", diag_tickTime], ["discreet", _discreet]];
while {count _hist > 60} do { _hist deleteAt 0; };
_s set ["notifHistory", _hist];
_s set ["notifUnread", (_s getOrDefault ["notifUnread", 0]) + 1];
if ((_s getOrDefault ["activePage", ""]) isEqualTo "NOTIFS") then { [{ ["NOTIFS"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame; };
// Vibreur seul : la coque tremble sans son, aucun bandeau (le centre de notifications garde le texte).
if (_discreet) exitWith { [true] call comspec_atak_native_fnc_vibrate; true };
if !(profileNamespace getVariable ["COMSPEC_ATAK_Notifications",true]) exitWith {false};
// Préférences réglées depuis le web (Mon ATAK) : types muets (historique seulement), durée imposée des bandeaux.
private _muted = profileNamespace getVariable ["COMSPEC_ATAK_NotifMuted", []];
if ((_muted isEqualType []) && {(toUpper _type) in _muted}) exitWith {false};
private _forced = profileNamespace getVariable ["COMSPEC_ATAK_NotifToastSec", 0];
if ((_forced isEqualType 0) && {_forced > 0}) then { _duration = _forced; };
private _q = _s getOrDefault ["notifications",[]];
// Même texte déjà en file : on le remet en avant au lieu de l'empiler (alertes répétées chaque seconde).
private _dup = _q findIf { (_x getOrDefault ["message", ""]) isEqualTo _message };
if (_dup >= 0) then { _q deleteAt _dup; };
private _id = (_s getOrDefault ["notifSeq", 0]) + 1;
_s set ["notifSeq", _id];
_q pushBack createHashMapFromArray [
    ["id", _id], ["type", toUpper _type], ["message", _message], ["priority", _priority],
    ["duration", (_duration max 2) min 12], ["created", diag_tickTime], ["expires", -1]
];
while {count _q > 12} do {_q deleteAt 0;};
_s set ["notifications",_q];
[] call comspec_atak_native_fnc_notificationsRender;
true
