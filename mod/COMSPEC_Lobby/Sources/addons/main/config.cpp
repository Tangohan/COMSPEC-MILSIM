// COMSPEC Lobby : écran de sélection des rôles refait (RscDisplayMultiplayerSetup). Le système de postes d'Arma reste celui du jeu
// (liste des rôles, camps, pool de joueurs, paramètres de mission, verrou, expulsion : tous les contrôles d'origine et leurs
// identifiants sont gardés). Le mod ajoute le style, le dossier de l'opérateur et le code du poste, vérifié par le serveur.
// Dépendance : CBA_A3. ACE3, ZEN et COMSPEC ATAK sont détectés (compatibilité optionnelle).
#include "script_component.hpp"

class CfgPatches {
    class comspec_lobby_main {
        name = "COMSPEC Lobby";
        author = "COMSPEC";
        url = "https://athena-comspec.eu/public";
        requiredVersion = 2.18;
        requiredAddons[] = {"cba_main", "A3_UI_F"};
        units[] = {};
        weapons[] = {};
        magazines[] = {};
        ammo[] = {};
        version = VERSION;
        versionStr = QUOTE(VERSION_STR);
        versionAr[] = {VERSION_AR};
    };
};

// Fin de partie quand le code du poste est refusé : écran de bilan, jamais une fenêtre d'alerte.
class CfgDebriefing {
    class COMSPEC_Lobby_PinRefused {
        title = "$STR_COMSPEC_LOBBY_PinTitle";
        subtitle = "$STR_COMSPEC_LOBBY_PinSubtitle";
        description = "$STR_COMSPEC_LOBBY_PinDescription";
        pictureBackground = "";
        picture = "b_inf";
        pictureColor[] = {0.94, 0.65, 0.23, 1};
    };
};

#include "CfgEventHandlers.hpp"
#include "CfgFunctions.hpp"
#include "RscLobby.hpp"
