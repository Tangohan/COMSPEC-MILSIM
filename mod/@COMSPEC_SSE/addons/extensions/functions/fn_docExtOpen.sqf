/*
    Ouvre la fenêtre « SSE ROLEPLAY · Extensions » (module Zeus, bouton du menu Échap, menu COMSPEC SSE d'Eden).
    [] call comspec_sse_fnc_docExtOpen -> Boolean
*/
if (!hasInterface) exitWith { false };
["open"] call comspec_sse_fnc_docExtWindow
