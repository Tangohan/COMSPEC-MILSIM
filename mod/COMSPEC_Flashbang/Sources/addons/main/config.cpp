// COMSPEC Flashbang : grenades assourdissantes multi-détonations (1 à 9 flashs) et grenade d'entraînement DRILL.
// Mod indépendant : seule dépendance CBA_A3. ACE3, ACRE2, TFAR et ZEN sont détectés en jeu (compatibilité optionnelle).
#include "script_component.hpp"

class CfgPatches {
    class comspec_flashbang_main {
        name = "COMSPEC Flashbang";
        author = "COMSPEC";
        url = "https://athena-comspec.eu/public";
        requiredVersion = 2.18;
        requiredAddons[] = {"cba_main", "A3_UI_F", "A3_Weapons_F", "A3_Weapons_F_Ammoboxes", "A3_Modules_F"};
        units[] = {"COMSPEC_FB_Box", "COMSPEC_FB_Module"};
        weapons[] = {};
        magazines[] = {
            "COMSPEC_FB_Flash_1", "COMSPEC_FB_Flash_2", "COMSPEC_FB_Flash_3", "COMSPEC_FB_Flash_4", "COMSPEC_FB_Flash_5",
            "COMSPEC_FB_Flash_6", "COMSPEC_FB_Flash_7", "COMSPEC_FB_Flash_8", "COMSPEC_FB_Flash_9", "COMSPEC_FB_Drill"
        };
        ammo[] = {
            "COMSPEC_FB_Ammo_1", "COMSPEC_FB_Ammo_2", "COMSPEC_FB_Ammo_3", "COMSPEC_FB_Ammo_4", "COMSPEC_FB_Ammo_5",
            "COMSPEC_FB_Ammo_6", "COMSPEC_FB_Ammo_7", "COMSPEC_FB_Ammo_8", "COMSPEC_FB_Ammo_9", "COMSPEC_FB_Ammo_Drill"
        };
        version = VERSION;
        versionStr = QUOTE(VERSION_STR);
        versionAr[] = {VERSION_AR};
    };
};

// Projectile sans fumée : la classe d'effet vide remplace la fumée de SmokeShell.
class COMSPEC_FB_NoEffect {};

#include "CfgEventHandlers.hpp"
#include "CfgFunctions.hpp"
#include "CfgRemoteExec.hpp"
#include "CfgSons.hpp"
#include "CfgAmmo.hpp"
#include "CfgMagazines.hpp"
#include "CfgWeapons.hpp"
#include "CfgVehicles.hpp"
#include "RscTitles.hpp"
