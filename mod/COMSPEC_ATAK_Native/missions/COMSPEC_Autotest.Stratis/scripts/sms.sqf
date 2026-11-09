// App SMS : ouverture, SMS reçu d'une unité simulée (liste, pastille), réponse (statut), lecture. Solo : pas de second joueur,
// l'expéditeur est simulé par un appel direct à fn_p2pReceive (sans objet expéditeur, comme une alerte du poste).
if !("sms" call AT_fnc_groupe) exitWith {};
["sms_app", {
    if (["comspec_atak_native_fnc_pageSms", "comspec_atak_native_fnc_p2pReceive", "comspec_atak_native_fnc_p2pSend", "comspec_atak_native_fnc_appBadge"] call AT_fnc_nil) exitWith {};
    private _peer = "Alpha 1-2 (simulé)";
    private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap];
    _data set ["p2p", (_data getOrDefault ["p2p", []]) select { (_x getOrDefault ["peer", ""]) isNotEqualTo _peer }];
    // L'app est déclarée avec son abréviation.
    private _app = ([] call comspec_atak_native_fnc_appList) select { (_x get "page") isEqualTo "SMS" };
    [(count _app) > 0, "app SMS absente de la liste des apps"] call AT_fnc_check;
    [(((_app param [0, createHashMap]) getOrDefault ["short", ""]) isNotEqualTo ""), "abréviation (short) de l'app SMS vide"] call AT_fnc_check;
    // Réception : conversation créée, non-lu compté par l'app SMS.
    [_peer, "Contact au nord, ca bouge", "10:00", "at-sms-1", objNull, "", true] call comspec_atak_native_fnc_p2pReceive;
    [(["SMS"] call comspec_atak_native_fnc_appBadge) >= 1, "pastille SMS : aucun non-lu après réception"] call AT_fnc_check;
    // Ouverture de l'app : liste des conversations, sans canaux.
    [(["SMS"] call AT_fnc_page), "page SMS non affichée"] call AT_fnc_check;
    [((call AT_fnc_etat) getOrDefault ["pageDrawn", ""]) isEqualTo "SMS", "page SMS non dessinée"] call AT_fnc_check;
    ["sms_liste"] call AT_fnc_shot;
    // Conversation ouverte : le SMS est lu.
    private _st = call AT_fnc_etat;
    _st set ["chatPeer", _peer]; _st set ["chatView", "CONV"];
    ["SMS"] call comspec_atak_native_fnc_pageRender;
    uiSleep 1;
    ["read", _peer] call comspec_atak_native_fnc_chatUnread;
    [(["SMS"] call comspec_atak_native_fnc_appBadge) isEqualTo 0, "non-lu SMS non remis à zéro à la lecture"] call AT_fnc_check;
    // Réponse : le SMS sortant existe et porte un statut (expéditeur absent du serveur : « non transmis »).
    [_peer, "Bien reçu, j'arrive"] call comspec_atak_native_fnc_p2pSend;
    private _out = ((_data getOrDefault ["p2p", []]) select { (_x getOrDefault ["peer", ""]) isEqualTo _peer && {(_x getOrDefault ["dir", ""]) isEqualTo "out"} });
    [(count _out) > 0, "SMS sortant absent de la conversation"] call AT_fnc_check;
    [((_out param [0, createHashMap]) getOrDefault ["status", ""]) isNotEqualTo "", "statut du SMS sortant vide"] call AT_fnc_check;
    ["sms_conversation"] call AT_fnc_shot;
    _data set ["p2p", (_data getOrDefault ["p2p", []]) select { (_x getOrDefault ["peer", ""]) isNotEqualTo _peer }];
    call AT_fnc_done;
}, 60] call AT_fnc_run;
