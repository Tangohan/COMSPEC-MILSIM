/*
    COMSPEC Lobby : événements et gardes au démarrage de la mission.
      serveur : table des codes (pinLoad), contrôle d'un code (comspec_lobby_pinCheck), pose d'un code par Zeus (comspec_lobby_pinSet),
                garde des postes toutes les 5 s ;
      client  : envoi du code saisi dans l'écran des rôles, réponse du serveur, module Zeus (ZEN).
    L'écran des rôles lui-même ne passe pas par ici : voir RscLobby.hpp et functions\fn_lobbyOpen.sqf.
*/
if (isServer) then {
    [] call comspec_lobby_fnc_pinLoad;
    ["comspec_lobby_pinCheck", { _this call comspec_lobby_fnc_serverCheck }] call CBA_fnc_addEventHandler;
    ["comspec_lobby_pinSet", { _this call comspec_lobby_fnc_serverSet }] call CBA_fnc_addEventHandler;
    [{ [] call comspec_lobby_fnc_serverTick }, 5] call CBA_fnc_addPerFrameHandler;
};
if (hasInterface) then {
    ["comspec_lobby_pinResult", { _this call comspec_lobby_fnc_clientResult }] call CBA_fnc_addEventHandler;
    [] call comspec_lobby_fnc_clientCheck;
    [] call comspec_lobby_fnc_zenInit;
};
