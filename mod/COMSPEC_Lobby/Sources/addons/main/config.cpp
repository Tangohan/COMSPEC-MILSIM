// COMSPEC Lobby : écran de sélection des rôles refait (RscDisplayMultiplayerSetup). Le système de postes d'Arma reste celui du jeu
// (liste des rôles, camps, pool de joueurs, paramètres de mission, verrou, expulsion : tous les contrôles d'origine et leurs
// identifiants sont gardés). Le mod ajoute le style et le dossier de l'opérateur.
// Aucune dépendance autre que le jeu.
#include "script_component.hpp"

class CfgPatches {
    class comspec_lobby_main {
        name = "COMSPEC Lobby";
        author = "COMSPEC";
        url = "https://athena-comspec.eu/public";
        requiredVersion = 2.18;
        requiredAddons[] = {"A3_UI_F"};
        units[] = {};
        weapons[] = {};
        magazines[] = {};
        ammo[] = {};
        version = VERSION;
        versionStr = QUOTE(VERSION_STR);
        versionAr[] = {VERSION_AR};
    };
};

#include "RscLobby.hpp"
