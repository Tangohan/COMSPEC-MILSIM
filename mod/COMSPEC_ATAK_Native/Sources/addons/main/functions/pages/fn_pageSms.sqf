/*
    App SMS : conversations directes de téléphone à téléphone, avec l'interface de Messages (fn_pageChat, même liste,
    mêmes bulles, même saisie, mêmes accusés Envoi… / Distribué / Lu, même file d'attente sans réseau).
    Seule différence : pas de canaux TOC / Athena, et le bandeau d'état ne parle que du réseau du téléphone.
    Les SMS partent par fn_p2pSend (réseau mobile, tours relais, MPU5 si liaison réaliste) et arrivent par fn_p2pReceive.
    Entrée dans l'app depuis une autre page : on repart de la liste des conversations.
*/
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if ((_s getOrDefault ["pagePrev", ""]) isNotEqualTo "SMS") then {
    _s set ["chatView", "LIST"];
    _s set ["chatPick", false];
    _s set ["chatWiki", false];
    _s set ["chatDelArm", ""];
};
[] call comspec_atak_native_fnc_pageChat
