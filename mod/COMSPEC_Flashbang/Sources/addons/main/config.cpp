// COMSPEC Flashbang : grenades assourdissantes multi-détonations (1 à 9 flashs) et grenade d'entraînement DRILL,
// chacune en mèche standard (réglable) et en mèche à délai 3 s / 8 s.
// Mod indépendant : seule dépendance CBA_A3. ACE3, ACRE2, TFAR et ZEN sont détectés en jeu (compatibilité optionnelle).
#include "script_component.hpp"

// Variantes à délai d'une classe : FB_D2(COMSPEC_FB_Flash_,5) -> "COMSPEC_FB_Flash_5_D3", "COMSPEC_FB_Flash_5_D8".
#define FB_D2(P,V) QUOTE(P##V##_D3), QUOTE(P##V##_D8)

class CfgPatches {
    class comspec_flashbang_main {
        name = "COMSPEC Flashbang";
        author = "COMSPEC";
        url = "https://athena-comspec.eu/public";
        requiredVersion = 2.18;
        requiredAddons[] = {"cba_main", "A3_UI_F", "A3_Weapons_F", "A3_Weapons_F_Ammoboxes", "A3_Modules_F"};
        units[] = {"COMSPEC_FB_Box", "COMSPEC_FB_Box_Delay", "COMSPEC_FB_Module"};
        weapons[] = {};
        magazines[] = {
            "COMSPEC_FB_Flash_1", "COMSPEC_FB_Flash_2", "COMSPEC_FB_Flash_3", "COMSPEC_FB_Flash_4", "COMSPEC_FB_Flash_5",
            "COMSPEC_FB_Flash_6", "COMSPEC_FB_Flash_7", "COMSPEC_FB_Flash_8", "COMSPEC_FB_Flash_9", "COMSPEC_FB_Flash_50", "COMSPEC_FB_Drill",
            FB_D2(COMSPEC_FB_Flash_,1), FB_D2(COMSPEC_FB_Flash_,2), FB_D2(COMSPEC_FB_Flash_,3), FB_D2(COMSPEC_FB_Flash_,4),
            FB_D2(COMSPEC_FB_Flash_,5), FB_D2(COMSPEC_FB_Flash_,6), FB_D2(COMSPEC_FB_Flash_,7), FB_D2(COMSPEC_FB_Flash_,8),
            FB_D2(COMSPEC_FB_Flash_,9), FB_D2(COMSPEC_FB_Flash_,50), FB_D2(COMSPEC_FB_,Drill),
            "COMSPEC_FB_Flash_2_R", "COMSPEC_FB_Flash_3_R", "COMSPEC_FB_Flash_4_R", "COMSPEC_FB_Flash_5_R", "COMSPEC_FB_Flash_6_R", "COMSPEC_FB_Flash_7_R", "COMSPEC_FB_Flash_8_R", "COMSPEC_FB_Flash_9_R", "COMSPEC_FB_Flash_50_R"
        };
        ammo[] = {
            "COMSPEC_FB_Ammo_1", "COMSPEC_FB_Ammo_2", "COMSPEC_FB_Ammo_3", "COMSPEC_FB_Ammo_4", "COMSPEC_FB_Ammo_5",
            "COMSPEC_FB_Ammo_6", "COMSPEC_FB_Ammo_7", "COMSPEC_FB_Ammo_8", "COMSPEC_FB_Ammo_9", "COMSPEC_FB_Ammo_50", "COMSPEC_FB_Ammo_Drill",
            FB_D2(COMSPEC_FB_Ammo_,1), FB_D2(COMSPEC_FB_Ammo_,2), FB_D2(COMSPEC_FB_Ammo_,3), FB_D2(COMSPEC_FB_Ammo_,4),
            FB_D2(COMSPEC_FB_Ammo_,5), FB_D2(COMSPEC_FB_Ammo_,6), FB_D2(COMSPEC_FB_Ammo_,7), FB_D2(COMSPEC_FB_Ammo_,8),
            FB_D2(COMSPEC_FB_Ammo_,9), FB_D2(COMSPEC_FB_Ammo_,50), FB_D2(COMSPEC_FB_Ammo_,Drill),
            "COMSPEC_FB_Ammo_2_R", "COMSPEC_FB_Ammo_3_R", "COMSPEC_FB_Ammo_4_R", "COMSPEC_FB_Ammo_5_R", "COMSPEC_FB_Ammo_6_R", "COMSPEC_FB_Ammo_7_R", "COMSPEC_FB_Ammo_8_R", "COMSPEC_FB_Ammo_9_R", "COMSPEC_FB_Ammo_50_R"
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
#include "CfgSons.hpp"
#include "CfgAmmo.hpp"
#include "CfgMagazines.hpp"
#include "CfgWeapons.hpp"
#include "CfgVehicles.hpp"
#include "RscTitles.hpp"
