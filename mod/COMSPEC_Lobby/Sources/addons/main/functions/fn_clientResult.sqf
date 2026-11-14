/*
    CLIENT. Réponse du serveur au code envoyé (événement comspec_lobby_pinResult) : [accepté, essais restants].
    Accepté : le poste est à nous (missionNamespace COMSPEC_LOBBY_Verified). Refusé : la mission se termine pour ce joueur sur
    l'écran de bilan « Code du poste refusé » (CfgDebriefing) ; il revient à l'écran des rôles et peut ressaisir le code.
*/
params [["_ok", false, [true]], ["_left", 0, [0]]];
if (_ok) exitWith { missionNamespace setVariable ["COMSPEC_LOBBY_Verified", true]; };
uiNamespace setVariable ["COMSPEC_LOBBY_Pin", ["", ""]];
endMission "COMSPEC_Lobby_PinRefused";
