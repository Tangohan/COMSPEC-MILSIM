/*
    CLIENT. À l'arrivée du joueur sur son poste, envoie au serveur le code saisi dans l'écran des rôles (uiNamespace
    COMSPEC_LOBBY_Pin = [étiquette, code], posé par fn_lobbyOpen). Le serveur lit lui-même le poste du joueur et dit si
    le code est bon (comspec_lobby_pinResult). Rien n'est envoyé si le poste n'a pas d'étiquette. Un joueur sans code saisi
    envoie un code vide : le serveur décide si le poste en demande un.
    [] call comspec_lobby_fnc_clientCheck
*/
if (!hasInterface || {!(missionNamespace getVariable ["comspec_lobby_enabled", true])}) exitWith {};
[{ !isNull player && {alive player} }, {
    ([roleDescription player] call comspec_lobby_fnc_tagParse) params ["_tag"];
    if (_tag isEqualTo "") exitWith {};
    private _stored = uiNamespace getVariable ["COMSPEC_LOBBY_Pin", ["", ""]];
    private _pin = if ((toUpper (_stored param [0, ""])) isEqualTo (toUpper _tag)) then { _stored param [1, ""] } else { "" };
    ["comspec_lobby_pinCheck", [_pin, player]] call CBA_fnc_serverEvent;
}, [], 90] call CBA_fnc_waitUntilAndExecute;
